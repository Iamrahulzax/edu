-- Comprehensive error fixes for EcoEdu platform
-- Run this SQL in phpMyAdmin to fix database-related errors

USE ecoedu_db;

-- Ensure all required tables exist
SOURCE missing_tables.sql;

-- Fix any data integrity issues
UPDATE users SET eco_points = 0 WHERE eco_points IS NULL;
UPDATE users SET total_badges = 0 WHERE total_badges IS NULL;
UPDATE users SET level_id = 1 WHERE level_id IS NULL OR level_id = 0;
UPDATE users SET is_active = 1 WHERE is_active IS NULL;
UPDATE users SET profile_image = 'default-avatar.png' WHERE profile_image IS NULL OR profile_image = '';

-- Ensure all users have a username (use email prefix if missing)
UPDATE users 
SET username = SUBSTRING_INDEX(email, '@', 1) 
WHERE username IS NULL OR username = '';

-- Fix any orphaned records
DELETE FROM user_badges WHERE user_id NOT IN (SELECT id FROM users);
DELETE FROM quiz_attempts WHERE user_id NOT IN (SELECT id FROM users);
DELETE FROM challenge_participation WHERE user_id NOT IN (SELECT id FROM users);

-- Update quiz totals
UPDATE quizzes q 
SET total_questions = (
    SELECT COUNT(*) 
    FROM quiz_questions qq 
    WHERE qq.quiz_id = q.id
) 
WHERE q.total_questions = 0 OR q.total_questions IS NULL;

-- Ensure all levels exist
INSERT IGNORE INTO levels (id, name, min_points, max_points, badge_icon, color_code) VALUES
(1, 'Eco Newbie', 0, 99, 'fas fa-seedling', '#28a745'),
(2, 'Green Explorer', 100, 299, 'fas fa-leaf', '#20c997'),
(3, 'Nature Guardian', 300, 599, 'fas fa-tree', '#17a2b8'),
(4, 'Eco Warrior', 600, 999, 'fas fa-shield-alt', '#ffc107'),
(5, 'Planet Protector', 1000, 1999, 'fas fa-globe', '#fd7e14'),
(6, 'Environmental Champion', 2000, 4999, 'fas fa-trophy', '#dc3545'),
(7, 'Eco Legend', 5000, 999999, 'fas fa-crown', '#6f42c1');

-- Ensure all categories exist
INSERT IGNORE INTO categories (id, name, description, icon, color) VALUES
(1, 'Climate Change', 'Learn about global warming and climate impacts', 'fas fa-thermometer-half', '#dc3545'),
(2, 'Renewable Energy', 'Explore sustainable energy sources', 'fas fa-solar-panel', '#ffc107'),
(3, 'Waste Management', 'Reduce, reuse, and recycle practices', 'fas fa-recycle', '#28a745'),
(4, 'Biodiversity', 'Protect wildlife and ecosystems', 'fas fa-paw', '#17a2b8'),
(5, 'Water Conservation', 'Save and protect water resources', 'fas fa-tint', '#007bff'),
(6, 'Sustainable Living', 'Eco-friendly lifestyle choices', 'fas fa-home', '#20c997');

-- Fix any missing foreign key references
UPDATE users SET level_id = 1 WHERE level_id NOT IN (SELECT id FROM levels);
UPDATE learning_content SET category_id = 1 WHERE category_id NOT IN (SELECT id FROM categories);
UPDATE quizzes SET category_id = 1 WHERE category_id NOT IN (SELECT id FROM categories);
UPDATE challenges SET category_id = 1 WHERE category_id NOT IN (SELECT id FROM categories);

-- Create admin user if not exists
INSERT IGNORE INTO users (username, email, password, first_name, last_name, role, is_active, created_at) 
VALUES ('admin', 'admin@ecoedu.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'User', 'admin', 1, NOW());

-- Update user levels based on current points
UPDATE users u 
SET level_id = (
    SELECT l.id 
    FROM levels l 
    WHERE u.eco_points >= l.min_points 
    ORDER BY l.min_points DESC 
    LIMIT 1
) 
WHERE u.is_active = 1;

-- Clean up any invalid data
DELETE FROM quiz_questions WHERE quiz_id NOT IN (SELECT id FROM quizzes);
DELETE FROM quiz_attempts WHERE quiz_id NOT IN (SELECT id FROM quizzes);
DELETE FROM challenge_participation WHERE challenge_id NOT IN (SELECT id FROM challenges);

-- Ensure proper data types and constraints
ALTER TABLE users MODIFY COLUMN eco_points INT DEFAULT 0 NOT NULL;
ALTER TABLE users MODIFY COLUMN total_badges INT DEFAULT 0 NOT NULL;
ALTER TABLE users MODIFY COLUMN level_id INT DEFAULT 1 NOT NULL;
ALTER TABLE users MODIFY COLUMN is_active BOOLEAN DEFAULT TRUE NOT NULL;

-- Add any missing indexes
CREATE INDEX IF NOT EXISTS idx_users_active ON users(is_active);
CREATE INDEX IF NOT EXISTS idx_users_role ON users(role);
CREATE INDEX IF NOT EXISTS idx_quiz_questions_quiz ON quiz_questions(quiz_id);
CREATE INDEX IF NOT EXISTS idx_challenge_participation_challenge ON challenge_participation(challenge_id);

-- Update statistics
UPDATE users u SET total_badges = (
    SELECT COUNT(*) 
    FROM user_badges ub 
    WHERE ub.user_id = u.id
) WHERE u.is_active = 1;
