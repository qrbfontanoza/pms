-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Aug 05, 2026 at 02:54 PM
-- Server version: 8.4.3
-- PHP Version: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pms_connection`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `name`, `email`, `password`) VALUES
(1, 'Admin', 'admin@pms.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `vehicle_id` int NOT NULL,
  `booking_ref` varchar(32) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `rental_date` date NOT NULL,
  `return_date` date NOT NULL,
  `pickup_time` time DEFAULT NULL,
  `dropoff_time` time DEFAULT NULL,
  `days` int DEFAULT NULL,
  `rate` decimal(10,2) DEFAULT NULL,
  `discount` decimal(10,2) DEFAULT '0.00',
  `total_amount` decimal(10,2) DEFAULT NULL,
  `contact_number` varchar(32) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` enum('completed','pending','confirmed','cancelled') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `voucher_id` int DEFAULT NULL,
  `age` int NOT NULL DEFAULT '18',
  `license_file` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `paid` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `user_id`, `vehicle_id`, `booking_ref`, `rental_date`, `return_date`, `pickup_time`, `dropoff_time`, `days`, `rate`, `discount`, `total_amount`, `contact_number`, `status`, `created_at`, `voucher_id`, `age`, `license_file`, `paid`) VALUES
(37, 3, 19, 'B17624367024149', '2025-11-07', '2025-11-07', '21:44:00', '21:44:00', 1, 5500.00, 0.00, 5500.00, '0999988777', 'pending', '2025-11-06 13:45:02', NULL, 18, NULL, 0),
(38, 3, 23, 'B17624373743387', '2025-11-07', '2025-11-07', '21:55:00', '21:56:00', 1, 6990.00, 0.00, 6990.00, '0999988777', 'confirmed', '2025-11-06 13:56:14', NULL, 18, NULL, 0),
(39, 3, 23, 'B17624375564604', '2025-11-07', '2025-11-08', '21:59:00', '21:59:00', 1, 6990.00, 0.00, 6990.00, '0999988777', 'confirmed', '2025-11-06 13:59:16', NULL, 18, NULL, 7000),
(40, 5, 4, 'B17624411119862', '2025-11-07', '2025-11-08', '22:57:00', '22:57:00', 1, 4500.00, 0.00, 4500.00, '0999988777', 'cancelled', '2025-11-06 14:58:31', NULL, 18, NULL, 7000),
(41, 5, 4, 'B17624412739303', '2025-11-03', '2025-11-08', '23:01:00', '23:01:00', 1, 4500.00, 0.00, 4500.00, '0999988777', 'confirmed', '2025-11-06 15:01:13', NULL, 18, NULL, 7000),
(42, 5, 34, 'B17624417381095', '2025-11-07', '2025-11-08', '23:08:00', '23:08:00', 1, 800.00, 300.00, 500.00, '0999988777', 'confirmed', '2025-11-06 15:08:58', 3, 18, NULL, 1000),
(43, 5, 37, 'B17624420954747', '2025-11-07', '2025-11-08', '23:14:00', '23:14:00', 1, 1000.00, 300.00, 700.00, '0999988777', 'confirmed', '2025-11-06 15:14:55', 3, 18, NULL, 1000),
(44, 5, 13, 'B17624433728778', '2025-11-07', '2025-11-08', '23:35:00', '23:35:00', 1, 5800.00, 0.00, 5800.00, '0999988777', 'pending', '2025-11-06 15:36:12', NULL, 18, NULL, 8000);

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `message` text COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int NOT NULL,
  `booking_id` int NOT NULL,
  `transaction_ref` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `paid_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `booking_id`, `transaction_ref`, `amount`, `paid_at`) VALUES
(9, 39, 'TX17624382182150', 6990.00, '2025-11-06 14:10:18'),
(10, 38, 'TX17624398214176', 6990.00, '2025-11-06 14:37:01'),
(11, 41, 'TX17624413832148', 4500.00, '2025-11-06 15:03:03'),
(12, 42, 'TX17624418063408', 500.00, '2025-11-06 15:10:06'),
(13, 43, 'TX17624421516048', 700.00, '2025-11-06 15:15:51');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` varchar(50) COLLATE utf8mb4_general_ci DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `role`, `created_at`) VALUES
(3, 'Gabriel Santos', 'qgksantos@tip.edu.ph', '$2y$10$9W8W2yNnVWKQty8eHRN34.sW6JBfK7aG2vrBj1p/rKz.ejo6jSWTO', 'user', '2025-11-06 12:22:51'),
(5, 'testlang1', 'testlang@gmail.com', '$2y$10$.hXc98.AkUFdW9qWWFN5NeKs2AnX2a.pxSS2T6AA8jpXznbf7.E5q', 'user', '2025-11-06 14:56:42');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int NOT NULL,
  `title` varchar(150) COLLATE utf8mb4_general_ci NOT NULL,
  `category` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `seats` int NOT NULL DEFAULT '4',
  `fuel` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Gasoline',
  `transmission` varchar(30) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Automatic',
  `price_per_day` int NOT NULL DEFAULT '0',
  `units_total` int NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `thumbnail` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `slug` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `title`, `category`, `seats`, `fuel`, `transmission`, `price_per_day`, `units_total`, `is_active`, `thumbnail`, `created_at`, `slug`) VALUES
