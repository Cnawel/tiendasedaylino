-- =========================
-- SQL Tienda Seda y Lino
-- Estructura completa de base de datos
-- =========================
-- 
-- Este archivo contiene la estructura completa de la base de datos
-- incluyendo todas las mejoras de Prioridad 1 y mejoras críticas:
-- - Tablas con columnas actualizadas (id_pedido en Movimientos_Stock, monto en Pagos, fecha_actualizacion)
-- - Validaciones de integridad implementadas en PHP (NO se usan triggers - ver includes/queries/)
-- - Índices para mejorar rendimiento
-- - Validaciones a nivel de base de datos
-- - Campos de soft delete (activo) en tablas principales
-- - Campos de auditoría (fecha_creacion, fecha_actualizacion)
-- - Campos funcionales (sku, numero_transaccion, etc.)
-- - UNIQUE KEY para prevenir duplicados
-- =========================

-- Configuración de codificación UTF-8
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET character_set_connection = utf8mb4;

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
-- Índices automáticos creados por Foreign Keys:
-- - Movimientos_Stock(id_variante) -> Stock_Variantes(id_variante)
-- - Movimientos_Stock(id_pedido) -> Pedidos(id_pedido)
-- - Movimientos_Stock(id_usuario) -> Usuarios(id_usuario)
-- - Pagos(id_pedido) -> Pedidos(id_pedido)
-- - Pagos(id_forma_pago) -> Forma_Pagos(id_forma_pago)
-- - Pedidos(id_usuario) -> Usuarios(id_usuario)
-- - Detalle_Pedido(id_pedido) -> Pedidos(id_pedido)
-- - Detalle_Pedido(id_variante) -> Stock_Variantes(id_variante)
-- - Productos(id_categoria) -> Categorias(id_categoria)
-- - Stock_Variantes(id_producto) -> Productos(id_producto)
-- - Fotos_Producto(id_producto) -> Productos(id_producto)
--
-- Índice compuesto útil para historial de movimientos (no redundante):
CREATE INDEX idx_movimientos_variante_fecha ON Movimientos_Stock(id_variante, fecha_movimiento);

-- NOTA: idx_usuarios_email NO es necesario porque email tiene UNIQUE constraint
-- que automáticamente crea un índice único en InnoDB

-- =========================
-- NOTA IMPORTANTE: TRIGGERS NO SE USAN
-- =========================
-- 
-- ⚠️ ADVERTENCIA: Los triggers definidos a continuación NO se utilizan en la aplicación.
-- Toda la lógica de negocio se implementa en PHP en los archivos:
-- - includes/queries/stock_queries.php (gestión de stock)
-- - includes/queries/pago_queries.php (gestión de pagos)
-- - includes/queries/pedido_queries.php (gestión de pedidos)
--
-- Los triggers están comentados para referencia histórica pero NO deben ejecutarse.
-- Si se ejecutan, pueden causar conflictos con la lógica PHP (doble actualización de stock, etc.)
--
-- Para más información, ver:
-- - docs/BUSINESS_LOGIC.md (documentación completa de lógica de negocio)
-- - docs/DATABASE_REVIEW.md (reporte de revisión de base de datos)
--
-- =========================
-- Triggers (NO USADOS - Solo para referencia)
-- =========================

