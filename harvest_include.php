<?php

// Connect to MySQL Server > v5.7
$mysqli = new mysqli($_GLOBALS["myhost"], $_GLOBALS["myuser"], $_GLOBALS["mypassword"], $_GLOBALS["mydbase"], $_GLOBALS["myport"]);

if (mysqli_connect_errno()) {
    printf("Connect failed: %s\n", mysqli_connect_error());
    exit();
}
$mysqli->set_charset("utf8");

// Connect to Sierra Postgres server
$pgconn_string = "host='" . $_GLOBALS["pghost"] . "' port='" . $_GLOBALS["pgport"] . "' dbname='" . $_GLOBALS["pgdbase"] . "' user='" . $_GLOBALS["pguser"] . "' password='" . $_GLOBALS["pgpassword"] . "' sslmode='" . $_GLOBALS["pgsslmode"] . "' connect_timeout='" . $_GLOBALS["pgtimeout"] . "'";
$pgconn = pg_connect($pgconn_string);
$stat = pg_connection_status($pgconn);
  if ($stat === PGSQL_CONNECTION_OK) {
//      echo 'Connection status ok'; 
  } else {
      echo 'Connection status bad';
  }

 // Build array of locations.  This is very static, so avoid the costs of the joins
$query = "SELECT 
  location.id as id, 
  location.code as locationcode, 
  location_name.name as location, 
  location.branch_code_num as branchcode, 
  branch_name.name as branch
FROM 
  sierra_view.location, 
  sierra_view.location_name, 
  sierra_view.branch, 
  sierra_view.branch_name
WHERE 
  location.branch_code_num = branch.code_num AND
  location_name.location_id = location.id AND
  branch_name.branch_id = branch.id";

$result = pg_query($pgconn, $query);
if (!$result) {
	echo "nothing to process\n";
	exit;
}
while ($row = pg_fetch_array($result)) {
	$_GLOBALS["location"][$row["locationcode"]]["location"] = $row["location"];
	$_GLOBALS["location"][$row["locationcode"]]["branchcode"] = $row["branchcode"];
	$_GLOBALS["location"][$row["locationcode"]]["branch"] = $row["branch"];	
}

 

/**
 * Handle errors thrown by old PHP function.  Likely no longer needed
 */
 
function errHandle($errNo, $errStr, $errFile, $errLine) {
    $msg = "$errStr in $errFile on line $errLine";
    if ($errNo == E_NOTICE || $errNo == E_WARNING) {
        throw new ErrorException($msg, $errNo);
    } else {
        echo $msg;
    }
}

/**
 * Email to the log admin
 *
 * @example
 *    maillog("person@email.com", "Here is the log");
 */

 function maillog ($mailto, $mailsubject) {
	error_reporting(0);
	mail(stripslashes($mailto), stripslashes($mailsubject), $msg);
}

/**
 * Scan for new or updated Bib Records.
 *
 * If no bib_record_id is given, assume this is an initial scan and start harvesting from the beginning or last record
 * This process with also scan associated 6xx subfield b subjects as a subquery
 *
 * @example
 *    bibscan("1234567890");
 */
