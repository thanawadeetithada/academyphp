-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 29, 2026 at 05:32 PM
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
-- Database: `academydb`
--

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `course_id` varchar(20) NOT NULL,
  `name` varchar(100) NOT NULL,
  `level` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `details` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `other_expense_name` varchar(255) DEFAULT NULL,
  `other_expense_price` decimal(10,2) DEFAULT 0.00,
  `days` varchar(255) DEFAULT NULL,
  `time` varchar(100) DEFAULT NULL,
  `course_type` varchar(50) DEFAULT 'คอร์สตัวต่อตัว',
  `course_month` varchar(50) DEFAULT NULL,
  `year_be` varchar(10) DEFAULT NULL,
  `duration` varchar(100) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`course_id`, `name`, `level`, `price`, `details`, `created_at`, `other_expense_name`, `other_expense_price`, `days`, `time`, `course_type`, `course_month`, `year_be`, `duration`, `deleted_at`) VALUES
('C90575', 'เริ่มมย', 'ด', 44.00, '', '2026-06-22 01:09:35', '', 0.00, 'อาทิตย์', '', 'คอร์สกลุ่ม', 'มิย', '2569', '', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `enrollments`
--

CREATE TABLE `enrollments` (
  `enroll_id` varchar(20) NOT NULL,
  `timestamp` datetime DEFAULT current_timestamp(),
  `user_id` varchar(20) NOT NULL,
  `course_id` varchar(20) NOT NULL,
  `approval_status` varchar(50) DEFAULT 'pending_approval',
  `payment_status` varchar(50) DEFAULT 'pending_payment',
  `approved_date` datetime DEFAULT NULL,
  `slip_url` text DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `paid_month` varchar(50) DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `include_other_expense` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = ไม่เอา expense, 1 = เอา expense',
  `net_price` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'ราคาสุทธิของบิลนี้'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `enrollments`
--

INSERT INTO `enrollments` (`enroll_id`, `timestamp`, `user_id`, `course_id`, `approval_status`, `payment_status`, `approved_date`, `slip_url`, `payment_method`, `paid_month`, `deleted_at`, `include_other_expense`, `net_price`) VALUES
('E6a3a2dfdae715', '2026-06-23 13:55:57', 'ST-0002', 'C90575', 'approved', 'pending_payment', NULL, NULL, NULL, 'มิย 2569', NULL, 0, 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `news`
--

CREATE TABLE `news` (
  `id` int(11) NOT NULL,
  `group_id` varchar(50) NOT NULL,
  `icon_header` varchar(100) DEFAULT NULL,
  `header_name` varchar(255) DEFAULT NULL,
  `icon_topic` text DEFAULT NULL,
  `topic` varchar(255) DEFAULT NULL,
  `detail_topic` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `news`
--

INSERT INTO `news` (`id`, `group_id`, `icon_header`, `header_name`, `icon_topic`, `topic`, `detail_topic`, `created_at`) VALUES
(7, 'G_1781929808575', 'bi-lightbulb', 'ประกาศ', '[IMAGE]uploads/news/news_1781929808_3756.png', '', '', '2026-06-20 10:23:48');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` varchar(20) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `nickname` varchar(50) DEFAULT NULL,
  `grade` varchar(50) DEFAULT NULL,
  `school` varchar(100) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `parent_name` varchar(100) DEFAULT NULL,
  `parent_phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `role` varchar(20) DEFAULT 'student',
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `full_name`, `nickname`, `grade`, `school`, `phone`, `parent_name`, `parent_phone`, `address`, `role`, `password_hash`, `created_at`, `deleted_at`) VALUES
('ST-0001', 'admin', 'admin', 'ม.4', 'รร', '0123654789', 'ทดสอบ', '0147852369', '110', 'admin', '$2y$10$Xl9mqWBmT0aqH1Tam/ywEe4BRq6LvEzpXcoZIa0lKFzgTMqKQ3Pv.', '2026-06-20 10:21:34', NULL),
('ST-0002', 'user', 'user', 'ม.5', 'ยย', '0125478547', 'ทดสอบ1', '0125489652', '111', 'student', '$2y$10$0PF4VvsdVGWCrI3dsMLgnuv2UMExX0LiAR899saYz9yjOFJB75/Ie', '2026-06-20 10:22:13', '2026-06-23 07:10:29');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`course_id`);

--
-- Indexes for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD PRIMARY KEY (`enroll_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `news`
--
ALTER TABLE `news`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `news`
--
ALTER TABLE `news`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `enrollments`
--
ALTER TABLE `enrollments`
  ADD CONSTRAINT `enrollments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `enrollments_ibfk_2` FOREIGN KEY (`course_id`) REFERENCES `courses` (`course_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
