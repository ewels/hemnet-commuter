-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 02, 2024 at 10:40 PM
-- Server version: 10.6.19-MariaDB-cll-lve
-- PHP Version: 8.3.11

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `hemnet_commuter`
--

-- --------------------------------------------------------

--
-- Table structure for table `commute_locations`
--

CREATE TABLE IF NOT EXISTS `commute_locations` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `nickname` varchar(255) NOT NULL,
  `address` varchar(255) NOT NULL,
  `max_time` int(12) NOT NULL,
  `lat` double NOT NULL,
  `lng` double NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commute_map`
--

CREATE TABLE IF NOT EXISTS `commute_map` (
  `id` int(20) NOT NULL AUTO_INCREMENT,
  `request_str` text NOT NULL,
  `commute_id` int(20) DEFAULT NULL,
  `map_id` varchar(255) NOT NULL,
  `layer_name` varchar(255) NOT NULL,
  `result` longblob NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commute_times`
--

CREATE TABLE IF NOT EXISTS `commute_times` (
  `id` int(20) NOT NULL AUTO_INCREMENT,
  `commute_id` int(20) NOT NULL,
  `house_id` int(20) NOT NULL,
  `status` varchar(30) NOT NULL,
  `distance_text` varchar(255) DEFAULT NULL,
  `distance_value` int(20) DEFAULT NULL,
  `duration_text` varchar(255) DEFAULT NULL,
  `duration_value` int(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `houses`
--

CREATE TABLE IF NOT EXISTS `houses` (
  `id` int(20) NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `isUpcoming` int(1) NOT NULL DEFAULT 0,
  `isNewConstruction` int(1) NOT NULL DEFAULT 0,
  `isForeclosure` int(1) NOT NULL DEFAULT 0,
  `isBiddingOngoing` int(1) NOT NULL DEFAULT 0,
  `livingArea` double NOT NULL,
  `landArea` double NOT NULL,
  `floor` double NOT NULL,
  `storeys` double NOT NULL DEFAULT 0,
  `supplementalArea` double NOT NULL,
  `daysOnHemnet` int(30) NOT NULL,
  `numberOfRooms` double NOT NULL,
  `lat` double NOT NULL,
  `lng` double NOT NULL,
  `askingPrice` double NOT NULL,
  `runningCosts` double NOT NULL,
  `nextOpenHouse` int(30) DEFAULT NULL,
  `upcomingOpenHouses` varchar(255) NOT NULL,
  `size_total` double NOT NULL,
  `image_url` varchar(255) NOT NULL DEFAULT '',
  `created` int(18) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `house_comments`
--

CREATE TABLE IF NOT EXISTS `house_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `house_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `house_ratings`
--

CREATE TABLE IF NOT EXISTS `house_ratings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `house_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` varchar(20) NOT NULL,
  `created` int(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `house_tags`
--

CREATE TABLE IF NOT EXISTS `house_tags` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `house_id` int(12) NOT NULL,
  `tag_id` int(12) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ratings`
--

CREATE TABLE IF NOT EXISTS `ratings` (
  `house_id` varchar(255) NOT NULL,
  `ratings` longblob NOT NULL,
  PRIMARY KEY (`house_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `saved_searches`
--

CREATE TABLE IF NOT EXISTS `saved_searches` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `search_id` int(12) NOT NULL,
  `name` varchar(255) NOT NULL,
  `search` longblob NOT NULL,
  `created` int(20) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `search_id` (`search_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `school_comments`
--

CREATE TABLE IF NOT EXISTS `school_comments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `school_ratings`
--

CREATE TABLE IF NOT EXISTS `school_ratings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `school_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `rating` varchar(20) NOT NULL,
  `created` int(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tags`
--

CREATE TABLE IF NOT EXISTS `tags` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `tag` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `translations`
--

CREATE TABLE IF NOT EXISTS `translations` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `query` text CHARACTER SET utf8mb3 COLLATE utf8mb3_swedish_ci NOT NULL,
  `target` text CHARACTER SET utf8mb3 COLLATE utf8mb3_swedish_ci NOT NULL,
  `translatedText` text CHARACTER SET utf8mb3 COLLATE utf8mb3_swedish_ci NOT NULL,
  `created` int(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(12) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
