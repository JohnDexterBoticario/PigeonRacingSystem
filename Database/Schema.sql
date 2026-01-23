CREATE DATABASE IF NOT EXISTS pigeon_racing;
USE pigeon_racing;

-- USERS
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role ENUM('admin','member') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- MEMBERS
CREATE TABLE members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,               -- Add this column to link users
    loft_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- LOFTS
CREATE TABLE lofts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    latitude DECIMAL(10,6) NOT NULL,
    longitude DECIMAL(10,6) NOT NULL,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
);

-- PIGEONS
CREATE TABLE pigeons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ring_number VARCHAR(50) UNIQUE NOT NULL,
    year INT NOT NULL,
    color VARCHAR(50),
    gender ENUM('Male','Female'),
    member_id INT NOT NULL,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
);

-- RACES
CREATE TABLE races (
    id INT AUTO_INCREMENT PRIMARY KEY,
    race_name VARCHAR(100) NOT NULL,
    release_point VARCHAR(100) NOT NULL,
    release_lat DECIMAL(10,6) NOT NULL,
    release_lng DECIMAL(10,6) NOT NULL,
    release_datetime DATETIME NOT NULL,
    status ENUM('Pending','Released','Completed') DEFAULT 'Pending'
);

-- RACE ENTRIES
CREATE TABLE race_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    race_id INT NOT NULL,
    pigeon_id INT NOT NULL,
    distance_km DECIMAL(8,3),
    FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE,
    FOREIGN KEY (pigeon_id) REFERENCES pigeons(id) ON DELETE CASCADE
);

-- RESULTS
CREATE TABLE race_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    race_id INT NOT NULL,
    pigeon_id INT NOT NULL,
    arrival_time DATETIME,
    speed_mpm DECIMAL(8,2),
    rank INT,
    FOREIGN KEY (race_id) REFERENCES races(id) ON DELETE CASCADE,
    FOREIGN KEY (pigeon_id) REFERENCES pigeons(id) ON DELETE CASCADE
);
-- SYSTEM LOGS
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    page VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- TRAINING RECORDS
CREATE TABLE training_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    pigeon_id INT NOT NULL,
    training_date DATE NOT NULL,
    distance_km DECIMAL(8,2),
    duration_minutes INT,
    notes TEXT,
    FOREIGN KEY (member_id) REFERENCES members(id),
    FOREIGN KEY (pigeon_id) REFERENCES pigeons(id)
);