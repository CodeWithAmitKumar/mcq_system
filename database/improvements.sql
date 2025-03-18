-- Add indexes for better performance
ALTER TABLE quiz_attempts ADD INDEX idx_user_quiz (user_id, quiz_id);
ALTER TABLE user_answers ADD INDEX idx_attempt_question (attempt_id, question_id);
ALTER TABLE questions ADD INDEX idx_quiz_id (quiz_id);
ALTER TABLE quiz_results ADD INDEX idx_user_quiz (user_id, quiz_id);

-- Add foreign key constraints
ALTER TABLE quiz_attempts 
    ADD CONSTRAINT fk_quiz_attempts_user 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_quiz_attempts_quiz 
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE;

ALTER TABLE user_answers 
    ADD CONSTRAINT fk_user_answers_attempt 
    FOREIGN KEY (attempt_id) REFERENCES quiz_attempts(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_user_answers_question 
    FOREIGN KEY (question_id) REFERENCES questions(id) ON DELETE CASCADE;

ALTER TABLE questions 
    ADD CONSTRAINT fk_questions_quiz 
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE;

ALTER TABLE quiz_results 
    ADD CONSTRAINT fk_quiz_results_user 
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_quiz_results_quiz 
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE;

-- Add soft delete columns
ALTER TABLE quizzes ADD COLUMN deleted_at DATETIME DEFAULT NULL;
ALTER TABLE questions ADD COLUMN deleted_at DATETIME DEFAULT NULL;
ALTER TABLE quiz_attempts ADD COLUMN deleted_at DATETIME DEFAULT NULL;
ALTER TABLE user_answers ADD COLUMN deleted_at DATETIME DEFAULT NULL;
ALTER TABLE quiz_results ADD COLUMN deleted_at DATETIME DEFAULT NULL;

-- Add audit columns for quizzes
ALTER TABLE quizzes ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE quizzes ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add audit columns for questions
ALTER TABLE questions ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE questions ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add audit columns for quiz_attempts
ALTER TABLE quiz_attempts ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE quiz_attempts ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add audit columns for user_answers
ALTER TABLE user_answers ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE user_answers ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add audit columns for quiz_results
ALTER TABLE quiz_results ADD COLUMN created_at DATETIME DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE quiz_results ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Add additional useful columns
ALTER TABLE quiz_attempts 
    ADD COLUMN time_taken INT DEFAULT NULL COMMENT 'Time taken in seconds',
    ADD COLUMN status ENUM('in_progress', 'completed', 'abandoned') DEFAULT 'in_progress';

ALTER TABLE quiz_results 
    ADD COLUMN percentage DECIMAL(5,2) DEFAULT NULL,
    ADD COLUMN status ENUM('passed', 'failed') DEFAULT NULL;

-- Create audit log table
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add index for audit logs
ALTER TABLE audit_logs ADD INDEX idx_user_action (user_id, action);
ALTER TABLE audit_logs ADD INDEX idx_entity (entity_type, entity_id);

-- Create quiz settings table
CREATE TABLE IF NOT EXISTS quiz_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    setting_key VARCHAR(50) NOT NULL,
    setting_value TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    UNIQUE KEY unique_quiz_setting (quiz_id, setting_key)
);

-- Add indexes for quiz settings
ALTER TABLE quiz_settings ADD INDEX idx_quiz_id (quiz_id);
ALTER TABLE quiz_settings ADD INDEX idx_setting_key (setting_key); 