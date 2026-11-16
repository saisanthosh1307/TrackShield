-- =====================================================================
-- TrackShield - Railway Emergency Management System
-- Database schema for MySQL 5.7+ / MariaDB 10.3+
-- =====================================================================

CREATE DATABASE IF NOT EXISTS trackshield_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE trackshield_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS emergency_updates;
DROP TABLE IF EXISTS emergency_assignments;
DROP TABLE IF EXISTS emergencies;
DROP TABLE IF EXISTS response_teams;
DROP TABLE IF EXISTS emergency_categories;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Table 1: users
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name   VARCHAR(100) NOT NULL,
  email       VARCHAR(150) NOT NULL,
  phone       VARCHAR(20)  NOT NULL,
  password    VARCHAR(255) NOT NULL,
  role        ENUM('passenger','response','admin') NOT NULL DEFAULT 'passenger',
  status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role (role),
  KEY idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 2: emergency_categories
-- ---------------------------------------------------------------------
CREATE TABLE emergency_categories (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  category_name  VARCHAR(100) NOT NULL,
  description    VARCHAR(255) DEFAULT NULL,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_category_name (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 3: emergencies
-- ---------------------------------------------------------------------
CREATE TABLE emergencies (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference_number  VARCHAR(30)  NOT NULL,
  user_id           INT UNSIGNED NOT NULL,
  category_id       INT UNSIGNED NOT NULL,
  title             VARCHAR(150) NOT NULL,
  description       TEXT NOT NULL,
  station_name      VARCHAR(120) NOT NULL,
  train_number      VARCHAR(20)  DEFAULT NULL,
  location          VARCHAR(255) NOT NULL,
  incident_datetime DATETIME     NOT NULL,
  priority          ENUM('Critical','High','Medium') NOT NULL DEFAULT 'Medium',
  status            ENUM('Submitted','Under Verification','Assigned','Response Team Dispatched',
                         'In Progress','Resolved','Rejected') NOT NULL DEFAULT 'Submitted',
  contact_number    VARCHAR(20)  NOT NULL,
  image             VARCHAR(255) DEFAULT NULL,
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reference_number (reference_number),
  KEY idx_emergencies_user (user_id),
  KEY idx_emergencies_category (category_id),
  KEY idx_emergencies_status (status),
  KEY idx_emergencies_priority (priority),
  KEY idx_emergencies_created (created_at),
  CONSTRAINT fk_emergencies_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_emergencies_category FOREIGN KEY (category_id)
    REFERENCES emergency_categories (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 4: response_teams
-- user_id links a team to the login account used by its members.
-- ---------------------------------------------------------------------
CREATE TABLE response_teams (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         INT UNSIGNED DEFAULT NULL,
  team_name       VARCHAR(100) NOT NULL,
  team_leader     VARCHAR(100) NOT NULL,
  email           VARCHAR(150) NOT NULL,
  phone           VARCHAR(20)  NOT NULL,
  specialization  VARCHAR(100) NOT NULL,
  availability    ENUM('Available','Busy','Off Duty') NOT NULL DEFAULT 'Available',
  created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_team_name (team_name),
  UNIQUE KEY uq_team_user (user_id),
  KEY idx_team_availability (availability),
  CONSTRAINT fk_teams_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 5: emergency_assignments
-- ---------------------------------------------------------------------
CREATE TABLE emergency_assignments (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  emergency_id       INT UNSIGNED NOT NULL,
  team_id            INT UNSIGNED NOT NULL,
  assigned_by        INT UNSIGNED NOT NULL,
  assigned_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  assignment_status  ENUM('Active','Completed','Reassigned') NOT NULL DEFAULT 'Active',
  completed_at       DATETIME DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_assign_emergency (emergency_id),
  KEY idx_assign_team (team_id),
  KEY idx_assign_status (assignment_status),
  CONSTRAINT fk_assign_emergency FOREIGN KEY (emergency_id)
    REFERENCES emergencies (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_assign_team FOREIGN KEY (team_id)
    REFERENCES response_teams (id) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_assign_by FOREIGN KEY (assigned_by)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 6: emergency_updates (status history)
-- ---------------------------------------------------------------------
CREATE TABLE emergency_updates (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  emergency_id     INT UNSIGNED NOT NULL,
  updated_by       INT UNSIGNED NOT NULL,
  previous_status  VARCHAR(40) DEFAULT NULL,
  new_status       VARCHAR(40) NOT NULL,
  remarks          TEXT DEFAULT NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_updates_emergency (emergency_id),
  KEY idx_updates_user (updated_by),
  CONSTRAINT fk_updates_emergency FOREIGN KEY (emergency_id)
    REFERENCES emergencies (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_updates_user FOREIGN KEY (updated_by)
    REFERENCES users (id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Table 7: notifications
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL,
  emergency_id  INT UNSIGNED DEFAULT NULL,
  message       VARCHAR(255) NOT NULL,
  is_read       TINYINT(1) NOT NULL DEFAULT 0,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_user_read (user_id, is_read),
  KEY idx_notif_emergency (emergency_id),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_notif_emergency FOREIGN KEY (emergency_id)
    REFERENCES emergencies (id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Seed data
-- =====================================================================
INSERT INTO emergency_categories (category_name, description) VALUES
('Railway Accident',       'Derailment, collision or any accident involving trains'),
('Fire Emergency',         'Fire or smoke on trains, platforms or station premises'),
('Medical Emergency',      'Passenger or staff requiring urgent medical attention'),
('Track Damage',           'Broken rails, obstructions or damaged track infrastructure'),
('Signal Failure',         'Malfunctioning or non-operational railway signals'),
('Train Breakdown',        'Train stalled due to mechanical or electrical failure'),
('Passenger Safety Issue', 'Harassment, overcrowding, theft or other safety concerns'),
('Suspicious Activity',    'Unattended baggage or suspicious behaviour'),
('Other Emergencies',      'Any other railway-related emergency');

-- Initial administrator account
--   Email:    admin@trackshield.local
--   Password: Admin@123   (bcrypt hash below - CHANGE IT after first login)
INSERT INTO users (full_name, email, phone, password, role, status) VALUES
('System Administrator', 'admin@trackshield.local', '1800111139',
 '$2y$10$uX/eOiqCpBm7OiS3hqSEUOfs0vCpJ9nAIhXWjJPRi8gQY52IoVE3q', 'admin', 'active');

-- Demo response team account
--   Email:    rescue@trackshield.local
--   Password: Team@123
INSERT INTO users (full_name, email, phone, password, role, status) VALUES
('Central Rescue Unit', 'rescue@trackshield.local', '9000000001',
 '$2y$10$dZbU0I61MLBJOwjcqCCj5.FWVRFv9QjMxZuNuauoAI9TCEJVbiBO6', 'response', 'active');

INSERT INTO response_teams (user_id, team_name, team_leader, email, phone, specialization, availability) VALUES
(2, 'Central Rescue Unit', 'R. Sharma', 'rescue@trackshield.local', '9000000001', 'Accident & Rescue', 'Available');
