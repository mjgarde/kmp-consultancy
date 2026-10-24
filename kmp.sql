-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Oct 22, 2026 at 04:26 PM
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
(41, 'Gryk\'s', 'Gerald Reyes', 'gryks@example.com', '09171234501', 'Gen. Santos Drive, Zone III, Koronadal City', 'Food & Restaurant', '2026-10-19 12:03:20', '2026-10-19 12:03:20'),
(42, 'Kamrynne Skills Pathway & Assessment Center, Inc.', 'Karen Villanueva', 'kamrynneskills@example.com', '09171234502', 'Roxas Street, Zone IV, Koronadal City', 'Education & Training', '2026-10-19 12:03:20', '2026-10-19 12:03:20'),
(43, 'Pabalaque Training Institute', 'Marites Dela Cruz', 'trainingcenter@example.com', '09171234503', 'Brgy. Zone II, Koronadal City', 'Education & Training', '2026-10-19 12:03:20', '2026-10-19 12:03:20'),
(44, 'LD Since 2023', 'Lorna Dizon', 'ld2023@example.com', '09171234504', 'Morales Avenue, Brgy. Morales, Koronadal City', 'Retail', '2026-10-19 12:03:20', '2026-10-19 12:03:20'),
(45, 'Jeri\'s Meats', 'Jerry Atienza', 'jerismeats@example.com', '09171234505', 'Aquino Street, Zone II, Koronadal City', 'Food & Meat Retail', '2026-10-19 12:03:20', '2026-10-19 12:03:20'),
(46, 'KMP Car Rental', 'Kevin Mendoza', 'kmpcarrental@example.com', '09171234506', 'Brgy. Carpenter Hill, Koronadal City', 'Automotive & Rental', '2026-10-19 12:03:20', '2026-10-19 12:03:20'),
(47, 'Clothina Online Shop', 'Clarissa Ramos', 'clothina@example.com', '09171234507', 'Brgy. Zone IV, Koronadal City', 'E-commerce & Fashion', '2026-10-19 12:03:20', '2026-10-19 12:03:20'),
(48, 'ArmAnn Pharma and Medical Supplies Distribution', 'Armando Lopez', 'armann@example.com', '09171234508', 'Roxas Street, Zone IV, Koronadal City', 'Pharmacy & Medical Supplies', '2026-10-19 12:03:20', '2026-10-19 12:03:20'),
(49, 'Eternity', 'Elena Mercado', 'eternity@example.com', '09171234509', 'Brgy. Assumption, Koronadal City', 'Health & Wellness', '2026-10-19 12:03:20', '2026-10-19 12:03:20'),
(50, '8Talk Custom Apparel', 'Eight Santos', '8talk@example.com', '09171234510', 'Brgy. San Isidro, Koronadal City', 'Apparel & Printing', '2026-10-19 12:03:20', '2026-10-19 12:03:20'),
(51, 'CampusTop QQ English', 'Queenie Cruz', 'campustop@example.com', '09171234511', 'Brgy. Zone I, Koronadal City', 'Education & Online Tutoring', '2026-10-19 12:03:20', '2026-10-19 12:03:20'),
(52, 'R&R Designers Shop', 'Rodel Bautista', 'rrdesigners@example.com', '09171234512', 'Gen. Santos Drive, Zone III, Koronadal City', 'Design & Printing', '2026-10-19 12:03:20', '2026-10-19 12:03:20'),
(53, 'Fry Avenue', 'Francis Pascual', 'fryavenue@example.com', '09171234513', 'Morales Avenue, Brgy. Morales, Koronadal City', 'Food & Restaurant', '2026-10-19 12:03:20', '2026-10-19 12:03:20'),
(54, 'Kamrynne Paddle Park (KPP)', 'Kristine Fernandez', 'kpp@example.com', '09171234514', 'Brgy. Assumption, Koronadal City', 'Sports & Recreation', '2026-10-19 12:03:20', '2026-10-19 12:03:20'),
(55, 'Keystone Credit Cooperative', 'Kenneth Garcia', 'keystonecoop@example.com', '09171234515', 'Gen. Santos Drive, Zone III, Koronadal City', 'Finance & Cooperative', '2026-10-19 12:03:20', '2026-10-19 12:03:20'),
(56, 'Office of the Barangay Council, Brgy. Tampakan', 'Hon. Ruben Torres', 'brgytampakan@example.com', '09171234516', 'Tampakan, South Cotabato', 'Government', '2026-10-19 12:03:20', '2026-10-19 12:03:20'),
(57, 'Remegio E. Magan, MD Ophthalmologist', 'Dr. Remegio Magan', 'drmagan@example.com', '09171234517', 'Roxas Street, Zone IV, Koronadal City', 'Healthcare', '2026-10-19 12:03:20', '2026-10-19 12:03:20'),
(58, 'eBenta', 'Ella Benitez', 'ebenta@example.com', '09171234518', 'Brgy. Zone IV, Koronadal City', 'E-commerce', '2026-10-19 12:03:20', '2026-10-19 12:03:20');

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
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('Draft','Approved','Rejected','Revert') NOT NULL DEFAULT 'Draft',
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