function bibscan ($bib_record_id = NULL) {
	GLOBAL $_GLOBALS, $mysqli, $pgconn;

	if (isset($bib_record_id)) {
		$recordstoscan = "= " . $bib_record_id;
	} else {
		$sql = "SELECT IFNULL(MAX(record_id), 0) AS lastrecord FROM  `" . $_GLOBALS["bibtable"] . "`"; 
		$query = $mysqli->query($sql);
		$row = $query->fetch_array(MYSQLI_ASSOC);
		$recordstoscan = ">= '" . $row["lastrecord"] . "'";
		
		if ($row["lastrecord"] == 0) {
			markscan('T');
		}
	}

	$harvestquery = "SELECT
		record_id, 
		bmd.record_num AS recordkey,
		b.language_code AS language,
		COALESCE(b.cataloging_date_gmt::date, '1900-01-01') AS catdate,
		bmd.record_last_updated_gmt::timestamp without time zone AS last_edited_gmt,
		bp.material_code AS mattype,
		b.bcode3 as bibstatus,
		b.country_code as country,
		btrim(bp.best_title, ',./') AS title,
		btrim((
			SELECT content
			FROM sierra_view.subfield
			WHERE record_id = b.record_id AND marc_tag like '100' and field_type_code = 'a' and tag = 'a'
			LIMIT 1
		), ',./') as author,
		(
			SELECT content
			FROM sierra_view.subfield
			WHERE record_id = b.record_id AND (marc_tag like '092' or marc_tag like '082')
			LIMIT 1
		) AS callno,
		(
			SELECT SUBSTRING(field_content FROM '\d+')
			FROM sierra_view.varfield
			WHERE record_id = b.record_id AND varfield_type_code = 'i'
			ORDER BY occ_num LIMIT 1
		) AS isbnissn,
		(
			SELECT content
			FROM sierra_view.subfield
			WHERE (record_id = b.record_id) AND marc_tag = '856' and tag = 'u' LIMIT 1
		) AS exturl,
		array_to_string(
			array(
				SELECT marc_tag || '<field-delimiter>' || tag || '<field-delimiter>' || btrim(content, ',./') as subfield
				FROM sierra_view.subfield
				WHERE (record_id = b.record_id) AND marc_tag like '6__' and field_type_code = 'd'
			), '<subject-delimiter>'
		) AS subject
	FROM
		sierra_view.bib_record AS b
			JOIN sierra_view.record_metadata AS bmd
				ON ( bmd.id = b.record_id ) 	
			JOIN sierra_view.bib_record_property AS bp
				ON ( bp.bib_record_id = b.record_id )
	WHERE 
		b.record_id " . $recordstoscan . " 
	ORDER BY 
		b.record_id 
	LIMIT " . $_GLOBALS["scanlimit"];

//		echo $harvestquery;
	$result = pg_query($pgconn, $harvestquery);
	if (!$result) {
		echo "Nothing to process\n";
		exit;
	}
	while ($bibrecord = pg_fetch_array($result, NULL, PGSQL_ASSOC)) {
		switch ($bibrecord["mattype"]) {
			case "b":
			case "v":
				$bibrecord["coverimage_src"] = $_GLOBALS["defaultdvdcover"]["coverimage_src"];
				$bibrecord["coverimage_sizex"] = $_GLOBALS["defaultdvdcover"]["coverimage_sizex"];
				$bibrecord["coverimage_sizey"] = $_GLOBALS["defaultdvdcover"]["coverimage_sizey"];
				break;
			case "a":
			case "t":
			case "x":
				$bibrecord["coverimage_src"] = $_GLOBALS["defaultcdcover"]["coverimage_src"];
				$bibrecord["coverimage_sizex"] = $_GLOBALS["defaultcdcover"]["coverimage_sizex"];
				$bibrecord["coverimage_sizey"] = $_GLOBALS["defaultcdcover"]["coverimage_sizey"];
				break;
			default :
				$bibrecord["coverimage_src"] = $_GLOBALS["defaultbookcover"]["coverimage_src"];
				$bibrecord["coverimage_sizex"] = $_GLOBALS["defaultbookcover"]["coverimage_sizex"];
				$bibrecord["coverimage_sizey"] = $_GLOBALS["defaultbookcover"]["coverimage_sizey"];
				break;
		}

		$bibrecord["modifydate"] = Date($_GLOBALS["timestampformat"]);
		$bibrecord["coverscan_count"] = 0;
			
//			Create an array of MARC 6xx subjects
			
		if ($bibrecord['subject'] != NULL) {
// 650||a||Mimms, John A.|||650||q||(John Archibald),|||650||d||1921-|||650||a||World War, 1939-1945|||650||v||Personal narratives, Canadian|||650||a||World War, 1939-1945|||650||x||Humor
			unset($bibsubjects);
			$bibsubjects = explode("<subject-delimiter>", $bibrecord['subject']);
// 650||a||Mimms, John A.
// 650||q||(John Archibald),
// 650||d||1921-
// 650||a||World War, 1939-1945
// 650||v||Personal narratives, Canadian
// 650||a||World War, 1939-1945
// 650||x||Humor
			$escaped_values = array_map(array($mysqli, 'real_escape_string'), $bibsubjects);
			foreach ($escaped_values as $key => $value)	$explodedbibsubjects[$key] = explode("<field-delimiter>", $value);
// explodedbibsubjects[0][0] = 650
// explodedbibsubjects[0][1] = a
// explodedbibsubjects[0][2] = Mimms, John A.
			unset($escaped_values);


			foreach ($explodedbibsubjects as $key => $value) {
// must have MARC Code, subfield and value
				if (isset($explodedbibsubjects[$key][0]) && isset($explodedbibsubjects[$key][1]) && isset($explodedbibsubjects[$key][2])) {
					$query = "INSERT INTO `" . $_GLOBALS["subjecttable"] . "` (marctype, subject, subfield) VALUES (" . $explodedbibsubjects[$key][0] . ", \"" . $explodedbibsubjects[$key][2] . "\", \"" . $explodedbibsubjects[$key][1] . "\")
								ON DUPLICATE KEY UPDATE subjectuid=LAST_INSERT_ID(subjectuid)";
					$mysqli->query($query) or die('Invalid query: ' . $mysqli->error . "\n" . $query);
// build array to limit the DB calls
					$subjectlinks[] = "(" . $bibrecord["record_id"] . ", " . $mysqli->insert_id . ")";
				}
			}
			unset($explodedbibsubjects);
				
// delete old links because we can't trust they are valid anymore and prevents orphan linkages (might be solved with foreign keys in the future)
			$query = "delete from `" . $_GLOBALS["bibsubjectlinktable"] . "` where `bib_record_id` = " . $bibrecord["record_id"];
			$mysqli->query($query) or die('Invalid query: ' . $mysqli->error);
// dump array of new linkages, making sure values are unique
			$query = "INSERT INTO `" . $_GLOBALS["bibsubjectlinktable"]  . "` (`bib_record_id`, `subjectuid`) VALUES " . implode(",", array_unique($subjectlinks));
			$mysqli->query($query) or die('Invalid query: ' . $mysqli->error);
		}
		unset($subjectlinks);
		unset($bibrecord['subject']); // Not a field in the offline table

// Save Bib Record
//echo "Save:" . $bibrecord["record_id"] . "\n";
	$columns = implode(", ",array_keys($bibrecord));
	$escaped_values = array_map(array($mysqli, 'real_escape_string'), $bibrecord);
	foreach ($escaped_values as $key=>$value) $escaped_values[$key] = "'".$value."'";
		$values  = implode(", ", $escaped_values);
		$query = "REPLACE INTO `" . $_GLOBALS["bibtable"] . "` ($columns) VALUES ($values)";
		$mysqli->query($query) or die('Invalid query: ' . $mysqli->error);
	}
	if (IS_NULL($bib_record_id)) echo progress("sierra_view.bib_record", $_GLOBALS["bibtable"]);
	return;
}
	
