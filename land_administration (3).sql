-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 20, 2025 at 07:48 AM
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
-- Database: `land_administration`
--

-- --------------------------------------------------------

--
-- Table structure for table `beneficiaries`
--

CREATE TABLE `beneficiaries` (
  `ben_id` int(11) NOT NULL,
  `md5_ben_id` varchar(255) NOT NULL,
  `name` varchar(200) NOT NULL,
  `name_sinhala` varchar(150) NOT NULL,
  `name_tamil` varchar(150) NOT NULL,
  `location_id` int(11) NOT NULL,
  `is_individual` tinyint(1) DEFAULT 1,
  `contact_person` varchar(200) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `district` varchar(100) DEFAULT 'Trincomalee',
  `ds_division_id` int(11) DEFAULT NULL,
  `ds_division_text` varchar(200) DEFAULT NULL,
  `gn_division_id` int(11) DEFAULT NULL,
  `gn_division_text` varchar(200) DEFAULT NULL,
  `nic_reg_no` varchar(50) NOT NULL,
  `dob` date DEFAULT NULL,
  `nationality` varchar(50) NOT NULL,
  `telephone` varchar(50) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `language` varchar(50) NOT NULL DEFAULT 'English',
  `created_by` int(11) DEFAULT NULL,
  `created_on` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` int(11) NOT NULL DEFAULT 1,
  `address_tamil` varchar(200) NOT NULL,
  `address_sinhala` varchar(200) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `client_registration`
--

CREATE TABLE `client_registration` (
  `c_id` int(10) NOT NULL,
  `client_name` varchar(300) NOT NULL,
  `coordinates` varchar(5000) NOT NULL,
  `md5_client` varchar(250) NOT NULL,
  `user_license` int(10) NOT NULL,
  `client_id` varchar(100) NOT NULL,
  `client_type` varchar(50) NOT NULL,
  `district` varchar(50) NOT NULL,
  `supply_by` varchar(50) NOT NULL,
  `client_email` varchar(100) NOT NULL,
  `client_phone` varchar(30) NOT NULL,
  `contact_name` varchar(80) NOT NULL,
  `contact_email` varchar(100) NOT NULL,
  `contact_phone` varchar(20) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `regNumber` varchar(80) NOT NULL,
  `region` varchar(50) NOT NULL,
  `bank_and_branch` varchar(150) NOT NULL,
  `account_number` varchar(150) NOT NULL,
  `account_name` varchar(150) NOT NULL,
  `payment_sms` int(11) NOT NULL DEFAULT 0,
  `remindes_sms` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `gn_division`
--

CREATE TABLE `gn_division` (
  `gn_id` int(11) NOT NULL,
  `gn_name` varchar(100) NOT NULL,
  `gn_no` varchar(50) DEFAULT NULL,
  `gn_code` varchar(50) DEFAULT NULL,
  `c_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `group_activity_map`
--

CREATE TABLE `group_activity_map` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `act_id` int(11) NOT NULL,
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `land_registration`
--

CREATE TABLE `land_registration` (
  `land_id` int(11) NOT NULL,
  `ds_id` int(11) NOT NULL,
  `gn_id` int(11) NOT NULL,
  `address` varchar(255) NOT NULL,
  `land_area` decimal(15,4) NOT NULL,
  `scaled_by` varchar(32) NOT NULL,
  `hectares` decimal(15,6) NOT NULL,
  `latitude` varchar(10) NOT NULL,
  `longitude` varchar(10) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_on` datetime NOT NULL DEFAULT current_timestamp(),
  `lcg_area` decimal(15,6) DEFAULT NULL,
  `lcg_area_unit` varchar(32) DEFAULT NULL,
  `lcg_hectares` decimal(15,6) DEFAULT NULL,
  `lcg_plan_no` varchar(64) DEFAULT NULL,
  `val_area` decimal(15,6) DEFAULT NULL,
  `val_area_unit` varchar(32) DEFAULT NULL,
  `val_hectares` decimal(15,6) DEFAULT NULL,
  `val_plan_no` varchar(64) DEFAULT NULL,
  `survey_area` decimal(15,6) DEFAULT NULL,
  `survey_area_unit` varchar(32) DEFAULT NULL,
  `survey_hectares` decimal(15,6) DEFAULT NULL,
  `survey_plan_no` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `leases`
--

CREATE TABLE `leases` (
  `lease_id` int(11) NOT NULL,
  `land_id` int(11) NOT NULL,
  `beneficiary_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `lease_number` varchar(100) DEFAULT NULL,
  `file_number` varchar(100) NOT NULL,
  `valuation_amount` decimal(15,2) NOT NULL,
  `premium` decimal(10,2) NOT NULL,
  `initial_annual_rent` decimal(10,2) NOT NULL,
  `annual_rent_percentage` decimal(5,2) DEFAULT 4.00,
  `revision_period` int(11) DEFAULT 5,
  `revision_percentage` decimal(5,2) DEFAULT 20.00,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` enum('active','expired','cancelled') DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_on` datetime DEFAULT current_timestamp(),
  `approved_date` date DEFAULT NULL,
  `valuation_date` date NOT NULL DEFAULT current_timestamp(),
  `value_date` date DEFAULT NULL,
  `duration_years` decimal(10,2) NOT NULL,
  `lease_type_id` int(11) NOT NULL,
  `type_of_project` varchar(100) NOT NULL,
  `name_of_the_project` varchar(100) NOT NULL,
  `updated_by` varchar(10) NOT NULL,
  `updated_on` date DEFAULT NULL,
  `lease_status` int(11) NOT NULL DEFAULT 1,
  `first_lease` int(11) NOT NULL DEFAULT 1,
  `last_lease_annual_value` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lease_master`
--

CREATE TABLE `lease_master` (
  `lease_type_id` int(11) NOT NULL,
  `lease_type_name` varchar(100) NOT NULL,
  `purpose` varchar(50) NOT NULL,
  `base_rent_percent` decimal(5,2) NOT NULL,
  `economy_rate` decimal(10,2) NOT NULL,
  `economy_valuvation` decimal(10,2) NOT NULL,
  `premium_percent` decimal(5,2) DEFAULT NULL,
  `duration_years` int(11) NOT NULL,
  `revision_interval` int(11) DEFAULT NULL,
  `revision_increase_percent` decimal(5,2) DEFAULT NULL,
  `penalty_rate` decimal(5,2) DEFAULT NULL,
  `allow_interest_waiver` tinyint(1) DEFAULT 0,
  `discount_percent` decimal(5,2) DEFAULT NULL,
  `discount_start` date DEFAULT NULL,
  `discount_end` date DEFAULT NULL,
  `effective_from` date NOT NULL,
  `discount_rate` decimal(10,2) NOT NULL,
  `premium_times` int(11) NOT NULL,
  `status` enum('Active','Inactive') DEFAULT 'Active',
  `created_by` int(11) DEFAULT NULL,
  `created_on` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lease_payments`
--

CREATE TABLE `lease_payments` (
  `payment_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL,
  `lease_id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `rent_paid` decimal(10,2) NOT NULL,
  `panalty_paid` decimal(10,2) NOT NULL,
  `premium_paid` decimal(10,2) NOT NULL,
  `discount_apply` decimal(10,2) NOT NULL,
  `current_year_payment` decimal(10,2) NOT NULL,
  `payment_type` enum('rent','penalty','both') DEFAULT 'both',
  `receipt_number` varchar(100) DEFAULT NULL,
  `payment_method` enum('cash','cheque','bank_transfer','online') DEFAULT 'cash',
  `reference_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_on` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lease_payments_detail`
--

CREATE TABLE `lease_payments_detail` (
  `id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `rent_paid` decimal(10,2) NOT NULL,
  `penalty_paid` decimal(10,2) NOT NULL,
  `premium_paid` decimal(10,2) NOT NULL,
  `discount_apply` decimal(10,2) NOT NULL,
  `current_year_payment` decimal(10,2) NOT NULL,
  `status` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lease_schedules`
--

CREATE TABLE `lease_schedules` (
  `schedule_id` int(11) NOT NULL,
  `lease_id` int(11) NOT NULL,
  `schedule_year` year(4) NOT NULL,
  `due_date` date NOT NULL DEFAULT current_timestamp(),
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `base_amount` decimal(15,2) NOT NULL,
  `premium` decimal(10,2) NOT NULL,
  `premium_paid` decimal(10,2) NOT NULL,
  `annual_amount` decimal(15,2) NOT NULL,
  `panalty` decimal(10,2) NOT NULL,
  `paid_rent` decimal(10,2) NOT NULL,
  `discount_apply` decimal(10,2) NOT NULL,
  `total_paid` decimal(10,2) NOT NULL,
  `panalty_paid` decimal(10,2) NOT NULL,
  `revision_number` int(11) DEFAULT 0,
  `is_revision_year` tinyint(1) DEFAULT 0,
  `penalty_rate` decimal(5,2) DEFAULT 10.00,
  `status` enum('pending','paid','overdue') DEFAULT 'pending',
  `created_on` datetime DEFAULT current_timestamp(),
  `penalty_last_calc` date DEFAULT NULL,
  `penalty_updated_by` int(11) NOT NULL,
  `penalty_remarks` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lease_schedules_temp`
--

CREATE TABLE `lease_schedules_temp` (
  `schedule_id` int(11) NOT NULL,
  `lease_id` int(11) NOT NULL,
  `schedule_year` year(4) NOT NULL,
  `due_date` date NOT NULL DEFAULT current_timestamp(),
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `base_amount` decimal(15,2) NOT NULL,
  `premium` decimal(10,2) NOT NULL,
  `premium_paid` decimal(10,2) NOT NULL,
  `annual_amount` decimal(15,2) NOT NULL,
  `panalty` decimal(10,2) NOT NULL,
  `paid_rent` decimal(10,2) NOT NULL,
  `discount_apply` decimal(10,2) NOT NULL,
  `total_paid` decimal(10,2) NOT NULL,
  `panalty_paid` decimal(10,2) NOT NULL,
  `revision_number` int(11) DEFAULT 0,
  `is_revision_year` tinyint(1) DEFAULT 0,
  `penalty_rate` decimal(5,2) DEFAULT 10.00,
  `status` enum('pending','paid','overdue') DEFAULT 'pending',
  `created_on` datetime DEFAULT current_timestamp(),
  `penalty_last_calc` date DEFAULT NULL,
  `penalty_updated_by` int(11) NOT NULL,
  `penalty_remarks` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `letter_head`
--

CREATE TABLE `letter_head` (
  `id` int(11) NOT NULL,
  `entity` varchar(60) NOT NULL,
  `address` varchar(100) NOT NULL,
  `email` varchar(50) NOT NULL,
  `telephone` varchar(100) NOT NULL,
  `vat_no` varchar(20) NOT NULL,
  `reg_no` varchar(100) NOT NULL,
  `invoice_prefix` varchar(10) NOT NULL,
  `admin_device_approval` int(11) NOT NULL DEFAULT 0,
  `company_name` varchar(100) NOT NULL,
  `VAT` varchar(50) NOT NULL,
  `gm_mobile` varchar(12) NOT NULL,
  `system_email` varchar(50) NOT NULL,
  `domain` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `ip_address` varchar(45) DEFAULT NULL,
  `attempt_time` datetime DEFAULT NULL,
  `try_for` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ltl_feald_visits`
--

CREATE TABLE `ltl_feald_visits` (
  `id` int(11) NOT NULL,
  `lease_id` int(11) NOT NULL,
  `date` date DEFAULT NULL,
  `officers_visited` varchar(150) NOT NULL,
  `visite_status` varchar(50) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `recodrd_on` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ltl_land_document_type`
--

CREATE TABLE `ltl_land_document_type` (
  `doc_type_id` int(11) NOT NULL,
  `doc_name` varchar(255) NOT NULL,
  `doc_group` varchar(255) NOT NULL,
  `order_no` int(11) NOT NULL,
  `approval_required` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 1,
  `print_url` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ltl_land_files`
--

CREATE TABLE `ltl_land_files` (
  `id` int(11) NOT NULL,
  `ben_id` int(11) NOT NULL,
  `file_type` varchar(150) NOT NULL,
  `file_url` varchar(250) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `submitted_date` date DEFAULT NULL,
  `received_date` date DEFAULT NULL,
  `referance_no` varchar(150) NOT NULL,
  `approval_status` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ltl_land_registration`
--

CREATE TABLE `ltl_land_registration` (
  `land_id` int(11) NOT NULL,
  `ben_id` int(11) NOT NULL,
  `ds_id` int(11) NOT NULL,
  `gn_id` int(11) NOT NULL,
  `land_address` varchar(255) NOT NULL,
  `landBoundary` varchar(2000) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT '1',
  `sketch_plan_no` varchar(255) NOT NULL,
  `plc_plan_no` varchar(255) NOT NULL,
  `survey_plan_no` varchar(255) NOT NULL,
  `extent` varchar(50) NOT NULL,
  `extent_unit` varchar(50) NOT NULL,
  `extent_ha` varchar(30) NOT NULL,
  `developed_status` varchar(150) NOT NULL DEFAULT 'Not Developed'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ltl_premium_change`
--

CREATE TABLE `ltl_premium_change` (
  `id` int(11) NOT NULL,
  `lease_id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `old_amount` decimal(10,2) NOT NULL,
  `new_amount` decimal(10,2) NOT NULL,
  `created_by` int(11) NOT NULL,
  `record_on` datetime NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ltl_reminders`
--

CREATE TABLE `ltl_reminders` (
  `id` int(11) NOT NULL,
  `lease_id` int(11) NOT NULL,
  `reminders_type` varchar(50) NOT NULL,
  `sent_date` date DEFAULT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `created_by` int(11) NOT NULL,
  `created_on` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ltl_write_off`
--

CREATE TABLE `ltl_write_off` (
  `id` int(11) NOT NULL,
  `lease_id` int(11) NOT NULL,
  `schedule_id` int(11) NOT NULL,
  `write_off_amount` decimal(10,2) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_on` datetime NOT NULL DEFAULT current_timestamp(),
  `status` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manage_activities`
--

CREATE TABLE `manage_activities` (
  `act_id` int(11) NOT NULL,
  `activity` varchar(50) NOT NULL,
  `module` varchar(20) NOT NULL,
  `is_menu` int(11) NOT NULL DEFAULT 0,
  `activity_url` varchar(150) NOT NULL,
  `icon_script` varchar(60) NOT NULL,
  `order_no` int(11) NOT NULL DEFAULT 1000,
  `status` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `manage_user_group`
--

CREATE TABLE `manage_user_group` (
  `group_id` int(11) NOT NULL,
  `group_name` varchar(50) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_log`
--

CREATE TABLE `sms_log` (
  `id` int(11) NOT NULL,
  `lease_id` int(11) NOT NULL,
  `mobile_number` varchar(30) NOT NULL,
  `sms_type` varchar(30) NOT NULL,
  `sms_text` varchar(300) NOT NULL,
  `sent_status` varchar(30) NOT NULL,
  `delivery_status` varchar(30) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sms_templates`
--

CREATE TABLE `sms_templates` (
  `sms_id` int(11) NOT NULL,
  `sms_name` varchar(100) NOT NULL,
  `tamil_sms` text DEFAULT NULL,
  `english_sms` text DEFAULT NULL,
  `sinhala_sms` text DEFAULT NULL,
  `status` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_device`
--

CREATE TABLE `user_device` (
  `d_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `pf_no` varchar(100) NOT NULL,
  `token` varchar(200) NOT NULL,
  `v_from` datetime NOT NULL DEFAULT current_timestamp(),
  `IP` varchar(200) NOT NULL,
  `last_used` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_license`
--

CREATE TABLE `user_license` (
  `usr_id` int(11) NOT NULL,
  `customer` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `mobile_no` varchar(12) NOT NULL,
  `role_id` int(11) NOT NULL,
  `password` varchar(500) NOT NULL,
  `user_rights` varchar(100) NOT NULL,
  `account_status` int(5) NOT NULL DEFAULT 2,
  `i_name` varchar(50) NOT NULL,
  `nic` varchar(30) NOT NULL,
  `company` varchar(500) NOT NULL,
  `token` varchar(112) NOT NULL,
  `dr_token` varchar(112) NOT NULL,
  `last_log_in` varchar(50) NOT NULL,
  `last_token` varchar(180) NOT NULL,
  `material` int(11) NOT NULL,
  `accounts` int(11) NOT NULL,
  `store` int(11) NOT NULL,
  `admin` int(11) NOT NULL,
  `report` int(11) NOT NULL,
  `opd` int(11) NOT NULL,
  `ip` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_location`
--

CREATE TABLE `user_location` (
  `id` int(11) NOT NULL,
  `usr_id` int(11) NOT NULL,
  `location_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_log`
--

CREATE TABLE `user_log` (
  `id` int(11) NOT NULL,
  `ben_id` int(11) DEFAULT NULL,
  `usr_id` int(11) NOT NULL,
  `module` int(11) NOT NULL,
  `location` int(11) NOT NULL,
  `action` varchar(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `detail` varchar(2500) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
  `log_date` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `beneficiaries`
--
ALTER TABLE `beneficiaries`
  ADD PRIMARY KEY (`ben_id`);

--
-- Indexes for table `client_registration`
--
ALTER TABLE `client_registration`
  ADD PRIMARY KEY (`c_id`);

--
-- Indexes for table `gn_division`
--
ALTER TABLE `gn_division`
  ADD PRIMARY KEY (`gn_id`);

--
-- Indexes for table `group_activity_map`
--
ALTER TABLE `group_activity_map`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `land_registration`
--
ALTER TABLE `land_registration`
  ADD PRIMARY KEY (`land_id`);

--
-- Indexes for table `leases`
--
ALTER TABLE `leases`
  ADD PRIMARY KEY (`lease_id`);

--
-- Indexes for table `lease_master`
--
ALTER TABLE `lease_master`
  ADD PRIMARY KEY (`lease_type_id`);

--
-- Indexes for table `lease_payments`
--
ALTER TABLE `lease_payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD UNIQUE KEY `receipt_number` (`receipt_number`),
  ADD KEY `lease_id` (`lease_id`),
  ADD KEY `schedule_id` (`schedule_id`);

--
-- Indexes for table `lease_payments_detail`
--
ALTER TABLE `lease_payments_detail`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `lease_schedules`
--
ALTER TABLE `lease_schedules`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `lease_id` (`lease_id`);

--
-- Indexes for table `lease_schedules_temp`
--
ALTER TABLE `lease_schedules_temp`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `lease_id` (`lease_id`);

--
-- Indexes for table `letter_head`
--
ALTER TABLE `letter_head`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ltl_feald_visits`
--
ALTER TABLE `ltl_feald_visits`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ltl_land_document_type`
--
ALTER TABLE `ltl_land_document_type`
  ADD PRIMARY KEY (`doc_type_id`);

--
-- Indexes for table `ltl_land_files`
--
ALTER TABLE `ltl_land_files`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ltl_land_registration`
--
ALTER TABLE `ltl_land_registration`
  ADD PRIMARY KEY (`land_id`);

--
-- Indexes for table `ltl_premium_change`
--
ALTER TABLE `ltl_premium_change`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ltl_reminders`
--
ALTER TABLE `ltl_reminders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ltl_write_off`
--
ALTER TABLE `ltl_write_off`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `manage_activities`
--
ALTER TABLE `manage_activities`
  ADD PRIMARY KEY (`act_id`);

--
-- Indexes for table `manage_user_group`
--
ALTER TABLE `manage_user_group`
  ADD PRIMARY KEY (`group_id`);

--
-- Indexes for table `sms_log`
--
ALTER TABLE `sms_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sms_templates`
--
ALTER TABLE `sms_templates`
  ADD PRIMARY KEY (`sms_id`);

--
-- Indexes for table `user_device`
--
ALTER TABLE `user_device`
  ADD PRIMARY KEY (`d_id`);

--
-- Indexes for table `user_license`
--
ALTER TABLE `user_license`
  ADD PRIMARY KEY (`usr_id`);

--
-- Indexes for table `user_location`
--
ALTER TABLE `user_location`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_log`
--
ALTER TABLE `user_log`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `beneficiaries`
--
ALTER TABLE `beneficiaries`
  MODIFY `ben_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client_registration`
--
ALTER TABLE `client_registration`
  MODIFY `c_id` int(10) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `gn_division`
--
ALTER TABLE `gn_division`
  MODIFY `gn_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `group_activity_map`
--
ALTER TABLE `group_activity_map`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `land_registration`
--
ALTER TABLE `land_registration`
  MODIFY `land_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `leases`
--
ALTER TABLE `leases`
  MODIFY `lease_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lease_master`
--
ALTER TABLE `lease_master`
  MODIFY `lease_type_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lease_payments`
--
ALTER TABLE `lease_payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lease_payments_detail`
--
ALTER TABLE `lease_payments_detail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lease_schedules`
--
ALTER TABLE `lease_schedules`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lease_schedules_temp`
--
ALTER TABLE `lease_schedules_temp`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `letter_head`
--
ALTER TABLE `letter_head`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ltl_feald_visits`
--
ALTER TABLE `ltl_feald_visits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ltl_land_document_type`
--
ALTER TABLE `ltl_land_document_type`
  MODIFY `doc_type_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ltl_land_files`
--
ALTER TABLE `ltl_land_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ltl_land_registration`
--
ALTER TABLE `ltl_land_registration`
  MODIFY `land_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ltl_premium_change`
--
ALTER TABLE `ltl_premium_change`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ltl_reminders`
--
ALTER TABLE `ltl_reminders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ltl_write_off`
--
ALTER TABLE `ltl_write_off`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `manage_activities`
--
ALTER TABLE `manage_activities`
  MODIFY `act_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `manage_user_group`
--
ALTER TABLE `manage_user_group`
  MODIFY `group_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sms_log`
--
ALTER TABLE `sms_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sms_templates`
--
ALTER TABLE `sms_templates`
  MODIFY `sms_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_device`
--
ALTER TABLE `user_device`
  MODIFY `d_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_license`
--
ALTER TABLE `user_license`
  MODIFY `usr_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_location`
--
ALTER TABLE `user_location`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_log`
--
ALTER TABLE `user_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `lease_payments`
--
ALTER TABLE `lease_payments`
  ADD CONSTRAINT `lease_payments_ibfk_1` FOREIGN KEY (`lease_id`) REFERENCES `leases` (`lease_id`);

--
-- Constraints for table `lease_schedules`
--
ALTER TABLE `lease_schedules`
  ADD CONSTRAINT `lease_schedules_ibfk_1` FOREIGN KEY (`lease_id`) REFERENCES `leases` (`lease_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
