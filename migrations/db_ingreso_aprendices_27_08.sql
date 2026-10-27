-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 27-10-2026 a las 20:22:33
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `db_ingreso_aprendices`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asistencia`
--

CREATE TABLE `asistencia` (
  `id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora_entrada` time DEFAULT NULL,
  `hora_salida` time DEFAULT NULL,
  `Usuario_id` int(11) NOT NULL,
  `Sesion_id` int(11) NOT NULL,
  `registrado_por` int(11) NOT NULL,
  `estado` enum('Activo','Completado') NOT NULL DEFAULT 'Activo',
  `codigo_llavero` varchar(50) DEFAULT NULL COMMENT 'Snapshot del llavero al momento del registro',
  `minutos_retardo` int(11) NOT NULL DEFAULT 0,
  `minutos_anticipacion` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `asistencia`
--

INSERT INTO `asistencia` (`id`, `fecha`, `hora_entrada`, `hora_salida`, `Usuario_id`, `Sesion_id`, `registrado_por`, `estado`, `codigo_llavero`, `minutos_retardo`, `minutos_anticipacion`) VALUES
(1, '2026-10-07', '09:24:52', '09:26:14', 4, 1, 1, 'Completado', '0002271972', 624, 0),
(2, '2026-10-07', '09:34:20', '09:35:21', 6, 1, 7, 'Completado', '0002266653', 419, 0),
(3, '2026-10-07', '09:34:47', '09:35:08', 7, 2, 7, 'Completado', '0009433667', 634, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `competencia`
--

CREATE TABLE `competencia` (
  `id` int(11) NOT NULL,
  `Programa_id` int(11) NOT NULL,
  `Instructor_id` int(11) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `competencia`
--

INSERT INTO `competencia` (`id`, `Programa_id`, `Instructor_id`, `nombre`, `descripcion`) VALUES
(1, 1, NULL, 'Formación Técnica', 'Competencia general por defecto');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estado_excusa`
--

CREATE TABLE `estado_excusa` (
  `id` int(11) NOT NULL,
  `fecha` date DEFAULT NULL,
  `respuesta` text DEFAULT NULL,
  `estado` enum('Aprobada','Rechazada') DEFAULT NULL,
  `Excusa_id` int(11) NOT NULL,
  `Instructor_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `excusa`
--

CREATE TABLE `excusa` (
  `id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `motivo` text NOT NULL,
  `evidencia` varchar(255) DEFAULT NULL,
  `Usuario_id` int(11) NOT NULL,
  `Asistencia_id` int(11) DEFAULT NULL,
  `Inasistencia_id` int(11) DEFAULT NULL
) ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ficha`
--

CREATE TABLE `ficha` (
  `id` int(11) NOT NULL,
  `codigo` int(7) NOT NULL,
  `Programa_id` int(11) NOT NULL,
  `jornada_id` int(11) DEFAULT NULL,
  `instructor_id` int(11) NOT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `ficha`
--

INSERT INTO `ficha` (`id`, `codigo`, `Programa_id`, `jornada_id`, `instructor_id`, `estado`) VALUES
(1, 3234082, 1, 1, 2, 'Activo'),
(2, 3234982, 1, 1, 4, 'Activo'),
(3, 288878, 2, 1, 7, 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inasistencia`
--

