-- Estructura de la base de datos para el proyecto Nutrileche 2025

-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS c2780418_nutri;
USE c2780418_nutri;

-- Tabla de usuarios
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `departamento` varchar(50) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telefono` varchar(20) NOT NULL,
  `ciudad` varchar(50) NOT NULL,
  `institucion` varchar(100) NOT NULL,
  `seccion` varchar(10) DEFAULT NULL,
  `turno` varchar(20) DEFAULT NULL,
  `modalidad` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de evidencias
CREATE TABLE IF NOT EXISTS `evidences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `type` enum('proyecto','redacciones','fotos','videos') NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `evidences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de permisos por departamento
CREATE TABLE IF NOT EXISTS `department_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `departamento` varchar(50) NOT NULL,
  `can_create_account` tinyint(1) NOT NULL DEFAULT '0',
  `can_edit_submission` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `departamento` (`departamento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertar departamentos iniciales
INSERT INTO `department_permissions` (`departamento`, `can_create_account`, `can_edit_submission`) VALUES
('altoparaguay', 0, 0),
('boqueron', 0, 0),
('misiones', 1, 1),
('presidentehayes', 0, 0);

-- Índices adicionales para mejorar rendimiento
CREATE INDEX idx_evidences_type ON evidences(type);
CREATE INDEX idx_users_departamento ON users(departamento); 