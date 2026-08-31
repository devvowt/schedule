-- Banco separado usado pela suíte de testes.
CREATE DATABASE IF NOT EXISTS `schedule_service_test`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON `schedule_service_test`.* TO 'schedule_service'@'%';
FLUSH PRIVILEGES;
