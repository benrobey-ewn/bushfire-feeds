-- phpMyAdmin SQL Dump
-- version 3.3.7
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Jan 17, 2013 at 08:03 PM
-- Server version: 5.0.77
-- PHP Version: 5.3.3

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

--
-- Database: `haz18108_geotest`
--

-- --------------------------------------------------------

--
-- Table structure for table `last_update_time`
--

CREATE TABLE IF NOT EXISTS `last_update_time` (
  `id` int(11) NOT NULL,
  `state` varchar(8) character set latin1 NOT NULL,
  `type` varchar(32) character set utf8 collate utf8_bin NOT NULL,
  `event` enum('bushfire','traffic') character set latin1 NOT NULL,
  `time_string` varchar(64) character set latin1 NOT NULL,
  `update_timestamp` int(10) unsigned NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Dumping data for table `last_update_time`
--

INSERT INTO `last_update_time` (`id`, `state`, `type`, `event`, `time_string`, `update_timestamp`) VALUES
(1, 'NSW', 'alert', 'bushfire', 'Wed, 22 Jul 2015 21:10:30 GMT', 1437599530),
(3, 'TAS', 'alert', 'bushfire', 'Thu, 23 Jul 2015 07:12:01 +1000', 1437599531),
(4, 'VIC', 'alert', 'bushfire', 'Mon, 20 Jul 2015 13:31:30 GMT', 1437599530),
(5, 'VIC', 'incident', 'bushfire', 'Wed, 22 Jul 2015 21:11:00 GMT', 1437599531),
(7, 'WA', 'alert', 'bushfire', 'Wed, 22 Jul 2015 21:07:27 GMT', 1437599529),
(8, 'NSW', 'north', 'traffic', '2015-07-22T12:08:31Z', 1437599529),
(9, 'NSW', 'south', 'traffic', '2015-07-22T20:50:53Z', 1437599529),
(10, 'NSW', 'west', 'traffic', '2015-07-22T18:05:58Z', 1437599529),
(11, 'NSW', 'sydneyinner', 'traffic', '2015-07-22T21:08:53Z', 1437599529),
(12, 'NSW', 'sydneynorth', 'traffic', '2015-07-22T20:56:26Z', 1437599529),
(13, 'NSW', 'sydneysouth', 'traffic', '2015-07-22T11:51:49Z', 1437599529),
(14, 'NSW', 'sydneywest', 'traffic', '2015-07-22T21:03:30Z', 1437599530),
(15, 'QLD', 'incident', 'traffic', '1437599100', 1437599527),
(16, 'QLD', 'roadwork', 'traffic', '1437904800', 1437599527),
(17, 'QLD', 'event', 'traffic', '1443297600', 1437599527),
(18, 'QLD', 'limit', 'traffic', '1437535080', 1437599527),
(19, 'NT', '0', 'traffic', 'Thu Jul 23 06:42:10 CST 2015 CST', 1437599531),
(20, 'VIC', '0', 'traffic', 'aed349618020e9fbebb3401bdf325334', 1437599531),
(21, 'WA', '0', 'traffic', 'Wed, 19 Nov 2014 03:08:08 GMT', 1437599530),
(31, 'NT', 'alert', 'bushfire', '1437599528', 1437599529),
(32, 'QLD', 'alert', 'bushfire', '1437597420', 1437599527),
(33, 'SA', 'all_events', 'traffic', '4d7268f05bdab8b5e14c2e0511c3f3a2', 1437599527),
(34, 'ACT', 'all_events', 'traffic', '23 Jul 2015 07:10:58 EST', 1437599526);
