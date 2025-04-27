-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 09, 2025 at 06:23 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `wallstreet_budget`
--

-- --------------------------------------------------------

--
-- Table structure for table `budgets`
--

CREATE TABLE `budgets` (
  `budget_id` int NOT NULL,
  `user_id` int NOT NULL,
  `category_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `expense_id` int NOT NULL,
  `user_id` int NOT NULL,
  `category_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `expense_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

CREATE TABLE `expense_categories` (
  `category_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `income`
--

CREATE TABLE `income` (
  `income_id` int NOT NULL,
  `user_id` int NOT NULL,
  `category_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `income_date` date NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `income_categories`
--

CREATE TABLE `income_categories` (
  `category_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `name` varchar(50) NOT NULL,
  `description` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_admin` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `budgets`
--
ALTER TABLE `budgets`
  ADD PRIMARY KEY (`budget_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`expense_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `income`
--
ALTER TABLE `income`
  ADD PRIMARY KEY (`income_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `income_categories`
--
ALTER TABLE `income_categories`
  ADD PRIMARY KEY (`category_id`),
  ADD KEY `user_id` (`user_id`);

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
-- AUTO_INCREMENT for table `budgets`
--
ALTER TABLE `budgets`
  MODIFY `budget_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `expense_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `category_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `income`
--
ALTER TABLE `income`
  MODIFY `income_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `income_categories`
--
ALTER TABLE `income_categories`
  MODIFY `category_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `budgets`
--
ALTER TABLE `budgets`
  ADD CONSTRAINT `budgets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `budgets_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`category_id`);

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `expenses_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`category_id`);

--
-- Constraints for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD CONSTRAINT `expense_categories_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `income`
--
ALTER TABLE `income`
  ADD CONSTRAINT `income_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `income_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `income_categories` (`category_id`);

--
-- Constraints for table `income_categories`
--
ALTER TABLE `income_categories`
  ADD CONSTRAINT `income_categories_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
