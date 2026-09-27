-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Sep 27, 2026 at 10:18 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.1.25

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `kmp`
--

-- --------------------------------------------------------

--
-- Table structure for table `administrator`
--

CREATE TABLE `administrator` (
  `admin_id` int(10) UNSIGNED NOT NULL,
  `fullname` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `administrator`
--

INSERT INTO `administrator` (`admin_id`, `fullname`, `email`, `password`, `created_at`, `updated_at`) VALUES
(1, 'Kent Kyle B. Cali', 'admin@kmpconsulthub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.YeIVpKzO0FGgeZzYqLh0Uk3z1lRAJVNqK', '2026-08-09 00:36:39', '2026-08-09 00:36:39'),
(2, 'Michael Jude Garde', 'admin@gmail.com', '$2y$10$f0CvAD5swn9Ycv/4V4GSFuZPgW1tDjc800U3zH.6b35vUAG.coVr6', '2026-08-09 00:48:34', '2026-08-09 00:48:34');

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `client_id` int(10) UNSIGNED NOT NULL,
  `company_name` varchar(150) NOT NULL,
  `contact_person` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `address` varchar(255) NOT NULL,
  `industry` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`client_id`, `company_name`, `contact_person`, `email`, `contact_number`, `address`, `industry`, `created_at`, `updated_at`) VALUES
(36, 'Cotabato Trans Rentals', 'Mark Anthony Santos', 'mark.santos@ctransrentals.com', '091784526', 'Cotabato City, Maguindanao del Norte', 'Transportation and Logistics', '2026-09-22 05:51:22', '2026-09-22 05:51:22');

-- --------------------------------------------------------

--
-- Table structure for table `contracts`
--

