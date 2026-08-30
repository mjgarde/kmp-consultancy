-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Aug 30, 2026 at 05:34 AM
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
(1, 'ABC Corporation', 'Juan Dela Cruz', 'juan@abccorp.com', '09171234567', 'Makati City, Metro Manila', 'Manufacturing', '2026-08-10 07:35:32', '2026-08-10 07:35:32'),
(2, 'XYZ Company', 'Maria Reyes', 'maria@xyzcompany.com', '09181234567', 'Cebu City, Cebu', 'Retail', '2026-08-10 07:35:32', '2026-08-10 07:35:32'),
(3, 'LMN Solutions', 'Pedro Santos', 'pedro@lmnsolutions.com', '09191234567', 'Davao City, Davao del Sur', 'IT Services', '2026-08-10 07:35:32', '2026-08-10 07:35:32'),
(4, 'Greenfield Agri Traders', 'Rosalinda M. Dizon', 'rosalinda.dizon@greenfieldagri.com', '09171122334', 'Poblacion, Tantangan, South Cotabato', 'Agriculture', '2026-08-20 02:55:47', '2026-08-20 02:55:47'),
(5, 'Koronadal Builders Corp.', 'Ernesto V. Padilla', 'ernesto.padilla@koronadalbuilders.com', '09182233445', 'National Highway, City of Koronadal, South Cotabato', 'Construction', '2026-08-20 02:55:47', '2026-08-20 02:55:47'),
(6, 'Sunrise Grocery Mart', 'Angelica T. Ferrer', 'angelica.ferrer@sunrisegrocery.com', '09193344556', 'Zone II, Surallah, South Cotabato', 'Retail', '2026-08-20 02:55:47', '2026-08-20 02:55:47'),
(7, 'MetroLink Logistics Inc.', 'Ramil J. Aquino', 'ramil.aquino@metrolinklogistics.com', '09204455667', 'Purok Malipayon, General Santos City', 'Logistics', '2026-08-20 02:55:47', '2026-08-20 02:55:47'),
(8, 'BrightPath Learning Center', 'Carmela S. Bautista', 'carmela.bautista@brightpathlearning.com', '09215566778', 'Purok Mabuhay, Tupi, South Cotabato', 'Education', '2026-08-20 02:55:47', '2026-08-20 02:55:47'),
(9, 'Coastal Fresh Seafoods', 'Dominador L. Cruz', 'dominador.cruz@coastalfresh.com', '09226677889', 'Barangay Poblacion, Polomolok, South Cotabato', 'Food Processing', '2026-08-20 02:55:47', '2026-08-20 02:55:47'),
(10, 'SouthCore IT Solutions', 'Jasmine R. Villanueva', 'jasmine.villanueva@southcoreit.com', '09237788990', 'Gensan Drive, General Santos City', 'IT Services', '2026-08-20 02:55:47', '2026-08-20 02:55:47'),
(11, 'Golden Harvest Rice Mill', 'Feliciano D. Torres', 'feliciano.torres@goldenharvestmill.com', '09248899001', 'Barangay San Isidro, Banga, South Cotabato', 'Manufacturing', '2026-08-20 02:55:47', '2026-08-20 02:55:47'),
(12, 'Prime Health Diagnostics', 'Marivic O. Santiago', 'marivic.santiago@primehealthdx.com', '09259900112', 'Real Street, City of Koronadal, South Cotabato', 'Healthcare', '2026-08-20 02:55:47', '2026-08-20 02:55:47'),
(13, 'Cotabato Trans Rentals', 'Bernard K. Reyes', 'bernard.reyes@cotabatotransrentals.com', '09261100223', 'Purok Uno, Tacurong City', 'Transportation', '2026-08-20 02:55:47', '2026-08-20 02:55:47');

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `contracts`
--