/**
 * Scan for new or updated Item Records.
 *
 * If no item_record_id is given, assume this is an initial scan and start harvesting from the beginning or last record
 *
 * @example
 *    itemscan("1234567890");
 */
function itemscan ($item_record_id = NULL) {
	GLOBAL $_GLOBALS, $mysqli, $pgconn;

	if (isset($item_record_id)) {
		$recordstoscan = "= '" . $item_record_id ."'";
	} else {
		$sql = "SELECT IFNULL(MAX(record_id), 0) AS lastrecord FROM  `" . $_GLOBALS["itemtable"] . "`"; 
		$query = $mysqli->query($sql);
		$row = $query->fetch_array(MYSQLI_ASSOC);
		$recordstoscan = ">= '" . $row["lastrecord"] . "'";

		if ($row["lastrecord"] == 0) {
			markscan('T');
		}
	}

	$query = "
		SELECT
			item_view.id as record_id,
			COALESCE(item_record_property.call_number_norm, NULL) AS callno,
			COALESCE(item_record_property.barcode, NULL) AS barcode,
			COALESCE(item_view.location_code, NULL) AS locationcode,
			COALESCE(item_view.opac_message_code, NULL) AS shelf,
			item_view.price AS price,
			item_view.item_status_code AS itemstatus,
			item_view.record_num AS itemuid,
			item_view.itype_code_num AS itype,
			item_view.icode2,
			item_view.icode1,
			item_view.checkout_total,
			item_view.renewal_total,
			item_view.last_year_to_date_checkout_total AS lastytd_checkout,
			item_view.year_to_date_checkout_total AS ytd_checkout,
			COALESCE(item_view.inventory_gmt::timestamp without time zone, '1900-01-01 00:00:00') as inventory_gmt,
			bib_record_item_record_link.bib_record_id AS bib_record_id,
			item_view.item_message_code,
			COALESCE(record_metadata.record_last_updated_gmt::timestamp without time zone, '1900-01-01 00:00:00') AS last_edited_gmt
		FROM
			sierra_view.bib_record_item_record_link,
			sierra_view.item_record_property,
			sierra_view.item_view,
			sierra_view.record_metadata
		WHERE
			bib_record_item_record_link.item_record_id = item_view.id AND
			item_record_property.item_record_id = item_view.id AND
			record_metadata.record_num = item_view.record_num AND
			record_metadata.record_type_code = 'i'  AND
			item_view.id " . $recordstoscan . " 
		ORDER BY item_view.id 
		LIMIT " . $_GLOBALS["scanlimit"];
	$result = pg_query($pgconn, $query);
	unset($item);

	$item = array();
	while ($item = pg_fetch_array($result, NULL, PGSQL_ASSOC)) {

		$item["branchcode"] = $item["locationcode"] != NULL ? $_GLOBALS["location"][$item["locationcode"]]["branchcode"] : NULL;

		$columns = implode(", ",array_keys($item));
		$escaped_values = array_map(array($mysqli, 'real_escape_string'), $item);
		foreach ($escaped_values as $key => $value) $escaped_values[$key] = "'" . $value . "'";
		$values  = implode(", ", $escaped_values);
		$query = "REPLACE INTO `" . $_GLOBALS["itemtable"] . "` ($columns) VALUES ($values)";
		$mysqli->query($query) or die('Record ID:' . $item["record_id"] . ' Invalid query: ' . $mysqli->error);
	}

	if (IS_NULL($item_record_id)) echo progress("sierra_view.item_view", $_GLOBALS["itemtable"]);

	return;
}

