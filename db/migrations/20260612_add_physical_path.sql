-- Fügt die Spalte physical_path für die optimierte, zufällige Dateispeicherung hinzu
ALTER TABLE `files` ADD COLUMN `physical_path` VARCHAR(255) DEFAULT NULL;
