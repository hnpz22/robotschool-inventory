-- =============================================================
-- ROBOTSchool Inventory & Platform — Seeds v3.3
-- =============================================================
-- Generado: 2026-03-17
-- Propósito: Datos mínimos para arranque limpio de la plataforma.
--            No contiene datos operacionales (pedidos, clientes,
--            kits, elementos, matrículas). Esos se ingresan desde
--            la interfaz una vez levantado el sistema.
--
-- Importar DESPUÉS de schema.sql:
--   mysql -u rsuser -p robotschool_inventory < database/schema.sql
--   mysql -u rsuser -p robotschool_inventory < database/seeds.sql
--
-- Tablas incluidas:
--   roles                  — 6 roles del sistema (IDs 1-6)
--   rol_permisos           — permisos granulares por rol y módulo
--   categorias             — 21 categorías de inventario
--   codigos_secuencia      — contadores en 0 para cada categoría
--   configuracion          — configuración base de empresa y sistema
--   sedes                  — 3 sedes físicas de ROBOTSchool
--   maquinas_produccion    — 3 máquinas de producción
--   tipos_caja             — 5 tipos de caja/empaque
--   escuela_cursos         — 6 cursos del catálogo de la escuela
--   escuela_programas      — 5 programas académicos
--   proveedores            — 4 proveedores base
--   proveedores_old        — 4 proveedores (tabla legacy, requerida por FK)
--   proveedores_secuencia  — contador en 4
--   prototipos_secuencia   — contador en 0
--   despachos_secuencia    — contador año 2026 en 0
--   usuarios               — 1 usuario administrador inicial
-- =============================================================

USE `robotschool_inventory`;
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =============================================================
-- ROLES
-- 6 roles fijos definidos en Auth.php / AGENTS.md
-- IDs 1-6 son hardcodeados en la lógica de Auth — no cambiar.
-- =============================================================

INSERT INTO `roles` (`id`, `nombre`, `descripcion`) VALUES
(1, 'gerencia',        'Acceso total sin restricciones. Bypasea cualquier check de permisos.'),
(2, 'administracion',  'Gestión de inventario, kits, colegios, pedidos tienda, producción y reportes.'),
(3, 'academia',        'Cursos, matrículas, pagos, académico LMS y colegios.'),
(4, 'produccion',      'Inventario, kits y tablero de producción.'),
(5, 'comercial',       'Pipeline de convenios, requerimientos y colegios.'),
(6, 'consulta',        'Solo lectura: dashboard, inventario y reportes.');

-- =============================================================
-- PERMISOS GRANULARES POR ROL
-- Alineados con $ROL_MENUS en Auth.php.
-- Gerencia (rol 1) siempre bypasea — estos registros son
-- de referencia para Auth::puede() en edge cases.
-- =============================================================

INSERT INTO `rol_permisos` (`rol_id`, `modulo`, `ver`, `crear`, `editar`, `eliminar`) VALUES
-- Gerencia: acceso total
(1, 'dashboard',      1, 1, 1, 1),
(1, 'elementos',      1, 1, 1, 1),
(1, 'inventario',     1, 1, 1, 1),
(1, 'barcodes',       1, 1, 1, 1),
(1, 'kits',           1, 1, 1, 1),
(1, 'colegios',       1, 1, 1, 1),
(1, 'importaciones',  1, 1, 1, 1),
(1, 'pedidos_tienda', 1, 1, 1, 1),
(1, 'produccion',     1, 1, 1, 1),
(1, 'despachos',      1, 1, 1, 1),
(1, 'cursos',         1, 1, 1, 1),
(1, 'matriculas',     1, 1, 1, 1),
(1, 'academico',      1, 1, 1, 1),
(1, 'comercial',      1, 1, 1, 1),
(1, 'reportes',       1, 1, 1, 1),
(1, 'usuarios',       1, 1, 1, 1),

-- Administración: sin eliminar, sin usuarios
(2, 'dashboard',      1, 0, 0, 0),
(2, 'elementos',      1, 1, 1, 0),
(2, 'inventario',     1, 1, 1, 0),
(2, 'barcodes',       1, 1, 1, 0),
(2, 'kits',           1, 1, 1, 0),
(2, 'colegios',       1, 1, 1, 0),
(2, 'importaciones',  1, 1, 1, 0),
(2, 'pedidos_tienda', 1, 0, 1, 0),
(2, 'produccion',     1, 1, 1, 0),
(2, 'despachos',      1, 1, 1, 0),
(2, 'reportes',       1, 0, 0, 0),
(2, 'usuarios',       0, 0, 0, 0),

