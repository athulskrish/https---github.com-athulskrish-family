-- Create the database with proper encoding
-- CREATE DATABASE IF NOT EXISTS familytree
--     CHARACTER SET = utf8mb4
--     COLLATE = utf8mb4_unicode_ci;
-- USE familytree;

-- Security settings
-- SET GLOBAL sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION';
-- SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ENGINE_SUBSTITUTION';

-- Users table with enhanced security
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    status ENUM('active', 'inactive', 'banned') DEFAULT 'inactive',
    email_verified BOOLEAN DEFAULT FALSE,
    failed_login_attempts INT DEFAULT 0,
    last_login_attempt TIMESTAMP NULL,
    account_locked_until TIMESTAMP NULL,
    password_last_changed TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CHECK (LENGTH(password) >= 60), -- For bcrypt hashes
    CHECK (email REGEXP '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\.[A-Za-z]{2,}$'),
    CHECK (LENGTH(username) >= 3)
) ENGINE=InnoDB;

-- Family trees table
CREATE TABLE IF NOT EXISTS family_trees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    owner_id INT NOT NULL,
    privacy_level ENUM('public', 'private', 'shared') DEFAULT 'private',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);

-- People table with enhanced details
CREATE TABLE IF NOT EXISTS people (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tree_id INT NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    last_name VARCHAR(50) NOT NULL,
    maiden_name VARCHAR(50),
    gender ENUM('M', 'F', 'O') NOT NULL,
    date_of_birth DATE,
    date_of_death DATE,
    birth_place VARCHAR(255),
    death_place VARCHAR(255),
    photo_url VARCHAR(255),
    occupation VARCHAR(100),
    notes TEXT,
    is_living BOOLEAN DEFAULT TRUE,
    generation_level INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tree_id) REFERENCES family_trees(id) ON DELETE CASCADE
);

-- Relationships table with enhanced details
CREATE TABLE IF NOT EXISTS relationships (
    id INT PRIMARY KEY AUTO_INCREMENT,
    person1_id INT NOT NULL,
    person2_id INT NOT NULL,
    relationship_type VARCHAR(50),
    relationship_subtype VARCHAR(50),
    marriage_date DATE,
    marriage_place VARCHAR(255),
    divorce_date DATE,
    is_biological BOOLEAN DEFAULT TRUE,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (person1_id) REFERENCES people(id) ON DELETE CASCADE,
    FOREIGN KEY (person2_id) REFERENCES people(id) ON DELETE CASCADE,
    UNIQUE KEY unique_relationship (person1_id, person2_id, relationship_type)
);

-- Media table
CREATE TABLE IF NOT EXISTS media (
    id INT PRIMARY KEY AUTO_INCREMENT,
    person_id INT NOT NULL,
    media_type ENUM('photo', 'document', 'video') NOT NULL,
    file_url VARCHAR(255) NOT NULL,
    title VARCHAR(100),
    description TEXT,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE
);

-- Tree sharing table
CREATE TABLE IF NOT EXISTS tree_sharing (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tree_id INT NOT NULL,
    user_id INT NOT NULL,
    permission_level ENUM('view', 'edit', 'admin') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tree_id) REFERENCES family_trees(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tree access table (alias for tree_sharing for backward compatibility)
CREATE TABLE IF NOT EXISTS tree_access (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tree_id INT NOT NULL,
    user_id INT NOT NULL,
    permission_level ENUM('view', 'edit', 'admin') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tree_id) REFERENCES family_trees(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Security audit log
CREATE TABLE IF NOT EXISTS security_audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action_type ENUM('login', 'logout', 'failed_login', 'password_change', 'profile_update',
                    'tree_access', 'media_upload', 'media_download', 'export', 'import') NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255),
    action_status ENUM('success', 'failure') NOT NULL,
    details JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Password reset tokens
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (token),
    CHECK (expires_at > created_at)
) ENGINE=InnoDB;

-- Email verification tokens
CREATE TABLE IF NOT EXISTS email_verification_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (token),
    CHECK (expires_at > created_at)
) ENGINE=InnoDB;

-- Remember me tokens
CREATE TABLE IF NOT EXISTS remember_tokens (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (token),
    CHECK (expires_at > created_at)
) ENGINE=InnoDB;

-- Rate limiting
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    identifier VARCHAR(255) NOT NULL,
    action_type VARCHAR(50) NOT NULL,
    attempt_count INT DEFAULT 1,
    first_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (identifier, action_type),
    CHECK (attempt_count >= 0)
) ENGINE=InnoDB;

-- Generation tracking table
CREATE TABLE IF NOT EXISTS generations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tree_id INT NOT NULL,
    generation_number INT NOT NULL,
    generation_name VARCHAR(50),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tree_id) REFERENCES family_trees(id) ON DELETE CASCADE,
    UNIQUE KEY unique_generation (tree_id, generation_number)
) ENGINE=InnoDB;
