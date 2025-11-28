# 🛍️ Tienda Seda y Lino

E-commerce de Ropa y Sistema de gestión interno de pedidos

## 🚀 Instalación Paso a Paso

### 1. Descargar el código
```bash
git clone [URL_DEL_REPOSITORIO]
cd tiendasedaylino```

O descargue el código fuente y extraiga los archivos en la carpeta correspondiente de su servidor web.

### Paso 2: Configurar el Servidor Web

#### Para WAMP (Windows):
1. Copie la carpeta del proyecto a `C:\wamp64\www\tiendasedaylino`
2. Inicie WAMP y verifique que Apache y MySQL estén activos (íconos en verde)

#### Para XAMPP:
1. Copie la carpeta del proyecto a `C:\xampp\htdocs\tiendasedaylino` (Windows) o `/opt/lampp/htdocs/tiendasedaylino` (Linux)
2. Inicie el panel de control de XAMPP
3. Inicie los servicios Apache y MySQL

#### Para servidor Linux con Apache:
1. Copie el proyecto a `/var/www/html/tiendasedaylino` o configure un virtual host
2. Asegúrese de que Apache y MySQL estén en ejecución

### Paso 3: Crear la Base de Datos

Existen dos métodos para crear la base de datos:

#### Método 1: Usando phpMyAdmin (Recomendado)

1. Abra phpMyAdmin en su navegador (generalmente `http://localhost/phpmyadmin`)
2. Haga clic en la pestaña "Importar"
3. Seleccione el archivo `sql/inicial.sql`
4. Asegúrese de que la opción "Permitir la interrupción de la importación" esté desactivada
5. Haga clic en "Continuar" o "Ejecutar"
6. Verifique que se haya creado la base de datos `tiendasedaylino_db` y todas las tablas

#### Método 2: Usando Línea de Comandos MySQL

```bash
# Conectarse a MySQL
mysql -u root -p

# Ejecutar el script SQL
SOURCE /ruta/completa/al/proyecto/sql/inicial.sql;

# O desde la línea de comandos directamente:
mysql -u root -p < sql/inicial.sql
```

**Nota importante**: El archivo `inicial.sql` crea automáticamente:
- La base de datos `tiendasedaylino_db`
- Todas las tablas necesarias con su estructura completa
- El usuario ADMIN inicial

### Paso 4: Verificar el Usuario Administrador Inicial

El script `inicial.sql` crea automáticamente un usuario administrador con las siguientes credenciales:

- **Email**: `admin@sedaylino.com`
- **Contraseña**: `admin@sedaylino.com`
- **Rol**: `admin`

**⚠️ IMPORTANTE**: Cambie esta contraseña inmediatamente después del primer inicio de sesión por razones de seguridad.
```

### Paso 5: Configurar la Conexión a la Base de Datos

El archivo `config/database.php` detecta automáticamente si está ejecutándose en localhost o en hosting. Para desarrollo local, la configuración por defecto es:

```php
$host = '127.0.0.1';
$dbname = 'tiendasedaylino_db';
$username = 'root';
$password = '';
$port = 3306;```

**Si su configuración es diferente**, edite `config/database.php` y ajuste los valores según su entorno:

- **Usuario MySQL**: Generalmente `root` en desarrollo local
- **Contraseña MySQL**: Generalmente vacía en WAMP/XAMPP, o la que configuró durante la instalación
- **Puerto**: Generalmente `3306` (puerto por defecto de MySQL)

### Paso 6: Verificar Permisos de Archivos

Asegúrese de que las siguientes carpetas tengan permisos de escritura (si está en Linux):

```bash
chmod -R 755 imagenes/productos/
chmod -R 755 uploads/```

### Paso 7: Verificar la Instalación

1. Abra su navegador y acceda a la aplicación:
   - WAMP: `http://localhost/tiendasedaylino/`
   - XAMPP: `http://localhost/tiendasedaylino/`
   - Linux: `http://localhost/tiendasedaylino/` o según su configuración

2. Verifique que la página de inicio se cargue correctamente

3. Intente iniciar sesión con las credenciales del Admin

4. Si el login es exitoso, será redirigido al panel de administración