-- Academia: cursos, matrículas, LMS
(3, 'dashboard',      1, 0, 0, 0),
(3, 'cursos',         1, 1, 1, 0),
(3, 'matriculas',     1, 1, 1, 0),
(3, 'academico',      1, 1, 1, 0),
(3, 'colegios',       1, 0, 0, 0),

-- Producción: inventario y kits
(4, 'dashboard',      1, 0, 0, 0),
(4, 'elementos',      1, 0, 1, 0),
(4, 'inventario',     1, 1, 1, 0),
(4, 'barcodes',       1, 1, 0, 0),
(4, 'kits',           1, 1, 1, 0),
(4, 'produccion',     1, 1, 1, 0),

-- Comercial: pipeline y colegios
(5, 'dashboard',      1, 0, 0, 0),
(5, 'comercial',      1, 1, 1, 0),
(5, 'colegios',       1, 1, 1, 0),

-- Consulta: solo lectura
(6, 'dashboard',      1, 0, 0, 0),
(6, 'elementos',      1, 0, 0, 0),
(6, 'inventario',     1, 0, 0, 0),
(6, 'reportes',       1, 0, 0, 0);

-- =============================================================
-- CATEGORÍAS DE INVENTARIO
-- 21 categorías con prefijo para generación de código RS-XXX-NNN.
-- No eliminar ni cambiar prefijos — los códigos de elementos
-- existentes dependen de ellos.
-- =============================================================

INSERT INTO `categorias` (`id`, `nombre`, `prefijo`, `descripcion`, `icono`, `color`, `activa`) VALUES
(1,  'Arduino y Clones',         'ARD', 'Placas Arduino UNO, Mega, Nano y compatibles',                   'bi-cpu',         '#e84315', 1),
(2,  'ESP8266 / ESP32',          'ESP', 'Módulos WiFi y Bluetooth de Espressif',                           'bi-wifi',        '#00897b', 1),
(3,  'Raspberry Pi',             'RPI', 'Placas Raspberry Pi y accesorios',                               'bi-hdd-rack',    '#c62828', 1),
(4,  'Sensores',                 'SEN', 'Sensores de temperatura, distancia, movimiento, etc.',            'bi-thermometer', '#6a1b9a', 1),
(5,  'Módulos',                  'MOD', 'Módulos de comunicación, display, reloj, etc.',                   'bi-grid',        '#1565c0', 1),
(6,  'Actuadores',               'ACT', 'Servomotores, motores DC, relés',                                 'bi-gear-wide',   '#2e7d32', 1),
(7,  'Fuentes y Energía',        'PWR', 'Baterías, reguladores, cargadores, convertidores',                'bi-lightning',   '#f57f17', 1),
(8,  'Cables y Conectores',      'CAB', 'Jumpers, protoboard, terminales, adaptadores',                    'bi-plugin',      '#37474f', 1),
(9,  'Herramientas',             'HER', 'Soldadores, multímetros, pistolas de calor, pinzas',              'bi-tools',       '#4e342e', 1),
(10, 'Impresión 3D',             'IMP', 'Filamentos, piezas impresas, accesorios de impresora',            'bi-printer',     '#5c6bc0', 1),
(11, 'Corte Láser',              'LAS', 'MDF, acrílico, piezas cortadas en láser',                         'bi-stars',       '#f4511e', 1),
(12, 'Cajas de Almacenamiento',  'CAJ', 'Cajas internas por colegio y empaque intermedio',                 'bi-box-seam',    '#00838f', 1),
(13, 'Kits Armados',             'KIT', 'Kits completos listos para entrega',                              'bi-bag-check',   '#43a047', 1),
(14, 'Otros',                    'OTR', 'Elementos varios no clasificados',                                 'bi-three-dots',  '#78909c', 1),
(15, 'Tornillos y Tuercas',      'TOR', 'Tornillos, tuercas, separadores, arandelas, remaches',            'bi-gear',        '#795548', 1),
(16, 'LEDs y Displays',          'LED', 'LEDs de colores, displays LCD, OLED, 7 segmentos, matrices',      'bi-lightbulb',   '#FF6F00', 1),
(17, 'Resistencias y Caps',      'RES', 'Resistencias, capacitores, inductores, diodos, transistores',     'bi-diagram-3',   '#9C27B0', 1),
(18, 'MDF y Madera',             'MDF', 'Láminas MDF, acrílico, madera balsa, contrachapado',              'bi-grid-1x2',    '#6D4C41', 1),
(19, 'Filamentos 3D',            'FIL', 'PLA, ABS, PETG, TPU clasificados por color y tipo',               'bi-circle',      '#00ACC1', 1),
(20, 'Stickers e Impresión',     'STK', 'Stickers, etiquetas adhesivas, laminado, vinilo de corte',        'bi-tag',         '#F06292', 1),
(21, 'Empaque y Cajas',          'EMP', 'Cajas de empaque final para envío, bolsas, espumas protectoras',  'bi-box2',        '#26A69A', 1);

