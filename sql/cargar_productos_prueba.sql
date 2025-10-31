-- =========================
-- CARGAR PRODUCTOS DE PRUEBA - Tienda Seda y Lino
-- =========================
-- Este script carga productos basado en las imágenes disponibles en imagenes/productos/
-- La miniatura (foto_prod_miniatura) es la imagen solo de producto (sin modelo ni grupal)
-- Las imágenes grupales en la raíz de cada categoría son para tarjetas de categoría
-- =========================

USE tiendasedaylino_db;

-- Configuración de codificación UTF-8
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET character_set_connection = utf8mb4;

-- =========================
-- INSERTAR CATEGORÍAS (si no existen)
-- =========================
INSERT IGNORE INTO Categorias (nombre_categoria, descripcion_categoria) VALUES
('Camisas', 'Camisas de seda y lino para hombre y mujer'),
('Blusas', 'Blusas elegantes de seda y lino para mujer'),
('Pantalones', 'Pantalones de lino cómodos y elegantes'),
('Shorts', 'Shorts de lino para hombre y mujer');

-- =========================
-- BLUSAS (Mujer)
-- =========================
-- Blusa Azul
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero) VALUES
('Blusa de Seda Manga Larga Azul', 'Hermosa blusa de seda manga larga en color azul, perfecta para ocasiones elegantes y el trabajo.', 12990.00, 
 (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Blusas' LIMIT 1), 
 'mujer');

SET @producto_blusa_azul = LAST_INSERT_ID();

INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color) VALUES
(@producto_blusa_azul, 
 'imagenes/productos/blusas/azul/blusa_mujer_mangalarga_azul.png', -- Miniatura: solo producto, sin modelo
 'imagenes/productos/blusas/azul/blusa_azul_modelo.png', -- Modelo
 'imagenes/productos/blusas/azul/blusa_mujer_mangalarga_azul - Copy.png', -- Copia
 NULL,
 'azul');

INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES
(@producto_blusa_azul, 'S', 'azul', 5),
(@producto_blusa_azul, 'M', 'azul', 5),
(@producto_blusa_azul, 'L', 'azul', 5),
(@producto_blusa_azul, 'XL', 'azul', 0); -- Sin stock

-- Blusa Blanca
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero) VALUES
('Blusa de Seda Manga Larga Blanca', 'Elegante blusa de seda manga larga en color blanco/crema, ideal para el día a día y eventos especiales.', 12490.00, 
 (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Blusas' LIMIT 1), 
 'mujer');

SET @producto_blusa_blanca = LAST_INSERT_ID();

INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color) VALUES
(@producto_blusa_blanca, 
 'imagenes/productos/blusas/blanca/blusa_crema_mini.jpg.png', -- Miniatura: tiene "mini" en el nombre
 'imagenes/productos/blusas/blanca/blusa_crema_modelo.png', -- Modelo
 'imagenes/productos/blusas/blanca/blusa_crema1.jpg.png', -- Otra foto
 'imagenes/productos/blusas/blanca/blusa_mujer_mangalarga_blanco.png', -- Solo producto
 'blanca');

INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES
(@producto_blusa_blanca, 'S', 'blanca', 5),
(@producto_blusa_blanca, 'M', 'blanca', 5),
(@producto_blusa_blanca, 'L', 'blanca', 0), -- Sin stock
(@producto_blusa_blanca, 'XL', 'blanca', 5);

-- Blusa Celeste
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero) VALUES
('Blusa de Seda Modelo Simple Celeste', 'Blusa de seda modelo simple en color celeste, fresca y cómoda para el verano.', 10990.00, 
 (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Blusas' LIMIT 1), 
 'mujer');

SET @producto_blusa_celeste = LAST_INSERT_ID();

INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color) VALUES
(@producto_blusa_celeste, 
 'imagenes/productos/blusas/celeste/blusa_mujer_modelosimple_celeste.png', -- Única imagen disponible (modelo simple)
 NULL,
 NULL,
 NULL,
 'celeste');

INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES
(@producto_blusa_celeste, 'S', 'celeste', 5),
(@producto_blusa_celeste, 'M', 'celeste', 5),
(@producto_blusa_celeste, 'L', 'celeste', 0); -- Sin stock

