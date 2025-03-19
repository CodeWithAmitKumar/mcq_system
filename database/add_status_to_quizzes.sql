ALTER TABLE `quizzes` 
ADD COLUMN `status` enum('draft','published') NOT NULL DEFAULT 'draft' AFTER `time_limit`; 