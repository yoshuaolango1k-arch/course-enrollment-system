CREATE DATABASE IF NOT EXISTS course_enrollment CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE course_enrollment;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  phone VARCHAR(20) DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('student','admin') NOT NULL DEFAULT 'student',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS courses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  instructor VARCHAR(120) DEFAULT 'Course Team',
  duration VARCHAR(80) DEFAULT 'Self-paced',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  provider ENUM('paystack','mpesa') NOT NULL,
  reference VARCHAR(100) NOT NULL UNIQUE,
  provider_reference VARCHAR(100) DEFAULT NULL,
  provider_receipt VARCHAR(100) DEFAULT NULL,
  amount_kes INT UNSIGNED NOT NULL DEFAULT 1,
  status ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
  provider_payload JSON DEFAULT NULL,
  verified_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_payments_course FOREIGN KEY (course_id) REFERENCES courses(id),
  INDEX idx_payments_status(status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS enrollments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  payment_id BIGINT UNSIGNED NOT NULL,
  status ENUM('active','pending','cancelled') NOT NULL DEFAULT 'pending',
  enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_student_course (user_id, course_id),
  CONSTRAINT fk_enroll_user FOREIGN KEY (user_id) REFERENCES users(id),
  CONSTRAINT fk_enroll_course FOREIGN KEY (course_id) REFERENCES courses(id),
  CONSTRAINT fk_enroll_payment FOREIGN KEY (payment_id) REFERENCES payments(id)
) ENGINE=InnoDB;

INSERT INTO courses (title, description, instructor, duration) VALUES
('Introduction to Web Development','Learn the foundations of HTML, CSS, JavaScript and how websites work.','ICT Department','4 weeks'),
('Database Fundamentals','Understand relational databases, tables, SQL queries and data management.','ICT Department','3 weeks'),
('PHP Programming Basics','Build dynamic web applications using PHP, forms, sessions and database connections.','ICT Department','5 weeks');