CREATE TABLE `contracts` (
  `contract_id` int(10) UNSIGNED NOT NULL,
  `contract_number` varchar(30) NOT NULL,
  `quotation_id` int(10) UNSIGNED NOT NULL,
  `request_id` int(10) UNSIGNED NOT NULL,
  `client_id` int(10) UNSIGNED NOT NULL,
  `scope_summary` text DEFAULT NULL,
  `terms_conditions` text DEFAULT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('Draft','Pending Approval','Approved','Rejected') NOT NULL DEFAULT 'Draft',
  `prepared_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `contract_file_name` varchar(255) DEFAULT NULL,
  `contract_file_path` varchar(255) DEFAULT NULL,
  `contract_file_size` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contracts`
--

INSERT INTO `contracts` (`contract_id`, `contract_number`, `quotation_id`, `request_id`, `client_id`, `scope_summary`, `terms_conditions`, `total_amount`, `start_date`, `end_date`, `status`, `prepared_by`, `approved_by`, `approved_at`, `created_at`, `updated_at`, `contract_file_name`, `contract_file_path`, `contract_file_size`) VALUES
(7, 'SOW-2026-0001', 15, 42, 36, 'Conduct a comprehensive fleet risk assessment covering vehicle operations, safety practices, operational risks, and existing risk controls. The project will include risk identification, assessment of current controls, and preparation of a report with recommended improvements.', NULL, 11200.00, '2026-09-23', '2026-09-24', 'Approved', 1, 1, '2026-09-22 06:50:14', '2026-09-22 06:50:14', '2026-09-22 06:50:14', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `contract_revisions`
--

CREATE TABLE `contract_revisions` (
  `revision_id` int(10) UNSIGNED NOT NULL,
  `contract_id` int(10) UNSIGNED NOT NULL,
  `revision_note` text NOT NULL,
  `revised_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `knowledge_documents`
--

CREATE TABLE `knowledge_documents` (
  `document_id` int(10) UNSIGNED NOT NULL,
  `item_type` enum('folder','file') NOT NULL DEFAULT 'file',
  `parent_id` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `category` enum('SOW Template','Contract Template','Best Practice','Proposal Template','Reference Material','Other') DEFAULT NULL,
  `description` text DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_size` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `file_type` varchar(20) DEFAULT NULL,
  `uploaded_by` int(10) UNSIGNED DEFAULT NULL,
  `uploaded_by_role` enum('admin','manager','supervisor','staff') NOT NULL DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `knowledge_documents`
--

INSERT INTO `knowledge_documents` (`document_id`, `item_type`, `parent_id`, `title`, `category`, `description`, `file_name`, `file_path`, `file_size`, `file_type`, `uploaded_by`, `uploaded_by_role`, `created_at`, `updated_at`) VALUES
(11, 'folder', NULL, 'Client Files', NULL, NULL, NULL, NULL, 0, NULL, NULL, 'admin', '2026-09-17 05:49:04', '2026-09-17 05:49:04'),
(12, 'folder', NULL, 'Company Templates', NULL, NULL, NULL, NULL, 0, NULL, NULL, 'admin', '2026-09-17 05:49:04', '2026-09-17 05:49:04'),
(13, 'folder', 11, 'Michael Jude Garde', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-09-17 05:50:21', '2026-09-17 05:50:21'),
(14, 'folder', 11, 'Kent Kyle Cali', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-09-17 06:02:11', '2026-09-17 06:02:11'),
(17, 'folder', 11, 'Juan Carlos', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-09-18 13:32:20', '2026-09-18 13:32:20'),
(18, 'folder', 11, 'Juan Dela Cruz', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-09-18 13:33:58', '2026-09-18 13:33:58'),
(19, 'folder', 11, 'Maria Santos', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-09-18 13:34:26', '2026-09-18 13:34:26'),
(20, 'folder', 11, 'Jose Garcia', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-09-18 13:34:42', '2026-09-18 13:34:42'),
(21, 'folder', 11, 'Ana Reyes', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-09-18 13:34:56', '2026-09-18 13:34:56'),
(22, 'folder', 11, 'Mark Bautista', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-09-18 13:35:29', '2026-09-18 13:35:29'),
(23, 'folder', 11, 'John Mendoza', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-09-18 13:35:41', '2026-09-18 13:35:41'),
(24, 'folder', 11, 'Michael Cruz', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-09-18 13:35:59', '2026-09-18 13:35:59'),
(25, 'folder', 11, 'Angelica Ramos', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-09-18 13:36:50', '2026-09-18 13:36:50'),
(26, 'folder', 11, 'Christian Flores', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-09-18 13:37:02', '2026-09-18 13:37:02'),
(27, 'folder', 11, 'Patricia Aquino', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-09-18 13:37:15', '2026-09-18 13:37:15'),
(28, 'folder', 11, 'Carlo Fernandez', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-09-18 13:37:26', '2026-09-18 13:37:26'),
(29, 'folder', 11, 'Michelle Navarro', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-09-18 13:37:39', '2026-09-18 13:37:39'),
(30, 'folder', 11, 'Daniel Castillo', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-09-18 13:37:51', '2026-09-18 13:37:51'),
(31, 'folder', 11, 'Christine Torres', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-09-18 13:38:04', '2026-09-18 13:38:04'),
(32, 'folder', 11, 'Kevin Villanueva', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-09-18 13:38:19', '2026-09-18 13:38:19'),
(33, 'file', 12, 'GARDE_MICHAEL_JUDE_RESUME.pdf', NULL, NULL, 'GARDE_MICHAEL_JUDE_RESUME.pdf', 'repository/doc_6ab12e234dd804.60280232_GARDE_MICHAEL_JUDE_RESUME.pdf', 1806116, 'pdf', 2, 'admin', '2026-09-21 13:16:19', '2026-09-21 13:16:19'),
(36, 'file', 12, 'Consultant-Proposal-Template.docx', NULL, NULL, 'Consultant-Proposal-Template.docx', 'repository/doc_6ab1968fdc4338.69547789_Consultant-Proposal-Template.docx', 36668, 'docx', 2, 'admin', '2026-09-21 20:41:51', '2026-09-21 20:41:51'),
(37, 'file', 21, '2x2 PHOTO.jpg', NULL, NULL, '9a34803572876920a0d6254bbc0d0346(1).jpg', 'repository/doc_6ab197a20cf838.57862832_9a34803572876920a0d6254bbc0d0346_1_.jpg', 44037, 'jpg', 2, 'admin', '2026-09-21 20:46:26', '2026-09-21 20:47:07'),
(38, 'file', 21, 'SOW-2026-0003.docx', NULL, NULL, 'SOW-2026-0003.docx', 'repository/doc_6ab198007010e3.45606119_SOW-2026-0003.docx', 4923, 'docx', 2, 'admin', '2026-09-21 20:48:00', '2026-09-21 20:48:00'),
(39, 'file', 21, 'Proposal.docx', NULL, NULL, 'Consultant-Proposal-Template.docx', 'repository/doc_6ab198338b7e97.13915112_Consultant-Proposal-Template.docx', 36668, 'docx', 2, 'admin', '2026-09-21 20:48:51', '2026-09-21 20:49:06'),
(44, 'folder', 11, 'ABC Company', NULL, NULL, NULL, NULL, 0, NULL, 1, 'staff', '2026-09-22 01:57:28', '2026-09-22 01:57:28'),
(45, 'file', 44, ' Statement of work (SOW) template_.docx', NULL, NULL, ' Statement of work (SOW) template_.docx', 'repository/doc_6ab1e09d616d64.65539751__Statement_of_work__SOW__template_.docx', 952432, 'docx', 1, 'staff', '2026-09-22 01:57:49', '2026-09-22 01:57:49'),
(46, 'file', 44, '9a34803572876920a0d6254bbc0d0346(1).jpg', NULL, NULL, '9a34803572876920a0d6254bbc0d0346(1).jpg', 'repository/doc_6ab1e0a9d898f8.12389695_9a34803572876920a0d6254bbc0d0346_1_.jpg', 44037, 'jpg', 1, 'staff', '2026-09-22 01:58:01', '2026-09-22 01:58:01'),
(47, 'file', 44, 'Statement of work (SOW) template_.docx', NULL, NULL, 'Statement of work (SOW) template_.docx', 'repository/doc_6ab1e9adad4bf9.06315591_Statement_of_work__SOW__template_.docx', 30826, 'docx', 4, 'manager', '2026-09-22 02:36:29', '2026-09-22 02:36:29'),
(48, 'file', 44, 'Statement of work (SOW) template_.docx', NULL, NULL, 'Statement of work (SOW) template_.docx', 'repository/doc_6ab1e9c843cd83.45112854_Statement_of_work__SOW__template_.docx', 30826, 'docx', 4, 'manager', '2026-09-22 02:36:56', '2026-09-22 02:36:56'),
(49, 'file', 12, 'contracts.docx', NULL, NULL, 'contract.docx', 'repository/doc_6ab235313d4da9.79917704_contract.docx', 41017, 'docx', 2, 'admin', '2026-09-22 07:58:41', '2026-09-22 07:58:51');

-- --------------------------------------------------------

--
-- Table structure for table `quotations`
--

CREATE TABLE `quotations` (
  `quotation_id` int(10) UNSIGNED NOT NULL,
  `quotation_number` varchar(30) NOT NULL,
  `request_id` int(10) UNSIGNED NOT NULL,
  `client_id` int(10) UNSIGNED NOT NULL,
  `project_scope` text DEFAULT NULL,
  `status` enum('Draft','Sent','Approved','Rejected') NOT NULL DEFAULT 'Draft',
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `valid_until` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `prepared_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quotations`
--

INSERT INTO `quotations` (`quotation_id`, `quotation_number`, `request_id`, `client_id`, `project_scope`, `status`, `subtotal`, `tax_rate`, `tax_amount`, `total_amount`, `valid_until`, `notes`, `prepared_by`, `created_at`, `updated_at`) VALUES
(15, 'QT-2026-0001', 42, 36, 'Conduct a comprehensive fleet risk assessment covering vehicle operations, safety practices, operational risks, and existing risk controls. The project will include risk identification, assessment of current controls, and preparation of a report with recommended improvements.', 'Approved', 10000.00, 12.00, 1200.00, 11200.00, '2026-09-23', NULL, 1, '2026-09-22 05:55:00', '2026-09-22 05:55:00');

-- --------------------------------------------------------

--
-- Table structure for table `quotation_items`
--

CREATE TABLE `quotation_items` (
  `item_id` int(10) UNSIGNED NOT NULL,
  `quotation_id` int(10) UNSIGNED NOT NULL,
  `description` text DEFAULT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quotation_items`
--

INSERT INTO `quotation_items` (`item_id`, `quotation_id`, `description`, `quantity`, `unit_price`, `line_total`, `sort_order`) VALUES
(20, 15, 'Prepare a detailed report containing findings and recommended risk controls', 1.00, 10000.00, 10000.00, 0);

-- --------------------------------------------------------

--
-- Table structure for table `service_requests`
--

CREATE TABLE `service_requests` (
  `request_id` int(10) UNSIGNED NOT NULL,
  `client_id` int(10) UNSIGNED NOT NULL,
  `request_title` varchar(150) NOT NULL,
  `request_details` text DEFAULT NULL,
  `required_skill` varchar(100) DEFAULT NULL,
  `status` enum('New','In Progress','Completed','Cancelled') NOT NULL DEFAULT 'New',
  `assigned_to` int(10) UNSIGNED DEFAULT NULL,
  `assigned_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_requests`
--

INSERT INTO `service_requests` (`request_id`, `client_id`, `request_title`, `request_details`, `required_skill`, `status`, `assigned_to`, `assigned_by`, `created_at`, `updated_at`) VALUES
(42, 36, 'Fleet Risk Assessment', NULL, 'Risk Assessment', 'Completed', 1, 2, '2026-09-22 05:51:22', '2026-09-27 07:28:40');

-- --------------------------------------------------------

--
-- Table structure for table `staff_skills`
--

CREATE TABLE `staff_skills` (
  `skill_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `skill_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_skills`
--

INSERT INTO `staff_skills` (`skill_id`, `user_id`, `skill_name`, `created_at`) VALUES
(1, 1, 'Financial Consulting', '2026-08-20 02:21:06'),
(2, 1, 'Tax Advisory', '2026-08-20 02:21:06'),
(3, 1, 'Business Registration', '2026-08-20 02:21:06'),
(4, 1, 'Bookkeeping', '2026-08-20 02:21:06'),
(5, 1, 'Audit Assistance', '2026-08-20 02:21:06'),
(11, 5, 'Marketing Strategy', '2026-08-20 02:21:06'),
(12, 5, 'Market Research', '2026-08-20 02:21:06'),
(13, 5, 'Client Relations Management', '2026-08-20 02:21:06'),
(14, 5, 'Contract Drafting', '2026-08-20 02:21:06'),
(15, 5, 'Legal Compliance Review', '2026-08-20 02:21:06'),
(16, 5, 'Quality Assurance', '2026-08-20 02:21:06'),
(17, 5, 'Risk Assessment', '2026-08-20 02:21:06'),
(18, 3, 'IT Infrastructure Assessment', '2026-09-22 02:11:29'),
(19, 3, 'Business Process Improvement', '2026-09-22 02:11:29'),
(20, 3, 'Data Privacy Compliance', '2026-09-22 02:11:29'),
(21, 3, 'Systems Analysis', '2026-09-22 02:11:29'),
(22, 3, 'Project Documentation', '2026-09-22 02:11:29');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `middlename` varchar(100) DEFAULT NULL,
  `lastname` varchar(100) NOT NULL,
  `birthday` date NOT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `address` varchar(255) NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('manager','supervisor','staff') NOT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `firstname`, `middlename`, `lastname`, `birthday`, `gender`, `address`, `contact_number`, `email`, `password`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Kent Kyle', 'B.', 'Cali', '1999-05-14', 'Male', 'City of Koronadal, South Cotabato', '+639091712345', 'cali@gmail.com', '$2y$10$yL64umsuEI4Xk2squIMO4ubhDoPWSaBOtPb3RJc9o3Hl7dfE4DG32', 'staff', 'Active', '2026-08-09 21:10:42', '2026-08-11 01:14:15'),
(2, 'Alvin', 'G.', 'Salmo', '2000-08-22', 'Male', 'Tupi, South Cotabato', '+639091812345', 'salmo@gmail.com', '$2y$10$JblbCq/TuxTlHRtsuehvnOJDd7Wxr6Iw0KMZ57cXmoJAA9aeVz2Kq', 'supervisor', 'Active', '2026-08-09 21:10:42', '2026-08-11 01:15:26'),
(3, 'Maria', '', 'Santos', '2001-02-10', 'Female', 'Marbel, South Cotabato', '+639091912345', 'staff@kmpconsulthub.com', '$2y$10$6Ly7PP7uFNWWBSe5O.1R6.hDeA7NzYVBkQq.VIsw8/xXT/qNZ5Kgy', 'staff', 'Active', '2026-08-09 21:10:42', '2026-09-22 02:11:29'),
(4, 'Mike', 'Cruz', 'Garde', '2002-02-15', 'Male', 'Banga, South Cotabato', '+639090982828', 'gars@gmail.com', '$2y$10$7Nx4RAPVDXcBEfL1qfP0MexPRouLq9t396qGxx1aq0RbfQ9H70kpa', 'manager', 'Active', '2026-08-09 21:49:28', '2026-08-12 05:45:32'),
(5, 'Juan', 'Cruz', 'Ramirez', '2004-02-14', 'Male', 'Banga, South Cotabato', '+639010101022', 'juan@gmail.com', '$2y$10$2OiaJu4VW2wUppT7LjhnTuVIuPxyRupyv7VTqiTJCYN9PaSp.CQru', 'staff', 'Active', '2026-08-17 02:08:14', '2026-08-17 02:08:14');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `administrator`
--
ALTER TABLE `administrator`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`client_id`);

--
-- Indexes for table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`contract_id`),
  ADD UNIQUE KEY `contract_number` (`contract_number`),
  ADD UNIQUE KEY `quotation_id` (`quotation_id`),
  ADD KEY `fk_contracts_request` (`request_id`),
  ADD KEY `fk_contracts_client` (`client_id`),
  ADD KEY `fk_contracts_prepared_by` (`prepared_by`),
  ADD KEY `fk_contracts_approved_by` (`approved_by`);

--
-- Indexes for table `contract_revisions`
--
ALTER TABLE `contract_revisions`
  ADD PRIMARY KEY (`revision_id`),
  ADD KEY `fk_contract_revisions_contract` (`contract_id`),
  ADD KEY `fk_contract_revisions_user` (`revised_by`);

--
-- Indexes for table `knowledge_documents`
--
ALTER TABLE `knowledge_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `fk_knowledge_documents_parent` (`parent_id`);

--
-- Indexes for table `quotations`
--
ALTER TABLE `quotations`
  ADD PRIMARY KEY (`quotation_id`),
  ADD UNIQUE KEY `quotation_number` (`quotation_number`),
  ADD KEY `fk_quotations_request` (`request_id`),
  ADD KEY `fk_quotations_client` (`client_id`),
  ADD KEY `fk_quotations_prepared_by` (`prepared_by`);

--
-- Indexes for table `quotation_items`
--
ALTER TABLE `quotation_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `fk_quotation_items_quotation` (`quotation_id`);

--
-- Indexes for table `service_requests`
--
ALTER TABLE `service_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `fk_service_requests_client` (`client_id`),
  ADD KEY `fk_service_requests_staff` (`assigned_to`),
  ADD KEY `fk_service_requests_assigned_by` (`assigned_by`);

--
-- Indexes for table `staff_skills`
--
ALTER TABLE `staff_skills`
  ADD PRIMARY KEY (`skill_id`),
  ADD KEY `fk_staff_skills_user` (`user_id`);

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
-- AUTO_INCREMENT for table `administrator`
--
ALTER TABLE `administrator`
  MODIFY `admin_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `client_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `contract_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `contract_revisions`
--
ALTER TABLE `contract_revisions`
  MODIFY `revision_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `knowledge_documents`
--
ALTER TABLE `knowledge_documents`
  MODIFY `document_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `quotations`
--
ALTER TABLE `quotations`
  MODIFY `quotation_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `quotation_items`
--
ALTER TABLE `quotation_items`
  MODIFY `item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `service_requests`
--
ALTER TABLE `service_requests`
  MODIFY `request_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=43;

--
-- AUTO_INCREMENT for table `staff_skills`
--
ALTER TABLE `staff_skills`
  MODIFY `skill_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `contracts`
--
ALTER TABLE `contracts`
  ADD CONSTRAINT `fk_contracts_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_contracts_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_contracts_prepared_by` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_contracts_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`quotation_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_contracts_request` FOREIGN KEY (`request_id`) REFERENCES `service_requests` (`request_id`) ON DELETE CASCADE;

--
-- Constraints for table `contract_revisions`
--
ALTER TABLE `contract_revisions`
  ADD CONSTRAINT `fk_contract_revisions_contract` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`contract_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_contract_revisions_user` FOREIGN KEY (`revised_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `knowledge_documents`
--
ALTER TABLE `knowledge_documents`
  ADD CONSTRAINT `fk_knowledge_documents_parent` FOREIGN KEY (`parent_id`) REFERENCES `knowledge_documents` (`document_id`) ON DELETE CASCADE;

--
-- Constraints for table `quotations`
--
ALTER TABLE `quotations`
  ADD CONSTRAINT `fk_quotations_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_quotations_prepared_by` FOREIGN KEY (`prepared_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_quotations_request` FOREIGN KEY (`request_id`) REFERENCES `service_requests` (`request_id`) ON DELETE CASCADE;

--
-- Constraints for table `quotation_items`
--
ALTER TABLE `quotation_items`
  ADD CONSTRAINT `fk_quotation_items_quotation` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`quotation_id`) ON DELETE CASCADE;

--
-- Constraints for table `service_requests`
--
ALTER TABLE `service_requests`
  ADD CONSTRAINT `fk_service_requests_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`user_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_service_requests_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_service_requests_staff` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `staff_skills`
--
ALTER TABLE `staff_skills`
  ADD CONSTRAINT `fk_staff_skills_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;