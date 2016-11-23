-- phpMyAdmin SQL Dump
-- version 3.5.8.1deb1
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: May 31, 2014 at 09:27 PM
-- Server version: 5.5.34-0ubuntu0.13.04.1
-- PHP Version: 5.4.9-4ubuntu2.4

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Database: `ewn2`
--

-- --------------------------------------------------------

--
-- Table structure for table `incidents`
--

CREATE TABLE IF NOT EXISTS `incidents` (
  `guid` varchar(128) CHARACTER SET latin1 NOT NULL,
  `title` varchar(128) CHARACTER SET latin1 NOT NULL,
  `state` varchar(4) CHARACTER SET latin1 NOT NULL,
  `description` varchar(10000) CHARACTER SET latin1 NOT NULL,
  `category` varchar(64) CHARACTER SET latin1 NOT NULL,
  `last_category` varchar(64) CHARACTER SET latin1 NOT NULL,
  `link` varchar(256) CHARACTER SET latin1 NOT NULL,
  `unixtimestamp` int(11) NOT NULL,
  `lon` double(12,9) DEFAULT NULL,
  `lat` double(12,9) DEFAULT NULL,
  `last_lon` double(12,9) DEFAULT NULL,
  `last_lat` double(12,9) DEFAULT NULL,
  `point_str` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `point_geom` point DEFAULT NULL,
  `geocoded` tinyint(4) NOT NULL DEFAULT '0',
  `type` varchar(64) CHARACTER SET latin1 DEFAULT NULL,
  `event` enum('bushfire','traffic') CHARACTER SET latin1 NOT NULL,
  `update_ts` int(11) NOT NULL,
  `feed_type` varchar(64) COLLATE utf8_unicode_ci NOT NULL,
  `posted` tinyint(4) NOT NULL DEFAULT '0',
  `alert_key` int(11) NOT NULL DEFAULT '0',
  `delivery_location_key` int(11) NOT NULL DEFAULT '0',
  `posted2` tinyint(4) NOT NULL DEFAULT '0',
  `alert_key2` int(11) NOT NULL DEFAULT '0',
  `delivery_location_key2` int(11) NOT NULL DEFAULT '0',
  `geometries` varchar(10000) COLLATE utf8_unicode_ci DEFAULT NULL,
  UNIQUE KEY `title` (`title`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
-- May need to alter db unicode because of unique characters being passed through.
/* ALTER DATABASE <DBNAME> CHARACTER SET utf8 COLLATE utf8_general_ci;*/

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
