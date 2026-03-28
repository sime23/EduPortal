-- ============================================================
-- Assignment Portal Database Schema
-- Database: assignment_portal
-- ============================================================

CREATE DATABASE IF NOT EXISTS `assweb`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `assweb`;

-- ------------------------------------------------------------
-- Table: users
-- Stores both student and teacher accounts
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `full_name`   VARCHAR(120)    NOT NULL,
  `email`       VARCHAR(180)    NOT NULL UNIQUE,
  `password`    VARCHAR(255)    NOT NULL,          -- bcrypt hash
  `role`        ENUM('student','teacher','admin') NOT NULL DEFAULT 'student',
  `profile_pic` VARCHAR(255)    NOT NULL DEFAULT 'default_avatar.png',
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_email` (`email`),
  INDEX `idx_role`  (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: classes
-- Teachers create classes and add students to them
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `classes` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `teacher_id`  INT UNSIGNED    NOT NULL,
  `class_name`  VARCHAR(200)    NOT NULL,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_classes_teacher`
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: class_students
-- Associates students with specific classes
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `class_students` (
  `class_id`    INT UNSIGNED    NOT NULL,
  `student_id`  INT UNSIGNED    NOT NULL,
  `joined_at`   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`class_id`, `student_id`),
  CONSTRAINT `fk_class_students_class`
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_class_students_student`
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: assignments
-- Created by teachers; visible to all students
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `assignments` (
  `id`          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `class_id`    INT UNSIGNED    DEFAULT NULL,      -- FK → classes.id
  `title`       VARCHAR(200)    NOT NULL,
  `description` TEXT            NOT NULL,
  `attachment`  VARCHAR(255)    DEFAULT NULL,
  `master_correction` VARCHAR(255) DEFAULT NULL,   -- Master correction file (visible after deadline)
  `deadline`    DATETIME        NOT NULL,
  `created_by`  INT UNSIGNED    NOT NULL,          -- FK → users.id (teacher)
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_assignments_teacher`
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignments_class`
    FOREIGN KEY (`class_id`) REFERENCES `classes`(`id`) ON DELETE CASCADE,
  INDEX `idx_deadline` (`deadline`),
  INDEX `idx_class_id` (`class_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Table: submissions
-- One submission per student per assignment (enforced by UNIQUE)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `submissions` (
  `id`            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `assignment_id` INT UNSIGNED    NOT NULL,
  `user_id`       INT UNSIGNED    NOT NULL,
  `file_path`     VARCHAR(255)    NOT NULL,
  `grade`         VARCHAR(10)     DEFAULT NULL,     -- e.g. "A+", "85", "N/A"
  `feedback`      TEXT            DEFAULT NULL,
  `correction_path` VARCHAR(255)  DEFAULT NULL,     -- Path to teacher's corrected file
  `attempts`      INT UNSIGNED    NOT NULL DEFAULT 1,
  `submitted_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_one_sub_per_student` (`assignment_id`, `user_id`),
  CONSTRAINT `fk_submissions_assignment`
    FOREIGN KEY (`assignment_id`) REFERENCES `assignments`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_submissions_user`
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Seed Data – demo accounts (passwords: "password123")
-- Generated with: password_hash('password123', PASSWORD_BCRYPT)
-- ============================================================

INSERT INTO `users` (`full_name`, `email`, `password`, `role`) VALUES
('Sime Bryan', 'simebryan2003@gmail.com', '$2y$10$w6vL.Ido1xlWu4r7lRBmxut5R1c82dm6g7DRsSeh1Np8vFG.rtFJO', 'admin');

INSERT INTO `assignments` (`title`, `description`, `deadline`, `created_by`) VALUES
('Research Paper – Climate Change',
 'Write a 2000-word research paper covering the causes, effects, and mitigation strategies of climate change. Cite at least 5 peer-reviewed sources.',
 DATE_ADD(NOW(), INTERVAL 7 DAY), 1),

('Math Problem Set #4',
 'Complete all exercises in Chapter 7 (Differential Equations). Show all working steps clearly.',
 DATE_ADD(NOW(), INTERVAL 18 HOUR), 1),

('UX Case Study Presentation',
 'Prepare a 10-slide presentation analysing the UX of a popular mobile application of your choice.',
 DATE_ADD(NOW(), INTERVAL 14 DAY), 1);
