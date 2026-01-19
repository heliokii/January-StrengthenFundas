CREATE TABLE IF NOT EXISTS `StudySession` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `input_text` TEXT NOT NULL,
    `action_type` VARCHAR(50) NOT NULL,
    `ai_response` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS `Flashcard` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `study_session_id` INT,
    `question` TEXT NOT NULL,
    `answer` TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`study_session_id`) REFERENCES `StudySession`(`id`) ON DELETE CASCADE
);
