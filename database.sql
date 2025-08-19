-- Create database
CREATE DATABASE workshop_booking;
USE workshop_booking;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('user', 'admin') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Mechanics table
CREATE TABLE mechanics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    specialty VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    status ENUM('available', 'unavailable') DEFAULT 'available',
    current_orders INT DEFAULT 0,
    max_orders INT DEFAULT 4,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Repair services table
CREATE TABLE repair_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    duration_hours INT NOT NULL,
    status ENUM('available', 'unavailable') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Bookings table
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    mechanic_id INT NOT NULL,
    service_id INT NOT NULL,
    booking_date DATE NOT NULL,
    booking_time TIME NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    total_price DECIMAL(10,2) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (mechanic_id) REFERENCES mechanics(id),
    FOREIGN KEY (service_id) REFERENCES repair_services(id)
);

-- Insert sample admin user (password: admin123)
INSERT INTO users (username, email, password, role) VALUES 
('admin', 'admin@workshop.com', 'admin123', 'admin');

-- Insert sample mechanics
INSERT INTO mechanics (name, specialty, email, phone) VALUES 
('Tawkir Arifin', 'Engine Specialist', 'tawkirarifin@gmail.com', '01911111111'),
('Nafis Rayan', 'Brake Expert', 'nafis@gmail.com', '01911111111'),
('Tashin Rahman', 'Transmission Specialist', 'tashin@gmail.com', '01911111111'),
('Tawsif Islam', 'Electrical Systems', 'tawsif@gmail.com', '01911111111');
('Saiyara Iffat', 'Electrical Systems', 'saiyaraiffat@gmail.com', '01911111111');
('Amily Khan', 'Electrical Systems', 'amilykhan@gmail.com', '01911111111');

-- Insert sample repair services
INSERT INTO repair_services (service_name, description, price, duration_hours) VALUES 
('Oil Change', 'Complete oil and filter change', 45.00, 1),
('Brake Repair', 'Brake pad and rotor replacement', 120.00, 2),
('Engine Diagnostic', 'Complete engine diagnostic check', 85.00, 1),
('Transmission Service', 'Transmission fluid change and inspection', 150.00, 2),
('Battery Replacement', 'Car battery replacement and testing', 95.00, 1),
('Tire Rotation', 'Complete tire rotation and balancing', 35.00, 1);