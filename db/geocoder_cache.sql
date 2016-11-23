-- phpMyAdmin SQL Dump
-- version 3.3.7
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Generation Time: Mar 24, 2013 at 08:42 AM
-- Server version: 5.0.77
-- PHP Version: 5.3.3

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";

--
-- Database: `haz18108_geotest`
--

-- --------------------------------------------------------

--
-- Table structure for table `geocoder_cache`
--

CREATE TABLE IF NOT EXISTS `geocoder_cache` (
  `insert_time` bigint(20) NOT NULL,
  `address` varchar(128) NOT NULL,
  `coordinates` varchar(64) default NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
