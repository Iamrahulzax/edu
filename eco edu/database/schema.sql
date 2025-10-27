-- EcoEdu Database Schema
-- Create database
CREATE DATABASE IF NOT EXISTS ecoedu_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ecoedu_db;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role ENUM('student', 'teacher', 'admin') DEFAULT 'student',
    school_name VARCHAR(100),
    grade_level VARCHAR(20),
    bio TEXT,
    eco_points INT DEFAULT 0,
    total_badges INT DEFAULT 0,
    level_id INT DEFAULT 1,
    profile_image VARCHAR(255) DEFAULT 'default-avatar.png',
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Levels table
CREATE TABLE levels (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    min_points INT NOT NULL,
    max_points INT NOT NULL,
    badge_icon VARCHAR(100),
    color_code VARCHAR(7) DEFAULT '#28a745',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Categories table for organizing content
CREATE TABLE categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    color VARCHAR(7) DEFAULT '#28a745',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Learning content table
CREATE TABLE learning_content (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    content LONGTEXT,
    content_type ENUM('article', 'video', 'infographic', 'interactive') DEFAULT 'article',
    category_id INT,
    difficulty_level ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    estimated_time INT DEFAULT 5, -- in minutes
    video_url VARCHAR(500),
    image_url VARCHAR(500),
    points_reward INT DEFAULT 10,
    is_featured BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    views_count INT DEFAULT 0,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Quizzes table
CREATE TABLE quizzes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    category_id INT,
    difficulty_level ENUM('easy', 'medium', 'hard') DEFAULT 'easy',
    time_limit INT DEFAULT 300, -- in seconds
    total_questions INT DEFAULT 0,
    points_per_question INT DEFAULT 5,
    pass_percentage DECIMAL(5,2) DEFAULT 70.00,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Quiz questions table
CREATE TABLE quiz_questions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    quiz_id INT NOT NULL,
    question TEXT NOT NULL,
    question_type ENUM('multiple_choice', 'true_false', 'fill_blank') DEFAULT 'multiple_choice',
    option_a VARCHAR(500),
    option_b VARCHAR(500),
    option_c VARCHAR(500),
    option_d VARCHAR(500),
    correct_answer VARCHAR(500) NOT NULL,
    explanation TEXT,
    points INT DEFAULT 5,
    order_index INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

-- Quiz attempts table
CREATE TABLE quiz_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    quiz_id INT NOT NULL,
    score DECIMAL(5,2) DEFAULT 0,
    total_questions INT DEFAULT 0,
    correct_answers INT DEFAULT 0,
    time_taken INT, -- in seconds
    is_passed BOOLEAN DEFAULT FALSE,
    points_earned INT DEFAULT 0,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE
);

-- Challenges table
CREATE TABLE challenges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    challenge_type ENUM('individual', 'group', 'school') DEFAULT 'individual',
    category_id INT,
    difficulty_level ENUM('easy', 'medium', 'hard') DEFAULT 'easy',
    points_reward INT DEFAULT 50,
    verification_type ENUM('photo', 'video', 'text', 'qr_code') DEFAULT 'photo',
    verification_instructions TEXT,
    duration_days INT DEFAULT 7,
    max_participants INT DEFAULT 0, -- 0 means unlimited
    is_active BOOLEAN DEFAULT TRUE,
    start_date DATE,
    end_date DATE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Challenge participation table
CREATE TABLE challenge_participation (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    challenge_id INT NOT NULL,
    status ENUM('joined', 'in_progress', 'submitted', 'verified', 'rejected') DEFAULT 'joined',
    submission_text TEXT,
    submission_file VARCHAR(500),
    verification_notes TEXT,
    points_earned INT DEFAULT 0,
    verified_by INT,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    submitted_at TIMESTAMP NULL,
    verified_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_participation (user_id, challenge_id)
);

-- Badges table
CREATE TABLE badges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(100),
    badge_type ENUM('achievement', 'milestone', 'special') DEFAULT 'achievement',
    criteria_type ENUM('points', 'quizzes', 'challenges', 'streak', 'special') DEFAULT 'points',
    criteria_value INT DEFAULT 0,
    rarity ENUM('common', 'rare', 'epic', 'legendary') DEFAULT 'common',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User badges table
CREATE TABLE user_badges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    badge_id INT NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (badge_id) REFERENCES badges(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_badge (user_id, badge_id)
);

-- User progress tracking
CREATE TABLE user_progress (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    content_id INT,
    quiz_id INT,
    challenge_id INT,
    progress_type ENUM('content_view', 'quiz_complete', 'challenge_join', 'challenge_complete') NOT NULL,
    points_earned INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES learning_content(id) ON DELETE SET NULL,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE SET NULL,
    FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE SET NULL
);

-- Schools table
CREATE TABLE schools (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(200) NOT NULL,
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    country VARCHAR(100) DEFAULT 'India',
    contact_email VARCHAR(100),
    contact_phone VARCHAR(20),
    total_students INT DEFAULT 0,
    total_points INT DEFAULT 0,
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- School leaderboard view
CREATE VIEW school_leaderboard AS
SELECT 
    s.id,
    s.name,
    s.city,
    s.state,
    COUNT(u.id) as student_count,
    COALESCE(SUM(u.eco_points), 0) as total_points,
    COALESCE(AVG(u.eco_points), 0) as avg_points_per_student
FROM schools s
LEFT JOIN users u ON u.school_name = s.name AND u.role = 'student'
WHERE s.is_verified = TRUE
GROUP BY s.id, s.name, s.city, s.state
ORDER BY total_points DESC;

-- User leaderboard view
CREATE VIEW user_leaderboard AS
SELECT 
    u.id,
    u.username,
    u.first_name,
    u.last_name,
    u.school_name,
    u.eco_points,
    u.total_badges,
    l.name as level_name,
    l.badge_icon as level_icon,
    RANK() OVER (ORDER BY u.eco_points DESC) as global_rank,
    RANK() OVER (PARTITION BY u.school_name ORDER BY u.eco_points DESC) as school_rank
FROM users u
LEFT JOIN levels l ON u.level_id = l.id
WHERE u.role = 'student' AND u.is_active = TRUE
ORDER BY u.eco_points DESC;

-- Insert default data
INSERT INTO levels (name, min_points, max_points, badge_icon, color_code) VALUES
('Eco Newbie', 0, 99, 'fas fa-seedling', '#28a745'),
('Green Explorer', 100, 299, 'fas fa-leaf', '#20c997'),
('Nature Guardian', 300, 599, 'fas fa-tree', '#17a2b8'),
('Eco Warrior', 600, 999, 'fas fa-shield-alt', '#ffc107'),
('Planet Protector', 1000, 1999, 'fas fa-globe', '#fd7e14'),
('Environmental Champion', 2000, 4999, 'fas fa-trophy', '#dc3545'),
('Eco Legend', 5000, 999999, 'fas fa-crown', '#6f42c1');

INSERT INTO categories (name, description, icon, color) VALUES
('Climate Change', 'Learn about global warming and climate impacts', 'fas fa-thermometer-half', '#dc3545'),
('Renewable Energy', 'Explore sustainable energy sources', 'fas fa-solar-panel', '#ffc107'),
('Waste Management', 'Reduce, reuse, and recycle practices', 'fas fa-recycle', '#28a745'),
('Biodiversity', 'Protect wildlife and ecosystems', 'fas fa-paw', '#17a2b8'),
('Water Conservation', 'Save and protect water resources', 'fas fa-tint', '#007bff'),
('Sustainable Living', 'Eco-friendly lifestyle choices', 'fas fa-home', '#20c997');

INSERT INTO badges (name, description, icon, badge_type, criteria_type, criteria_value, rarity) VALUES
('First Steps', 'Complete your first quiz', 'fas fa-baby', 'achievement', 'quizzes', 1, 'common'),
('Quiz Master', 'Complete 10 quizzes', 'fas fa-brain', 'achievement', 'quizzes', 10, 'rare'),
('Point Collector', 'Earn 100 eco-points', 'fas fa-coins', 'milestone', 'points', 100, 'common'),
('Eco Enthusiast', 'Earn 500 eco-points', 'fas fa-star', 'milestone', 'points', 500, 'rare'),
('Challenge Accepted', 'Complete your first challenge', 'fas fa-flag', 'achievement', 'challenges', 1, 'common'),
('Challenge Champion', 'Complete 5 challenges', 'fas fa-medal', 'achievement', 'challenges', 5, 'epic'),
('Streak Starter', '7-day login streak', 'fas fa-fire', 'achievement', 'streak', 7, 'rare'),
('Eco Legend', 'Reach 1000 eco-points', 'fas fa-crown', 'milestone', 'points', 1000, 'legendary');

-- Sample learning content
INSERT INTO learning_content (title, description, content, content_type, category_id, difficulty_level, points_reward, is_featured) VALUES
('What is Climate Change?', 'Understanding the basics of global warming', 'Climate change refers to long-term shifts in global temperatures and weather patterns...', 'article', 1, 'beginner', 15, TRUE),
('Solar Energy Basics', 'Introduction to solar power technology', 'Solar energy harnesses the power of the sun to generate electricity...', 'article', 2, 'beginner', 15, TRUE),
('The 3 Rs: Reduce, Reuse, Recycle', 'Learn the fundamental principles of waste management', 'The three Rs form the foundation of sustainable waste management...', 'article', 3, 'beginner', 10, FALSE);

-- Sample quizzes
INSERT INTO quizzes (title, description, category_id, difficulty_level, total_questions, points_per_question) VALUES
('Climate Change Basics', 'Test your knowledge about climate change fundamentals', 1, 'easy', 5, 10),
('Renewable Energy Quiz', 'How much do you know about clean energy?', 2, 'medium', 8, 15);

-- Sample quiz questions for Climate Change quiz
INSERT INTO quiz_questions (quiz_id, question, option_a, option_b, option_c, option_d, correct_answer, explanation, points) VALUES
(1, 'What is the main cause of climate change?', 'Natural disasters', 'Greenhouse gas emissions', 'Solar flares', 'Ocean currents', 'Greenhouse gas emissions', 'Human activities that release greenhouse gases are the primary driver of recent climate change.', 10),
(1, 'Which gas is the most abundant greenhouse gas?', 'Carbon dioxide', 'Methane', 'Water vapor', 'Nitrous oxide', 'Water vapor', 'While CO2 gets more attention, water vapor is actually the most abundant greenhouse gas.', 10),
(1, 'What does CO2 stand for?', 'Carbon Oxygen', 'Carbon Dioxide', 'Calcium Oxide', 'Carbon Monoxide', 'Carbon Dioxide', 'CO2 is the chemical formula for carbon dioxide, a major greenhouse gas.', 10);

-- Sample challenges
INSERT INTO challenges (title, description, challenge_type, category_id, points_reward, verification_type, duration_days, start_date, end_date) VALUES
('Plant a Tree', 'Plant a tree in your neighborhood and share a photo', 'individual', 4, 100, 'photo', 30, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY)),
('Plastic-Free Week', 'Avoid single-use plastics for one week', 'individual', 3, 150, 'text', 7, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 7 DAY)),
('School Recycling Drive', 'Organize a recycling collection in your school', 'group', 3, 200, 'photo', 14, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 14 DAY));