-- =============================================================
-- CONTADORES DE SECUENCIA DE CÓDIGOS
-- Todos en 0 — el primer elemento de cada categoría
-- tomará el número 1 (RS-ARD-001, RS-ESP-001, etc.)
-- =============================================================

INSERT INTO `codigos_secuencia` (`categoria_id`, `ultimo_numero`) VALUES
(1,  0), (2,  0), (3,  0), (4,  0), (5,  0),
(6,  0), (7,  0), (8,  0), (9,  0), (10, 0),
(11, 0), (12, 0), (13, 0), (14, 0), (15, 0),
(16, 0), (17, 0), (18, 0), (19, 0), (20, 0),
(21, 0);

-- =============================================================
-- CONFIGURACIÓN DEL SISTEMA
-- Tabla clave/valor por grupo. Solo se incluyen valores
-- que el sistema necesita para funcionar correctamente.
-- WooCommerce y Microsoft OAuth se configuran desde la UI
-- o directamente en la tabla una vez levantado el sistema.
-- =============================================================

INSERT INTO `configuracion` (`clave`, `valor`, `descripcion`, `grupo`) VALUES
-- Empresa (completar desde la UI o directamente en BD)
('empresa_nombre',        '',  'Nombre de la empresa',                    'empresa'),
('empresa_nit',           '',  'NIT de la empresa',                       'empresa'),
('empresa_ciudad',        '',  'Ciudad principal de operación',            'empresa'),
('empresa_telefono',      '',  'Teléfono de contacto',                    'empresa'),
('empresa_email',         '',  'Email de contacto',                       'empresa'),
('empresa_web',           '',  'Sitio web',                               'empresa'),

-- Sistema
('version',               '3.3',                   'Versión del sistema',                     'sistema'),
('items_por_pagina',      '25',                    'Registros por página en listados',        'sistema'),
('barcode_tipo',          'CODE128',               'Tipo de código de barras (JsBarcode)',    'sistema'),

-- Importaciones y costos
('moneda_compra',         'USD',                   'Moneda de compra en China',               'importacion'),
('trm_default',           '4200',                  'TRM USD→COP por defecto',                 'importacion'),
('arancel_default_pct',   '5',                     'Porcentaje de arancel por defecto',       'importacion'),
('iva_pct',               '19',                    'Porcentaje de IVA Colombia',              'importacion'),
('dhl_zona_factor',       '1.0',                   'Factor de zona DHL Colombia',             'importacion'),

-- WooCommerce (vacíos, se configuran desde la BD o futura UI)
('woo_url',               '',                      'URL base del sitio WordPress WooCommerce','woocommerce'),
('woo_consumer_key',      '',                      'Consumer Key WooCommerce REST API v3',    'woocommerce'),
('woo_consumer_secret',   '',                      'Consumer Secret WooCommerce REST API v3', 'woocommerce'),
('woo_campo_colegio',     '',                      'Campo personalizado del checkout (colegio)','woocommerce');

-- =============================================================
-- SEDES FÍSICAS
-- Las 3 sedes actuales de ROBOTSchool Colombia.
-- =============================================================

INSERT INTO `sedes` (`id`, `nombre`, `ciudad`, `direccion`, `telefono`, `email`, `responsable`, `color`, `activo`, `created_at`) VALUES
(1, 'ROBOTSchool Bogotá Norte',  'bogota', 'Calle 136 No. 16-20, Bogotá',         NULL, NULL, NULL, '#185FA5', 1, NOW()),
(2, 'ROBOTSchool Bogotá Sur',    'bogota', 'Calle 75 No. 20B-62, Bogotá',         NULL, NULL, NULL, '#16a34a', 1, NOW()),
(3, 'ROBOTSchool Cali',          'cali',   'Cali — dirección por confirmar',       NULL, NULL, NULL, '#dc2626', 1, NOW());