-- =========================
-- CAMISAS
-- =========================
-- Camisa Hombre Lino Natural
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero) VALUES
('Camisa de Lino para Hombre Natural', 'Camisa de lino natural para hombre, fresca y elegante, perfecta para el verano y el trabajo.', 13990.00, 
 (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Camisas' LIMIT 1), 
 'hombre');

SET @producto_camisa_hombre = LAST_INSERT_ID();

INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color) VALUES
(@producto_camisa_hombre, 
 'imagenes/productos/camisas/hombre/natural/camisa_hombre_lino_modelocerca.png', -- Miniatura: modelo cerca, no tiene "modelo" completo
 'imagenes/productos/camisas/hombre/natural/camisa_hombre_lino_modelo.png', -- Modelo completo
 NULL,
 NULL,
 'natural');

INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES
(@producto_camisa_hombre, 'S', 'natural', 5),
(@producto_camisa_hombre, 'M', 'natural', 5),
(@producto_camisa_hombre, 'L', 'natural', 5),
(@producto_camisa_hombre, 'XL', 'natural', 0); -- Sin stock

-- Camisa Mujer Lino Natural
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero) VALUES
('Camisa de Lino para Mujer Natural', 'Camisa de lino natural para mujer, cómoda y versátil, ideal para combinar con cualquier outfit.', 13490.00, 
 (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Camisas' LIMIT 1), 
 'mujer');

SET @producto_camisa_mujer = LAST_INSERT_ID();

INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color) VALUES
(@producto_camisa_mujer, 
 'imagenes/productos/camisas/mujer/natural/camisa_mujer_lino_modelocerca.png', -- Miniatura: modelo cerca
 'imagenes/productos/camisas/mujer/natural/camisa_mujer_lino_modelo.png', -- Modelo completo
 NULL,
 NULL,
 'natural');

INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES
(@producto_camisa_mujer, 'S', 'natural', 5),
(@producto_camisa_mujer, 'M', 'natural', 5),
(@producto_camisa_mujer, 'L', 'natural', 0), -- Sin stock
(@producto_camisa_mujer, 'XL', 'natural', 5);

-- =========================
-- PANTALONES HOMBRE
-- =========================
-- Pantalón Hombre Azul
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero) VALUES
('Pantalón de Lino para Hombre Azul', 'Pantalón de lino azul para hombre, cómodo y elegante, perfecto para el día a día y ocasiones especiales.', 14990.00, 
 (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Pantalones' LIMIT 1), 
 'hombre');

SET @producto_pantalon_hombre_azul = LAST_INSERT_ID();

INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color) VALUES
(@producto_pantalon_hombre_azul, 
 'imagenes/productos/pantalones/hombre/azul/pantalon_hombre_lino_azul.png', -- Miniatura: solo producto, sin modelo
 'imagenes/productos/pantalones/hombre/azul/pantalon_hombre_lino_azul_modelo2.png', -- Modelo 2
 'imagenes/productos/pantalones/hombre/azul/pantalon_hombre_lino_azul_modelo3.png', -- Modelo 3
 NULL,
 'azul');

INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES
(@producto_pantalon_hombre_azul, '42', 'azul', 5),
(@producto_pantalon_hombre_azul, '44', 'azul', 5),
(@producto_pantalon_hombre_azul, '46', 'azul', 5),
(@producto_pantalon_hombre_azul, '48', 'azul', 0); -- Sin stock

-- Pantalón Hombre Gris
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero) VALUES
('Pantalón de Lino para Hombre Gris', 'Pantalón de lino gris para hombre, versátil y elegante, ideal para combinar con cualquier prenda.', 14990.00, 
 (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Pantalones' LIMIT 1), 
 'hombre');

SET @producto_pantalon_hombre_gris = LAST_INSERT_ID();

INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color) VALUES
(@producto_pantalon_hombre_gris, 
 'imagenes/productos/pantalones/hombre/gris/pantalon_hombre_lino_gris.png', -- Miniatura: solo producto, sin modelo
 'imagenes/productos/pantalones/hombre/gris/pantalon_hombre_lino_gris_modelo.png', -- Modelo
 'imagenes/productos/pantalones/hombre/gris/pantalon_lino_hombre_gris_modelo2.png', -- Modelo 2
 'imagenes/productos/pantalones/hombre/gris/pantalon_lino_hombre_modelo_gris.png', -- Modelo 3
 'gris');

INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES
(@producto_pantalon_hombre_gris, '42', 'gris', 5),
(@producto_pantalon_hombre_gris, '44', 'gris', 0), -- Sin stock
(@producto_pantalon_hombre_gris, '46', 'gris', 5),
(@producto_pantalon_hombre_gris, '48', 'gris', 5);

-- Pantalón Hombre Negro
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero) VALUES
('Pantalón de Lino para Hombre Negro', 'Pantalón de lino negro para hombre, elegante y sofisticado, perfecto para ocasiones formales.', 15490.00, 
 (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Pantalones' LIMIT 1), 
 'hombre');

SET @producto_pantalon_hombre_negro = LAST_INSERT_ID();

INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color) VALUES
(@producto_pantalon_hombre_negro, 
 'imagenes/productos/pantalones/hombre/negro/pantalon_hombre_lino_negro.png', -- Única imagen disponible (solo producto)
 NULL,
 NULL,
 NULL,
 'negro');

INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES
(@producto_pantalon_hombre_negro, '42', 'negro', 5),
(@producto_pantalon_hombre_negro, '44', 'negro', 5),
(@producto_pantalon_hombre_negro, '46', 'negro', 0); -- Sin stock

-- =========================
-- SHORTS HOMBRE
-- =========================
-- Short Hombre Marrón
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero) VALUES
('Short de Lino para Hombre Marrón', 'Short de lino marrón para hombre, cómodo y fresco, ideal para el verano y días cálidos.', 8990.00, 
 (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Shorts' LIMIT 1), 
 'hombre');

SET @producto_short_hombre_marron = LAST_INSERT_ID();

INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color) VALUES
(@producto_short_hombre_marron, 
 'imagenes/productos/shorts/hombre/marron/short_hombre_marron_mini.jpg.png', -- Miniatura: tiene "mini" en el nombre
 'imagenes/productos/shorts/hombre/marron/short_hombre_marron.png', -- Solo producto
 'imagenes/productos/shorts/hombre/marron/short_hombre_marron1.jpg.png', -- Foto adicional
 'imagenes/productos/shorts/hombre/marron/short_hombre_marron2.jpg.png', -- Foto adicional
 'marron');

INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES
(@producto_short_hombre_marron, 'S', 'marron', 5),
(@producto_short_hombre_marron, 'M', 'marron', 5),
(@producto_short_hombre_marron, 'L', 'marron', 5),
(@producto_short_hombre_marron, 'XL', 'marron', 0); -- Sin stock

-- Short Hombre Negro
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero) VALUES
('Short de Lino para Hombre Negro', 'Short de lino negro para hombre, elegante y versátil, perfecto para combinar con cualquier prenda.', 8990.00, 
 (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Shorts' LIMIT 1), 
 'hombre');

SET @producto_short_hombre_negro = LAST_INSERT_ID();

INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color) VALUES
(@producto_short_hombre_negro, 
 'imagenes/productos/shorts/short_hombre_negro_mini.png', -- Miniatura: está en raíz de shorts, tiene "mini"
 'imagenes/productos/shorts/short_hombre_negro.png', -- Solo producto
 NULL,
 NULL,
 'negro');

INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES
(@producto_short_hombre_negro, 'S', 'negro', 5),
(@producto_short_hombre_negro, 'M', 'negro', 0), -- Sin stock
(@producto_short_hombre_negro, 'L', 'negro', 5),
(@producto_short_hombre_negro, 'XL', 'negro', 5);

-- =========================
-- SHORTS/PANTALONES MUJER
-- =========================
-- Pantalón Mujer Celeste
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero) VALUES
('Pantalón de Lino para Mujer Celeste', 'Pantalón de lino celeste para mujer, cómodo y elegante, perfecto para el día a día.', 11990.00, 
 (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Shorts' LIMIT 1), 
 'mujer');

SET @producto_pantalon_mujer_celeste = LAST_INSERT_ID();

INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color) VALUES
(@producto_pantalon_mujer_celeste, 
 'imagenes/productos/shorts/mujer/celeste/pantalon_mujer_celeste.png', -- Miniatura: solo producto, sin modelo
 'imagenes/productos/shorts/mujer/celeste/pantalon_mujer_celeste_modelo.png', -- Modelo
 NULL,
 NULL,
 'celeste');

INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES
(@producto_pantalon_mujer_celeste, 'S', 'celeste', 5),
(@producto_pantalon_mujer_celeste, 'M', 'celeste', 5),
(@producto_pantalon_mujer_celeste, 'L', 'celeste', 0), -- Sin stock
(@producto_pantalon_mujer_celeste, 'XL', 'celeste', 5);

-- Pantalón Mujer Crema
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero) VALUES
('Pantalón de Lino para Mujer Crema', 'Pantalón de lino crema para mujer, elegante y versátil, ideal para combinar con blusas y camisas.', 11990.00, 
 (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Shorts' LIMIT 1), 
 'mujer');

SET @producto_pantalon_mujer_crema = LAST_INSERT_ID();

INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color) VALUES
(@producto_pantalon_mujer_crema, 
 'imagenes/productos/shorts/mujer/crema/pantalon_mujer_crema_mini.png', -- Miniatura: tiene "mini"
 'imagenes/productos/shorts/mujer/crema/pantalon_mujer_crema_modelo.png', -- Modelo
 'imagenes/productos/shorts/mujer/crema/pantalon_mujer_crema.png', -- Solo producto
 'imagenes/productos/shorts/mujer/crema/pantalon_mujer_crema1.jpg.png', -- Foto adicional
 'crema');

INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES
(@producto_pantalon_mujer_crema, 'S', 'crema', 5),
(@producto_pantalon_mujer_crema, 'M', 'crema', 5),
(@producto_pantalon_mujer_crema, 'L', 'crema', 5),
(@producto_pantalon_mujer_crema, 'XL', 'crema', 0); -- Sin stock

-- Short Mujer Negro
INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero) VALUES
('Short de Lino para Mujer Negro', 'Short de lino negro para mujer, cómodo y elegante, perfecto para el verano y días cálidos.', 9490.00, 
 (SELECT id_categoria FROM Categorias WHERE nombre_categoria = 'Shorts' LIMIT 1), 
 'mujer');

SET @producto_short_mujer_negro = LAST_INSERT_ID();

INSERT INTO Fotos_Producto (id_producto, foto_prod_miniatura, foto1_prod, foto2_prod, foto3_prod, color) VALUES
(@producto_short_mujer_negro, 
 'imagenes/productos/shorts/short_mujer_negro_mini.jpg.png', -- Miniatura: está en raíz de shorts, tiene "mini"
 'imagenes/productos/shorts/short_mujer_negro_mini_modelo.png', -- Modelo mini
 'imagenes/productos/shorts/short_mujer_negro2.jpg.png', -- Foto adicional
 NULL,
 'negro');

INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES
(@producto_short_mujer_negro, 'S', 'negro', 5),
(@producto_short_mujer_negro, 'M', 'negro', 5),
(@producto_short_mujer_negro, 'L', 'negro', 0); -- Sin stock

-- =========================
-- RESUMEN
-- =========================
-- Total productos insertados: 12
-- - 3 Blusas (mujer)
-- - 2 Camisas (hombre y mujer)
-- - 3 Pantalones (hombre)
-- - 4 Shorts/Pantalones (2 hombre, 2 mujer)
-- 
-- Notas sobre imágenes:
-- - foto_prod_miniatura: Imagen solo de producto (sin "modelo" ni "grupal" en el nombre, o tiene "mini")
-- - foto1_prod, foto2_prod, foto3_prod: Otras fotos del producto (pueden ser modelos)
-- - Las imágenes grupales en la raíz de cada categoría NO se usan aquí (son para tarjetas de categoría)
-- 
-- Stock:
-- - La mayoría de variantes tienen stock de 5 unidades
-- - Algunas variantes tienen stock 0 (marcadas como "Sin stock")
-- - Total variantes con stock: aproximadamente 36 variantes con stock, 12 sin stock
-- =========================
