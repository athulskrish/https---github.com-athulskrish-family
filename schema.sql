-- Family trees table
CREATE TABLE IF NOT EXISTS family_trees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    owner_id INT NOT NULL,
    description TEXT,
    privacy_level ENUM('private', 'shared', 'public') DEFAULT 'private',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Tree access permissions table
CREATE TABLE IF NOT EXISTS tree_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tree_id INT NOT NULL,
    user_id INT NOT NULL,
    access_level ENUM('view', 'edit', 'admin') DEFAULT 'view',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tree_id) REFERENCES family_trees(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_access (tree_id, user_id)
);

-- Enhanced family members table with maiden_name field
CREATE TABLE IF NOT EXISTS family_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tree_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    maiden_name VARCHAR(100),
    birth_date DATE,
    death_date DATE,
    gender ENUM('male', 'female', 'other'),
    photo VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tree_id) REFERENCES family_trees(id) ON DELETE CASCADE
);

-- Relationships table to handle complex family connections
CREATE TABLE IF NOT EXISTS family_relationships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    person1_id INT NOT NULL,
    person2_id INT NOT NULL,
    relationship_type ENUM('parent', 'child', 'spouse', 'sibling') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (person1_id) REFERENCES family_members(id) ON DELETE CASCADE,
    FOREIGN KEY (person2_id) REFERENCES family_members(id) ON DELETE CASCADE,
    UNIQUE KEY unique_relationship (person1_id, person2_id, relationship_type)
);

-- Migration script to add maiden_name to existing table
-- Run this if the family_members table already exists:
-- ALTER TABLE family_members ADD COLUMN maiden_name VARCHAR(100) AFTER name;

-- Example indexes for better performance (optional)
CREATE INDEX idx_family_members_tree_id ON family_members(tree_id);
CREATE INDEX idx_family_members_name ON family_members(name);
CREATE INDEX idx_family_members_maiden_name ON family_members(maiden_name);
CREATE INDEX idx_family_relationships_person1 ON family_relationships(person1_id);
CREATE INDEX idx_family_relationships_person2 ON family_relationships(person2_id);