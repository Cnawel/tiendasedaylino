-- ============================================================================
-- DATOS DE EJEMPLO - SISTEMA E-COMMERCE SEDA Y LINO
-- ============================================================================
-- 
-- Este archivo contiene datos de ejemplo para poblar la base de datos
-- de la tienda online "Seda y Lino". Incluye usuarios, categorías, productos,
-- imágenes, variantes de stock, pedidos y métodos de pago.
-- 
-- IMPORTANTE: 
-- - Ejecutar primero tiendasedaylino.sql para crear la estructura
-- - Estos datos son solo para desarrollo y testing
-- - En producción, usar datos reales de la tienda
-- - Las contraseñas están en texto plano (solo para testing)
-- 
-- @author      Sistema E-commerce Seda y Lino
-- @version     1.0.0
-- @since       2025-01-27
-- @license     MIT
-- 
-- ============================================================================

-- Seleccionar la base de datos
USE tiendasedaylino_db;

-- ============================================================================
-- USUARIOS DE PRUEBA
-- ============================================================================
-- 
-- Datos de usuarios de ejemplo con diferentes roles para testing
-- Incluye clientes y personal de la tienda
-- 
-- NOTA: Las contraseñas están en texto plano solo para desarrollo
-- En producción deben estar hasheadas con password_hash()
-- ============================================================================
INSERT INTO Usuarios (nombre, apellido, email, contrasena, rol, telefono, direccion, localidad, provincia, codigo_postal, fecha_registro)
VALUES
('Ana', 'Martínez', 'ana.martinez@example.com', 'pass123', 'cliente', '1122334455', 'Calle 123', 'CABA', 'Buenos Aires', '1000', NOW()),
('Carlos', 'Gómez', 'carlos.gomez@example.com', 'pass123', 'cliente', '1133445566', 'Av. Siempreviva 742', 'CABA', 'Buenos Aires', '1001', NOW()),
('Lucía', 'Fernández', 'lucia.fernandez@example.com', 'pass123', 'cliente', '1144556677', 'Mitre 500', 'Rosario', 'Santa Fe', '2000', NOW()),
('Javier', 'Pérez', 'javier.perez@example.com', 'pass123', 'marketing', '1155667788', 'Belgrano 800', 'La Plata', 'Buenos Aires', '1900', NOW());

-- ============================================================================
-- CATEGORÍAS DE PRODUCTOS
-- ============================================================================
-- 
-- Categorías principales de la tienda organizadas por tipo de prenda
-- Cada categoría tiene una descripción para SEO y navegación
-- ============================================================================
INSERT INTO Categorias (nombre_categoria, descripcion_categoria)
VALUES
('Camisas', 'Camisas de lino para hombre y mujer'),           -- Categoría 1
('Pantalones', 'Pantalones de lino para hombre y mujer'),     -- Categoría 2  
('Blusas', 'Blusas de seda y lino para mujer'),               -- Categoría 3
('Shorts', 'Shorts de lino y algodón para hombre y mujer');   -- Categoría 4

-- ============================================================================
-- PRODUCTOS DE LA TIENDA
-- ============================================================================
-- 
-- Productos de ejemplo con precios en pesos argentinos
-- Cada producto está asociado a una categoría y tiene un género específico
-- Los precios son realistas para el mercado argentino (2025)
-- ============================================================================
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero)
VALUES
('Camisa Mujer Lino Natural', 'Camisa elegante de lino 100% natural, corte femenino con detalles refinados.', 35000.00, 1, 'mujer'),      -- Producto 1
('Camisa Hombre Lino Clásico', 'Camisa clásica de lino para hombre, corte tradicional con acabados de primera calidad.', 38000.00, 1, 'hombre'), -- Producto 2
('Pantalón Mujer Lino', 'Pantalón de lino recto, ideal para uso casual y elegante.', 42000.00, 2, 'mujer'),              -- Producto 3
('Pantalón Hombre Lino', 'Pantalón de lino para hombre, elegante y cómodo.', 45000.00, 2, 'hombre'),        -- Producto 4
('Blusa Seda Mujer', 'Blusa suelta de seda y lino, cómoda y moderna.', 32000.00, 3, 'mujer'),              -- Producto 5
('Shorts Lino Hombre', 'Shorts de lino y algodón, frescos y prácticos.', 28000.00, 4, 'hombre'),        -- Producto 6
('Shorts Lino Mujer', 'Shorts de lino para mujer, cómodos y versátiles.', 30000.00, 4, 'mujer');        -- Producto 7

