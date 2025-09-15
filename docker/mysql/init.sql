-- MySQL initialization script for Bagisto
SET GLOBAL sql_mode = 'ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION';

-- Create testing database for PHPUnit tests
CREATE DATABASE IF NOT EXISTS `testing` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Grant permissions for testing database
GRANT ALL PRIVILEGES ON `testing`.* TO '${MYSQL_USER}'@'%';

-- Ensure the main database exists with proper charset
CREATE DATABASE IF NOT EXISTS `${MYSQL_DATABASE}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Flush privileges
FLUSH PRIVILEGES;

-- Set timezone
SET GLOBAL time_zone = '+00:00';

-- Optimize MySQL settings for Bagisto
SET GLOBAL innodb_buffer_pool_size = 128M;
SET GLOBAL query_cache_size = 32M;
SET GLOBAL max_connections = 200;
