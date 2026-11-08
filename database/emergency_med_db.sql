CREATE DATABASE emergency_med_db
CHARACTER SET utf8mb4
COLLATE utf8mb4_general_ci;

USE emergency_med_db;


CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


CREATE TABLE patient_profiles (
    profile_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    full_name VARCHAR(150) NOT NULL,
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


CREATE TABLE medical_conditions (
    condition_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    condition_name VARCHAR(150) NOT NULL,
    diagnosed_year SMALLINT,
    notes TEXT,

    FOREIGN KEY (patient_id)
    REFERENCES patient_profiles(profile_id)
    ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE allergies (
    allergy_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    allergen_name VARCHAR(150) NOT NULL,
    severity VARCHAR(30),
    reaction_notes TEXT,

    FOREIGN KEY (patient_id)
    REFERENCES patient_profiles(profile_id)
    ON DELETE CASCADE
) ENGINE=InnoDB;


CREATE TABLE emergency_contacts (
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


CREATE TABLE organ_blood_registry (
    registry_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL UNIQUE,
    is_blood_donor TINYINT(1) DEFAULT 0,
    is_organ_donor TINYINT(1) DEFAULT 0,
    district VARCHAR(100),
    last_donated_date DATE,

    FOREIGN KEY (patient_id)
    REFERENCES patient_profiles(profile_id)
    ON DELETE CASCADE
) ENGINE=InnoDB;