-- ============================================================================
-- IMÁGENES DE PRODUCTOS
-- ============================================================================
-- 
-- URLs de imágenes para cada producto (placeholders para desarrollo)
-- En producción, estas serían rutas reales a archivos de imagen
-- 
-- ESTRUCTURA:
-- - foto_prod_miniatura: Imagen pequeña para listados y carritos
-- - foto1_prod: Imagen principal del producto
-- - foto2_prod: Imagen secundaria (opcional)
-- - foto3_prod: Imagen terciaria (opcional)
-- ============================================================================
INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod)
VALUES
(1, 'imagenes/productos/camisas/camisa_mujer_lino_modelo.png', 'imagenes/productos/camisas/camisa_mujer_lino_modelo.png', 'imagenes/productos/camisas/camisa_mujer_lino_modelocerca.png', NULL),     -- Camisa mujer
(2, 'imagenes/productos/camisas/camisa_hombre_lino_modelo.png', 'imagenes/productos/camisas/camisa_hombre_lino_modelo.png', 'imagenes/productos/camisas/camisa_hombre_lino_modelocerca.png', NULL), -- Camisa hombre
(3, 'imagenes/productos/pantalones/pantalon_lino_mujer_modelo_gris.png', 'imagenes/productos/pantalones/pantalon_lino_mujer_modelo_gris.png', 'imagenes/productos/pantalones/pantalon_lino_mujer_modelo_celeste.png', NULL), -- Pantalón mujer
(4, 'imagenes/productos/pantalones/pantalon_hombre_lino_negro.png', 'imagenes/productos/pantalones/pantalon_hombre_lino_negro.png', 'imagenes/productos/pantalones/pantalon_hombre_lino_gris.png', 'imagenes/productos/pantalones/pantalon_hombre_lino_azul.png'), -- Pantalón hombre
(5, 'imagenes/productos/blusas/blusa_mujer_beige.png', 'imagenes/productos/blusas/blusa_mujer_beige.png', NULL, NULL),         -- Blusa mujer
(6, 'imagenes/imagen.png', 'imagenes/imagen.png', NULL, NULL),     -- Shorts hombre
(7, 'imagenes/imagen.png', 'imagenes/imagen.png', NULL, NULL);     -- Shorts mujer

-- ============================================================================
-- VARIANTES DE STOCK
-- ============================================================================
-- 
-- Combinaciones de talla y color disponibles para cada producto
-- con sus respectivas cantidades en stock
-- 
-- ESTRATEGIA DE COLORES:
-- - Mujer: beige, celeste, negro (colores más suaves y femeninos)
-- - Hombre: gris, azul claro, negro (colores más neutros y masculinos)
-- 
-- TALLAS DISPONIBLES: S, M, L, XL (estándar argentino)
-- ============================================================================
INSERT INTO Stock_Variantes (id_producto, talle, color, stock)
VALUES
-- ===== CAMISA MUJER (ID: 1) =====
(1, 'S', 'natural', 8),      -- Variante 1
(1, 'M', 'natural', 12),     -- Variante 2
(1, 'L', 'natural', 10),     -- Variante 3
(1, 'XL', 'natural', 6),     -- Variante 4

-- ===== CAMISA HOMBRE (ID: 2) =====
(2, 'S', 'natural', 5),      -- Variante 5
(2, 'M', 'natural', 15),     -- Variante 6
(2, 'L', 'natural', 12),     -- Variante 7
(2, 'XL', 'natural', 8),     -- Variante 8

-- ===== PANTALÓN MUJER (ID: 3) =====
(3, 'S', 'gris', 7),         -- Variante 9
(3, 'M', 'gris', 10),        -- Variante 10
(3, 'L', 'gris', 8),         -- Variante 11
(3, 'S', 'celeste', 5),      -- Variante 12
(3, 'M', 'celeste', 9),      -- Variante 13
(3, 'L', 'celeste', 6),      -- Variante 14

-- ===== PANTALÓN HOMBRE (ID: 4) =====
(4, 'S', 'negro', 6),        -- Variante 15
(4, 'M', 'negro', 12),       -- Variante 16
(4, 'L', 'negro', 10),       -- Variante 17
(4, 'XL', 'negro', 7),       -- Variante 18
(4, 'S', 'gris', 4),         -- Variante 19
(4, 'M', 'gris', 8),         -- Variante 20
(4, 'L', 'gris', 9),         -- Variante 21
(4, 'XL', 'gris', 5),        -- Variante 22
(4, 'S', 'azul', 3),         -- Variante 23
(4, 'M', 'azul', 7),         -- Variante 24
(4, 'L', 'azul', 6),         -- Variante 25
(4, 'XL', 'azul', 4),        -- Variante 26

-- ===== BLUSA MUJER (ID: 5) =====
(5, 'S', 'beige', 8),        -- Variante 27
(5, 'M', 'beige', 12),       -- Variante 28
(5, 'L', 'beige', 10),       -- Variante 29
(5, 'XL', 'beige', 6),       -- Variante 30

-- ===== SHORTS HOMBRE (ID: 6) =====
(6, 'S', 'natural', 10),     -- Variante 31
(6, 'M', 'natural', 15),     -- Variante 32
(6, 'L', 'natural', 12),     -- Variante 33
(6, 'XL', 'natural', 8),     -- Variante 34