-- =============================================================
-- MÁQUINAS DE PRODUCCIÓN
-- 3 cortadoras láser del taller.
-- =============================================================

INSERT INTO `maquinas_produccion` (`id`, `nombre`, `tipo`, `descripcion`, `color`, `activa`) VALUES
(1, 'Láser 1', 'laser', 'Cortadora láser principal',  '#e53935', 1),
(2, 'Láser 2', 'laser', 'Cortadora láser secundaria', '#7c3aed', 1),
(3, 'Láser 3', 'laser', 'Cortadora láser de apoyo',   '#0891b2', 1);

-- =============================================================
-- TIPOS DE CAJA
-- 5 tipos de empaque usados en el sistema.
-- =============================================================

INSERT INTO `tipos_caja` (`id`, `nombre`, `tipo`, `largo_mm`, `ancho_mm`, `alto_mm`, `material`, `descripcion`, `costo_cop`, `stock_actual`, `stock_minimo`, `activo`) VALUES
(1, 'Caja pequeña almacén',  'almacenamiento', NULL, NULL, NULL, 'Plástico',        'Para componentes pequeños',                '0.00', 0, 5, 1),
(2, 'Caja mediana almacén',  'almacenamiento', NULL, NULL, NULL, 'Plástico',        'Para módulos medianos',                    '0.00', 0, 5, 1),
(3, 'Caja grande almacén',   'almacenamiento', NULL, NULL, NULL, 'Cartón',          'Para kits completos en bodega',            '0.00', 0, 5, 1),
(4, 'Empaque kit básico',    'empaque_final',  NULL, NULL, NULL, 'Cartón impreso',  'Empaque final estudiante — kit básico',    '0.00', 0, 5, 1),
(5, 'Empaque kit premium',   'empaque_final',  NULL, NULL, NULL, 'Cartón rígido',   'Empaque final estudiante — kit premium',   '0.00', 0, 5, 1);

-- =============================================================
-- CURSOS DE LA ESCUELA DE ROBÓTICA
-- Catálogo base de 6 cursos. Precios y cupos son orientativos
-- y se ajustan desde el módulo de Cursos.
-- =============================================================

INSERT INTO `escuela_cursos` (`id`, `nombre`, `slug`, `categoria`, `descripcion`, `objetivos`, `tematicas`, `imagen`, `banner_ia`, `color_primario`, `color_secundario`, `edad_min`, `edad_max`, `nivel`, `duracion_min`, `num_sesiones`, `cupo_max`, `precio`, `precio_semestral`, `activo`, `destacado`, `created_at`, `updated_at`) VALUES
(1, 'Robótica con Lego Spike',  NULL, 'robotica',    'Construye y programa robots con Lego Spike Prime de forma divertida y creativa.',
   '["Pensamiento computacional","Construcción de robots","Programación por bloques","Trabajo en equipo"]',
   '["Introducción a la robótica","Sensores y motores","Programación Spike","Desafíos y competencias"]',
   NULL, NULL, '#E3A600', '#0f4c81',  6, 12, 'basico',     120, 16, 10, '200000', '0', 1, 1, NOW(), NOW()),

(2, 'Programación Python',      NULL, 'programacion','Introducción al lenguaje Python para jóvenes: lógica, algoritmos y proyectos reales.',
   '["Lógica de programación","Variables y funciones","Algoritmos básicos","Proyectos reales"]',
   '["Fundamentos Python","Condiciones y ciclos","Funciones","Juegos con Python"]',
   NULL, NULL, '#3776AB', '#0f4c81', 10, 17, 'intermedio', 120, 16, 10, '220000', '0', 1, 1, NOW(), NOW()),

(3, 'Minecraft Education',      NULL, 'videojuegos', 'Aprende matemáticas, ciencias y programación dentro del universo Minecraft.',
   '["Creatividad digital","Lógica espacial","Introducción al código","Proyectos STEAM"]',
   '["Construcción y diseño","Redstone básico","Code Builder","Proyectos colaborativos"]',
   NULL, NULL, '#62B53E', '#0f4c81',  7, 14, 'basico',     120, 16, 10, '180000', '0', 1, 0, NOW(), NOW()),

