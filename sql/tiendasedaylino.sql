-- ============================================================================
-- ESQUEMA DE BASE DE DATOS - SISTEMA E-COMMERCE SEDA Y LINO
-- ============================================================================
-- 
-- Este archivo contiene la definición completa del esquema de base de datos
-- para el sistema de e-commerce "Seda y Lino". Incluye todas las tablas,
-- relaciones, índices y restricciones necesarias para el funcionamiento
-- del sistema de tienda online.
-- 
-- CARACTERÍSTICAS:
-- - Diseño normalizado (3NF) para evitar redundancia
-- - Relaciones bien definidas con claves foráneas
-- - Soporte para múltiples variantes de productos (talla/color)
-- - Sistema de auditoría con movimientos de stock
-- - Gestión completa de pedidos y pagos
-- 
-- @author      Sistema E-commerce Seda y Lino
-- @version     1.0.0
-- @since       2025-01-27
-- @license     MIT
-- 
-- ============================================================================

-- Crear la base de datos si no existe
CREATE DATABASE IF NOT EXISTS tiendasedaylino_db;

-- Seleccionar la base de datos para las operaciones siguientes
USE tiendasedaylino_db;

-- ============================================================================
-- TABLA: USUARIOS
-- ============================================================================
-- 
-- Almacena información de todos los usuarios del sistema: clientes y personal
-- Incluye datos personales, credenciales de acceso y roles de usuario
-- 
-- ROLES DISPONIBLES:
-- - cliente: Usuario final que compra productos
-- - admin: Administrador completo del sistema
-- - marketing: Personal de marketing (reportes, promociones)
-- - ventas: Personal de ventas (gestión de pedidos)
-- 
-- SEGURIDAD:
-- - Email único para evitar duplicados
-- - Contraseña con hash (VARCHAR 255 para password_hash())
-- - Campos opcionales para flexibilidad en registro
-- ============================================================================
CREATE TABLE IF NOT EXISTS Usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,                    -- Clave primaria auto-incremental
    nombre VARCHAR(100) NOT NULL,                                 -- Nombre del usuario
    apellido VARCHAR(100) NOT NULL,                               -- Apellido del usuario
    email VARCHAR(150) UNIQUE NOT NULL,                           -- Email único (usado como login)
    contrasena VARCHAR(255) NOT NULL,                             -- Hash de la contraseña
    rol ENUM('cliente','admin','marketing','ventas') NOT NULL DEFAULT 'cliente', -- Rol del usuario
    telefono VARCHAR(30) NULL,                                    -- Teléfono de contacto
    direccion VARCHAR(255) NULL,                                  -- Dirección de envío
    localidad VARCHAR(100) NULL,                                  -- Ciudad/localidad
    provincia VARCHAR(100) NULL,                                  -- Provincia/estado
    codigo_postal VARCHAR(15) NULL,                               -- Código postal
    fecha_registro DATETIME NOT NULL                              -- Fecha de registro en el sistema
);

-- ============================================================================
-- TABLA: CATEGORÍAS
-- ============================================================================
-- 
-- Categorías de productos para organización y navegación
-- Permite agrupar productos por tipo (camisas, pantalones, etc.)
-- 
-- USO:
-- - Navegación por categorías en el frontend
-- - Filtrado de productos
-- - SEO y estructura de URLs
-- ============================================================================
CREATE TABLE IF NOT EXISTS Categorias (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,                    -- Clave primaria auto-incremental
    nombre_categoria VARCHAR(100) NOT NULL,                         -- Nombre de la categoría
    descripcion_categoria TEXT NULL                                 -- Descripción para SEO
);

-- ============================================================================
-- TABLA: PRODUCTOS
-- ============================================================================
-- 
-- Información básica de los productos de la tienda
-- Contiene datos comunes a todas las variantes del producto
-- 
-- CARACTERÍSTICAS:
-- - Precio actual (puede cambiar, se guarda histórico en pedidos)
-- - Género objetivo del producto
-- - Relación con categoría para organización
-- 
-- NOTA: Las variantes específicas (talla/color) están en Stock_Variantes
-- ============================================================================
CREATE TABLE IF NOT EXISTS Productos (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,                    -- Clave primaria auto-incremental
    nombre_producto VARCHAR(150) NOT NULL,                         -- Nombre comercial del producto
    descripcion_producto TEXT NULL,                                -- Descripción detallada
    precio_actual DECIMAL(10,2) NOT NULL,                          -- Precio actual (máximo 99,999,999.99)
    id_categoria INT NOT NULL,                                     -- Categoría a la que pertenece
    genero ENUM('hombre','mujer','unisex') NOT NULL DEFAULT 'unisex', -- Género objetivo
    FOREIGN KEY (id_categoria) REFERENCES Categorias(id_categoria) -- Relación con categorías
);

