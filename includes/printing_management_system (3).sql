-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: 09 سبتمبر 2025 الساعة 14:23
-- إصدار الخادم: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `printing_management_system`
--

-- --------------------------------------------------------

--
-- بنية الجدول `accounts`
--

CREATE TABLE `accounts` (
  `account_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('asset','liability','equity','revenue','expense') NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `accounts`
--

INSERT INTO `accounts` (`account_id`, `name`, `type`, `description`) VALUES
(1, 'عبد الرؤوف', 'expense', 'mm');

-- --------------------------------------------------------

--
-- بنية الجدول `activity_log`
--

CREATE TABLE `activity_log` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `activity_log`
--

INSERT INTO `activity_log` (`log_id`, `user_id`, `action`, `description`, `ip_address`, `user_agent`, `created_at`) VALUES
(16, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 16:15:09'),
(17, 1, 'add_customer', 'تم إضافة عميل جديد: عبد الرؤوف', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 16:17:04'),
(18, 1, 'add_customer', 'تم إضافة عميل جديد: عبد الرؤوف', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 16:17:55'),
(19, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 16:21:34'),
(20, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 16:21:37'),
(21, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 16:21:43'),
(22, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 16:21:46'),
(23, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 16:23:07'),
(24, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 16:24:18'),
(25, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 16:37:27'),
(26, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 16:37:41'),
(27, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 17:24:31'),
(28, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 20:23:36'),
(29, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 20:30:25'),
(30, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 20:30:29'),
(31, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 20:30:33'),
(32, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 20:30:35'),
(33, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 20:30:44'),
(34, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 20:30:53'),
(35, 1, 'add_customer', 'تم إضافة عميل جديد: عبد الرؤوف', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 20:33:17'),
(36, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 20:36:47'),
(37, 1, 'add_customer', 'تم إضافة عميل جديد: عبد الرؤوف', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 20:39:46'),
(38, 1, 'add_customer', 'تم إضافة عميل جديد: عبد الرؤوف', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 20:39:53'),
(39, 1, 'add_customer', 'تم إضافة عميل جديد: عبد الرؤوف', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 20:40:01'),
(40, 1, 'add_customer', 'تم إضافة عميل جديد: عبد الرؤوف', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 20:40:09'),
(41, 1, 'add_customer', 'تم إضافة عميل جديد: ohg]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 20:40:43'),
(42, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 20:50:50'),
(43, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 20:51:14'),
(44, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 20:51:40'),
(45, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 20:54:03'),
(46, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 20:54:09'),
(47, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 20:56:31'),
(48, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 21:01:47'),
(49, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 21:02:51'),
(50, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 21:03:03'),
(51, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 21:03:08'),
(52, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 21:03:21'),
(53, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 21:06:51'),
(54, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 21:06:55'),
(55, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 21:07:23'),
(56, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 21:07:28'),
(57, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-12 21:11:45'),
(58, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 08:44:41'),
(59, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 08:44:44'),
(60, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 08:44:48'),
(61, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 08:47:22'),
(62, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 08:48:00'),
(63, 1, 'add_customer', 'تم إضافة عميل جديد: سالم', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 08:51:05'),
(64, 1, 'add_customer', 'تم إضافة عميل جديد: علي خليفة', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 08:51:19'),
(65, 1, 'add_customer', 'تم إضافة عميل جديد: محمد', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 08:51:31'),
(66, 1, 'add_customer', 'تم إضافة عميل جديد: خيري يزيد', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 08:51:41'),
(67, 1, 'add_customer', 'تم إضافة عميل جديد: محمود علي محمد', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 08:51:55'),
(68, 1, 'add_customer', 'تم إضافة عميل جديد: محمود علي محمد', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 08:52:03'),
(69, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 08:58:35'),
(70, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 09:28:31'),
(71, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 09:39:12'),
(72, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 09:39:15'),
(73, 1, 'add_customer', 'تم إضافة عميل جديد: علي خليفة', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 09:54:25'),
(74, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 10:09:56'),
(75, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 10:09:59'),
(76, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 10:12:53'),
(77, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 10:13:12'),
(78, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 10:38:21'),
(79, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 10:38:26'),
(80, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 10:42:22'),
(81, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 11:07:38'),
(82, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 11:16:38'),
(83, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 11:33:27'),
(84, 1, 'add_inventory', 'تم إضافة صنف جديد: حبر', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 11:35:47'),
(85, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 11:37:43'),
(86, 2, 'add_order', 'تم إنشاء طلب جديد رقم 4', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 11:38:09'),
(87, 2, 'add_customer', 'تم إضافة عميل جديد: علي خليفة', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 11:47:16'),
(88, 2, 'add_customer', 'تم إضافة عميل جديد: علي خليفة', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 11:52:22'),
(89, 2, 'add_customer', 'تم إضافة عميل جديد: كاس', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 11:54:30'),
(90, 2, 'add_order', 'تم إنشاء طلب جديد رقم 5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 12:03:14'),
(91, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 12:17:01'),
(92, 1, 'add_inventory', 'تم إضافة صنف جديد: حديد', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 12:17:57'),
(93, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 12:20:45'),
(94, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 12:22:13'),
(95, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 12:24:16'),
(96, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 12:26:30'),
(97, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 12:26:35'),
(98, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 12:28:05'),
(99, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 12:41:38'),
(100, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 12:41:43'),
(101, 2, 'add_order', 'تم إنشاء طلب جديد رقم 11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 12:43:24'),
(102, 2, 'add_order', 'تم إنشاء طلب جديد رقم 12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-13 12:48:45'),
(104, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-17 18:22:53'),
(105, 1, 'add_order', 'تم إنشاء طلب جديد رقم 13', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-17 18:23:40'),
(106, 1, 'add_customer', 'تم إضافة عميل جديد: عبد الرؤوف', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-17 18:36:43'),
(108, 1, 'add_customer', 'تم إضافة عميل جديد: خالد', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-17 18:50:03'),
(109, 1, 'add_order', 'تم إنشاء طلب جديد رقم 14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-17 18:50:46'),
(110, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-17 21:15:08'),
(111, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-17 21:15:30'),
(112, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-17 21:15:32'),
(113, 1, 'add_order', 'تم إنشاء طلب جديد رقم 15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-17 21:16:05'),
(114, 1, 'add_customer', 'تم إضافة عميل جديد: ere', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-17 21:20:28'),
(115, 1, 'add_order', 'تم إنشاء طلب جديد رقم 16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-17 21:32:20'),
(116, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-17 22:04:09'),
(117, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-17 22:04:15'),
(118, 2, 'add_order', 'تم إنشاء طلب جديد رقم 17', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-17 22:04:39'),
(119, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-17 22:09:59'),
(120, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-17 22:26:16'),
(121, 2, 'update_customer', 'تم تحديث بيانات العميل: ere (ID: 21)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-17 22:48:59'),
(122, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-17 22:58:17'),
(124, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-08-17 23:01:34'),
(125, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-18 12:44:13'),
(126, 1, 'update_customer', 'تم تحديث بيانات العميل: ere (ID: 21)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-18 12:44:45'),
(127, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-18 12:52:39'),
(128, 2, 'add_order', 'تم إنشاء طلب جديد رقم 18', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-18 12:53:00'),
(129, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-25 02:50:51'),
(130, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-25 12:18:29'),
(131, 1, 'add_customer', 'تم إضافة عميل جديد: عبد المنعم', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-25 12:20:07'),
(132, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-25 12:21:46'),
(133, 2, 'add_order', 'تم إنشاء طلب جديد رقم 19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-25 12:22:36'),
(134, 2, 'add_order', 'تم إنشاء طلب جديد رقم 20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-25 12:24:55'),
(135, 2, 'update_customer', 'تم تحديث بيانات العميل: عبد المنعم (ID: 22)', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-25 12:26:12'),
(136, 2, 'add_order', 'تم إنشاء طلب جديد رقم 21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-25 12:30:30'),
(137, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-25 12:34:58'),
(138, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-26 19:56:36'),
(139, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36 Edg/139.0.0.0', '2025-08-26 19:57:29'),
(144, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-08 06:13:27'),
(148, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-08 16:15:46'),
(149, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 07:39:24'),
(151, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 07:56:47'),
(152, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 07:57:11'),
(154, 1, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 07:57:25'),
(155, 1, 'view_leave_requests', 'عرض صفحة طلبات الإجازة', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 08:43:26'),
(156, 1, 'view_leave_requests', 'عرض صفحة طلبات الإجازة', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 08:44:22'),
(157, 1, 'view_leave_requests', 'عرض صفحة طلبات الإجازة', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 08:44:24'),
(158, 1, 'view_leave_requests', 'عرض صفحة طلبات الإجازة', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 08:44:25'),
(159, 1, 'view_leave_requests', 'عرض صفحة طلبات الإجازة', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 08:44:26'),
(160, 1, 'view_leave_requests', 'عرض صفحة طلبات الإجازة', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 08:55:25'),
(161, 1, 'create_user', 'إنشاء مستخدم جديد: admin11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 09:18:42'),
(162, 6, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 09:24:58'),
(163, 2, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 09:25:14'),
(164, 6, 'login', 'تسجيل دخول ناجح', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 09:25:27'),
(165, 6, 'view_activity_log', 'عرض سجل النشاطات', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 09:26:17'),
(166, 6, 'view_activity_log', 'عرض سجل النشاطات', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 09:27:05'),
(167, 6, 'view_activity_log', 'عرض سجل النشاطات', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 09:27:12'),
(168, 6, 'view_activity_log', 'عرض سجل النشاطات', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 09:27:17'),
(169, 6, 'view_activity_log', 'عرض سجل النشاطات', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 09:30:15'),
(170, 6, 'view_activity_log', 'عرض سجل النشاطات', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 09:30:15'),
(171, 6, 'view_accounting_dashboard', 'عرض لوحة تحكم المحاسبة', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 09:55:16'),
(172, 6, 'view_accounting_dashboard', 'عرض لوحة تحكم المحاسبة', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 10:01:03'),
(173, 6, 'view_accounts', 'عرض صفحة الحسابات العامة', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 10:02:15'),
(174, 6, 'add_account', 'إضافة حساب جديد: عبد الرؤوف', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 10:04:48'),
(175, 6, 'view_accounts', 'عرض صفحة الحسابات العامة', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 10:04:48'),
(176, 6, 'view_accounts', 'عرض صفحة الحسابات العامة', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 10:05:03'),
(177, 6, 'view_backup_page', 'عرض صفحة النسخ الاحتياطي', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 12:09:16'),
(178, 6, 'view_backup_page', 'عرض صفحة النسخ الاحتياطي', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 12:11:36'),
(179, 6, 'view_backup_page', 'عرض صفحة النسخ الاحتياطي', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 12:11:42'),
(180, 6, 'view_security_page', 'عرض صفحة إدارة الصلاحيات والأمان', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 12:20:29'),
(181, 6, 'update_role', 'تحديث صلاحية المستخدم 6 إلى security_manager', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 12:20:46'),
(182, 6, 'view_security_page', 'عرض صفحة إدارة الصلاحيات والأمان', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 12:20:46'),
(183, 6, 'update_role', 'تحديث صلاحية المستخدم 6 إلى security_manager', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 12:20:55'),
(184, 6, 'view_security_page', 'عرض صفحة إدارة الصلاحيات والأمان', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 12:20:55'),
(185, 6, 'view_security_page', 'عرض صفحة إدارة الصلاحيات والأمان', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 12:21:58'),
(186, 6, 'update_role', 'تحديث صلاحية المستخدم 6 إلى admin', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 12:22:12'),
(187, 6, 'view_security_page', 'عرض صفحة إدارة الصلاحيات والأمان', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 12:22:12'),
(188, 6, 'update_role', 'تحديث صلاحية المستخدم 5 إلى hr', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 12:22:21'),
(189, 6, 'view_security_page', 'عرض صفحة إدارة الصلاحيات والأمان', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36 Edg/140.0.0.0', '2025-09-09 12:22:21');

-- --------------------------------------------------------

--
-- بنية الجدول `attendance`
--

CREATE TABLE `attendance` (
  `attendance_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `check_in` time DEFAULT NULL,
  `check_out` time DEFAULT NULL,
  `status` enum('present','absent','late','vacation','sick') DEFAULT 'absent',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `customers`
--

CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `company_name` varchar(100) DEFAULT NULL,
  `tax_number` varchar(50) DEFAULT NULL,
  `is_vip` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `id_proof_path` varchar(255) DEFAULT NULL COMMENT 'مسار ملف إثبات الهوية',
  `document_type` enum('id','commercial_license','other') DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `customers`
--

INSERT INTO `customers` (`customer_id`, `name`, `phone`, `email`, `address`, `company_name`, `tax_number`, `is_vip`, `notes`, `created_by`, `created_at`, `updated_at`, `id_proof_path`, `document_type`) VALUES
(1, 'عبد الرؤوف', '0916276939', 'bdalrwwfalmlah1@gmail.com', 'قصر بن غشير', 'lorem', NULL, 1, 'ىىى', 1, '2025-08-12 16:17:04', NULL, 'uploads/customers_docs/689b6900b9877.pdf', NULL),
(2, 'عبد الرؤوف', '0916276939', 'bdalrwwfalmlah1@gmail.com', 'قصر بن غشير', 'lorem', NULL, 1, 'ىىى', 1, '2025-08-12 16:17:55', NULL, 'uploads/customers_docs/689b6933e33b7.pdf', NULL),
(3, 'عبد الرؤوف', '0916276939', 'bdalrwwfalmlah1@gmail.com', 'قصر بن غشير', 'lorem', NULL, 0, 'ىىى', 1, '2025-08-12 20:33:17', NULL, 'uploads/customers_docs/689ba50dbb376.pdf', NULL),
(4, 'عبد الرؤوف', '0916276939', 'bdalrwwfalmlah1@gmail.com', 'قصر بن غشير', 'lorem', NULL, 0, 'ىىى', 1, '2025-08-12 20:39:45', NULL, 'uploads/customers_docs/689ba691e10d4.pdf', NULL),
(5, 'عبد الرؤوف', '0916276939', 'bdalrwwfalmlah1@gmail.com', 'قصر بن غشير', 'lorem', NULL, 0, 'ىىى', 1, '2025-08-12 20:39:53', NULL, '', NULL),
(6, 'عبد الرؤوف', '0916276939', 'bdalrwwfalmlah1@gmail.com', 'قصر بن غشير', 'lorem', NULL, 0, 'ىىى', 1, '2025-08-12 20:40:01', NULL, '', NULL),
(7, 'عبد الرؤوف', '0916276939', 'bdalrwwfalmlah1@gmail.com', 'قصر بن غشير', 'lorem', NULL, 0, 'ىىى', 1, '2025-08-12 20:40:08', NULL, '', NULL),
(8, 'ohg]', '0916276939', 'bdalrwwfalmlah1@gmail.com', 'قصر بن غشير', 'lorem', NULL, 1, 'ىىى', 1, '2025-08-12 20:40:43', NULL, '', NULL),
(9, 'سالم', '09223882', 'abdalraouf@gmail.com', 'قصر بن غشير', 'lorem', NULL, 0, 'ىىى', 1, '2025-08-13 08:51:05', NULL, '', NULL),
(10, 'علي خليفة', '09223882', 'abdalraouf@gmail.com', '', 'lorem', NULL, 0, '', 1, '2025-08-13 08:51:19', NULL, '', NULL),
(11, 'محمد', '0916276939', 'bdalrwwfalmlah1@gmail.com', 'j', 'lorem', NULL, 0, '', 1, '2025-08-13 08:51:31', NULL, '', NULL),
(12, 'خيري يزيد', '0916276939', 'bdalrwwfalmlah1@gmail.com', 'قصر بن غشير', 'lorem', NULL, 0, 'ىىى', 1, '2025-08-13 08:51:41', NULL, '', NULL),
(13, 'محمود علي محمد', '0916276939', 'bdalrwwfalmlah1@gmail.com', 'j', 'lorem', NULL, 0, '', 1, '2025-08-13 08:51:55', NULL, '', NULL),
(14, 'محمود علي محمد', '0916276939', 'bdalrwwfalmlah1@gmail.com', '', 'lorem', NULL, 0, '', 1, '2025-08-13 08:52:03', NULL, '', NULL),
(15, 'علي خليفة', '0916276939', 'abdalraouf@gmail.com', 'قصر بن غشير', 'lorem', NULL, 1, 'ىىى', 1, '2025-08-13 09:54:25', NULL, '', NULL),
(16, 'علي خليفة', '0916276939', 'abdalraouf@gmail.com', 'طرابلس', 'lorem', NULL, 1, 'وو', 2, '2025-08-13 11:47:16', NULL, 'uploads/customers_docs/689c7b448e025.pdf', NULL),
(17, 'علي خليفة', '0916276939', 'abdalraouf@gmail.com', 'قصر بن غشير', 'lorem', NULL, 1, 'تاع', 2, '2025-08-13 11:52:22', NULL, 'uploads/customers_docs/689c7c765c0ea.pdf', NULL),
(18, 'كاس', '0923349393', 'ngh@gmail.com', 'idis', 'jjf', NULL, 1, 'jjfj', 2, '2025-08-13 11:54:30', NULL, 'uploads/customers_docs/689c7cf638571.pdf', NULL),
(19, 'عبد الرؤوف', '091627693999', 'bdalrwfalmlah1@gmail.com', 'قصر بن غشير', 'lorem', NULL, 1, 'لاا', 1, '2025-08-17 18:36:43', NULL, 'uploads/customers_docs/68a2213b9649d.pdf', NULL),
(20, 'خالد', '091627693999', 'ngwksalmlah1@gmail.com', 'قصر بن غشير', 'lorem', NULL, 1, 'لاا', 1, '2025-08-17 18:50:03', NULL, 'uploads/customers_docs/68a2245bc46b1.pdf', NULL),
(21, 'ere', '091627693999', 'dswalah1@gmail.com', 'قصر بن غشير', 'kik', NULL, 1, 'no', 1, '2025-08-17 21:20:28', '2025-08-18 12:44:45', 'uploads/customers_docs/68a2479c75c7b.pdf', NULL),
(22, 'عبد المنعم', '09162769398', 'bdalrwwfalmlah1@gmail.com', 'قصر بن غشير', 'lorem', NULL, 1, 'jytouy', 1, '2025-08-25 12:20:07', '2025-08-25 12:26:12', 'uploads/customers_docs/68ac54f75b535.pdf', NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `customer_documents`
--

CREATE TABLE `customer_documents` (
  `document_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `document_type` enum('id','commercial_license','other') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `uploaded_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `departments`
--

CREATE TABLE `departments` (
  `department_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `employees`
--

CREATE TABLE `employees` (
  `employee_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `national_id` varchar(20) NOT NULL,
  `hire_date` date NOT NULL,
  `position` varchar(50) NOT NULL,
  `department` enum('management','sales','production','design','accounting','hr') NOT NULL,
  `salary` decimal(12,2) NOT NULL,
  `emergency_contact` varchar(100) DEFAULT NULL,
  `emergency_phone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `expenses`
--

CREATE TABLE `expenses` (
  `expense_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `expense_date` date NOT NULL,
  `paid_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `inventory`
--

CREATE TABLE `inventory` (
  `item_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category_id` int(11) DEFAULT 1,
  `unit` enum('piece','pack','ream','box','kg','meter') NOT NULL,
  `current_quantity` decimal(10,2) DEFAULT 0.00,
  `min_quantity` decimal(10,2) DEFAULT 5.00,
  `cost_price` decimal(10,2) DEFAULT NULL,
  `selling_price` decimal(10,2) DEFAULT NULL,
  `barcode` varchar(50) DEFAULT NULL,
  `last_updated` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `image` varchar(255) DEFAULT 'default.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `inventory`
--

INSERT INTO `inventory` (`item_id`, `name`, `description`, `category_id`, `unit`, `current_quantity`, `min_quantity`, `cost_price`, `selling_price`, `barcode`, `last_updated`, `image`) VALUES
(8, 'ورق A4', 'ورق طباعة أبيض 80 جرام', 1, 'ream', 99.00, 20.00, 12.50, 15.00, 'PAPER001', '2025-08-13 12:40:36', 'default.png'),
(10, 'غلاف كتاب', 'غلاف بلاستيكي شفاف', 3, 'piece', 198.00, 50.00, 0.50, 1.00, 'COVER001', '2025-08-17 18:24:00', 'default.png'),
(12, 'دباسة', 'دباسة مكتبية كبيرة', 3, 'piece', 20.00, 5.00, 25.00, 35.00, 'STAPLER01', '2025-08-13 12:46:16', 'default.png'),
(13, 'ختم', 'ننتن', 2, 'piece', 6.00, 2.00, 10.00, 20.00, '00908889009890909', '2025-08-17 18:50:46', '689c73ebd0513.jpg'),
(14, 'حبر', 'حبر غانق', 2, 'pack', 22.00, 5.00, 2.00, 5.00, '00990909', '2025-09-08 16:57:25', 'default.png'),
(15, 'حديد', 'حديد لافتة رقيق', 3, 'meter', 273.00, 5.00, 30.00, 79.00, '77566674884993844', '2025-08-13 12:48:45', 'default.png'),
(16, 'مشبك', 'مشبك ورق حجم صغير', 1, 'piece', 99.00, 10.00, 1.00, 1.20, '099923332399', '2025-09-09 07:57:47', '689c88c606d7a.jpg'),
(17, 'لوحة خارجية', NULL, 3, 'meter', 23.00, 3.00, 30.00, 50.00, '77566674884993844', '2025-08-25 12:24:55', '68a21e9b04eb4.png'),
(18, 'لافتة', 'لا يوجد', 3, 'meter', 9.00, 2.00, 20.00, 30.00, '00990909', '2025-08-25 12:22:36', '68a2488735b76.jpg'),
(19, 'فوم', 'كميبنكضم', 3, 'meter', 0.00, 2.00, 90.00, 100.00, '77566674884993844', '2025-08-25 12:31:58', '68ac572896b26.png');

-- --------------------------------------------------------

--
-- بنية الجدول `inventory_transactions`
--

CREATE TABLE `inventory_transactions` (
  `transaction_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `transaction_type` enum('purchase','sale','adjustment','return') NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `performed_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `invoices`
--

CREATE TABLE `invoices` (
  `invoice_id` int(11) NOT NULL,
  `invoice_number` varchar(20) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `customer_id` int(11) NOT NULL,
  `issue_date` date NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('draft','sent','paid','overdue','cancelled') DEFAULT 'draft',
  `subtotal` decimal(12,2) NOT NULL,
  `tax_amount` decimal(12,2) DEFAULT 0.00,
  `discount_amount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `invoices`
--

INSERT INTO `invoices` (`invoice_id`, `invoice_number`, `order_id`, `customer_id`, `issue_date`, `due_date`, `status`, `subtotal`, `tax_amount`, `discount_amount`, `total_amount`, `notes`, `created_by`, `created_at`) VALUES
(6, 'INV-20250813-6798', NULL, 9, '2025-08-13', '2025-08-20', 'draft', 0.00, 0.00, 0.00, 79.00, '', 1, '2025-08-13 12:35:58'),
(7, 'INV-20250813-7224', NULL, 7, '2025-08-13', '2025-08-20', 'draft', 0.00, 0.00, 0.00, 35.00, '', 1, '2025-08-13 12:36:22'),
(9, 'INV-20250813-8708', NULL, 12, '2025-08-13', '2025-08-20', 'draft', 0.00, 0.00, 0.00, 20.00, '', 1, '2025-08-13 12:38:27'),
(11, 'INV-20250813-7058', NULL, 9, '2025-08-13', '2025-08-20', 'draft', 0.00, 0.00, 0.00, 15.00, '', 1, '2025-08-13 12:40:36'),
(14, 'INV-20250817-0556', NULL, 9, '2025-08-17', '2025-08-24', 'draft', 0.00, 0.00, 0.00, 1.00, '', 1, '2025-08-17 18:24:00'),
(15, 'INV-20250817-5926', NULL, 9, '2025-08-17', '2025-08-24', 'draft', 0.00, 0.00, 0.00, 150.00, 'لا يوجد', 1, '2025-08-17 18:26:19'),
(30, 'INV-20250817-6483', NULL, 21, '2025-08-17', '2025-08-24', 'draft', 0.00, 0.00, 0.00, 30.00, '', 1, '2025-08-17 21:25:58'),
(32, 'INV-20250825-6661', NULL, 22, '2025-08-25', '2025-08-29', 'draft', 0.00, 0.00, 0.00, 100.00, '', 2, '2025-08-25 12:31:58'),
(33, 'INV-20250908-1481', NULL, 1, '2025-09-08', '2025-09-15', 'draft', 0.00, 0.00, 0.00, 5.00, '', 1, '2025-09-08 16:57:25'),
(34, 'INV-20250909-7504', NULL, 1, '2025-09-09', '2025-09-16', 'draft', 0.00, 0.00, 0.00, 1.20, '', 1, '2025-09-09 07:57:47');

-- --------------------------------------------------------

--
-- بنية الجدول `invoice_items`
--

CREATE TABLE `invoice_items` (
  `item_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `invoice_items`
--

INSERT INTO `invoice_items` (`item_id`, `invoice_id`, `product_id`, `quantity`, `unit_price`, `discount`, `total`, `description`) VALUES
(8, 11, NULL, 1.00, 15.00, 0.00, 0.00, NULL),
(10, 14, NULL, 1.00, 1.00, 0.00, 0.00, NULL),
(12, 7, NULL, 1.00, 35.00, 0.00, 0.00, NULL),
(13, 9, NULL, 1.00, 20.00, 0.00, 0.00, NULL),
(14, 33, NULL, 1.00, 5.00, 0.00, 0.00, NULL),
(15, 6, NULL, 1.00, 79.00, 0.00, 0.00, NULL),
(16, 34, NULL, 1.00, 1.20, 0.00, 0.00, NULL),
(17, 15, NULL, 3.00, 50.00, 0.00, 0.00, NULL),
(18, 30, NULL, 1.00, 30.00, 0.00, 0.00, NULL),
(19, 32, NULL, 1.00, 100.00, 0.00, 0.00, NULL);

-- --------------------------------------------------------

--
-- بنية الجدول `invoice_payments`
--

CREATE TABLE `invoice_payments` (
  `payment_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_method` enum('cash','check','bank_transfer','credit_card') NOT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `received_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `items`
-- (See below for the actual view)
--
CREATE TABLE `items` (
`item_id` int(11)
,`name` varchar(100)
,`description` text
,`selling_price` decimal(10,2)
,`current_quantity` decimal(10,2)
,`cost_price` decimal(10,2)
,`barcode` varchar(50)
);

-- --------------------------------------------------------

--
-- بنية الجدول `journal_entries`
--

CREATE TABLE `journal_entries` (
  `entry_id` int(11) NOT NULL,
  `entry_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `journal_entry_details`
--

CREATE TABLE `journal_entry_details` (
  `detail_id` int(11) NOT NULL,
  `entry_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `debit` decimal(10,2) DEFAULT 0.00,
  `credit` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `leave_requests`
--

CREATE TABLE `leave_requests` (
  `leave_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `type` enum('annual','sick','unpaid','other') NOT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `type` enum('inventory','order','invoice','attendance','other') NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `orders`
--

CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `required_date` date DEFAULT NULL,
  `completed_date` timestamp NULL DEFAULT NULL,
  `status` enum('pending','approved','rejected','completed','design','production','ready','delivered','cancelled') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `orders`
--

INSERT INTO `orders` (`order_id`, `customer_id`, `order_date`, `required_date`, `completed_date`, `status`, `approved_by`, `approved_at`, `rejection_reason`, `priority`, `notes`, `created_by`) VALUES
(11, 9, '2025-08-13 12:43:24', '2025-08-25', NULL, 'pending', NULL, NULL, NULL, 'medium', 'لاشيء', 2),
(12, 10, '2025-08-13 12:48:45', '2025-08-27', NULL, 'pending', NULL, NULL, NULL, 'medium', '', 2),
(13, 9, '2025-08-17 18:23:40', '2025-08-19', NULL, 'pending', NULL, NULL, NULL, 'medium', '', 1),
(14, 20, '2025-08-17 18:50:46', '2025-08-17', NULL, 'pending', NULL, NULL, NULL, 'high', 'لا يوجد', 1),
(15, 20, '2025-08-17 21:16:05', '2025-08-18', NULL, 'pending', NULL, NULL, NULL, 'medium', 'gh', 1),
(16, 21, '2025-08-17 21:32:19', '2025-08-17', NULL, 'pending', NULL, NULL, NULL, 'medium', 'لا يوجد', 1),
(17, 21, '2025-08-17 22:04:39', '2025-08-18', NULL, 'pending', NULL, NULL, NULL, 'medium', 'لا يوجد ', 2),
(18, 4, '2025-08-18 12:53:00', '2025-08-26', NULL, 'production', 2, NULL, NULL, 'medium', '', 2),
(19, 22, '2025-08-25 12:22:36', '2025-08-31', NULL, 'completed', 2, NULL, NULL, 'medium', 'متر فالمتر ', 2),
(20, 22, '2025-08-25 12:24:55', '2025-08-30', NULL, 'delivered', 2, NULL, NULL, 'urgent', 'لا يوجد', 2),
(21, 20, '2025-08-25 12:30:30', '2025-08-24', NULL, 'pending', NULL, NULL, NULL, 'medium', '', 2);

-- --------------------------------------------------------

--
-- بنية الجدول `order_items`
--

CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `specifications` text DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `order_items`
--

INSERT INTO `order_items` (`order_item_id`, `order_id`, `item_id`, `quantity`, `unit_price`, `discount`, `specifications`, `status`) VALUES
(8, 11, 10, 1.00, 1.00, 0.00, NULL, 'pending'),
(9, 11, 14, 6.00, 5.00, 0.00, NULL, 'pending'),
(10, 12, 15, 9.00, 79.00, 0.00, NULL, 'pending'),
(11, 12, 15, 8.00, 79.00, 0.00, NULL, 'pending'),
(12, 13, 13, 1.00, 20.00, 0.00, '', 'pending'),
(13, 14, 13, 1.00, 20.00, 0.00, 'لا يوجد', 'pending'),
(14, 15, 17, 1.00, 50.00, 0.00, '', 'pending'),
(15, 16, 18, 1.00, 30.00, 0.00, 'لا يوجد', 'pending'),
(16, 17, 17, 1.00, 50.00, 0.00, NULL, 'pending'),
(17, 18, 17, 1.00, 50.00, 0.00, NULL, 'pending'),
(18, 19, 18, 1.00, 30.00, 0.00, NULL, 'pending'),
(19, 20, 17, 1.00, 50.00, 6.00, 'كمسنرهمش', 'pending'),
(20, 21, 19, 18.00, 100.00, 0.00, '', 'pending');

-- --------------------------------------------------------

--
-- بنية الجدول `payroll`
--

CREATE TABLE `payroll` (
  `payroll_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `month` tinyint(4) NOT NULL,
  `year` smallint(6) NOT NULL,
  `basic_salary` decimal(12,2) NOT NULL,
  `bonuses` decimal(12,2) DEFAULT 0.00,
  `deductions` decimal(12,2) DEFAULT 0.00,
  `tax` decimal(12,2) DEFAULT 0.00,
  `net_salary` decimal(12,2) NOT NULL,
  `payment_date` date DEFAULT NULL,
  `status` enum('pending','paid') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `prepared_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `product_categories`
--

CREATE TABLE `product_categories` (
  `category_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `product_categories`
--

INSERT INTO `product_categories` (`category_id`, `name`, `description`) VALUES
(1, 'أوراق', 'مواد ورقية للطباعة والتغليف'),
(2, 'حبر ومواد طباعة', 'أحبار ومواد استهلاكية للطابعات'),
(3, 'مستلزمات عامة', 'أدوات مكتبية ومستلزمات متنوعة');

-- --------------------------------------------------------

--
-- بنية الجدول `revenues`
--

CREATE TABLE `revenues` (
  `revenue_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `description` text DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `revenue_date` date NOT NULL,
  `received_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','sales','workshop','designer','accountant','hr') NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT 'default.png'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- إرجاع أو استيراد بيانات الجدول `users`
--

INSERT INTO `users` (`user_id`, `username`, `password`, `full_name`, `email`, `phone`, `role`, `is_active`, `created_at`, `last_login`, `profile_image`) VALUES
(1, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مدير النظام', 'admin@example.com', '0501234567', 'admin', 1, '2025-08-12 16:14:45', '2025-09-09 07:57:25', 'admin.png'),
(2, 'sales1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'موظف مبيعات', 'sales@example.com', '0507654321', '', 1, '2025-08-12 16:14:45', '2025-09-09 09:25:14', 'user.png'),
(3, 'designer1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مصمم جرافيك', 'designer@example.com', '0501112233', 'designer', 1, '2025-08-12 16:14:45', NULL, 'user.png'),
(4, 'workshop1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'مسؤول ورشة', 'workshop@example.com', '0504445566', 'workshop', 1, '2025-08-12 16:14:45', NULL, 'user.png'),
(5, 'accountant1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'محاسب رئيسي', 'accountant@example.com', '0508889999', 'hr', 1, '2025-08-12 16:14:45', NULL, 'user.png'),
(6, 'admin11', '$2y$10$a7B7VlbLCXvsem2MaFu8ru9MBUtyfQOskdPa.8tv13CRUqXBc6O3y', '', 'bdalrwwfalmlah111@gmail.com', NULL, 'admin', 1, '2025-09-09 09:18:42', '2025-09-09 09:25:27', 'default.png');

-- --------------------------------------------------------

--
-- Structure for view `items`
--
DROP TABLE IF EXISTS `items`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `items`  AS SELECT `inventory`.`item_id` AS `item_id`, `inventory`.`name` AS `name`, `inventory`.`description` AS `description`, `inventory`.`selling_price` AS `selling_price`, `inventory`.`current_quantity` AS `current_quantity`, `inventory`.`cost_price` AS `cost_price`, `inventory`.`barcode` AS `barcode` FROM `inventory` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`account_id`);

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`attendance_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`,`date`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`customer_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `customer_documents`
--
ALTER TABLE `customer_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`department_id`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`employee_id`),
  ADD UNIQUE KEY `national_id` (`national_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`expense_id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `paid_by` (`paid_by`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD PRIMARY KEY (`transaction_id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `performed_by` (`performed_by`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`invoice_id`),
  ADD UNIQUE KEY `invoice_number` (`invoice_number`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`item_id`),
  ADD UNIQUE KEY `invoice_id_2` (`invoice_id`,`product_id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `invoice_payments`
--
ALTER TABLE `invoice_payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `received_by` (`received_by`);

--
-- Indexes for table `journal_entries`
--
ALTER TABLE `journal_entries`
  ADD PRIMARY KEY (`entry_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `journal_entry_details`
--
ALTER TABLE `journal_entry_details`
  ADD PRIMARY KEY (`detail_id`),
  ADD KEY `entry_id` (`entry_id`),
  ADD KEY `account_id` (`account_id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`leave_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`order_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`order_item_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `payroll`
--
ALTER TABLE `payroll`
  ADD PRIMARY KEY (`payroll_id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`,`month`,`year`),
  ADD KEY `prepared_by` (`prepared_by`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`category_id`);

--
-- Indexes for table `revenues`
--
ALTER TABLE `revenues`
  ADD PRIMARY KEY (`revenue_id`),
  ADD KEY `account_id` (`account_id`),
  ADD KEY `received_by` (`received_by`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `account_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=190;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `attendance_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `customer_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `customer_documents`
--
ALTER TABLE `customer_documents`
  MODIFY `document_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `department_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `employee_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `expense_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  MODIFY `transaction_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `invoice_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `invoice_payments`
--
ALTER TABLE `invoice_payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `journal_entries`
--
ALTER TABLE `journal_entries`
  MODIFY `entry_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `journal_entry_details`
--
ALTER TABLE `journal_entry_details`
  MODIFY `detail_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `leave_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `order_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `order_item_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `payroll`
--
ALTER TABLE `payroll`
  MODIFY `payroll_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `category_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `revenues`
--
ALTER TABLE `revenues`
  MODIFY `revenue_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- قيود الجداول المُلقاة.
--

--
-- قيود الجداول `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- قيود الجداول `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`);

--
-- قيود الجداول `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- قيود الجداول `customer_documents`
--
ALTER TABLE `customer_documents`
  ADD CONSTRAINT `customer_documents_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `customer_documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`user_id`);

--
-- قيود الجداول `employees`
--
ALTER TABLE `employees`
  ADD CONSTRAINT `employees_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- قيود الجداول `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`),
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`paid_by`) REFERENCES `users` (`user_id`);

--
-- قيود الجداول `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`category_id`);

--
-- قيود الجداول `inventory_transactions`
--
ALTER TABLE `inventory_transactions`
  ADD CONSTRAINT `inventory_transactions_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `inventory` (`item_id`),
  ADD CONSTRAINT `inventory_transactions_ibfk_2` FOREIGN KEY (`performed_by`) REFERENCES `users` (`user_id`);

--
-- قيود الجداول `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`),
  ADD CONSTRAINT `invoices_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  ADD CONSTRAINT `invoices_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- قيود الجداول `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`invoice_id`),
  ADD CONSTRAINT `invoice_items_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `inventory` (`item_id`) ON DELETE SET NULL;

--
-- قيود الجداول `invoice_payments`
--
ALTER TABLE `invoice_payments`
  ADD CONSTRAINT `invoice_payments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`invoice_id`),
  ADD CONSTRAINT `invoice_payments_ibfk_2` FOREIGN KEY (`received_by`) REFERENCES `users` (`user_id`);

--
-- قيود الجداول `journal_entries`
--
ALTER TABLE `journal_entries`
  ADD CONSTRAINT `journal_entries_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- قيود الجداول `journal_entry_details`
--
ALTER TABLE `journal_entry_details`
  ADD CONSTRAINT `journal_entry_details_ibfk_1` FOREIGN KEY (`entry_id`) REFERENCES `journal_entries` (`entry_id`),
  ADD CONSTRAINT `journal_entry_details_ibfk_2` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`);

--
-- قيود الجداول `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `leave_requests_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `leave_requests_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`);

--
-- قيود الجداول `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- قيود الجداول `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`);

--
-- قيود الجداول `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `inventory` (`item_id`);

--
-- قيود الجداول `payroll`
--
ALTER TABLE `payroll`
  ADD CONSTRAINT `payroll_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`employee_id`),
  ADD CONSTRAINT `payroll_ibfk_2` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`user_id`);

--
-- قيود الجداول `revenues`
--
ALTER TABLE `revenues`
  ADD CONSTRAINT `revenues_ibfk_1` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`account_id`),
  ADD CONSTRAINT `revenues_ibfk_2` FOREIGN KEY (`received_by`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
