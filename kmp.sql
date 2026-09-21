-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Sep 21, 2026 at 10:01 PM
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
(4, 'Greenfield Agri Traders', 'Rosalinda S. Dizon', 'rosalinda.dizon@greenfieldagri.com', '09171122334', 'Poblacion, Tantangan, South Cotabato', 'Agriculture', '2026-08-20 02:55:47', '2026-09-17 00:33:49'),
(5, 'Koronadal Builders Corp.', 'Ernesto V. Padilla', 'ernesto.padilla@koronadalbuilders.com', '09182233445', 'National Highway, City of Koronadal, South Cotabato', 'Construction', '2026-08-20 02:55:47', '2026-08-20 02:55:47'),
(6, 'Sunrise Grocery Mart', 'Angelica T. Ferrer', 'angelica.ferrer@sunrisegrocery.com', '09193344556', 'Zone II, Surallah, South Cotabato', 'Retail', '2026-08-20 02:55:47', '2026-08-20 02:55:47'),
(7, 'MetroLink Logistics Inc.', 'Ramil J. Aquino', 'ramil.aquino@metrolinklogistics.com', '09204455667', 'Purok Malipayon, General Santos City', 'Logistics', '2026-08-20 02:55:47', '2026-08-20 02:55:47'),
(8, 'BrightPath Learning Center', 'Carmela S. Bautista', 'carmela.bautista@brightpathlearning.com', '09215566778', 'Purok Mabuhay, Tupi, South Cotabato', 'Education', '2026-08-20 02:55:47', '2026-08-20 02:55:47'),
(9, 'Coastal Fresh Seafoods', 'Dominador L. Cruz', 'dominador.cruz@coastalfresh.com', '09226677889', 'Barangay Poblacion, Polomolok, South Cotabato', 'Food Processing', '2026-08-20 02:55:47', '2026-08-20 02:55:47'),
(10, 'SouthCore IT Solutions', 'Jasmine R. Villanueva', 'jasmine.villanueva@southcoreit.com', '09237788990', 'Gensan Drive, General Santos City', 'IT Services', '2026-08-20 02:55:47', '2026-08-20 02:55:47'),
(11, 'Golden Harvest Rice Mill', 'Feliciano D. Torres', 'feliciano.torres@goldenharvestmill.com', '09248899001', 'Barangay San Isidro, Banga, South Cotabato', 'Manufacturing', '2026-08-20 02:55:47', '2026-08-20 02:55:47'),
(12, 'Prime Health Diagnostics', 'Marivic O. Santiago', 'marivic.santiago@primehealthdx.com', '09259900112', 'Real Street, City of Koronadal, South Cotabato', 'Healthcare', '2026-08-20 02:55:47', '2026-08-20 02:55:47'),
(13, 'Cotabato Trans Rentals', 'Bernard K. Reyes', 'bernard.reyes@cotabatotransrentals.com', '09261100223', 'Purok Uno, Tacurong City', 'Transportation', '2026-08-20 02:55:47', '2026-08-20 02:55:47'),
(14, 'Alpha Logistics Corp', 'Maria Santos', 'maria.santos@alphalogistics.com', '09171234501', '123 Rizal Ave, Manila', 'Logistics', '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(15, 'BrightPath Solutions', 'John Cruz', 'john.cruz@brightpath.com', '09171234502', '45 Mabini St, Quezon City', 'IT Services', '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(16, 'Coastal Traders Inc', 'Ana Reyes', 'ana.reyes@coastaltraders.com', '09171234503', '78 Ayala Ave, Makati', 'Retail', '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(17, 'Delta Manufacturing', 'Carlos Dizon', 'carlos.dizon@deltamfg.com', '09171234504', '12 Katipunan Rd, Marikina', 'Manufacturing', '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(18, 'Evergreen Realty', 'Liza Tan', 'liza.tan@evergreenrealty.com', '09171234505', '89 Shaw Blvd, Pasig', 'Real Estate', '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(19, 'Fortune Foods Co', 'Miguel Torres', 'miguel.torres@fortunefoods.com', '09171234506', '34 Taft Ave, Manila', 'Food and Beverage', '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(20, 'Golden Gate Finance', 'Patricia Lim', 'patricia.lim@goldengate.com', '09171234507', '56 Ortigas Ave, Pasig', 'Finance', '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(21, 'Horizon Textiles', 'Rafael Garcia', 'rafael.garcia@horizontextiles.com', '09171234508', '21 EDSA, Caloocan', 'Textiles', '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(22, 'Ironclad Security', 'Sofia Mendoza', 'sofia.mendoza@ironclad.com', '09171234509', '67 Commonwealth Ave, Quezon City', 'Security Services', '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(23, 'Jade Valley Hotels', 'Diego Fernandez', 'diego.fernandez@jadevalley.com', '09171234510', '90 Roxas Blvd, Manila', 'Hospitality', '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(24, 'Keystone Builders', 'Isabel Ramos', 'isabel.ramos@keystonebuilders.com', '09171234511', '15 Aurora Blvd, Quezon City', 'Construction', '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(25, 'Luminous Energy Corp', 'Antonio Bautista', 'antonio.bautista@luminousenergy.com', '09171234512', '43 Quezon Ave, Quezon City', 'Energy', '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(26, 'Maple Grove Agri', 'Christine Flores', 'christine.flores@maplegrove.com', '09171234513', '28 Marcos Highway, Antipolo', 'Agriculture', '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(27, 'Nova Digital Media', 'Ramon Aquino', 'ramon.aquino@novadigital.com', '09171234514', '71 Bonifacio Global City, Taguig', 'Media', '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(28, 'Oceanic Freight Lines', 'Cristina Valdez', 'cristina.valdez@oceanicfreight.com', '09171234515', '19 Macapagal Blvd, Pasay', 'Shipping', '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(29, 'Pinnacle Consulting', 'Victor Santos', 'victor.santos@pinnacleconsulting.com', '09171234516', '52 Timog Ave, Quezon City', 'Consulting', '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(30, 'Quantum Tech Labs', 'Bianca Navarro', 'bianca.navarro@quantumtech.com', '09171234517', '37 Ortigas Center, Pasig', 'Technology', '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(31, 'Riverside Pharmaceuticals', 'Eduardo Castillo', 'eduardo.castillo@riversidepharma.com', '09171234518', '64 España Blvd, Manila', 'Pharmaceuticals', '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(32, 'Summit Retail Group', 'Karen Morales', 'karen.morales@summitretail.com', '09171234519', '82 Alabang Zapote Rd, Muntinlupa', 'Retail', '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(33, 'Trinity Insurance Corp', 'Noel Villanueva', 'noel.villanueva@trinityinsurance.com', '09171234520', '26 Session Rd, Baguio', 'Insurance', '2026-09-18 12:45:46', '2026-09-18 12:45:46');

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
(1, 'SOW-2026-0001', 2, 7, 5, 'Review of quarterly tax filings prior to submission, covering VAT, withholding tax, and income tax computations.', 'g', 13440.00, '2027-02-15', '2027-02-16', 'Approved', 4, 4, '2026-09-09 07:17:10', '2026-08-21 23:16:24', '2026-09-09 07:17:10', NULL, NULL, NULL),
(3, 'SOW-2026-0002', 4, 17, 1, 'Full audit of FY2025 financial statements covering accounts payable, accounts receivable, inventory valuation, and internal controls in preparation for BIR filing.', '1. SCOPE OF SERVICES\r\nThe Consultant shall perform a full audit of the Client\'s FY2025 financial statements, covering accounts payable, accounts receivable, inventory valuation, and internal controls, in accordance with the agreed project scope.\r\n\r\n2. PAYMENT TERMS\r\n- 50% downpayment upon signing of this contract.\r\n- 50% balance upon completion and delivery of the final audit report.\r\n- All payments shall be made via bank transfer within fifteen (15) days from receipt of invoice.\r\n\r\n3. PROJECT TIMELINE\r\nThe engagement shall commence on the Start Date and be completed on or before the End Date. Any extension shall require written approval from both parties.\r\n\r\n4. DELIVERABLES\r\n- Audited financial statements\r\n- Management letter with findings and recommendations\r\n- Final audit report presented to the Client\'s management team\r\n\r\n5. CONFIDENTIALITY\r\nBoth parties agree to keep all financial and business information disclosed during this engagement strictly confidential and shall not disclose such information to any third party without prior written consent.\r\n\r\n6. REVISIONS\r\nThe Client is entitled to two (2) rounds of revisions on the draft report. Additional revisions shall be billed separately.\r\n\r\n7. TERMINATION\r\nEither party may terminate this contract with thirty (30) days written notice. Fees for services rendered prior to termination shall remain payable.\r\n\r\n8. GOVERNING LAW\r\nThis contract shall be governed by the laws of the Republic of the Philippines.', 58240.00, '2026-09-25', '2026-09-29', 'Approved', 4, 4, '2026-09-11 11:18:40', '2026-09-11 11:18:27', '2026-09-11 11:18:40', NULL, NULL, NULL),
(4, 'SOW-2026-0003', 5, 18, 7, 'Assess the client\'s current manual inventory process and implement a digital inventory system integrated with their existing POS for real-time stock visibility across all warehouses.', '1. SCOPE OF SERVICES\r\nThe Consultant shall assess the Client\'s current manual warehouse inventory process, recommend and implement a digital inventory system integrated with the Client\'s existing POS, migrate existing inventory records, and provide staff training.\r\n\r\n2. PAYMENT TERMS\r\n- 50% downpayment upon signing of this contract.\r\n- 50% balance upon completion of system setup, data migration, and staff training.\r\n- All payments shall be made via bank transfer within fifteen (15) days from receipt of invoice.\r\n\r\n3. PROJECT TIMELINE\r\nThe engagement shall commence on the Start Date and be completed on or before the End Date. Any extension shall require written approval from both parties.\r\n\r\n4. DELIVERABLES\r\n- Systems assessment report\r\n- Software recommendation document\r\n- Migrated inventory data\r\n- Configured warehouse inventory system with POS integration\r\n- Staff training sessions\r\n- Post-implementation support for thirty (30) days\r\n\r\n5. CLIENT RESPONSIBILITIES\r\nThe Client shall provide warehouse access for on-site assessment, existing inventory records in digital or physical format, and a designated point of contact for coordination throughout the engagement.\r\n\r\n6. EXCLUSIONS\r\n- Software license fees (billed separately by vendor)\r\n- Hardware procurement costs (scanners, terminals, servers)\r\n- Internet or network infrastructure upgrades\r\n- Extended support beyond the 30-day post-implementation period\r\n\r\n7. DATA PRIVACY\r\nThe Consultant shall comply with the Data Privacy Act of 2012 (RA 10173) in handling all Client data throughout the engagement. All inventory and operational data shall remain the sole property of the Client.\r\n\r\n8. REVISIONS\r\nSystem configuration revisions within the agreed scope are unlimited during the project duration. Additional features or functionality beyond the original scope shall be quoted separately.\r\n\r\n9. CONFIDENTIALITY\r\nBoth parties agree to keep all business, operational, and technical information disclosed during this engagement strictly confidential and shall not disclose such information to any third party without prior written consent.\r\n\r\n10. TERMINATION\r\nEither party may terminate this contract with thirty (30) days written notice. Fees for services rendered prior to termination shall remain payable based on the milestone completion percentage.\r\n\r\n11. GOVERNING LAW\r\nThis contract shall be governed by the laws of the Republic of the Philippines.', 39200.00, '2026-09-24', '2026-09-30', 'Approved', 2, 4, '2026-09-11 11:43:03', '2026-09-11 11:24:08', '2026-09-11 11:43:03', NULL, NULL, NULL),
(5, 'SOW-2026-0004', 6, 15, 13, 'Assistance in the registration, permitting, and regulatory compliance requirements for Cotabato Trans Rental\'s vehicle rental business, including business permit processing, LTFRB/LTO documentation guidance, and operational policy setup.', '1. SCOPE OF WORK\r\nThis contract covers only the services stated in the Project Scope. Additional work outside this scope requires a separate quotation.\r\n\r\n2. PAYMENT TERMS\r\n50% downpayment is required before work begins. The remaining balance is due upon completion of the project. Late payments beyond 15 days will incur a 2% monthly charge.\r\n\r\n3. TIMELINE\r\nCompletion depends on the Client providing required documents and information on time. Delays caused by the Client may extend the project timeline.\r\n\r\n4. CONFIDENTIALITY\r\nBoth parties agree to keep all shared business information confidential.\r\n\r\n5. GOVERNMENT FEES\r\nGovernment and third-party fees (permits, filing, notarial, etc.) are not included in the professional fee and will be billed separately.\r\n\r\n6. TERMINATION\r\nEither party may terminate this agreement with a 15-day written notice. Services already rendered will still be charged accordingly.\r\n\r\n7. GOVERNING LAW\r\nThis agreement is governed by the laws of the Republic of the Philippines.', 8960.00, '2026-09-16', '2026-09-17', 'Rejected', 4, NULL, NULL, '2026-09-15 01:09:06', '2026-09-20 01:23:19', NULL, NULL, NULL);

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
  `uploaded_by_role` enum('admin','manager','supervisor') NOT NULL DEFAULT 'admin',
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
(34, 'file', 12, 'FDD&BMC.docx', NULL, NULL, 'FDD&BMC.docx', 'repository/doc_6ab12e9680bbd6.30052922_FDD_BMC.docx', 154063, 'docx', 2, 'admin', '2026-09-21 13:18:14', '2026-09-21 13:18:14'),
(35, 'file', 12, 'Letter-of-Intent-to-DICT-Region-XII.docx', NULL, NULL, 'Letter-of-Intent-to-DICT-Region-XII.docx', 'repository/doc_6ab1802d2b6008.80966367_Letter-of-Intent-to-DICT-Region-XII.docx', 78551, 'docx', 2, 'admin', '2026-09-21 19:06:21', '2026-09-21 19:06:21');

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
(1, 'QT-2026-0001', 6, 4, 'Assistance with DTI and BIR registration requirements for a new branch, including document preparation and submission support.', 'Sent', 15000.00, 12.00, 1800.00, 16800.00, '2026-09-30', 'Client requested expedited processing.', 4, '2026-08-21 23:02:47', '2026-09-09 01:21:45'),
(2, 'QT-2026-0002', 7, 5, 'Review of quarterly tax filings prior to submission, covering VAT, withholding tax, and income tax computations.', 'Approved', 12000.00, 12.00, 1440.00, 13440.00, '2026-09-15', 'Approved by client via email confirmation.', 4, '2026-08-21 23:02:47', '2026-09-09 01:09:32'),
(4, 'QT-2026-0003', 17, 1, 'Full audit of FY2025 financial statements covering accounts payable, accounts receivable, inventory valuation, and internal controls in preparation for BIR filing.', 'Approved', 52000.00, 12.00, 6240.00, 58240.00, '2026-09-25', 'Quotation valid for 30 days. 50% downpayment upon signing, balance upon completion. Includes two (2) on-site visits. Excludes government filing fees.', 4, '2026-09-11 11:17:07', '2026-09-11 11:17:17'),
(5, 'QT-2026-0004', 18, 7, 'Assess the client\'s current manual inventory process and implement a digital inventory system integrated with their existing POS for real-time stock visibility across all warehouses.', 'Approved', 35000.00, 12.00, 4200.00, 39200.00, '2026-09-24', 'Timeline: 10–12 weeks from kickoff. Software license fees are excluded and will be billed separately by the vendor. Includes 30-day post-launch support via email and phone. Client must provide warehouse access for on-site assessment (up to 3 days).', 2, '2026-09-11 11:23:16', '2026-09-11 11:23:24'),
(6, 'QT-2026-0005', 15, 13, 'Assistance in the registration, permitting, and regulatory compliance requirements for Cotabato Trans Rental\'s vehicle rental business, including business permit processing, LTFRB/LTO documentation guidance, and operational policy setup.', 'Approved', 8000.00, 12.00, 960.00, 8960.00, '2026-09-16', 'Quotation is valid for 30 days from date of issuance. 50% downpayment required upon approval of quotation to begin engagement. Remaining balance due upon completion of permit processing. Prices are exclusive of government filing fees, which will be billed separately at actual cost.', 4, '2026-09-15 01:07:38', '2026-09-15 01:07:44'),
(7, 'QT-2026-0006', 15, 13, 'Assessment of risks related to vehicle rental and transport operations.', 'Approved', 2000.00, 12.00, 240.00, 2240.00, '2026-09-24', 'Includes assessment of fleet operations, identification of common operational risks, and preparation of basic recommendations.', 2, '2026-09-16 07:11:11', '2026-09-16 07:11:23'),
(8, 'QT-2026-0007', 19, 1, '', 'Approved', 8000.00, 12.00, 960.00, 8960.00, '2026-09-24', '', 2, '2026-09-16 07:26:51', '2026-09-16 07:27:09'),
(9, 'QT-2026-0008', 18, 7, '', 'Draft', 10.00, 0.00, 0.00, 10.00, '2026-09-23', '', 2, '2026-09-16 08:00:23', '2026-09-16 08:00:23');

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
(6, 2, 'Income Tax Computation Check', 1.00, 4500.00, 4500.00, 2),
(8, 4, 'Financial statements review and verification', 1.00, 50000.00, 50000.00, 0),
(9, 4, 'Inventory valuation audit', 1.00, 2000.00, 2000.00, 1),
(10, 5, 'Systems assessment and requirements gathering', 1.00, 35000.00, 35000.00, 0),
(11, 6, 'Business Registration Assistance (DTI/SEC, Mayor\'s Permit, BIR)', 1.00, 8000.00, 8000.00, 0),
(12, 7, 'Fleet Risk Assessment', 1.00, 2000.00, 2000.00, 0),
(13, 8, 'Business Reistration', 1.00, 4000.00, 4000.00, 0),
(14, 8, 'Taxation', 1.00, 4000.00, 4000.00, 1),
(15, 9, 'w', 1.00, 10.00, 10.00, 0);

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
(2, 2, 'Process Improvement Review', 'Client requested a review of current business processes.', NULL, 'In Progress', NULL, NULL, '2026-08-10 07:35:32', '2026-08-10 07:35:32'),
(3, 3, 'IT Infrastructure Assessment', 'Client requested an assessment of their current IT infrastructure.', NULL, 'Completed', NULL, NULL, '2026-08-10 07:35:32', '2026-08-10 07:35:32'),
(6, 4, 'Business Registration Assistance', 'Client needs help completing DTI and BIR registration requirements for a new branch.', 'Business Registration', 'In Progress', 1, 2, '2026-08-20 02:56:59', '2026-09-06 02:36:02'),
(7, 5, 'Quarterly Tax Filing Review', 'Client requested a review of their quarterly tax filings before submission.', 'Tax Advisory', 'In Progress', 1, 4, '2026-08-20 02:56:59', '2026-08-30 04:55:04'),
(8, 6, 'Inventory Bookkeeping Setup', 'Client wants a proper bookkeeping system set up for their retail inventory.', 'Bookkeeping', 'New', 1, 2, '2026-08-20 02:56:59', '2026-09-06 02:49:46'),
(9, 7, 'Warehouse Systems Assessment', 'Client requested an assessment of their current warehouse and logistics IT systems.', 'IT Infrastructure Assessment', 'New', 1, 2, '2026-08-20 02:56:59', '2026-09-08 05:29:45'),
(10, 8, 'Enrollment Process Improvement', 'Client wants to streamline their student enrollment and records process.', 'Business Process Improvement', 'New', NULL, NULL, '2026-08-20 02:56:59', '2026-08-20 02:56:59'),
(11, 9, 'Data Privacy Compliance Check', 'Client needs a compliance review of their customer data handling practices.', 'Data Privacy Compliance', 'New', NULL, NULL, '2026-08-20 02:56:59', '2026-08-20 02:56:59'),
(12, 10, 'Marketing Strategy Development', 'Client wants a marketing plan to expand their IT services to new areas.', 'Marketing Strategy', 'New', NULL, NULL, '2026-08-20 02:56:59', '2026-08-20 02:56:59'),
(13, 11, 'Supplier Contract Drafting', 'Client needs a standard supplier contract template drafted for their rice mill operations.', 'Contract Drafting', 'New', NULL, NULL, '2026-08-20 02:56:59', '2026-08-20 02:56:59'),
(14, 12, 'Client Relations Audit', 'Client wants an audit of their patient/client relations and feedback handling process.', 'Client Relations Management', 'In Progress', 1, 2, '2026-08-20 02:56:59', '2026-09-08 05:30:25'),
(15, 13, 'Fleet Risk Assessment', 'Client requested a risk assessment covering their vehicle rental and transport operations.', 'Risk Assessment', 'New', NULL, NULL, '2026-08-20 02:56:59', '2026-08-20 02:56:59'),
(17, 1, 'ABC Manufacturing Corp.', 'Client is requesting a full audit of their FY2025 financial statements in preparation for BIR filing and bank loan application. Scope includes review of accounts payable, accounts receivable, inventory valuation, and internal controls. Target completion before March 31, 2026.', 'Audit Assistance', 'New', NULL, NULL, '2026-09-11 11:14:24', '2026-09-11 11:14:24'),
(18, 7, 'Warehouse Inventory System Setup', 'Client wants to digitize their warehouse inventory tracking. Need assessment of current manual process, recommendation of suitable inventory software, data migration support, and staff training. Integration with existing POS system is required.', 'IT Infrastructure Assessment', 'New', NULL, NULL, '2026-09-11 11:21:19', '2026-09-11 11:21:19'),
(19, 1, 'Business Registration', '', 'Business Registration', 'New', NULL, NULL, '2026-09-16 07:25:18', '2026-09-16 07:25:18'),
(20, 1, 'Website revamp for booking portal', 'Client wants a redesign of their online booking system.', 'Web Development', 'New', NULL, NULL, '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(21, 2, 'Network infrastructure audit', 'Assess current network setup and recommend upgrades.', 'Network Engineering', 'In Progress', NULL, NULL, '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(22, 3, 'Inventory management system', 'Build a system to track retail stock across branches.', 'Software Development', 'Completed', NULL, NULL, '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(23, 4, 'Production line automation review', 'Evaluate automation opportunities in the factory floor.', 'Industrial Engineering', 'New', NULL, NULL, '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(24, 5, 'Property listing mobile app', 'Develop a mobile app for property listings.', 'Mobile Development', 'In Progress', NULL, NULL, '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(25, 6, 'Supply chain optimization', 'Improve supply chain efficiency for perishable goods.', 'Logistics Planning', 'Completed', NULL, NULL, '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(26, 7, 'Financial reporting dashboard', 'Create a dashboard for real-time financial reporting.', 'Data Analytics', 'New', NULL, NULL, '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(27, 8, 'Quality control system upgrade', 'Upgrade QC processes for textile production.', 'Quality Assurance', 'In Progress', NULL, NULL, '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(28, 9, 'CCTV system integration', 'Integrate new CCTV systems across client sites.', 'Systems Integration', 'Cancelled', NULL, NULL, '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(29, 10, 'Hotel booking chatbot', 'Build a chatbot to assist with hotel reservations.', 'AI Development', 'New', NULL, NULL, '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(30, 11, 'Construction project tracker', 'Develop a tool to track ongoing construction projects.', 'Software Development', 'In Progress', NULL, NULL, '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(31, 12, 'Solar panel monitoring system', 'Create a monitoring dashboard for solar installations.', 'IoT Development', 'Completed', NULL, NULL, '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(32, 13, 'Farm inventory tracking app', 'App to track crop yield and inventory.', 'Mobile Development', 'New', NULL, NULL, '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(33, 14, 'Content management platform', 'Build a CMS for managing digital media content.', 'Web Development', 'In Progress', NULL, NULL, '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(34, 15, 'Freight tracking system', 'Real-time tracking system for freight shipments.', 'Software Development', 'New', NULL, NULL, '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(35, 16, 'Client relationship management tool', 'CRM tool for consulting engagements.', 'Software Development', 'Completed', NULL, NULL, '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(36, 17, 'Data pipeline for research lab', 'Build a data pipeline for lab research data.', 'Data Engineering', 'New', NULL, NULL, '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(37, 18, 'Pharmaceutical inventory system', 'System to track medicine stock and expiry.', 'Software Development', 'In Progress', NULL, NULL, '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(38, 19, 'Retail POS system upgrade', 'Upgrade point-of-sale systems across branches.', 'Systems Integration', 'Completed', NULL, NULL, '2026-09-18 12:45:46', '2026-09-18 12:45:46'),
(39, 20, 'Insurance claims portal', 'Online portal for filing and tracking insurance claims.', 'Web Development', 'New', NULL, NULL, '2026-09-18 12:45:46', '2026-09-18 12:45:46');

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
(3, 'Maria', '', 'Santos', '2001-02-10', 'Female', 'Marbel, South Cotabato', '+639091912345', 'staff@kmpconsulthub.com', '$2y$10$92IXUNpkjO0rOQ5byMi.YeIVpKzO0FGgeZzYqLh0Uk3z1lRAJVNqK', 'staff', 'Active', '2026-08-09 21:10:42', '2026-08-10 07:30:46'),
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
  MODIFY `client_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `contract_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `contract_revisions`
--
ALTER TABLE `contract_revisions`
  MODIFY `revision_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `knowledge_documents`
--
ALTER TABLE `knowledge_documents`
  MODIFY `document_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `quotations`
--
ALTER TABLE `quotations`
  MODIFY `quotation_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `quotation_items`
--
ALTER TABLE `quotation_items`
  MODIFY `item_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `service_requests`
--
ALTER TABLE `service_requests`
  MODIFY `request_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

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