/*
DELIMITER //

-- Trigger 1: Actualizar stock automáticamente cuando se inserta un movimiento
-- IMPORTANTE: Cantidad siempre positiva para venta/ingreso/devolucion. 
-- Para ajustes, cantidad puede ser positiva (suma) o negativa (resta)
CREATE TRIGGER trg_actualizar_stock_insert
AFTER INSERT ON Movimientos_Stock
FOR EACH ROW
BEGIN
    IF NEW.tipo_movimiento = 'venta' THEN
        -- Restar stock (venta)
        UPDATE Stock_Variantes 
        SET stock = stock - NEW.cantidad,
            fecha_actualizacion = NOW()
        WHERE id_variante = NEW.id_variante;
    ELSEIF NEW.tipo_movimiento IN ('ingreso', 'devolucion') THEN
        -- Sumar stock (ingreso o devolución)
        UPDATE Stock_Variantes 
        SET stock = stock + NEW.cantidad,
            fecha_actualizacion = NOW()
        WHERE id_variante = NEW.id_variante;
    ELSEIF NEW.tipo_movimiento = 'ajuste' THEN
        -- Los ajustes pueden tener cantidad positiva (suma stock) o negativa (resta stock)
        -- La cantidad se suma directamente: positivo aumenta stock, negativo lo disminuye
        UPDATE Stock_Variantes 
        SET stock = stock + NEW.cantidad,
            fecha_actualizacion = NOW()
        WHERE id_variante = NEW.id_variante;
    END IF;
END//

-- Trigger 3b: Validar que ajustes negativos no causen stock negativo
CREATE TRIGGER trg_validar_ajuste_stock
BEFORE INSERT ON Movimientos_Stock
FOR EACH ROW
BEGIN
    DECLARE stock_actual INT;
    DECLARE variante_activa TINYINT;
    DECLARE producto_activo TINYINT;
    DECLARE mensaje_error VARCHAR(255);
    
    IF NEW.tipo_movimiento = 'ajuste' AND NEW.cantidad < 0 THEN
        -- Usar FOR UPDATE para prevenir race conditions y validar estado activo
        SELECT sv.stock, sv.activo, p.activo 
        INTO stock_actual, variante_activa, producto_activo
        FROM Stock_Variantes sv
        INNER JOIN Productos p ON sv.id_producto = p.id_producto
        WHERE sv.id_variante = NEW.id_variante
        FOR UPDATE;
        
        IF stock_actual IS NULL THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La variante de stock no existe';
        ELSEIF variante_activa = 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'No se puede ajustar una variante inactiva';
        ELSEIF producto_activo = 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'No se puede ajustar stock de un producto inactivo';
        ELSEIF (stock_actual + NEW.cantidad) < 0 THEN
            SET mensaje_error = CONCAT('El ajuste causaría stock negativo. Stock actual: ', CAST(stock_actual AS CHAR), ', Ajuste: ', CAST(NEW.cantidad AS CHAR), ', Resultado: ', CAST((stock_actual + NEW.cantidad) AS CHAR));
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = mensaje_error;
        END IF;
    END IF;
END//

-- Trigger 2: Validar que no haya stock negativo
CREATE TRIGGER trg_validar_stock_antes_update
BEFORE UPDATE ON Stock_Variantes
FOR EACH ROW
BEGIN
    DECLARE mensaje_error VARCHAR(255);
    
    IF NEW.stock < 0 THEN
        SET mensaje_error = CONCAT('No se puede tener stock negativo. Stock actual: ', CAST(OLD.stock AS CHAR), ', Intento: ', CAST(NEW.stock AS CHAR));
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = mensaje_error;
    END IF;
END//

-- Trigger 3: Validar stock disponible antes de crear un movimiento de venta
CREATE TRIGGER trg_validar_stock_disponible_antes_venta
BEFORE INSERT ON Movimientos_Stock
FOR EACH ROW
BEGIN
    DECLARE stock_actual INT;
    DECLARE variante_activa TINYINT;
    DECLARE producto_activo TINYINT;
    DECLARE mensaje_error VARCHAR(255);
    
    -- Validar stock disponible antes de crear un movimiento de venta
    IF NEW.tipo_movimiento = 'venta' THEN
        -- Usar FOR UPDATE para prevenir race conditions y validar estado activo
        SELECT sv.stock, sv.activo, p.activo 
        INTO stock_actual, variante_activa, producto_activo
        FROM Stock_Variantes sv
        INNER JOIN Productos p ON sv.id_producto = p.id_producto
        WHERE sv.id_variante = NEW.id_variante
        FOR UPDATE;
        
        IF stock_actual IS NULL THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'La variante de stock no existe';
        ELSEIF variante_activa = 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'No se puede vender una variante inactiva';
        ELSEIF producto_activo = 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'No se puede vender un producto inactivo';
        ELSEIF stock_actual < NEW.cantidad THEN
            SET mensaje_error = CONCAT('Stock insuficiente. Stock disponible: ', CAST(stock_actual AS CHAR), ', Intento de venta: ', CAST(NEW.cantidad AS CHAR));
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = mensaje_error;
        END IF;
    END IF;
END//

-- Trigger 4: Validar usuario activo al crear pedido
CREATE TRIGGER trg_validar_usuario_activo_pedido
BEFORE INSERT ON Pedidos
FOR EACH ROW
BEGIN
    DECLARE usuario_activo TINYINT;
    
    SELECT activo INTO usuario_activo
    FROM Usuarios
    WHERE id_usuario = NEW.id_usuario;
    
    IF usuario_activo IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'El usuario no existe';
    ELSEIF usuario_activo = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se puede crear un pedido para un usuario inactivo';
    END IF;
END//

-- Trigger 5: Validar categoría activa al crear producto
CREATE TRIGGER trg_validar_categoria_activa_producto
BEFORE INSERT ON Productos
FOR EACH ROW
BEGIN
    DECLARE categoria_activa TINYINT;
    
    SELECT activo INTO categoria_activa
    FROM Categorias
    WHERE id_categoria = NEW.id_categoria;
    
    IF categoria_activa IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La categoría no existe';
    ELSEIF categoria_activa = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se puede crear un producto en una categoría inactiva';
    END IF;
END//

-- Trigger 6: Validar monto > 0 cuando se aprueba pago y prevenir múltiples pagos aprobados
CREATE TRIGGER trg_validar_pago_unico_aprobado
BEFORE INSERT ON Pagos
FOR EACH ROW
BEGIN
    DECLARE pagos_aprobados INT;
    
    -- Validar monto > 0 si se está aprobando el pago
    IF NEW.estado_pago = 'aprobado' AND NEW.monto <= 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se puede aprobar un pago con monto menor o igual a cero';
    END IF;
    
    -- Verificar si ya existe un pago aprobado para este pedido
    -- Usar FOR UPDATE para prevenir race conditions en operaciones concurrentes
    SELECT COUNT(*) INTO pagos_aprobados
    FROM Pagos
    WHERE id_pedido = NEW.id_pedido
      AND estado_pago = 'aprobado'
    FOR UPDATE;
    
    IF pagos_aprobados > 0 AND NEW.estado_pago = 'aprobado' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Ya existe un pago aprobado para este pedido';
    END IF;
END//

-- Trigger 7: Validar monto > 0 al aprobar y prevenir múltiples pagos aprobados al actualizar
CREATE TRIGGER trg_validar_pago_unico_aprobado_update
BEFORE UPDATE ON Pagos
FOR EACH ROW
BEGIN
    DECLARE pagos_aprobados INT;
    
    -- Solo validar si se está aprobando un pago
    IF NEW.estado_pago = 'aprobado' AND OLD.estado_pago != 'aprobado' THEN
        -- Validar monto > 0 cuando se aprueba
        IF NEW.monto <= 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'No se puede aprobar un pago con monto menor o igual a cero';
        END IF;
        
        -- Verificar si ya existe otro pago aprobado para este pedido (excluyendo el actual)
        -- Usar FOR UPDATE para prevenir race conditions en operaciones concurrentes
        SELECT COUNT(*) INTO pagos_aprobados
        FROM Pagos
        WHERE id_pedido = NEW.id_pedido
          AND estado_pago = 'aprobado'
          AND id_pago != NEW.id_pago
        FOR UPDATE;
        
        IF pagos_aprobados > 0 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Ya existe otro pago aprobado para este pedido';
        END IF;
    END IF;
END//

-- Trigger 8: Actualizar estado_pedido cuando se aprueba/rechaza pago
CREATE TRIGGER trg_actualizar_pedido_por_pago
AFTER UPDATE ON Pagos
FOR EACH ROW
BEGIN
    -- Cuando el pago se aprueba, cambiar pedido a preparacion
    IF NEW.estado_pago = 'aprobado' AND OLD.estado_pago != 'aprobado' THEN
        UPDATE Pedidos 
        SET estado_pedido = 'preparacion',
            fecha_actualizacion = NOW()
        WHERE id_pedido = NEW.id_pedido 
          AND estado_pedido = 'pendiente';
    END IF;
    
    -- Cuando el pago se rechaza o cancela, cambiar pedido a cancelado
    -- Solo cancelar si el pedido está en un estado que permita cancelación
    IF NEW.estado_pago IN ('rechazado', 'cancelado') 
       AND OLD.estado_pago != NEW.estado_pago 
       AND OLD.estado_pago IN ('pendiente', 'preparacion') THEN
        UPDATE Pedidos 
        SET estado_pedido = 'cancelado',
            fecha_actualizacion = NOW()
        WHERE id_pedido = NEW.id_pedido
          AND estado_pedido IN ('pendiente', 'preparacion');
    END IF;
END//

-- Trigger 9: Validar que la variante y producto estén activos al crear Detalle_Pedido
CREATE TRIGGER trg_validar_variante_activa_detalle_pedido
BEFORE INSERT ON Detalle_Pedido
FOR EACH ROW
BEGIN
    DECLARE variante_activa TINYINT;
    DECLARE producto_activo TINYINT;
    
    SELECT sv.activo, p.activo 
    INTO variante_activa, producto_activo
    FROM Stock_Variantes sv
    INNER JOIN Productos p ON sv.id_producto = p.id_producto
    WHERE sv.id_variante = NEW.id_variante;
    
    IF variante_activa IS NULL OR producto_activo IS NULL THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La variante o producto no existe';
    ELSEIF variante_activa = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se puede agregar una variante inactiva al pedido';
    ELSEIF producto_activo = 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'No se puede agregar un producto inactivo al pedido';
    END IF;
END//

DELIMITER ;
*/

-- =========================
-- NOTAS DE COMPATIBILIDAD
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