/**
 * Look for updates to Bibs and Items
 *
 * Records are pulled from the record_metadata table which includes deleted records. All record types are in this table
 * Scan all records created since the timestamp in the lastscan value from the markers table.
 *
 * @example
 *    update();
 */
function update() {
	GLOBAL $_GLOBALS, $mysqli, $pgconn;
	
	$sql = "SELECT markers.fieldvalue AS lastscan FROM `markers` where markers.fieldname = 'lastscan'"; 
	$query = $mysqli->query($sql);
	$myresult = $query->fetch_array(MYSQLI_ASSOC);
	if (!$myresult) {
		echo "No records have been scanned yet. Run the either bibscan or itemscan first\n";
		exit;
	}
	// no limit on this query, shouldn't be a problem as there shouldn't be too many changes since first or last scan
	$query = "SELECT rm.id as id, rm.record_type_code AS record_type_code, rm.record_num AS record_num, rm.deletion_date_gmt as deletion_date_gmt, rm.record_last_updated_gmt AS record_last_updated_gmt  
				FROM sierra_view.record_metadata AS rm WHERE rm.record_type_code IN ('b', 'i') AND (rm.deletion_date_gmt > '" . Date("Y-m-d", $myresult["lastscan"]) . "' OR rm.record_last_updated_gmt > to_timestamp('" . $myresult["lastscan"] . "')) ORDER BY rm.record_last_updated_gmt";
	$pgresult = pg_query($pgconn, $query);
	if (!$pgresult) {
		echo "An error occurred.\n";
		exit;
	}
	$counter = array("item" => array("deleted" => 0, "updated" => 0), "bib" => array("deleted" => 0, "updated" => 0)); // TODO - This should be changed to allow counters of all record types deleted

	while ($row = pg_fetch_array($pgresult)) {
		$records[$row["id"]]["record_type_code"] = $row["record_type_code"];
		$records[$row["id"]]["record_num"] = $row["record_num"];
		$records[$row["id"]]["deletion_date_gmt"] = $row["deletion_date_gmt"];
		$records[$row["id"]]["record_last_updated_gmt"] = strtotime($row["record_last_updated_gmt"]);
	}
	if (empty($records)) {
		print "Nothing to process\n";
		exit;
	}
	
	foreach($records as $key => $value) {
		switch ($records[$key]["record_type_code"]) {
			case "i":
				if (isset($records[$key]["deletion_date_gmt"])) {
					$query = "DELETE FROM `" . $_GLOBALS["itemtable"] . "` where `record_id` = '" . $key . "'";
					echo $query ."\n";
					$mysqli->query($query) or die('Invalid query: ' . $mysqli->error);
					$counter["item"]["deleted"]++;
				} else {
					// TODO - evaluate if this is successful before updating counter
					itemscan($key);
					$counter["item"]["updated"]++;
				}
				break;
			case "b":
				if (isset($records[$key]["deletion_date_gmt"])) {
					// TODO - This *WILL* leave orphan subjects which should be fixed with foreign keys
					$query = "DELETE FROM `" . $_GLOBALS["itemtable"] . "` where `bib_record_id` = '" . $key . "'";
					$mysqli->query($query) or die('Invalid query: ' . $mysqli->error);
					$query = "DELETE FROM `" . $_GLOBALS["bibsubjectlinktable"] . "` where `bib_record_id` = '" . $key . "'";
					$mysqli->query($query) or die('Invalid query: ' . $mysqli->error);
					$query = "DELETE FROM `" . $_GLOBALS["bibtable"] . "` where `record_id` = '" . $key . "'";
					$mysqli->query($query) or die('Invalid query: ' . $mysqli->error);
					$counter["bib"]["deleted"]++;
				} else {
					// TODO - evaluate if this is successful before updating counter
					bibscan($key);
					$counter["bib"]["updated"]++;
				}
				break;
		}
		// If record update is successful, change the pointer.  Deleted items will potentially be scanned multiple times due to date granularity
		markscan('F', $records[$key]["record_last_updated_gmt"]);
	}
	print_r($counter);
	return;
}