(4, 'Roblox Studio',            NULL, 'videojuegos', 'Crea tus propios videojuegos en Roblox Studio y aprende a programar en Lua.',
   '["Diseño de videojuegos","Programación Lua","Modelado 3D básico","Publicación de juegos"]',
   '["Interfaz Roblox Studio","Scripts básicos","Física y colisiones","Tu primer juego"]',
   NULL, NULL, '#E53935', '#0f4c81', 10, 17, 'intermedio', 120, 16, 10, '220000', '0', 1, 0, NOW(), NOW()),

(5, 'Impresión 3D y Diseño',    NULL, 'impresion3d', 'Diseña y materializa tus ideas con impresoras 3D y software de modelado digital.',
   '["Diseño 3D","Modelado digital","Fabricación digital","Creatividad técnica"]',
   '["Introducción a TinkerCAD","Diseño de piezas","Preparación de archivos","Impresión y acabados"]',
   NULL, NULL, '#FF6F00', '#0f4c81',  9, 17, 'basico',     120, 12, 10, '200000', '0', 1, 0, NOW(), NOW()),

(6, 'Electrónica y Arduino',    NULL, 'electronica', 'Aprende electrónica básica y programación de microcontroladores Arduino.',
   '["Circuitos básicos","Programación Arduino","Sensores y actuadores","Proyectos IoT"]',
   '["Electrónica básica","Arduino UNO","Sensores","Proyecto final"]',
   NULL, NULL, '#00979D', '#0f4c81', 11, 17, 'intermedio', 120, 16, 10, '220000', '0', 1, 0, NOW(), NOW());

-- =============================================================
-- PROGRAMAS ACADÉMICOS DE LA ESCUELA
-- =============================================================

INSERT INTO `escuela_programas` (`id`, `nombre`, `tipo`, `descripcion`, `nivel`, `edad_min`, `edad_max`, `duracion_semanas`, `sesiones_semana`, `valor_matricula`, `valor_mensualidad`, `valor_semestral`, `activo`, `created_at`) VALUES
(1, 'Robótica Básica Sábados',      'libre',      'Clases sabatinas de robótica para niños entre 7 y 10 años.',      'basico',     7,  10, NULL, 1, '0', '200000', '0', 1, NOW()),
(2, 'Robótica Intermedia',          'libre',      'Clases sabatinas para jóvenes con conocimientos previos.',         'intermedio', 10, 14, NULL, 1, '0', '220000', '0', 1, NOW()),
(3, 'Robótica Avanzada',            'libre',      'Programa intensivo para jóvenes avanzados.',                      'avanzado',   12, 17, NULL, 1, '0', '250000', '0', 1, NOW()),
(4, 'Semestral Primer Semestre',    'semestral',  'Programa semestral con continuidad y proyectos integradores.',    'basico',      7, 17, NULL, 1, '0',      '0', '0', 1, NOW()),
(5, 'Taller Vacacional',            'vacacional', 'Taller intensivo en temporada de vacaciones escolares.',          'basico',      6, 14, NULL, 5, '0', '180000', '0', 1, NOW());

-- =============================================================
-- PROVEEDORES
-- Tabla nueva (proveedores) y tabla legacy (proveedores_old).
-- Ambas son necesarias por las FK en elementos y pedidos_importacion.
-- =============================================================

