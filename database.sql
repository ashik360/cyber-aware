CREATE DATABASE IF NOT EXISTS cyber_aware
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE cyber_aware;

DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS user_badges;
DROP TABLE IF EXISTS badges;
DROP TABLE IF EXISTS user_missions;
DROP TABLE IF EXISTS missions;
DROP TABLE IF EXISTS quiz_answers;
DROP TABLE IF EXISTS quiz_attempts;
DROP TABLE IF EXISTS question_options;
DROP TABLE IF EXISTS questions;
DROP TABLE IF EXISTS study_materials;
DROP TABLE IF EXISTS articles;
DROP TABLE IF EXISTS lessons;
DROP TABLE IF EXISTS topics;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('user', 'admin') NOT NULL DEFAULT 'user',
  avatar VARCHAR(255) DEFAULT NULL,
  total_xp INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE topics (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(160) NOT NULL,
  slug VARCHAR(160) NOT NULL UNIQUE,
  summary TEXT,
  level ENUM('Beginner', 'Intermediate', 'Advanced') NOT NULL DEFAULT 'Beginner',
  icon VARCHAR(80) DEFAULT 'fa-solid fa-shield-halved',
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE lessons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  topic_id INT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  body LONGTEXT NOT NULL,
  estimated_minutes INT UNSIGNED NOT NULL DEFAULT 5,
  is_published TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE
);

CREATE TABLE articles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(220) NOT NULL,
  source VARCHAR(120) DEFAULT NULL,
  url VARCHAR(500) DEFAULT NULL,
  summary TEXT,
  published_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE study_materials (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  topic_id INT UNSIGNED DEFAULT NULL,
  title VARCHAR(220) NOT NULL,
  material_type ENUM('Article', 'PDF', 'External Link', 'Video') NOT NULL DEFAULT 'Article',
  file_path VARCHAR(255) DEFAULT NULL,
  external_url VARCHAR(500) DEFAULT NULL,
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE SET NULL
);

CREATE TABLE questions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  topic_id INT UNSIGNED DEFAULT NULL,
  question_text TEXT NOT NULL,
  difficulty ENUM('Easy', 'Medium', 'Hard') NOT NULL DEFAULT 'Easy',
  points INT UNSIGNED NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE SET NULL
);

CREATE TABLE question_options (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question_id INT UNSIGNED NOT NULL,
  option_text TEXT NOT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE
);

CREATE TABLE quiz_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  score INT UNSIGNED NOT NULL DEFAULT 0,
  total_questions INT UNSIGNED NOT NULL DEFAULT 0,
  correct_answers INT UNSIGNED NOT NULL DEFAULT 0,
  time_taken_seconds INT UNSIGNED DEFAULT NULL,
  started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME DEFAULT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE quiz_answers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attempt_id INT UNSIGNED NOT NULL,
  question_id INT UNSIGNED NOT NULL,
  selected_option_id INT UNSIGNED DEFAULT NULL,
  is_correct TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
  FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE,
  FOREIGN KEY (selected_option_id) REFERENCES question_options(id) ON DELETE SET NULL
);

CREATE TABLE missions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(80) NOT NULL UNIQUE,
  title VARCHAR(160) NOT NULL,
  description TEXT,
  xp_reward INT UNSIGNED NOT NULL DEFAULT 10,
  unlock_order INT UNSIGNED NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_missions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  mission_id INT UNSIGNED NOT NULL,
  status ENUM('locked', 'pending', 'completed') NOT NULL DEFAULT 'pending',
  score INT UNSIGNED NOT NULL DEFAULT 0,
  completed_at DATETIME DEFAULT NULL,
  UNIQUE KEY unique_user_mission (user_id, mission_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (mission_id) REFERENCES missions(id) ON DELETE CASCADE
);

CREATE TABLE badges (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  description TEXT,
  icon VARCHAR(80) DEFAULT 'fa-solid fa-award',
  required_xp INT UNSIGNED DEFAULT 0,
  required_mission_slug VARCHAR(80) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_badges (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  badge_id INT UNSIGNED NOT NULL,
  earned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_user_badge (user_id, badge_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE
);

CREATE TABLE activity_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED DEFAULT NULL,
  action_type VARCHAR(80) NOT NULL,
  action_text TEXT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

INSERT INTO topics (id, title, slug, summary, level, icon, sort_order) VALUES
(1, 'Phishing Awareness', 'phishing-awareness', 'Learn how to detect fake emails, suspicious links, and urgent scam messages.', 'Beginner', 'fa-solid fa-envelope-open-text', 1),
(2, 'Password Security', 'password-security', 'Learn how to create strong passwords and protect accounts.', 'Beginner', 'fa-solid fa-key', 2),
(3, 'Malware Safety', 'malware-safety', 'Learn how to identify unsafe downloads, fake warnings, and malicious websites.', 'Beginner', 'fa-solid fa-bug', 3),
(4, 'Social Engineering', 'social-engineering', 'Learn how attackers manipulate people through calls, messages, and pressure tactics.', 'Intermediate', 'fa-solid fa-user-secret', 4);

INSERT INTO lessons (topic_id, title, body, estimated_minutes, is_published) VALUES
(1, 'Phishing Basics', 'Phishing is a cyber attack where criminals use fake emails, websites, or messages to trick people into sharing sensitive information. Common warning signs include urgent language, unknown senders, spelling mistakes, suspicious links, and requests for passwords or OTP codes.', 5, 1),
(2, 'Password Hygiene', 'A strong password should be long, unique, and difficult to guess. Use uppercase letters, lowercase letters, numbers, and symbols. Never reuse the same password across multiple accounts.', 5, 1),
(3, 'Malware Basics', 'Malware is harmful software designed to damage devices, steal data, or gain unauthorized access. Avoid downloading unknown files and always check website safety indicators.', 5, 1),
(4, 'Social Engineering Basics', 'Social engineering attacks manipulate people instead of systems. Attackers may pretend to be IT staff, bank representatives, or trusted contacts to pressure users into unsafe actions.', 6, 1);

INSERT INTO missions (id, slug, title, description, xp_reward, unlock_order) VALUES
(1, 'phishing', 'Phishing Trap', 'Identify suspicious clues inside a fake email.', 10, 1),
(2, 'password', 'Password Forge', 'Create a password that meets all security rules.', 10, 2),
(3, 'malware', 'Malware Radar', 'Spot unsafe signs on a suspicious website.', 10, 3),
(4, 'social', 'Social Shield', 'Choose the safest response in a social engineering scenario.', 10, 4);

INSERT INTO badges (name, description, icon, required_xp, required_mission_slug) VALUES
('Phishing Defender', 'Completed the phishing awareness mission.', 'fa-solid fa-envelope-circle-check', 0, 'phishing'),
('Password Master', 'Completed the password security mission.', 'fa-solid fa-key', 0, 'password'),
('Malware Hunter', 'Completed the malware safety mission.', 'fa-solid fa-bug', 0, 'malware'),
('Social Shield', 'Completed the social engineering mission.', 'fa-solid fa-user-secret', 0, 'social'),
('Cyber Starter', 'Earned 20 XP.', 'fa-solid fa-seedling', 20, NULL),
('Cyber Guardian', 'Earned 40 XP.', 'fa-solid fa-shield-halved', 40, NULL);