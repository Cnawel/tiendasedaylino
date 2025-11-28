Preguntas_Recupero
*Contiene preguntas de seguridad para recuperación de contraseña de usuarios*
@id_pregunta = INT PRIMARY KEY
texto_pregunta = varchar(100) *Texto de la pregunta de seguridad, longitud: 10-100 [A-Z, a-z, á, é, í, ó, ú, Á, É, Í, Ó, Ú, ñ, Ñ, ü, Ü, 0-9, ?, ¿, espacios, .]*
activa = tinyint *Estado de la pregunta, 1=activa, 0=inactiva, DEFAULT 1*
orden = int *Orden de visualización de la pregunta, DEFAULT 0*
fecha_creacion = datetime *Fecha de creación del registro, formato: AAAA-MM-DD HH:MM:SS, DEFAULT CURRENT_TIMESTAMP*

Usuarios
*Contiene información de usuarios del sistema (clientes, administradores, marketing, ventas)*
@id_usuario = INT PRIMARY KEY
nombre = varchar(100) *Nombre de pila del usuario, longitud: 2-100 [A-Z, a-z, á, é, í, ó, ú, Á, É, Í, Ó, Ú, ñ, Ñ, ü, Ü, espacios, ', ´], NULL permitido para usuarios eliminados*
apellido = varchar(100) *Apellido del usuario, longitud: 2-100 [A-Z, a-z, á, é, í, ó, ú, Á, É, Í, Ó, Ú, ñ, Ñ, ü, Ü, espacios, ', ´], NULL permitido para usuarios eliminados*
email = varchar(150) *Dirección de correo electrónico, longitud: 6-150 [A-Z, a-z, 0-9, @, _, -, ., +], NULL permitido para usuarios eliminados (sin UNIQUE constraint)*
contrasena = varchar(255) *Contraseña almacenada como hash, longitud: 6-255 [A-Z, a-z, 0-9, @, _, -, ., !], NULL permitido para usuarios eliminados*
rol = enum('cliente','admin','marketing','ventas') *Rol del usuario, valores permitidos: cliente, admin, marketing, ventas, DEFAULT 'cliente'*
activo = tinyint *Estado del usuario, 1=activo, 0=inactivo (soft delete), DEFAULT 1*
telefono = varchar(20) *Número telefónico, longitud: 6-20 [0-9, +, (, ), -]*
direccion = varchar(100) *Dirección del usuario, longitud: 5-100 [A-Z, a-z, á, é, í, ó, ú, Á, É, Í, Ó, Ú, ñ, Ñ, ü, Ü, 0-9, espacios, ., -, ', `]*
localidad = varchar(100) *Localidad del usuario, longitud: 3-100 [A-Z, a-z, á, é, í, ó, ú, Á, É, Í, Ó, Ú, ñ, Ñ, ü, Ü, espacios]*
provincia = varchar(100) *Provincia del usuario, longitud: 3-100 [A-Z, a-z, á, é, í, ó, ú, Á, É, Í, Ó, Ú, ñ, Ñ, ü, Ü, espacios]*
codigo_postal = varchar(10) *Código postal, longitud: 4-10 [0-9, A-Z, a-z]*
fecha_registro = datetime *Fecha de registro, formato: AAAA-MM-DD HH:MM:SS, DEFAULT CURRENT_TIMESTAMP*
fecha_actualizacion = datetime *Fecha de última actualización, formato: AAAA-MM-DD HH:MM:SS, NULL permitido*
deleted_at = datetime *Fecha de anonimización/eliminación, formato: AAAA-MM-DD HH:MM:SS, NULL permitido*
fecha_nacimiento = date *Fecha de nacimiento, formato: AAAA-MM-DD, rango: 1925–2012*
@pregunta_recupero = INT FOREIGN KEY *Referencia a Preguntas_Recupero(id_pregunta)*
respuesta_recupero = varchar(255) *Respuesta almacenada como hash, longitud: 4-255 [A-Z, a-z, 0-9, espacios]*

Categorias
*Contiene categorías de productos disponibles en la tienda*
@id_categoria = INT PRIMARY KEY
nombre_categoria = varchar(100) *Nombre de la categoría, longitud: 3-100 [A-Z, a-z, á, é, í, ó, ú, Á, É, Í, Ó, Ú, ñ, Ñ, ü, Ü, 0-9, espacios, -]*
activo = tinyint *Estado de la categoría, 1=activa, 0=inactiva, DEFAULT 1*
descripcion_categoria = varchar(255) *Descripción opcional, longitud: 0-255 [A-Z, a-z, á, é, í, ó, ú, Á, É, Í, Ó, Ú, ñ, Ñ, ü, Ü, 0-9, espacios, ., -, coma, :, ;]. Bloquea: < > { } [ ] | \ / &*
fecha_creacion = datetime *Fecha de creación, formato: AAAA-MM-DD HH:MM:SS, DEFAULT CURRENT_TIMESTAMP*
fecha_actualizacion = datetime *Última actualización, formato: AAAA-MM-DD HH:MM:SS*

Productos
*Contiene información de productos disponibles en la tienda*
@id_producto = INT PRIMARY KEY
nombre_producto = varchar(100) *Nombre del producto, longitud: 3-100 [A-Z, a-z, á, é, í, ó, ú, Á, É, Í, Ó, Ú, ñ, Ñ, ü, Ü, 0-9, espacios, -]*
descripcion_producto = varchar(255) *Descripción del producto, longitud: 0-255 [A-Z, a-z, á, é, í, ó, ú, Á, É, Í, Ó, Ú, ñ, Ñ, ü, Ü, 0-9, espacios, ., -, coma, :, ;]. Bloquea: < > { } [ ] | \ / &*
precio_actual = decimal(10,2) *Precio actual, rango: >=0*
@id_categoria = INT FOREIGN KEY *Referencia a Categorias(id_categoria)*
genero = enum('hombre','mujer','unisex') *Género del producto, valores permitidos: hombre, mujer, unisex, DEFAULT 'unisex'*
activo = tinyint *Estado del producto, 1=activo, 0=inactivo, DEFAULT 1*
sku = varchar(50) *Código SKU del producto para inventario, longitud: 3-50 [A-Z, a-z, 0-9, -,_], UNIQUE, NULL permitido*
fecha_creacion = datetime *Fecha de creación, DEFAULT CURRENT_TIMESTAMP*
fecha_actualizacion = datetime *Fecha de última actualización, NULL permitido*

Fotos_Producto
*Contiene fotos asociadas a productos*
@id_foto = INT PRIMARY KEY
@id_producto = INT FOREIGN KEY *Referencia a Productos(id_producto)*
foto_prod_miniatura = varchar(255) *Ruta o URL de miniatura, longitud: 5-255 [A-Z, a-z, 0-9, /, :, ., _, -]*
foto1_prod = varchar(255) *URL de foto principal, longitud: 5-255 [A-Z, a-z, 0-9, /, :, ., _, -]*
foto2_prod = varchar(255) *URL de foto secundaria, longitud: 5-255 [A-Z, a-z, 0-9, /, :, ., _, -]*
foto3_prod = varchar(255) *URL de foto terciaria, longitud: 5-255 [A-Z, a-z, 0-9, /, :, ., _, -]*
color = varchar(50) *Color del producto, longitud: 3-50 [A-Z, a-z]*
activo = tinyint *Estado, 1=activa, 0=inactiva, DEFAULT 1*

Stock_Variantes
*Contiene variantes de stock por talle y color*
@id_variante = INT PRIMARY KEY
@id_producto = INT FOREIGN KEY *Referencia a Productos(id_producto)*
talle = varchar(50) *Talle de la variante, longitud: 1-50 [A-Z, a-z, 0-9, espacios, ., -]*
color = varchar(50) *Color de la variante, longitud: 3-50 [A-Z, a-z]*
stock = int *Cache del stock actual (se mantiene automáticamente mediante funciones PHP), rango: 0–100000, DEFAULT 0*
activo = tinyint *Estado, 1=activo, 0=eliminado, DEFAULT 1*
fecha_creacion = datetime *Fecha de creación, DEFAULT CURRENT_TIMESTAMP*
fecha_actualizacion = datetime *Última actualización, NULL permitido*
*NOTA: UNIQUE KEY en (id_producto, talle, color) previene duplicados de variantes*

Pedidos
*Contiene pedidos realizados por usuarios*
@id_pedido = INT PRIMARY KEY
@id_usuario = INT FOREIGN KEY *Referencia a Usuarios(id_usuario), NULL permitido para pedidos desvinculados de usuarios anonimizados, ON DELETE SET NULL*
fecha_pedido = datetime *Fecha y hora del pedido, formato: AAAA-MM-DD HH:MM:SS*
estado_pedido = enum('pendiente','preparacion','en_viaje','completado','devolucion','cancelado') *Estado del pedido*
total = decimal(10,2) *Monto total del pedido (calculado o cache), >=0, NULL permitido*
direccion_entrega = varchar(100) *Dirección de entrega, longitud: 5-100 [A-Z, a-z, á, é, í, ó, ú, Á, É, Í, Ó, Ú, ñ, Ñ, ü, Ü, 0-9, espacios, ., -, ', `]*
telefono_contacto = varchar(20) *Teléfono de contacto, longitud: 6-20 [0-9, +, (, ), -]*
observaciones = varchar(500) *Notas del cliente o internas, longitud: 0-500 [A-Z, a-z, á, é, í, ó, ú, Á, É, Í, Ó, Ú, ñ, Ñ, ü, Ü, 0-9, espacios, ., -, coma, :, ;, ', `]*
fecha_actualizacion = datetime *Última actualización, formato: AAAA-MM-DD HH:MM:SS*

Detalle_Pedido
*Contiene los productos incluidos en cada pedido*
@id_detalle = INT PRIMARY KEY
@id_pedido = INT FOREIGN KEY *Referencia a Pedidos(id_pedido)*
@id_variante = INT FOREIGN KEY *Referencia a Stock_Variantes(id_variante)*
cantidad = int *Cantidad de productos, rango: 1–1000*
precio_unitario = decimal(10,2) *Precio unitario, >=0*

Forma_Pagos
*Contiene las formas de pago disponibles*
@id_forma_pago = INT PRIMARY KEY
nombre = varchar(100) *Nombre de la forma de pago, longitud: 3-100 [A-Z, a-z, á, é, í, ó, ú, Á, É, Í, Ó, Ú, ñ, Ñ, ü, Ü, 0-9, espacios, -]*
activo = tinyint *Estado, 1=activa, 0=inactiva, DEFAULT 1*
descripcion = varchar(255) *Descripción opcional, longitud: 0-255 [A-Z, a-z, á, é, í, ó, ú, Á, É, Í, Ó, Ú, ñ, Ñ, ü, Ü, 0-9, espacios, ., -, coma, :, ;]. Bloquea: < > { } [ ] | \ / &*

Pagos
*Registro de pagos realizados*
@id_pago = INT PRIMARY KEY
@id_pedido = INT FOREIGN KEY *Referencia a Pedidos(id_pedido)*
@id_forma_pago = INT FOREIGN KEY *Referencia a Forma_Pagos(id_forma_pago)*
numero_transaccion = varchar(100) *Número de transacción o referencia para conciliación, longitud: 5-100 [A-Z, a-z, 0-9, -,_], UNIQUE, NULL permitido*
estado_pago = enum('pendiente','pendiente_aprobacion','aprobado','rechazado','cancelado') *Estado del pago, valores permitidos: pendiente, pendiente_aprobacion, aprobado, rechazado, cancelado, DEFAULT 'pendiente'*
monto = decimal(10,2) *Monto del pago, >=0, DEFAULT 0.00*
fecha_pago = datetime *Fecha del pago, formato: AAAA-MM-DD HH:MM:SS, DEFAULT CURRENT_TIMESTAMP*
fecha_aprobacion = datetime *Fecha de aprobación, formato: AAAA-MM-DD HH:MM:SS*
motivo_rechazo = varchar(500) *Razón del rechazo, longitud: 0-500 [A-Z, a-z, á, é, í, ó, ú, Á, É, Í, Ó, Ú, ñ, Ñ, ü, Ü, 0-9, espacios, ., -, coma, :, ;, ', `]*
fecha_actualizacion = datetime *Última actualización, formato: AAAA-MM-DD HH:MM:SS*

Movimientos_Stock
*Historial de movimientos de stock (ventas, ajustes, devoluciones, ingresos)*
@id_movimiento = INT PRIMARY KEY
@id_variante = INT FOREIGN KEY *Referencia a Stock_Variantes(id_variante)*
tipo_movimiento = enum('venta','ajuste','devolucion','ingreso') *Tipo de movimiento*
cantidad = int *Cantidad afectada. Siempre positiva para venta/ingreso/devolucion. Para ajustes puede ser positiva (suma) o negativa (resta), rango: -100000–100000*
fecha_movimiento = datetime *Fecha y hora, formato: AAAA-MM-DD HH:MM:SS, DEFAULT CURRENT_TIMESTAMP*
@id_usuario = INT FOREIGN KEY *Referencia a Usuarios(id_usuario)*
@id_pedido = INT FOREIGN KEY *Referencia a Pedidos(id_pedido), NULL permitido*
observaciones = varchar(500) *Observaciones del movimiento, longitud: 0-500 [A-Z, a-z, á, é, í, ó, ú, Á, É, Í, Ó, Ú, ñ, Ñ, ü, Ü, 0-9, espacios, ., -, coma, :, ;, ', `]*
