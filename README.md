# 🛍️ Tienda Seda y Lino

E-commerce de ropa de lino y seda con diseño moderno y funcionalidad completa.

## 📋 Características

- **Productos dinámicos** desde base de datos MySQL
- **Páginas de categorías** (Camisas, Pantalones, Blusas, Shorts)
- **Detalle de productos** con galería de imágenes
- **Sistema de login** con roles (Cliente, Ventas, Marketing, Admin)
- **Carrito de compras** con gestión de sesión
- **Checkout completo** con validación de stock y métodos de pago
- **Emails automáticos** de confirmación de pedido
- **Diseño responsivo** con Bootstrap 5

## 🚀 Instalación

### 1. Descargar el código
```bash
git clone [URL_DEL_REPOSITORIO]
cd tiendasedaylino
```

### 2. Configurar base de datos
1. Crear base de datos MySQL:
```sql
CREATE DATABASE tiendasedaylino_db;
```

2. Importar estructura base:
```sql
SOURCE sql/database_estructura.sql;
```

3. **IMPORTANTE**: Aplicar migraciones (funcionalidades de baja de usuarios y stock automático):
```sql
SOURCE sql/migraciones_stock_y_usuarios.sql;
```

4. Insertar datos de ejemplo (opcional):
```sql
-- Ver archivo NOSUBIR/productosx4_insertar.txt
```

### 3. Configurar conexión
Editar `config/database.php` con tus credenciales (ya configurado para localhost):
```php
$host = 'localhost';
$dbname = 'tiendasedaylino_db';
$username = 'tu_usuario';
$password = 'tu_contraseña';
```

### 3b. Configurar Email (Opcional - Para confirmaciones)
1. Instalar dependencias:
```bash
composer install
```

2. Copiar y configurar:
```bash
cp config/email.example.php config/email.php
```

3. Editar `config/email.php` con datos de tu servidor SMTP

📧 **Guía completa:** Ver `CONFIGURACION_EMAIL.md`

### 4. Configurar servidor web
- **WAMP/XAMPP**: Colocar en `www/` o `htdocs/`
- **Apache/Nginx**: Configurar virtual host
- **PHP**: Versión 7.4+ requerida

## 📁 Estructura del Proyecto

```
tiendasedaylino/
├── css/
│   └── style.css          # Estilos principales
├── imagenes/
│   └── productos/         # Imágenes de productos
├── sql/
│   ├── tiendasedaylino.sql    # Estructura de BD
│   └── datos_ejemplo.sql      # Datos de ejemplo
├── config/
│   └── database.php       # Configuración de BD
├── index.html             # Página principal
├── login.php              # Sistema de login
├── detalle-producto.php   # Detalle de productos
├── camisas.php            # Página de camisas
├── pantalones.php         # Página de pantalones
├── blusas.php             # Página de blusas
└── shorts.php             # Página de shorts
```

## 🎯 Uso

### Páginas Principales
- **Inicio**: `index.php`
- **Login**: `login.php`
- **Catálogo**: `catalogo.php?categoria=X`
- **Detalle**: `detalle-producto.php?id=X`
- **Carrito**: `carrito.php`
- **Checkout**: `checkout.php`
- **Perfil**: `perfil.php`

### Usuarios de Prueba
- **Cliente**: 
- **Admin**: 
- **Ventas**: 
- **Marketing**: 

## 🛠️ Tecnologías

- **Backend**: PHP 7.4+
- **Base de Datos**: MySQL 5.7+
- **Frontend**: HTML5, CSS3, JavaScript
- **Framework**: Bootstrap 5.3.8
- **Iconos**: Font Awesome 6.0.0

## 📝 Notas

- Las imágenes de productos están en `imagenes/productos/`
- Los estilos están centralizados en `css/style.css`
- La configuración de BD está en `config/database.php`
- Compatible con WAMP, XAMPP y servidores Linux

## 🔧 Soporte

Para problemas o dudas, revisar:
1. Configuración de base de datos
2. Permisos de archivos
3. Versión de PHP (7.4+)
4. Extensiones PHP habilitadas (PDO, MySQL)