-- ===== SHORTS MUJER (ID: 7) =====
(7, 'S', 'natural', 8),      -- Variante 35
(7, 'M', 'natural', 12),     -- Variante 36
(7, 'L', 'natural', 10),     -- Variante 37
(7, 'XL', 'natural', 6);     -- Variante 38

-- ============================================================================
-- PEDIDOS DE EJEMPLO
-- ============================================================================
-- 
-- Pedidos de prueba con diferentes estados para testing del sistema
-- Simula el flujo completo de un pedido desde creación hasta entrega
-- 
-- ESTADOS DISPONIBLES: pendiente, pagado, enviado, entregado, cancelado
-- ============================================================================
INSERT INTO Pedidos (id_usuario, fecha_pedido, estado_pedido)
VALUES
(1, NOW(), 'pendiente'),     -- Pedido 1: Ana Martínez - Recién creado
(2, NOW(), 'pagado'),        -- Pedido 2: Carlos Gómez - Pagado, esperando envío
(3, NOW(), 'enviado'),       -- Pedido 3: Lucía Fernández - En tránsito
(1, NOW(), 'entregado');     -- Pedido 4: Ana Martínez - Completado

-- ============================================================================
-- DETALLES DE PEDIDOS
-- ============================================================================
-- 
-- Items específicos de cada pedido con sus variantes, cantidades y precios
-- Cada detalle se vincula a una variante específica de stock
-- 
-- NOTA: Los precios se guardan al momento del pedido para mantener
-- el precio histórico aunque el producto cambie de precio después
-- ============================================================================
INSERT INTO Detalle_Pedido (id_pedido, id_variante, cantidad, precio_unitario)
VALUES
-- Pedido 1: Ana Martínez - 2 camisas mujer natural M + 1 pantalón mujer gris M
(1, 2, 2, 35000.00),     -- 2x Camisa mujer natural M (variante 2)
(1, 10, 1, 42000.00),    -- 1x Pantalón mujer gris M (variante 10)

-- Pedido 2: Carlos Gómez - 1 blusa beige M
(2, 28, 1, 32000.00),    -- 1x Blusa beige M (variante 28)

-- Pedido 3: Lucía Fernández - 2 shorts hombre natural M
(3, 32, 2, 28000.00);    -- 2x Shorts hombre natural M (variante 32)

-- ============================================================================
-- MÉTODOS DE PAGO
-- ============================================================================
-- 
-- Formas de pago aceptadas por la tienda
-- Incluye métodos tradicionales y digitales populares en Argentina
-- ============================================================================
INSERT INTO Forma_Pagos (nombre, descripcion)
VALUES
('Efectivo', 'Pago en efectivo contra entrega'),                    -- Pago 1
('Tarjeta de Crédito', 'Visa, Mastercard, Amex'),                   -- Pago 2
('Transferencia Bancaria', 'CBU/alias'),                           -- Pago 3
('MercadoPago', 'Pago online con billetera virtual');              -- Pago 4

-- ============================================================================
-- REGISTRO DE PAGOS
-- ============================================================================
-- 
-- Registro de pagos realizados para cada pedido
-- Vincula pedidos con métodos de pago y su estado
-- 
-- ESTADOS DE PAGO: pendiente, aprobado, rechazado, cancelado
-- ============================================================================
INSERT INTO Pagos (id_pedido, id_forma_pago, estado_pago)
VALUES
(1, 2, 'pendiente'),     -- Pedido 1: Tarjeta de Crédito - Pendiente
(2, 4, 'aprobado'),      -- Pedido 2: MercadoPago - Aprobado
(3, 3, 'aprobado'),      -- Pedido 3: Transferencia - Aprobado
(4, 1, 'aprobado');      -- Pedido 4: Efectivo - Aprobado

-- ============================================================================
-- MOVIMIENTOS DE STOCK
-- ============================================================================
-- 
-- Registro de todos los movimientos de stock para auditoría y control
-- Incluye ventas, ajustes, devoluciones e ingresos
-- 
-- TIPOS DE MOVIMIENTO: venta, ajuste, devolucion, ingreso
-- 
-- NOTA: Los movimientos de venta deben coincidir con los detalles de pedido
-- ============================================================================
INSERT INTO Movimientos_Stock (id_variante, tipo_movimiento, cantidad, id_usuario, observaciones)
VALUES
-- Movimientos de venta (coinciden con Detalle_Pedido)
(1, 'venta', 2, 1, 'Venta Camisa gris M - Pedido #1'),        -- 2x Camisa gris M
(5, 'venta', 1, 2, 'Venta Pantalón beige M - Pedido #1'),    -- 1x Pantalón beige M
(9, 'venta', 1, 3, 'Venta Blusa celeste M - Pedido #2'),     -- 1x Blusa celeste M
(13, 'ajuste', 5, 4, 'Ajuste inicial de Shorts negro M');    -- Ajuste de stock inicial