-- ============================================================================
-- TABLA: FOTOS_PRODUCTO
-- ============================================================================
-- 
-- Almacena las imágenes asociadas a cada producto
-- Permite múltiples imágenes por producto para galerías
-- 
-- ESTRUCTURA DE IMÁGENES:
-- - foto_prod_miniatura: Imagen pequeña para listados y carritos
-- - foto1_prod: Imagen principal del producto
-- - foto2_prod: Imagen secundaria (opcional)
-- - foto3_prod: Imagen terciaria (opcional)
-- 
-- NOTA: Las rutas son relativas al directorio de imágenes del sitio
-- ============================================================================
CREATE TABLE IF NOT EXISTS Fotos_Producto (
    id_foto INT AUTO_INCREMENT PRIMARY KEY,                    -- Clave primaria auto-incremental
    id_producto INT NOT NULL,                                 -- Producto al que pertenecen las fotos
    foto_prod_miniatura VARCHAR(255) NULL,                    -- Ruta de imagen miniatura
    foto1_prod VARCHAR(255) NULL,                             -- Ruta de imagen principal
    foto2_prod VARCHAR(255) NULL,                             -- Ruta de imagen secundaria
    foto3_prod VARCHAR(255) NULL,                             -- Ruta de imagen terciaria
    FOREIGN KEY (id_producto) REFERENCES Productos(id_producto) -- Relación con productos
);

-- ============================================================================
-- TABLA: STOCK_VARIANTES
-- ============================================================================
-- 
-- Variantes específicas de cada producto (combinación talla/color)
-- Contiene el stock actual de cada variante
-- 
-- CARACTERÍSTICAS:
-- - Una fila por cada combinación talla/color de un producto
-- - Stock actual como cache (se mantiene con Movimientos_Stock)
-- - Permite diferentes colores y tallas por producto
-- 
-- OPTIMIZACIÓN:
-- - El stock se actualiza automáticamente con cada movimiento
-- - Evita consultas complejas para obtener stock actual
-- ============================================================================
CREATE TABLE IF NOT EXISTS Stock_Variantes (
    id_variante INT AUTO_INCREMENT PRIMARY KEY,                -- Clave primaria auto-incremental
    id_producto INT NOT NULL,                                 -- Producto al que pertenece la variante
    talle VARCHAR(10) NOT NULL,                               -- Talla (S, M, L, XL, etc.)
    color VARCHAR(50) NOT NULL,                               -- Color de la variante
    stock INT NOT NULL DEFAULT 0,                             -- Stock actual (cache)
    FOREIGN KEY (id_producto) REFERENCES Productos(id_producto) -- Relación con productos
);

-- ============================================================================
-- TABLA: PEDIDOS
-- ============================================================================
-- 
-- Encabezado de pedidos realizados por los usuarios
-- Contiene información general del pedido y su estado
-- 
-- ESTADOS DEL PEDIDO:
-- - pendiente: Pedido creado, esperando pago
-- - pagado: Pago confirmado, preparando envío
-- - enviado: En tránsito hacia el cliente
-- - entregado: Pedido completado exitosamente
-- - cancelado: Pedido cancelado (por cliente o sistema)
-- 
-- NOTA: Los detalles específicos están en Detalle_Pedido
-- ============================================================================
CREATE TABLE IF NOT EXISTS Pedidos (
    id_pedido INT AUTO_INCREMENT PRIMARY KEY,                    -- Clave primaria auto-incremental
    id_usuario INT NOT NULL,                                     -- Usuario que realizó el pedido
    fecha_pedido DATETIME NOT NULL,                              -- Fecha y hora del pedido
    estado_pedido ENUM('pendiente','pagado','enviado','entregado','cancelado') NOT NULL DEFAULT 'pendiente', -- Estado actual
    FOREIGN KEY (id_usuario) REFERENCES Usuarios(id_usuario)     -- Relación con usuarios
);

