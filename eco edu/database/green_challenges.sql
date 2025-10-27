-- Green Challenges Module Database Structure
-- Smart Environmental Learning Platform

-- Table for challenge categories
CREATE TABLE IF NOT EXISTS challenge_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'fas fa-leaf',
    color VARCHAR(20) DEFAULT '#28a745',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table for eco challenges
CREATE TABLE IF NOT EXISTS eco_challenges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id INT,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    instructions TEXT,
    ecopoints_reward INT DEFAULT 50,
    difficulty_level ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
    estimated_time VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES challenge_categories(id) ON DELETE SET NULL
);

-- Table for user challenge submissions
CREATE TABLE IF NOT EXISTS challenge_submissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    challenge_id INT NOT NULL,
    submission_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    photo_path VARCHAR(500),
    video_path VARCHAR(500),
    description TEXT,
    location VARCHAR(200),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_feedback TEXT,
    approved_by INT,
    approved_at TIMESTAMP NULL,
    ecopoints_awarded INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (challenge_id) REFERENCES eco_challenges(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Table for user challenge progress tracking
CREATE TABLE IF NOT EXISTS user_challenge_progress (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    challenge_id INT NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    status ENUM('started', 'submitted', 'completed', 'abandoned') DEFAULT 'started',
    attempts INT DEFAULT 1,
    UNIQUE KEY unique_user_challenge (user_id, challenge_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (challenge_id) REFERENCES eco_challenges(id) ON DELETE CASCADE
);

-- Table for rewards and achievements
CREATE TABLE IF NOT EXISTS eco_rewards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    type ENUM('badge', 'certificate', 'title') DEFAULT 'badge',
    icon VARCHAR(100),
    points_required INT NOT NULL,
    color VARCHAR(20) DEFAULT '#ffc107',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Table for user earned rewards
CREATE TABLE IF NOT EXISTS user_rewards (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    reward_id INT NOT NULL,
    earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    challenge_submission_id INT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reward_id) REFERENCES eco_rewards(id) ON DELETE CASCADE,
    FOREIGN KEY (challenge_submission_id) REFERENCES challenge_submissions(id) ON DELETE SET NULL
);

-- Insert default challenge categories
INSERT INTO challenge_categories (name, description, icon, color) VALUES
('Tree Plantation', 'Plant trees and contribute to reforestation', 'fas fa-tree', '#28a745'),
('Plastic Reduction', 'Reduce plastic usage and promote alternatives', 'fas fa-recycle', '#17a2b8'),
('Clean-Up Drive', 'Participate in environmental cleanup activities', 'fas fa-broom', '#ffc107'),
('Recycling Action', 'Collect and recycle waste materials', 'fas fa-sync-alt', '#6f42c1'),
('Water Conservation', 'Promote water saving and awareness', 'fas fa-tint', '#007bff'),
('Energy Saving', 'Implement energy conservation practices', 'fas fa-bolt', '#fd7e14'),
('Wildlife Protection', 'Support local wildlife and biodiversity', 'fas fa-paw', '#20c997'),
('Sustainable Transport', 'Use eco-friendly transportation methods', 'fas fa-bicycle', '#6c757d');

-- Insert sample eco challenges
INSERT INTO eco_challenges (category_id, title, description, instructions, ecopoints_reward, difficulty_level, estimated_time) VALUES
(1, 'Plant a Sapling', 'Plant a tree sapling in your area and help combat climate change', 'Choose a suitable location, plant a native tree species, water it properly, and take a photo showing the planted sapling with clear surroundings', 100, 'medium', '2-3 hours'),
(1, 'Create a Mini Forest', 'Plant 5 or more saplings to create a small forest area', 'Select an appropriate area, plant at least 5 native tree saplings with proper spacing, ensure adequate watering, and document the entire process', 250, 'hard', '1 day'),
(2, 'Plastic-Free Week', 'Go plastic-free for one week and document alternatives used', 'Replace all single-use plastics with sustainable alternatives, keep a daily log, and photograph your plastic-free lifestyle choices', 150, 'medium', '1 week'),
(2, 'DIY Eco-Bag Creation', 'Create reusable bags from old clothes or materials', 'Design and create at least 3 reusable bags using recycled materials, demonstrate their use, and share the creation process', 75, 'easy', '3-4 hours'),
(3, 'Neighborhood Cleanup', 'Organize or participate in a local area cleanup drive', 'Clean a public area like park, street, or beach, collect and properly dispose of waste, involve others if possible, and document before/after photos', 120, 'medium', '4-6 hours'),
(3, 'School Campus Beautification', 'Clean and beautify your school or college campus', 'Organize a campus cleanup, plant flowers or decorative plants, create awareness posters, and engage fellow students', 100, 'medium', 'Half day'),
(4, 'Waste Segregation Project', 'Set up a waste segregation system at home or school', 'Create separate bins for different types of waste, educate family/friends about proper segregation, and maintain the system for a week', 80, 'easy', '1 week'),
(4, 'E-Waste Collection Drive', 'Collect electronic waste from your community for proper recycling', 'Gather old electronics, batteries, and gadgets from neighbors, find proper e-waste recycling center, and ensure safe disposal', 130, 'medium', '1 day'),
(5, 'Rainwater Harvesting Setup', 'Install a simple rainwater collection system', 'Set up a basic rainwater harvesting system at home, demonstrate its working, and calculate water saved', 180, 'hard', '1 day'),
(5, 'Water Conservation Awareness', 'Create and distribute water saving tips in your community', 'Design informative posters about water conservation, distribute in your locality, and educate at least 10 people about water saving', 90, 'easy', '2-3 days');

-- Insert default rewards
INSERT INTO eco_rewards (name, description, type, icon, points_required, color) VALUES
('Eco Enthusiast', 'Awarded for earning your first 500 EcoPoints', 'badge', 'fas fa-seedling', 500, '#28a745'),
('Sustainability Champion', 'Recognized for outstanding environmental commitment', 'certificate', 'fas fa-award', 1000, '#ffc107'),
('Green Ambassador', 'Elite status for environmental leadership', 'title', 'fas fa-crown', 1500, '#6f42c1'),
('Tree Planter', 'Specialist in tree plantation activities', 'badge', 'fas fa-tree', 300, '#28a745'),
('Plastic Warrior', 'Fighter against plastic pollution', 'badge', 'fas fa-shield-alt', 400, '#17a2b8'),
('Clean-Up Hero', 'Champion of environmental cleanup', 'badge', 'fas fa-broom', 350, '#ffc107'),
('Recycling Master', 'Expert in waste management and recycling', 'badge', 'fas fa-recycle', 450, '#6f42c1'),
('Water Guardian', 'Protector of water resources', 'badge', 'fas fa-tint', 250, '#007bff');
