-- =========================
-- SQL Tienda Seda y Lino
-- =========================

CREATE DATABASE IF NOT EXISTS tiendasedaylino_db;
USE tiendasedaylino_db;

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
    telefono VARCHAR(30) NULL,
    direccion VARCHAR(255) NULL,
    localidad VARCHAR(100) NULL,
    provincia VARCHAR(100) NULL,
    codigo_postal VARCHAR(15) NULL,
    fecha_registro DATETIME NOT NULL
);

-- =========================
-- Tabla Categorias
-- =========================
CREATE TABLE IF NOT EXISTS Categorias (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre_categoria VARCHAR(100) NOT NULL,
    descripcion_categoria TEXT NULL
);

-- =========================
-- Tabla Productos
-- =========================
CREATE TABLE IF NOT EXISTS Productos (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    nombre_producto VARCHAR(150) NOT NULL,
    descripcion_producto TEXT NULL,
    precio_actual DECIMAL(10,2) NOT NULL,
    id_categoria INT NOT NULL,
    genero ENUM('hombre','mujer','unisex') NOT NULL DEFAULT 'unisex',
    FOREIGN KEY (id_categoria) REFERENCES Categorias(id_categoria)
);

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
    FOREIGN KEY (id_producto) REFERENCES Productos(id_producto)
);

-- =========================
-- Tabla Stock_Variantes
-- =========================
CREATE TABLE IF NOT EXISTS Stock_Variantes (
    id_variante INT AUTO_INCREMENT PRIMARY KEY,
    id_producto INT NOT NULL,
    talle VARCHAR(10) NOT NULL,
    color VARCHAR(50) NOT NULL,
    stock INT NOT NULL DEFAULT 0, -- cache del stock actual (se mantiene con Movimientos_Stock)
    FOREIGN KEY (id_producto) REFERENCES Productos(id_producto)
);

-- =========================
-- Tabla Pedidos
-- =========================
CREATE TABLE IF NOT EXISTS Pedidos (
    id_pedido INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT NOT NULL,
    fecha_pedido DATETIME NOT NULL,
    estado_pedido ENUM('pendiente','pagado','enviado','entregado','cancelado') NOT NULL DEFAULT 'pendiente',
    FOREIGN KEY (id_usuario) REFERENCES Usuarios(id_usuario)
);

-- =========================
-- Tabla Detalle_Pedido
-- =========================
CREATE TABLE IF NOT EXISTS Detalle_Pedido (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_variante INT NOT NULL,
    cantidad INT NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_pedido) REFERENCES Pedidos(id_pedido),
    FOREIGN KEY (id_variante) REFERENCES Stock_Variantes(id_variante)
);

-- =========================
-- Tabla Forma_Pagos
-- =========================
CREATE TABLE IF NOT EXISTS Forma_Pagos (
    id_forma_pago INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255) NULL
);

-- =========================
-- Tabla Pagos
-- =========================
CREATE TABLE IF NOT EXISTS Pagos (
    id_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_pedido INT NOT NULL,
    id_forma_pago INT NOT NULL,
    estado_pago ENUM('pendiente','aprobado','rechazado','cancelado') NOT NULL DEFAULT 'pendiente',
    fecha_pago DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_pedido) REFERENCES Pedidos(id_pedido),
    FOREIGN KEY (id_forma_pago) REFERENCES Forma_Pagos(id_forma_pago)
);

-- =========================
-- Tabla Movimientos_Stock
-- =========================
CREATE TABLE IF NOT EXISTS Movimientos_Stock (
    id_movimiento INT AUTO_INCREMENT PRIMARY KEY,
    id_variante INT NOT NULL,
    tipo_movimiento ENUM('venta','ajuste','devolucion','ingreso') NOT NULL,
    cantidad INT NOT NULL,
    fecha_movimiento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    id_usuario INT NULL,
    observaciones TEXT NULL,
    FOREIGN KEY (id_variante) REFERENCES Stock_Variantes(id_variante),
    FOREIGN KEY (id_usuario) REFERENCES Usuarios(id_usuario)
);
