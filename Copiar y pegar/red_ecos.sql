-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1:3307
-- Tiempo de generación: 25-10-2026 a las 21:32:01
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
-- Base de datos: `red_ecos`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cancion`
--

DROP TABLE IF EXISTS `cancion`;
CREATE TABLE `cancion` (
  `id_cancion` int(11) NOT NULL,
  `id_artista` int(11) DEFAULT NULL,
  `titulo` varchar(100) DEFAULT NULL,
  `duracion` time DEFAULT NULL,
  `archivo_url` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `comentario`
--

DROP TABLE IF EXISTS `comentario`;
CREATE TABLE `comentario` (
  `id_comentario` int(11) NOT NULL,
  `id_publicacion` int(11) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `contenido` text DEFAULT NULL,
  `fecha_creacion` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `hashtag`
--

DROP TABLE IF EXISTS `hashtag`;
CREATE TABLE `hashtag` (
  `id_hashtag` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `publicacion`
--

DROP TABLE IF EXISTS `publicacion`;
CREATE TABLE `publicacion` (
  `id_publicacion` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `texto` text DEFAULT NULL,
  `fecha_creacion` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `publicacion_hashtag`
--

DROP TABLE IF EXISTS `publicacion_hashtag`;
CREATE TABLE `publicacion_hashtag` (
  `id_pub_hash` int(11) NOT NULL,
  `id_publicacion` int(11) DEFAULT NULL,
  `id_hashtag` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `reaccion`
--

DROP TABLE IF EXISTS `reaccion`;
CREATE TABLE `reaccion` (
  `id_reaccion` int(11) NOT NULL,
  `id_publicacion` int(11) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recomendacion_carrusel`
--

DROP TABLE IF EXISTS `recomendacion_carrusel`;
CREATE TABLE `recomendacion_carrusel` (
  `id_recomendacion` int(11) NOT NULL,
  `titulo` varchar(150) DEFAULT NULL,
  `tipo` varchar(50) DEFAULT NULL,
  `imagen_url` varchar(255) DEFAULT NULL,
  `enlace_url` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `relacion_usuario`
--

DROP TABLE IF EXISTS `relacion_usuario`;
CREATE TABLE `relacion_usuario` (
  `id_relacion` int(11) NOT NULL,
  `id_usuario_origen` int(11) DEFAULT NULL,
  `id_usuario_destino` int(11) DEFAULT NULL,
  `tipo_relacion` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol`
--

DROP TABLE IF EXISTS `rol`;
CREATE TABLE `rol` (
  `id_rol` int(11) NOT NULL,
  `nombre` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `rol`
--

INSERT INTO `rol` (`id_rol`, `nombre`) VALUES
(1, 'Usuario'),
(2, 'Administrador');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

DROP TABLE IF EXISTS `usuario`;
CREATE TABLE `usuario` (
  `id_rol` int(11) DEFAULT NULL,
  `nombre` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `contraseña_hash` varchar(100) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `genero` varchar(50) DEFAULT NULL,
  `alias` varchar(100) DEFAULT NULL,
  `pais` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `banner` varchar(255) DEFAULT NULL,
  `id_usuario` int(11) NOT NULL,
  `bio` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`id_rol`, `nombre`, `email`, `contraseña_hash`, `fecha_nacimiento`, `genero`, `alias`, `pais`, `avatar`, `banner`, `id_usuario`, `bio`) VALUES
(1, 'Robert Pendleton', 'robert@gmail.com', '$2y$10$C4vCEo4I0l1.7/YUhmKR.ORXcl9224FHsMBhGOv/x6PBzA7c4fRX6', '1900-05-09', 'masculino', '@robertpendleton188X', 'Londres', 'Image/25a9a61a55b9902c7c233a1baf6a6917.png', 'Image/ce051ff10af3b898d1dd47887fd7be97.png', 0, 'Me gustan los gatos');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `cancion`
--
ALTER TABLE `cancion`
  ADD PRIMARY KEY (`id_cancion`),
  ADD KEY `id_artista` (`id_artista`);

--
-- Indices de la tabla `comentario`
--
ALTER TABLE `comentario`
  ADD PRIMARY KEY (`id_comentario`),
  ADD KEY `id_publicacion` (`id_publicacion`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `hashtag`
--
ALTER TABLE `hashtag`
  ADD PRIMARY KEY (`id_hashtag`);

--
-- Indices de la tabla `publicacion`
--
ALTER TABLE `publicacion`
  ADD PRIMARY KEY (`id_publicacion`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `publicacion_hashtag`
--
ALTER TABLE `publicacion_hashtag`
  ADD PRIMARY KEY (`id_pub_hash`),
  ADD KEY `id_publicacion` (`id_publicacion`),
  ADD KEY `id_hashtag` (`id_hashtag`);

--
-- Indices de la tabla `reaccion`
--
ALTER TABLE `reaccion`
  ADD PRIMARY KEY (`id_reaccion`),
  ADD KEY `id_publicacion` (`id_publicacion`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `recomendacion_carrusel`
--
ALTER TABLE `recomendacion_carrusel`
  ADD PRIMARY KEY (`id_recomendacion`);

--
-- Indices de la tabla `relacion_usuario`
--
ALTER TABLE `relacion_usuario`
  ADD PRIMARY KEY (`id_relacion`),
  ADD KEY `id_usuario_origen` (`id_usuario_origen`),
  ADD KEY `id_usuario_destino` (`id_usuario_destino`);

--
-- Indices de la tabla `rol`
--
ALTER TABLE `rol`
  ADD PRIMARY KEY (`id_rol`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD KEY `id_rol` (`id_rol`);

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `cancion`
--
ALTER TABLE `cancion`
  ADD CONSTRAINT `cancion_ibfk_1` FOREIGN KEY (`id_artista`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `comentario`
--
ALTER TABLE `comentario`
  ADD CONSTRAINT `comentario_ibfk_1` FOREIGN KEY (`id_publicacion`) REFERENCES `publicacion` (`id_publicacion`),
  ADD CONSTRAINT `comentario_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `publicacion`
--
ALTER TABLE `publicacion`
  ADD CONSTRAINT `publicacion_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `publicacion_hashtag`
--
ALTER TABLE `publicacion_hashtag`
  ADD CONSTRAINT `publicacion_hashtag_ibfk_1` FOREIGN KEY (`id_publicacion`) REFERENCES `publicacion` (`id_publicacion`),
  ADD CONSTRAINT `publicacion_hashtag_ibfk_2` FOREIGN KEY (`id_hashtag`) REFERENCES `hashtag` (`id_hashtag`);

--
-- Filtros para la tabla `reaccion`
--
ALTER TABLE `reaccion`
  ADD CONSTRAINT `reaccion_ibfk_1` FOREIGN KEY (`id_publicacion`) REFERENCES `publicacion` (`id_publicacion`),
  ADD CONSTRAINT `reaccion_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `relacion_usuario`
--
ALTER TABLE `relacion_usuario`
  ADD CONSTRAINT `relacion_usuario_ibfk_1` FOREIGN KEY (`id_usuario_origen`) REFERENCES `usuario` (`id_usuario`),
  ADD CONSTRAINT `relacion_usuario_ibfk_2` FOREIGN KEY (`id_usuario_destino`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD CONSTRAINT `usuario_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id_rol`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
