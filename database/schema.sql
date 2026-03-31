-- ROBOTSchool Inventory — Schema
-- Generado: 2026-03-16 | MySQL 8.0 compatible
--
-- USO:
--   mysql -u rsuser -p robotschool_inventory < database/schema.sql
--   mysql -u rsuser -p robotschool_inventory < database/seeds.sql
--
-- CAMBIOS vs seed.sql original:
--   - Columna `edad` VIRTUAL eliminada de `estudiantes` (CURDATE() no permitido en MySQL 8)
--   - DEFINER removido de vistas para compatibilidad de importación
--   - Separado de datos (seeds.sql)

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
  -- `edad` eliminada (MySQL 8 no permite CURDATE() en columnas generadas)
  -- Calcular en PHP: (new DateTime($row['fecha_nac']))->diff(new DateTime())->y
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


-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `matriculas`
--

CREATE TABLE `matriculas` (
  `id` int(10) UNSIGNED NOT NULL,
  `estudiante_id` int(10) UNSIGNED NOT NULL,
  `grupo_id` int(10) UNSIGNED NOT NULL,
  `estado` enum('activa','pausada','retirada','completada','pendiente_pago') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente_pago',
  `fecha_matricula` date NOT NULL DEFAULT (CURDATE()),
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
  `fecha_pago` date NOT NULL DEFAULT (CURDATE()),
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
  `estado` enum('pendiente','aprobado','en_produccion','listo_produccion','en_alistamiento','listo_envio','despachado','entregado','cancelado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `woo_status` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Estado original en WooCommerce (processing, completed, etc.)',
  `woo_payment_method` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Método de pago reportado por WooCommerce',
  `woo_total` decimal(12,2) DEFAULT NULL COMMENT 'Total pagado según WooCommerce',
  `woo_payload` json DEFAULT NULL COMMENT 'Payload JSON completo del webhook/API para auditoría',
  `woo_items_payload` json DEFAULT NULL COMMENT 'Array de line_items del pedido WooCommerce',
  `numero_pedido` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Número de pedido legible (#1234)',
  `cliente_nombre` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cliente_telefono` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cliente_email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direccion` varchar(300) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ciudad` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `colegio_nombre` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `colegio_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK a colegios si se cruza',
  `kit_nombre` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cantidad` smallint(5) UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Número de kits/unidades del pedido. Parseado del CSV; DEFAULT 1 para pedidos de API.',
  `categoria` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_compra` date NOT NULL,
  `fecha_limite` date DEFAULT NULL,
  `fecha_despacho` date DEFAULT NULL,
  `fecha_entrega` date DEFAULT NULL,
  `guia_envio` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `transportadora` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notas_internas` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `instrucciones_especiales` text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Nota especial del cliente al hacer el pedido (Customer Note del checkout)',
  `aprobado_por` int(10) UNSIGNED DEFAULT NULL COMMENT 'FK a usuarios.id — quién aprobó el pedido',
  `aprobado_at` datetime DEFAULT NULL COMMENT 'Timestamp de cuándo fue aprobado',
  `asignado_a` int(10) UNSIGNED DEFAULT NULL,
  `creado_desde_csv` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `tienda_pedidos`
--


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

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_colegios`  AS SELECT `col`.`id` AS `id`, `col`.`nombre` AS `nombre`, `col`.`ciudad` AS `ciudad`, `col`.`tipo` AS `tipo`, `col`.`nivel` AS `nivel`, `col`.`contacto` AS `contacto`, `col`.`email` AS `email`, `col`.`telefono` AS `telefono`, `col`.`rector` AS `rector`, `col`.`logo` AS `logo`, `col`.`activo` AS `activo`, count(distinct `cur`.`id`) AS `total_cursos`, sum(`cur`.`num_estudiantes`) AS `total_estudiantes`, count(distinct `cur`.`kit_id`) AS `kits_distintos`, count(distinct case when `cur`.`activo` = 1 and `cur`.`kit_id` is not null then `cur`.`id` end) AS `cursos_con_kit` FROM (`colegios` `col` left join `cursos` `cur` on(`cur`.`colegio_id` = `col`.`id`)) GROUP BY `col`.`id`  ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_grupos_cupos`
--
DROP TABLE IF EXISTS `v_grupos_cupos`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_grupos_cupos`  AS SELECT `g`.`id` AS `id`, `g`.`programa_id` AS `programa_id`, `g`.`nombre` AS `nombre`, `g`.`dia_semana` AS `dia_semana`, `g`.`hora_inicio` AS `hora_inicio`, `g`.`hora_fin` AS `hora_fin`, `g`.`docente` AS `docente`, `g`.`cupo_max` AS `cupo_max`, `g`.`elemento_id` AS `elemento_id`, `g`.`kit_id` AS `kit_id`, `g`.`periodo` AS `periodo`, `g`.`activo` AS `activo`, `p`.`nombre` AS `programa_nombre`, `p`.`tipo` AS `programa_tipo`, `p`.`nivel` AS `programa_nivel`, coalesce(`e`.`stock_actual`,`g`.`cupo_max`,0) AS `stock_disponible`, `e`.`nombre` AS `elemento_nombre`, `e`.`codigo` AS `elemento_codigo`, `k`.`nombre` AS `kit_nombre`, `k`.`codigo` AS `kit_codigo`, (select count(0) from `matriculas` `m` where `m`.`grupo_id` = `g`.`id` and `m`.`estado` in ('activa','pendiente_pago')) AS `matriculas_activas`, greatest(0,coalesce(`e`.`stock_actual`,`g`.`cupo_max`,0) - (select count(0) from `matriculas` `m2` where `m2`.`grupo_id` = `g`.`id` and `m2`.`estado` in ('activa','pendiente_pago'))) AS `cupos_libres`, CASE WHEN greatest(0,coalesce(`e`.`stock_actual`,`g`.`cupo_max`,0) - (select count(0) from `matriculas` `m3` where `m3`.`grupo_id` = `g`.`id` AND `m3`.`estado` in ('activa','pendiente_pago'))) = 0 THEN 'lleno' WHEN greatest(0,coalesce(`e`.`stock_actual`,`g`.`cupo_max`,0) - (select count(0) from `matriculas` `m4` where `m4`.`grupo_id` = `g`.`id` AND `m4`.`estado` in ('activa','pendiente_pago'))) <= 2 THEN 'casi_lleno' ELSE 'disponible' END AS `disponibilidad` FROM (((`escuela_grupos` `g` join `escuela_programas` `p` on(`p`.`id` = `g`.`programa_id`)) left join `elementos` `e` on(`e`.`id` = `g`.`elemento_id`)) left join `kits` `k` on(`k`.`id` = `g`.`kit_id`)) WHERE `g`.`activo` = 1  ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_kits`
--
DROP TABLE IF EXISTS `v_kits`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_kits`  AS SELECT `k`.`id` AS `id`, `k`.`codigo` AS `codigo`, `k`.`nombre` AS `nombre`, coalesce(`k`.`tipo`,'generico') AS `tipo`, coalesce(`k`.`nivel`,'basico') AS `nivel`, `k`.`costo_cop` AS `costo_cop`, `k`.`activo` AS `activo`, count(distinct `ke`.`id`) AS `num_elementos`, count(distinct `kp`.`id`) AS `num_prototipos` FROM ((`kits` `k` left join `kit_elementos` `ke` on(`ke`.`kit_id` = `k`.`id`)) left join `kit_prototipos` `kp` on(`kp`.`kit_id` = `k`.`id`)) WHERE `k`.`activo` = 1 GROUP BY `k`.`id`  ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_movimientos`
--
DROP TABLE IF EXISTS `v_movimientos`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_movimientos`  AS SELECT `m`.`id` AS `id`, `m`.`created_at` AS `created_at`, `m`.`tipo` AS `tipo`, `m`.`cantidad` AS `cantidad`, `m`.`stock_antes` AS `stock_antes`, `m`.`stock_despues` AS `stock_despues`, `m`.`referencia` AS `referencia`, `m`.`motivo` AS `motivo`, `e`.`codigo` AS `elem_codigo`, `e`.`nombre` AS `elem_nombre`, `c`.`nombre` AS `categoria`, `u`.`nombre` AS `usuario` FROM (((`movimientos` `m` join `elementos` `e` on(`e`.`id` = `m`.`elemento_id`)) join `categorias` `c` on(`c`.`id` = `e`.`categoria_id`)) left join `usuarios` `u` on(`u`.`id` = `m`.`usuario_id`))  ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_pedidos`
--
DROP TABLE IF EXISTS `v_pedidos`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_pedidos`  AS SELECT `p`.`id` AS `id`, `p`.`codigo_pedido` AS `codigo_pedido`, `p`.`estado` AS `estado`, `p`.`fecha_pedido` AS `fecha_pedido`, `p`.`fecha_llegada` AS `fecha_llegada`, `p`.`numero_tracking_dhl` AS `numero_tracking_dhl`, `p`.`peso_total_kg` AS `peso_total_kg`, `p`.`costo_dhl_usd` AS `costo_dhl_usd`, `p`.`tasa_cambio_usd_cop` AS `tasa_cambio_usd_cop`, `p`.`costo_total_cop` AS `costo_total_cop`, count(`pi`.`id`) AS `total_items`, sum(`pi`.`cantidad`) AS `total_unidades`, `pv`.`nombre` AS `proveedor` FROM ((`pedidos_importacion` `p` left join `pedido_items` `pi` on(`pi`.`pedido_id` = `p`.`id`)) left join `proveedores` `pv` on(`pv`.`id` = `p`.`proveedor_id`)) GROUP BY `p`.`id`  ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_stock_semaforo`
--
DROP TABLE IF EXISTS `v_stock_semaforo`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_stock_semaforo`  AS SELECT `e`.`id` AS `id`, `e`.`codigo` AS `codigo`, `e`.`nombre` AS `nombre`, `c`.`nombre` AS `categoria`, `c`.`color` AS `cat_color`, `e`.`stock_actual` AS `stock_actual`, `e`.`stock_minimo` AS `stock_minimo`, `e`.`stock_maximo` AS `stock_maximo`, `e`.`ubicacion` AS `ubicacion`, `e`.`costo_real_cop` AS `costo_real_cop`, `e`.`precio_venta_cop` AS `precio_venta_cop`, CASE WHEN `e`.`stock_actual` <= 0 THEN 'rojo' WHEN `e`.`stock_actual` <= `e`.`stock_minimo` THEN 'amarillo' WHEN `e`.`stock_actual` >= `e`.`stock_maximo` THEN 'azul' ELSE 'verde' END AS `semaforo`, round(`e`.`stock_actual` / nullif(`e`.`stock_maximo`,0) * 100,1) AS `pct_stock` FROM (`elementos` `e` join `categorias` `c` on(`c`.`id` = `e`.`categoria_id`)) WHERE `e`.`activo` = 1  ;

-- --------------------------------------------------------

--
-- Estructura para la vista `v_tienda_pedidos`
--
DROP TABLE IF EXISTS `v_tienda_pedidos`;

CREATE ALGORITHM=UNDEFINED SQL SECURITY DEFINER VIEW `v_tienda_pedidos`  AS SELECT `p`.`id` AS `id`, `p`.`woo_order_id` AS `woo_order_id`, `p`.`estado` AS `estado`, `p`.`cliente_nombre` AS `cliente_nombre`, `p`.`cliente_telefono` AS `cliente_telefono`, `p`.`cliente_email` AS `cliente_email`, `p`.`direccion` AS `direccion`, `p`.`ciudad` AS `ciudad`, `p`.`colegio_nombre` AS `colegio_nombre`, `p`.`colegio_id` AS `colegio_id`, `p`.`kit_nombre` AS `kit_nombre`, `p`.`categoria` AS `categoria`, `p`.`fecha_compra` AS `fecha_compra`, `p`.`fecha_limite` AS `fecha_limite`, `p`.`fecha_despacho` AS `fecha_despacho`, `p`.`fecha_entrega` AS `fecha_entrega`, `p`.`guia_envio` AS `guia_envio`, `p`.`transportadora` AS `transportadora`, `p`.`notas_internas` AS `notas_internas`, `p`.`asignado_a` AS `asignado_a`, `p`.`creado_desde_csv` AS `creado_desde_csv`, `p`.`created_at` AS `created_at`, `p`.`updated_at` AS `updated_at`, to_days(curdate()) - to_days(`p`.`fecha_compra`) AS `dias_transcurridos`, CASE WHEN `p`.`estado` in ('entregado','cancelado') THEN 'completado' WHEN to_days(curdate()) - to_days(`p`.`fecha_compra`) <= 5 THEN 'verde' WHEN to_days(curdate()) - to_days(`p`.`fecha_compra`) <= 7 THEN 'amarillo' ELSE 'rojo' END AS `semaforo`, `col`.`nombre` AS `colegio_bd`, `u`.`nombre` AS `asignado_nombre` FROM ((`tienda_pedidos` `p` left join `colegios` `col` on(`col`.`id` = `p`.`colegio_id`)) left join `usuarios` `u` on(`u`.`id` = `p`.`asignado_a`))  ;

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
  ADD KEY `fk_tp_user` (`asignado_a`),
  ADD KEY `fk_tp_aprobado` (`aprobado_por`);

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