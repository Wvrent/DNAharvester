/*
MySQL - 5.7.17-0ubuntu0.16.04.1 : Database - < MYDBASE >
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
CREATE DATABASE /*!32312 IF NOT EXISTS*/`< MYDBASE >` /*!40100 DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci */;

USE `< MYDBASE >`;

/*Table structure for table `bibs` */

DROP TABLE IF EXISTS `bibs`;

CREATE TABLE `bibs` (
  `record_id` bigint(20) unsigned NOT NULL,
  `RECORDKEY` int(10) unsigned NOT NULL COMMENT 'Unique Bib #',
  `LANGUAGE` varchar(5) CHARACTER SET latin1 DEFAULT NULL,
  `CATDATE` date DEFAULT NULL,
  `MATTYPE` char(1) CHARACTER SET latin1 DEFAULT NULL,
  `BIBSTATUS` char(1) CHARACTER SET latin1 DEFAULT NULL,
  `COUNTRY` char(3) CHARACTER SET latin1 DEFAULT NULL,
  `TITLE` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `AUTHOR` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `CALLNO` varchar(1000) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ISBNISSN` varchar(25) CHARACTER SET latin1 DEFAULT NULL,
  `EXTURL` text CHARACTER SET latin1 COMMENT 'Link to external resource',
  `modifydate` datetime DEFAULT NULL,
  `coverimage_src` text CHARACTER SET latin1,
  `coverimage_sizex` tinyint(3) unsigned DEFAULT NULL,
  `coverimage_sizey` tinyint(3) unsigned DEFAULT NULL,
  `coverscan_count` tinyint(4) DEFAULT NULL COMMENT 'How many times has this been attempted to be scanned',
  `last_edited_gmt` datetime DEFAULT NULL COMMENT 'Last bib edit date',
  PRIMARY KEY (`record_id`),
  UNIQUE KEY `catdate` (`CATDATE`,`record_id`),
  UNIQUE KEY `bibstatus` (`BIBSTATUS`,`record_id`),
  KEY `author` (`AUTHOR`),
  KEY `mattype` (`MATTYPE`),
  KEY `title` (`TITLE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

/*Table structure for table `bibsubjectlink` */

DROP TABLE IF EXISTS `bibsubjectlink`;

CREATE TABLE `bibsubjectlink` (
  `bib_record_id` bigint(10) unsigned NOT NULL COMMENT 'Bib Record',
  `subjectuid` int(10) unsigned NOT NULL COMMENT 'subject record',
  PRIMARY KEY (`bib_record_id`,`subjectuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*Table structure for table `items` */

DROP TABLE IF EXISTS `items`;

CREATE TABLE `items` (
  `record_id` bigint(20) unsigned NOT NULL,
  `itemuid` int(10) unsigned NOT NULL COMMENT 'unique call number',
  `bib_record_id` bigint(20) unsigned NOT NULL COMMENT 'Bib record',
  `locationcode` varchar(8) CHARACTER SET latin1 DEFAULT NULL COMMENT '8 letter location code',
  `branchcode` varchar(5) CHARACTER SET latin1 DEFAULT NULL COMMENT '5 letter branchcode',
  `callno` varchar(1000) CHARACTER SET latin1 DEFAULT NULL COMMENT 'Call Number',
  `barcode` varchar(1000) DEFAULT NULL COMMENT 'Item Barcode',
  `shelf` varchar(20) CHARACTER SET latin1 DEFAULT NULL COMMENT 'Shelf Location, may be blank',
  `price` decimal(10,2) DEFAULT NULL,
  `itemstatus` char(1) CHARACTER SET latin1 DEFAULT NULL,
  `itype` smallint(4) DEFAULT NULL,
  `icode1` char(5) CHARACTER SET latin1 DEFAULT NULL,
  `icode2` char(5) CHARACTER SET latin1 DEFAULT NULL,
  `checkout_total` int(10) unsigned DEFAULT NULL,
  `renewal_total` int(10) unsigned DEFAULT NULL,
  `lastytd_checkout` int(10) unsigned DEFAULT NULL,
  `ytd_checkout` int(10) unsigned DEFAULT NULL,
  `inventory_gmt` datetime DEFAULT NULL,
  `item_message_code` char(1) CHARACTER SET latin1 DEFAULT NULL,
  `last_edited_gmt` datetime DEFAULT NULL,
  PRIMARY KEY (`record_id`),
  UNIQUE KEY `callno` (`callno`,`record_id`),
  KEY `shelf` (`shelf`),
  KEY `fk_bibrecord` (`bib_record_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*Table structure for table `markers` */

DROP TABLE IF EXISTS `markers`;

CREATE TABLE `markers` (
  `fieldname` varchar(100) NOT NULL,
  `fieldvalue` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`fieldname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*Table structure for table `subjects` */

DROP TABLE IF EXISTS `subjects`;

CREATE TABLE `subjects` (
  `subjectuid` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `marctype` smallint(5) unsigned NOT NULL COMMENT '650 or 655',
  `subject` varchar(750) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `subfield` varchar(3) CHARACTER SET latin1 DEFAULT NULL,
  PRIMARY KEY (`subjectuid`),
  UNIQUE KEY `marctype` (`marctype`,`subject`),
  FULLTEXT KEY `subject` (`subject`)
) ENGINE=InnoDB AUTO_INCREMENT=2197408 DEFAULT CHARSET=utf8;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
