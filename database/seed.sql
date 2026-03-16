-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Servidor: localhost
-- Tiempo de generación: 16-03-2026 a las 15:34:14
-- Versión del servidor: 10.4.21-MariaDB
-- Versión de PHP: 8.1.6

CREATE DATABASE robotschool_inventory;
USE robotschool_inventory;


SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `robotschool_inventory`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `acad_actividades`
--

CREATE TABLE `acad_actividades` (
  `id` int(10) UNSIGNED NOT NULL,
  `unidad_id` int(10) UNSIGNED NOT NULL,
  `titulo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('lectura','video','ejercicio','proyecto','evaluacion','guia','taller') COLLATE utf8mb4_unicode_ci DEFAULT 'guia',
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contenido` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'HTML o Markdown',
  `archivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_video` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `puntos` smallint(6) DEFAULT 0 COMMENT 'Puntos XP para gamificacion',
  `orden` tinyint(4) DEFAULT 1,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `acad_asignaciones`
--

CREATE TABLE `acad_asignaciones` (
  `id` int(10) UNSIGNED NOT NULL,
  `material_id` int(10) UNSIGNED NOT NULL,
  `estudiante_id` int(10) UNSIGNED NOT NULL,
  `entregado` tinyint(1) DEFAULT 0,
  `fecha_entrega` date DEFAULT NULL,
  `devuelto` tinyint(1) DEFAULT 0,
  `fecha_devol` date DEFAULT NULL,
  `notas` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `acad_colegios`
--

CREATE TABLE `acad_colegios` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ciudad` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nit` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contacto` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `acad_coordinadores`
--

CREATE TABLE `acad_coordinadores` (
  `id` int(10) UNSIGNED NOT NULL,
  `colegio_id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cargo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `acad_cursos`
--

CREATE TABLE `acad_cursos` (
  `id` int(10) UNSIGNED NOT NULL,
  `colegio_id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `grado` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grupo` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nivel` enum('preescolar','primaria','secundaria','media') COLLATE utf8mb4_unicode_ci DEFAULT 'primaria',
  `anio` year(4) DEFAULT NULL,
  `periodo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `docente` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#185FA5',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `acad_estudiantes`
--

CREATE TABLE `acad_estudiantes` (
  `id` int(10) UNSIGNED NOT NULL,
  `curso_id` int(10) UNSIGNED NOT NULL,
  `nombres` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellidos` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `documento` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `acad_materiales`
--

CREATE TABLE `acad_materiales` (
  `id` int(10) UNSIGNED NOT NULL,
  `curso_id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('libro','guia','kit','ficha','video','otro') COLLATE utf8mb4_unicode_ci DEFAULT 'guia',
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `archivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `para_grado` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `acad_progreso`
--

CREATE TABLE `acad_progreso` (
  `id` int(10) UNSIGNED NOT NULL,
  `estudiante_id` int(10) UNSIGNED NOT NULL,
  `actividad_id` int(10) UNSIGNED NOT NULL,
  `estado` enum('pendiente','en_progreso','completado') COLLATE utf8mb4_unicode_ci DEFAULT 'pendiente',
  `puntos_obtenidos` smallint(6) DEFAULT 0,
  `intentos` tinyint(4) DEFAULT 0,
  `completado_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `acad_unidades`
--

CREATE TABLE `acad_unidades` (
  `id` int(10) UNSIGNED NOT NULL,
  `curso_id` int(10) UNSIGNED NOT NULL,
  `titulo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orden` tinyint(4) NOT NULL DEFAULT 1,
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#185FA5',
  `icono` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'bi-book',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `acad_xp`
--

CREATE TABLE `acad_xp` (
  `estudiante_id` int(10) UNSIGNED NOT NULL,
  `curso_id` int(10) UNSIGNED NOT NULL,
  `xp_total` int(11) DEFAULT 0,
  `nivel_xp` tinyint(4) DEFAULT 1 COMMENT '1=Aprendiz 2=Constructor 3=Ingeniero 4=Maestro',
  `racha_dias` tinyint(4) DEFAULT 0,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria`
--

CREATE TABLE `auditoria` (
  `id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `accion` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'crear_elemento, liquidar_pedido, etc.',
  `tabla` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registro_id` int(10) UNSIGNED DEFAULT NULL,
  `datos_antes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos_antes`)),
  `datos_desp` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`datos_desp`)),
  `ip` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `auditoria`
--

INSERT INTO `auditoria` (`id`, `usuario_id`, `accion`, `tabla`, `registro_id`, `datos_antes`, `datos_desp`, `ip`, `created_at`) VALUES
(1, 1, 'crear_elemento', 'elementos', 1, NULL, NULL, '::1', '2026-03-12 15:58:28'),
(2, 1, 'editar_elemento', 'elementos', 1, NULL, NULL, '::1', '2026-03-12 16:06:19'),
(3, 1, 'crear_elemento', 'elementos', 2, NULL, NULL, '::1', '2026-03-12 17:06:27'),
(4, 1, 'editar_elemento', 'elementos', 1, NULL, NULL, '::1', '2026-03-15 16:25:42');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prefijo` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ej: ARD, SEN, ESP, RPI',
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icono` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'bi-box',
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#3a72e8',
  `activa` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id`, `nombre`, `prefijo`, `descripcion`, `icono`, `color`, `activa`) VALUES
(1, 'Arduino & Clones', 'ARD', 'Placas Arduino UNO, Mega, Nano, etc.', 'bi-cpu', '#e84315', 1),
(2, 'ESP8266 / ESP32', 'ESP', 'Módulos WiFi y BT de Espressif', 'bi-wifi', '#00897b', 1),
(3, 'Raspberry Pi', 'RPI', 'Placas Raspberry Pi y accesorios', 'bi-hdd-rack', '#c62828', 1),
(4, 'Sensores', 'SEN', 'Sensores de todo tipo', 'bi-thermometer', '#6a1b9a', 1),
(5, 'Módulos', 'MOD', 'Módulos de comunicación, display, etc.', 'bi-grid', '#1565c0', 1),
(6, 'Actuadores', 'ACT', 'Servos, motores, relés', 'bi-gear-wide', '#2e7d32', 1),
(7, 'Fuentes y Energía', 'PWR', 'Baterías, reguladores, cargadores', 'bi-lightning', '#f57f17', 1),
(8, 'Cables y Conectores', 'CAB', 'Jumpers, protoboard, terminales', 'bi-plugin', '#37474f', 1),
(9, 'Herramientas', 'HER', 'Soldadores, multímetros, pistolas de calor', 'bi-tools', '#4e342e', 1),
(10, 'Impresión 3D', 'IMP', 'Filamento, piezas impresas', 'bi-printer', '#5c6bc0', 1),
(11, 'Corte Láser', 'LAS', 'MDF, acrílico, piezas cortadas', 'bi-stars', '#f4511e', 1),
(12, 'Cajas de Almacenamiento', 'CAJ', 'Cajas internas por colegio y empaque final', 'bi-box-seam', '#00838f', 1),
(13, 'Kits Armados', 'KIT', 'Kits completos listos para entrega', 'bi-bag-check', '#43a047', 1),
(14, 'Otros', 'OTR', 'Elementos varios no clasificados', 'bi-three-dots', '#78909c', 1),
(15, 'Tornillos y Tuercas', 'TOR', 'Tornillos, tuercas, separadores, arandelas', 'bi-gear', '#795548', 1),
(16, 'LEDs y Displays', 'LED', 'LEDs de colores, displays LCD, OLED, 7 segmentos', 'bi-lightbulb', '#FF6F00', 1),
(17, 'Resistencias y Caps', 'RES', 'Resistencias, capacitores, inductores, diodos', 'bi-diagram-3', '#9C27B0', 1),
(18, 'Cables MDF y Madera', 'MDF', 'Láminas MDF, acrílico, madera balsa', 'bi-grid-1x2', '#6D4C41', 1),
(19, 'Filamentos 3D', 'FIL', 'PLA, ABS, PETG, TPU por color y tipo', 'bi-circle', '#00ACC1', 1),
(20, 'Stickers e Impresión', 'STK', 'Stickers, etiquetas, laminado, vinilo', 'bi-tag', '#F06292', 1),
(21, 'Empaque y Cajas', 'EMP', 'Cajas de empaque final, bolsas, espumas', 'bi-box2', '#26A69A', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `codigos_secuencia`
--

CREATE TABLE `codigos_secuencia` (
  `categoria_id` smallint(5) UNSIGNED NOT NULL,
  `ultimo_numero` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `codigos_secuencia`
--

INSERT INTO `codigos_secuencia` (`categoria_id`, `ultimo_numero`) VALUES
(1, 2),
(2, 0),
(3, 0),
(4, 0),
(5, 0),
(6, 0),
(7, 0),
(8, 0),
(9, 0),
(10, 0),
(11, 0),
(12, 0),
(13, 0),
(14, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `colegios`
--

CREATE TABLE `colegios` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nit` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ciudad` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'Bogotá',
  `direccion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contacto` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nivel` set('preescolar','primaria','secundaria','media') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` enum('publico','privado','mixto') COLLATE utf8mb4_unicode_ci DEFAULT 'privado',
  `rector` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `colegios`
--

INSERT INTO `colegios` (`id`, `nombre`, `nit`, `ciudad`, `direccion`, `contacto`, `email`, `telefono`, `logo`, `nivel`, `tipo`, `rector`, `notas`, `updated_at`, `activo`, `created_at`) VALUES
(1, 'Colegio San Viator Bilingue internacional', '860042095-1', 'Bogotá', 'Autopista Norte # 209-51', 'Orlando Peña', 'sanviator@sanviator.edu.co', '', 'colegios/img_69b3245671cb67.54937184.png', '', 'privado', 'Pdre. Alejandro Adame', '', '2026-03-12 15:38:46', 1, '2026-03-12 15:38:20');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion`
--

CREATE TABLE `configuracion` (
  `clave` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grupo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'general'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `configuracion`
--

INSERT INTO `configuracion` (`clave`, `valor`, `descripcion`, `grupo`) VALUES
('arancel_default_pct', '5', '% arancel por defecto', 'importacion'),
('barcode_tipo', 'CODE128', 'Tipo código de barras', 'sistema'),
('dhl_zona_factor', '1.0', 'Factor zona DHL Colombia', 'importacion'),
('empresa_ciudad', 'Bogotá', 'Ciudad', 'empresa'),
('empresa_nit', '900.000.000-0', 'NIT', 'empresa'),
('empresa_nombre', 'ROBOTSchool Colombia', 'Nombre de la empresa', 'empresa'),
('empresa_telefono', '318 654 1859', 'Teléfono', 'empresa'),
('items_por_pagina', '25', 'Paginación por defecto', 'sistema'),
('iva_pct', '19', '% IVA Colombia', 'importacion'),
('moneda_compra', 'USD', 'Moneda de compra en China', 'importacion'),
('trm_default', '4200', 'TRM USD→COP por defecto', 'importacion'),
('version', '1.0.0', 'Versión del sistema', 'sistema');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `convenios`
--

CREATE TABLE `convenios` (
  `id` int(10) UNSIGNED NOT NULL,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `colegio_id` int(10) UNSIGNED DEFAULT NULL,
  `nombre_colegio` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `comercial_id` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `nombre_comercial` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_convenio` date DEFAULT NULL,
  `vigencia_inicio` date DEFAULT NULL,
  `vigencia_fin` date DEFAULT NULL,
  `valor_total` decimal(14,0) DEFAULT 0,
  `estado` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'borrador',
  `motivo_rechazo` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aprobado_por` int(10) UNSIGNED DEFAULT NULL,
  `fecha_aprobacion` datetime DEFAULT NULL,
  `doc_convenio` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `convenios`
--

INSERT INTO `convenios` (`id`, `codigo`, `colegio_id`, `nombre_colegio`, `comercial_id`, `nombre_comercial`, `fecha_convenio`, `vigencia_inicio`, `vigencia_fin`, `valor_total`, `estado`, `motivo_rechazo`, `aprobado_por`, `fecha_aprobacion`, `doc_convenio`, `notas`, `activo`, `created_at`, `updated_at`) VALUES
(1, 'CON-2026-001', NULL, 'Colegio Is Cool', 1, 'Francisco Puchana', '2026-03-14', '2026-03-14', '2027-03-14', '4500000', 'aprobado', '', 1, '2026-03-15 18:47:27', 'conv_1773544284_69b6235c15e69.pdf', 'Kits con libros', 0, '2026-03-14 22:00:32', '2026-03-15 20:02:12'),
(2, 'CON-2026-002', 1, 'Colegio San Viator Bilingue internacional', 1, 'Francisco Puchana', '2026-03-15', '2026-03-15', '2026-03-15', '5100000', 'en_produccion', NULL, 1, '2026-03-15 20:04:09', 'conv_1773623035_69b756fbe8066.pdf', 'Capacitacion y libros', 1, '2026-03-15 20:03:55', '2026-03-15 20:04:09');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `convenio_cursos`
--

CREATE TABLE `convenio_cursos` (
  `id` int(10) UNSIGNED NOT NULL,
  `convenio_id` int(10) UNSIGNED NOT NULL,
  `nombre_curso` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `curso_id` int(10) UNSIGNED DEFAULT NULL,
  `num_estudiantes` int(10) UNSIGNED DEFAULT 0,
  `kit_id` int(10) UNSIGNED DEFAULT NULL,
  `nombre_kit` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor_kit` decimal(12,0) DEFAULT 0,
  `incluye_libro` tinyint(1) DEFAULT 0,
  `nombre_libro` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor_libro` decimal(12,0) DEFAULT 0,
  `valor_total` decimal(12,0) DEFAULT 0,
  `notas` text COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `convenio_cursos`
--

INSERT INTO `convenio_cursos` (`id`, `convenio_id`, `nombre_curso`, `curso_id`, `num_estudiantes`, `kit_id`, `nombre_kit`, `valor_kit`, `incluye_libro`, `nombre_libro`, `valor_libro`, `valor_total`, `notas`) VALUES
(2, 1, 'Grado 1°', 6, 30, 3, 'Kit explorer Bandeja', '125000', 1, 'Ciudades Inteligentes', '25000', '4500000', NULL),
(3, 2, 'Grado 1°', NULL, 30, 1, 'Kit Junior Ciencia y tecnologia', '150000', 1, NULL, '20000', '5100000', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `convenio_historial`
--

CREATE TABLE `convenio_historial` (
  `id` int(10) UNSIGNED NOT NULL,
  `convenio_id` int(10) UNSIGNED NOT NULL,
  `estado` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `comentario` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `convenio_historial`
--

INSERT INTO `convenio_historial` (`id`, `convenio_id`, `estado`, `usuario_id`, `comentario`, `created_at`) VALUES
(1, 1, 'borrador', 1, 'Convenio guardado', '2026-03-14 22:00:32'),
(2, 1, 'pendiente_aprobacion', 1, 'Documento subido, pendiente aprobacion', '2026-03-14 22:11:24'),
(3, 1, 'aprobado', 1, '', '2026-03-15 18:47:27'),
(4, 2, 'pendiente_aprobacion', 1, 'Documento subido, pendiente aprobacion', '2026-03-15 20:03:55'),
(5, 2, 'aprobado', 1, NULL, '2026-03-15 20:04:09');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cursos`
--

CREATE TABLE `cursos` (
  `id` int(10) UNSIGNED NOT NULL,
  `colegio_id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Ej: Grado 5°A, Robótica Junior',
  `grado` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ej: 5, 6, 10',
  `grupo` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ej: A, B, 01',
  `nivel` enum('preescolar','primaria','secundaria','media','otro') COLLATE utf8mb4_unicode_ci DEFAULT 'primaria',
  `num_estudiantes` smallint(5) UNSIGNED DEFAULT 0,
  `docente` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kit_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Kit asignado a este curso',
  `anio` year(4) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `cursos`
--

INSERT INTO `cursos` (`id`, `colegio_id`, `nombre`, `grado`, `grupo`, `nivel`, `num_estudiantes`, `docente`, `kit_id`, `anio`, `activo`, `created_at`) VALUES
(1, 1, 'Tercero', '3', '', 'primaria', 8, 'Orlando Peña', 1, NULL, 1, '2026-03-12 21:42:23'),
(2, 1, 'Tercero', '3', '', 'primaria', 8, 'Orlando Peña', 1, NULL, 0, '2026-03-12 21:44:42'),
(3, 1, 'Cuarto', '4', '', 'primaria', 80, 'Andrés Terán', 3, 2026, 1, '2026-03-12 21:53:27'),
(4, 1, 'Cuarto', '4', '', 'primaria', 80, 'Andrés Terán', 3, 2026, 0, '2026-03-12 21:55:51');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `despachos`
--

CREATE TABLE `despachos` (
  `id` int(10) UNSIGNED NOT NULL,
  `codigo` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'DES-2024-001',
  `colegio_id` int(10) UNSIGNED DEFAULT NULL,
  `curso_id` int(10) UNSIGNED DEFAULT NULL,
  `fecha` date NOT NULL,
  `estado` enum('preparando','despachado','entregado','anulado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'preparando',
  `guia_transporte` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transportadora` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre_recibe` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cargo_recibe` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_entrega` date DEFAULT NULL,
  `valor_flete_cop` decimal(12,2) DEFAULT 0.00,
  `notas` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `creado_por` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `despachos`
--

INSERT INTO `despachos` (`id`, `codigo`, `colegio_id`, `curso_id`, `fecha`, `estado`, `guia_transporte`, `transportadora`, `nombre_recibe`, `cargo_recibe`, `fecha_entrega`, `valor_flete_cop`, `notas`, `creado_por`, `created_at`) VALUES
(1, 'DES-2026-001', 1, NULL, '2026-03-12', 'preparando', '04', 'Mensajeria ROBOTSchool', 'Orlando Peña', 'coordinados', NULL, '0.00', '', 1, '2026-03-12 18:31:49'),
(2, 'DES-2026-002', 1, NULL, '2026-03-14', 'preparando', '', '', '', '', NULL, '0.00', '', 1, '2026-03-14 09:07:03');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `despachos_secuencia`
--

CREATE TABLE `despachos_secuencia` (
  `anio` year(4) NOT NULL,
  `ultimo_numero` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `despachos_secuencia`
--

INSERT INTO `despachos_secuencia` (`anio`, `ultimo_numero`) VALUES
(2026, 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `despacho_kits`
--

CREATE TABLE `despacho_kits` (
  `id` int(10) UNSIGNED NOT NULL,
  `despacho_id` int(10) UNSIGNED NOT NULL,
  `kit_id` int(10) UNSIGNED NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `despacho_kits`
--

INSERT INTO `despacho_kits` (`id`, `despacho_id`, `kit_id`, `cantidad`) VALUES
(1, 1, 1, 5),
(2, 2, 6, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `elementos`
--

CREATE TABLE `elementos` (
  `id` int(10) UNSIGNED NOT NULL,
  `categoria_id` smallint(5) UNSIGNED NOT NULL,
  `proveedor_id` int(10) UNSIGNED DEFAULT NULL,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'RS-ARD-001',
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `especificaciones` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'JSON con specs técnicas',
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto3` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `precio_usd` decimal(10,4) NOT NULL DEFAULT 0.0000 COMMENT 'Precio unitario en USD desde China',
  `peso_gramos` decimal(10,3) NOT NULL DEFAULT 0.000 COMMENT 'Peso del elemento en gramos',
  `largo_mm` decimal(8,2) DEFAULT NULL,
  `ancho_mm` decimal(8,2) DEFAULT NULL,
  `alto_mm` decimal(8,2) DEFAULT NULL,
  `unidad` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unidad' COMMENT 'unidad, par, set, rollo',
  `es_consumible` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Si es consumible (tornillos, leds, etc)',
  `stock_lote` int(11) NOT NULL DEFAULT 1 COMMENT 'Cantidad por lote de compra (ej: 100 tornillos)',
  `precio_lote_usd` decimal(10,4) DEFAULT 0.0000 COMMENT 'Precio por lote en USD',
  `stock_actual` int(11) NOT NULL DEFAULT 0,
  `stock_minimo` int(11) NOT NULL DEFAULT 5 COMMENT 'Alerta amarilla',
  `stock_maximo` int(11) NOT NULL DEFAULT 100 COMMENT 'Nivel óptimo',
  `ubicacion` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Estante/gaveta física',
  `costo_real_cop` decimal(12,2) DEFAULT 0.00 COMMENT 'Último costo real liquidado en COP',
  `precio_venta_cop` decimal(12,2) DEFAULT 0.00 COMMENT 'Precio sugerido de venta',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `elementos`
--

INSERT INTO `elementos` (`id`, `categoria_id`, `proveedor_id`, `codigo`, `nombre`, `descripcion`, `especificaciones`, `foto`, `foto2`, `foto3`, `precio_usd`, `peso_gramos`, `largo_mm`, `ancho_mm`, `alto_mm`, `unidad`, `es_consumible`, `stock_lote`, `precio_lote_usd`, `stock_actual`, `stock_minimo`, `stock_maximo`, `ubicacion`, `costo_real_cop`, `precio_venta_cop`, `activo`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 3, 'RS-ARD-001', 'UNO R3 Improved Version CH340 Chip', 'Placa de desarrollo para primeros cursos de robótica', 'Ref. Description Ref. Description\r\nX1 Power jack 2.1x5.5mm U1 SPX1117M3-L-5 Regulator\r\nX2 USB B Connector U3 ATMEGA16U2 Module\r\nPC1 EEE-1EA470WP 25V SMD Capacitor U5 LMV358LIST-A.9 IC\r\nPC2 EEE-1EA470WP 25V SMD Capacitor F1 Chip Capacitor, High Density\r\nD1 CGRA4007-G Rectifier ICSP Pin header connector (through hole 6)\r\nJ-ZU4 ATMEGA328P Module ICSP1 Pin header connector (through hole 6)\r\nY1 ECS-160-20-4X-DU Oscillator', 'elementos/img_69b723d65120c9.76067405.jpg', NULL, NULL, '2.8300', '100.000', '12.00', '6.00', '1.80', 'unidad', 0, 1, '0.0000', 49, 5, 100, 'Bodega principal', '0.00', '27000.00', 1, 1, '2026-03-12 15:58:28', '2026-03-15 16:25:42'),
(2, 1, 3, 'RS-ARD-002', 'UNO R3 +USB Cable ATMEGa 328', 'Arduino Uno con chip AT Mega de', 'Basado en el microcontrolador ATMEGA328\r\nRango de alimentación recomendado: 7 a 12 volts\r\nRango de alimentación absoluto: 6 a 20 volts\r\n14 entradas/salidas digitales\r\n6 canales de PWM\r\n6 entradas analógicas\r\nCorriente máxima de salida en pines de IO: 20 mA\r\nCorriente de salida en el pin de 3.3 volts: 50 mA\r\n32 KB de memoria Flash para programas\r\n2 KB de memoria RAM\r\n1 KB de EEPROM\r\nFrecuencia de reloj de 16 Mhz', 'elementos/img_69b338e364be25.16113282.png', NULL, NULL, '4.2800', '100.000', '12.00', '6.00', '1.50', 'unidad', 0, 1, '0.0000', 15, 5, 100, 'Bodega principal', '0.00', '40000.00', 1, 1, '2026-03-12 17:06:27', '2026-03-15 18:46:49');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `escuela_cursos`
--

CREATE TABLE `escuela_cursos` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'URL amigable',
  `categoria` enum('robotica','programacion','maker','videojuegos','impresion3d','electronica','otro') COLLATE utf8mb4_unicode_ci DEFAULT 'robotica',
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `objetivos` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'JSON array de objetivos',
  `tematicas` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'JSON array de temas',
  `imagen` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `banner_ia` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'HTML del banner generado por IA',
  `color_primario` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#185FA5',
  `color_secundario` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#0f4c81',
  `edad_min` tinyint(4) DEFAULT 6,
  `edad_max` tinyint(4) DEFAULT 17,
  `nivel` enum('inicial','basico','intermedio','avanzado') COLLATE utf8mb4_unicode_ci DEFAULT 'basico',
  `duracion_min` smallint(6) DEFAULT 120 COMMENT 'Duracion en minutos por sesion',
  `num_sesiones` tinyint(4) DEFAULT 16 COMMENT 'Total de sesiones del curso',
  `cupo_max` tinyint(3) UNSIGNED NOT NULL DEFAULT 10,
  `precio` decimal(12,0) DEFAULT 0,
  `precio_semestral` decimal(12,0) DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `destacado` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `escuela_cursos`
--

INSERT INTO `escuela_cursos` (`id`, `nombre`, `slug`, `categoria`, `descripcion`, `objetivos`, `tematicas`, `imagen`, `banner_ia`, `color_primario`, `color_secundario`, `edad_min`, `edad_max`, `nivel`, `duracion_min`, `num_sesiones`, `cupo_max`, `precio`, `precio_semestral`, `activo`, `destacado`, `created_at`, `updated_at`) VALUES
(1, 'Robotica con Lego Spike', NULL, 'robotica', 'Aprende a construir y programar robots con Lego Spike Prime de forma divertida', '[\"Pensamiento computacional\",\"Construccion de robots\",\"Programacion por bloques\",\"Trabajo en equipo\"]', '[\"Introduccion a la robotica\",\"Sensores y motores\",\"Programacion Spike\",\"Desafios y competencias\"]', NULL, NULL, '#E3A600', '#0f4c81', 6, 12, 'basico', 120, 16, 10, '200000', '0', 1, 1, '2026-03-14 17:34:54', '2026-03-14 17:34:54'),
(2, 'Programacion Python', NULL, 'programacion', 'Introduccion al lenguaje de programacion Python para jovenes', '[\"Logica de programacion\",\"Variables y funciones\",\"Algoritmos basicos\",\"Proyectos reales\"]', '[\"Fundamentos Python\",\"Condiciones y ciclos\",\"Funciones\",\"Juegos con Python\"]', NULL, NULL, '#3776AB', '#0f4c81', 10, 17, 'intermedio', 120, 16, 10, '220000', '0', 1, 1, '2026-03-14 17:34:54', '2026-03-14 17:34:54'),
(3, 'Minecraft Education', NULL, 'videojuegos', 'Aprende matematicas, ciencias y programacion dentro del universo Minecraft', '[\"Creatividad digital\",\"Logica espacial\",\"Introduccion al codigo\",\"Proyectos STEAM\"]', '[\"Construccion y diseño\",\"Redstone basico\",\"Code Builder\",\"Proyectos colaborativos\"]', NULL, NULL, '#62B53E', '#0f4c81', 7, 14, 'basico', 120, 16, 10, '180000', '0', 1, 0, '2026-03-14 17:34:54', '2026-03-14 17:34:54'),
(4, 'Roblox Studio', NULL, 'videojuegos', 'Crea tus propios juegos en Roblox Studio y aprende Lua', '[\"Diseño de videojuegos\",\"Programacion Lua\",\"Modelado 3D basico\",\"Publicacion de juegos\"]', '[\"Interfaz Roblox Studio\",\"Scripts basicos\",\"Fisica y colisiones\",\"Tu primer juego\"]', NULL, NULL, '#E53935', '#0f4c81', 10, 17, 'intermedio', 120, 16, 10, '220000', '0', 1, 0, '2026-03-14 17:34:54', '2026-03-14 17:34:54'),
(5, 'Impresion 3D y Diseño', NULL, 'impresion3d', 'Diseña y materializa tus ideas con impresoras 3D y software de diseño', '[\"Diseño 3D\",\"Modelado digital\",\"Fabricacion digital\",\"Creatividad tecnica\"]', '[\"Introduccion a TinkerCAD\",\"Diseño de piezas\",\"Preparacion de archivos\",\"Impresion y acabados\"]', NULL, NULL, '#FF6F00', '#0f4c81', 9, 17, 'basico', 120, 12, 10, '200000', '0', 1, 0, '2026-03-14 17:34:54', '2026-03-14 17:34:54'),
(6, 'Electronica y Arduino', NULL, 'electronica', 'Aprende electronica basica y programacion de microcontroladores Arduino', '[\"Circuitos basicos\",\"Programacion Arduino\",\"Sensores y actuadores\",\"Proyectos IoT\"]', '[\"Electronica basica\",\"Arduino UNO\",\"Sensores\",\"Proyecto final\"]', NULL, NULL, '#00979D', '#0f4c81', 11, 17, 'intermedio', 120, 16, 10, '220000', '0', 1, 0, '2026-03-14 17:34:54', '2026-03-14 17:34:54'),
(7, 'Robotica con Lego Spike', NULL, 'robotica', 'Aprende a construir y programar robots con Lego Spike Prime de forma divertida', '[\"Pensamiento computacional\",\"Construccion de robots\",\"Programacion por bloques\",\"Trabajo en equipo\"]', '[\"Introduccion a la robotica\",\"Sensores y motores\",\"Programacion Spike\",\"Desafios y competencias\"]', NULL, NULL, '#E3A600', '#0f4c81', 6, 12, 'basico', 120, 16, 10, '200000', '0', 1, 1, '2026-03-14 18:00:15', '2026-03-14 18:00:15'),
(8, 'Programacion Python', NULL, 'programacion', 'Introduccion al lenguaje de programacion Python para jovenes', '[\"Logica de programacion\",\"Variables y funciones\",\"Algoritmos basicos\",\"Proyectos reales\"]', '[\"Fundamentos Python\",\"Condiciones y ciclos\",\"Funciones\",\"Juegos con Python\"]', NULL, NULL, '#3776AB', '#0f4c81', 10, 17, 'intermedio', 120, 16, 10, '220000', '0', 1, 1, '2026-03-14 18:00:15', '2026-03-14 18:00:15'),
(9, 'Minecraft Education', NULL, 'videojuegos', 'Aprende matematicas, ciencias y programacion dentro del universo Minecraft', '[\"Creatividad digital\",\"Logica espacial\",\"Introduccion al codigo\",\"Proyectos STEAM\"]', '[\"Construccion y diseño\",\"Redstone basico\",\"Code Builder\",\"Proyectos colaborativos\"]', NULL, NULL, '#62B53E', '#0f4c81', 7, 14, 'basico', 120, 16, 10, '180000', '0', 1, 0, '2026-03-14 18:00:15', '2026-03-14 18:00:15'),
(10, 'Roblox Studio', NULL, 'videojuegos', 'Crea tus propios juegos en Roblox Studio y aprende Lua', '[\"Diseño de videojuegos\",\"Programacion Lua\",\"Modelado 3D basico\",\"Publicacion de juegos\"]', '[\"Interfaz Roblox Studio\",\"Scripts basicos\",\"Fisica y colisiones\",\"Tu primer juego\"]', NULL, NULL, '#E53935', '#0f4c81', 10, 17, 'intermedio', 120, 16, 10, '220000', '0', 1, 0, '2026-03-14 18:00:15', '2026-03-14 18:00:15'),
(11, 'Impresion 3D y Diseño', NULL, 'impresion3d', 'Diseña y materializa tus ideas con impresoras 3D y software de diseño', '[\"Diseño 3D\",\"Modelado digital\",\"Fabricacion digital\",\"Creatividad tecnica\"]', '[\"Introduccion a TinkerCAD\",\"Diseño de piezas\",\"Preparacion de archivos\",\"Impresion y acabados\"]', NULL, NULL, '#FF6F00', '#0f4c81', 9, 17, 'basico', 120, 12, 10, '200000', '0', 1, 0, '2026-03-14 18:00:15', '2026-03-14 18:00:15'),
(12, 'Electronica y Arduino', NULL, 'electronica', 'Aprende electronica basica y programacion de microcontroladores Arduino', '[\"Circuitos basicos\",\"Programacion Arduino\",\"Sensores y actuadores\",\"Proyectos IoT\"]', '[\"Electronica basica\",\"Arduino UNO\",\"Sensores\",\"Proyecto final\"]', NULL, NULL, '#00979D', '#0f4c81', 11, 17, 'intermedio', 120, 16, 10, '220000', '0', 1, 0, '2026-03-14 18:00:15', '2026-03-14 18:00:15');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `escuela_grupos`
--

CREATE TABLE `escuela_grupos` (
  `id` int(10) UNSIGNED NOT NULL,
  `programa_id` int(10) UNSIGNED NOT NULL,
  `kit_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Kit del inventario que usan en este grupo',
  `elemento_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'O un elemento especifico (ej: Lego Spike)',
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sede` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sede_id` int(10) UNSIGNED DEFAULT NULL,
  `dia_semana` tinyint(4) DEFAULT 6 COMMENT '6=sabado',
  `hora_inicio` time DEFAULT '09:00:00',
  `hora_fin` time DEFAULT '11:00:00',
  `docente` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cupo_max` tinyint(4) DEFAULT 15,
  `periodo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '2025-1, 2025-2',
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `escuela_grupos`
--

INSERT INTO `escuela_grupos` (`id`, `programa_id`, `kit_id`, `elemento_id`, `nombre`, `sede`, `sede_id`, `dia_semana`, `hora_inicio`, `hora_fin`, `docente`, `cupo_max`, `periodo`, `fecha_inicio`, `fecha_fin`, `activo`, `created_at`) VALUES
(1, 3, NULL, NULL, 'Arduino', '', NULL, 7, '22:31:00', '12:30:00', '', 15, '2026-3', '2026-03-14', '2026-04-14', 1, '2026-03-14 20:25:34');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `escuela_horarios`
--

CREATE TABLE `escuela_horarios` (
  `id` int(10) UNSIGNED NOT NULL,
  `curso_id` int(10) UNSIGNED NOT NULL,
  `dia_semana` tinyint(4) NOT NULL DEFAULT 7 COMMENT '7=sabado',
  `hora_inicio` time NOT NULL DEFAULT '08:00:00',
  `hora_fin` time NOT NULL DEFAULT '10:00:00',
  `instructor` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sede` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sede_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Sede donde se dicta el horario',
  `periodo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '2025-1',
  `elemento_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Recurso del inventario (computadores, kits, etc)',
  `kit_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'O kit especifico',
  `cupo_max` tinyint(3) UNSIGNED NOT NULL DEFAULT 15 COMMENT 'Cupo maximo manual (puede diferir del stock si hay reservas)',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `escuela_modulos`
--

CREATE TABLE `escuela_modulos` (
  `id` int(10) UNSIGNED NOT NULL,
  `curso_id` int(10) UNSIGNED NOT NULL,
  `titulo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `orden` tinyint(4) NOT NULL DEFAULT 1,
  `sesiones` tinyint(4) DEFAULT 2 COMMENT 'Sesiones que toma este modulo',
  `icono` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'bi-bookmark',
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#185FA5',
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `escuela_programas`
--

CREATE TABLE `escuela_programas` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('semestral','libre','taller','vacacional') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'libre',
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nivel` enum('infantil','basico','intermedio','avanzado') COLLATE utf8mb4_unicode_ci DEFAULT 'basico',
  `edad_min` tinyint(4) DEFAULT NULL,
  `edad_max` tinyint(4) DEFAULT NULL,
  `duracion_semanas` smallint(6) DEFAULT NULL,
  `sesiones_semana` tinyint(4) DEFAULT 1,
  `valor_matricula` decimal(12,0) DEFAULT 0,
  `valor_mensualidad` decimal(12,0) DEFAULT 0,
  `valor_semestral` decimal(12,0) DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `escuela_programas`
--

INSERT INTO `escuela_programas` (`id`, `nombre`, `tipo`, `descripcion`, `nivel`, `edad_min`, `edad_max`, `duracion_semanas`, `sesiones_semana`, `valor_matricula`, `valor_mensualidad`, `valor_semestral`, `activo`, `created_at`) VALUES
(1, 'Robotica Basica Sabados', 'libre', NULL, 'basico', 7, 10, NULL, 1, '0', '200000', '0', 1, '2026-03-14 12:49:32'),
(2, 'Robotica Intermedia', 'libre', NULL, 'intermedio', 10, 14, NULL, 1, '0', '220000', '0', 1, '2026-03-14 12:49:32'),
(3, 'Robotica Avanzada', 'libre', NULL, 'avanzado', 12, 17, NULL, 1, '0', '250000', '0', 1, '2026-03-14 12:49:32'),
(4, 'Semestral Primer Semestre', 'semestral', NULL, 'basico', 7, 17, NULL, 1, '0', '0', '0', 1, '2026-03-14 12:49:32'),
(5, 'Taller Vacacional', 'vacacional', NULL, 'basico', 6, 14, NULL, 5, '0', '180000', '0', 1, '2026-03-14 12:49:32');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estudiantes`
--

CREATE TABLE `estudiantes` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombres` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellidos` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `documento` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_doc` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'TI',
  `fecha_nac` date DEFAULT NULL,
  `genero` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rh` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `eps` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `num_seguro` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alergias` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `condicion_medica` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `edad` tinyint(4) GENERATED ALWAYS AS (timestampdiff(YEAR,`fecha_nac`,curdate())) VIRTUAL,
  `acudiente` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parentesco` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `doc_acudiente` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono2` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `barrio` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ciudad` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'Bogota',
  `colegio_ext` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Colegio donde estudia',
  `grado_ext` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jornada_ext` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `como_conocio` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `autorizacion_foto` tinyint(1) DEFAULT 0,
  `autorizacion_datos` tinyint(1) DEFAULT 1,
  `notas` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar_color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#185FA5',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `estudiantes`
--

INSERT INTO `estudiantes` (`id`, `nombres`, `apellidos`, `documento`, `tipo_doc`, `fecha_nac`, `genero`, `rh`, `eps`, `num_seguro`, `alergias`, `condicion_medica`, `acudiente`, `parentesco`, `doc_acudiente`, `telefono`, `telefono2`, `email`, `direccion`, `barrio`, `ciudad`, `colegio_ext`, `grado_ext`, `jornada_ext`, `como_conocio`, `autorizacion_foto`, `autorizacion_datos`, `notas`, `foto`, `avatar_color`, `activo`, `created_at`) VALUES
(1, 'Juanita Maria', 'Puchana Borda', '1011345678', 'TI', '2011-05-06', 'F', 'O+', 'compensar', '232323232', 'Ninguna', 'Ninguna', 'Claudia Liliana Borda Rodríguez', 'Madre', '52214647', '3183403773', '3186541859', 'robotschoolcol@gmail.com', 'CALLE 75 No. 20B 62', 'San Felipe', 'Bogota', 'Colegio Bethlemitas chapinero', '9', 'manana', 'Facebook', 1, 1, 'Ninguna', NULL, '#185FA5', 1, '2026-03-14 20:02:48');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `kits`
--

CREATE TABLE `kits` (
  `id` int(10) UNSIGNED NOT NULL,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'KIT-001',
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('generico','colegio','proyecto') COLLATE utf8mb4_unicode_ci DEFAULT 'generico',
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nivel` enum('basico','intermedio','avanzado') COLLATE utf8mb4_unicode_ci DEFAULT 'basico',
  `incluye_prototipo` tinyint(1) DEFAULT 0,
  `colegio_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'NULL = kit genérico',
  `tipo_caja_id` smallint(5) UNSIGNED DEFAULT NULL,
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `costo_cop` decimal(12,2) DEFAULT 0.00 COMMENT 'Calculado automáticamente',
  `precio_cop` decimal(12,2) DEFAULT 0.00,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `kits`
--

INSERT INTO `kits` (`id`, `codigo`, `nombre`, `tipo`, `descripcion`, `nivel`, `incluye_prototipo`, `colegio_id`, `tipo_caja_id`, `foto`, `costo_cop`, `precio_cop`, `activo`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'KIT-001', 'Kit Junior Ciencia y tecnologia', 'proyecto', 'Kit Junior con diferentes proyectos mecánica y mecanismos Kuntur', 'basico', 1, NULL, 2, NULL, '0.00', '130000.00', 1, 1, '2026-03-12 15:41:44', '2026-03-12 15:41:44'),
(2, 'KIT-002', 'Kit KIDS', 'generico', 'dsads', 'basico', 0, 1, NULL, NULL, '0.00', '0.00', 0, 1, '2026-03-12 21:19:31', '2026-03-14 11:53:57'),
(3, 'KIT-003', 'Kit explorer Bandeja', 'generico', 'Kit de exploración Arduino, para iniciar en el tema de robotica, Ultrasonido, servomotor', 'basico', 0, 1, 1, 'kits/img_69b37b8f71d502.58676925.png', '0.00', '150000.00', 1, 1, '2026-03-12 21:50:55', '2026-03-12 21:50:55'),
(4, 'KIT-004', 'robotica basica borrar', 'colegio', 'Kit de robotica sin componentes', 'basico', 0, NULL, 4, NULL, '0.00', '0.00', 0, 1, '2026-03-14 00:47:21', '2026-03-14 11:54:10'),
(5, 'KIT-005', 'robotica basica 2', 'colegio', '', 'basico', 0, NULL, NULL, NULL, '0.00', '0.00', 0, 1, '2026-03-14 07:04:13', '2026-03-14 11:53:18'),
(6, 'KIT-006', 'kit 1', 'colegio', '', 'basico', 0, NULL, NULL, NULL, '0.00', '0.00', 0, 1, '2026-03-14 09:05:37', '2026-03-14 11:54:06'),
(7, 'KIT-007', 'robotica basica borra2', 'colegio', '', 'basico', 0, NULL, NULL, NULL, '0.00', '0.00', 0, 1, '2026-03-14 09:39:56', '2026-03-14 11:54:14'),
(8, 'KIT-008', 'robot', 'colegio', '', 'basico', 0, 1, 9, NULL, '0.00', '0.00', 0, 1, '2026-03-15 20:14:22', '2026-03-15 20:26:56');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `kit_elementos`
--

CREATE TABLE `kit_elementos` (
  `id` int(10) UNSIGNED NOT NULL,
  `kit_id` int(10) UNSIGNED NOT NULL,
  `elemento_id` int(10) UNSIGNED NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `kit_prototipos`
--

CREATE TABLE `kit_prototipos` (
  `id` int(10) UNSIGNED NOT NULL,
  `kit_id` int(10) UNSIGNED NOT NULL,
  `prototipo_id` int(10) UNSIGNED NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `notas` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `maquinas_produccion`
--

CREATE TABLE `maquinas_produccion` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('laser','impresora3d','cortadora','ensamble','otro') COLLATE utf8mb4_unicode_ci DEFAULT 'laser',
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#185FA5',
  `activa` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `maquinas_produccion`
--

INSERT INTO `maquinas_produccion` (`id`, `nombre`, `tipo`, `descripcion`, `color`, `activa`) VALUES
(1, 'Laser 1', 'laser', NULL, '#e53935', 1),
(2, 'Laser 2', 'laser', NULL, '#7c3aed', 1),
(3, 'Laser 3', 'laser', NULL, '#0891b2', 1),
(4, 'Laser 1', 'laser', NULL, '#e53935', 1),
(5, 'Laser 2', 'laser', NULL, '#7c3aed', 1),
(6, 'Laser 3', 'laser', NULL, '#0891b2', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `matriculas`
--

CREATE TABLE `matriculas` (
  `id` int(10) UNSIGNED NOT NULL,
  `estudiante_id` int(10) UNSIGNED NOT NULL,
  `grupo_id` int(10) UNSIGNED NOT NULL,
  `estado` enum('activa','pausada','retirada','completada','pendiente_pago') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente_pago',
  `fecha_matricula` date NOT NULL DEFAULT curdate(),
  `fecha_retiro` date DEFAULT NULL,
  `descuento_pct` tinyint(4) DEFAULT 0,
  `notas` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `matriculas`
--

INSERT INTO `matriculas` (`id`, `estudiante_id`, `grupo_id`, `estado`, `fecha_matricula`, `fecha_retiro`, `descuento_pct`, `notas`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'pendiente_pago', '2026-03-14', NULL, 0, NULL, 1, '2026-03-14 20:31:19', '2026-03-14 20:31:19');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos`
--

CREATE TABLE `movimientos` (
  `id` int(10) UNSIGNED NOT NULL,
  `elemento_id` int(10) UNSIGNED NOT NULL,
  `tipo` enum('entrada','salida','ajuste','devolucion','transferencia') COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad` int(11) NOT NULL COMMENT 'Positivo=entrada, negativo=salida',
  `stock_antes` int(11) NOT NULL,
  `stock_despues` int(11) NOT NULL,
  `referencia` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Número pedido, kit, colegio, etc.',
  `motivo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `costo_unit_cop` decimal(12,4) DEFAULT NULL,
  `pedido_id` int(10) UNSIGNED DEFAULT NULL,
  `kit_id` int(10) UNSIGNED DEFAULT NULL,
  `usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `movimientos`
--

INSERT INTO `movimientos` (`id`, `elemento_id`, `tipo`, `cantidad`, `stock_antes`, `stock_despues`, `referencia`, `motivo`, `costo_unit_cop`, `pedido_id`, `kit_id`, `usuario_id`, `created_at`) VALUES
(2, 2, 'entrada', 50, 400, 450, '233232', 'Compra pe', NULL, NULL, NULL, 1, '2026-03-12 21:14:00'),
(3, 2, 'salida', -35, 450, 415, 'Kits maximino', 'venta', NULL, NULL, NULL, 1, '2026-03-12 21:57:40'),
(4, 1, 'entrada', 49, 0, 49, 'arduinos', 'Compra pedido casa de la banda', NULL, NULL, NULL, 1, '2026-03-12 21:58:32');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id` int(10) UNSIGNED NOT NULL,
  `usuario_id` int(10) UNSIGNED NOT NULL COMMENT 'Destinatario',
  `tipo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'solicitud_produccion, pedido_listo, etc.',
  `titulo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensaje` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Link a dónde ir',
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos`
--

CREATE TABLE `pagos` (
  `id` int(10) UNSIGNED NOT NULL,
  `matricula_id` int(10) UNSIGNED NOT NULL,
  `tipo` enum('matricula','mensualidad','semestral','material','otro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mensualidad',
  `concepto` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor` decimal(12,0) NOT NULL DEFAULT 0,
  `descuento` decimal(12,0) NOT NULL DEFAULT 0,
  `valor_pagado` decimal(12,0) NOT NULL DEFAULT 0,
  `medio_pago` enum('efectivo','transferencia','nequi','daviplata','tarjeta','otro') COLLATE utf8mb4_unicode_ci DEFAULT 'efectivo',
  `referencia` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Numero de transferencia',
  `fecha_pago` date NOT NULL DEFAULT curdate(),
  `sabado_ref` date DEFAULT NULL COMMENT 'Sabado al que corresponde',
  `estado` enum('pagado','pendiente','parcial','anulado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `comprobante` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Foto del comprobante',
  `registrado_por` int(10) UNSIGNED DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedidos_importacion`
--

CREATE TABLE `pedidos_importacion` (
  `id` int(10) UNSIGNED NOT NULL,
  `codigo_pedido` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'PED-2024-001',
  `proveedor_id` int(10) UNSIGNED DEFAULT NULL,
  `fecha_pedido` date NOT NULL,
  `fecha_envio` date DEFAULT NULL,
  `fecha_llegada` date DEFAULT NULL,
  `numero_tracking_dhl` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `peso_total_kg` decimal(8,3) NOT NULL DEFAULT 0.000 COMMENT 'Peso real del paquete en kg',
  `peso_volumetrico_kg` decimal(8,3) DEFAULT NULL COMMENT '(L×A×H)/5000',
  `peso_cobrado_kg` decimal(8,3) DEFAULT NULL COMMENT 'MAX(real, volumétrico)',
  `costo_dhl_usd` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tasa_cambio_usd_cop` decimal(10,2) NOT NULL DEFAULT 4200.00 COMMENT 'TRM del día',
  `valor_fob_usd` decimal(12,2) DEFAULT 0.00 COMMENT 'Valor total FOB declarado',
  `valor_seguro_usd` decimal(10,2) DEFAULT 0.00,
  `arancel_pct` decimal(5,2) DEFAULT 0.00 COMMENT '% arancel según partida arancelaria',
  `iva_pct` decimal(5,2) DEFAULT 19.00 COMMENT 'IVA Colombia 19%',
  `otros_impuestos_cop` decimal(12,2) DEFAULT 0.00 COMMENT 'Gastos bancarios, agencia aduanera',
  `total_cif_usd` decimal(12,2) DEFAULT 0.00 COMMENT 'FOB + flete + seguro',
  `total_arancel_cop` decimal(12,2) DEFAULT 0.00,
  `total_iva_cop` decimal(12,2) DEFAULT 0.00,
  `total_dhl_cop` decimal(12,2) DEFAULT 0.00,
  `costo_total_cop` decimal(14,2) DEFAULT 0.00 COMMENT 'Todo incluido en COP',
  `estado` enum('borrador','en_transito','en_aduana','recibido','liquidado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'borrador',
  `notas` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `liquidado_por` int(10) UNSIGNED DEFAULT NULL,
  `liquidado_at` datetime DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `pedidos_importacion`
--

INSERT INTO `pedidos_importacion` (`id`, `codigo_pedido`, `proveedor_id`, `fecha_pedido`, `fecha_envio`, `fecha_llegada`, `numero_tracking_dhl`, `peso_total_kg`, `peso_volumetrico_kg`, `peso_cobrado_kg`, `costo_dhl_usd`, `tasa_cambio_usd_cop`, `valor_fob_usd`, `valor_seguro_usd`, `arancel_pct`, `iva_pct`, `otros_impuestos_cop`, `total_cif_usd`, `total_arancel_cop`, `total_iva_cop`, `total_dhl_cop`, `costo_total_cop`, `estado`, `notas`, `liquidado_por`, `liquidado_at`, `created_by`, `created_at`) VALUES
(2, 'PED-2026-001', NULL, '2026-03-12', NULL, NULL, '', '0.000', NULL, NULL, '0.00', '4200.00', '0.00', '0.00', '5.00', '19.00', '0.00', '0.00', '0.00', '0.00', '0.00', '0.00', 'borrador', '', NULL, NULL, 1, '2026-03-12 21:15:57');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pedido_items`
--

CREATE TABLE `pedido_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `pedido_id` int(10) UNSIGNED NOT NULL,
  `elemento_id` int(10) UNSIGNED NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `precio_unit_usd` decimal(10,4) NOT NULL DEFAULT 0.0000,
  `peso_unit_gramos` decimal(10,3) NOT NULL DEFAULT 0.000,
  `peso_total_gramos` decimal(12,3) GENERATED ALWAYS AS (`cantidad` * `peso_unit_gramos`) STORED,
  `pct_peso` decimal(8,6) DEFAULT 0.000000 COMMENT 'Proporción de peso este item / peso total',
  `flete_asignado_cop` decimal(12,2) DEFAULT 0.00 COMMENT 'Flete DHL asignado proporcional',
  `arancel_asignado_cop` decimal(12,2) DEFAULT 0.00,
  `iva_asignado_cop` decimal(12,2) DEFAULT 0.00,
  `costo_unit_final_cop` decimal(12,4) DEFAULT 0.0000 COMMENT 'Costo real unitario liquidado',
  `subtotal_usd` decimal(12,4) GENERATED ALWAYS AS (`cantidad` * `precio_unit_usd`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `produccion_cronograma`
--

CREATE TABLE `produccion_cronograma` (
  `id` int(10) UNSIGNED NOT NULL,
  `solicitud_id` int(10) UNSIGNED NOT NULL,
  `maquina_id` int(10) UNSIGNED NOT NULL,
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime NOT NULL,
  `horas_estimadas` decimal(5,1) DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prototipos`
--

CREATE TABLE `prototipos` (
  `id` int(10) UNSIGNED NOT NULL,
  `codigo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'RS-PRO-001',
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo_fabricacion` set('laser','impresion_3d','manual','electronica','mixto') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'laser',
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `archivo_diseno` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'SVG, DXF, STL, etc.',
  `material_principal` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MDF 3mm, PLA, Acrílico, etc.',
  `color_material` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `grosor_mm` decimal(5,2) DEFAULT NULL,
  `peso_gramos` decimal(10,3) DEFAULT NULL,
  `tiempo_laser_min` smallint(5) UNSIGNED DEFAULT 0 COMMENT 'Minutos en cortadora láser',
  `tiempo_3d_min` smallint(5) UNSIGNED DEFAULT 0 COMMENT 'Minutos en impresora 3D',
  `tiempo_ensamble_min` smallint(5) UNSIGNED DEFAULT 0 COMMENT 'Minutos de ensamble manual',
  `costo_material_cop` decimal(12,2) DEFAULT 0.00,
  `costo_maquina_cop` decimal(12,2) DEFAULT 0.00 COMMENT 'Costo hora máquina × tiempo',
  `costo_mano_obra_cop` decimal(12,2) DEFAULT 0.00,
  `costo_total_cop` decimal(12,2) GENERATED ALWAYS AS (`costo_material_cop` + `costo_maquina_cop` + `costo_mano_obra_cop`) STORED,
  `version` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'v1.0',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `prototipos`
--

INSERT INTO `prototipos` (`id`, `codigo`, `nombre`, `descripcion`, `tipo_fabricacion`, `foto`, `foto2`, `archivo_diseno`, `material_principal`, `color_material`, `grosor_mm`, `peso_gramos`, `tiempo_laser_min`, `tiempo_3d_min`, `tiempo_ensamble_min`, `costo_material_cop`, `costo_maquina_cop`, `costo_mano_obra_cop`, `version`, `activo`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'RS-PRO-001', 'Proyecto Tuku bluetooth', 'Proyecto Tuyku en MDF con placa de desarrollo Arduino y módulo bluetooth HC-06', 'laser,impresion_3d,manual,electronica,mixto', 'prototipos/img_69b3728155be98.91909856.jpg', NULL, NULL, 'MDF 2.5', 'Madera', '2.50', '200.000', 9, 1, 10, '1500.00', '700.00', '7800.00', 'v1.0', 0, 1, '2026-03-12 21:12:17', '2026-03-13 18:13:07'),
(2, 'RS-PRO-002', 'Explorer en tablero', 'Exploración kit de inicio robótica ardiuno', 'laser,manual,electronica', NULL, NULL, NULL, '', '', NULL, NULL, 0, 0, 0, '0.00', '0.00', '0.00', 'v1.0', 0, 1, '2026-03-12 21:52:26', '2026-03-13 18:13:13');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `prototipos_secuencia`
--

CREATE TABLE `prototipos_secuencia` (
  `ultimo_numero` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `prototipos_secuencia`
--

INSERT INTO `prototipos_secuencia` (`ultimo_numero`) VALUES
(2),
(2),
(2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores`
--

CREATE TABLE `proveedores` (
  `id` int(10) UNSIGNED NOT NULL,
  `codigo` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'RS-PROV-001',
  `nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_comercial` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Razón social o nombre de tienda',
  `tipo` enum('electronica_china','electronica_colombia','cajas_empaque','stickers_impresion','libros_material','fabricacion_materiales','transporte','otro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'electronica_china',
  `pais` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'China',
  `ciudad` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contacto_nombre` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contacto_cargo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_tienda` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_catalogo` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'PDF o link al catálogo',
  `nit_rut` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tiempo_entrega_dias` smallint(5) UNSIGNED DEFAULT NULL COMMENT 'Días promedio de entrega',
  `moneda` enum('COP','USD','CNY','EUR') COLLATE utf8mb4_unicode_ci DEFAULT 'USD',
  `minimo_pedido` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Descripción del mínimo (ej: $50 USD, 10 unidades)',
  `descuento_habitual_pct` decimal(5,2) DEFAULT 0.00,
  `calificacion` tinyint(3) UNSIGNED DEFAULT 3 COMMENT '1-5 estrellas',
  `metodo_pago` set('transferencia','paypal','tarjeta','efectivo','credito','ali_escrow') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `requiere_dhl` tinyint(1) DEFAULT 0,
  `codigo_proveedor_dhl` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Account number DHL si aplica',
  `puerto_origen` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Ej: Shenzhen, Guangzhou',
  `incoterm` enum('EXW','FOB','CIF','DDP','DAP') COLLATE utf8mb4_unicode_ci DEFAULT 'EXW',
  `categorias_producto` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Descripción de qué vende',
  `foto` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Logo del proveedor',
  `notas` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `es_preferido` tinyint(1) DEFAULT 0 COMMENT 'Proveedor principal para este tipo',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `proveedores`
--

INSERT INTO `proveedores` (`id`, `codigo`, `nombre`, `nombre_comercial`, `tipo`, `pais`, `ciudad`, `contacto_nombre`, `contacto_cargo`, `email`, `telefono`, `whatsapp`, `url_tienda`, `url_catalogo`, `nit_rut`, `tiempo_entrega_dias`, `moneda`, `minimo_pedido`, `descuento_habitual_pct`, `calificacion`, `metodo_pago`, `requiere_dhl`, `codigo_proveedor_dhl`, `puerto_origen`, `incoterm`, `categorias_producto`, `foto`, `notas`, `activo`, `es_preferido`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'RS-PROV-001', 'AliExpress', NULL, 'electronica_china', 'China', NULL, NULL, NULL, NULL, NULL, NULL, 'https://aliexpress.com', NULL, NULL, NULL, 'USD', NULL, '0.00', 3, NULL, 0, NULL, NULL, 'EXW', NULL, NULL, NULL, 1, 0, NULL, '2026-03-12 14:22:57', '2026-03-12 14:22:57'),
(2, 'RS-PROV-002', 'Alibaba', NULL, 'electronica_china', 'China', NULL, NULL, NULL, NULL, NULL, NULL, 'https://alibaba.com', NULL, NULL, NULL, 'USD', NULL, '0.00', 3, NULL, 0, NULL, NULL, 'EXW', NULL, NULL, NULL, 1, 0, NULL, '2026-03-12 14:22:57', '2026-03-12 14:22:57'),
(3, 'RS-PROV-003', 'LCSC Electronics', NULL, 'electronica_china', 'China', NULL, NULL, NULL, NULL, NULL, NULL, 'https://lcsc.com', NULL, NULL, NULL, 'USD', NULL, '0.00', 3, NULL, 0, NULL, NULL, 'EXW', NULL, NULL, NULL, 1, 0, NULL, '2026-03-12 14:22:57', '2026-03-12 14:22:57'),
(4, 'RS-PROV-004', 'Mouser Colombia', NULL, 'electronica_colombia', 'Colombia', NULL, NULL, NULL, NULL, NULL, NULL, 'https://mouser.com', NULL, NULL, NULL, 'USD', NULL, '0.00', 3, NULL, 0, NULL, NULL, 'EXW', NULL, NULL, NULL, 1, 0, NULL, '2026-03-12 14:22:57', '2026-03-12 14:22:57');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores_old`
--

CREATE TABLE `proveedores_old` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pais` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT 'China',
  `contacto` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url_tienda` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notas` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `proveedores_old`
--

INSERT INTO `proveedores_old` (`id`, `nombre`, `pais`, `contacto`, `email`, `telefono`, `url_tienda`, `notas`, `activo`, `created_at`) VALUES
(1, 'AliExpress', 'China', NULL, NULL, NULL, 'https://aliexpress.com', NULL, 1, '2026-03-12 18:19:55'),
(2, 'Alibaba', 'China', NULL, NULL, NULL, 'https://alibaba.com', NULL, 1, '2026-03-12 18:19:55'),
(3, 'LCSC Electronics', 'China', NULL, NULL, NULL, 'https://lcsc.com', NULL, 1, '2026-03-12 18:19:55'),
(4, 'Mouser Colombia', 'Colombia', NULL, NULL, NULL, 'https://mouser.com', NULL, 1, '2026-03-12 18:19:55');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores_secuencia`
--

CREATE TABLE `proveedores_secuencia` (
  `ultimo_numero` int(10) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `proveedores_secuencia`
--

INSERT INTO `proveedores_secuencia` (`ultimo_numero`) VALUES
(4);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedor_contactos`
--

CREATE TABLE `proveedor_contactos` (
  `id` int(10) UNSIGNED NOT NULL,
  `proveedor_id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cargo` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `whatsapp` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `es_principal` tinyint(1) DEFAULT 0,
  `notas` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `nombre` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `nombre`, `descripcion`) VALUES
(1, 'Administrador', 'Acceso total al sistema'),
(2, 'Operador', 'Gestión de inventario y pedidos'),
(3, 'Consulta', 'Solo lectura'),
(4, 'Tienda', 'Gestión de pedidos de tienda online, cambio de estados y solicitudes a producción'),
(5, 'Produccion', 'Recibe solicitudes de producción, gestiona armado de kits y actualiza avances'),
(6, 'Despachos', 'Gestiona despachos, guías de envío y confirmación de entregas'),
(7, 'Administrador', 'Acceso total al sistema'),
(8, 'Operador', 'Gestión de inventario y pedidos'),
(9, 'Consulta', 'Solo lectura'),
(10, 'Tienda', 'Gestion de pedidos de tienda online'),
(11, 'Produccion', 'Gestiona armado de kits y solicitudes'),
(12, 'Despachos', 'Gestiona despachos y confirmacion de entregas');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol_permisos`
--

CREATE TABLE `rol_permisos` (
  `rol_id` tinyint(3) UNSIGNED NOT NULL,
  `modulo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'pedidos_tienda, inventario, kits, etc.',
  `ver` tinyint(1) NOT NULL DEFAULT 0,
  `crear` tinyint(1) NOT NULL DEFAULT 0,
  `editar` tinyint(1) NOT NULL DEFAULT 0,
  `eliminar` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `rol_permisos`
--

INSERT INTO `rol_permisos` (`rol_id`, `modulo`, `ver`, `crear`, `editar`, `eliminar`) VALUES
(1, 'barcodes', 1, 1, 1, 1),
(1, 'colegios', 1, 1, 1, 1),
(1, 'dashboard', 1, 1, 1, 1),
(1, 'despachos', 1, 1, 1, 1),
(1, 'elementos', 1, 1, 1, 1),
(1, 'importaciones', 1, 1, 1, 1),
(1, 'inventario', 1, 1, 1, 1),
(1, 'kits', 1, 1, 1, 1),
(1, 'pedidos_tienda', 1, 1, 1, 1),
(1, 'produccion', 1, 1, 1, 1),
(1, 'reportes', 1, 1, 1, 1),
(1, 'usuarios', 1, 1, 1, 1),
(2, 'barcodes', 1, 1, 1, 0),
(2, 'colegios', 1, 1, 1, 0),
(2, 'dashboard', 1, 0, 0, 0),
(2, 'despachos', 1, 1, 1, 0),
(2, 'elementos', 1, 1, 1, 0),
(2, 'importaciones', 1, 1, 1, 0),
(2, 'inventario', 1, 1, 1, 0),
(2, 'kits', 1, 1, 1, 0),
(2, 'pedidos_tienda', 1, 0, 1, 0),
(2, 'produccion', 1, 1, 1, 0),
(2, 'reportes', 1, 0, 0, 0),
(2, 'usuarios', 0, 0, 0, 0),
(3, 'barcodes', 1, 0, 0, 0),
(3, 'colegios', 1, 0, 0, 0),
(3, 'dashboard', 1, 0, 0, 0),
(3, 'despachos', 1, 0, 0, 0),
(3, 'elementos', 1, 0, 0, 0),
(3, 'importaciones', 1, 0, 0, 0),
(3, 'inventario', 1, 0, 0, 0),
(3, 'kits', 1, 0, 0, 0),
(3, 'pedidos_tienda', 1, 0, 0, 0),
(3, 'produccion', 1, 0, 0, 0),
(3, 'reportes', 1, 0, 0, 0),
(3, 'usuarios', 0, 0, 0, 0),
(4, 'barcodes', 0, 0, 0, 0),
(4, 'colegios', 1, 0, 0, 0),
(4, 'dashboard', 1, 0, 0, 0),
(4, 'despachos', 1, 0, 0, 0),
(4, 'elementos', 1, 0, 0, 0),
(4, 'importaciones', 0, 0, 0, 0),
(4, 'inventario', 1, 0, 0, 0),
(4, 'kits', 1, 0, 0, 0),
(4, 'pedidos_tienda', 1, 1, 1, 0),
(4, 'produccion', 1, 1, 0, 0),
(4, 'reportes', 1, 0, 0, 0),
(4, 'usuarios', 0, 0, 0, 0),
(5, 'barcodes', 1, 1, 0, 0),
(5, 'colegios', 1, 0, 0, 0),
(5, 'dashboard', 1, 0, 0, 0),
(5, 'despachos', 1, 0, 0, 0),
(5, 'elementos', 1, 0, 1, 0),
(5, 'importaciones', 0, 0, 0, 0),
(5, 'inventario', 1, 1, 1, 0),
(5, 'kits', 1, 1, 1, 0),
(5, 'pedidos_tienda', 1, 0, 1, 0),
(5, 'produccion', 1, 1, 1, 0),
(5, 'reportes', 1, 0, 0, 0),
(5, 'usuarios', 0, 0, 0, 0),
(6, 'barcodes', 1, 1, 0, 0),
(6, 'colegios', 1, 0, 0, 0),
(6, 'dashboard', 1, 0, 0, 0),
(6, 'despachos', 1, 1, 1, 0),
(6, 'elementos', 1, 0, 0, 0),
(6, 'importaciones', 0, 0, 0, 0),
(6, 'inventario', 1, 0, 0, 0),
(6, 'kits', 1, 0, 0, 0),
(6, 'pedidos_tienda', 1, 0, 1, 0),
(6, 'produccion', 1, 0, 0, 0),
(6, 'reportes', 1, 0, 0, 0),
(6, 'usuarios', 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sedes`
--

CREATE TABLE `sedes` (
  `id` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ciudad` enum('bogota','cali','medellin','otro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bogota',
  `direccion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `responsable` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `color` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT '#185FA5',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `sedes`
--

INSERT INTO `sedes` (`id`, `nombre`, `ciudad`, `direccion`, `telefono`, `email`, `responsable`, `color`, `activo`, `created_at`) VALUES
(1, 'ROBOTSchool Bogota San Felipe', 'bogota', 'Calle 75 #20b-62, Bogota', NULL, NULL, NULL, '#185FA5', 1, '2026-03-14 18:40:46'),
(2, 'ROBOTSchool Bogota 134', 'bogota', 'Calle 136 No 16-20', NULL, NULL, NULL, '#16a34a', 1, '2026-03-14 18:40:46'),
(3, 'ROBOTSchool Cali', 'cali', 'Direccion Cali', NULL, NULL, NULL, '#dc2626', 1, '2026-03-14 18:40:46');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes_produccion`
--

CREATE TABLE `solicitudes_produccion` (
  `id` int(10) UNSIGNED NOT NULL,
  `fuente` enum('tienda','comercial','colegio','cliente','salon','interno') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tienda' COMMENT 'Origen de la solicitud',
  `pedido_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Pedido tienda (opcional)',
  `convenio_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Vinculo con convenio comercial',
  `colegio_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Colegio solicitante',
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Titulo descriptivo de la solicitud',
  `tipo` enum('armar_kit','verificar_stock','personalizar','urgente') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'armar_kit',
  `estado` enum('pendiente','en_proceso','listo','rechazado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `kit_nombre` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Kit a armar',
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `prioridad` tinyint(4) NOT NULL DEFAULT 2 COMMENT '1=alta 2=normal 3=baja',
  `notas` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Instrucciones para producción',
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `valor_total` decimal(14,0) DEFAULT 0,
  `notas_respuesta` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Respuesta de producción',
  `solicitado_por` int(10) UNSIGNED DEFAULT NULL,
  `asignado_a` int(10) UNSIGNED DEFAULT NULL COMMENT 'Operario de producción',
  `fecha_limite` date DEFAULT NULL,
  `completado_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `solicitudes_produccion`
--

INSERT INTO `solicitudes_produccion` (`id`, `fuente`, `pedido_id`, `convenio_id`, `colegio_id`, `titulo`, `tipo`, `estado`, `kit_nombre`, `cantidad`, `prioridad`, `notas`, `descripcion`, `valor_total`, `notas_respuesta`, `solicitado_por`, `asignado_a`, `fecha_limite`, `completado_at`, `created_at`, `updated_at`) VALUES
(2, 'tienda', 5, NULL, NULL, 'Pedido Tienda: Kit robótica Maximino 4° — Luisa Alexandra Arambula Sanchez', 'armar_kit', 'pendiente', 'Kit robótica Maximino 4°', 1, 3, 'Cliente: Luisa Alexandra Arambula Sanchez | Bogotá', NULL, '0', NULL, 1, NULL, NULL, NULL, '2026-03-15 20:00:52', '2026-03-15 20:00:52'),
(3, 'tienda', 6, NULL, NULL, 'Pedido Tienda: Kit complemento grado 5° — RICARDO ANDRES SARMIENTO NARANJO', 'armar_kit', 'pendiente', 'Kit complemento grado 5°', 1, 3, 'Cliente: RICARDO ANDRES SARMIENTO NARANJO | YOPAL', NULL, '0', NULL, 1, NULL, NULL, NULL, '2026-03-15 20:00:58', '2026-03-15 20:00:58'),
(4, 'comercial', NULL, 2, 1, 'Convenio: Colegio San Viator Bilingue internacional — Grado 1°', 'armar_kit', 'listo', 'Kit Junior Ciencia y tecnologia', 30, 1, 'Convenio CON-2026-002 aprobado por gerencia', NULL, '0', NULL, 1, NULL, '2026-03-15', '2026-03-15 20:21:00', '2026-03-15 20:04:09', '2026-03-15 20:21:00'),
(5, 'tienda', 7, NULL, NULL, 'Pedido Tienda: Kit de robótica grado 1° + libro — Yuleidy Carvajal', 'armar_kit', 'pendiente', 'Kit de robótica grado 1° + libro', 1, 3, 'Cliente: Yuleidy Carvajal | Bogotá', NULL, '0', NULL, 1, NULL, NULL, NULL, '2026-03-15 20:09:23', '2026-03-15 20:09:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitud_historial`
--

CREATE TABLE `solicitud_historial` (
  `id` int(10) UNSIGNED NOT NULL,
  `solicitud_id` int(10) UNSIGNED NOT NULL,
  `estado` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `comentario` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `solicitud_historial`
--

INSERT INTO `solicitud_historial` (`id`, `solicitud_id`, `estado`, `usuario_id`, `comentario`, `created_at`) VALUES
(2, 2, 'pendiente', 1, 'Enviado a producción desde módulo Tienda', '2026-03-15 20:00:52'),
(3, 3, 'pendiente', 1, 'Enviado a producción desde módulo Tienda', '2026-03-15 20:00:58'),
(6, 4, 'pendiente', 1, 'Generado automáticamente al aprobar convenio CON-2026-002', '2026-03-15 20:04:09'),
(7, 4, 'en_proceso', 1, NULL, '2026-03-15 20:09:06'),
(8, 5, 'pendiente', 1, 'Enviado a producción desde módulo Tienda', '2026-03-15 20:09:23'),
(9, 4, 'listo', 1, NULL, '2026-03-15 20:21:00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitud_items`
--

CREATE TABLE `solicitud_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `solicitud_id` int(10) UNSIGNED NOT NULL,
  `tipo_item` enum('kit','elemento','libro','material','otro') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'kit',
  `kit_id` int(10) UNSIGNED DEFAULT NULL,
  `elemento_id` int(10) UNSIGNED DEFAULT NULL,
  `nombre_item` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 1,
  `valor_unit` decimal(12,0) DEFAULT 0,
  `notas` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `listo` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tienda_pedidos`
--

CREATE TABLE `tienda_pedidos` (
  `id` int(10) UNSIGNED NOT NULL,
  `woo_order_id` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'ID del pedido en WooCommerce',
  `estado` enum('pendiente','en_produccion','listo_envio','despachado','entregado','cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `cliente_nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cliente_telefono` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cliente_email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ciudad` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `colegio_nombre` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `colegio_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK a colegios si se cruza',
  `kit_nombre` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `categoria` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_compra` date NOT NULL,
  `fecha_limite` date DEFAULT NULL,
  `fecha_despacho` date DEFAULT NULL,
  `fecha_entrega` date DEFAULT NULL,
  `guia_envio` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transportadora` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notas_internas` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `asignado_a` int(10) UNSIGNED DEFAULT NULL,
  `creado_desde_csv` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tienda_pedidos`
--

INSERT INTO `tienda_pedidos` (`id`, `woo_order_id`, `estado`, `cliente_nombre`, `cliente_telefono`, `cliente_email`, `direccion`, `ciudad`, `colegio_nombre`, `colegio_id`, `kit_nombre`, `categoria`, `fecha_compra`, `fecha_limite`, `fecha_despacho`, `fecha_entrega`, `guia_envio`, `transportadora`, `notas_internas`, `asignado_a`, `creado_desde_csv`, `created_at`, `updated_at`) VALUES
(1, '8110', 'pendiente', 'Angela Paola Chacón Torres', '3102975229', 'chaconangela1966@gmail.com', 'CALLE 24 SUR # 50A - 49, casa primer piso', 'Bogotá', 'Liceo Bogotá', NULL, 'Kit 8° Escorpión', 'Colegios - Kit´s>Liceo Bogotá', '2026-03-13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(2, '8109', 'pendiente', 'Wilson Munoz Camelo', '3214929785', 'wilmuca2@gmail.com', 'Carrera 81b nro 17 - 80, apartamento 404', 'Bogotá', 'Gimnasio Nuevo Modelia', NULL, 'Kit robótica grado 11° + complemento', 'Colegios - Kit´s>Gimnasio Nuevo Modelia', '2026-03-13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(3, '8108', 'pendiente', 'Luz Amanda Sánchez Carrillo', '3178913784', 'amandasanchezca@gmail.com', 'Calle 12# 7e-70 conjunto Lima, Torre 2 apto 502', 'Mosquera', 'Instituto Salesiano San José Mosquera', NULL, 'Kit robótica grado 5°', 'Colegios - Kit´s>Instituto Salesiano San José Mosquera', '2026-03-13', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(4, '8105', 'en_produccion', 'Nadia Páez', '3012510956', 'nadialuciapaezprieto@gmail.com', 'Carrera 101#150a60, Casa35', 'Bogotá', 'Colegio Maximino Poitiers', NULL, 'Kit robótica Maximino 9°', 'Colegios - Kit´s>Colegio Maximino Poitiers', '2026-03-12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-15 20:00:42'),
(5, '8104', 'en_produccion', 'Luisa Alexandra Arambula Sanchez', '3112321707', 'laas1593@gmail.com', 'Calle 148 #99 - 38, Torre 1 apto 202', 'Bogotá', 'Colegio Maximino Poitiers', NULL, 'Kit robótica Maximino 4°', 'Colegios - Kit´s>Colegio Maximino Poitiers', '2026-03-12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-15 20:00:52'),
(6, '8103', 'en_produccion', 'RICARDO ANDRES SARMIENTO NARANJO', '3208175130', 'Andrecitonaranjo@hotmail.com', 'carrera 20 #25-57', 'YOPAL', 'Colegio La Presentación Yopal', NULL, 'Kit complemento grado 5°', 'Colegios - Kit´s>Colegio La Presentación Yopal', '2026-03-12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-15 20:00:58'),
(7, '8102', 'en_produccion', 'Yuleidy Carvajal', '3227402462', 'yulcarvajalp@gmail.com', 'Av cra 68 5-75, 107 torre 2', 'Bogotá', 'Chenano', NULL, 'Kit de robótica grado 1° + libro', 'Colegios - Kit´s>Chenano', '2026-03-12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-15 20:09:23'),
(8, '8101', 'entregado', 'ANGELA FRANCO', '3133962084', 'angelajfrancor@gmail.com', 'calle 23 # 72A - 91, INTERIOR 1 APTO 603', 'Bogotá', 'Gimnasio Nuevo Modelia', NULL, 'Kit robótica grado 8° + complemento', 'Colegios - Kit´s>Gimnasio Nuevo Modelia', '2026-03-12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-14 09:37:30'),
(9, '8100', 'entregado', 'AMANDA PALACIOS SANCHEZ', '3144575212', 'amandapalacios099@gmail.com', 'AVENIDA CARRERA 68 # 5-17 CONJUNTO PORTO AMERICAS, TORRE 1 APARTAMENTO 1705', 'BOGOTÁ', 'Chenano', NULL, 'Kit de robótica grado 3° + libro', 'Colegios - Kit´s>Chenano', '2026-03-12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(10, '8098', 'entregado', 'Andrea felipe Gómez pulgarin', '3163248808', 'pipemalo270@gmail.com', 'Calle42b#84a-63, 302', 'Medellín', 'Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', NULL, 'Complemento robótico grado 7°', 'Colegios - Kit´s>Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', '2026-03-12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(11, '8097', 'entregado', 'Cindry tatiana Duque mazo', '3028038916', 'cindrytatiana@icloud.com', 'Calle 92 A # 66-4, Local comercial', 'Medellin', 'Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', NULL, 'Kit robótica grado 3°', 'Colegios - Kit´s>Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', '2026-03-12', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(12, '8093', 'entregado', 'Hector Peña', '3214924862', 'hector.00.com@hotmail.com', 'Cra 139#142c-3, Casa naranja piso 2', 'Bogotá', 'Colegio Maximino Poitiers', NULL, 'Kit robótica Maximino 8°', 'Colegios - Kit´s>Colegio Maximino Poitiers', '2026-03-11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(13, '8091', 'entregado', 'INGRID PAOLA LOPEZ CASTELLANOS', '3232877041', 'NINI_LAU18@HOTMAIL.COM', 'Carrera 8 n. 7 -09', 'Funza', 'Instituto Salesiano San José Mosquera', NULL, 'Kit robótica grado 8°', 'Colegios - Kit´s>Instituto Salesiano San José Mosquera', '2026-03-11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(14, '8090', 'entregado', 'JINA PAOLA ROMO DELGADO', '3002016395', 'gromod@hotmail.com', 'CARRERA 36 17 53, APARTAMENTO 803', 'PASTO', 'Colegio Sagrado Corazón de Jesús Bethlemitas Pasto', NULL, 'Kit complemento grado 2°', 'Colegios - Kit´s>Colegio Sagrado Corazón de Jesús Bethlemitas Pasto', '2026-03-11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(15, '8089', 'entregado', 'ANDREA RODRIGUEZ', '3209281145', 'andreitaj24@hotmail.com', 'AV CRA 50 No 1 96, casa taller de motos POWERBIKE SCOOTER', 'Bogota', 'Liceo Bogotá', NULL, 'Kit 3° Explorer', 'Colegios - Kit´s>Liceo Bogotá', '2026-03-11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(16, '8088', 'entregado', 'Alejandro gomez', '3002993364', 'farrasnorte@gmail.com', 'Cra 81 B N°7-19 Urbanizacion Reserva de Los Bernal Apto 101, apto 101', 'Medellin', 'Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', NULL, 'Complemento robótica grado 11°', 'Colegios - Kit´s>Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', '2026-03-11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(17, '8085', 'entregado', 'Luciana Ortiz', '3153002456', 'jortiz@stratop.com.co', 'Cr 53 27 19, Apto 301', 'Bello', 'Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', NULL, 'Complemento robótica grado 11°', 'Colegios - Kit´s>Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', '2026-03-11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(18, '8084', 'entregado', 'Edwin Ernesto Soler Triana', '3167876707', 'edsolwin@hotmail.com', 'Calle 10a#35a-18 sur, Casa Piso 1 Ciudad Montes', 'Bogotá', 'Liceo Bogotá', NULL, 'Kit 6° Ascensor', 'Colegios - Kit´s>Liceo Bogotá', '2026-03-11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(19, '8083', 'entregado', 'paula andrea berna lopez', '3102136854', 'hse.paula@gmail.com', 'Cl. 30 #28-46, altos de manare 1 T23 APT 401', 'Yopal', 'Colegio La Presentación Yopal', NULL, 'Kit complemento grado 5°', 'Colegios - Kit´s>Colegio La Presentación Yopal', '2026-03-11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(20, '8082', 'entregado', 'Sandra Arbelaez', '3103145606', 'sandyar98@gmail.com', 'Cr 102 154 30, Apartamento 108 Torre 4 Arboleda del Pinar II', 'Bogotá', 'Colegio Maximino Poitiers', NULL, 'Kit robótica Maximino 8°', 'Colegios - Kit´s>Colegio Maximino Poitiers', '2026-03-10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(21, '8080', 'entregado', 'Arianna Sanchez Goyeneche', '5,73185E+11', 'ari1409@gmail.com', 'Calle 3 sur #70-81, casa 41', '1127611474', 'Chenano', NULL, 'Kit de robótica grado 1° + libro', 'Colegios - Kit´s>Chenano', '2026-03-10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(22, '8078', 'entregado', 'Luis guillermo Hurtado', '3022511424', 'luishurtado.bsns@gmail.com', 'Calle127d#18-6, 301', 'Bogotá', 'Colegio Campestre San José', NULL, 'Kit de robótica grado 1°', 'Colegios - Kit´s>Colegio Campestre San José', '2026-03-10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(23, '8077', 'entregado', 'Yenith Paola Méndez Leal', '3208671035', 'yenithpaolamendezleal@gmail.com', 'Calle 3 # 31-04, 304', 'Bogotá', 'Liceo Bogotá', NULL, 'Kit 6° Ascensor', 'Colegios - Kit´s>Liceo Bogotá', '2026-03-10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(24, '8072', 'entregado', 'Lorena Orozco', '3245782528', 'lorenaorozcoproyectos@gmail.com', 'cra 77 33A 30.Laureles, Medellin, apto 204', 'Medellin', 'Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', NULL, 'Complemento robótico grado 3°', 'Colegios - Kit´s>Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', '2026-03-10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(25, '8068', 'entregado', 'Sonia Mayorga', '3167596470', 'smayorgag@yahoo.es', 'TRANSV. 94# 22 i 20, Torre. 1 APTO. 201', 'Bogotá', 'Gimnasio Nuevo Modelia', NULL, 'Kit complemento grado 7°', 'Colegios - Kit´s>Gimnasio Nuevo Modelia', '2026-03-10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(26, '8065', 'entregado', 'Luz Adriana Avellaneda Bolivar', '3016186797', 'Luzavellab@yahoo.es', 'Cra 75A # 20 60 Torre 18 Apto 506, Conjunto Residencial La Bahia / La Felicidad', 'BOGOTA', 'Gimnasio Nuevo Modelia', NULL, 'Kit complemento grado 8°', 'Colegios - Kit´s>Gimnasio Nuevo Modelia', '2026-03-10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(27, '8064', 'entregado', 'ROSA BRITO', '3014518829', 'rosabelandria16@hotmail.com', 'Cra 78a #6b - 28, int 17 apto 201', 'BOGOTA', 'Gimnasio Nuevo Modelia', NULL, 'Kit complemento grado 8°', 'Colegios - Kit´s>Gimnasio Nuevo Modelia', '2026-03-09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(28, '8063', 'entregado', 'Anibal Antonio Cardona Arango', '3127134355', 'animonin@gmail.com', 'Calle 5 # 76A-150, Urbina vitenza apto 801 torre 1', 'Medellin', 'Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', NULL, 'Complemento robótica grado 9°', 'Colegios - Kit´s>Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', '2026-03-09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(29, '8059', 'entregado', 'Sonia Andrea González', '3132021500', 'sonia.and09@gmail.com', 'Carrera 27 10-65, Local 121 centro comercial C-krea', 'Bogotá', 'Liceo Bogotá', NULL, 'Kit 6° Ascensor', 'Colegios - Kit´s>Liceo Bogotá', '2026-03-09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(30, '8048', 'entregado', 'Claudia Esperanza Fernandez Galvis', '3024135297', 'valeriassdd@gmail.com', 'Tv. 36 #Tr36 72-93, Tv. 36 #72-113, Laureles - Estadio, Medellín,', 'Medellin', 'Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', NULL, 'Kit robótica grado 11°', 'Colegios - Kit´s>Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', '2026-03-08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(31, '8047', 'entregado', 'Laura Alvarez', '3138519452', 'lauranata16@gmail.com', 'Calle 8A # 2-34, Quintas del trébol manzana 11, casa 9', 'Mosquera', 'Liceo Campestre Thomas De Iriarte', NULL, 'Kit Robótica grado 10°', 'Colegios - Kit´s>Liceo Campestre Thomas De Iriarte', '2026-03-08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(32, '8045', 'entregado', 'LINA VELEZ RUIZ', '3007471226', 'lina_lvr@hotmail.com', 'calle 44 A 79-143, edificio alcazar de san juan - apto 203', 'Medellín', 'Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', NULL, 'Complemento robótica grado 11°', 'Colegios - Kit´s>Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', '2026-03-07', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(33, '8030', 'entregado', 'Yury David', '3013887464', 'paulina.david@bethlemitas.edu.co', 'Cr45 #81-30, Local', 'MEDELLIN', 'Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', NULL, 'Complemento robótica grado 11°', 'Colegios - Kit´s>Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', '2026-03-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(34, '8023', 'entregado', 'Angela Paez', '3223102814', 'angelapaezdesuarez@gmail.com', 'Calle 152d#102b-10, Interior 11 apt 201', 'Bogotá', 'Colegio Maximino Poitiers', NULL, 'Kit robótica Maximino 6°', 'Colegios - Kit´s>Colegio Maximino Poitiers', '2026-03-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(35, '8016', 'entregado', 'Norman Beodya', '3155491630', 'builesadiela@gmail.com', 'Tv.36 #36 72-93, medellin, Colegio bethlemitas medellin', 'Medellin', 'Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', NULL, 'Complemento robótica grado 10°', 'Colegios - Kit´s>Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', '2026-03-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(36, '8005', 'entregado', 'LEO MAIRA BAUTISTA GIL', '3204110809', 'mairabautista2409@gmail.com', 'CALLE 34 # 19- 30, APTO 202', 'YOPAL', 'Colegio La Presentación Yopal', NULL, 'Kit robótica transición', 'Colegios - Kit´s>Colegio La Presentación Yopal', '2026-03-04', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(37, '8003', 'entregado', 'maria dolores castro pulgarin', '3117526133', 'mariadcastrop@gmail.com', 'cll 42 108a 215, apto 324 bloque 12', 'Medellín', 'Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', NULL, 'Complemento robótica grado 9°', 'Colegios - Kit´s>Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', '2026-03-04', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(38, '7997', 'entregado', 'Andrés Vélez', '3112548180', 'avelez380@gmail.com', 'Calle 34 c 88b 55 bloque 19, Apartamento 437', 'Medellin', 'Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', NULL, 'Complemento robótica grado 11°', 'Colegios - Kit´s>Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', '2026-03-03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(39, '7987', 'entregado', 'Andrea Betancur Restrepo', '3165174591', 'dcachealmacen@hotmail.com', 'Calle 42 #73-46, Apto 1201, edificio torres de laurelen 2', 'MEDELLIN', 'Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', NULL, 'Complemento robótica grado 11°', 'Colegios - Kit´s>Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', '2026-03-03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(40, '7984', 'entregado', 'Jenifer Gómez zuluaga', '3137342749', 'vivi_gomez1034@hotmail.com', 'Calle 15 #79-250 santa María de la Loma, Casa 101', 'Medellin', 'Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', NULL, 'Kit robótica grado 6°', 'Colegios - Kit´s>Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', '2026-03-03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(41, '7982', 'entregado', 'Sandra Gómez', '3102807573', 'sandravivi07@yahoo.es', 'Carrera 37 #1-18, Casa', 'Bogota', 'Liceo Bogotá', NULL, 'Kit 10° Tuku seguidor de linea', 'Colegios - Kit´s>Liceo Bogotá', '2026-03-03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(42, '7980', 'entregado', 'Mayra Daza', '3168279424', 'mayra-1007@hotmail.com', 'Calle 23 # 28-110 las mariselas', 'Yopal', 'Colegio La Presentación Yopal', NULL, 'Kit robótica grado 3°', 'Colegios - Kit´s>Colegio La Presentación Yopal', '2026-03-03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(43, '7979', 'entregado', 'Edis Naranjo', '3132775813', 'stephanie.naranjov@bethlemitas.edu.co', 'Tr36 #72-93, Colegio bethlemitas Medellín', 'Medellin', 'Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', NULL, 'Complemento robótica grado 11°', 'Colegios - Kit´s>Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', '2026-03-03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(44, '7975', 'entregado', 'Paola Arango', '33123753095', 'paolaas852@gmail.com', 'Calle 18sur #38a32, Apartamento 201', 'Bogotá', 'Liceo Bogotá', NULL, 'Kit 9° Casa domótica con ESP32', 'Colegios - Kit´s>Liceo Bogotá', '2026-03-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(45, '7974', 'entregado', 'Dolly Zuluaga', '3104909012', 'dozuar@hotmail.com', 'Cra 65F # 31-40, Apt. 418 - Nuevo conquistadores Boreal', 'Medellín', 'Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', NULL, 'Kit robótica grado 2°', 'Colegios - Kit´s>Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', '2026-03-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(46, '7970', 'entregado', 'Yeimy Johanna Villanueva García', '3108508600', 'johannitavillanueva@gmail.com', 'Dg 16 sur 38 26, Casa piso 3', 'Bogotá, D.C.', 'Liceo Bogotá', NULL, 'Kit 6° Ascensor', 'Colegios - Kit´s>Liceo Bogotá', '2026-03-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-14 09:37:30'),
(47, '7969', 'entregado', 'Homes Rivas', '3137083156', 'holmesrivas@gmail.com', 'Tv. 36 #Tr36 72-93, Tv. 36 #72-113, Laureles - Estadio, Medellín, Laureles, Medellín, Antioquia, Colegio Bethlemitas Laureles', 'Medellín', 'Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', NULL, 'Complemento robótica grado 11°', 'Colegios - Kit´s>Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', '2026-03-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(48, '7968', 'entregado', 'Pablo Zapata Giraldo', '3006857375', 'comunicando@gmail.com', 'Calle 87 sur #55-552 casa 5, Parque Residencial Ecológico La Aldea', 'La Estrella', 'Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', NULL, 'Complemento robótica grado 11°', 'Colegios - Kit´s>Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', '2026-03-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(49, '7964', 'entregado', 'Yuly maily Quintero', '3126310356', 'ymaily86@gmail.com', 'Calle 149#91-50, Torre 1 Apt 504', 'Bogotá', 'Colegio Agustiniano Norte', NULL, 'Kit de robótica grado 4°', 'Colegios - Kit´s>Colegio Agustiniano Norte', '2026-03-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(50, '7962', 'entregado', 'VERONICA MONTOYA MEJIA', '3044138821', 'verito3m@gmail.com', 'Cra 73B 75-191, Apartamento 105 condominio turquesa', 'MEDELLIN', 'Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', NULL, 'Complemento robótica grado 11°', 'Colegios - Kit´s>Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', '2026-03-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(51, '7948', 'entregado', 'María Mónica Parada duarte', '3222139833', 'mkjs0822@gmail.com', 'CRA 52 # 19 40 Sur, Rincon de San Eusebio 1 casa 43', 'Bogota', 'Liceo Bogotá', NULL, 'Kit 8° Escorpión', 'Colegios - Kit´s>Liceo Bogotá', '2026-03-01', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(52, '7935', 'entregado', 'YULI CALDERON', '3168331388', 'yulimcd@gmail.com', 'CRA 79 46 75, 1101', 'MEDELLIN', 'Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', NULL, 'Complemento robótica grado 9°', 'Colegios - Kit´s>Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', '2026-02-28', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(53, '7932', 'entregado', 'Paola Ballestas', '3006491615', 'ballestaspaola@gmail.com', 'Calle 144No11-70, Apartamento 204 edificio pasarelas', 'Bogotá', 'Colegio Campestre San José', NULL, 'Kit de robótica grado 8°', 'Colegios - Kit´s>Colegio Campestre San José', '2026-02-28', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(54, '7931', 'entregado', 'Juan Guillermo Gil', '3042839013', 'isabelmacias3010@gmail.com', 'Cra 33 #29-22, Apto 1108', 'Medellín', 'Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', NULL, 'Complemento robótica grado 11°', 'Colegios - Kit´s>Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', '2026-02-28', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(55, '7925', 'entregado', 'ZAYDA LORENA RIOS', '3133490046', 'ZRIOS1996@GMAIL.COM', 'Calle 23#72D-27 2', 'BOGOTÁ, D.C.', 'Gimnasio Nuevo Modelia', NULL, 'Kit robótica grado  2° + complemento', 'Colegios - Kit´s>Gimnasio Nuevo Modelia', '2026-02-27', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(56, '7916', 'entregado', 'Luz Elena Zabaleta Serrano', '3017551809', 'nataliacorreazb@gmail.com', 'Calle 128B #19-55, Interior 2, Apto 401', 'Bogotá', 'Colegio Campestre San José', NULL, 'Kit de robótica grado 6°', 'Colegios - Kit´s>Colegio Campestre San José', '2026-02-27', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(57, '7912', 'entregado', 'Luz Antonia Martinez Ruiz', '3103542003', 'luzmartin577@gmail.com', 'calle 12c # 71c-60, Portón de Alsacia, Torre 4 apartamento 103', 'Bogotá', 'Gimnasio Nuevo Modelia', NULL, 'Kit complemento grado 11°', 'Colegios - Kit´s>Gimnasio Nuevo Modelia', '2026-02-26', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(58, '7908', 'entregado', 'Maria Eugenia martinez', '3006556448', 'mary.martinez60268@gmail.com', 'cra 71 b # 64c-08 casa 2, casa 2', 'bogotá', 'Gimnasio Nuevo Modelia', NULL, 'Kit complemento grado 7°', 'Colegios - Kit´s>Gimnasio Nuevo Modelia', '2026-02-26', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(59, '7894', 'entregado', 'Luz stella Zabala', '3223083080', 'zabalaluzstella@gmail.com', 'Carrera 38#15-19, Centrick park/torre 3 apt 910', 'Bogotá', 'Liceo Bogotá', NULL, 'Kit Explora Transición', 'Colegios - Kit´s>Liceo Bogotá', '2026-02-25', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(60, '7806', 'entregado', 'Nini Johanna Quiroga Olaya', '3118480849', 'alarconherrerajairo@gmail.com', 'Cra 72C #22A-27, Cojunto Residencial la cima-Apartamento 421 torre 3', 'Bogota', 'Gimnasio Nuevo Modelia', NULL, 'Kit complemento grado 4°', 'Colegios - Kit´s>Gimnasio Nuevo Modelia', '2026-02-21', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(61, '7762', 'entregado', 'Jeny Mejía Cuartas', '3008261686', 'jem220@hotmail.com', 'Calle 50A 97-215, Apto. 1306, C.R. El Rosal', 'Medellin', 'Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', NULL, 'Complemento robótica grado 9°', 'Colegios - Kit´s>Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', '2026-02-18', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-14 09:37:30'),
(62, '7748', 'entregado', 'Rudy Barrera Corredor', '3105742188', 'rudidbarreracorredorq@yahoo.com.co', 'Calle 32 #14 - 79, Casa', 'Yopal', 'Colegio La Presentación Yopal', NULL, 'Kit robótica grado 11°', 'Colegios - Kit´s>Colegio La Presentación Yopal', '2026-02-17', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(63, '7724', 'entregado', 'Julio Cesar Hernandez Jauregui', '3188646060', 'juliocehj@hotmail.com', 'Carrera 70 # 22 - 75, Interior 1 apartamento 101', 'Bogota', 'Gimnasio Nuevo Modelia', NULL, 'Kit robótica grado  2° + complemento', 'Colegios - Kit´s>Gimnasio Nuevo Modelia', '2026-02-17', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(64, '7670', 'entregado', 'Lizbeth Geovana Fierro Guarin', '3006607124', 'lizfierro473@gmail.com', 'Cra 6a # 14 44 sur, Torre 4 apartamento 414', 'Mosquera', 'Instituto Salesiano San José Mosquera', NULL, 'Kit robótica grado 3°', 'Colegios - Kit´s>Instituto Salesiano San José Mosquera', '2026-02-14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(65, '7575', 'en_produccion', 'MARIA ELENA BONILLA HERRERA', '3133108552 3216478355', 'mariahelenab4@yahoo.com', 'Calle 21 #81b-30, Torre 16, apto 604', 'Bogotá', 'Gimnasio Nuevo Modelia', NULL, 'Kit complemento grado 9°', 'Colegios - Kit´s>Gimnasio Nuevo Modelia', '2026-02-11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-14 11:45:49'),
(66, '7557', 'entregado', 'Leidy Paola Vargas', '3107970906', 'lpvargasmoreno@gmail.com', 'Carrera 16 36 40, Hacienda casa Blanca casa 32', 'Yopal', 'Colegio La Presentación Yopal', NULL, 'Kit robótica grado 7°', 'Colegios - Kit´s>Colegio La Presentación Yopal', '2026-02-10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-14 09:37:30'),
(67, '7523', 'entregado', 'Monica Hernandez', '3202807675', 'ingmonicaher@gmail.com', 'Carrera 20 N. 4-65, Balcones de montebello torre b Apto 402', 'Yopal', 'Colegio La Presentación Yopal', NULL, 'Kit robótica grado 7°', 'Colegios - Kit´s>Colegio La Presentación Yopal', '2026-02-10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(68, '7511', 'entregado', 'nancy sanchez cadena', '3057106711', 'construnancy@hotmail.com', 'calle 23 g No 82-69, casa', 'bogota', 'Gimnasio Nuevo Modelia', NULL, 'Kit complemento grado 10°', 'Colegios - Kit´s>Gimnasio Nuevo Modelia', '2026-02-09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(69, '7498', 'entregado', 'Cristian Rojas', '3202370508', 'cristianrojas28@hotmail.com', 'Carrera 80 # 8a-04, Esquina Panderia Asturias', 'Bogotá', 'Gimnasio Nuevo Modelia', NULL, 'Kit robótica grado  2° + complemento', 'Colegios - Kit´s>Gimnasio Nuevo Modelia', '2026-02-09', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(70, '7481', 'entregado', 'Gina Paola Buitrago Guerrero', '3103092528', 'pao1371@gmail.com', 'CR 101B No.23D-51, CASA', 'Bogotá', 'Gimnasio Nuevo Modelia', NULL, 'Kit robótica grado 8° + complemento', 'Colegios - Kit´s>Gimnasio Nuevo Modelia', '2026-02-08', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(71, '7464', 'entregado', 'Maryelis Fernandez', '3202438331', 'fernandezmaryelis@gmail.com', 'Calle 6c #82a-57 torre 3 apto602, Torre 3 apto 602', 'Bogotá', 'Gimnasio Nuevo Modelia', NULL, 'Kit robótica grado 9° + complemento', 'Colegios - Kit´s>Gimnasio Nuevo Modelia', '2026-02-06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(72, '7455', 'entregado', 'ANGELICA PAOLA GALINDO GACHARNA', '3174242337', 'angoga1202@hotmail.com', 'DIAGONAL 24 C 96-44, CASA CERCA A LA EMPRESA COCACOLA FONTIBON/CASA TRES PISOS FRENTE A PINOS', 'BOGOTA', 'Gimnasio Nuevo Modelia', NULL, 'Kit robótica grado 9° + complemento', 'Colegios - Kit´s>Gimnasio Nuevo Modelia', '2026-02-06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(73, '7454', 'entregado', 'Iván Mauricio Peláez Mendoza', '3103398228', 'ivanpelaezm@yahoo.com', 'Carrera 24C # 84-84, Cundinamarca', 'Bogotá', 'Gimnasio Nuevo Modelia', NULL, 'Kit complemento grado 8°', 'Colegios - Kit´s>Gimnasio Nuevo Modelia', '2026-02-06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(74, '7447', 'entregado', 'Marcela Rodriguez', '3132774383', 'makarocas06@yahoo.es', 'carrera 90 No. 23i-67, interior 3 apto 401', 'Bogotá', 'Gimnasio Nuevo Modelia', NULL, 'Kit complemento grado 6°', 'Colegios - Kit´s>Gimnasio Nuevo Modelia', '2026-02-06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(75, '7445', 'entregado', 'Francisco Jiménez', '5,73005E+11', 'zicojp@hotmail.com', 'Avda Esperanza No 69A 56, Inte 5 Apto 503, Conjunto Inticaya', 'Bogotá', 'Gimnasio Nuevo Modelia', NULL, 'Kit robótica grado 7° + complemento', 'Colegios - Kit´s>Gimnasio Nuevo Modelia', '2026-02-06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(76, '7444', 'entregado', 'Diego Andres Pinzon Zambrano', '3112104311', 'pinzon.diegoandres@gmail.com', 'Carrera 72C # 22A 77, TORRE 3 INTERIOR 6 APARTAMENTO 324 - CONJUNTO LA COLINA', 'Bogotá', 'Gimnasio Nuevo Modelia', NULL, 'Kit robótica grado 9° + complemento', 'Colegios - Kit´s>Gimnasio Nuevo Modelia', '2026-02-06', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(77, '7434', 'entregado', 'Juan Felipe Rendón franco', '3103897856', 'felipe1707hp@hotmail.con', 'Carrera 84B #7-95, Torre 2 apto 811', 'Medellín', 'Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', NULL, 'Kit robótica grado 3°', 'Colegios - Kit´s>Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', '2026-02-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(78, '7409', 'entregado', 'chabela hernandez', '3123748217', 'chabelahernandezc@hotmail.com', 'calle 50b 11-94', 'yopal', 'Colegio La Presentación Yopal', NULL, 'Kit complemento grado 7°', 'Colegios - Kit´s>Colegio La Presentación Yopal', '2026-02-05', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(79, '7365', 'entregado', 'Yeni Mogollón Vargas', '3166524503', 'rlopezluna28@gmail.com', 'Calle 150a #95-40, Torre 2 apt 402', 'Bogotá', 'Colegio Maximino Poitiers', NULL, 'Kit robótica Maximino 0°', 'Colegios - Kit´s>Colegio Maximino Poitiers', '2026-02-04', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(80, '7332', 'entregado', 'JOSE JOHAN LEON ROJAS', '3176456719', 'josejohanleonrojas@gmail.com', 'CALLE 65 B N 88-52 CASA 40, TORRECAMPO 6 CASA 40', 'BOGOTA / ENGATIVA', 'Gimnasio Nuevo Modelia', NULL, 'Kit complemento grado 8°', 'Colegios - Kit´s>Gimnasio Nuevo Modelia', '2026-02-03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(81, '7327', 'entregado', 'Mary fernanda González Aranguren', '3204498759', 'maryfernanda90@hotmail.com', 'Carrera 29 33 45, El remanso', 'YOPAL', 'Colegio La Presentación Yopal', NULL, 'Kit robótica grado 9°', 'Colegios - Kit´s>Colegio La Presentación Yopal', '2026-02-03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(82, '7314', 'entregado', 'wendy gineth gutierrez sanchez', '5,73112E+11', 'wendyginethg@gmail.com', 'Calle 95 #N. 71-31, Torre 5 apto 803', 'Bogotá, D. C', 'Colegio Agustiniano Norte', NULL, 'Kit de robótica grado 6°', 'Colegios - Kit´s>Colegio Agustiniano Norte', '2026-02-03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(83, '7312', 'entregado', 'OSCAR OBDULIO GUERRA URQUIJO', '3114813289', 'iqx.victoriavargas@hotmail.com', 'Calle 30 # 28-46, Torre 22 Apto.101', 'Yopal', 'Colegio La Presentación Yopal', NULL, 'Kit complemento grado 7°', 'Colegios - Kit´s>Colegio La Presentación Yopal', '2026-02-03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-14 09:37:30'),
(84, '7310', 'entregado', 'Yurley Steffanny Rivera Leal', '3154248542', 'steffarivera@gmail.com', 'Cra 89 # 127- 05, T9 - AP 704', 'BOGOTÁ. D.C.', 'Colegio Maximino Poitiers', NULL, 'Kit robótica Maximino 6°', 'Colegios - Kit´s>Colegio Maximino Poitiers', '2026-02-03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-14 09:37:30'),
(85, '7286', 'entregado', 'MARIANA ANGELICA CONTRERAS GAMBOA', '3167597952', 'marianacontreras411@gmail.com', 'Carrera 51a # 127 - 52, atabanza4, interior2 , apartamento 602', 'Bogotá, D.c.', 'Colegio Campestre San José', NULL, 'Kit de robótica grado 2°', 'Colegios - Kit´s>Colegio Campestre San José', '2026-02-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(86, '7276', 'entregado', 'Osval Felipe Bermudez Prieto', '3219204694', 'felipe.bermudez@hotmail.com', 'calle 152 A bis # 110 - 17 Pinos de lombardia-Suba, apartamento 101', 'Bogota', 'Colegio Maximino Poitiers', NULL, 'Kit robótica Maximino 1°', 'Colegios - Kit´s>Colegio Maximino Poitiers', '2026-02-02', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(87, '7147', 'entregado', 'Cesar Garcia', '3028576029', 'cesar.rugeles@hotmail.com', 'Carrera 81b #19b-85, Casa 113', 'Bogotá', 'Gimnasio Nuevo Modelia', NULL, 'Kit robótica grado 11° + complemento', 'Colegios - Kit´s>Gimnasio Nuevo Modelia', '2026-01-27', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-14 09:37:30'),
(88, '7098', 'entregado', 'Gloria Arevalo', '1052358949', 'Avancemossgsst@gmail.com', 'Cra 6#14-37S bloque 2 casa 76 conjunto nogal novaterra, Casa 76 bloque 2 conjunto novaterra', 'Mosquera barrio novaterra', 'Instituto Salesiano San José Mosquera', NULL, 'Kit robótica grado 7°', 'Colegios - Kit´s>Instituto Salesiano San José Mosquera', '2026-01-24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(89, '7065', 'entregado', 'NATHALIA ARTEAGA', '3217391047', 'natus1811@hotmail.com', 'CARRERA 41 N 20 70 MORASURCO, CASA', 'PASTO', 'Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', NULL, 'Kit robótica grado 9°', 'Colegios - Kit´s>Colegio Sagrado Corazón De Jesús Bethlemitas Medellín', '2026-01-21', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(90, '7026', 'entregado', 'Mauricio Casallas', '3187071865', 'mauricio_casallas@yahoo.com', 'Carrera 108 #81-33', 'Bogotá', 'Colegio Agustiniano Norte', NULL, 'Kit de robótica grado 3°', 'Colegios - Kit´s>Colegio Agustiniano Norte', '2026-01-17', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(91, '6996', 'entregado', 'Lida Esperanza García Riaño', '3134539362', 'lidaesperanza82@gmail.com', 'Cra. 23 #10a-16, Yopal, Casanare, Colombia', 'Yopal', 'Colegio La Presentación Yopal', NULL, 'Kit robótica grado 7°', 'Colegios - Kit´s>Colegio La Presentación Yopal', '2026-01-15', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:54:40'),
(92, '6982', 'entregado', 'Mónica Gañán Gerlein', '3108092571', 'monicaganangerlein@hotmail.com', 'Calle 187A # 21-27, Casa 14 - Marantá 1', 'Bogotá', '', NULL, 'Kit Ingenio T° y 1°', 'Colegios - Kit´s,', '2026-01-14', NULL, NULL, '2026-03-13', NULL, NULL, NULL, NULL, 1, '2026-03-13 22:54:40', '2026-03-13 22:58:11');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tienda_pedidos_historial`
--

CREATE TABLE `tienda_pedidos_historial` (
  `id` int(10) UNSIGNED NOT NULL,
  `pedido_id` int(10) UNSIGNED NOT NULL,
  `estado_ant` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado_nuevo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nota` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `usuario_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tienda_pedidos_historial`
--

INSERT INTO `tienda_pedidos_historial` (`id`, `pedido_id`, `estado_ant`, `estado_nuevo`, `nota`, `usuario_id`, `created_at`) VALUES
(1, 1, NULL, 'pendiente', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(2, 2, NULL, 'pendiente', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(3, 3, NULL, 'pendiente', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(4, 4, NULL, 'pendiente', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(5, 5, NULL, 'pendiente', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(6, 6, NULL, 'pendiente', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(7, 7, NULL, 'pendiente', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(8, 8, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(9, 9, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(10, 10, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(11, 11, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(12, 12, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(13, 13, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(14, 14, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(15, 15, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(16, 16, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(17, 17, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(18, 18, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(19, 19, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(20, 20, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(21, 21, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(22, 22, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(23, 23, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(24, 24, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(25, 25, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(26, 26, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(27, 27, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(28, 28, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(29, 29, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(30, 30, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(31, 31, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(32, 32, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(33, 33, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(34, 34, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(35, 35, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(36, 36, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(37, 37, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(38, 38, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(39, 39, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(40, 40, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(41, 41, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(42, 42, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(43, 43, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(44, 44, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(45, 45, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(46, 46, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(47, 47, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(48, 48, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(49, 49, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(50, 50, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(51, 51, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(52, 52, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(53, 53, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(54, 54, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(55, 55, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(56, 56, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(57, 57, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(58, 58, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(59, 59, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(60, 60, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(61, 61, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(62, 62, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(63, 63, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(64, 64, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(65, 65, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(66, 66, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(67, 67, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(68, 68, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(69, 69, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(70, 70, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(71, 71, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(72, 72, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(73, 73, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(74, 74, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(75, 75, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(76, 76, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(77, 77, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(78, 78, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(79, 79, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(80, 80, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(81, 81, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(82, 82, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(83, 83, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(84, 84, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(85, 85, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(86, 86, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(87, 87, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(88, 88, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(89, 89, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(90, 90, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(91, 91, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(92, 92, NULL, 'pendiente', 'Importado desde CSV', 1, '2026-03-13 22:54:40'),
(93, 92, 'pendiente', 'entregado', NULL, 1, '2026-03-13 22:58:11'),
(94, 4, 'pendiente', 'en_produccion', NULL, 1, '2026-03-13 22:58:17'),
(95, 6, NULL, 'pendiente', 'Importado desde CSV', 1, '2026-03-14 09:09:51'),
(96, 6, NULL, 'pendiente', 'Importado desde CSV', 1, '2026-03-14 09:09:51'),
(97, 6, NULL, 'pendiente', 'Importado desde CSV', 1, '2026-03-14 09:37:30'),
(98, 6, NULL, 'pendiente', 'Importado desde CSV', 1, '2026-03-14 09:37:30'),
(99, 8, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-14 09:37:30'),
(100, 8, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-14 09:37:30'),
(101, 46, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-14 09:37:30'),
(102, 46, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-14 09:37:30'),
(103, 61, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-14 09:37:30'),
(104, 61, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-14 09:37:30'),
(105, 66, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-14 09:37:30'),
(106, 66, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-14 09:37:30'),
(107, 83, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-14 09:37:30'),
(108, 83, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-14 09:37:30'),
(109, 84, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-14 09:37:30'),
(110, 84, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-14 09:37:30'),
(111, 87, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-14 09:37:30'),
(112, 87, NULL, 'entregado', 'Importado desde CSV', 1, '2026-03-14 09:37:30'),
(113, 4, 'en_produccion', 'listo_envio', NULL, 1, '2026-03-14 11:30:00'),
(114, 4, 'listo_envio', 'pendiente', NULL, 1, '2026-03-14 11:30:25'),
(115, 4, 'pendiente', 'pendiente', NULL, 1, '2026-03-14 11:37:19'),
(116, 65, 'entregado', 'en_produccion', NULL, 1, '2026-03-14 11:45:49'),
(117, 4, 'pendiente', 'en_produccion', NULL, 1, '2026-03-15 20:00:42'),
(118, 5, 'pendiente', 'en_produccion', NULL, 1, '2026-03-15 20:00:52'),
(119, 6, 'pendiente', 'en_produccion', NULL, 1, '2026-03-15 20:00:58'),
(120, 7, 'pendiente', 'en_produccion', NULL, 1, '2026-03-15 20:09:23');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_caja`
--

CREATE TABLE `tipos_caja` (
  `id` smallint(5) UNSIGNED NOT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('almacenamiento','empaque_final','transporte') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'almacenamiento',
  `largo_mm` decimal(8,2) DEFAULT NULL,
  `ancho_mm` decimal(8,2) DEFAULT NULL,
  `alto_mm` decimal(8,2) DEFAULT NULL,
  `material` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descripcion` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `costo_cop` decimal(10,2) DEFAULT 0.00,
  `stock_actual` int(11) NOT NULL DEFAULT 0,
  `stock_minimo` int(11) NOT NULL DEFAULT 5,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tipos_caja`
--

INSERT INTO `tipos_caja` (`id`, `nombre`, `tipo`, `largo_mm`, `ancho_mm`, `alto_mm`, `material`, `descripcion`, `costo_cop`, `stock_actual`, `stock_minimo`, `activo`) VALUES
(1, 'Caja pequeña almacén', 'almacenamiento', NULL, NULL, NULL, 'Plástico', 'Para componentes pequeños', '0.00', 0, 5, 1),
(2, 'Caja mediana almacén', 'almacenamiento', NULL, NULL, NULL, 'Plástico', 'Para módulos medianos', '0.00', 0, 5, 1),
(3, 'Caja grande almacén', 'almacenamiento', NULL, NULL, NULL, 'Cartón', 'Para kits completos internos', '0.00', 0, 5, 1),
(4, 'Empaque kit básico', 'empaque_final', NULL, NULL, NULL, 'Cartón impreso', 'Empaque final estudiante kit básico', '0.00', 0, 5, 1),
(5, 'Empaque kit premium', 'empaque_final', NULL, NULL, NULL, 'Cartón rígido', 'Empaque final estudiante kit premium', '0.00', 0, 5, 1),
(6, 'Caja pequeña almacén', 'almacenamiento', NULL, NULL, NULL, 'Plástico', 'Para componentes pequeños', '0.00', 0, 5, 1),
(7, 'Caja mediana almacén', 'almacenamiento', NULL, NULL, NULL, 'Plástico', 'Para módulos medianos', '0.00', 0, 5, 1),
(8, 'Caja grande almacén', 'almacenamiento', NULL, NULL, NULL, 'Cartón', 'Para kits completos internos', '0.00', 0, 5, 1),
(9, 'Empaque kit básico', 'empaque_final', NULL, NULL, NULL, 'Cartón impreso', 'Empaque final estudiante kit básico', '0.00', 0, 5, 1),
(10, 'Empaque kit premium', 'empaque_final', NULL, NULL, NULL, 'Cartón rígido', 'Empaque final estudiante kit premium', '0.00', 0, 5, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(10) UNSIGNED NOT NULL,
  `rol_id` tinyint(3) UNSIGNED NOT NULL DEFAULT 2,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `ultimo_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `microsoft_id` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `microsoft_token` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `rol_id`, `nombre`, `email`, `password_hash`, `avatar`, `activo`, `ultimo_login`, `created_at`, `microsoft_id`, `microsoft_token`, `avatar_url`) VALUES
(1, 1, 'Francisco Puchana', 'admin@robotschool.com', '$2y$12$JfStDQCVAskkLMEJQgExDu3QV58htSgMbugraucuD46KsqZobz9Oq', NULL, 1, '2026-03-16 09:09:35', '2026-03-12 18:19:55', NULL, NULL, NULL),
(3, 7, 'Tomas Esteban Puchana', 'tpuchana@robotschool.com.co', '$2y$12$ub2MZpY2RuIjZ8TGJn72TeJ5fU7nTwaYcQMakfzgJUSptsCwcnxp.', NULL, 1, NULL, '2026-03-14 21:26:13', NULL, NULL, NULL),
(4, 10, 'Gabriela Rodriguez Cristancho', 'administracion@robotschool.com.co', '$2y$12$ownaVsnV2WPNibhT8AQC6.NDp3xavEpFnGDxkFJ2U5revSMHoCRHe', NULL, 1, '2026-03-14 21:45:55', '2026-03-14 21:43:55', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_colegios`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_colegios` (
`id` int(10) unsigned
,`nombre` varchar(200)
,`ciudad` varchar(100)
,`tipo` enum('publico','privado','mixto')
,`nivel` set('preescolar','primaria','secundaria','media')
,`contacto` varchar(150)
,`email` varchar(150)
,`telefono` varchar(40)
,`rector` varchar(150)
,`logo` varchar(255)
,`activo` tinyint(1)
,`total_cursos` bigint(21)
,`total_estudiantes` decimal(27,0)
,`kits_distintos` bigint(21)
,`cursos_con_kit` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_grupos_cupos`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_grupos_cupos` (
`id` int(10) unsigned
,`programa_id` int(10) unsigned
,`nombre` varchar(100)
,`dia_semana` tinyint(4)
,`hora_inicio` time
,`hora_fin` time
,`docente` varchar(150)
,`cupo_max` tinyint(4)
,`elemento_id` int(10) unsigned
,`kit_id` int(10) unsigned
,`periodo` varchar(20)
,`activo` tinyint(1)
,`programa_nombre` varchar(150)
,`programa_tipo` enum('semestral','libre','taller','vacacional')
,`programa_nivel` enum('infantil','basico','intermedio','avanzado')
,`stock_disponible` int(11)
,`elemento_nombre` varchar(200)
,`elemento_codigo` varchar(20)
,`kit_nombre` varchar(200)
,`kit_codigo` varchar(20)
,`matriculas_activas` bigint(21)
,`cupos_libres` bigint(22)
,`disponibilidad` varchar(10)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_kits`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_kits` (
`id` int(10) unsigned
,`codigo` varchar(20)
,`nombre` varchar(200)
,`tipo` varchar(8)
,`nivel` varchar(10)
,`costo_cop` decimal(12,2)
,`activo` tinyint(1)
,`num_elementos` bigint(21)
,`num_prototipos` bigint(21)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_movimientos`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_movimientos` (
`id` int(10) unsigned
,`created_at` datetime
,`tipo` enum('entrada','salida','ajuste','devolucion','transferencia')
,`cantidad` int(11)
,`stock_antes` int(11)
,`stock_despues` int(11)
,`referencia` varchar(100)
,`motivo` varchar(255)
,`elem_codigo` varchar(20)
,`elem_nombre` varchar(200)
,`categoria` varchar(100)
,`usuario` varchar(100)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_pedidos`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_pedidos` (
`id` int(10) unsigned
,`codigo_pedido` varchar(30)
,`estado` enum('borrador','en_transito','en_aduana','recibido','liquidado')
,`fecha_pedido` date
,`fecha_llegada` date
,`numero_tracking_dhl` varchar(60)
,`peso_total_kg` decimal(8,3)
,`costo_dhl_usd` decimal(10,2)
,`tasa_cambio_usd_cop` decimal(10,2)
,`costo_total_cop` decimal(14,2)
,`total_items` bigint(21)
,`total_unidades` decimal(32,0)
,`proveedor` varchar(200)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_stock_semaforo`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_stock_semaforo` (
`id` int(10) unsigned
,`codigo` varchar(20)
,`nombre` varchar(200)
,`categoria` varchar(100)
,`cat_color` varchar(7)
,`stock_actual` int(11)
,`stock_minimo` int(11)
,`stock_maximo` int(11)
,`ubicacion` varchar(100)
,`costo_real_cop` decimal(12,2)
,`precio_venta_cop` decimal(12,2)
,`semaforo` varchar(8)
,`pct_stock` decimal(15,1)
);

-- --------------------------------------------------------

--
-- Estructura Stand-in para la vista `v_tienda_pedidos`
-- (Véase abajo para la vista actual)
--
CREATE TABLE `v_tienda_pedidos` (
`id` int(10) unsigned
,`woo_order_id` varchar(30)
,`estado` enum('pendiente','en_produccion','listo_envio','despachado','entregado','cancelado')
,`cliente_nombre` varchar(200)
,`cliente_telefono` varchar(60)
,`cliente_email` varchar(150)
,`direccion` varchar(300)
,`ciudad` varchar(100)
,`colegio_nombre` varchar(200)
,`colegio_id` int(10) unsigned
,`kit_nombre` varchar(255)
,`categoria` varchar(255)
,`fecha_compra` date
,`fecha_limite` date
,`fecha_despacho` date
,`fecha_entrega` date
,`guia_envio` varchar(80)
,`transportadora` varchar(80)
,`notas_internas` text
,`asignado_a` int(10) unsigned
,`creado_desde_csv` tinyint(1)
,`created_at` datetime
,`updated_at` datetime
,`dias_transcurridos` int(7)
,`semaforo` varchar(10)
,`colegio_bd` varchar(200)
,`asignado_nombre` varchar(100)
);

-- --------------------------------------------------------

--
-- Estructura para la vista `v_colegios`
--
DROP TABLE IF EXISTS `v_colegios`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_colegios`  AS SELECT `col`.`id` AS `id`, `col`.`nombre` AS `nombre`, `col`.`ciudad` AS `ciudad`, `col`.`tipo` AS `tipo`, `col`.`nivel` AS `nivel`, `col`.`contacto` AS `contacto`, `col`.`email` AS `email`, `col`.`telefono` AS `telefono`, `col`.`rector` AS `rector`, `col`.`logo` AS `logo`, `col`.`activo` AS `activo`, count(distinct `cur`.`id`) AS `total_cursos`, sum(`cur`.`num_estudiantes`) AS `total_estudiantes`, count(distinct `cur`.`kit_id`) AS `kits_distintos`, count(distinct case when `cur`.`activo` = 1 and `cur`.`kit_id` is not null then `cur`.`id` end) AS `cursos_con_kit` FROM (`colegios` `col` left join `cursos` `cur` on(`cur`.`colegio_id` = `col`.`id`)) GROUP BY `col`.`id``id`  ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_grupos_cupos`
--
DROP TABLE IF EXISTS `v_grupos_cupos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_grupos_cupos`  AS SELECT `g`.`id` AS `id`, `g`.`programa_id` AS `programa_id`, `g`.`nombre` AS `nombre`, `g`.`dia_semana` AS `dia_semana`, `g`.`hora_inicio` AS `hora_inicio`, `g`.`hora_fin` AS `hora_fin`, `g`.`docente` AS `docente`, `g`.`cupo_max` AS `cupo_max`, `g`.`elemento_id` AS `elemento_id`, `g`.`kit_id` AS `kit_id`, `g`.`periodo` AS `periodo`, `g`.`activo` AS `activo`, `p`.`nombre` AS `programa_nombre`, `p`.`tipo` AS `programa_tipo`, `p`.`nivel` AS `programa_nivel`, coalesce(`e`.`stock_actual`,`g`.`cupo_max`,0) AS `stock_disponible`, `e`.`nombre` AS `elemento_nombre`, `e`.`codigo` AS `elemento_codigo`, `k`.`nombre` AS `kit_nombre`, `k`.`codigo` AS `kit_codigo`, (select count(0) from `matriculas` `m` where `m`.`grupo_id` = `g`.`id` and `m`.`estado` in ('activa','pendiente_pago')) AS `matriculas_activas`, greatest(0,coalesce(`e`.`stock_actual`,`g`.`cupo_max`,0) - (select count(0) from `matriculas` `m2` where `m2`.`grupo_id` = `g`.`id` and `m2`.`estado` in ('activa','pendiente_pago'))) AS `cupos_libres`, CASE WHEN greatest(0,coalesce(`e`.`stock_actual`,`g`.`cupo_max`,0) - (select count(0) from `matriculas` `m3` where `m3`.`grupo_id` = `g`.`id` AND `m3`.`estado` in ('activa','pendiente_pago'))) = 0 THEN 'lleno' WHEN greatest(0,coalesce(`e`.`stock_actual`,`g`.`cupo_max`,0) - (select count(0) from `matriculas` `m4` where `m4`.`grupo_id` = `g`.`id` AND `m4`.`estado` in ('activa','pendiente_pago'))) <= 2 THEN 'casi_lleno' ELSE 'disponible' END AS `disponibilidad` FROM (((`escuela_grupos` `g` join `escuela_programas` `p` on(`p`.`id` = `g`.`programa_id`)) left join `elementos` `e` on(`e`.`id` = `g`.`elemento_id`)) left join `kits` `k` on(`k`.`id` = `g`.`kit_id`)) WHERE `g`.`activo` = 11  ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_kits`
--
DROP TABLE IF EXISTS `v_kits`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_kits`  AS SELECT `k`.`id` AS `id`, `k`.`codigo` AS `codigo`, `k`.`nombre` AS `nombre`, coalesce(`k`.`tipo`,'generico') AS `tipo`, coalesce(`k`.`nivel`,'basico') AS `nivel`, `k`.`costo_cop` AS `costo_cop`, `k`.`activo` AS `activo`, count(distinct `ke`.`id`) AS `num_elementos`, count(distinct `kp`.`id`) AS `num_prototipos` FROM ((`kits` `k` left join `kit_elementos` `ke` on(`ke`.`kit_id` = `k`.`id`)) left join `kit_prototipos` `kp` on(`kp`.`kit_id` = `k`.`id`)) WHERE `k`.`activo` = 1 GROUP BY `k`.`id``id`  ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_movimientos`
--
DROP TABLE IF EXISTS `v_movimientos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_movimientos`  AS SELECT `m`.`id` AS `id`, `m`.`created_at` AS `created_at`, `m`.`tipo` AS `tipo`, `m`.`cantidad` AS `cantidad`, `m`.`stock_antes` AS `stock_antes`, `m`.`stock_despues` AS `stock_despues`, `m`.`referencia` AS `referencia`, `m`.`motivo` AS `motivo`, `e`.`codigo` AS `elem_codigo`, `e`.`nombre` AS `elem_nombre`, `c`.`nombre` AS `categoria`, `u`.`nombre` AS `usuario` FROM (((`movimientos` `m` join `elementos` `e` on(`e`.`id` = `m`.`elemento_id`)) join `categorias` `c` on(`c`.`id` = `e`.`categoria_id`)) left join `usuarios` `u` on(`u`.`id` = `m`.`usuario_id`))  ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_pedidos`
--
DROP TABLE IF EXISTS `v_pedidos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_pedidos`  AS SELECT `p`.`id` AS `id`, `p`.`codigo_pedido` AS `codigo_pedido`, `p`.`estado` AS `estado`, `p`.`fecha_pedido` AS `fecha_pedido`, `p`.`fecha_llegada` AS `fecha_llegada`, `p`.`numero_tracking_dhl` AS `numero_tracking_dhl`, `p`.`peso_total_kg` AS `peso_total_kg`, `p`.`costo_dhl_usd` AS `costo_dhl_usd`, `p`.`tasa_cambio_usd_cop` AS `tasa_cambio_usd_cop`, `p`.`costo_total_cop` AS `costo_total_cop`, count(`pi`.`id`) AS `total_items`, sum(`pi`.`cantidad`) AS `total_unidades`, `pv`.`nombre` AS `proveedor` FROM ((`pedidos_importacion` `p` left join `pedido_items` `pi` on(`pi`.`pedido_id` = `p`.`id`)) left join `proveedores` `pv` on(`pv`.`id` = `p`.`proveedor_id`)) GROUP BY `p`.`id``id`  ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_stock_semaforo`
--
DROP TABLE IF EXISTS `v_stock_semaforo`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_stock_semaforo`  AS SELECT `e`.`id` AS `id`, `e`.`codigo` AS `codigo`, `e`.`nombre` AS `nombre`, `c`.`nombre` AS `categoria`, `c`.`color` AS `cat_color`, `e`.`stock_actual` AS `stock_actual`, `e`.`stock_minimo` AS `stock_minimo`, `e`.`stock_maximo` AS `stock_maximo`, `e`.`ubicacion` AS `ubicacion`, `e`.`costo_real_cop` AS `costo_real_cop`, `e`.`precio_venta_cop` AS `precio_venta_cop`, CASE WHEN `e`.`stock_actual` <= 0 THEN 'rojo' WHEN `e`.`stock_actual` <= `e`.`stock_minimo` THEN 'amarillo' WHEN `e`.`stock_actual` >= `e`.`stock_maximo` THEN 'azul' ELSE 'verde' END AS `semaforo`, round(`e`.`stock_actual` / nullif(`e`.`stock_maximo`,0) * 100,1) AS `pct_stock` FROM (`elementos` `e` join `categorias` `c` on(`c`.`id` = `e`.`categoria_id`)) WHERE `e`.`activo` = 11  ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_tienda_pedidos`
--
DROP TABLE IF EXISTS `v_tienda_pedidos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_tienda_pedidos`  AS SELECT `p`.`id` AS `id`, `p`.`woo_order_id` AS `woo_order_id`, `p`.`estado` AS `estado`, `p`.`cliente_nombre` AS `cliente_nombre`, `p`.`cliente_telefono` AS `cliente_telefono`, `p`.`cliente_email` AS `cliente_email`, `p`.`direccion` AS `direccion`, `p`.`ciudad` AS `ciudad`, `p`.`colegio_nombre` AS `colegio_nombre`, `p`.`colegio_id` AS `colegio_id`, `p`.`kit_nombre` AS `kit_nombre`, `p`.`categoria` AS `categoria`, `p`.`fecha_compra` AS `fecha_compra`, `p`.`fecha_limite` AS `fecha_limite`, `p`.`fecha_despacho` AS `fecha_despacho`, `p`.`fecha_entrega` AS `fecha_entrega`, `p`.`guia_envio` AS `guia_envio`, `p`.`transportadora` AS `transportadora`, `p`.`notas_internas` AS `notas_internas`, `p`.`asignado_a` AS `asignado_a`, `p`.`creado_desde_csv` AS `creado_desde_csv`, `p`.`created_at` AS `created_at`, `p`.`updated_at` AS `updated_at`, to_days(curdate()) - to_days(`p`.`fecha_compra`) AS `dias_transcurridos`, CASE WHEN `p`.`estado` in ('entregado','cancelado') THEN 'completado' WHEN to_days(curdate()) - to_days(`p`.`fecha_compra`) <= 5 THEN 'verde' WHEN to_days(curdate()) - to_days(`p`.`fecha_compra`) <= 7 THEN 'amarillo' ELSE 'rojo' END AS `semaforo`, `col`.`nombre` AS `colegio_bd`, `u`.`nombre` AS `asignado_nombre` FROM ((`tienda_pedidos` `p` left join `colegios` `col` on(`col`.`id` = `p`.`colegio_id`)) left join `usuarios` `u` on(`u`.`id` = `p`.`asignado_a`))  ;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `acad_actividades`
--
ALTER TABLE `acad_actividades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_aa_uni` (`unidad_id`);

--
-- Indices de la tabla `acad_asignaciones`
--
ALTER TABLE `acad_asignaciones`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_asig` (`material_id`,`estudiante_id`),
  ADD KEY `fk_asig_mat` (`material_id`),
  ADD KEY `fk_asig_est` (`estudiante_id`);

--
-- Indices de la tabla `acad_colegios`
--
ALTER TABLE `acad_colegios`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `acad_coordinadores`
--
ALTER TABLE `acad_coordinadores`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_coord_col` (`colegio_id`);

--
-- Indices de la tabla `acad_cursos`
--
ALTER TABLE `acad_cursos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ac_col` (`colegio_id`);

--
-- Indices de la tabla `acad_estudiantes`
--
ALTER TABLE `acad_estudiantes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ae_cur` (`curso_id`);

--
-- Indices de la tabla `acad_materiales`
--
ALTER TABLE `acad_materiales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_am_cur` (`curso_id`);

--
-- Indices de la tabla `acad_progreso`
--
ALTER TABLE `acad_progreso`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_prog` (`estudiante_id`,`actividad_id`),
  ADD KEY `fk_pr_est` (`estudiante_id`),
  ADD KEY `fk_pr_act` (`actividad_id`);

--
-- Indices de la tabla `acad_unidades`
--
ALTER TABLE `acad_unidades`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_au_cur` (`curso_id`);

--
-- Indices de la tabla `acad_xp`
--
ALTER TABLE `acad_xp`
  ADD PRIMARY KEY (`estudiante_id`,`curso_id`),
  ADD KEY `fk_xp_cur` (`curso_id`);

--
-- Indices de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_auditoria_usuario` (`usuario_id`),
  ADD KEY `idx_auditoria_accion` (`accion`),
  ADD KEY `idx_auditoria_fecha` (`created_at`);

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_prefijo` (`prefijo`);

--
-- Indices de la tabla `codigos_secuencia`
--
ALTER TABLE `codigos_secuencia`
  ADD PRIMARY KEY (`categoria_id`);

--
-- Indices de la tabla `colegios`
--
ALTER TABLE `colegios`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `configuracion`
--
ALTER TABLE `configuracion`
  ADD PRIMARY KEY (`clave`);

--
-- Indices de la tabla `convenios`
--
ALTER TABLE `convenios`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `convenio_cursos`
--
ALTER TABLE `convenio_cursos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cc_conv` (`convenio_id`);

--
-- Indices de la tabla `convenio_historial`
--
ALTER TABLE `convenio_historial`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ch_conv` (`convenio_id`);

--
-- Indices de la tabla `cursos`
--
ALTER TABLE `cursos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_curso_colegio` (`colegio_id`),
  ADD KEY `fk_curso_kit` (`kit_id`);

--
-- Indices de la tabla `despachos`
--
ALTER TABLE `despachos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_des_codigo` (`codigo`);

--
-- Indices de la tabla `despachos_secuencia`
--
ALTER TABLE `despachos_secuencia`
  ADD PRIMARY KEY (`anio`);

--
-- Indices de la tabla `despacho_kits`
--
ALTER TABLE `despacho_kits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_dk_desp` (`despacho_id`),
  ADD KEY `fk_dk_kit` (`kit_id`);

--
-- Indices de la tabla `elementos`
--
ALTER TABLE `elementos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_codigo` (`codigo`),
  ADD KEY `fk_elem_cat` (`categoria_id`),
  ADD KEY `fk_elem_prov` (`proveedor_id`);

--
-- Indices de la tabla `escuela_cursos`
--
ALTER TABLE `escuela_cursos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `escuela_grupos`
--
ALTER TABLE `escuela_grupos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_eg_prog` (`programa_id`),
  ADD KEY `fk_eg_kit` (`kit_id`),
  ADD KEY `fk_eg_elem` (`elemento_id`),
  ADD KEY `fk_eg_sede` (`sede_id`);

--
-- Indices de la tabla `escuela_horarios`
--
ALTER TABLE `escuela_horarios`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_eh_curso` (`curso_id`),
  ADD KEY `fk_eh_elem` (`elemento_id`),
  ADD KEY `fk_eh_kit` (`kit_id`),
  ADD KEY `fk_eh_sede` (`sede_id`);

--
-- Indices de la tabla `escuela_modulos`
--
ALTER TABLE `escuela_modulos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_em_curso` (`curso_id`);

--
-- Indices de la tabla `escuela_programas`
--
ALTER TABLE `escuela_programas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_doc` (`documento`);

--
-- Indices de la tabla `kits`
--
ALTER TABLE `kits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_kit_codigo` (`codigo`),
  ADD KEY `fk_kit_colegio` (`colegio_id`),
  ADD KEY `fk_kit_tipocaja` (`tipo_caja_id`);

--
-- Indices de la tabla `kit_elementos`
--
ALTER TABLE `kit_elementos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_kit_elem` (`kit_id`,`elemento_id`),
  ADD KEY `fk_ke_kit` (`kit_id`),
  ADD KEY `fk_ke_elem` (`elemento_id`);

--
-- Indices de la tabla `kit_prototipos`
--
ALTER TABLE `kit_prototipos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_kit_proto` (`kit_id`,`prototipo_id`),
  ADD KEY `fk_kp_proto` (`prototipo_id`);

--
-- Indices de la tabla `maquinas_produccion`
--
ALTER TABLE `maquinas_produccion`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `matriculas`
--
ALTER TABLE `matriculas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_mat` (`estudiante_id`,`grupo_id`),
  ADD KEY `fk_mat_est` (`estudiante_id`),
  ADD KEY `fk_mat_grp` (`grupo_id`);

--
-- Indices de la tabla `movimientos`
--
ALTER TABLE `movimientos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_mov_elem` (`elemento_id`),
  ADD KEY `fk_mov_ped` (`pedido_id`),
  ADD KEY `fk_mov_user` (`usuario_id`);

--
-- Indices de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_notif_user` (`usuario_id`),
  ADD KEY `idx_notif_leida` (`leida`);

--
-- Indices de la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pago_mat` (`matricula_id`),
  ADD KEY `idx_pago_fecha` (`fecha_pago`),
  ADD KEY `idx_pago_sabado` (`sabado_ref`);

--
-- Indices de la tabla `pedidos_importacion`
--
ALTER TABLE `pedidos_importacion`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_codigo_pedido` (`codigo_pedido`),
  ADD KEY `fk_ped_prov` (`proveedor_id`);

--
-- Indices de la tabla `pedido_items`
--
ALTER TABLE `pedido_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pi_pedido` (`pedido_id`),
  ADD KEY `fk_pi_elemento` (`elemento_id`);

--
-- Indices de la tabla `produccion_cronograma`
--
ALTER TABLE `produccion_cronograma`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pc_sol` (`solicitud_id`),
  ADD KEY `fk_pc_maq` (`maquina_id`);

--
-- Indices de la tabla `prototipos`
--
ALTER TABLE `prototipos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_proto_codigo` (`codigo`);

--
-- Indices de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_prov_codigo` (`codigo`),
  ADD KEY `idx_prov_tipo` (`tipo`),
  ADD KEY `idx_prov_pais` (`pais`),
  ADD KEY `idx_prov_activo` (`activo`);

--
-- Indices de la tabla `proveedores_old`
--
ALTER TABLE `proveedores_old`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `proveedor_contactos`
--
ALTER TABLE `proveedor_contactos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pc_prov` (`proveedor_id`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `rol_permisos`
--
ALTER TABLE `rol_permisos`
  ADD PRIMARY KEY (`rol_id`,`modulo`);

--
-- Indices de la tabla `sedes`
--
ALTER TABLE `sedes`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `solicitudes_produccion`
--
ALTER TABLE `solicitudes_produccion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sp_pedido` (`pedido_id`),
  ADD KEY `fk_sp_solicit` (`solicitado_por`),
  ADD KEY `fk_sp_asignado` (`asignado_a`),
  ADD KEY `idx_sp_estado` (`estado`);

--
-- Indices de la tabla `solicitud_historial`
--
ALTER TABLE `solicitud_historial`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_sh_sol` (`solicitud_id`);

--
-- Indices de la tabla `solicitud_items`
--
ALTER TABLE `solicitud_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_si_sol` (`solicitud_id`);

--
-- Indices de la tabla `tienda_pedidos`
--
ALTER TABLE `tienda_pedidos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_woo_order` (`woo_order_id`),
  ADD KEY `idx_estado` (`estado`),
  ADD KEY `idx_fecha` (`fecha_compra`),
  ADD KEY `idx_colegio` (`colegio_id`),
  ADD KEY `fk_tp_user` (`asignado_a`);

--
-- Indices de la tabla `tienda_pedidos_historial`
--
ALTER TABLE `tienda_pedidos_historial`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tph_pedido` (`pedido_id`);

--
-- Indices de la tabla `tipos_caja`
--
ALTER TABLE `tipos_caja`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_email` (`email`),
  ADD KEY `fk_usuario_rol` (`rol_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `acad_actividades`
--
ALTER TABLE `acad_actividades`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `acad_asignaciones`
--
ALTER TABLE `acad_asignaciones`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `acad_colegios`
--
ALTER TABLE `acad_colegios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `acad_coordinadores`
--
ALTER TABLE `acad_coordinadores`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `acad_cursos`
--
ALTER TABLE `acad_cursos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `acad_estudiantes`
--
ALTER TABLE `acad_estudiantes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `acad_materiales`
--
ALTER TABLE `acad_materiales`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `acad_progreso`
--
ALTER TABLE `acad_progreso`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `acad_unidades`
--
ALTER TABLE `acad_unidades`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT de la tabla `colegios`
--
ALTER TABLE `colegios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `convenios`
--
ALTER TABLE `convenios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `convenio_cursos`
--
ALTER TABLE `convenio_cursos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `convenio_historial`
--
ALTER TABLE `convenio_historial`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `cursos`
--
ALTER TABLE `cursos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `despachos`
--
ALTER TABLE `despachos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `despacho_kits`
--
ALTER TABLE `despacho_kits`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `elementos`
--
ALTER TABLE `elementos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `escuela_cursos`
--
ALTER TABLE `escuela_cursos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `escuela_grupos`
--
ALTER TABLE `escuela_grupos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `escuela_horarios`
--
ALTER TABLE `escuela_horarios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `escuela_modulos`
--
ALTER TABLE `escuela_modulos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `escuela_programas`
--
ALTER TABLE `escuela_programas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `estudiantes`
--
ALTER TABLE `estudiantes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `kits`
--
ALTER TABLE `kits`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `kit_elementos`
--
ALTER TABLE `kit_elementos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `kit_prototipos`
--
ALTER TABLE `kit_prototipos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `maquinas_produccion`
--
ALTER TABLE `maquinas_produccion`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `matriculas`
--
ALTER TABLE `matriculas`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `movimientos`
--
ALTER TABLE `movimientos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pagos`
--
ALTER TABLE `pagos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `pedidos_importacion`
--
ALTER TABLE `pedidos_importacion`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `pedido_items`
--
ALTER TABLE `pedido_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `produccion_cronograma`
--
ALTER TABLE `produccion_cronograma`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `prototipos`
--
ALTER TABLE `prototipos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `proveedores_old`
--
ALTER TABLE `proveedores_old`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `proveedor_contactos`
--
ALTER TABLE `proveedor_contactos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` tinyint(3) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `sedes`
--
ALTER TABLE `sedes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `solicitudes_produccion`
--
ALTER TABLE `solicitudes_produccion`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `solicitud_historial`
--
ALTER TABLE `solicitud_historial`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de la tabla `solicitud_items`
--
ALTER TABLE `solicitud_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `tienda_pedidos`
--
ALTER TABLE `tienda_pedidos`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=201;

--
-- AUTO_INCREMENT de la tabla `tienda_pedidos_historial`
--
ALTER TABLE `tienda_pedidos_historial`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=121;

--
-- AUTO_INCREMENT de la tabla `tipos_caja`
--
ALTER TABLE `tipos_caja`
  MODIFY `id` smallint(5) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `acad_actividades`
--
ALTER TABLE `acad_actividades`
  ADD CONSTRAINT `fk_aa_uni` FOREIGN KEY (`unidad_id`) REFERENCES `acad_unidades` (`id`);

--
-- Filtros para la tabla `acad_asignaciones`
--
ALTER TABLE `acad_asignaciones`
  ADD CONSTRAINT `fk_asig_est` FOREIGN KEY (`estudiante_id`) REFERENCES `acad_estudiantes` (`id`),
  ADD CONSTRAINT `fk_asig_mat` FOREIGN KEY (`material_id`) REFERENCES `acad_materiales` (`id`);

--
-- Filtros para la tabla `acad_coordinadores`
--
ALTER TABLE `acad_coordinadores`
  ADD CONSTRAINT `fk_coord_col` FOREIGN KEY (`colegio_id`) REFERENCES `acad_colegios` (`id`);

--
-- Filtros para la tabla `acad_cursos`
--
ALTER TABLE `acad_cursos`
  ADD CONSTRAINT `fk_ac_col` FOREIGN KEY (`colegio_id`) REFERENCES `acad_colegios` (`id`);

--
-- Filtros para la tabla `acad_estudiantes`
--
ALTER TABLE `acad_estudiantes`
  ADD CONSTRAINT `fk_ae_cur` FOREIGN KEY (`curso_id`) REFERENCES `acad_cursos` (`id`);

--
-- Filtros para la tabla `acad_materiales`
--
ALTER TABLE `acad_materiales`
  ADD CONSTRAINT `fk_am_cur` FOREIGN KEY (`curso_id`) REFERENCES `acad_cursos` (`id`);

--
-- Filtros para la tabla `acad_progreso`
--
ALTER TABLE `acad_progreso`
  ADD CONSTRAINT `fk_pr_act` FOREIGN KEY (`actividad_id`) REFERENCES `acad_actividades` (`id`),
  ADD CONSTRAINT `fk_pr_est` FOREIGN KEY (`estudiante_id`) REFERENCES `acad_estudiantes` (`id`);

--
-- Filtros para la tabla `acad_unidades`
--
ALTER TABLE `acad_unidades`
  ADD CONSTRAINT `fk_au_cur` FOREIGN KEY (`curso_id`) REFERENCES `acad_cursos` (`id`);

--
-- Filtros para la tabla `acad_xp`
--
ALTER TABLE `acad_xp`
  ADD CONSTRAINT `fk_xp_cur` FOREIGN KEY (`curso_id`) REFERENCES `acad_cursos` (`id`),
  ADD CONSTRAINT `fk_xp_est` FOREIGN KEY (`estudiante_id`) REFERENCES `acad_estudiantes` (`id`);

--
-- Filtros para la tabla `codigos_secuencia`
--
ALTER TABLE `codigos_secuencia`
  ADD CONSTRAINT `fk_seq_cat` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`);

--
-- Filtros para la tabla `convenio_cursos`
--
ALTER TABLE `convenio_cursos`
  ADD CONSTRAINT `fk_cc_conv` FOREIGN KEY (`convenio_id`) REFERENCES `convenios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `convenio_historial`
--
ALTER TABLE `convenio_historial`
  ADD CONSTRAINT `fk_ch_conv` FOREIGN KEY (`convenio_id`) REFERENCES `convenios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `cursos`
--
ALTER TABLE `cursos`
  ADD CONSTRAINT `fk_curso_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_curso_kit` FOREIGN KEY (`kit_id`) REFERENCES `kits` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `despacho_kits`
--
ALTER TABLE `despacho_kits`
  ADD CONSTRAINT `fk_dk_desp` FOREIGN KEY (`despacho_id`) REFERENCES `despachos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dk_kit` FOREIGN KEY (`kit_id`) REFERENCES `kits` (`id`);

--
-- Filtros para la tabla `elementos`
--
ALTER TABLE `elementos`
  ADD CONSTRAINT `fk_elem_cat` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`),
  ADD CONSTRAINT `fk_elem_prov` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores_old` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `escuela_grupos`
--
ALTER TABLE `escuela_grupos`
  ADD CONSTRAINT `fk_eg_elem` FOREIGN KEY (`elemento_id`) REFERENCES `elementos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_eg_kit` FOREIGN KEY (`kit_id`) REFERENCES `kits` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_eg_prog` FOREIGN KEY (`programa_id`) REFERENCES `escuela_programas` (`id`),
  ADD CONSTRAINT `fk_eg_sede` FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `escuela_horarios`
--
ALTER TABLE `escuela_horarios`
  ADD CONSTRAINT `fk_eh_curso` FOREIGN KEY (`curso_id`) REFERENCES `escuela_cursos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_eh_elem` FOREIGN KEY (`elemento_id`) REFERENCES `elementos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_eh_kit` FOREIGN KEY (`kit_id`) REFERENCES `kits` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_eh_sede` FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `escuela_modulos`
--
ALTER TABLE `escuela_modulos`
  ADD CONSTRAINT `fk_em_curso` FOREIGN KEY (`curso_id`) REFERENCES `escuela_cursos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `kits`
--
ALTER TABLE `kits`
  ADD CONSTRAINT `fk_kit_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_kit_tipocaja` FOREIGN KEY (`tipo_caja_id`) REFERENCES `tipos_caja` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `kit_elementos`
--
ALTER TABLE `kit_elementos`
  ADD CONSTRAINT `fk_ke_elem` FOREIGN KEY (`elemento_id`) REFERENCES `elementos` (`id`),
  ADD CONSTRAINT `fk_ke_kit` FOREIGN KEY (`kit_id`) REFERENCES `kits` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `kit_prototipos`
--
ALTER TABLE `kit_prototipos`
  ADD CONSTRAINT `fk_kp_kit` FOREIGN KEY (`kit_id`) REFERENCES `kits` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_kp_proto` FOREIGN KEY (`prototipo_id`) REFERENCES `prototipos` (`id`);

--
-- Filtros para la tabla `matriculas`
--
ALTER TABLE `matriculas`
  ADD CONSTRAINT `fk_mat_est` FOREIGN KEY (`estudiante_id`) REFERENCES `estudiantes` (`id`),
  ADD CONSTRAINT `fk_mat_grp` FOREIGN KEY (`grupo_id`) REFERENCES `escuela_grupos` (`id`);

--
-- Filtros para la tabla `movimientos`
--
ALTER TABLE `movimientos`
  ADD CONSTRAINT `fk_mov_elem` FOREIGN KEY (`elemento_id`) REFERENCES `elementos` (`id`);

--
-- Filtros para la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `pagos`
--
ALTER TABLE `pagos`
  ADD CONSTRAINT `fk_pago_mat` FOREIGN KEY (`matricula_id`) REFERENCES `matriculas` (`id`);

--
-- Filtros para la tabla `pedidos_importacion`
--
ALTER TABLE `pedidos_importacion`
  ADD CONSTRAINT `fk_ped_prov` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores_old` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `pedido_items`
--
ALTER TABLE `pedido_items`
  ADD CONSTRAINT `fk_pi_elemento` FOREIGN KEY (`elemento_id`) REFERENCES `elementos` (`id`),
  ADD CONSTRAINT `fk_pi_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos_importacion` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `produccion_cronograma`
--
ALTER TABLE `produccion_cronograma`
  ADD CONSTRAINT `fk_pc_maq` FOREIGN KEY (`maquina_id`) REFERENCES `maquinas_produccion` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pc_sol` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes_produccion` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `rol_permisos`
--
ALTER TABLE `rol_permisos`
  ADD CONSTRAINT `fk_rp_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `solicitudes_produccion`
--
ALTER TABLE `solicitudes_produccion`
  ADD CONSTRAINT `fk_sp_asignado` FOREIGN KEY (`asignado_a`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sp_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `tienda_pedidos` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sp_solicit` FOREIGN KEY (`solicitado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `solicitud_historial`
--
ALTER TABLE `solicitud_historial`
  ADD CONSTRAINT `fk_sh_sol` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes_produccion` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `solicitud_items`
--
ALTER TABLE `solicitud_items`
  ADD CONSTRAINT `fk_si_sol` FOREIGN KEY (`solicitud_id`) REFERENCES `solicitudes_produccion` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `tienda_pedidos`
--
ALTER TABLE `tienda_pedidos`
  ADD CONSTRAINT `fk_tp_colegio` FOREIGN KEY (`colegio_id`) REFERENCES `colegios` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_tp_user` FOREIGN KEY (`asignado_a`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `tienda_pedidos_historial`
--
ALTER TABLE `tienda_pedidos_historial`
  ADD CONSTRAINT `fk_tph_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `tienda_pedidos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
