-- Create admin user for EcoEdu platform
-- Run this SQL in phpMyAdmin to create an admin account

USE ecoedu_db;

-- Create admin user (password: admin123)
-- The password hash is for 'admin123' - change this after first login!
INSERT IGNORE INTO users (
    username, 
    email, 
    password, 
    first_name, 
    last_name, 
    role, 
    school_name, 
    grade_level, 
    eco_points, 
    level_id, 
    is_active, 
    email_verified,
    created_at
) VALUES (
    'admin',
    'admin@ecoedu.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Admin',
    'User',
    'admin',
    'EcoEdu Platform',
    'Administrator',
    1000,
    7,
    1,
    1,
    NOW()
);

-- Verify admin user was created
SELECT id, username, email, first_name, last_name, role 
FROM users 
WHERE role = 'admin';

-- If you want to make an existing user an admin, use this query instead:
-- UPDATE users SET role = 'admin' WHERE email = 'your-email@example.com';
