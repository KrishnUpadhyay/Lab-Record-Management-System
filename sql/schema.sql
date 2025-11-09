-- 1. Users Table (Naya Table)
-- Ismein login/registration data store hoga
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') NOT NULL
);

-- 2. Components Table (Inventory)
CREATE TABLE components (
    component_id INT AUTO_INCREMENT PRIMARY KEY,
    component_name VARCHAR(255) NOT NULL,
    total_stock INT NOT NULL
);

-- 3. Issued Records Table (Issue/Return Data)
CREATE TABLE issued_records (
    record_id INT AUTO_INCREMENT PRIMARY KEY,
    component_id INT NOT NULL,
    student_name VARCHAR(255) NOT NULL,
    student_email VARCHAR(255) NOT NULL,
    student_phone VARCHAR(20) NOT NULL,
    issue_date DATE NOT NULL,
    return_date DATE NOT NULL,
    issued_by_assistant VARCHAR(255) NOT NULL,
    is_returned BOOLEAN DEFAULT 0,
    actual_return_date DATE DEFAULT NULL,
    FOREIGN KEY (component_id) REFERENCES components(component_id)
);