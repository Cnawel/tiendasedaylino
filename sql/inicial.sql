-- =========================
-- SQL INICIAL - Tienda Seda y Lino
-- Base de datos completa desde cero
-- =========================
-- 
-- Este archivo contiene la estructura completa de la base de datos
-- y el usuario administrador inicial para comenzar desde cero.
-- 
-- INSTRUCCIONES:
-- 1. Ejecutar este archivo en phpMyAdmin o cliente MySQL
-- 2. El script creará la base de datos y todas las tablas necesarias
-- 3. Se insertará automáticamente el usuario admin inicial
-- 
-- USUARIO ADMIN INICIAL:
-- - Email: admin@sedaylino.com
-- - Contraseña: admin@sedaylino.com
-- - Rol: admin
-- 
-- IMPORTANTE: Cambiar la contraseña después del primer inicio de sesión
-- =========================

-- Configuración de codificación UTF-8
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET character_set_connection = utf8mb4;

-- Crear base de datos si no existe
CREATE DATABASE IF NOT EXISTS tiendasedaylino_db 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE tiendasedaylino_db;

-- =========================
-- Tabla Preguntas_Recupero
-- =========================
CREATE TABLE IF NOT EXISTS Preguntas_Recupero (
    id_pregunta INT AUTO_INCREMENT PRIMARY KEY,
    texto_pregunta VARCHAR(100) NOT NULL,
    activa TINYINT NOT NULL DEFAULT 1 COMMENT '1=activa, 0=inactiva',
    orden INT NOT NULL DEFAULT 0 COMMENT 'Orden de visualización',
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================
-- Tabla Usuarios
-- =========================
CREATE TABLE IF NOT EXISTS Usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    contrasena VARCHAR(255) NOT NULL,
    rol ENUM('cliente','admin','marketing','ventas') NOT NULL DEFAULT 'cliente',
    activo TINYINT NOT NULL DEFAULT 1 COMMENT '1=activo, 0=inactivo (soft delete)',
    telefono VARCHAR(20) NULL,
    direccion VARCHAR(100) NULL,
    localidad VARCHAR(100) NULL,
    provincia VARCHAR(100) NULL,
    codigo_postal VARCHAR(10) NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de registro del usuario',
    fecha_actualizacion DATETIME NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última actualización',
    fecha_nacimiento DATE NULL COMMENT 'Fecha de nacimiento del usuario',
    pregunta_recupero INT NULL COMMENT 'ID de pregunta de recupero seleccionada',
    respuesta_recupero VARCHAR(255) NULL COMMENT 'Hash de la respuesta a la pregunta de recupero (almacenado como hash por seguridad)',
    FOREIGN KEY (pregunta_recupero) REFERENCES Preguntas_Recupero(id_pregunta) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =========================
-- Tabla Categorias
-- =========================
CREATE TABLE IF NOT EXISTS Categorias (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre_categoria VARCHAR(100) NOT NULL,
    activo TINYINT NOT NULL DEFAULT 1 COMMENT '1=activa, 0=inactiva (soft delete)',
    descripcion_categoria VARCHAR(255) NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    fecha_actualizacion DATETIME NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última actualización'
) ENGINE=InnoDB;

-- =========================
-- Tabla Productos
-- =========================
CREATE TABLE IF NOT EXISTS Productos (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    nombre_producto VARCHAR(100) NOT NULL,
    descripcion_producto VARCHAR(255) NULL,
    precio_actual DECIMAL(10,2) NOT NULL,
    id_categoria INT NOT NULL,
    genero ENUM('hombre','mujer','unisex') NOT NULL DEFAULT 'unisex',
    activo TINYINT NOT NULL DEFAULT 1 COMMENT '1=activo, 0=inactivo (soft delete)',
    sku VARCHAR(50) NULL UNIQUE COMMENT 'Código SKU del producto para inventario',
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    fecha_actualizacion DATETIME NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última actualización',
    FOREIGN KEY (id_categoria) REFERENCES Categorias(id_categoria) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_productos_precio_positivo CHECK (precio_actual >= 0)
) ENGINE=InnoDB;

-- =========================
-- Tabla Fotos_Producto
-- =========================
CREATE TABLE IF NOT EXISTS Fotos_Producto (
    id_foto INT AUTO_INCREMENT PRIMARY KEY,
    id_producto INT NOT NULL,
    foto_prod_miniatura VARCHAR(255) NULL,
    foto1_prod VARCHAR(255) NULL,
    foto2_prod VARCHAR(255) NULL,
    foto3_prod VARCHAR(255) NULL,
    color VARCHAR(50) NULL COMMENT 'Color de la variante del producto',
    activo TINYINT NOT NULL DEFAULT 1 COMMENT '1=activa, 0=inactiva (soft delete)',
    FOREIGN KEY (id_producto) REFERENCES Productos(id_producto) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =========================
-- Tabla Stock_Variantes
-- =========================
CREATE TABLE IF NOT EXISTS Stock_Variantes (
    id_variante INT AUTO_INCREMENT PRIMARY KEY,
    id_producto INT NOT NULL,
    talle VARCHAR(50) NOT NULL,
    color VARCHAR(50) NOT NULL,
    stock INT NOT NULL DEFAULT 0 COMMENT 'Cache del stock actual (se mantiene automáticamente mediante funciones PHP en includes/queries/stock_queries.php)',
    activo TINYINT NOT NULL DEFAULT 1 COMMENT '1=activa (disponible), 0=eliminada (soft delete)',
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Fecha de creación',
    fecha_actualizacion DATETIME NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última actualización',
    FOREIGN KEY (id_producto) REFERENCES Productos(id_producto) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY uk_variante_producto (id_producto, talle, color),
    CONSTRAINT chk_stock_variantes_stock_positivo CHECK (stock >= 0)
) ENGINE=InnoDB;

-- =========================
-- Tabla Pedidos
-- =========================
CREATE TABLE IF NOT EXISTS Pedidos (
    id_pedido INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    fecha_pedido DATETIME NOT NULL,
    estado_pedido ENUM('pendiente','preparacion','en_viaje','completado','devolucion','cancelado') NOT NULL DEFAULT 'pendiente',
    total DECIMAL(10,2) NULL COMMENT 'Total del pedido (calculado o cache)',
    direccion_entrega VARCHAR(100) NULL COMMENT 'Dirección completa de entrega del pedido',
    telefono_contacto VARCHAR(20) NULL COMMENT 'Teléfono de contacto para envío',
    observaciones VARCHAR(500) NULL COMMENT 'Notas internas o del cliente',
    fecha_actualizacion DATETIME NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última actualización',
    FOREIGN KEY (id_usuario) REFERENCES Usuarios(id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =========================
-- Tabla Detalle_Pedido
-- =========================
CREATE TABLE IF NOT EXISTS Detalle_Pedido (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_variante INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_pedido) REFERENCES Pedidos(id_pedido) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_variante) REFERENCES Stock_Variantes(id_variante) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_detalle_pedido_cantidad_positiva CHECK (cantidad > 0 AND cantidad <= 1000),
    CONSTRAINT chk_detalle_pedido_precio_positivo CHECK (precio_unitario >= 0)
) ENGINE=InnoDB;

-- =========================
-- Tabla Forma_Pagos
-- =========================
CREATE TABLE IF NOT EXISTS Forma_Pagos (
    id_forma_pago INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    activo TINYINT NOT NULL DEFAULT 1 COMMENT '1=activa, 0=inactiva (soft delete)',
    descripcion VARCHAR(255) NULL
) ENGINE=InnoDB;

-- =========================
-- Tabla Pagos
-- =========================
CREATE TABLE IF NOT EXISTS Pagos (
    id_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_forma_pago INT NOT NULL,
    numero_transaccion VARCHAR(100) NULL UNIQUE COMMENT 'Número de transacción o referencia para conciliación',
    estado_pago ENUM('pendiente','pendiente_aprobacion','aprobado','rechazado','cancelado') NOT NULL DEFAULT 'pendiente',
    monto DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Monto total del pago',
    fecha_pago DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_aprobacion DATETIME NULL COMMENT 'Fecha de aprobación del pago',
    motivo_rechazo VARCHAR(500) NULL COMMENT 'Razón del rechazo si el pago fue rechazado',
    fecha_actualizacion DATETIME NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Fecha de última actualización',
    FOREIGN KEY (id_pedido) REFERENCES Pedidos(id_pedido) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_forma_pago) REFERENCES Forma_Pagos(id_forma_pago) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_pagos_monto_positivo CHECK (monto >= 0)
) ENGINE=InnoDB;

-- =========================
-- Tabla Movimientos_Stock
-- =========================
CREATE TABLE IF NOT EXISTS Movimientos_Stock (
    id_movimiento INT AUTO_INCREMENT PRIMARY KEY,
    id_variante INT NOT NULL,
    tipo_movimiento ENUM('venta','ajuste','devolucion','ingreso') NOT NULL,
    cantidad INT NOT NULL COMMENT 'Cantidad siempre positiva para venta/ingreso/devolucion. Para ajustes puede ser positiva (suma) o negativa (resta)',
    fecha_movimiento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_usuario INT NULL,
    id_pedido INT NULL COMMENT 'ID del pedido relacionado (NULL para ajustes/ingresos manuales)',
    observaciones VARCHAR(500) NULL,
    FOREIGN KEY (id_variante) REFERENCES Stock_Variantes(id_variante) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES Usuarios(id_usuario) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (id_pedido) REFERENCES Pedidos(id_pedido) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

-- =========================
-- Índices para mejorar rendimiento
-- =========================
-- NOTA IMPORTANTE: Las Foreign Keys en InnoDB crean índices automáticos
-- en las columnas referenciadas. Por lo tanto, NO es necesario crear índices
-- explícitos para columnas que ya tienen Foreign Keys, ya que serían redundantes.
-- 
-- Índice compuesto útil para historial de movimientos (no redundante):
CREATE INDEX idx_movimientos_variante_fecha ON Movimientos_Stock(id_variante, fecha_movimiento);

-- =========================
-- USUARIO ADMIN INICIAL
-- =========================
-- Eliminar usuario admin si ya existe (para evitar duplicados)
DELETE FROM Usuarios WHERE email = 'admin@sedaylino.com';

-- Insertar usuario administrador inicial
INSERT INTO Usuarios (
    nombre, 
    apellido, 
    email, 
    contrasena, 
    rol, 
    activo, 
    fecha_registro
) VALUES (
    'Administrador',
    'Sistema',
    'admin@sedaylino.com',
    '$2y$10$LFMK0hlPPYIvt8/aI0op7OqamoVUk04TIxV70dDeSd6uhF7SQk5XO',
    'admin',
    1,
    NOW()
);

-- =========================
-- VERIFICACIÓN
-- =========================
-- Verificar que el usuario admin se creó correctamente
SELECT 
    id_usuario, 
    nombre, 
    apellido, 
    email, 
    rol, 
    activo,
    fecha_registro
FROM Usuarios 
WHERE email = 'admin@sedaylino.com';

-- =========================
-- NOTA IMPORTANTE: TRIGGERS NO SE USAN
-- =========================
-- 
-- ⚠️ ADVERTENCIA: Los triggers NO se utilizan en la aplicación.
-- Toda la lógica de negocio se implementa en PHP en los archivos:
-- - includes/queries/stock_queries.php (gestión de stock)
-- - includes/queries/pago_queries.php (gestión de pagos)
-- - includes/queries/pedido_queries.php (gestión de pedidos)
--
-- Para más información sobre triggers históricos (comentados), ver:
-- - sql/database_estructura.sql (contiene triggers comentados para referencia)
--
-- =========================
-- NOTAS IMPORTANTES
-- =========================
-- 
-- CHECK Constraints:
-- - Requieren MySQL 8.0.16 o superior para funcionar correctamente
-- - En versiones anteriores (MySQL 5.7, MariaDB), los CHECK se crean pero no se validan
-- - Si usas una versión anterior, la validación se hace en PHP (ver includes/queries/)
-- 
-- UNIQUE Constraints:
-- - Los campos UNIQUE (sku, numero_transaccion) permiten NULL múltiples veces
-- - Solo valores no-NULL deben ser únicos
-- 
-- Lógica de Negocio (PHP):
-- - Toda la lógica de negocio se implementa en PHP, NO en triggers SQL
-- - Ver includes/queries/ para funciones de validación y actualización
-- - Las funciones PHP usan transacciones y FOR UPDATE para prevenir race conditions
-- - Validaciones: stock disponible, usuario activo, variante/producto activos, etc.
-- - Ver docs/BUSINESS_LOGIC.md para documentación completa
-- 
-- Soft Delete:
-- - Todos los campos 'activo' tienen valor DEFAULT 1 (activo por defecto)
-- - Para "eliminar" un registro, cambiar activo = 0 en lugar de DELETE
-- - Esto preserva datos históricos en tablas relacionadas
-- - Las funciones PHP validan activo = 1 antes de permitir operaciones críticas
--
-- Validaciones de Integridad (implementadas en PHP):
-- - Función valida stock disponible ANTES de crear movimiento de venta
-- - Función valida ajustes negativos no causen stock negativo
-- - Función valida usuario activo ANTES de crear pedido
-- - Función valida categoría activa ANTES de crear producto
-- - Función previene múltiples pagos aprobados para el mismo pedido
-- - Función valida variante/producto activos ANTES de crear Detalle_Pedido
-- - FOR UPDATE previene race conditions en operaciones concurrentes
-- - CHECK constraints validan valores positivos (requiere MySQL 8.0.16+)
-- - respuesta_recupero almacena hash (VARCHAR 255) para mayor seguridad
-- - Todas las foreign keys tienen ON DELETE RESTRICT para prevenir eliminaciones accidentales
-- 
-- Seguridad:
-- - La contraseña del usuario admin está hasheada con bcrypt (PASSWORD_DEFAULT)
-- - Cambiar la contraseña después del primer inicio de sesión
-- - Email: admin@sedaylino.com
-- - Contraseña inicial: admin@sedaylino.com
--

