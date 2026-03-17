-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 17, 2026 at 06:05 AM
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
-- Database: `college_scheduling`
--

-- --------------------------------------------------------

--
-- Table structure for table `consultation_hours`
--

CREATE TABLE `consultation_hours` (
  `id` int(11) NOT NULL,
  `professor_id` int(11) NOT NULL,
  `day` varchar(20) NOT NULL,
  `time_from` time NOT NULL,
  `time_to` time NOT NULL,
  `room` varchar(100) DEFAULT '',
  `notes` text DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `courses`
--

CREATE TABLE `courses` (
  `id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `department` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `courses`
--

INSERT INTO `courses` (`id`, `code`, `name`, `department`, `created_at`) VALUES
(1, 'BSIS', 'Bachelor of Science in Information Systems', 'College of Computing', '2026-03-14 14:03:07'),
(4, 'BTVTED', 'Bachelor of Technical-Vocational Teacher Education', 'Education', '2026-03-16 08:16:02'),
(5, 'BPED', 'Bachelor of Physical Education', 'Education', '2026-03-16 08:16:02'),
(6, 'BSTM', 'Bachelor of Science in Tourism Management', 'Business', '2026-03-16 08:16:02'),
(7, 'CRIM', 'Bachelor of Science in Criminology', 'Criminology', '2026-03-16 08:16:02'),
(8, 'BSPSYCH', 'Bachelor of Science in Psychology', 'Psychology', '2026-03-16 08:16:02'),
(9, 'BSCPE', 'Bachelor of Science in Computer Engineering', 'Engineering', '2026-03-16 08:16:02'),
(10, 'BSAIS', 'Bachelor of Science in Accounting Information System', 'Business', '2026-03-16 08:16:02'),
(11, 'BSENTREP', 'Bachelor of Science in Entrepreneurship', 'Business', '2026-03-16 08:16:02');

-- --------------------------------------------------------

--
-- Table structure for table `professors`
--

CREATE TABLE `professors` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `employee_id` varchar(30) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `specialization` varchar(150) DEFAULT NULL,
  `employment_type` varchar(20) NOT NULL DEFAULT 'Full Time',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `professors`
--

INSERT INTO `professors` (`id`, `name`, `employee_id`, `department`, `specialization`, `employment_type`, `created_at`) VALUES
(1, 'Prof. Juan Cruz', 'EMP-001', 'IT Department', 'Programming, Algorithms', 'Full Time', '2026-03-14 14:03:07'),
(2, 'Prof. Maria Santos', 'EMP-002', 'General Ed', 'Social Sciences, Ethics', 'Full Time', '2026-03-14 14:03:07'),
(3, 'Prof. Jose Reyes', 'EMP-003', 'PE Department', 'Physical Education', 'Full Time', '2026-03-14 14:03:07'),
(4, 'Prof. Ana Dela Cruz', 'EMP-004', 'IT Department', 'Networking, Database', 'Full Time', '2026-03-14 14:03:07'),
(5, 'Prof. Carlos Bautista', 'EMP-005', 'Math Department', 'Mathematics, Statistics', 'Full Time', '2026-03-14 14:03:07'),
(6, 'Prof. Elena Ramirez', 'EMP-006', 'General Ed', 'Philippine History, Social Sciences', 'Full Time', '2026-03-16 10:42:16'),
(7, 'Prof. Daniel Villanueva', 'EMP-007', 'General Ed', 'Communication, Language Studies', 'Full Time', '2026-03-16 10:42:16'),
(8, 'Prof. Teresa Navarro', 'EMP-008', 'General Ed', 'Rizal Studies, Humanities', 'Full Time', '2026-03-16 10:42:16'),
(9, 'Prof. Ricardo Mendoza', 'EMP-009', 'General Ed', 'Science and Technology Studies', 'Full Time', '2026-03-16 10:42:16'),
(10, 'Prof. Liza Fernandez', 'EMP-010', 'General Ed', 'Art Appreciation, Culture', 'Full Time', '2026-03-16 10:42:16'),
(11, 'Prof. Mark Aguilar', 'EMP-011', 'IT Department', 'Software Development, Programming', 'Full Time', '2026-03-16 10:42:16'),
(12, 'Prof. Kevin Domingo', 'EMP-012', 'IT Department', 'Database Systems, Information Systems', 'Full Time', '2026-03-16 10:42:16'),
(13, 'Prof. Angela Torres', 'EMP-013', 'Math Department', 'Applied Mathematics, Statistics', 'Full Time', '2026-03-16 10:42:16'),
(14, 'Prof. Patrick Lim', 'EMP-014', 'PE Department', 'Sports Science, Physical Education', 'Full Time', '2026-03-16 10:42:16'),
(15, 'Prof. Sophia Castillo', 'EMP-015', 'Business Department', 'Entrepreneurship, Business Management', 'Full Time', '2026-03-16 10:42:16'),
(17, 'Kian Rodriguez', '22020268', 'IT DEPARTMENT', 'Networking, Database', 'Part Time', '2026-03-16 16:46:46');

-- --------------------------------------------------------

--
-- Table structure for table `rooms`
--

CREATE TABLE `rooms` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `building` varchar(100) DEFAULT NULL,
  `capacity` int(11) DEFAULT 0,
  `room_type` enum('Lecture','Computer Lab','Gym','Laboratory','Auditorium') DEFAULT 'Lecture',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `rooms`
--

INSERT INTO `rooms` (`id`, `name`, `building`, `capacity`, `room_type`, `created_at`) VALUES
(1, 'Room 305', 'Main Building', 40, 'Lecture', '2026-03-14 14:03:07'),
(2, 'Room 306', 'Main Building', 40, 'Lecture', '2026-03-14 14:03:07'),
(3, 'ComLab', 'IT Building', 30, 'Computer Lab', '2026-03-14 14:03:07'),
(4, 'LAB2', 'IT Building', 30, 'Computer Lab', '2026-03-14 14:03:07'),
(5, 'Gym', 'Sports Complex', 200, 'Gym', '2026-03-14 14:03:07'),
(6, 'Computer Lab 2', 'Main Building', 40, 'Gym', '2026-03-16 09:08:18'),
(7, 'Room 307', 'Main Building', 40, 'Lecture', '2026-03-16 10:43:50'),
(8, 'Room 308', 'Main Building', 40, 'Lecture', '2026-03-16 10:43:50'),
(9, 'Room 309', 'Main Building', 40, 'Lecture', '2026-03-16 10:43:50'),
(10, 'Room 310', 'Main Building', 40, 'Lecture', '2026-03-16 10:43:50'),
(11, 'Room 311', 'Main Building', 40, 'Lecture', '2026-03-16 10:43:50'),
(12, 'Room 312', 'Main Building', 40, 'Lecture', '2026-03-16 10:43:50'),
(13, 'Room 313', 'Main Building', 40, 'Lecture', '2026-03-16 10:43:50'),
(14, 'Room 314', 'Main Building', 40, 'Lecture', '2026-03-16 10:43:50'),
(15, 'ComLab 1', 'IT Building', 30, 'Computer Lab', '2026-03-16 10:43:50'),
(16, 'ComLab 2', 'IT Building', 30, 'Computer Lab', '2026-03-16 10:43:50'),
(17, 'ComLab 3', 'IT Building', 30, 'Computer Lab', '2026-03-16 10:43:50'),
(18, 'Networking Lab', 'IT Building', 25, 'Computer Lab', '2026-03-16 10:43:50'),
(19, 'Programming Lab', 'IT Building', 25, 'Computer Lab', '2026-03-16 10:43:50'),
(20, 'Science Lab 1', 'Science Building', 35, 'Laboratory', '2026-03-16 10:43:50'),
(21, 'Science Lab 2', 'Science Building', 35, 'Laboratory', '2026-03-16 10:43:50'),
(22, 'Psychology Lab', 'Science Building', 30, 'Laboratory', '2026-03-16 10:43:50'),
(23, 'Business Lab', 'Business Building', 35, 'Lecture', '2026-03-16 10:43:50'),
(24, 'Tourism Lab', 'Hospitality Building', 35, 'Laboratory', '2026-03-16 10:43:50'),
(25, 'Criminology Lab', 'Criminology Building', 30, 'Laboratory', '2026-03-16 10:43:50'),
(26, 'Engineering Lab', 'Engineering Building', 30, 'Laboratory', '2026-03-16 10:43:50');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `section_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `semester` enum('1st Semester','2nd Semester') NOT NULL,
  `day_code` enum('M','T','W','Th','F','Sa') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room_id` int(11) NOT NULL,
  `professor_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `section_id`, `subject_id`, `semester`, `day_code`, `start_time`, `end_time`, `room_id`, `professor_id`, `created_at`) VALUES
(36, 1, 9, '1st Semester', 'M', '07:00:00', '09:00:00', 15, 6, '2026-03-16 10:53:11'),
(37, 1, 10, '1st Semester', 'M', '09:00:00', '11:00:00', 16, 1, '2026-03-16 10:53:11'),
(38, 1, 11, '1st Semester', 'M', '12:30:00', '15:30:00', 1, 7, '2026-03-16 10:53:11'),
(39, 1, 12, '1st Semester', 'Sa', '12:30:00', '15:30:00', 2, 13, '2026-03-16 10:53:11'),
(40, 1, 13, '1st Semester', 'W', '07:00:00', '09:00:00', 7, 8, '2026-03-16 10:53:11'),
(41, 1, 14, '1st Semester', 'W', '09:00:00', '11:00:00', 8, 10, '2026-03-16 10:53:11'),
(42, 1, 15, '1st Semester', 'W', '12:30:00', '15:30:00', 9, 7, '2026-03-16 10:53:11'),
(43, 1, 16, '1st Semester', 'Sa', '07:00:00', '09:00:00', 5, 3, '2026-03-16 10:53:11'),
(44, 1, 17, '1st Semester', 'Sa', '09:00:00', '11:00:00', 10, 9, '2026-03-16 10:53:11'),
(45, 3, 18, '1st Semester', 'M', '07:00:00', '09:00:00', 11, 4, '2026-03-16 10:53:11'),
(46, 3, 19, '1st Semester', 'M', '12:30:00', '15:30:00', 12, 12, '2026-03-16 10:53:11'),
(47, 3, 20, '1st Semester', 'T', '07:00:00', '09:00:00', 13, 11, '2026-03-16 10:53:11'),
(48, 3, 21, '1st Semester', 'T', '09:00:00', '11:00:00', 14, 6, '2026-03-16 10:53:11'),
(49, 3, 22, '1st Semester', 'W', '07:00:00', '09:00:00', 15, 9, '2026-03-16 10:53:11'),
(50, 3, 23, '1st Semester', 'W', '09:00:00', '11:00:00', 16, 7, '2026-03-16 10:53:11'),
(51, 3, 24, '1st Semester', 'Th', '07:00:00', '09:00:00', 1, 15, '2026-03-16 10:53:11'),
(52, 3, 25, '1st Semester', 'F', '10:00:00', '12:00:00', 5, 14, '2026-03-16 10:53:11'),
(53, 3, 26, '1st Semester', 'Sa', '09:00:00', '11:00:00', 2, 15, '2026-03-16 10:53:11'),
(54, 6, 27, '1st Semester', 'M', '07:00:00', '09:00:00', 17, 11, '2026-03-16 10:53:11'),
(55, 6, 28, '1st Semester', 'M', '09:00:00', '11:00:00', 18, 12, '2026-03-16 10:53:11'),
(56, 6, 29, '1st Semester', 'T', '07:00:00', '09:00:00', 19, 1, '2026-03-16 10:53:11'),
(57, 6, 30, '1st Semester', 'T', '09:00:00', '11:00:00', 3, 4, '2026-03-16 10:53:11'),
(58, 6, 31, '1st Semester', 'W', '12:30:00', '15:30:00', 20, 11, '2026-03-16 10:53:11'),
(59, 6, 32, '1st Semester', 'W', '09:00:00', '11:00:00', 21, 13, '2026-03-16 10:53:11'),
(60, 6, 33, '1st Semester', 'Th', '09:00:00', '11:00:00', 22, 15, '2026-03-16 10:53:11'),
(61, 7, 34, '1st Semester', 'M', '09:00:00', '11:00:00', 23, 11, '2026-03-16 10:53:11'),
(62, 7, 35, '1st Semester', 'T', '07:00:00', '09:00:00', 24, 15, '2026-03-16 10:53:11'),
(63, 7, 36, '1st Semester', 'T', '09:00:00', '11:00:00', 25, 12, '2026-03-16 10:53:11'),
(64, 7, 37, '1st Semester', 'W', '07:00:00', '09:00:00', 26, 11, '2026-03-16 10:53:11'),
(65, 7, 38, '1st Semester', 'Th', '09:00:00', '11:00:00', 1, 1, '2026-03-16 10:53:11'),
(66, 7, 39, '1st Semester', 'F', '07:00:00', '09:00:00', 2, 12, '2026-03-16 10:53:11'),
(69, 1, 75, '1st Semester', 'M', '15:30:00', '18:30:00', 8, 12, '2026-03-16 16:24:29');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `id` int(11) NOT NULL,
  `course_id` int(11) NOT NULL,
  `year_level` varchar(20) NOT NULL,
  `name` varchar(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`id`, `course_id`, `year_level`, `name`, `created_at`) VALUES
(1, 1, '1st Year', 'BSIS 1101', '2026-03-14 14:03:07'),
(2, 1, '1st Year', 'BSIS 1102', '2026-03-14 14:03:07'),
(3, 1, '2nd Year', 'BSIS 2101', '2026-03-14 14:03:07'),
(6, 1, '3rd Year', 'BSIS 3101', '2026-03-16 10:46:28'),
(7, 1, '4th Year', 'BSIS 4101', '2026-03-16 10:46:28'),
(8, 1, '1st Year', 'BSIS 1103', '2026-03-16 13:07:23'),
(10, 1, '2nd Year', 'BSIS 2102', '2026-03-16 13:13:51');

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `course_id` int(11) DEFAULT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `year_level` varchar(20) NOT NULL,
  `semester` enum('1st Semester','2nd Semester') NOT NULL,
  `units` tinyint(4) DEFAULT 3,
  `hours_week` tinyint(4) DEFAULT 3,
  `days_week` tinyint(4) DEFAULT 3,
  `professor_id` int(11) DEFAULT NULL,
  `subject_type` varchar(30) NOT NULL DEFAULT 'Regular',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subjects`
--

INSERT INTO `subjects` (`id`, `course_id`, `code`, `name`, `year_level`, `semester`, `units`, `hours_week`, `days_week`, `professor_id`, `subject_type`, `created_at`) VALUES
(9, 1, 'CC101', 'Introduction to Computing', '1st Year', '1st Semester', 3, 3, 1, NULL, 'Regular', '2026-03-16 07:32:31'),
(10, 1, 'CC102', 'Computer Programming 1', '1st Year', '1st Semester', 3, 3, 1, NULL, 'Regular', '2026-03-16 07:32:31'),
(11, 1, 'GE1', 'Understanding the Self', '1st Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(12, 1, 'GE2', 'Mathematics in the Modern World', '1st Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(13, 1, 'GE3', 'Science, Technology and Society', '1st Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(14, 1, 'GE4', 'Purposive Communication', '1st Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(15, 1, 'FIL1', 'Kontekstwalisadong Komunikasyon sa Filipino', '1st Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(16, 1, 'PE1', 'Physical Education 1', '1st Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(17, 1, 'NSTP1', 'National Service Training 1', '1st Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(18, 1, 'CC104', 'Data Structure and Algorithms', '2nd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(19, 1, 'IS102', 'Professional Issues in Information System', '2nd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(20, 1, 'IS103', 'IT Infrastructure and Network Technologies', '2nd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(21, 1, 'GE8', 'The Contemporary World', '2nd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(22, 1, 'GE9', 'Life and Works of Rizal', '2nd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(23, 1, 'GEELEC1', 'Living in the IT Era', '2nd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(24, 1, 'FIL3', 'Sosyedad at Literaturang Panlipunan', '2nd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(25, 1, 'PE3', 'Physical Education 3', '2nd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(26, 1, 'DM101', 'Organization and Management Concepts', '2nd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(27, 1, 'IS106', 'IS Project Management', '3rd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(28, 1, 'ADV04', 'IS Information and New Technologies', '3rd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(29, 1, 'ISELEC1', 'Human Computer Interaction', '3rd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(30, 1, 'ADV03', 'IT Audit Controls', '3rd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(31, 1, 'CC106', 'Application Development and Emerging Technologies 1', '3rd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(32, 1, 'QUAMET', 'Quantitative Methods', '3rd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(33, 1, 'DM103', 'Business Process Management', '3rd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(34, 1, 'ADV07', 'IS Project Management 2', '4th Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(35, 1, 'ADV12', 'Customer Relationship Management', '4th Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(36, 1, 'GEELEC3', 'Technopreneurship', '4th Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(37, 1, 'ADV08', 'Data Mining', '4th Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(38, 1, 'CAP102', 'Capstone Project 2', '4th Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(39, 1, 'ADV11', 'Supply Chain Management', '4th Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 07:32:31'),
(40, 4, 'GE1', 'Understanding the Self', '1st Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(41, 4, 'GE2', 'Mathematics in the Modern World', '1st Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(42, 4, 'GE3', 'Science, Technology and Society', '1st Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(43, 4, 'GE4', 'Purposive Communication', '1st Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(44, 4, 'GE5', 'Readings in Philippine History', '1st Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(45, 4, 'GE6', 'Art Appreciation', '1st Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(46, 4, 'GE9', 'Life and Works of Rizal', '1st Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(47, 4, 'FIL1', 'Kontekstwalisadong Komunikasyon sa Filipino', '1st Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(48, 4, 'PE1', 'Physical Education 1', '1st Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(49, 4, 'NSTP1', 'National Service Training Program 1', '1st Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(50, 4, 'INTROAFA', 'Introduction to Agri-Fishery and Arts', '2nd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(51, 4, 'INTROIA', 'Introduction to Industrial Arts', '2nd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(52, 4, 'ENTREP', 'Entrepreneurship', '2nd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(53, 4, 'FSM111', 'Occupational Safety and Health Practices', '2nd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(54, 4, 'FIL3', 'Sosyedad at Literaturang Panlipunan', '2nd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(55, 4, 'PE3', 'Physical Education 3', '2nd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(56, 4, 'PEDC5', 'Facilitating Learner-Centered Teaching', '2nd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(57, 4, 'PEDC6', 'The Andragogy of Learning', '2nd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(58, 4, 'PEDC7', 'Assessment of Learning 1', '2nd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(59, 4, 'PEDC9', 'Technology of Teaching and Learning 1', '2nd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(60, 4, 'PEDC10', 'Building and Emerging New Literacies', '2nd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(61, 4, 'FSM121', 'Meal Management', '3rd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(62, 4, 'FSM122', 'Advanced Baking', '3rd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(63, 4, 'FSM211', 'Food Processing', '3rd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(64, 4, 'FSM212', 'Basic Baking', '3rd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(65, 4, 'FSM221', 'International Cuisine', '3rd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(66, 4, 'FSM222', 'Quantity Cookery', '3rd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(67, 4, 'TR1', 'Technology Research 1', '3rd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(68, 4, 'TCCIA', 'Teaching Common Competencies in IA', '3rd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(69, 4, 'TCCICT', 'Teaching Common Competencies in ICT', '3rd Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(70, 4, 'FS1', 'Field Study 1', '4th Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(71, 4, 'FS2', 'Field Study 2', '4th Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(72, 4, 'SIT', 'Supervised Industrial Training', '4th Year', '1st Semester', 3, 3, 3, NULL, 'Regular', '2026-03-16 09:45:44'),
(75, NULL, 'CONSULT', 'Consultation Hours', 'All', '', 3, 3, 1, NULL, 'Consultation', '2026-03-16 16:18:45');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `consultation_hours`
--
ALTER TABLE `consultation_hours`
  ADD PRIMARY KEY (`id`),
  ADD KEY `professor_id` (`professor_id`);

--
-- Indexes for table `courses`
--
ALTER TABLE `courses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `professors`
--
ALTER TABLE `professors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_id` (`employee_id`);

--
-- Indexes for table `rooms`
--
ALTER TABLE `rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `section_id` (`section_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `room_id` (`room_id`),
  ADD KEY `professor_id` (`professor_id`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `course_id` (`course_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `course_id` (`course_id`,`code`,`semester`),
  ADD KEY `course_id_2` (`course_id`),
  ADD KEY `professor_id` (`professor_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `consultation_hours`
--
ALTER TABLE `consultation_hours`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `courses`
--
ALTER TABLE `courses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `professors`
--
ALTER TABLE `professors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `rooms`
--
ALTER TABLE `rooms`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=76;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `consultation_hours`
--
ALTER TABLE `consultation_hours`
  ADD CONSTRAINT `consultation_hours_ibfk_1` FOREIGN KEY (`professor_id`) REFERENCES `professors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`section_id`) REFERENCES `sections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedules_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedules_ibfk_3` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedules_ibfk_4` FOREIGN KEY (`professor_id`) REFERENCES `professors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subjects_ibfk_2` FOREIGN KEY (`professor_id`) REFERENCES `professors` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
