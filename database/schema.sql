-- ============================================================
-- Book&Go Lite - Barbería  |  Esquema + datos de prueba
-- Importar:  mysql -u root < database/schema.sql
-- (o desde phpMyAdmin > Importar)
-- El usuario ADMIN NO está aquí: se crea con  php database/create_admin.php
-- ============================================================

SET NAMES utf8mb4;   -- evita acentos rotos al importar desde la consola de Windows

DROP DATABASE IF EXISTS bookgo_barberia;
CREATE DATABASE bookgo_barberia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE bookgo_barberia;

-- 1) ROLES ---------------------------------------------------
CREATE TABLE roles (
  id     TINYINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  nombre VARCHAR(20) NOT NULL UNIQUE          -- ADMIN | CLIENTE
) ENGINE=InnoDB;

-- 2) USUARIOS ------------------------------------------------
CREATE TABLE usuarios (
  id               INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  rol_id           TINYINT UNSIGNED NOT NULL,
  nombre           VARCHAR(60)  NOT NULL,
  apellido         VARCHAR(60)  NOT NULL,
  correo           VARCHAR(120) NOT NULL UNIQUE,
  password_hash    VARCHAR(255) NOT NULL,
  estado           ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  intentos_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0,   -- control de fuerza bruta
  bloqueado_hasta  DATETIME NULL,
  fecha_registro   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles(id)
) ENGINE=InnoDB;

-- 3) CATEGORIAS ----------------------------------------------
CREATE TABLE categorias (
  id          INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  nombre      VARCHAR(60)  NOT NULL UNIQUE,
  descripcion VARCHAR(255) NULL,
  estado      ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO'
) ENGINE=InnoDB;

-- 4) SERVICIOS -----------------------------------------------
CREATE TABLE servicios (
  id           INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  categoria_id INT UNSIGNED NOT NULL,
  nombre       VARCHAR(100) NOT NULL,
  descripcion  TEXT NULL,
  precio       DECIMAL(8,2) NOT NULL,
  duracion_min SMALLINT UNSIGNED NOT NULL,
  estado       ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  CONSTRAINT fk_servicios_categoria FOREIGN KEY (categoria_id) REFERENCES categorias(id),
  CONSTRAINT ck_servicios_precio   CHECK (precio >= 0),
  CONSTRAINT ck_servicios_duracion CHECK (duracion_min > 0)
) ENGINE=InnoDB;

-- 5) HORARIOS ------------------------------------------------
CREATE TABLE horarios (
  id                INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  servicio_id       INT UNSIGNED NOT NULL,
  fecha             DATE NOT NULL,
  hora_inicio       TIME NOT NULL,
  hora_fin          TIME NOT NULL,
  cupos_totales     SMALLINT UNSIGNED NOT NULL,
  cupos_disponibles SMALLINT UNSIGNED NOT NULL,   -- UNSIGNED: nunca negativo
  estado            ENUM('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  CONSTRAINT fk_horarios_servicio FOREIGN KEY (servicio_id) REFERENCES servicios(id),
  CONSTRAINT ck_horarios_horas  CHECK (hora_fin > hora_inicio),
  CONSTRAINT ck_horarios_cupos  CHECK (cupos_totales > 0 AND cupos_disponibles <= cupos_totales),
  -- clave única extra: permite que reservas apunte a (horario, servicio) juntos
  UNIQUE KEY uq_horario_servicio (id, servicio_id)
) ENGINE=InnoDB;

-- 6) RESERVAS ------------------------------------------------
CREATE TABLE reservas (
  id                INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  cliente_id        INT UNSIGNED NOT NULL,
  servicio_id       INT UNSIGNED NOT NULL,
  horario_id        INT UNSIGNED NOT NULL,
  estado            ENUM('PENDIENTE','CONFIRMADA','REALIZADA','CANCELADA') NOT NULL DEFAULT 'PENDIENTE',
  fecha_registro    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  -- Solo vale (horario_id) mientras la reserva esté activa; NULL si no.
  -- Con el UNIQUE de abajo, un cliente no puede tener 2 reservas activas en el mismo horario.
  horario_activo    INT UNSIGNED AS (IF(estado IN ('PENDIENTE','CONFIRMADA'), horario_id, NULL)) STORED,
  CONSTRAINT fk_reservas_cliente FOREIGN KEY (cliente_id) REFERENCES usuarios(id),
  -- FK compuesta: el horario DEBE pertenecer al servicio reservado
  CONSTRAINT fk_reservas_horario_servicio FOREIGN KEY (horario_id, servicio_id)
      REFERENCES horarios(id, servicio_id),
  UNIQUE KEY uq_cliente_horario_activo (cliente_id, horario_activo)
) ENGINE=InnoDB;

