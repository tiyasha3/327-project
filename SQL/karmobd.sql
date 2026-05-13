-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 02, 2026 at 07:20 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `karmobd`
--

-- --------------------------------------------------------

--
-- Table structure for table `applications`
--

CREATE TABLE `applications` (
  `application_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `status` enum('pending','reviewed','accepted','rejected') DEFAULT 'pending',
  `apply_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `applications`
--

INSERT INTO `applications` (`application_id`, `user_id`, `job_id`, `message`, `status`, `apply_date`) VALUES
(2, 3, 2, 'I can help with posts and product photos after school.', 'accepted', '2026-05-01 19:58:19'),
(4, 15, 7, '', 'accepted', '2026-05-01 20:57:21');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `job_id` int(11) NOT NULL,
  `employer_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text NOT NULL,
  `salary` decimal(10,2) DEFAULT 0.00,
  `category` varchar(80) DEFAULT 'General',
  `location` varchar(120) DEFAULT NULL,
  `duration` varchar(80) DEFAULT 'Flexible',
  `status` enum('open','closed','filled') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `jobs`
--

INSERT INTO `jobs` (`job_id`, `employer_id`, `title`, `description`, `salary`, `category`, `location`, `duration`, `status`, `created_at`) VALUES
(2, 4, 'Social Media Helper', 'Help a local bakery schedule posts, reply to simple comments, and take product photos after school.', 1200.00, 'Tech', 'Uttara, Dhaka', 'Part-time', 'open', '2026-05-01 19:58:19'),
(3, 4, 'SSC Math Tutor', 'Tutor a younger student twice a week for algebra and geometry practice. Guardian will be present.', 900.00, 'Teaching', 'Dhanmondi, Dhaka', 'Recurring', 'open', '2026-05-01 19:58:19'),
(4, 4, 'Event Photography Assistant', 'Assist with taking candid photos at a small family event. Camera experience preferred.', 1800.00, 'Photography', 'Gulshan, Dhaka', 'Weekend', 'open', '2026-05-01 19:58:19'),
(5, 4, 'Poster Designer', 'Create simple Canva posters for a neighborhood clothing shop.', 700.00, 'Design', 'Chattogram', 'Flexible', 'open', '2026-05-01 19:58:19'),
(7, 16, 'Wall Art', 'Wall art infront of my house.', 8325.00, 'Art', 'Mirpur,Dhaka', 'Flexible', 'open', '2026-05-01 20:55:34');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `message_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `sent_at` datetime DEFAULT current_timestamp(),
  `is_read` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`message_id`, `sender_id`, `receiver_id`, `job_id`, `content`, `sent_at`, `is_read`) VALUES
(3, 3, 4, 2, 'Hi! Thanks for accepting my application. I can start this weekend.', '2026-05-02 01:58:19', 0),
(4, 4, 3, 2, 'Great. Please bring a phone with a good camera and a parent contact number.', '2026-05-02 01:58:19', 1);

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `review_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `reviewed_id` int(11) NOT NULL,
  `job_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `age` int(11) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('teen','employer','admin') DEFAULT 'teen',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `avatar` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `skills` text DEFAULT NULL,
  `location` varchar(120) DEFAULT NULL,
  `verified` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `name`, `email`, `password`, `age`, `phone`, `role`, `created_at`, `avatar`, `bio`, `skills`, `location`, `verified`) VALUES
(3, 'Demo Teen', 'demo.teen@karmobd.com', '$2y$10$sDRxw5ffePQJkCMiLLBwHeTH3Tf0WL3PDevzMB6fbdIeUubDiM3EO', 17, '01700000001', 'teen', '2026-05-01 19:50:46', NULL, 'I am a teen looking for part-time work in Dhaka. Skilled in tutoring, photography, and social media.', 'Tutoring, Photography, Social Media, Graphic Design', 'Dhaka', 1),
(4, 'Demo Employer', 'demo.employer@karmobd.com', '$2y$10$42SK2fAEpqJ1Abn1WNO7uuZVsehPmL7XdwfcRZ/.Ruc9o.vMNeM4a', 35, '01700000002', 'employer', '2026-05-01 19:50:46', NULL, 'We are a small Dhaka business looking for reliable young people for flexible, supervised work.', '', 'Gulshan, Dhaka', 1),
(15, 'Hamim Ahmed', 'hamim22@gmail.com', '$2y$10$Ypdp2b2.JtbuUVDAZxK6eeLPuDjAOIV9WTeUvRwsKfh.NuZVCFg5O', 14, '0111111111111111', 'teen', '2026-05-01 20:51:48', NULL, 'Hello', 'Artist', 'Mirpur, Dhaka', 0),
(16, 'Rahim Ahmed', 'rahim22@gmail.com', '$2y$10$V2XtJpGHnYnonxdxK3WjBeMTB4hOXaIW8ptBJlFpGkvnmBBid0Gzy', 0, '022222222222', 'employer', '2026-05-01 20:53:46', NULL, '', 'Artist', 'Mirpur, Dhaka', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `applications`
--
ALTER TABLE `applications`
  ADD PRIMARY KEY (`application_id`),
  ADD UNIQUE KEY `uniq_application_user_job` (`user_id`,`job_id`),
  ADD KEY `job_id` (`job_id`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`job_id`),
  ADD KEY `employer_id` (`employer_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`message_id`),
  ADD KEY `idx_job` (`job_id`),
  ADD KEY `idx_sender` (`sender_id`),
  ADD KEY `idx_receiver` (`receiver_id`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`review_id`),
  ADD UNIQUE KEY `uniq_review` (`reviewer_id`,`reviewed_id`,`job_id`),
  ADD KEY `idx_reviewed` (`reviewed_id`),
  ADD KEY `idx_reviewer` (`reviewer_id`),
  ADD KEY `idx_review_job` (`job_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `applications`
--
ALTER TABLE `applications`
  MODIFY `application_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `job_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `message_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `review_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `applications`
--
ALTER TABLE `applications`
  ADD CONSTRAINT `applications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `applications_ibfk_2` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`job_id`) ON DELETE CASCADE;

--
-- Constraints for table `jobs`
--
ALTER TABLE `jobs`
  ADD CONSTRAINT `jobs_ibfk_1` FOREIGN KEY (`employer_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`job_id`) REFERENCES `jobs` (`job_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