(1, 'Honda Civic', 'Sedan', 4, 'Gasoline', 'Manual', 3400, 2, 1, 'Civic.jpg', '2025-11-05 07:49:25', NULL),
(2, 'Toyoto Corolla', 'Sedan', 4, 'Gasoline', 'Manual', 2199, 2, 1, 'ToyotaCorolla.jfif', '2025-11-05 07:55:57', NULL),
(3, 'Mazda 6', 'Sedan', 4, 'Gasoline', 'Automatic', 2700, 1, 1, 'Mazda6.jpg', '2025-11-05 07:57:56', NULL),
(4, 'Ford Everest', 'SUV', 7, 'Diesel', 'Automatic', 4500, 0, 0, 'FordEverest.jpg', '2025-11-05 08:18:08', NULL),
(5, 'Mitsubishi Montero Sport', 'SUV', 7, 'Gasoline', 'Automatic', 4400, 2, 1, 'Mitsubushi.jpg', '2025-11-05 08:18:41', NULL),
(6, 'Nissan Terra', 'SUV', 7, 'Gasoline', 'Automatic', 4570, 1, 1, 'NissanTerra.jpg', '2025-11-05 08:19:26', NULL),
(7, 'Nissan Urvan', 'Van', 15, 'Diesel', 'Automatic', 4500, 1, 1, 'Nissan Urvan.jfif', '2025-11-05 08:23:30', NULL),
(8, 'Hyundai H350', 'Van', 15, 'Diesel', 'Automatic', 4500, 2, 1, 'Hyundai H350.jfif', '2025-11-05 08:24:06', NULL),
(9, 'Maxus V80', 'Van', 15, 'Gasoline', 'Automatic', 4800, 1, 1, 'Maxus V80.jpg', '2025-11-05 08:25:01', NULL),
(10, 'Honda Click 125i', 'Scooter', 2, 'Diesel', 'Automatic', 300, 1, 1, 'Honda Click 125i.jfif', '2025-11-05 08:33:49', NULL),
(11, 'Vespa Sprint', 'Scooter', 2, 'Diesel', 'Automatic', 450, 3, 1, 'Vespa Sprint.jpg', '2025-11-05 08:34:17', NULL),
(12, 'Yamaha NMAX', 'Scooter', 2, 'Diesel', 'Automatic', 700, 1, 1, 'Yamaha NMAX.jfif', '2025-11-05 08:34:45', NULL),
(13, 'Ford Ranged Raptor', 'Pickup', 8, 'Diesel', 'Automatic', 5800, 1, 1, '2024-Ford-Ranger-Raptor-14.jpg', '2025-11-05 08:36:12', NULL),
(14, 'Mitsubishi Strada', 'Pickup', 8, 'Diesel', 'Automatic', 7800, 2, 1, 'Mitsubishi Strada.png', '2025-11-05 08:36:43', NULL),
(15, 'Nissan Navarra', 'Pickup', 18, 'Diesel', 'Automatic', 7000, 1, 1, 'Nissan Navara.jfif', '2025-11-05 08:37:10', NULL),
(16, 'Hyundai Elantra', 'Sedan', 4, 'Gasoline', 'Automatic', 8990, 1, 1, 'Hyundai Elantra.jfif', '2025-11-05 09:11:52', NULL),
(17, 'Nissan Almera', 'Sedan', 4, 'Hybrid', 'Manual', 4400, 2, 1, 'Nissan Almera.jpg', '2025-11-05 09:23:22', NULL),
(18, 'Kia Forte', 'Sedan', 4, 'Diesel', 'Automatic', 4900, 2, 1, 'Kia Forte.jpg', '2025-11-05 09:24:13', NULL),
(19, 'Chevrolet Cruze', 'Sedan', 4, 'Gasoline', 'Automatic', 5500, 3, 1, 'Chevrolet Cruze.jfif', '2025-11-05 09:24:43', NULL),
(20, 'Toyota RAV4', 'SUV', 6, 'Gasoline', 'Automatic', 4444, 2, 1, 'Toyota RAV4.jpg', '2025-11-05 09:26:38', NULL),
(21, 'Honda CR-V', 'SUV', 6, 'Gasoline', 'Automatic', 6800, 4, 1, 'Honda CR-V.jpg', '2025-11-05 09:27:08', NULL),
(22, 'Subaru Forester', 'SUV', 6, 'Diesel', 'Automatic', 7777, 3, 1, 'Subaru Forester.jpg', '2025-11-05 09:27:56', NULL),
(23, 'Chevrolet Trailblazer', 'SUV', 6, 'Diesel', 'Automatic', 6990, 1, 0, 'Chevrolet Trailblazer.jfif', '2025-11-05 09:28:26', NULL),
(24, 'Foton TransVan', 'Van', 6, 'Diesel', 'Automatic', 7850, 3, 1, 'Foton TransVan.jpg', '2025-11-05 09:29:16', NULL),
(25, 'Maxus G10', 'Van', 6, 'Diesel', 'Automatic', 5890, 3, 1, 'Maxus G10.jpg', '2025-11-05 09:29:42', NULL),
(26, 'Hyundai Staria', 'Van', 6, 'Diesel', 'Automatic', 8990, 3, 1, 'Hyundai Staria.jfif', '2025-11-05 09:31:45', NULL),
(27, 'Suzuki Ertiga', 'Minivan', 6, 'Diesel', 'Automatic', 6000, 2, 1, 'Suzuki Ertiga.jfif', '2025-11-05 09:32:38', NULL),
(28, 'Kia Carens', 'Minivan', 6, 'Gasoline', 'Automatic', 8000, 2, 1, 'Kia Carens.jpg', '2025-11-05 09:33:24', NULL),
(29, 'Hyundai Custin', 'Minivan', 10, 'Diesel', 'Automatic', 8650, 2, 1, 'Hyundai Custin.jfif', '2025-11-05 09:34:19', NULL),
(30, 'Toyota Sienta', 'Minivan', 10, 'Diesel', 'Automatic', 8750, 4, 1, 'Toyota Sienta.jpg', '2025-11-05 09:34:57', NULL),
(31, 'Honda PCX160', 'Scooter', 2, 'Diesel', 'Automatic', 600, 3, 1, 'Honda PCX160.jfif', '2025-11-05 09:36:12', NULL),
(32, 'Yamaha Aerox', 'Scooter', 2, 'Diesel', 'Automatic', 700, 4, 1, 'Yamaha Aerox.jpeg', '2025-11-05 09:37:20', NULL),
(34, 'Honda Beat', 'Scooter', 2, 'Diesel', 'Automatic', 800, 7, 1, 'Honda Beat.jfif', '2025-11-05 09:38:48', NULL),
(37, 'Toyota', 'SUV', 4, 'Gasoline', 'Automatic', 1000, 0, 0, 'Toyota-Innova.jpg', '2025-11-06 15:13:27', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vouchers`
--

CREATE TABLE `vouchers` (
  `id` int NOT NULL,
  `code` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT NULL,
  `discount_pct` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `single_use` tinyint(1) DEFAULT '1',
  `usage_count` int DEFAULT '0',
  `usage_limit` int DEFAULT '1',
  `used_at` datetime DEFAULT NULL,
  `used_by` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vouchers`
--

INSERT INTO `vouchers` (`id`, `code`, `discount_amount`, `discount_pct`, `is_active`, `single_use`, `usage_count`, `usage_limit`, `used_at`, `used_by`, `created_at`) VALUES
(3, 'SAVE300', 300.00, 0, 1, 1, 2, 3, '2025-11-06 23:14:55', 5, '2025-11-06 15:07:16');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vehicle_id` (`vehicle_id`),
  ADD KEY `status` (`status`),
  ADD KEY `rental_date` (`rental_date`),
  ADD KEY `return_date` (`return_date`),
  ADD KEY `bookings_user_fk` (`user_id`),
  ADD KEY `bookings_voucher_fk` (`voucher_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_ref` (`transaction_ref`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vouchers`
--
ALTER TABLE `vouchers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `used_by` (`used_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `vouchers`
--
ALTER TABLE `vouchers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_user_fk` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_vehicle_fk` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookings_voucher_fk` FOREIGN KEY (`voucher_id`) REFERENCES `vouchers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_booking_fk` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `vouchers`
--
ALTER TABLE `vouchers`
  ADD CONSTRAINT `vouchers_ibfk_1` FOREIGN KEY (`used_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