## 🎯 Uso

La aplicación incluye las siguientes funcionalidades principales:

#### E-commerce
- ✅ Catálogo de productos con filtros por categoría, talle, género y color
- ✅ Detalle de productos con variantes (talle y color)
- ✅ Carrito de compras con persistencia en sesión
- ✅ Proceso de checkout completo con validación de stock
- ✅ Gestión de pedidos con seguimiento de estados
- ✅ Cálculo automático de costos de envío según ubicación y monto

#### Gestión de Usuarios
- ✅ Sistema de registro con validaciones de seguridad
- ✅ Login con protección contra ataques de fuerza bruta
- ✅ Recupero de contraseña mediante preguntas de seguridad
- ✅ Gestión de perfiles de usuario
- ✅ Eliminación de cuenta (soft delete)

#### Gestión de Inventario
- ✅ Control de stock por variante (talle + color)
- ✅ Movimientos de stock (ventas, devoluciones, ajustes, ingresos)
- ✅ Validación de stock disponible antes de ventas

#### Gestión de Roles
- ✅ Sistema de roles: Cliente, Ventas, Marketing, Admin
- ✅ Control de acceso basado en roles (RBAC)
- ✅ Paneles específicos por rol

### Páginas Principales

- **Inicio**: `index.php`
- **Login**: `login.php`
- **Catálogo**: `catalogo.php?categoria=X`
- **Detalle de producto**: `detalle-producto.php?id=X`
- **Carrito**: `carrito.php`
- **Checkout**: `checkout.php` (requiere login)
- **Perfil**: `perfil.php`
- **Panel Admin**: `admin.php` (requiere rol admin)
- **Panel Ventas**: `ventas.php` (requiere rol ventas)
- **Panel Marketing**: `marketing.php` (requiere rol marketing)

## 👥 Alcances y Límites por Rol de Usuario

### Rol: Cliente

#### Funcionalidades Disponibles

**Navegación y Compra:**
- Navegar por el catálogo de productos con filtros
- Ver detalle de productos con variantes disponibles
- Agregar productos al carrito (máximo 10 unidades por variante)
- Modificar cantidades y eliminar productos del carrito
- Realizar checkout y crear pedidos (requiere estar logueado)
- Ver historial de sus propios pedidos

**Gestión de Pedidos Propios:**
- Ver todos sus pedidos en la pestaña "Mis Pedidos" del perfil
- Ver detalles completos de cada pedido (productos, estado, pago)
- Cancelar pedidos en estado `pendiente` o `preparacion`
- Marcar pagos como pagados (solo si el estado es `pendiente`)
- Solicitar devoluciones de items en pedidos `completados` o `en_viaje`

**Gestión de Perfil:**
- Actualizar datos personales (nombre, apellido, email, teléfono, fecha de nacimiento)
- Actualizar dirección de envío completa
- Cambiar contraseña
- Configurar pregunta y respuesta de recupero
- Eliminar cuenta (soft delete)

---

### Rol: Ventas

#### Funcionalidades Disponibles

**Gestión de Pedidos:**
- Ver todos los pedidos del sistema con selector de cantidad (10/50/Todos)
- Editar estado de pedidos entre: `pendiente`, `preparacion`, `en_viaje`, `completado`, `devolucion`, `cancelado`
- Editar información del pedido: dirección de entrega, teléfono de contacto, observaciones, total
- Ver detalles completos de cada pedido

**Gestión de Pagos:**
- Aprobar pagos (cambiar de `pendiente` a `aprobado`)
  - Automáticamente descuenta stock del pedido
  - Cambia estado del pedido a `preparacion`
- Rechazar pagos (cambiar a `rechazado` con motivo)
  - Restaura stock si había sido descontado
  - Cambia estado del pedido a `cancelado` si corresponde
- Cancelar pagos (cambiar a `cancelado`)
  - Restaura stock si había sido descontado
- Actualizar información de pago: monto, número de transacción, motivo de rechazo

**Gestión de Devoluciones:**
- Procesar devoluciones de items para pedidos en estado `completado` o `en_viaje`
- Especificar cantidad y motivo de devolución
- El stock se restaura automáticamente mediante `Movimientos_Stock` tipo `devolucion`

