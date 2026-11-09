-- =========================================
-- EMERGENCY MEDICAL ID AND HEALTH RECORD
-- SYSTEM WITH QR CODE
-- =========================================

CREATE DATABASE emergency_med_db;

USE emergency_med_db;

-- =========================================
-- 1. USERS TABLE
-- =========================================

CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- =========================================
-- 2. USER ROLES TABLE
-- =========================================

CREATE TABLE IF NOT EXISTS user_roles (
    user_role_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    role ENUM('patient', 'donor', 'hospital') NOT NULL,

    UNIQUE KEY unique_user_role (user_id, role),

    FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =========================================
-- 3. PATIENT PROFILES TABLE
-- =========================================

CREATE TABLE IF NOT EXISTS patient_profiles (
    profile_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    phone_number VARCHAR(30),
    dob DATE,
    gender VARCHAR(20),
    blood_group VARCHAR(5),
    height_cm DECIMAL(5,2),
    weight_kg DECIMAL(5,2),
    qr_token VARCHAR(64) UNIQUE,

    FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =========================================
-- 4. MEDICAL CONDITIONS TABLE
-- =========================================

CREATE TABLE IF NOT EXISTS medical_conditions (
    condition_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    condition_name VARCHAR(150) NOT NULL,
    diagnosed_year SMALLINT,
    notes TEXT,

    FOREIGN KEY (patient_id)
        REFERENCES patient_profiles(profile_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =========================================
-- 5. ALLERGIES TABLE
-- =========================================

CREATE TABLE IF NOT EXISTS allergies (
    allergy_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    allergen_name VARCHAR(150) NOT NULL,
    severity VARCHAR(30),
    reaction_notes TEXT,

    FOREIGN KEY (patient_id)
        REFERENCES patient_profiles(profile_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =========================================
-- 6. EMERGENCY CONTACTS TABLE
-- =========================================

CREATE TABLE IF NOT EXISTS emergency_contacts (
    contact_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    contact_name VARCHAR(150) NOT NULL,
    relationship VARCHAR(50),
    phone_number VARCHAR(30) NOT NULL,
    is_primary TINYINT(1) DEFAULT 0,

    FOREIGN KEY (patient_id)
        REFERENCES patient_profiles(profile_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =========================================
-- 7. DONOR PROFILES TABLE
-- =========================================

CREATE TABLE IF NOT EXISTS donor_profiles (
    donor_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
    dob DATE,
    gender VARCHAR(20),
    phone_number VARCHAR(30),
    blood_group VARCHAR(5),
    district VARCHAR(100),

    FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =========================================
-- 8. DONOR REGISTRY TABLE
-- =========================================

CREATE TABLE IF NOT EXISTS donor_registry (
    registry_id INT AUTO_INCREMENT PRIMARY KEY,
    donor_id INT NOT NULL UNIQUE,
    is_blood_donor TINYINT(1) DEFAULT 0,
    is_organ_donor TINYINT(1) DEFAULT 0,
    district VARCHAR(100),
    last_donated_date DATE,
    share_contact_on_accept TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,

    FOREIGN KEY (donor_id)
        REFERENCES donor_profiles(donor_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =========================================
-- 9. HOSPITAL PROFILES TABLE
-- =========================================

CREATE TABLE IF NOT EXISTS hospital_profiles (
    hospital_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    hospital_name VARCHAR(200) NOT NULL,
    registration_number VARCHAR(100),
    official_email VARCHAR(190),
    contact_number VARCHAR(30),
    address TEXT,
    district VARCHAR(100),

    verification_status ENUM(
        'pending',
        'verified',
        'rejected'
    ) DEFAULT 'pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =========================================
-- 10. HOSPITAL REQUESTS TABLE
-- =========================================

CREATE TABLE IF NOT EXISTS hospital_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    hospital_id INT NOT NULL,

    request_type ENUM('blood', 'organ') NOT NULL,

    patient_reference VARCHAR(100),
    blood_group VARCHAR(5),
    required_units INT,
    organ_type VARCHAR(50),
    required_date DATE,
    urgency VARCHAR(30),
    description TEXT,

    request_status ENUM(
        'pending',
        'approved',
        'rejected',
        'in_progress',
        'completed',
        'cancelled'
    ) DEFAULT 'pending',

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (hospital_id)
        REFERENCES hospital_profiles(hospital_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =========================================
-- 11. DONOR REQUEST RESPONSES TABLE
-- =========================================

CREATE TABLE IF NOT EXISTS donor_request_responses (
    response_id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    donor_id INT NOT NULL,
    forwarded_to_hospital TINYINT(1) NOT NULL DEFAULT 0,

    response_status ENUM(
        'pending',
        'accepted',
        'rejected'
    ) DEFAULT 'pending',

    responded_at DATETIME NULL,

    FOREIGN KEY (request_id)
        REFERENCES hospital_requests(request_id)
        ON DELETE CASCADE,

    FOREIGN KEY (donor_id)
        REFERENCES donor_profiles(donor_id)
        ON DELETE CASCADE,

    UNIQUE KEY unique_request_donor (request_id, donor_id)
) ENGINE=InnoDB;


-- =========================================
-- 12. ORGAN REFERRALS TABLE
-- =========================================

CREATE TABLE IF NOT EXISTS organ_referrals (
    referral_id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    referred_to VARCHAR(200),
    referral_method ENUM('email', 'manual') DEFAULT 'manual',

    referral_status ENUM(
        'pending',
        'referred',
        'closed'
    ) DEFAULT 'pending',

    notes TEXT,
    referred_at DATETIME NULL,

    FOREIGN KEY (request_id)
        REFERENCES hospital_requests(request_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =========================================
-- 13. PATIENT MEDICAL ACCESS REQUESTS
-- =========================================

CREATE TABLE IF NOT EXISTS patient_access_requests (
    access_request_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    admin_user_id INT NOT NULL,
    purpose TEXT NOT NULL,

    request_status ENUM(
        'pending',
        'approved',
        'rejected',
        'revoked',
        'expired'
    ) DEFAULT 'pending',

    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME NULL,
    expires_at DATETIME NULL,

    FOREIGN KEY (patient_id)
        REFERENCES patient_profiles(profile_id)
        ON DELETE CASCADE,

    FOREIGN KEY (admin_user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


-- =========================================
-- 14. MEDICAL ACCESS AUDIT LOGS
-- =========================================

CREATE TABLE IF NOT EXISTS medical_access_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    access_request_id INT NOT NULL,
    accessed_by INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    accessed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (access_request_id)
        REFERENCES patient_access_requests(access_request_id)
        ON DELETE CASCADE,

    FOREIGN KEY (accessed_by)
        REFERENCES users(user_id)
        ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE password_resets (
    reset_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    reset_token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
    ON DELETE CASCADE
);

-- =========================================
-- DATABASE CREATION COMPLETED
-- =========================================