function coverscan() {
		$coversfound = 0;
		$scan_filter = " < 6 "; // everyday scan items that have been tried less than a week
		if (date("w") == 0) {
			$scan_filter = " < 9 "; // scanned more than a week?  Now only on sundays
		}
		if (date("j") == 1) {
			$scan_filter = " >= 0 "; // First day of the month, try everything again
		}
		$max_times_to_scan = 15;
		$sql = "SELECT b.record_id, b.ISBNISSN, b.TITLE, b.MATTYPE, b.coverscan_count FROM `" . $_GLOBALS["bibtable"] ."` AS b WHERE b.catdate != '0000-00-00' AND (b.coverscan_count " . $scan_filter . " AND b.coverscan_count < " . $max_times_to_scan . ") AND b.ISBNISSN != \"\" AND (b.coverimage_src LIKE  \"http://www.londonpubliclibrary.ca%\" OR b.coverimage_src IS NULL) ORDER BY b.record_id DESC LIMIT 1000";
		$query = $mysqli->query($sql);
print $sql . "\r\n";
		while ($row = $query->fetch_array(MYSQLI_ASSOC)) {
			$bibnums[$row["record_id"]]["ISBN"] = $row["ISBNISSN"];
			$bibnums[$row["record_id"]]["TITLE"] = $row["TITLE"];
			$bibnums[$row["record_id"]]["MATTYPE"] = $row["MATTYPE"];
			$bibnums[$row["record_id"]]["coverscan_count"] = $row["coverscan_count"] + 1;
			$counter++;
		}
		foreach($bibnums as $key => $value) {
			switch ($bibnums[$key]["MATTYPE"]) { 
				case "b":
				case "v":
					$coverimage_src = "http://www.londonpubliclibrary.ca/sites/londonpubliclibrary.ca/misc/nocover-dvd.png";
					$coverimage_x = 150;
					$coverimage_y = 200;
					$temp_coverimage = "http://www.syndetics.com/index.aspx?upc=" . $bibnums[$key]["ISBN"] . "/MC.JPG&client=londonp";
// DVD http://www.syndetics.com/index.aspx?upc=024543283447/MC.JPG&client=londonp
					break;
				case "a":
				case "t":
				case "x":
					$coverimage_src = "http://www.londonpubliclibrary.ca/sites/londonpubliclibrary.ca/misc/nocover-cd.png";
					$coverimage_x = 130;
					$coverimage_y = 130;
					$temp_coverimage = "http://www.syndetics.com/index.aspx?upc=" . $bibnums[$key]["ISBN"] . "/MC.JPG&client=londonp";
// CD http://www.syndetics.com/index.aspx?upc=098787111224/LC.JPG&client=londonp
					break;
				default :
					$coverimage_src = "http://www.londonpubliclibrary.ca/sites/londonpubliclibrary.ca/misc/nocover.png";
					$coverimage_x = 130;
					$coverimage_y = 200;
					$temp_coverimage = "http://www.syndetics.com/index.aspx?isbn=" . $bibnums[$key]["ISBN"] . "/mc.jpg&client=londonp";
// Books http://www.syndetics.com/index.php?isbn=9780316300391/mc.jpg&client=londonp
					break;
			}
//print $key .  " : " . "\r\n";


			try {
				$size = getimagesize($temp_coverimage);
			} catch (ErrorException $e) {
				$size = false;
			}
			if ($size[0] > 1) {
				$coversfound++;
				$coverimage_src = $temp_coverimage;
				$coverimage_x = $size[0];
				$coverimage_y = $size[1];
			}
			$sql = "UPDATE `" . $_GLOBALS["bibtable"] . "` set `coverscan_count` = " . $bibnums[$key]["coverscan_count"] . ", `coverimage_src` = \"" . $mysqli->real_escape_string($coverimage_src) . "\", `coverimage_sizex` = " . $coverimage_x . ", `coverimage_sizey` = " . $coverimage_y . " where `record_id` = \"" . $key . "\"";
//print $sql . "\r\n";
			$query = $mysqli->query($sql);
		}
//		maillog($_GLOBALS["email_log"], "*New Items* Cover Scanned: $counter Updated: $coversfound");
	return;
}

