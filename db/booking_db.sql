-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 05, 2026 at 06:49 AM
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
-- Database: `booking_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `building_id` int(11) DEFAULT NULL,
  `booker_name` varchar(255) NOT NULL,
  `booker_email` varchar(255) DEFAULT NULL,
  `booker_phone` varchar(50) DEFAULT NULL,
  `organization` varchar(255) DEFAULT NULL,
  `event_name` varchar(255) NOT NULL,
  `event_description` text DEFAULT NULL,
  `booking_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `proposal_file` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `building_id`, `booker_name`, `booker_email`, `booker_phone`, `organization`, `event_name`, `event_description`, `booking_date`, `start_time`, `end_time`, `status`, `admin_notes`, `proposal_file`, `created_at`, `updated_at`) VALUES
(1, 1, 'Amat', 'setda@gmail.com', '0813333333', 'Setda', 'Coba', 'coba', '2026-03-08', '07:30:00', '11:55:00', 'approved', NULL, NULL, '2026-03-05 03:25:45', '2026-03-05 03:40:35'),
(2, 1, 'H. Guplah Shaleh', 'guplah.s27@gmail.com', '081235251158', 'PWRI', 'Acara Reuni ', '', '2026-03-11', '08:30:00', '14:00:00', 'approved', NULL, 'proposal_69aa285d3f315.pdf', '2026-03-06 01:05:33', '2026-03-06 01:06:05'),
(5, 2, 'H. Amatt', 'amatbanyak@gmail.com', '081235251158', 'PPNI', 'Baramian haja', '', '2026-03-12', '07:30:00', '12:00:00', 'approved', NULL, 'proposal_69ae32d3b074c.pdf', '2026-03-09 02:39:15', '2026-03-09 02:40:36'),
(7, 1, 'Syuhada', 'harysyuhada000@gmail.com', '081274708476', 'NKRI', 'Koordinasi ', 'Koordinasi perserikatan NKRI', '2026-04-27', '20:00:00', '22:00:00', 'approved', NULL, NULL, '2026-04-21 04:50:26', '2026-04-24 01:27:39'),
(9, 3, 'SSS', 'setdahstbagianumum@gmail.com', '0812', 'SSS', 'SSS', 'SSS', '2026-05-11', '08:00:00', '10:00:00', 'approved', NULL, NULL, '2026-05-05 04:29:57', '2026-05-05 04:30:13'),
(10, 3, 'AAA', NULL, '0812', 'AAA', 'AAA', NULL, '2026-05-12', '09:00:00', '10:00:00', 'approved', 'Aaa', NULL, '2026-05-05 04:47:04', '2026-05-05 04:47:04');

-- --------------------------------------------------------

--
-- Table structure for table `booking_items`
--

CREATE TABLE `booking_items` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `price_at_booking` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `booking_items`
--

INSERT INTO `booking_items` (`id`, `booking_id`, `item_id`, `quantity`, `price_at_booking`) VALUES
(1, 2, 2, 20, 3000.00),
(2, 2, 1, 4, 10000.00),
(3, 2, 3, 1, 500000.00),
(10, 5, 2, 25, 3000.00),
(11, 5, 1, 5, 10000.00),
(12, 5, 3, 1, 500000.00),
(13, 7, 2, 100, 3000.00),
(14, 7, 1, 3, 10000.00),
(15, 7, 3, 1, 500000.00);

-- --------------------------------------------------------

--
-- Table structure for table `buildings`
--

CREATE TABLE `buildings` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `category` enum('gratis','berbayar') NOT NULL DEFAULT 'gratis',
  `description` text DEFAULT NULL,
  `requirements` text DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `location` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `buildings`
--