INSERT INTO `contracts` (`contract_id`, `contract_number`, `quotation_id`, `request_id`, `client_id`, `scope_summary`, `terms_conditions`, `total_amount`, `start_date`, `end_date`, `status`, `prepared_by`, `approved_by`, `approved_at`, `created_at`, `updated_at`) VALUES
(1, 'SOW-2026-0001', 2, 7, 5, 'Review of quarterly tax filings prior to submission, covering VAT, withholding tax, and income tax computations.', 'g', 13440.00, '2027-02-15', '2027-02-16', 'Pending Approval', 4, 4, '2026-08-21 23:18:07', '2026-08-21 23:16:24', '2026-08-22 13:38:33');

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
  `title` varchar(150) NOT NULL,
  `category` enum('SOW Template','Contract Template','Best Practice','Proposal Template','Reference Material','Other') NOT NULL DEFAULT 'Other',
  `description` text DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `file_type` varchar(20) NOT NULL,
  `uploaded_by` int(10) UNSIGNED DEFAULT NULL,
  `uploaded_by_role` enum('admin','manager','supervisor') NOT NULL DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `knowledge_documents`
--

INSERT INTO `knowledge_documents` (`document_id`, `title`, `category`, `description`, `file_name`, `file_path`, `file_size`, `file_type`, `uploaded_by`, `uploaded_by_role`, `created_at`, `updated_at`) VALUES
(1, 'Standard SOW Template', 'SOW Template', 'Base template covering project scope, deliverables, timeline, and payment terms for new consultancy engagements.', 'SOW_Template.pdf', 'uploads/knowledge_documents/doc_6a87e3581f0187.64640422.pdf', 4071, 'pdf', 2, 'admin', '2026-08-21 05:34:16', '2026-08-21 05:34:16'),
(2, 'Consultancy Service Agreement Template', 'Contract Template', 'Standard contract template used for formalizing agreements between KMP and new clients.', 'Consultancy_Service_Agreement_Template.pdf', 'uploads/knowledge_documents/doc_6a87e3fb74f6a6.43644093.pdf', 4258, 'pdf', 2, 'admin', '2026-08-21 05:36:59', '2026-08-21 05:36:59'),
(3, 'Non-Disclosure Agreement (NDA) Template', 'Contract Template', 'Confidentiality agreement template for engagements involving sensitive client data.', 'NDA_Template.pdf', 'uploads/knowledge_documents/doc_6a87e4469bd459.50777238.pdf', 3165, 'pdf', 2, 'admin', '2026-08-21 05:38:14', '2026-08-21 05:38:14'),
(4, 'Client Proposal Template', 'Proposal Template', 'Standard format for presenting service proposals and cost estimates to prospective clients.', 'Client_Proposal_Template.pdf', 'uploads/knowledge_documents/doc_6a87e4ad198ec0.37493552.pdf', 2981, 'pdf', 2, 'admin', '2026-08-21 05:39:57', '2026-08-21 05:39:57'),
(5, 'Client Onboarding Checklist', 'Best Practice', 'Step-by-step guide for properly onboarding a new client account, from initial consultation to service request logging.', 'Client_Onboarding_Checklist.pdf', 'uploads/knowledge_documents/doc_6a87e4ee3c94c2.06504946.pdf', 3990, 'pdf', 2, 'admin', '2026-08-21 05:41:02', '2026-08-21 05:41:02');

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
(1, 'QT-2026-0001', 6, 4, 'Assistance with DTI and BIR registration requirements for a new branch, including document preparation and submission support.', 'Sent', 15000.00, 12.00, 1800.00, 16800.00, '2026-09-30', 'Client requested expedited processing.', 4, '2026-08-21 23:02:47', '2026-08-22 13:35:30'),
(2, 'QT-2026-0002', 7, 5, 'Review of quarterly tax filings prior to submission, covering VAT, withholding tax, and income tax computations.', 'Approved', 12000.00, 12.00, 1440.00, 13440.00, '2026-09-15', 'Approved by client via email confirmation.', 4, '2026-08-21 23:02:47', '2026-08-21 23:02:47');

-- --------------------------------------------------------

--
-- Table structure for table `quotation_items`
--