**Gestión de Clientes:**
- Ver lista completa de todos los clientes (rol `cliente`)
- Ver información detallada de cada cliente: nombre, email, teléfono, dirección, fecha de registro
- Ver total de pedidos realizados por cada cliente

**Gestión de Métodos de Pago:**
- Agregar nuevos métodos de pago (nombre y descripción)
- Editar métodos de pago existentes
- Eliminar métodos de pago (soft delete, solo si no están en uso)

**Métricas y Análisis:**
- Ver top productos más vendidos por variante (talle/color)
- Identificar pedidos con más tiempo en un estado específico

---

### Rol: Marketing

#### Funcionalidades Disponibles

**Gestión de Productos:**
- Ver lista de productos agrupados por nombre (unificando colores y talles)
- Editar productos existentes: nombre, descripción, precio, categoría, género
- Crear productos nuevos con categoría y género
- Gestionar variantes: agregar talles y colores a productos existentes
- Gestionar stock: agregar stock inicial a variantes
- Activar/desactivar productos (soft delete)

**Gestión de Categorías:**
- Crear categorías nuevas automáticamente al crear productos
- Ver lista de categorías disponibles
- Las categorías se crean automáticamente si no existen

**Carga Masiva desde CSV:**
- Subir archivo CSV para procesar múltiples productos y variantes
- Formato CSV requerido con columnas: `nombre_producto`, `descripcion_producto`, `precio_actual`, `categoria`, `genero`, `talle`, `color`, `stock`
- Cada fila del CSV representa una variante (talle + color)
- Productos con mismo nombre se agrupan automáticamente
- Validaciones automáticas de formato y datos

**Gestión de Imágenes:**
- Subir imágenes de productos: miniatura y fotos por color
- Asociar imágenes a variantes por color del producto
- Gestionar múltiples imágenes por producto (foto1, foto2, foto3)

**Métricas y Análisis:**
- Ver top productos más vendidos por variante (talle/color)
- Identificar productos sin movimiento (con stock pero sin ventas en últimos 30 días)

---

### Rol: Admin (Administrador)

#### Funcionalidades Disponibles

**Gestión Completa de Usuarios:**
- Crear usuarios de staff (Ventas y Marketing) con contraseña temporal generada automáticamente
- Modificar usuarios: cambiar nombre, apellido, email, rol, contraseña
- Cambiar roles entre: `cliente`, `ventas`, `marketing`, `admin`
- Eliminar usuarios (soft delete, marcar `activo = 0`)
- Ver estadísticas de usuarios por rol

**Acceso a Todos los Paneles:**
- Acceso completo al panel de administración
- Acceso al panel de ventas (puede gestionar pedidos y pagos)
- Acceso al panel de marketing (puede gestionar productos)
- Acceso a todas las funcionalidades del sistema

**Estadísticas y Reportes:**
- Ver total de usuarios por rol
- Contadores de usuarios: Total, Admins, Staff (Ventas + Marketing), Clientes


- **No puede quitarse su propio rol de administrador**: Validación que previene que un admin se quite su propio rol
- **No puede eliminarse a sí mismo**: Validación que previene auto-eliminación
- **Debe existir al menos un administrador**: No puede eliminar o cambiar el rol del último administrador activo del sistema

#### Notas Importantes

- El administrador tiene acceso completo al sistema, por lo que debe manejarse con extrema precaución
- Se recomienda cambiar la contraseña del usuario admin inicial inmediatamente después de la instalación
- Las operaciones de eliminación de usuarios son soft delete, preservando datos históricos para auditoría


## 📝 Notas Importantes

- Las imágenes de productos están en `imagenes/`
- Los estilos están centralizados en `css/style.css`
- La configuración de base de datos está en `config/database.php`
- Compatible con WAMP, XAMPP y servidores Linux
- Las contraseñas se almacenan como hash (nunca en texto plano)
- El sistema implementa protección contra ataques de fuerza bruta en login y recupero de contraseña


## 📄 Licencia

Este proyecto es de uso interno por alumnos de ESBA.
Todos los derechos reservados.

---

**Última actualización**: 14 Nov 2025