INSERT INTO `buildings` (`id`, `name`, `price`, `quantity`, `category`, `description`, `requirements`, `capacity`, `location`, `image_url`, `created_at`) VALUES
(1, 'Gedung Balai Rakyat (Malam Hari)', 1500000.00, 1, 'berbayar', 'Balai Rakyat Barabai adalah gedung pertemuan publik dan pusat kegiatan komunitas yang terletak di Kota Barabai', '', 200, 'Jl. Ganesha', 'uploads/buildings/building_69a8f9800ecc8.jpg', '2026-03-05 03:21:10'),
(2, 'Gedung Balai Rakyat (Siang Hari)', 1000000.00, 1, 'berbayar', 'Balai Rakyat Barabai adalah gedung pertemuan publik dan pusat kegiatan komunitas yang terletak di Kota Barabai', '', 200, 'Jl. Ganesha', 'uploads/buildings/building_69aa3364e5f92.png', '2026-03-05 03:21:52'),
(3, 'Auditorium Sekretariat Daerah Kab. Hulu Sungai Tengah', 0.00, 1, 'gratis', 'Auditorium Setda HST (Sekretariat Daerah Kabupaten Hulu Sungai Tengah) adalah fasilitas gedung pertemuan utama yang terletak di lingkungan Kantor Bupati Hulu Sungai Tengah, Barabai, Kalimantan Selatan', '', 100, 'Jl. Perwira No. 1 Barabai Selatan', 'uploads/buildings/building_69aa36c38ddba.jpeg', '2026-03-06 02:06:59'),
(4, 'Gedung Pendopo Kab. Hulu Sungai Tengah', 0.00, 1, 'gratis', 'Gedung ini merupakan bagian dari kompleks rumah dinas Bupati HST dan berfungsi sebagai pusat berbagai kegiatan resmi pemerintahan', '', 150, 'Jl. Bhakti', 'uploads/buildings/building_69aa386079573.png', '2026-03-06 02:13:52'),
(5, 'Ruang Rapat Sekretariat Daerah Kab. Hulu Sungai Tengah', 0.00, 1, 'gratis', 'Ruang Rapat Setda', '', 20, 'Jl. Perwira No. 01 Barabai Selatan', NULL, '2026-03-11 01:25:23');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` varchar(255) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('unpaid','paid','cancelled') NOT NULL DEFAULT 'unpaid',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `booking_id`, `amount`, `status`, `created_at`, `updated_at`) VALUES
('032026-0001', 5, 1625000.00, 'paid', '2026-03-09 02:39:15', '2026-03-09 02:40:43'),
('042026-0001', 7, 2330000.00, 'paid', '2026-04-21 04:50:26', '2026-04-24 01:27:35'),
('1', 1, 1500000.00, 'paid', '2026-03-05 03:25:45', '2026-03-09 02:40:40'),
('2', 2, 2100000.00, 'paid', '2026-03-06 01:05:33', '2026-03-06 02:22:00');

-- --------------------------------------------------------

--
-- Table structure for table `items`
--

CREATE TABLE `items` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `price_per_unit` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `items`
--

INSERT INTO `items` (`id`, `name`, `description`, `price_per_unit`, `created_at`) VALUES
(1, 'Meja', '', 10000.00, '2026-03-05 03:18:40'),
(2, 'Kursi', '', 3000.00, '2026-03-05 03:18:53'),
(3, 'Videotron per Kegiatan', 'Maksimal 2 (dua) jam penayangan per kegiatan', 500000.00, '2026-03-05 03:19:20');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('invoice_counter', '3'),
('last_invoice_month', '04-2026');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','user') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$In.PnxeiHwxysIQO3UjxFOsLH1zgN9efsMYZLu1iw2a7lO9/4cIWm', 'super_admin', '2026-03-05 03:10:24'),
(4, 'tiniumum', '$2y$10$vK6ykzdVd/HxSYr8deO7r.Hce.rtn2VY372rpcn861JXNexaXMtTa', 'admin', '2026-04-21 03:48:07'),
(5, 'prokom', '$2y$10$2U61Ozfs2SMiAE6Zphs.IOPFUtR8seR3ITGgWUnZc9ue3ns5O9osq', 'user', '2026-05-05 03:19:29');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `building_id` (`building_id`);

--
-- Indexes for table `booking_items`
--
ALTER TABLE `booking_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `item_id` (`item_id`);

--
-- Indexes for table `buildings`
--
ALTER TABLE `buildings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `items`
--
ALTER TABLE `items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `booking_items`
--
ALTER TABLE `booking_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `buildings`
--
ALTER TABLE `buildings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `items`
--
ALTER TABLE `items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `booking_items`
--
ALTER TABLE `booking_items`
  ADD CONSTRAINT `booking_items_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `booking_items_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `items` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