CREATE TABLE `quotation_items` (
  `item_id` int(10) UNSIGNED NOT NULL,
  `quotation_id` int(10) UNSIGNED NOT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(12,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `quotation_items`
--

INSERT INTO `quotation_items` (`item_id`, `quotation_id`, `description`, `quantity`, `unit_price`, `line_total`, `sort_order`) VALUES
(1, 1, 'DTI Business Name Registration', 1.00, 3500.00, 3500.00, 0),
(2, 1, 'BIR Registration and Documentation', 1.00, 6500.00, 6500.00, 1),
(3, 1, 'Business Permit Filing Assistance', 1.00, 5000.00, 5000.00, 2),
(4, 2, 'VAT Filing Review', 1.00, 4000.00, 4000.00, 0),
(5, 2, 'Withholding Tax Review', 1.00, 3500.00, 3500.00, 1),
(6, 2, 'Income Tax Computation Check', 1.00, 4500.00, 4500.00, 2);

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
(1, 1, 'Initial Business Consultation', 'Client requested an initial consultation regarding financial planning.', NULL, 'New', NULL, NULL, '2026-08-10 07:35:32', '2026-08-10 07:35:32'),
(2, 2, 'Process Improvement Review', 'Client requested a review of current business processes.', NULL, 'In Progress', NULL, NULL, '2026-08-10 07:35:32', '2026-08-10 07:35:32'),
(3, 3, 'IT Infrastructure Assessment', 'Client requested an assessment of their current IT infrastructure.', NULL, 'Completed', NULL, NULL, '2026-08-10 07:35:32', '2026-08-10 07:35:32'),
(4, 3, 'hi', 'hello', NULL, 'New', 1, NULL, '2026-08-12 05:51:02', '2026-08-17 02:15:48'),
(5, 3, 'm', 'm', NULL, 'New', 1, NULL, '2026-08-17 02:21:42', '2026-08-17 02:28:43'),
(6, 4, 'Business Registration Assistance', 'Client needs help completing DTI and BIR registration requirements for a new branch.', 'Business Registration', 'New', NULL, NULL, '2026-08-20 02:56:59', '2026-08-20 02:56:59'),
(7, 5, 'Quarterly Tax Filing Review', 'Client requested a review of their quarterly tax filings before submission.', 'Tax Advisory', 'New', NULL, NULL, '2026-08-20 02:56:59', '2026-08-20 02:56:59'),
(8, 6, 'Inventory Bookkeeping Setup', 'Client wants a proper bookkeeping system set up for their retail inventory.', 'Bookkeeping', 'New', NULL, NULL, '2026-08-20 02:56:59', '2026-08-20 02:56:59'),
(9, 7, 'Warehouse Systems Assessment', 'Client requested an assessment of their current warehouse and logistics IT systems.', 'IT Infrastructure Assessment', 'New', NULL, NULL, '2026-08-20 02:56:59', '2026-08-20 02:56:59'),
(10, 8, 'Enrollment Process Improvement', 'Client wants to streamline their student enrollment and records process.', 'Business Process Improvement', 'New', NULL, NULL, '2026-08-20 02:56:59', '2026-08-20 02:56:59'),
(11, 9, 'Data Privacy Compliance Check', 'Client needs a compliance review of their customer data handling practices.', 'Data Privacy Compliance', 'New', NULL, NULL, '2026-08-20 02:56:59', '2026-08-20 02:56:59'),
(12, 10, 'Marketing Strategy Development', 'Client wants a marketing plan to expand their IT services to new areas.', 'Marketing Strategy', 'New', NULL, NULL, '2026-08-20 02:56:59', '2026-08-20 02:56:59'),
(13, 11, 'Supplier Contract Drafting', 'Client needs a standard supplier contract template drafted for their rice mill operations.', 'Contract Drafting', 'New', NULL, NULL, '2026-08-20 02:56:59', '2026-08-20 02:56:59'),
(14, 12, 'Client Relations Audit', 'Client wants an audit of their patient/client relations and feedback handling process.', 'Client Relations Management', 'New', NULL, NULL, '2026-08-20 02:56:59', '2026-08-20 02:56:59'),
(15, 13, 'Fleet Risk Assessment', 'Client requested a risk assessment covering their vehicle rental and transport operations.', 'Risk Assessment', 'New', NULL, NULL, '2026-08-20 02:56:59', '2026-08-20 02:56:59');

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
(6, 3, 'IT Infrastructure Assessment', '2026-08-20 02:21:06'),
(7, 3, 'Business Process Improvement', '2026-08-20 02:21:06'),
(8, 3, 'Data Privacy Compliance', '2026-08-20 02:21:06'),
(9, 3, 'Systems Analysis', '2026-08-20 02:21:06'),
(10, 3, 'Project Documentation', '2026-08-20 02:21:06'),
(11, 5, 'Marketing Strategy', '2026-08-20 02:21:06'),
(12, 5, 'Market Research', '2026-08-20 02:21:06'),
(13, 5, 'Client Relations Management', '2026-08-20 02:21:06'),
(14, 5, 'Contract Drafting', '2026-08-20 02:21:06'),
(15, 5, 'Legal Compliance Review', '2026-08-20 02:21:06'),
(16, 5, 'Quality Assurance', '2026-08-20 02:21:06'),
(17, 5, 'Risk Assessment', '2026-08-20 02:21:06');

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
  `role` enum('Manager','Supervisor','Staff') NOT NULL,
  `status` enum('Active','Inactive') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `firstname`, `middlename`, `lastname`, `birthday`, `gender`, `address`, `contact_number`, `email`, `password`, `role`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Kent Kyle', 'B.', 'Cali', '1999-05-14', 'Male', 'City of Koronadal, South Cotabato', '+639091712345', 'cali@gmail.com', '$2y$10$yL64umsuEI4Xk2squIMO4ubhDoPWSaBOtPb3RJc9o3Hl7dfE4DG32', 'Staff', 'Active', '2026-08-09 21:10:42', '2026-08-11 01:14:15'),
(2, 'Alvin', 'G.', 'Salmo', '2000-08-22', 'Male', 'Tupi, South Cotabato', '+639091812345', 'salmo@gmail.com', '$2y$10$JblbCq/TuxTlHRtsuehvnOJDd7Wxr6Iw0KMZ57cXmoJAA9aeVz2Kq', 'Supervisor', 'Active', '2026-08-09 21:10:42', '2026-08-11 01:15:26'),
(3, 'Maria', '', 'Santos', '2001-02-10', 'Female', 'Marbel, South Cotabato', '+639091912345', 'staff@kmpconsulthub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.YeIVpKzO0FGgeZzYqLh0Uk3z1lRAJVNqK', 'Staff', 'Active', '2026-08-09 21:10:42', '2026-08-10 07:30:46'),
(4, 'Mike', 'Cruz', 'Garde', '2002-02-15', 'Male', 'Banga, South Cotabato', '+639090982828', 'gars@gmail.com', '$2y$10$7Nx4RAPVDXcBEfL1qfP0MexPRouLq9t396qGxx1aq0RbfQ9H70kpa', 'Manager', 'Active', '2026-08-09 21:49:28', '2026-08-12 05:45:32'),
(5, 'Juan', 'Cruz', 'Ramirez', '2004-02-14', 'Male', 'Banga, South Cotabato', '+639010101022', 'juan@gmail.com', '$2y$10$2OiaJu4VW2wUppT7LjhnTuVIuPxyRupyv7VTqiTJCYN9PaSp.CQru', 'Staff', 'Active', '2026-08-17 02:08:14', '2026-08-17 02:08:14');

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
  ADD PRIMARY KEY (`document_id`);

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
  MODIFY `client_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `contract_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `contract_revisions`
--
ALTER TABLE `contract_revisions`
  MODIFY `revision_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `knowledge_documents`
--
ALTER TABLE `knowledge_documents`
  MODIFY `document_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `quotations`
--
ALTER TABLE `quotations`
  MODIFY `quotation_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `quotation_items`
--
ALTER TABLE `quotation_items`
  MODIFY `item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `service_requests`
--
ALTER TABLE `service_requests`
  MODIFY `request_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `staff_skills`
--
ALTER TABLE `staff_skills`
  MODIFY `skill_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

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
