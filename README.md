# 🛍️ Tienda Seda y Lino

Tu tienda online de ropa con sistema completo de gestión de pedidos e inventario

## 🚀 Instalación

Para comenzar a usar la aplicación, necesitarás:

- Un servidor web (Apache, Nginx o similar)
- Una base de datos MySQL
- PHP instalado

### Pasos básicos

1. **Coloca los archivos del proyecto** en la carpeta correspondiente de tu servidor web
2. **Crea la base de datos** importando el archivo `sql/inicial.sql` desde phpMyAdmin o tu herramienta de gestión de base de datos preferida
3. **Accede a la aplicación** desde tu navegador

### Usuario administrador inicial

Al crear la base de datos, se genera automáticamente un usuario administrador con estas credenciales:

- **Email**: `admin@sedaylino.com`
- **Contraseña**: `admin@sedaylino.com`

**⚠️ Importante**: Por seguridad, cambia esta contraseña después de tu primer inicio de sesión.

## 🎯 ¿Qué puedes hacer?

La aplicación te permite gestionar una tienda online completa de ropa, desde la venta hasta el control interno. Aquí te contamos las principales funcionalidades:

### Para tus clientes

**Compras online:**
- Navegar por un catálogo completo con filtros por categoría, talle, género y color
- Ver detalles de cada producto con todas sus variantes disponibles
- Agregar productos al carrito y gestionar las cantidades
- Realizar compras de forma segura con validación automática de stock disponible
- Seguir el estado de sus pedidos en tiempo real
- El sistema calcula automáticamente los costos de envío según la ubicación y el monto de la compra

**Cuenta personal:**
- Registrarse y crear una cuenta
- Gestionar su perfil y datos personales
- Recuperar su contraseña mediante preguntas de seguridad
- Ver el historial completo de sus pedidos
- Cancelar o solicitar devoluciones cuando corresponda

### Para tu equipo

**Control de inventario:**
- Gestionar el stock de cada producto considerando talle y color
- Registrar movimientos de stock (ventas, devoluciones, ajustes)
- El sistema valida automáticamente que haya stock disponible antes de permitir una venta

**Gestión de pedidos:**
- Seguimiento completo del estado de cada pedido
- Gestión de pagos y aprobaciones
- Procesamiento de devoluciones con actualización automática de stock

**Roles y permisos:**
- Sistema de roles que define qué puede hacer cada persona en el sistema
- Cada rol tiene acceso a las herramientas que necesita para su trabajo
- Paneles personalizados según el tipo de usuario

## 👥 ¿Qué puede hacer cada tipo de usuario?

### Cliente

Si eres cliente de la tienda, puedes:

**Comprar productos:**
- Explorar el catálogo completo con filtros para encontrar lo que buscas
- Ver todos los detalles de cada producto, incluyendo talles y colores disponibles
- Agregar productos a tu carrito (hasta 10 unidades de cada variante)
- Modificar las cantidades o eliminar productos del carrito antes de comprar
- Realizar tu compra de forma segura (necesitas estar registrado e iniciar sesión)
- Ver todo tu historial de pedidos

**Gestionar tus pedidos:**
- Ver todos tus pedidos desde tu perfil
- Consultar los detalles completos de cada pedido: qué productos incluye, en qué estado está y el estado del pago
- Cancelar pedidos que aún están pendientes o en preparación
- Marcar tus pagos como realizados cuando el pedido está pendiente
- Solicitar devoluciones de productos en pedidos que ya fueron completados o están en camino

**Tu cuenta:**
- Actualizar tu información personal: nombre, apellido, email, teléfono y fecha de nacimiento
- Modificar tu dirección de envío
- Cambiar tu contraseña cuando lo necesites
- Configurar una pregunta de seguridad para recuperar tu cuenta si olvidas la contraseña
- Eliminar tu cuenta si lo deseas (tus datos se mantienen en el sistema para historial, pero tu cuenta queda inactiva)

---

### Ventas

Si trabajas en el área de ventas, tienes acceso a:

**Gestionar pedidos:**
- Ver todos los pedidos del sistema (puedes elegir cuántos ver: 10, 50 o todos)
- Cambiar el estado de los pedidos según avancen: pendiente, preparación, en viaje, completado, devolución o cancelado
- Editar información de los pedidos: dirección de entrega, teléfono de contacto, observaciones y total
- Ver todos los detalles de cada pedido

**Gestionar pagos:**
- Aprobar pagos cuando el cliente haya realizado el pago
  - El sistema automáticamente descuenta el stock de los productos vendidos
  - El pedido pasa a estado "preparación"
