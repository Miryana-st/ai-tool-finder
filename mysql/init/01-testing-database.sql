-- A separate database for the integration tests (backend/phpunit.integration.xml),
-- so running them never touches the development data in `ai_finder`.
-- MySQL runs this file only when it creates a fresh data volume; on an existing
-- volume run it once by hand (see README, "Integration tests").
CREATE DATABASE IF NOT EXISTS `ai_finder_testing`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON `ai_finder_testing`.* TO 'ai_finder_user'@'%';