INSERT INTO `contracts` (`contract_id`, `contract_number`, `quotation_id`, `request_id`, `client_id`, `scope_summary`, `total_amount`, `start_date`, `end_date`, `status`, `prepared_by`, `approved_by`, `approved_at`, `created_at`, `updated_at`, `contract_file_name`, `contract_file_path`, `contract_file_size`) VALUES
(13, 'SOW-2026-0001', 50, 63, 41, 'Assistance with DTI Business Name Registration, including document preparation, submission, and follow-up.', 3360.00, '2026-10-26', '2026-11-25', 'Draft', 1, NULL, NULL, '2026-10-20 06:53:00', '2026-10-21 06:26:43', NULL, NULL, NULL),
(14, 'SOW-2026-0002', 51, 64, 42, 'Assistance with Mayor\'s Permit Renewal, including document preparation, submission, and follow-up.', 5040.00, '2026-10-26', '2026-11-25', 'Draft', 1, NULL, NULL, '2026-10-19 07:06:00', '2026-10-21 06:26:43', NULL, NULL, NULL),
(15, 'SOW-2026-0003', 52, 65, 43, 'Assistance with BIR Registration (Certificate of Registration), including document preparation, submission, and follow-up.', 6160.00, '2026-10-26', '2026-11-25', 'Draft', 1, NULL, NULL, '2026-10-20 08:19:00', '2026-10-21 06:26:43', NULL, NULL, NULL),
(16, 'SOW-2026-0004', 53, 66, 44, 'Assistance with Barangay Business Clearance Application, including document preparation, submission, and follow-up.', 1904.00, '2026-10-26', '2026-11-25', 'Draft', 1, NULL, NULL, '2026-10-19 09:32:00', '2026-10-21 06:26:43', NULL, NULL, NULL),
(17, 'SOW-2026-0005', 54, 67, 45, 'Assistance with Sanitary Permit Application, including document preparation, submission, and follow-up.', 3472.00, '2026-10-26', '2026-11-25', 'Draft', 1, NULL, NULL, '2026-10-20 05:45:00', '2026-10-21 06:26:43', NULL, NULL, NULL),
(18, 'SOW-2026-0006', 55, 68, 46, 'Assistance with Business Name Renewal (DTI), including document preparation, submission, and follow-up.', 2240.00, '2026-10-26', '2026-11-25', 'Approved', 1, 2, '2026-10-19 12:25:00', '2026-10-19 06:58:00', '2026-10-21 06:26:43', NULL, NULL, NULL),
(19, 'SOW-2026-0007', 56, 69, 47, 'Assistance with Fire Safety Inspection Certificate (FSIC) Assistance, including document preparation, submission, and follow-up.', 4816.00, '2026-10-26', '2026-11-25', 'Draft', 3, NULL, NULL, '2026-10-20 07:11:00', '2026-10-21 06:26:43', NULL, NULL, NULL),
(20, 'SOW-2026-0008', 57, 70, 48, 'Assistance with SEC Registration Basic Guidance, including document preparation, submission, and follow-up.', 3920.00, '2026-10-26', '2026-11-25', 'Draft', 3, NULL, NULL, '2026-10-19 08:24:00', '2026-10-21 06:26:43', NULL, NULL, NULL),
(21, 'SOW-2026-0009', 58, 71, 49, 'Assistance with Cooperative Registration Requirements Guidance, including document preparation, submission, and follow-up.', 5040.00, '2026-10-26', '2026-11-25', 'Draft', 3, NULL, NULL, '2026-10-20 09:37:00', '2026-10-21 06:26:43', NULL, NULL, NULL),
(22, 'SOW-2026-0010', 59, 72, 50, 'Assistance with BIR Books of Accounts Registration, including document preparation, submission, and follow-up.', 5040.00, '2026-10-26', '2026-11-25', 'Approved', 3, 2, '2026-10-19 12:25:07', '2026-10-19 05:50:00', '2026-10-21 06:26:43', NULL, NULL, NULL);

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
(12, 'folder', NULL, 'Company Templates', NULL, NULL, NULL, NULL, 0, NULL, NULL, 'admin', '2026-09-17 05:49:04', '2026-09-17 05:49:04'),
(50, 'file', 12, 'Consultancy_Services_Agreement.docx', NULL, NULL, 'Consultancy_Services_Agreement_KMP-2.docx', 'repository/doc_6ab8d6189154c4.06247243_Consultancy_Services_Agreement_KMP-2.docx', 39621, 'docx', 2, 'supervisor', '2026-09-27 08:38:48', '2026-09-27 08:38:59'),
(51, 'file', 12, 'contract.docx', NULL, NULL, 'contract (1).docx', 'repository/doc_6ab8d62fa57687.93802817_contract__1_.docx', 41017, 'docx', 2, 'supervisor', '2026-09-27 08:39:11', '2026-09-27 08:39:22'),
(52, 'folder', NULL, 'Client Files', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-09-29 01:37:49', '2026-09-29 01:37:49'),
(53, 'folder', 52, 'CodeWave Technologies', NULL, NULL, NULL, NULL, 0, NULL, 1, 'staff', '2026-09-29 01:38:41', '2026-09-29 01:38:41'),
(54, 'folder', 52, 'ABC Company', NULL, NULL, NULL, NULL, 0, NULL, 1, 'staff', '2026-09-29 01:38:49', '2026-09-29 01:38:49'),
(55, 'folder', 52, 'Gryk\'s Food House', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-10-06 22:02:12', '2026-10-06 22:02:12'),
(56, 'folder', 52, 'ABCD Company', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-10-06 22:07:41', '2026-10-06 22:07:41'),
(57, 'folder', 52, 'Gryk\'s', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-10-19 12:34:38', '2026-10-19 12:34:38'),
(58, 'folder', 52, 'Kamrynne Skills Pathway & Assessment Center, Inc.', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-10-19 12:34:38', '2026-10-19 12:34:38'),
(59, 'folder', 52, 'Pabalaque Training Institute', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-10-19 12:34:38', '2026-10-19 12:34:38'),
(60, 'folder', 52, 'LD Since 2023', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-10-19 12:34:38', '2026-10-19 12:34:38'),
(61, 'folder', 52, 'Jeri\'s Meats', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-10-19 12:34:38', '2026-10-19 12:34:38'),
(62, 'folder', 52, 'KMP Car Rental', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-10-19 12:34:38', '2026-10-19 12:34:38'),
(63, 'folder', 52, 'Clothina Online Shop', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-10-19 12:34:38', '2026-10-19 12:34:38'),
(64, 'folder', 52, 'ArmAnn Pharma and Medical Supplies Distribution', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-10-19 12:34:38', '2026-10-19 12:34:38'),
(65, 'folder', 52, 'Eternity', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-10-19 12:34:38', '2026-10-19 12:34:38'),
(66, 'folder', 52, '8Talk Custom Apparel', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-10-19 12:34:38', '2026-10-19 12:34:38'),
(67, 'folder', 52, 'CampusTop QQ English', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-10-19 12:34:38', '2026-10-19 12:34:38'),
(68, 'folder', 52, 'R&R Designers Shop', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-10-19 12:34:38', '2026-10-19 12:34:38'),
(69, 'folder', 52, 'Fry Avenue', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-10-19 12:34:38', '2026-10-19 12:34:38'),
(70, 'folder', 52, 'Kamrynne Paddle Park (KPP)', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-10-19 12:34:38', '2026-10-19 12:34:38'),
(71, 'folder', 52, 'Keystone Credit Cooperative', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-10-19 12:34:38', '2026-10-19 12:34:38'),
(72, 'folder', 52, 'Office of the Barangay Council, Brgy. Tampakan', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-10-19 12:34:38', '2026-10-19 12:34:38'),
(73, 'folder', 52, 'Remegio E. Magan, MD Ophthalmologist', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-10-19 12:34:38', '2026-10-19 12:34:38'),
(74, 'folder', 52, 'eBenta', NULL, NULL, NULL, NULL, 0, NULL, 2, 'admin', '2026-10-19 12:34:38', '2026-10-19 12:34:38'),
(77, 'file', 66, 'Consultancy_Services_Agreement_KMP-2.docx', NULL, NULL, 'Consultancy_Services_Agreement_KMP-2.docx', 'repository/doc_6ada1ae40b2b78.50092788_Consultancy_Services_Agreement_KMP-2.docx', 39621, 'docx', 2, 'admin', '2026-10-22 14:17:08', '2026-10-22 14:17:08'),
(78, 'file', 66, 'Profile.jpg', NULL, NULL, '9a34803572876920a0d6254bbc0d0346.jpg', 'repository/doc_6ada1b9bcd9b16.66412103_9a34803572876920a0d6254bbc0d0346.jpg', 55026, 'jpg', 2, 'admin', '2026-10-22 14:20:11', '2026-10-22 14:20:29'),
(79, 'file', 66, 'contract.docx', NULL, NULL, 'contract (1).docx', 'repository/doc_6ada1c874c2f57.56593499_contract__1_.docx', 41017, 'docx', 2, 'admin', '2026-10-22 14:24:07', '2026-10-22 14:24:16'),
(80, 'file', 66, 'NDA_Template.pdf', NULL, NULL, 'NDA_Template.pdf', 'repository/doc_6ada1ca620c085.73720262_NDA_Template.pdf', 3165, 'pdf', 2, 'admin', '2026-10-22 14:24:38', '2026-10-22 14:24:38'),
(81, 'file', 66, 'Consultant-Proposal-Template.docx', NULL, NULL, 'Consultant-Proposal-Template.docx', 'repository/doc_6ada1cb58b6d10.18830371_Consultant-Proposal-Template.docx', 36668, 'docx', 2, 'admin', '2026-10-22 14:24:53', '2026-10-22 14:24:53');

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
  `status` enum('Draft','Approved','Rejected','Revert') NOT NULL DEFAULT 'Draft',
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(5,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `valid_until` date DEFAULT NULL,
  `prepared_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `revert_count` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quotations`
--

INSERT INTO `quotations` (`quotation_id`, `quotation_number`, `request_id`, `client_id`, `project_scope`, `status`, `subtotal`, `tax_rate`, `tax_amount`, `total_amount`, `valid_until`, `prepared_by`, `created_at`, `updated_at`, `revert_count`) VALUES
(50, 'QT-2026-0001', 63, 41, 'Assistance with DTI Business Name Registration, including document preparation, submission, and follow-up.', 'Approved', 3000.00, 12.00, 360.00, 3360.00, '2026-11-19', 1, '2026-10-20 06:31:00', '2026-10-21 06:26:42', 0),
(51, 'QT-2026-0002', 64, 42, 'Assistance with Mayor\'s Permit Renewal, including document preparation, submission, and follow-up.', 'Approved', 4500.00, 12.00, 540.00, 5040.00, '2026-11-19', 1, '2026-10-19 07:42:00', '2026-10-21 06:26:42', 0),
(52, 'QT-2026-0003', 65, 43, 'Assistance with BIR Registration (Certificate of Registration), including document preparation, submission, and follow-up.', 'Approved', 5500.00, 12.00, 660.00, 6160.00, '2026-11-19', 1, '2026-10-20 08:53:00', '2026-10-21 06:26:42', 0),
(53, 'QT-2026-0004', 66, 44, 'Assistance with Barangay Business Clearance Application, including document preparation, submission, and follow-up.', 'Approved', 1700.00, 12.00, 204.00, 1904.00, '2026-11-19', 1, '2026-10-19 09:04:00', '2026-10-21 06:26:42', 0),
(54, 'QT-2026-0005', 67, 45, 'Assistance with Sanitary Permit Application, including document preparation, submission, and follow-up.', 'Approved', 3100.00, 12.00, 372.00, 3472.00, '2026-11-19', 1, '2026-10-20 01:15:00', '2026-10-21 06:26:42', 0),
(55, 'QT-2026-0006', 68, 46, 'Assistance with Business Name Renewal (DTI), including document preparation, submission, and follow-up.', 'Approved', 2000.00, 12.00, 240.00, 2240.00, '2026-11-19', 1, '2026-10-19 02:26:00', '2026-10-21 06:26:42', 0),
(56, 'QT-2026-0007', 69, 47, 'Assistance with Fire Safety Inspection Certificate (FSIC) Assistance, including document preparation, submission, and follow-up.', 'Approved', 4300.00, 12.00, 516.00, 4816.00, '2026-11-19', 3, '2026-10-20 03:37:00', '2026-10-21 06:26:42', 0),
(57, 'QT-2026-0008', 70, 48, 'Assistance with SEC Registration Basic Guidance, including document preparation, submission, and follow-up.', 'Approved', 3500.00, 12.00, 420.00, 3920.00, '2026-11-19', 3, '2026-10-19 04:48:00', '2026-10-21 06:26:42', 0),
(58, 'QT-2026-0009', 71, 49, 'Assistance with Cooperative Registration Requirements Guidance, including document preparation, submission, and follow-up.', 'Approved', 4500.00, 12.00, 540.00, 5040.00, '2026-11-19', 3, '2026-10-20 05:59:00', '2026-10-21 06:26:42', 0),
(59, 'QT-2026-0010', 72, 50, 'Assistance with BIR Books of Accounts Registration, including document preparation, submission, and follow-up.', 'Approved', 4500.00, 12.00, 540.00, 5040.00, '2026-11-19', 3, '2026-10-19 06:10:00', '2026-10-21 06:26:42', 0),
(60, 'QT-2026-0011', 73, 51, 'Assistance with Business Permit Requirements Checklist, including document preparation, submission, and follow-up.', 'Draft', 1800.00, 12.00, 216.00, 2016.00, '2026-11-19', 3, '2026-10-20 07:21:00', '2026-10-21 06:26:42', 0),
(61, 'QT-2026-0012', 74, 52, 'Assistance with Employee SSS, PhilHealth, and Pag-IBIG Registration, including document preparation, submission, and follow-up.', 'Draft', 4500.00, 12.00, 540.00, 5040.00, '2026-11-19', 3, '2026-10-19 08:32:00', '2026-10-21 06:26:42', 0),
(62, 'QT-2026-0013', 75, 53, 'Assistance with Annual BIR Tax Filing Assistance, including document preparation, submission, and follow-up.', 'Draft', 5500.00, 12.00, 660.00, 6160.00, '2026-11-19', 5, '2026-10-20 09:43:00', '2026-10-21 06:26:42', 0),
(63, 'QT-2026-0014', 76, 54, 'Assistance with Business Closure Process Guidance, including document preparation, submission, and follow-up.', 'Draft', 6500.00, 12.00, 780.00, 7280.00, '2026-11-19', 5, '2026-10-19 01:54:00', '2026-10-21 06:26:42', 0),
(64, 'QT-2026-0015', 77, 55, 'Assistance with Occupancy Permit Application Guidance, including document preparation, submission, and follow-up.', 'Approved', 3500.00, 12.00, 420.00, 3920.00, '2026-11-19', 5, '2026-10-20 02:05:00', '2026-10-22 14:25:11', 0),
(65, 'QT-2026-0016', 78, 56, 'Assistance with Basic Business Plan Consultation, including document preparation, submission, and follow-up.', 'Draft', 6500.00, 12.00, 780.00, 7280.00, '2026-11-19', 5, '2026-10-19 03:16:00', '2026-10-21 06:26:42', 0),
(66, 'QT-2026-0017', 79, 57, 'Assistance with Mayor\'s Permit Application, including document preparation, submission, and follow-up.', 'Draft', 4500.00, 12.00, 540.00, 5040.00, '2026-11-19', 5, '2026-10-20 04:27:00', '2026-10-21 06:26:42', 0),
(67, 'QT-2026-0018', 80, 58, 'Assistance with DTI Business Name Registration, including document preparation, submission, and follow-up.', 'Draft', 3000.00, 12.00, 360.00, 3360.00, '2026-11-19', 5, '2026-10-19 05:38:00', '2026-10-21 06:26:42', 0);

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
(99, 50, 'DTI business name registration fee', 1.00, 500.00, 500.00, 0),
(100, 50, 'Document preparation and processing', 1.00, 1500.00, 1500.00, 1),
(101, 50, 'Business consultation', 1.00, 1000.00, 1000.00, 2),
(102, 51, 'Document review and requirements checklist', 1.00, 800.00, 800.00, 0),
(103, 51, 'Mayor\'s permit renewal processing', 1.00, 2500.00, 2500.00, 1),
(104, 51, 'LGU follow-up', 1.00, 1200.00, 1200.00, 2),
(105, 52, 'BIR form preparation', 1.00, 1500.00, 1500.00, 0),
(106, 52, 'BIR registration processing', 1.00, 3000.00, 3000.00, 1),
(107, 52, 'Business consultation', 1.00, 1000.00, 1000.00, 2),
(108, 53, 'Requirements checklist', 1.00, 500.00, 500.00, 0),
(109, 53, 'Barangay clearance processing', 1.00, 1200.00, 1200.00, 1),
(110, 54, 'Requirements guidance', 1.00, 600.00, 600.00, 0),
(111, 54, 'Sanitary permit application filing', 1.00, 1500.00, 1500.00, 1),
(112, 54, 'Inspection coordination', 1.00, 1000.00, 1000.00, 2),
(113, 55, 'Renewal document preparation', 1.00, 700.00, 700.00, 0),
(114, 55, 'DTI renewal processing', 1.00, 1300.00, 1300.00, 1),
(115, 56, 'Requirements guidance', 1.00, 800.00, 800.00, 0),
(116, 56, 'FSIC application filing', 1.00, 2000.00, 2000.00, 1),
(117, 56, 'Inspection coordination', 1.00, 1500.00, 1500.00, 2),
(118, 57, 'Consultation (per hour)', 2.00, 1000.00, 2000.00, 0),
(119, 57, 'SEC document checklist', 1.00, 1500.00, 1500.00, 1),
(120, 58, 'Consultation (per hour)', 3.00, 1000.00, 3000.00, 0),
(121, 58, 'Cooperative requirements checklist', 1.00, 1500.00, 1500.00, 1),
(122, 59, 'Books of accounts preparation', 1.00, 2000.00, 2000.00, 0),
(123, 59, 'BIR books registration processing', 1.00, 2500.00, 2500.00, 1),
(124, 60, 'Business consultation', 1.00, 1000.00, 1000.00, 0),
(125, 60, 'Permit requirements checklist preparation', 1.00, 800.00, 800.00, 1),
(126, 61, 'SSS employer registration', 1.00, 1500.00, 1500.00, 0),
(127, 61, 'PhilHealth employer registration', 1.00, 1500.00, 1500.00, 1),
(128, 61, 'Pag-IBIG employer registration', 1.00, 1500.00, 1500.00, 2),
(129, 62, 'Tax computation', 1.00, 3000.00, 3000.00, 0),
(130, 62, 'Annual filing assistance', 1.00, 2500.00, 2500.00, 1),
(131, 63, 'Business consultation', 1.00, 1000.00, 1000.00, 0),
(132, 63, 'Closure document preparation', 1.00, 3000.00, 3000.00, 1),
(133, 63, 'BIR and LGU clearance follow-up', 1.00, 2500.00, 2500.00, 2),
(134, 64, 'Requirements guidance', 1.00, 1000.00, 1000.00, 0),
(135, 64, 'Occupancy permit application filing', 1.00, 2500.00, 2500.00, 1),
(136, 65, 'Consultation (per hour)', 3.00, 1000.00, 3000.00, 0),
(137, 65, 'Business plan outline preparation', 1.00, 3500.00, 3500.00, 1),
(138, 66, 'Requirements checklist', 1.00, 800.00, 800.00, 0),
(139, 66, 'Mayor\'s permit application processing', 1.00, 2500.00, 2500.00, 1),
(140, 66, 'LGU follow-up', 1.00, 1200.00, 1200.00, 2),
(141, 67, 'DTI business name registration fee', 1.00, 500.00, 500.00, 0),
(142, 67, 'Document preparation and processing', 1.00, 1500.00, 1500.00, 1),
(143, 67, 'Business consultation', 1.00, 1000.00, 1000.00, 2);

-- --------------------------------------------------------

--
-- Table structure for table `quotation_revisions`
--

CREATE TABLE `quotation_revisions` (
  `revision_id` int(11) NOT NULL,
  `quotation_id` int(11) NOT NULL,
  `revision_note` text DEFAULT NULL,
  `revised_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quotation_revisions`
--

INSERT INTO `quotation_revisions` (`revision_id`, `quotation_id`, `revision_note`, `revised_by`, `created_at`) VALUES
(1, 16, 'Quotation reverted.', 2, '2026-10-13 08:56:40'),
(2, 18, 'Quotation reverted.', 2, '2026-10-13 08:56:44');

-- --------------------------------------------------------

--
-- Table structure for table `service_requests`
--

CREATE TABLE `service_requests` (
  `request_id` int(10) UNSIGNED NOT NULL,
  `client_id` int(10) UNSIGNED NOT NULL,
  `request_title` varchar(150) NOT NULL,
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

INSERT INTO `service_requests` (`request_id`, `client_id`, `request_title`, `required_skill`, `status`, `assigned_to`, `assigned_by`, `created_at`, `updated_at`) VALUES
(63, 41, 'DTI Business Name Registration', 'Business Registration', 'New', NULL, NULL, '2026-10-20 05:47:00', '2026-10-20 05:47:00'),
(64, 42, 'Mayor\'s Permit Renewal', 'Permit Processing', 'New', NULL, NULL, '2026-10-19 06:54:00', '2026-10-19 06:54:00'),
(65, 43, 'BIR Registration (Certificate of Registration)', 'BIR Processing', 'New', NULL, NULL, '2026-10-20 07:01:00', '2026-10-20 07:01:00'),
(66, 44, 'Barangay Business Clearance Application', 'Permit Processing', 'New', NULL, NULL, '2026-10-19 08:08:00', '2026-10-19 08:08:00'),
(67, 45, 'Sanitary Permit Application', 'Permit Processing', 'New', NULL, NULL, '2026-10-20 00:15:00', '2026-10-20 00:15:00'),
(68, 46, 'Business Name Renewal (DTI)', 'Business Registration', 'Completed', 1, 2, '2026-10-19 01:22:00', '2026-10-19 12:46:21'),
(69, 47, 'Fire Safety Inspection Certificate (FSIC) Assistance', 'Permit Processing', 'New', NULL, NULL, '2026-10-20 02:29:00', '2026-10-20 02:29:00'),
(70, 48, 'SEC Registration Basic Guidance', 'Business Registration', 'New', NULL, NULL, '2026-10-19 03:36:00', '2026-10-19 03:36:00'),
(71, 49, 'Cooperative Registration Requirements Guidance', 'Business Registration', 'New', NULL, NULL, '2026-10-20 04:43:00', '2026-10-20 04:43:00'),
(72, 50, 'BIR Books of Accounts Registration', 'BIR Processing', 'New', NULL, NULL, '2026-10-19 05:50:00', '2026-10-19 05:50:00'),
(73, 51, 'Business Permit Requirements Checklist', 'Business Consultation', 'New', NULL, NULL, '2026-10-20 06:57:00', '2026-10-20 06:57:00'),
(74, 52, 'Employee SSS, PhilHealth, and Pag-IBIG Registration', 'Government Compliance', 'New', NULL, NULL, '2026-10-19 07:04:00', '2026-10-19 07:04:00'),
(75, 53, 'Annual BIR Tax Filing Assistance', 'BIR Processing', 'New', NULL, NULL, '2026-10-20 08:11:00', '2026-10-20 08:11:00'),
(76, 54, 'Business Closure Process Guidance', 'Business Consultation', 'New', NULL, NULL, '2026-10-19 00:18:00', '2026-10-19 00:18:00'),
(77, 55, 'Occupancy Permit Application Guidance', 'Permit Processing', 'New', NULL, NULL, '2026-10-20 01:25:00', '2026-10-20 01:25:00'),
(78, 56, 'Basic Business Plan Consultation', 'Business Consultation', 'New', NULL, NULL, '2026-10-19 02:32:00', '2026-10-19 02:32:00'),
(79, 57, 'Mayor\'s Permit Application', 'Permit Processing', 'New', NULL, NULL, '2026-10-20 03:39:00', '2026-10-20 03:39:00'),
(80, 58, 'DTI Business Name Registration', 'Business Registration', 'New', NULL, NULL, '2026-10-19 04:46:00', '2026-10-19 04:46:00');

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
(18, 3, 'IT Infrastructure Assessment', '2026-09-22 02:11:29'),
(19, 3, 'Business Process Improvement', '2026-09-22 02:11:29'),
(20, 3, 'Data Privacy Compliance', '2026-09-22 02:11:29'),
(21, 3, 'Systems Analysis', '2026-09-22 02:11:29'),
(22, 3, 'Project Documentation', '2026-09-22 02:11:29'),
(23, 5, 'Marketing Strategy', '2026-10-08 12:02:19'),
(24, 5, 'Market Research', '2026-10-08 12:02:19'),
(25, 5, 'Client Relations Management', '2026-10-08 12:02:19'),
(26, 5, 'Contract Drafting', '2026-10-08 12:02:19'),
(27, 5, 'Legal Compliance Review', '2026-10-08 12:02:19'),
(28, 5, 'Quality Assurance', '2026-10-08 12:02:19'),
(29, 5, 'Risk Assessment', '2026-10-08 12:02:19');

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
  ADD KEY `fk_quotations_prepared_by` (`prepared_by`),
  ADD KEY `idx_quotations_status` (`status`);

--
-- Indexes for table `quotation_items`
--
ALTER TABLE `quotation_items`
  ADD PRIMARY KEY (`item_id`),
  ADD KEY `fk_quotation_items_quotation` (`quotation_id`);

--
-- Indexes for table `quotation_revisions`
--
ALTER TABLE `quotation_revisions`
  ADD PRIMARY KEY (`revision_id`),
  ADD KEY `quotation_id` (`quotation_id`);

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
  MODIFY `client_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `contract_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `contract_revisions`
--
ALTER TABLE `contract_revisions`
  MODIFY `revision_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `knowledge_documents`
--
ALTER TABLE `knowledge_documents`
  MODIFY `document_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=82;

--
-- AUTO_INCREMENT for table `quotations`
--
ALTER TABLE `quotations`
  MODIFY `quotation_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `quotation_items`
--
ALTER TABLE `quotation_items`
  MODIFY `item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=144;

--
-- AUTO_INCREMENT for table `quotation_revisions`
--
ALTER TABLE `quotation_revisions`
  MODIFY `revision_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `service_requests`
--
ALTER TABLE `service_requests`
  MODIFY `request_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `staff_skills`
--
ALTER TABLE `staff_skills`
  MODIFY `skill_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

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