- Rechazar pagos si hay algún problema, indicando el motivo
  - Si el stock ya había sido descontado, se restaura automáticamente
  - El pedido se cancela si corresponde
- Cancelar pagos cuando sea necesario
  - El stock se restaura automáticamente si había sido descontado
- Actualizar información de pagos: monto, número de transacción y motivos de rechazo

**Procesar devoluciones:**
- Gestionar devoluciones de productos en pedidos completados o en camino
- Especificar qué cantidad se devuelve y el motivo
- El stock se restaura automáticamente en el sistema

**Ver información de clientes:**
- Consultar la lista completa de todos los clientes registrados
- Ver los datos de cada cliente: nombre, email, teléfono, dirección y fecha de registro
- Conocer cuántos pedidos ha realizado cada cliente

**Gestionar métodos de pago:**
- Agregar nuevos métodos de pago disponibles para los clientes
- Editar los métodos de pago existentes
- Eliminar métodos de pago que ya no se usen (solo si no están asociados a ningún pedido)

**Ver estadísticas:**
- Consultar los productos más vendidos por talle y color
- Identificar pedidos que llevan mucho tiempo en un mismo estado

---

### Marketing

Si trabajas en marketing, puedes gestionar todo el catálogo:

**Gestionar productos:**
- Ver todos los productos organizados por nombre (agrupando todas sus variantes de talle y color)
- Editar productos existentes: cambiar nombre, descripción, precio, categoría y género
- Crear productos nuevos con su categoría y género
- Agregar variantes a productos: nuevos talles y colores
- Gestionar el stock inicial de cada variante
- Activar o desactivar productos del catálogo (los productos desactivados no se eliminan, solo se ocultan)

**Gestionar categorías:**
- Las categorías se crean automáticamente cuando creas un producto nuevo
- Ver todas las categorías disponibles en el sistema
- Si una categoría no existe, el sistema la crea automáticamente al usarla

**Carga masiva de productos:**
- Subir un archivo CSV para agregar muchos productos y variantes de una vez
- El archivo debe tener columnas específicas: nombre del producto, descripción, precio, categoría, género, talle, color y stock
- Cada fila del archivo representa una variante (una combinación de talle y color)
- Los productos con el mismo nombre se agrupan automáticamente
- El sistema valida automáticamente que los datos estén correctos

**Gestionar imágenes:**
- Subir imágenes de productos: foto principal y fotos por cada color disponible
- Asociar las imágenes a las variantes según el color del producto
- Agregar múltiples fotos por producto para mostrar diferentes ángulos

**Ver estadísticas:**
- Consultar los productos más vendidos por talle y color
- Identificar productos que tienen stock pero no se han vendido en los últimos 30 días

---

### Administrador

Como administrador, tienes acceso completo al sistema:

**Gestionar usuarios:**
- Crear usuarios para tu equipo (personal de ventas y marketing) con contraseñas temporales que se generan automáticamente
- Modificar cualquier usuario: cambiar nombre, apellido, email, rol y contraseña
- Asignar roles a los usuarios: cliente, ventas, marketing o administrador
- Desactivar usuarios cuando sea necesario (los datos se mantienen en el sistema para historial)
- Ver estadísticas de cuántos usuarios hay de cada tipo

**Acceso completo:**
- Tienes acceso a todos los paneles del sistema
- Puedes gestionar pedidos y pagos (como el personal de ventas)
- Puedes gestionar productos y catálogo (como el personal de marketing)
- Tienes acceso a todas las funcionalidades disponibles

**Ver estadísticas:**
- Consultar el total de usuarios por cada rol
- Ver contadores: total de usuarios, administradores, personal (ventas + marketing) y clientes

**Protecciones del sistema:**
- No puedes quitarte tu propio rol de administrador (para evitar bloquearte del sistema)
- No puedes eliminarte a ti mismo (para mantener siempre al menos un administrador)
- Debe existir al menos un administrador activo en el sistema (no puedes eliminar o cambiar el rol del último administrador)

**Importante:**
- Como administrador tienes acceso completo, así que usa este poder con responsabilidad
- Recuerda cambiar la contraseña del usuario administrador inicial después de la instalación
- Cuando desactivas un usuario, sus datos se mantienen en el sistema para conservar el historial


## 📝 Notas Importantes

- La aplicación funciona en diferentes entornos: WAMP, XAMPP y servidores Linux
- El sistema protege tu información y cuenta con medidas de seguridad para mantener tus datos seguros

## 📄 Licencia

Este proyecto es de uso interno por alumnos de ESBA.
Todos los derechos reservados.

---

**Última actualización**: 14 Nov 2025