-- 7) RESEÑAS -------------------------------------------------
CREATE TABLE resenas (
  id           INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  reserva_id   INT UNSIGNED NOT NULL UNIQUE,      -- una reseña por reserva
  cliente_id   INT UNSIGNED NOT NULL,
  calificacion TINYINT UNSIGNED NOT NULL,
  comentario   VARCHAR(500) NOT NULL,
  fecha        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_resenas_reserva FOREIGN KEY (reserva_id) REFERENCES reservas(id),
  CONSTRAINT fk_resenas_cliente FOREIGN KEY (cliente_id) REFERENCES usuarios(id),
  CONSTRAINT ck_resenas_calif CHECK (calificacion BETWEEN 1 AND 5)
) ENGINE=InnoDB;

-- ============================================================
-- DATOS DE PRUEBA  (clientes con contraseña:  Cliente123!)
-- ============================================================
INSERT INTO roles (id, nombre) VALUES (1,'ADMIN'), (2,'CLIENTE');

INSERT INTO usuarios (id, rol_id, nombre, apellido, correo, password_hash) VALUES
(1, 2, 'Carlos', 'Mendoza', 'carlos@ejemplo.com', '$2y$10$OeYxIWqNHhgwSJH5mCIPG.ddyCwzgAnarROVsTNWOfsy7ct4z3Rdm'),
(2, 2, 'Luis',   'Pérez',   'luis@ejemplo.com',   '$2y$10$OeYxIWqNHhgwSJH5mCIPG.ddyCwzgAnarROVsTNWOfsy7ct4z3Rdm'),
(3, 2, 'Andrés', 'Castillo','andres@ejemplo.com', '$2y$10$OeYxIWqNHhgwSJH5mCIPG.ddyCwzgAnarROVsTNWOfsy7ct4z3Rdm');

INSERT INTO categorias (id, nombre, descripcion) VALUES
(1, 'Cortes',        'Cortes clásicos y modernos para todas las edades'),
(2, 'Barba',         'Perfilado, arreglo y afeitado de barba'),
(3, 'Tratamientos',  'Cuidado capilar y de la piel');

INSERT INTO servicios (id, categoria_id, nombre, descripcion, precio, duracion_min) VALUES
(1, 1, 'Corte clásico',            'Corte a tijera y máquina con acabado a navaja.',        8.00, 30),
(2, 1, 'Corte degradado (fade)',   'Degradado moderno con transición suave y peinado.',    10.00, 45),
(3, 1, 'Corte infantil',           'Corte para niños de hasta 12 años.',                    6.00, 30),
(4, 2, 'Perfilado de barba',       'Diseño y recorte de barba con aceites.',                6.00, 30),
(5, 2, 'Afeitado con toalla caliente', 'Afeitado tradicional con navaja y toalla caliente.', 9.00, 40),
(6, 3, 'Tratamiento capilar',      'Lavado, masaje y mascarilla hidratante.',              12.00, 60);

-- Horarios: relativos a la fecha de importación (2 pasados + futuros)
INSERT INTO horarios (id, servicio_id, fecha, hora_inicio, hora_fin, cupos_totales, cupos_disponibles) VALUES
(1, 1, CURDATE() - INTERVAL 3 DAY, '09:00','09:30', 3, 1),
(2, 2, CURDATE() - INTERVAL 2 DAY, '10:00','10:45', 2, 1),
(3, 1, CURDATE() + INTERVAL 1 DAY, '09:00','09:30', 3, 1),
(4, 1, CURDATE() + INTERVAL 1 DAY, '10:00','10:30', 2, 2),
(5, 2, CURDATE() + INTERVAL 2 DAY, '11:00','11:45', 2, 2),
(6, 3, CURDATE() + INTERVAL 2 DAY, '14:00','14:30', 1, 0),  -- agotado
(7, 4, CURDATE() + INTERVAL 3 DAY, '15:00','15:30', 4, 4),
(8, 5, CURDATE() + INTERVAL 3 DAY, '16:00','16:40', 3, 3),
(9, 6, CURDATE() + INTERVAL 4 DAY, '09:00','10:00', 2, 2);

-- Reservas (cupos_disponibles arriba ya refleja estas reservas)
INSERT INTO reservas (id, cliente_id, servicio_id, horario_id, estado) VALUES
(1, 1, 1, 1, 'REALIZADA'),
(2, 2, 1, 1, 'REALIZADA'),
(3, 1, 2, 2, 'REALIZADA'),
(4, 1, 1, 3, 'PENDIENTE'),
(5, 2, 1, 3, 'CONFIRMADA'),
(6, 3, 2, 5, 'CANCELADA'),
(7, 2, 3, 6, 'PENDIENTE');

INSERT INTO resenas (reserva_id, cliente_id, calificacion, comentario) VALUES
(1, 1, 5, 'Excelente corte, muy puntuales y buen ambiente.'),
(2, 2, 4, 'Buen servicio, solo tuve que esperar un poco.');
