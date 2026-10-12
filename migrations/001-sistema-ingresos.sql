-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 07-08-2026 a las 23:41:32
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
-- Base de datos: `sistema-ingresos`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `aprendices`
--

CREATE TABLE `aprendices` (
  `idAprendices` int(11) NOT NULL,
  `codigoRfid` varchar(50) DEFAULT NULL,
  `fk_usuarios_idUsuario` int(11) NOT NULL,
  `fk_fichas_idFicha` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `excusasmedicas`
--

CREATE TABLE `excusasmedicas` (
  `idExcusa` int(11) NOT NULL,
  `fechaInicio` date NOT NULL,
  `fechaFin` date NOT NULL,
  `motivo` text NOT NULL,
  `archivoAdjunto` varchar(255) NOT NULL,
  `estado` enum('Pendiente','Aprobada','Rechazada') DEFAULT 'Pendiente',
  `comentarioRevision` text DEFAULT NULL,
  `fechaSolicitud` timestamp NULL DEFAULT NULL,
  `aprendices_idAprendices` int(11) NOT NULL,
  `ingresos_asistencias_idIngreso` int(11) NOT NULL,
  `instructor_usuarios_idUsuario` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `fichas`
--

CREATE TABLE `fichas` (
  `idFicha` int(11) NOT NULL,
  `codigoFicha` varchar(45) NOT NULL,
  `nombrePrograma` varchar(150) NOT NULL,
  `fk_instructor_lider` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `horarios`
--

CREATE TABLE `horarios` (
  `idHorario` int(11) NOT NULL,
  `diaSemana` enum('Lunes','Martes','Miercoles','Jueves','Viernes','Sabado') NOT NULL,
  `horaEntrada` time NOT NULL,
  `horaSalida` time NOT NULL,
  `toleranciaMinutos` int(11) DEFAULT 15,
  `fk_fichas_idFicha` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ingresos_asistencias`
--

CREATE TABLE `ingresos_asistencias` (
  `idIngreso` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `horaEntrada` time DEFAULT NULL,
  `horaSalida` time DEFAULT NULL,
  `estado` enum('A_Tiempo','Retardo','Salida_Temprana','Inasistencia','Justificado') DEFAULT 'A_Tiempo',
  `minutosRetardo` int(11) DEFAULT 0,
  `minutosSalidaAnticipada` int(11) DEFAULT 0,
  `fk_aprendices_idAprendices` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `idRol` int(11) NOT NULL,
  `nombre` varchar(45) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `idUsuario` int(11) NOT NULL,
  `numDocumento` varchar(45) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `correoElectronico` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `estado` enum('activo','inactivo','pendiente','bloqueado') DEFAULT 'activo',
  `creadoEn` timestamp NOT NULL DEFAULT current_timestamp(),
  `fk_roles_idRol` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `aprendices`
--
ALTER TABLE `aprendices`
  ADD PRIMARY KEY (`idAprendices`),
  ADD UNIQUE KEY `usuarios_idUsuario_UNIQUE` (`fk_usuarios_idUsuario`),
  ADD UNIQUE KEY `codigoRfid_UNIQUE` (`codigoRfid`),
  ADD KEY `fk_aprendices_usuarios1_idx` (`fk_usuarios_idUsuario`),
  ADD KEY `fk_aprendices_fichas1_idx` (`fk_fichas_idFicha`);

--
-- Indices de la tabla `excusasmedicas`
--
ALTER TABLE `excusasmedicas`
  ADD PRIMARY KEY (`idExcusa`),
  ADD KEY `fk_excusasMedicas_aprendices1_idx` (`aprendices_idAprendices`),
  ADD KEY `fk_excusasMedicas_ingresos_asistencias1_idx` (`ingresos_asistencias_idIngreso`),
  ADD KEY `fk_excusasMedicas_usuarios1_idx` (`instructor_usuarios_idUsuario`);

--
-- Indices de la tabla `fichas`
--
ALTER TABLE `fichas`
  ADD PRIMARY KEY (`idFicha`),
  ADD UNIQUE KEY `codigoFicha_UNIQUE` (`codigoFicha`),
  ADD KEY `fk_fichas_usuarios1_idx` (`fk_instructor_lider`);

--
-- Indices de la tabla `horarios`
--
ALTER TABLE `horarios`
  ADD PRIMARY KEY (`idHorario`),
  ADD KEY `fk_horarios_fichas1_idx` (`fk_fichas_idFicha`);

--
-- Indices de la tabla `ingresos_asistencias`
--
ALTER TABLE `ingresos_asistencias`
  ADD PRIMARY KEY (`idIngreso`),
  ADD KEY `fk_ingresos_asistencias_aprendices1_idx` (`fk_aprendices_idAprendices`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`idRol`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`idUsuario`),
  ADD UNIQUE KEY `numDocumento_UNIQUE` (`numDocumento`),
  ADD UNIQUE KEY `correoElectronico_UNIQUE` (`correoElectronico`),
  ADD KEY `fk_usuarios_roles_idx` (`fk_roles_idRol`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `aprendices`
--
ALTER TABLE `aprendices`
  MODIFY `idAprendices` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `fichas`
--
ALTER TABLE `fichas`
  MODIFY `idFicha` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `horarios`
--
ALTER TABLE `horarios`
  MODIFY `idHorario` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ingresos_asistencias`
--
ALTER TABLE `ingresos_asistencias`
  MODIFY `idIngreso` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `idRol` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `idUsuario` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `aprendices`
--
ALTER TABLE `aprendices`
  ADD CONSTRAINT `fk_aprendices_fichas1` FOREIGN KEY (`fk_fichas_idFicha`) REFERENCES `fichas` (`idFicha`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_aprendices_usuarios1` FOREIGN KEY (`fk_usuarios_idUsuario`) REFERENCES `usuarios` (`idUsuario`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `excusasmedicas`
--
ALTER TABLE `excusasmedicas`
  ADD CONSTRAINT `fk_excusasMedicas_aprendices1` FOREIGN KEY (`aprendices_idAprendices`) REFERENCES `aprendices` (`idAprendices`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_excusasMedicas_ingresos_asistencias1` FOREIGN KEY (`ingresos_asistencias_idIngreso`) REFERENCES `ingresos_asistencias` (`idIngreso`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `fk_excusasMedicas_usuarios1` FOREIGN KEY (`instructor_usuarios_idUsuario`) REFERENCES `usuarios` (`idUsuario`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `fichas`
--
ALTER TABLE `fichas`
  ADD CONSTRAINT `fk_fichas_usuarios1` FOREIGN KEY (`fk_instructor_lider`) REFERENCES `usuarios` (`idUsuario`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `horarios`
--
ALTER TABLE `horarios`
  ADD CONSTRAINT `fk_horarios_fichas1` FOREIGN KEY (`fk_fichas_idFicha`) REFERENCES `fichas` (`idFicha`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `ingresos_asistencias`
--
ALTER TABLE `ingresos_asistencias`
  ADD CONSTRAINT `fk_ingresos_asistencias_aprendices1` FOREIGN KEY (`fk_aprendices_idAprendices`) REFERENCES `aprendices` (`idAprendices`) ON DELETE NO ACTION ON UPDATE NO ACTION;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuarios_roles` FOREIGN KEY (`fk_roles_idRol`) REFERENCES `roles` (`idRol`) ON DELETE NO ACTION ON UPDATE NO ACTION;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;