INSERT INTO `proveedores` (`id`, `codigo`, `nombre`, `nombre_comercial`, `tipo`, `pais`, `ciudad`, `contacto_nombre`, `contacto_cargo`, `email`, `telefono`, `whatsapp`, `url_tienda`, `url_catalogo`, `nit_rut`, `tiempo_entrega_dias`, `moneda`, `minimo_pedido`, `descuento_habitual_pct`, `calificacion`, `metodo_pago`, `requiere_dhl`, `codigo_proveedor_dhl`, `puerto_origen`, `incoterm`, `categorias_producto`, `foto`, `notas`, `activo`, `es_preferido`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'RS-PROV-001', 'AliExpress',       NULL, 'electronica_china',    'China',    NULL, NULL, NULL, NULL, NULL, NULL, 'https://aliexpress.com', NULL, NULL, NULL, 'USD', NULL, '0.00', 3, NULL, 0, NULL, NULL, 'EXW', NULL, NULL, NULL, 1, 0, NULL, NOW(), NOW()),
(2, 'RS-PROV-002', 'Alibaba',          NULL, 'electronica_china',    'China',    NULL, NULL, NULL, NULL, NULL, NULL, 'https://alibaba.com',    NULL, NULL, NULL, 'USD', NULL, '0.00', 3, NULL, 0, NULL, NULL, 'EXW', NULL, NULL, NULL, 1, 0, NULL, NOW(), NOW()),
(3, 'RS-PROV-003', 'LCSC Electronics', NULL, 'electronica_china',    'China',    NULL, NULL, NULL, NULL, NULL, NULL, 'https://lcsc.com',       NULL, NULL, NULL, 'USD', NULL, '0.00', 3, NULL, 0, NULL, NULL, 'EXW', NULL, NULL, NULL, 1, 0, NULL, NOW(), NOW()),
(4, 'RS-PROV-004', 'Mouser Colombia',  NULL, 'electronica_colombia', 'Colombia', NULL, NULL, NULL, NULL, NULL, NULL, 'https://mouser.com',     NULL, NULL, NULL, 'USD', NULL, '0.00', 3, NULL, 0, NULL, NULL, 'EXW', NULL, NULL, NULL, 1, 0, NULL, NOW(), NOW());

INSERT INTO `proveedores_old` (`id`, `nombre`, `pais`, `contacto`, `email`, `telefono`, `url_tienda`, `notas`, `activo`, `created_at`) VALUES
(1, 'AliExpress',       'China',    NULL, NULL, NULL, 'https://aliexpress.com', NULL, 1, NOW()),
(2, 'Alibaba',          'China',    NULL, NULL, NULL, 'https://alibaba.com',    NULL, 1, NOW()),
(3, 'LCSC Electronics', 'China',    NULL, NULL, NULL, 'https://lcsc.com',       NULL, 1, NOW()),
(4, 'Mouser Colombia',  'Colombia', NULL, NULL, NULL, 'https://mouser.com',     NULL, 1, NOW());

-- =============================================================
-- CONTADORES DE SECUENCIA
-- proveedores_secuencia: el último ID insertado fue el 4.
-- prototipos_secuencia:  sin prototipos aún, contador en 0.
-- despachos_secuencia:   año 2026, sin despachos aún.
-- =============================================================

INSERT INTO `proveedores_secuencia` (`ultimo_numero`) VALUES (4);

INSERT INTO `prototipos_secuencia` (`ultimo_numero`) VALUES (0);

INSERT INTO `despachos_secuencia` (`anio`, `ultimo_numero`) VALUES (2026, 0);

-- =============================================================
-- USUARIO ADMINISTRADOR INICIAL
-- rol_id = 1 (gerencia) — acceso total.
-- Hash bcrypt cost 12 correspondiente a la contraseña actual.
-- IMPORTANTE: cambiar la contraseña después del primer login
-- desde el módulo de Usuarios, o usar setup_admin.php para
-- resetearla antes de levantar el sistema en un nuevo servidor.
-- =============================================================

-- =============================================================
-- USUARIO ADMINISTRADOR INICIAL
-- rol_id = 1 (gerencia) — acceso total.
--
-- NO se inserta un usuario con contraseña aquí.
-- El flujo correcto para crear el primer administrador es:
--
--   1. Importar schema.sql + seeds.sql
--   2. Levantar los contenedores: docker compose up -d
--   3. Abrir desde localhost: http://localhost:8081/setup_admin.php
--   4. Ingresar nombre, email y contraseña (mínimo 8 caracteres)
--   5. Eliminar setup_admin.php del servidor inmediatamente
--
-- setup_admin.php solo acepta conexiones desde 127.0.0.1 / ::1
-- y genera el hash bcrypt cost 12 directamente en PHP.
-- =============================================================

-- (sin INSERT de usuarios — usar setup_admin.php)

-- =============================================================

SET FOREIGN_KEY_CHECKS = 1;
COMMIT;

-- =============================================================
-- DESPUÉS DE IMPORTAR ESTE ARCHIVO:
-- 1. Ir a http://TU-IP:8081/setup_admin.php para verificar o
--    resetear la contraseña del administrador.
-- 2. Eliminar setup_admin.php del servidor.
-- 3. Configurar APP_URL en .env con la IP o dominio real.
-- 4. Ingresar al sistema y completar la configuración de
--    WooCommerce desde la BD (tabla configuracion, grupo
--    'woocommerce') hasta que exista la UI de configuración.
-- =============================================================