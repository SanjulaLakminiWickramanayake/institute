CREATE DATABASE IF NOT EXISTS `nexora_institute` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `nexora_institute`;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `status` ENUM('active','hidden') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `intakes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `status` ENUM('active','hidden') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_intakes_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `courses` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `category_id` INT NOT NULL,
  `title` VARCHAR(180) NOT NULL,
  `duration` VARCHAR(60) NOT NULL,
  `overview` TEXT NOT NULL,
  `learning_outcomes` TEXT NOT NULL,
  `status` ENUM('published','hidden') NOT NULL DEFAULT 'published',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_courses_category` (`category_id`),
  KEY `idx_courses_status` (`status`),
  CONSTRAINT `fk_courses_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `categories` (`name`, `status`) VALUES
  ('IT', 'active'),
  ('Business', 'active'),
  ('Short Courses', 'active'),
  ('Professional', 'active');

INSERT IGNORE INTO `intakes` (`name`, `status`) VALUES
  ('January Intake', 'active'),
  ('May Intake', 'active'),
  ('September Intake', 'active');

INSERT INTO `courses` (`category_id`, `title`, `duration`, `overview`, `learning_outcomes`, `status`)
SELECT c.`id`, seed.`title`, seed.`duration`, seed.`overview`, seed.`learning_outcomes`, 'published'
FROM (
  SELECT 'IT' AS `category`, 'Diploma in Information Technology' AS `title`, '12 Months' AS `duration`,
    'Build practical digital and technical skills across programming, networking, and systems.' AS `overview`,
    'Build practical digital and technical skills across programming, networking, and systems.' AS `learning_outcomes`
  UNION ALL SELECT 'Professional', 'Professional Web Development', '06 Months',
    'Learn modern frontend and web development concepts through real, project-based learning.',
    'Learn modern frontend and web development concepts through real, project-based learning.'
  UNION ALL SELECT 'IT', 'Software Development Fundamentals', '08 Months',
    'Explore programming logic, coding practices, and application design with guided support.',
    'Explore programming logic, coding practices, and application design with guided support.'
  UNION ALL SELECT 'Business', 'Diploma in Business Management', '12 Months',
    'Develop leadership, communication, and operational skills for modern business environments.',
    'Develop leadership, communication, and operational skills for modern business environments.'
  UNION ALL SELECT 'Business', 'Business Administration Essentials', '09 Months',
    'Understand core business operations, planning, and organizational decision-making.',
    'Understand core business operations, planning, and organizational decision-making.'
  UNION ALL SELECT 'Short Courses', 'Graphic Design Essentials', '03 Months',
    'Explore design principles, visual storytelling, and digital creativity in a focused short course.',
    'Explore design principles, visual storytelling, and digital creativity in a focused short course.'
  UNION ALL SELECT 'Short Courses', 'Digital Marketing Fundamentals', '04 Months',
    'Learn the essentials of online marketing, branding, campaigns, and audience engagement.',
    'Learn the essentials of online marketing, branding, campaigns, and audience engagement.'
  UNION ALL SELECT 'Professional', 'Professional Communication & Career Skills', '06 Weeks',
    'Develop confident communication, presentation, interview skills, and career readiness.',
    'Develop confident communication, presentation, interview skills, and career readiness.'
) AS seed
JOIN `categories` c ON c.`name` = seed.`category`
WHERE NOT EXISTS (
  SELECT 1 FROM `courses` existing WHERE existing.`title` = seed.`title`
);

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(150) NOT NULL,
  `username` VARCHAR(150) NULL,
  `email` VARCHAR(150) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','student') NOT NULL DEFAULT 'student',
  `status` ENUM('active','inactive','locked','pending') NOT NULL DEFAULT 'active',
  `temp_password` TINYINT(1) NOT NULL DEFAULT 0,
  `must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_signup_details` (
  `user_id` INT NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `nic_number` VARCHAR(50) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_signup_details_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `signup_verifications` (
  `user_id` INT NOT NULL,
  `code_hash` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `attempts` INT NOT NULL DEFAULT 0,
  `last_sent_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `fk_signup_verifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `applications` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `course_id` INT NOT NULL,
  `intake_id` INT NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `nic_number` VARCHAR(50) NOT NULL,
  `education_level` VARCHAR(100) NOT NULL,
  `message` TEXT NULL,
  `status` ENUM('pending_verification','pending_review','approved','rejected') NOT NULL DEFAULT 'pending_verification',
  `otp_hash` VARCHAR(255) NULL,
  `otp_expires_at` DATETIME NULL,
  `otp_attempts` INT NOT NULL DEFAULT 0,
  `otp_resend_at` DATETIME NULL,
  `email_verified_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_applications_status` (`status`),
  KEY `idx_applications_email` (`email`),
  CONSTRAINT `fk_applications_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_applications_intake` FOREIGN KEY (`intake_id`) REFERENCES `intakes` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `students` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `application_id` INT NULL,
  `student_number` VARCHAR(50) NOT NULL,
  `full_name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `nic_number` VARCHAR(50) NOT NULL,
  `education_level` VARCHAR(100) NOT NULL,
  `course_id` INT NOT NULL,
  `intake_id` INT NOT NULL,
  `registration_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_students_user` (`user_id`),
  UNIQUE KEY `uq_students_student_number` (`student_number`),
  UNIQUE KEY `uq_students_email` (`email`),
  UNIQUE KEY `uq_students_application` (`application_id`),
  KEY `idx_students_course` (`course_id`),
  CONSTRAINT `fk_students_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_students_application` FOREIGN KEY (`application_id`) REFERENCES `applications` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_students_course` FOREIGN KEY (`course_id`) REFERENCES `courses` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_students_intake` FOREIGN KEY (`intake_id`) REFERENCES `intakes` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `messages` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `subject` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `sender_name` VARCHAR(150) NOT NULL,
  `sender_email` VARCHAR(150) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `recipient_email` VARCHAR(150) NOT NULL,
  `subject` VARCHAR(200) NOT NULL,
  `status` ENUM('sent','failed','queued') NOT NULL DEFAULT 'queued',
  `message` TEXT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email_logs_recipient` (`recipient_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `token_hash` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_password_resets_user` (`user_id`),
  CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