-- Game Results table
CREATE TABLE game_results (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    game_type ENUM('quiz_battle', 'recycle_sorter', 'carbon_tracker', 'habit_streak', 'tree_grow', 'eco_hunt') NOT NULL,
    score DECIMAL(5,2) DEFAULT 0,
    points_earned INT DEFAULT 0,
    time_taken INT DEFAULT 0,
    game_data JSON,
    played_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- User Streaks table
CREATE TABLE user_streaks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    streak_type ENUM('daily_habit', 'quiz_streak', 'challenge_streak') NOT NULL,
    streak_count INT DEFAULT 0,
    last_activity_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_streak (user_id, streak_type)
);

-- User Content Progress table (for tracking learning content completion)
CREATE TABLE user_content_progress (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    content_id INT NOT NULL,
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (content_id) REFERENCES learning_content(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_content (user_id, content_id)
);

-- Create indexes for better performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_school ON users(school_name);
CREATE INDEX idx_users_points ON users(eco_points);
CREATE INDEX idx_quiz_attempts_user ON quiz_attempts(user_id);
CREATE INDEX idx_challenge_participation_user ON challenge_participation(user_id);
CREATE INDEX idx_user_progress_user ON user_progress(user_id);
CREATE INDEX idx_learning_content_category ON learning_content(category_id);
CREATE INDEX idx_quizzes_category ON quizzes(category_id);
CREATE INDEX idx_game_results_user ON game_results(user_id);
CREATE INDEX idx_game_results_type ON game_results(game_type);
CREATE INDEX idx_user_streaks_user ON user_streaks(user_id);
CREATE INDEX idx_user_content_progress_user ON user_content_progress(user_id);