CREATE TABLE `inasistencia` (
  `id` int(11) NOT NULL,
  `Usuario_id` int(11) NOT NULL,
  `Ficha_id` int(11) NOT NULL,
  `Sesion_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `generado_por` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `instructor_competencia`
--

CREATE TABLE `instructor_competencia` (
  `id` int(11) NOT NULL,
  `Instructor_id` int(11) NOT NULL,
  `Competencia_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `jornada`
--

CREATE TABLE `jornada` (
  `id` int(11) NOT NULL,
  `nombre` varchar(45) NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `jornada`
--

INSERT INTO `jornada` (`id`, `nombre`, `hora_inicio`, `hora_fin`) VALUES
(1, 'Mañana', '06:00:00', '12:00:00'),
(2, 'Tarde', '12:00:00', '18:00:00'),
(3, 'Noche', '18:00:00', '21:00:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `programa`
--

CREATE TABLE `programa` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `programa`
--

INSERT INTO `programa` (`id`, `nombre`, `descripcion`) VALUES
(1, 'Adso', NULL),
(2, 'cocina', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol`
--

CREATE TABLE `rol` (
  `id` int(11) NOT NULL,
  `nombre` varchar(45) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `rol`
--

INSERT INTO `rol` (`id`, `nombre`, `descripcion`) VALUES
(1, 'Administrador', 'Acceso total al sistema'),
(2, 'Instructor', 'Gestiona fichas y aprendices'),
(3, 'Aprendiz', 'Registro de ingreso/salida');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sesion`
--

CREATE TABLE `sesion` (
  `id` int(11) NOT NULL,
  `Ficha_id` int(11) NOT NULL,
  `Competencia_id` int(11) NOT NULL,
  `Instructor_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `estado` enum('Activo','Cancelado','Finalizada') NOT NULL DEFAULT 'Activo'
) ;

--
-- Volcado de datos para la tabla `sesion`
--

INSERT INTO `sesion` (`id`, `Ficha_id`, `Competencia_id`, `Instructor_id`, `fecha`, `hora_inicio`, `hora_fin`, `estado`) VALUES
(1, 2, 1, 4, '2026-10-07', '06:00:00', '12:00:00', 'Activo'),
(2, 3, 1, 7, '2026-10-07', '06:00:00', '12:00:00', 'Activo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `id` int(11) NOT NULL,
  `nombre` varchar(45) NOT NULL,
  `apellido` varchar(45) NOT NULL,
  `identificacion` bigint(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `Rol_id` int(11) NOT NULL,
  `Ficha_id` int(11) DEFAULT NULL,
  `codigo_llavero` varchar(50) DEFAULT NULL,
  `estado` enum('Activo','Inactivo') NOT NULL DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`id`, `nombre`, `apellido`, `identificacion`, `email`, `password`, `Rol_id`, `Ficha_id`, `codigo_llavero`, `estado`) VALUES
(1, 'Admin', 'SENA', 1000000001, 'admin@sena.edu.co', '$2y$10$3g/ZtekvGBqB/3yyY.LoJeUdTaKpwDxgoyNLiy15Faw5JSn4cTsn2', 1, NULL, NULL, 'Activo'),
(2, 'Ana', 'Garcia', 1000000002, 'instructor@sena.edu.co', '$2y$10$b2o2s5tejt3.T7xxa03HauzfhoPGIWbKjWmwwnkftyLlQxE0riz7K', 2, NULL, NULL, 'Activo'),
(3, 'Luis', 'Perez', 1000000003, 'aprendiz@sena.edu.co', '$2y$10$IbO3K/Fx1zqsDHPHIM5r8OGqaIrQlq6xBq1/h5rDFY6xZZH3.Lp6O', 3, 1, 'rr-ll', 'Inactivo'),
(4, 'Bladimir', 'Silva', 3, 'blasito@gmail.com', '$2y$10$y8/6FvxTjfpPf43yNwxNluMwMXb5slbBLe3h5JWVfrRz4kdnzCY9e', 2, NULL, '0002271972', 'Activo'),
(6, 'andres', 'perez', 2, 'perez@gmail.com', '$2y$10$6OLFFaN22BqKlghOCwbd6OPAyEAWxqjzjkfo/Mv0RnV9Gvw16QV4G', 3, 2, '0002266653', 'Activo'),
(7, 'maria', 'soto', 1004, 'maria@gmail.com', '$2y$10$./sC1WLYVVBMuWUnKzYH0ec99hFko4/9lcsCjyWUz1u3QkOHn2Jli', 2, NULL, '0009433667', 'Activo'),
(8, 'Juan', 'Diaz', 1010000001, 'aprendiz01@sena.edu.co', '$2y$10$cuELhZRLxMQzSyZMF4J19.QCJgjj7F7GQeRyveIrc548O24tmqza.', 3, 1, '1', 'Activo'),
(9, 'Sofia', 'Romero', 1010000002, 'aprendiz02@sena.edu.co', '$2y$10$cuELhZRLxMQzSyZMF4J19.QCJgjj7F7GQeRyveIrc548O24tmqza.', 3, 1, '2', 'Activo'),
(10, 'Mateo', 'Lopez', 1010000003, 'aprendiz03@sena.edu.co', '$2y$10$cuELhZRLxMQzSyZMF4J19.QCJgjj7F7GQeRyveIrc548O24tmqza.', 3, 1, '3', 'Activo'),
(11, 'Valentina', 'Morales', 1010000004, 'aprendiz04@sena.edu.co', '$2y$10$cuELhZRLxMQzSyZMF4J19.QCJgjj7F7GQeRyveIrc548O24tmqza.', 3, 1, '4', 'Activo'),
(12, 'Santiago', 'Ruiz', 1010000005, 'aprendiz05@sena.edu.co', '$2y$10$cuELhZRLxMQzSyZMF4J19.QCJgjj7F7GQeRyveIrc548O24tmqza.', 3, 1, '5', 'Activo'),
(13, 'Camila', 'Gutierrez', 1010000006, 'aprendiz06@sena.edu.co', '$2y$10$cuELhZRLxMQzSyZMF4J19.QCJgjj7F7GQeRyveIrc548O24tmqza.', 3, 2, '6', 'Activo'),
(14, 'David', 'Navarro', 1010000007, 'aprendiz07@sena.edu.co', '$2y$10$cuELhZRLxMQzSyZMF4J19.QCJgjj7F7GQeRyveIrc548O24tmqza.', 3, 2, '7', 'Activo'),
(15, 'Isabella', 'Ortiz', 1010000008, 'aprendiz08@sena.edu.co', '$2y$10$cuELhZRLxMQzSyZMF4J19.QCJgjj7F7GQeRyveIrc548O24tmqza.', 3, 2, '8', 'Activo'),
(16, 'Samuel', 'Castillo', 1010000009, 'aprendiz09@sena.edu.co', '$2y$10$cuELhZRLxMQzSyZMF4J19.QCJgjj7F7GQeRyveIrc548O24tmqza.', 3, 2, '9', 'Activo'),
(17, 'Mariana', 'Silva', 1010000010, 'aprendiz10@sena.edu.co', '$2y$10$cuELhZRLxMQzSyZMF4J19.QCJgjj7F7GQeRyveIrc548O24tmqza.', 3, 2, '10', 'Activo'),
(18, 'Carlos', 'Mendoza', 1020000001, 'instructor01@sena.edu.co', '$2y$10$cuELhZRLxMQzSyZMF4J19.QCJgjj7F7GQeRyveIrc548O24tmqza.', 2, NULL, NULL, 'Activo'),
(19, 'Patricia', 'Gomez', 1020000002, 'instructor02@sena.edu.co', '$2y$10$cuELhZRLxMQzSyZMF4J19.QCJgjj7F7GQeRyveIrc548O24tmqza.', 2, NULL, NULL, 'Activo'),
(20, 'Roberto', 'Herrera', 1020000003, 'instructor03@sena.edu.co', '$2y$10$cuELhZRLxMQzSyZMF4J19.QCJgjj7F7GQeRyveIrc548O24tmqza.', 2, NULL, NULL, 'Activo'),
(21, 'Claudia', 'Martinez', 1020000004, 'instructor04@sena.edu.co', '$2y$10$cuELhZRLxMQzSyZMF4J19.QCJgjj7F7GQeRyveIrc548O24tmqza.', 2, NULL, NULL, 'Activo'),
(22, 'Fernando', 'Morales', 1020000005, 'instructor05@sena.edu.co', '$2y$10$cuELhZRLxMQzSyZMF4J19.QCJgjj7F7GQeRyveIrc548O24tmqza.', 2, NULL, NULL, 'Activo'),
(23, 'Sonia', 'Vargas', 1020000006, 'instructor06@sena.edu.co', '$2y$10$cuELhZRLxMQzSyZMF4J19.QCJgjj7F7GQeRyveIrc548O24tmqza.', 2, NULL, NULL, 'Activo'),
(24, 'Javier', 'Ramirez', 1020000007, 'instructor07@sena.edu.co', '$2y$10$cuELhZRLxMQzSyZMF4J19.QCJgjj7F7GQeRyveIrc548O24tmqza.', 2, NULL, NULL, 'Activo'),
(25, 'Monica', 'Torres', 1020000008, 'instructor08@sena.edu.co', '$2y$10$cuELhZRLxMQzSyZMF4J19.QCJgjj7F7GQeRyveIrc548O24tmqza.', 2, NULL, NULL, 'Activo'),
(26, 'Andres', 'Castro', 1020000009, 'instructor09@sena.edu.co', '$2y$10$cuELhZRLxMQzSyZMF4J19.QCJgjj7F7GQeRyveIrc548O24tmqza.', 2, NULL, NULL, 'Activo'),
(27, 'Elena', 'Rios', 1020000010, 'instructor10@sena.edu.co', '$2y$10$cuELhZRLxMQzSyZMF4J19.QCJgjj7F7GQeRyveIrc548O24tmqza.', 2, NULL, NULL, 'Activo');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `asistencia`
--
ALTER TABLE `asistencia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_asistencia_usuario_sesion` (`Usuario_id`,`Sesion_id`),
  ADD KEY `fk_asistencia_usuario_idx` (`Usuario_id`),
  ADD KEY `fk_asistencia_sesion_idx` (`Sesion_id`),
  ADD KEY `fk_asistencia_registro_idx` (`registrado_por`);

--
-- Indices de la tabla `competencia`
--
ALTER TABLE `competencia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_competencia_programa_nombre` (`Programa_id`,`nombre`),
  ADD KEY `fk_competencia_instructor` (`Instructor_id`);

--
-- Indices de la tabla `estado_excusa`
--
ALTER TABLE `estado_excusa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_Estado_Excusa_Excusa1_idx` (`Excusa_id`),
  ADD KEY `fk_Estado_Excusa_Usuario1_idx` (`Instructor_id`);

--
-- Indices de la tabla `excusa`
--
ALTER TABLE `excusa`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_Excusa_Usuario1_idx` (`Usuario_id`),
  ADD KEY `fk_Excusa_Asistencia_idx` (`Asistencia_id`),
  ADD KEY `fk_Excusa_Inasistencia_idx` (`Inasistencia_id`);

--
-- Indices de la tabla `ficha`
--
ALTER TABLE `ficha`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_Ficha_Programa1_idx` (`Programa_id`),
  ADD KEY `fk_Ficha_jornada_idx` (`jornada_id`),
  ADD KEY `fk_Ficha_Instructor_idx` (`instructor_id`);

--
-- Indices de la tabla `inasistencia`
--
ALTER TABLE `inasistencia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_inasistencia_usuario_sesion` (`Usuario_id`,`Sesion_id`),
  ADD KEY `fk_inasistencia_ficha_idx` (`Ficha_id`),
  ADD KEY `fk_inasistencia_usuario_idx` (`Usuario_id`),
  ADD KEY `fk_inasistencia_sesion_idx` (`Sesion_id`);

--
-- Indices de la tabla `instructor_competencia`
--
ALTER TABLE `instructor_competencia`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_instructor_competencia` (`Instructor_id`,`Competencia_id`),
  ADD KEY `fk_ic_competencia` (`Competencia_id`);

--
-- Indices de la tabla `jornada`
--
ALTER TABLE `jornada`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_jornada_nombre` (`nombre`);

--
-- Indices de la tabla `programa`
--
ALTER TABLE `programa`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `rol`
--
ALTER TABLE `rol`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `sesion`
--
ALTER TABLE `sesion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sesion_ficha_idx` (`Ficha_id`),
  ADD KEY `fk_sesion_competencia_idx` (`Competencia_id`),
  ADD KEY `fk_sesion_instructor_idx` (`Instructor_id`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email_UNIQUE` (`email`),
  ADD UNIQUE KEY `identificacion_UNIQUE` (`identificacion`),
  ADD UNIQUE KEY `uq_usuario_codigo_llavero` (`codigo_llavero`),
  ADD KEY `fk_Usuario_Rol_idx` (`Rol_id`),
  ADD KEY `fk_Usuario_Ficha1_idx` (`Ficha_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `asistencia`
--
ALTER TABLE `asistencia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `competencia`
--
ALTER TABLE `competencia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `estado_excusa`
--
ALTER TABLE `estado_excusa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `excusa`
--
ALTER TABLE `excusa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ficha`
--
ALTER TABLE `ficha`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `inasistencia`
--
ALTER TABLE `inasistencia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `instructor_competencia`
--
ALTER TABLE `instructor_competencia`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `jornada`
--
ALTER TABLE `jornada`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `programa`
--
ALTER TABLE `programa`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `rol`
--
ALTER TABLE `rol`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `sesion`
--
ALTER TABLE `sesion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `asistencia`
--
ALTER TABLE `asistencia`
  ADD CONSTRAINT `fk_asistencia_registro` FOREIGN KEY (`registrado_por`) REFERENCES `usuario` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_asistencia_sesion` FOREIGN KEY (`Sesion_id`) REFERENCES `sesion` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_asistencia_usuario` FOREIGN KEY (`Usuario_id`) REFERENCES `usuario` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `competencia`
--
ALTER TABLE `competencia`
  ADD CONSTRAINT `fk_competencia_instructor` FOREIGN KEY (`Instructor_id`) REFERENCES `usuario` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_competencia_programa` FOREIGN KEY (`Programa_id`) REFERENCES `programa` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `estado_excusa`
--
ALTER TABLE `estado_excusa`
  ADD CONSTRAINT `fk_Estado_Excusa_Excusa1` FOREIGN KEY (`Excusa_id`) REFERENCES `excusa` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_Estado_Excusa_Usuario1` FOREIGN KEY (`Instructor_id`) REFERENCES `usuario` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `excusa`
--
ALTER TABLE `excusa`
  ADD CONSTRAINT `fk_Excusa_Asistencia` FOREIGN KEY (`Asistencia_id`) REFERENCES `asistencia` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_Excusa_Inasistencia` FOREIGN KEY (`Inasistencia_id`) REFERENCES `inasistencia` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_Excusa_Usuario1` FOREIGN KEY (`Usuario_id`) REFERENCES `usuario` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `ficha`
--
ALTER TABLE `ficha`
  ADD CONSTRAINT `fk_Ficha_Instructor` FOREIGN KEY (`instructor_id`) REFERENCES `usuario` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_Ficha_Programa1` FOREIGN KEY (`Programa_id`) REFERENCES `programa` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_ficha_jornada` FOREIGN KEY (`jornada_id`) REFERENCES `jornada` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `inasistencia`
--
ALTER TABLE `inasistencia`
  ADD CONSTRAINT `fk_inasistencia_ficha` FOREIGN KEY (`Ficha_id`) REFERENCES `ficha` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_inasistencia_sesion` FOREIGN KEY (`Sesion_id`) REFERENCES `sesion` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_inasistencia_usuario` FOREIGN KEY (`Usuario_id`) REFERENCES `usuario` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `instructor_competencia`
--
ALTER TABLE `instructor_competencia`
  ADD CONSTRAINT `fk_ic_competencia` FOREIGN KEY (`Competencia_id`) REFERENCES `competencia` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_ic_usuario` FOREIGN KEY (`Instructor_id`) REFERENCES `usuario` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `sesion`
--
ALTER TABLE `sesion`
  ADD CONSTRAINT `fk_sesion_competencia` FOREIGN KEY (`Competencia_id`) REFERENCES `competencia` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_sesion_ficha` FOREIGN KEY (`Ficha_id`) REFERENCES `ficha` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_sesion_instructor` FOREIGN KEY (`Instructor_id`) REFERENCES `usuario` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD CONSTRAINT `fk_Usuario_Ficha1` FOREIGN KEY (`Ficha_id`) REFERENCES `ficha` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_Usuario_Rol` FOREIGN KEY (`Rol_id`) REFERENCES `rol` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
