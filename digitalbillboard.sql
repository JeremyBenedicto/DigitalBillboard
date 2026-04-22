-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 22, 2026 at 05:27 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `digitalbillboard`
--

-- --------------------------------------------------------

--
-- Table structure for table `csm_offices`
--

CREATE TABLE `csm_offices` (
  `id` int(10) UNSIGNED NOT NULL,
  `office_code` varchar(150) NOT NULL,
  `office_label` varchar(255) NOT NULL,
  `office_title` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `csm_offices`
--

INSERT INTO `csm_offices` (`id`, `office_code`, `office_label`, `office_title`, `sort_order`, `is_active`, `created_at`) VALUES
(1, 'TAGATALA', 'REGISTRY', 'PAMBAYANG TANGGAPAN NG TAGATALA', 1, 1, '2026-04-22 03:27:00'),
(2, 'AGRIKULTURA', 'AGRICULTURE', 'PAMBAYANG TANGGAPAN NG AGRIKULTURA', 2, 1, '2026-04-22 03:27:00'),
(3, 'INHINYERO', 'ENGINEERING', 'PAMBAYANG TANGGAPAN NG INHINYERO', 3, 1, '2026-04-22 03:27:00'),
(4, 'INGAT-YAMAN', 'TREASURY', 'PAMBAYANG TANGGAPAN NG INGAT-YAMAN', 4, 1, '2026-04-22 03:27:00'),
(5, 'KALUSUGANG BAYAN', 'RHU', 'PAMBAYANG TANGGAPAN NG KALUSUGANG BAYAN', 5, 1, '2026-04-22 03:27:00'),
(6, 'PAGPAPLANO AT PAGPAPAUNLAD', 'PLANNING AND DEVELOPMENT', 'PAMBAYANG TANGGAPAN NG PAGPAPLANO AT PAGPAPAUNLAD', 6, 1, '2026-04-22 03:27:00'),
(7, 'PUNONG BAYAN', 'MAYOR''S OFFICE', 'PAMBAYANG TANGGAPAN NG PUNONG BAYAN', 7, 1, '2026-04-22 03:27:00'),
(8, 'PAMAMAHALA SA YAMANG TAO', 'HRMO (HUMAN RESOURCE MANAGEMENT)', 'PAMBAYANG TANGGAPAN NG PAMAMAHALA SA YAMANG TAO', 8, 1, '2026-04-22 03:27:00'),
(9, 'BADYET', 'BUDGET OFFICE', 'PAMBAYANG TANGGAPAN NG BADYET', 9, 1, '2026-04-22 03:27:00'),
(10, 'TAGASURI', 'ASSESSOR''S OFFICE', 'PAMBAYANG TANGGAPAN NG TAGASURI', 10, 1, '2026-04-22 03:27:00'),
(11, 'KALIKASAN AT LIKAS NA YAMAN', 'MENRO (ENVIRONMENT AND NATURAL RESOURCES)', 'PAMBAYANG TANGGAPAN NG KALIKASAN AT LIKAS NA YAMAN', 11, 1, '2026-04-22 03:27:00'),
(12, 'PANANALAPI', 'ACCOUNTING', 'PAMBAYANG TANGGAPAN NG PANANALAPI', 12, 1, '2026-04-22 03:27:00'),
(13, 'SISTEMA NG TUBIG', 'WATER SYSTEM', 'PAMBAYANG TANGGAPAN NG SISTEMA NG TUBIG', 13, 1, '2026-04-22 03:27:00'),
(14, 'SANGGUNIANG BAYAN', 'SANGGUNIANG BAYAN', 'PAMBAYANG TANGGAPAN NG SANGGUNIANG BAYAN', 14, 1, '2026-04-22 03:27:00'),
(15, 'PAMBAYANG PAMAMAHALA SA SAKUNA AT KALAMIDAD', 'MDRRMO (DISASTER RISK REDUCTION)', 'PAMBAYANG TANGGAPAN NG PAMBAYANG PAMAMAHALA SA SAKUNA AT KALAMIDAD', 15, 1, '2026-04-22 03:27:00'),
(16, 'TURISMO', 'TOURISM', 'PAMBAYANG TANGGAPAN NG TURISMO', 16, 1, '2026-04-22 03:27:00'),
(17, 'BPLO', 'BPLO', 'PAMBAYANG TANGGAPAN NG BPLO', 17, 1, '2026-04-22 03:27:00'),
(18, 'PESO', 'PESO', 'PAMBAYANG TANGGAPAN NG PESO', 18, 1, '2026-04-22 03:27:00'),
(19, 'BAC', 'BAC', 'PAMBAYANG TANGGAPAN NG BAC', 19, 1, '2026-04-22 03:27:00'),
(20, 'COOP', 'COOP', 'PAMBAYANG TANGGAPAN NG COOP', 20, 1, '2026-04-22 03:27:00'),
(21, 'GSO', 'GSO', 'PAMBAYANG TANGGAPAN NG GSO', 21, 1, '2026-04-22 03:27:00');

-- --------------------------------------------------------

--
-- Table structure for table `csm_office_services`
--

CREATE TABLE `csm_office_services` (
  `id` int(10) UNSIGNED NOT NULL,
  `office_code` varchar(150) NOT NULL,
  `service_name` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_satisfaction_response_details`
--

CREATE TABLE `client_satisfaction_response_details` (
  `id` int(10) UNSIGNED NOT NULL,
  `response_id` int(10) UNSIGNED NOT NULL,
  `office_selected` varchar(150) DEFAULT NULL,
  `office_custom_name` varchar(255) DEFAULT NULL,
  `office_title` varchar(255) DEFAULT NULL,
  `transaction_type` text DEFAULT NULL,
  `visit_date` date DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `region_name` varchar(120) DEFAULT NULL,
  `client_type_citizen` tinyint(1) NOT NULL DEFAULT 0,
  `client_type_business` tinyint(1) NOT NULL DEFAULT 0,
  `client_type_government` tinyint(1) NOT NULL DEFAULT 0,
  `gender_male` tinyint(1) NOT NULL DEFAULT 0,
  `gender_female` tinyint(1) NOT NULL DEFAULT 0,
  `cc1_choice` tinyint(3) UNSIGNED DEFAULT NULL,
  `cc2_choice` tinyint(3) UNSIGNED DEFAULT NULL,
  `cc3_choice` tinyint(3) UNSIGNED DEFAULT NULL,
  `sqd0` tinyint(3) UNSIGNED DEFAULT NULL,
  `sqd1` tinyint(3) UNSIGNED DEFAULT NULL,
  `sqd2` tinyint(3) UNSIGNED DEFAULT NULL,
  `sqd3` tinyint(3) UNSIGNED DEFAULT NULL,
  `sqd4` tinyint(3) UNSIGNED DEFAULT NULL,
  `sqd5` tinyint(3) UNSIGNED DEFAULT NULL,
  `sqd6` tinyint(3) UNSIGNED DEFAULT NULL,
  `sqd7` tinyint(3) UNSIGNED DEFAULT NULL,
  `sqd8` tinyint(3) UNSIGNED DEFAULT NULL,
  `suggestions` text DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_satisfaction_responses`
--

CREATE TABLE `client_satisfaction_responses` (
  `id` int(10) UNSIGNED NOT NULL,
  `office_selected` varchar(150) DEFAULT NULL,
  `office_custom_name` varchar(255) DEFAULT NULL,
  `office_title` varchar(255) DEFAULT NULL,
  `client_type_json` longtext DEFAULT NULL,
  `visit_date` date DEFAULT NULL,
  `gender_json` longtext DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `region_name` varchar(120) DEFAULT NULL,
  `transaction_type` text DEFAULT NULL,
  `cc1_json` longtext DEFAULT NULL,
  `cc2_json` longtext DEFAULT NULL,
  `cc3_json` longtext DEFAULT NULL,
  `sqd_json` longtext DEFAULT NULL,
  `suggestions` text DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `payload_json` longtext NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `client_satisfaction_responses`
--

INSERT INTO `client_satisfaction_responses` (`id`, `office_selected`, `office_custom_name`, `office_title`, `client_type_json`, `visit_date`, `gender_json`, `age`, `region_name`, `transaction_type`, `cc1_json`, `cc2_json`, `cc3_json`, `sqd_json`, `suggestions`, `email`, `payload_json`, `ip_address`, `user_agent`, `created_at`) VALUES
(5, 'TAGATALA', '', 'PAMBAYANG TANGGAPAN NG TAGATALA', '{\"mamamayan\":true,\"negosyo\":false,\"gobyerno\":false}', '2026-03-05', '{\"lalaki\":false,\"babae\":true}', 56, 'Region III', 'fgh', '[false,true,false,false]', '[true,false,false,false,false]', '[true,false,false,false]', '{\"sqd0\":\"5\",\"sqd1\":\"5\",\"sqd2\":\"5\",\"sqd3\":\"5\",\"sqd4\":\"5\",\"sqd5\":\"5\",\"sqd6\":\"5\",\"sqd7\":\"5\",\"sqd8\":\"5\"}', '', '', '{\"office\":{\"selected\":\"TAGATALA\",\"custom_name\":\"\",\"title\":\"PAMBAYANG TANGGAPAN NG TAGATALA\"},\"client_type\":{\"mamamayan\":true,\"negosyo\":false,\"gobyerno\":false},\"respondent\":{\"date\":\"2026-03-05\",\"kasarian\":{\"lalaki\":false,\"babae\":true},\"edad\":56,\"rehiyon\":\"Region III\"},\"transaction_type\":\"fgh\",\"citizen_charter\":{\"cc1\":[false,true,false,false],\"cc2\":[true,false,false,false,false],\"cc3\":[true,false,false,false]},\"sqd_ratings\":{\"sqd0\":\"5\",\"sqd1\":\"5\",\"sqd2\":\"5\",\"sqd3\":\"5\",\"sqd4\":\"5\",\"sqd5\":\"5\",\"sqd6\":\"5\",\"sqd7\":\"5\",\"sqd8\":\"5\"},\"suggestions\":\"\",\"email\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-03-05 06:32:32');

-- --------------------------------------------------------

--
-- Table structure for table `slideshow_images`
--

CREATE TABLE `slideshow_images` (
  `id` int(10) UNSIGNED NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `slideshow_images`
--

INSERT INTO `slideshow_images` (`id`, `file_name`, `file_path`, `created_at`) VALUES
(3, '600222df-d3f4-4120-baae-09666737fbe2.jpg', 'uploads/slideshow/slide_6204c5e7f86ba4be134e.jpg', '2026-03-05 06:31:27');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `client_satisfaction_responses`
--
ALTER TABLE `csm_offices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_office_code` (`office_code`);

--
-- Indexes for table `csm_office_services`
--
ALTER TABLE `csm_office_services`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_office_service` (`office_code`,`service_name`),
  ADD KEY `idx_office_code` (`office_code`);

--
-- Indexes for table `client_satisfaction_response_details`
--
ALTER TABLE `client_satisfaction_response_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_response_id` (`response_id`);

--
-- Indexes for table `client_satisfaction_responses`
--
ALTER TABLE `client_satisfaction_responses`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `slideshow_images`
--
ALTER TABLE `slideshow_images`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `client_satisfaction_responses`
--
ALTER TABLE `csm_offices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `csm_office_services`
--
ALTER TABLE `csm_office_services`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client_satisfaction_response_details`
--
ALTER TABLE `client_satisfaction_response_details`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client_satisfaction_responses`
--
ALTER TABLE `client_satisfaction_responses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `slideshow_images`
--
ALTER TABLE `slideshow_images`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for table `client_satisfaction_response_details`
--
ALTER TABLE `client_satisfaction_response_details`
  ADD CONSTRAINT `fk_csm_response_details_response`
  FOREIGN KEY (`response_id`) REFERENCES `client_satisfaction_responses` (`id`)
  ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
