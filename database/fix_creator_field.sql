-- Step 1: Check if created_by column exists, if not, create it
ALTER TABLE quizzes ADD COLUMN IF NOT EXISTS created_by INT;

-- Step 2: Check if creator_id column exists, if it does, copy data to created_by
SET @creator_id_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                           WHERE TABLE_SCHEMA = DATABASE() 
                           AND TABLE_NAME = 'quizzes' 
                           AND COLUMN_NAME = 'creator_id');

-- If creator_id exists, copy non-null values to created_by
SET @sql = IF(@creator_id_exists > 0, 
              'UPDATE quizzes SET created_by = creator_id WHERE created_by IS NULL AND creator_id IS NOT NULL', 
              'SELECT "creator_id column does not exist" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 3: Set null created_by values to admin user ID 1 (assuming admin has ID 1)
UPDATE quizzes SET created_by = 1 WHERE created_by IS NULL;

-- Step 4: Add index to created_by for better performance
ALTER TABLE quizzes ADD INDEX IF NOT EXISTS idx_created_by (created_by);

-- Step 5: Add foreign key constraint if not exists
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