function progress($srctable, $desttable) {
	GLOBAL $_GLOBALS, $mysqli, $pgconn;
	$query = "SELECT count(*) AS srctotal FROM " . $srctable;

	$result = pg_query($pgconn, $query);
	if (!$result) {
		echo "Source table is empty\n";
		exit;
	}

	$srcrecords = pg_fetch_array($result);
	$query = "SELECT count(*) AS desttotal FROM " . $desttable;
	$result = $mysqli->query($query);
	if (!$result) {
		echo "Destination table is empty\n";
		exit;
	}

	$destrecords = $result->fetch_array(MYSQLI_ASSOC);
	
	return($destrecords["desttotal"] . " of " . $srcrecords["srctotal"] . " processed. " . round(($destrecords["desttotal"]/$srcrecords["srctotal"])*100) . "% complete\n");
}

function markscan($firstscan = NULL, $timestamp = NULL) {
	GLOBAL $mysqli;
    if ($timestamp === NULL) $timestamp = time();
	
	// Set the current time as the place to start looking when running updates
	if ($firstscan == 'T') { // first scan through needs to be the start time of the first scan, regardless of type of record
		$query = "INSERT INTO markers (fieldname, fieldvalue) VALUES ('lastscan', '" . $timestamp . "') ON DUPLICATE KEY UPDATE fieldvalue = IF(fieldvalue > VALUES(fieldvalue), VALUES(fieldvalue), fieldvalue)";
	} else {
		$query = "REPLACE INTO markers (fieldname, fieldvalue) VALUES ('lastscan', '" . $timestamp . "')";
	}
	$mysqli->query($query) or die('Invalid query: ' . $mysqli->error);
	return;
}
?>
