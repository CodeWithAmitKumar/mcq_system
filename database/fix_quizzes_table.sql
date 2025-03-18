-- Check if created_by column exists and add it if it doesn't
ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS created_by INT;

-- If we have creator_id but not values in created_by, sync them
UPDATE quizzes SET created_by = creator_id WHERE created_by IS NULL AND creator_id IS NOT NULL;

-- Add an index on created_by for better query performance
ALTER TABLE quizzes ADD INDEX IF NOT EXISTS idx_created_by (created_by);

-- Add a foreign key constraint if not exists
-- First check if there's already a constraint
SET @constraint_exists = (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE
        TABLE_SCHEMA = DATABASE() AND
        TABLE_NAME = 'quizzes' AND
        COLUMN_NAME = 'created_by' AND
        REFERENCED_TABLE_NAME = 'users'
);

-- Add the constraint if it doesn't exist
SET @sql = IF(
    @constraint_exists = 0,
    'ALTER TABLE quizzes ADD CONSTRAINT fk_quiz_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL',
    'SELECT "Foreign key constraint already exists" AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt; 