-- ============================================================================
-- TABLA: DETALLE_PEDIDO
-- ============================================================================
-- 
-- Items específicos de cada pedido con sus variantes y cantidades
-- Permite múltiples productos por pedido con diferentes variantes
-- 
-- CARACTERÍSTICAS:
-- - Precio unitario guardado al momento del pedido (precio histórico)
-- - Cantidad específica de cada variante
-- - Relación con variante específica (talla/color)
-- 
-- IMPORTANTE: El precio se guarda aquí para mantener el precio histórico
-- aunque el producto cambie de precio después
-- ============================================================================
CREATE TABLE IF NOT EXISTS Detalle_Pedido (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,                -- Clave primaria auto-incremental
    id_pedido INT NOT NULL,                                   -- Pedido al que pertenece el detalle
    id_variante INT NOT NULL,                                 -- Variante específica del producto
    cantidad INT NOT NULL,                                    -- Cantidad solicitada
    precio_unitario DECIMAL(10,2) NOT NULL,                   -- Precio unitario al momento del pedido
    FOREIGN KEY (id_pedido) REFERENCES Pedidos(id_pedido),    -- Relación con pedidos
    FOREIGN KEY (id_variante) REFERENCES Stock_Variantes(id_variante) -- Relación con variantes
);

-- ============================================================================
-- TABLA: FORMA_PAGOS
-- ============================================================================
-- 
-- Métodos de pago aceptados por la tienda
-- Configuración flexible para agregar/quitar métodos de pago
-- 
-- USO:
-- - Configuración de métodos de pago disponibles
-- - Descripción para mostrar al usuario
-- - Relación con pagos específicos
-- ============================================================================
CREATE TABLE IF NOT EXISTS Forma_Pagos (
    id_forma_pago INT AUTO_INCREMENT PRIMARY KEY,            -- Clave primaria auto-incremental
    nombre VARCHAR(100) NOT NULL,                             -- Nombre del método de pago
    descripcion VARCHAR(255) NULL                             -- Descripción para el usuario
);

-- ============================================================================
-- TABLA: PAGOS
-- ============================================================================
-- 
-- Registro de pagos realizados para cada pedido
-- Vincula pedidos con métodos de pago y su estado
-- 
-- ESTADOS DEL PAGO:
-- - pendiente: Pago iniciado, esperando confirmación
-- - aprobado: Pago confirmado y procesado
-- - rechazado: Pago rechazado (tarjeta, fondos insuficientes, etc.)
-- - cancelado: Pago cancelado por el usuario
-- 
-- NOTA: Un pedido puede tener múltiples intentos de pago
-- ============================================================================
CREATE TABLE IF NOT EXISTS Pagos (
    id_pago INT AUTO_INCREMENT PRIMARY KEY,                    -- Clave primaria auto-incremental
    id_pedido INT NOT NULL,                                     -- Pedido al que corresponde el pago
    id_forma_pago INT NOT NULL,                                 -- Método de pago utilizado
    estado_pago ENUM('pendiente','aprobado','rechazado','cancelado') NOT NULL DEFAULT 'pendiente', -- Estado del pago
    fecha_pago DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,     -- Fecha y hora del pago
    FOREIGN KEY (id_pedido) REFERENCES Pedidos(id_pedido),      -- Relación con pedidos
    FOREIGN KEY (id_forma_pago) REFERENCES Forma_Pagos(id_forma_pago) -- Relación con formas de pago
);

-- ============================================================================
-- TABLA: MOVIMIENTOS_STOCK
-- ============================================================================
-- 
-- Auditoría completa de todos los movimientos de stock
-- Permite rastrear cambios en el inventario y generar reportes
-- 
-- TIPOS DE MOVIMIENTO:
-- - venta: Reducción por venta de productos
-- - ajuste: Corrección manual del stock
-- - devolucion: Aumento por devolución de productos
-- - ingreso: Aumento por compra de mercadería
-- 
-- CARACTERÍSTICAS:
-- - Registro de quién realizó el movimiento (id_usuario)
-- - Observaciones para contexto del movimiento
-- - Fecha y hora automática del movimiento
-- - Cantidad puede ser positiva o negativa según el tipo
-- 
-- IMPORTANTE: Esta tabla mantiene el stock actual en Stock_Variantes
-- ============================================================================
CREATE TABLE IF NOT EXISTS Movimientos_Stock (
    id_movimiento INT AUTO_INCREMENT PRIMARY KEY,            -- Clave primaria auto-incremental
    id_variante INT NOT NULL,                                -- Variante afectada por el movimiento
    tipo_movimiento ENUM('venta','ajuste','devolucion','ingreso') NOT NULL, -- Tipo de movimiento
    cantidad INT NOT NULL,                                    -- Cantidad del movimiento (puede ser negativa)
    fecha_movimiento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, -- Fecha y hora del movimiento
    id_usuario INT NULL,                                      -- Usuario que realizó el movimiento (opcional)
    observaciones TEXT NULL,                                  -- Observaciones del movimiento
    FOREIGN KEY (id_variante) REFERENCES Stock_Variantes(id_variante), -- Relación con variantes
    FOREIGN KEY (id_usuario) REFERENCES Usuarios(id_usuario) -- Relación con usuarios
);
