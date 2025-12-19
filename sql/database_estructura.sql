SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET character_set_connection = utf8mb4;

CREATE DATABASE IF NOT EXISTS if0_40082852_tiendasedaylino
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE if0_40082852_tiendasedaylino;

CREATE TABLE IF NOT EXISTS Preguntas_Recupero (
    id_pregunta INT AUTO_INCREMENT PRIMARY KEY,
    texto_pregunta VARCHAR(100) NOT NULL,
    activa TINYINT NOT NULL DEFAULT 1,
    orden INT NOT NULL DEFAULT 0,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NULL,
    apellido VARCHAR(100) NULL,
    email VARCHAR(100) NULL UNIQUE,
    contrasena VARCHAR(255) NULL,
    rol ENUM('cliente','admin','marketing','ventas') NOT NULL DEFAULT 'cliente',
    activo TINYINT NOT NULL DEFAULT 1,
    telefono VARCHAR(20) NULL,
    direccion VARCHAR(100) NULL,
    localidad VARCHAR(100) NULL,
    provincia VARCHAR(100) NULL,
    codigo_postal VARCHAR(10) NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    fecha_nacimiento DATE NULL,
    pregunta_recupero INT NULL,
    respuesta_recupero VARCHAR(255) NULL,
    FOREIGN KEY (pregunta_recupero) REFERENCES Preguntas_Recupero(id_pregunta) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Categorias (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre_categoria VARCHAR(100) NOT NULL,
    activo TINYINT NOT NULL DEFAULT 1,
    descripcion_categoria VARCHAR(255) NULL,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Productos (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    nombre_producto VARCHAR(100) NOT NULL,
    descripcion_producto VARCHAR(255) NULL,
    precio_actual DECIMAL(10,2) NOT NULL,
    id_categoria INT NOT NULL,
    genero ENUM('hombre','mujer','unisex') NOT NULL DEFAULT 'unisex',
    activo TINYINT NOT NULL DEFAULT 1,
    sku VARCHAR(50) NULL UNIQUE,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_categoria) REFERENCES Categorias(id_categoria) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_productos_precio_positivo CHECK (precio_actual >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Fotos_Producto (
    id_foto INT AUTO_INCREMENT PRIMARY KEY,
    id_producto INT NOT NULL,
    foto_prod_miniatura VARCHAR(255) NULL,
    foto1_prod VARCHAR(255) NULL,
    foto2_prod VARCHAR(255) NULL,
    foto3_prod VARCHAR(255) NULL,
    color VARCHAR(50) NULL,
    activo TINYINT NOT NULL DEFAULT 1,
    FOREIGN KEY (id_producto) REFERENCES Productos(id_producto) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Stock_Variantes (
    id_variante INT AUTO_INCREMENT PRIMARY KEY,
    id_producto INT NOT NULL,
    talle VARCHAR(50) NOT NULL,
    color VARCHAR(50) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    activo TINYINT NOT NULL DEFAULT 1,
    fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_producto) REFERENCES Productos(id_producto) ON DELETE RESTRICT ON UPDATE CASCADE,
    UNIQUE KEY uk_variante_producto (id_producto, talle, color),
    CONSTRAINT chk_stock_variantes_stock_positivo CHECK (stock >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Pedidos (
    id_pedido INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NULL,
    fecha_pedido DATETIME NOT NULL,
    estado_pedido ENUM('pendiente','preparacion','en_viaje','completado','devolucion','cancelado') NOT NULL DEFAULT 'pendiente',
    total DECIMAL(10,2) NULL,
    direccion_entrega VARCHAR(100) NULL,
    telefono_contacto VARCHAR(20) NULL,
    observaciones VARCHAR(500) NULL,
    fecha_actualizacion DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES Usuarios(id_usuario) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS Forma_Pagos (
    id_forma_pago INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    activo TINYINT NOT NULL DEFAULT 1,
    descripcion VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Pagos (
    id_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_forma_pago INT NOT NULL,
    numero_transaccion VARCHAR(100) NULL UNIQUE,
    estado_pago ENUM('pendiente','pendiente_aprobacion','aprobado','rechazado','cancelado') NOT NULL DEFAULT 'pendiente',
    monto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    fecha_pago DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_aprobacion DATETIME NULL,
    motivo_rechazo VARCHAR(500) NULL,
    fecha_actualizacion DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pedido) REFERENCES Pedidos(id_pedido) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_forma_pago) REFERENCES Forma_Pagos(id_forma_pago) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_pagos_monto_positivo CHECK (monto >= 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS Movimientos_Stock (
    id_movimiento INT AUTO_INCREMENT PRIMARY KEY,
    id_variante INT NOT NULL,
    tipo_movimiento ENUM('venta','ajuste','devolucion','ingreso') NOT NULL,
    cantidad INT NOT NULL,
    fecha_movimiento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_usuario INT NULL,
    id_pedido INT NULL,
    observaciones VARCHAR(500) NULL,
    FOREIGN KEY (id_variante) REFERENCES Stock_Variantes(id_variante) ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (id_usuario) REFERENCES Usuarios(id_usuario) ON DELETE SET NULL ON UPDATE CASCADE,
    FOREIGN KEY (id_pedido) REFERENCES Pedidos(id_pedido) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_movimientos_variante_fecha ON Movimientos_Stock(id_variante, fecha_movimiento);
