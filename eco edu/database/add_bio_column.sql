-- Add bio column to users table
-- Copy and paste ONLY the ALTER TABLE command below into phpMyAdmin SQL tab

ALTER TABLE users ADD COLUMN bio TEXT AFTER grade_level;
