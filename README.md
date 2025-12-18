# 🛍️ Tienda Seda y Lino

[![PHP](https://img.shields.io/badge/PHP-7.4+-blue.svg)](https://php.net)
[![MySQL](https://img.shields.io/badge/MySQL-5.7+-orange.svg)](https://mysql.com)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-4+-purple.svg)](https://getbootstrap.com)
[![License](https://img.shields.io/badge/License-Academic-green.svg)]()

Tienda online completa de ropa con sistema integral de gestión de pedidos, inventario y usuarios. Desarrollada con PHP nativo, MySQL y arquitectura modular.

## 🚀 Instalación

### Requisitos Previos

- **Servidor Web**: Apache/Nginx con PHP 7.4+
- **Base de Datos**: MySQL 5.7+ o MariaDB 10.0+
- **PHP**: Versión 7.4 o superior con extensiones mysqli, pdo_mysql, mbstring

### Instalación Paso a Paso

1. **Clonar o descargar el proyecto**
   ```bash
   git clone [url-del-repositorio]
   cd tienda-seda-lino
   ```

2. **Configurar la base de datos**
   - Crear una base de datos MySQL/MariaDB
   - Importar el archivo `sql/database_estructura.sql`
   - Ejecutar `sql/crear_usuarios_test.sql` para datos iniciales de

3. **Configurar conexión a base de datos**
   - Copiar `config/database.example.php` a `config/database.php`
   - Editar las credenciales de conexión

4. **Configurar servidor web**
   - Apuntar el document root a la carpeta del proyecto
   - Asegurar permisos de escritura en `uploads/` y `logs/`

5. **Acceder al sistema**
   - Abrir navegador en la URL configurada
   - Usuario administrador inicial:
- **Email**: `admin@test.com`
- **Contraseña**: `admin@test.com`

**🔒 Importante**: Cambia la contraseña del administrador después del primer acceso.

## 🎯 Características Principales

Sistema completo de e-commerce con roles diferenciados y funcionalidades específicas para cada tipo de usuario.

### 👤 Para Clientes

**🛒 Experiencia de Compra**
- Catálogo completo con filtros avanzados (categoría, talle, género, color)
- Visualización detallada de productos con variantes disponibles
- Carrito inteligente con validación de stock en tiempo real
- Checkout seguro con múltiples métodos de pago
- Seguimiento de pedidos en tiempo real
- Cálculo automático de costos de envío

**👨‍💼 Gestión de Cuenta**
- Registro e inicio de sesión seguro
- Perfil personal editable
- Recuperación de contraseña con preguntas de seguridad
- Historial completo de pedidos y transacciones
- Sistema de devoluciones y cancelaciones
- Cancelar o solicitar devoluciones cuando corresponda

### 👔 Para el Equipo

**📦 Gestión de Inventario**
- Control detallado de stock por variante (talle/color)
- Seguimiento automático de movimientos (ventas, devoluciones, ajustes)
- Validación en tiempo real de disponibilidad de stock
- Alertas de productos sin stock o con stock crítico

**📋 Administración de Pedidos**
- Dashboard completo con estados de pedidos
- Aprobación y rechazo de pagos con gestión automática de stock
- Procesamiento de devoluciones con restauración automática de inventario
- Seguimiento completo del ciclo de vida de cada pedido

**👥 Sistema de Usuarios y Roles**
- Roles diferenciados: Administrador, Ventas, Marketing, Cliente
- Paneles personalizados según permisos
- Gestión segura de usuarios con encriptación de contraseñas

## 👥 Roles y Funcionalidades

### 🛒 Cliente
- **Compra**: Catálogo completo, carrito inteligente, checkout seguro
- **Pedidos**: Seguimiento en tiempo real, cancelaciones, devoluciones
- **Cuenta**: Perfil editable, historial completo, recuperación de contraseña

### 💼 Ventas
- **Pedidos**: Gestión completa del ciclo de vida, estados y modificaciones
- **Pagos**: Aprobación/rechazo con gestión automática de stock
- **Devoluciones**: Procesamiento con restauración automática de inventario
- **Clientes**: Consulta de datos y estadísticas de pedidos
- **Métodos de Pago**: Configuración y gestión
- **Reportes**: Productos más vendidos, pedidos pendientes

### 📢 Marketing
- **Productos**: CRUD completo con variantes (talle/color), stock e imágenes
- **Catálogo**: Gestión de categorías y productos activos/inactivos
- **Carga Masiva**: Importación de productos vía CSV con validación
- **Imágenes**: Gestión de fotos por producto y variante
- **Analytics**: Productos más vendidos, stock sin movimiento

### ⚙️ Administrador
- **Usuarios**: Gestión completa de usuarios y roles
- **Sistema**: Acceso total a todas las funcionalidades
- **Seguridad**: Controles de integridad (no puede eliminarse a sí mismo)
- **Estadísticas**: Dashboard completo con métricas de usuarios


## 🏗️ Arquitectura y Tecnología

### 🛠️ Stack Tecnológico
- **Backend**: PHP 7.4+ con arquitectura procedural modular
- **Base de Datos**: MySQL/MariaDB con transacciones ACID
- **Frontend**: HTML5, CSS3, JavaScript vanilla
- **UI Framework**: Bootstrap 4+ para interfaz responsiva
- **Email**: PHPMailer para notificaciones automáticas

### 🏢 Lógica de Negocio Principal

#### Sistema de Reserva de Stock (24h)
**Propósito**: Prevenir condiciones de carrera en ventas simultáneas

**Flujo Implementado:**
1. **Creación de pedido** → Stock se reserva automáticamente
2. **Validación continua** → Stock disponible = Total - Reservado - Vendido
3. **Aprobación de pago** → Reserva se convierte en venta confirmada
4. **Expiración automática** → Después de 24h sin pago, stock se libera
5. **Cancelación** → Stock se restaura inmediatamente

**Beneficios:**
- ✅ Eliminación de overbooking
- ✅ Detección temprana de faltante de stock
- ✅ Experiencia de usuario mejorada
- ✅ Automatización completa sin intervención manual

#### Estados de Pedido y Pago
- **Pedido**: pendiente → preparación → en viaje → completado/cancelado
- **Pago**: pendiente → pendiente_aprobación → aprobado/rechazado/cancelado
- **Sincronización**: Estados acoplados con validaciones automáticas

## 🔒 Seguridad y Compatibilidad

- **Autenticación**: Sistema seguro con encriptación de contraseñas (password_hash)
- **Validaciones**: Sanitización completa de datos y protección XSS/CSRF
- **Sesiones**: Manejo seguro con regeneración automática de IDs
- **Base de Datos**: Transacciones ACID y prepared statements
- **Compatibilidad**: WAMP, XAMPP, LAMP y servidores cloud
- **Email**: Envío seguro con PHPMailer y SMTP

## 🛠️ Desarrollo y Contribución

### Requisitos para Desarrollo
- PHP 7.4+ con extensiones: mysqli, pdo_mysql, mbstring, gd
- Composer para dependencias
- Node.js para assets (opcional)
- Git para control de versiones

### Comandos Útiles
```bash
# Instalar dependencias
composer install

# Verificar sintaxis PHP
find . -name "*.php" -exec php -l {} \;

# Limpiar cache
rm -rf temp/* logs/*.log
```

### Estructura de Desarrollo
- **Arquitectura**: Modular con separación clara de responsabilidades
- **Patrones**: Factory para conexiones, Strategy para validaciones

## 📋 Estructura del Proyecto
```
tienda-seda-lino/
├── 📁 config/          # Configuraciones de BD y servicios externos
├── 📁 includes/        # Lógica de negocio y funciones auxiliares
├── 📁 css/            # Estilos CSS y Bootstrap
├── 📁 js/             # JavaScript del frontend
├── 📁 templates/      # Plantillas de email HTML
├── 📁 sql/            # Scripts de base de datos
├── 📁 uploads/        # Archivos CSV ejemplo (para carga masiva de productos)
└── 📄 *.php           # Páginas principales del sistema
```

## 🚀 Inicio Rápido

```bash
# 1. Clonar repositorio
git clone [url-del-repositorio]
cd tienda-seda-lino

# 2. Configurar base de datos
mysql -u root -p < sql/database_estructura.sql
mysql -u root -p < sql/crear_usuarios_test.sql
mysql -u root -p < sql/forma_pago_inicial.sql

# 3. Configurar conexión
cp config/database.example.php config/database.php
# Editar config/database.php con tus credenciales

# 4. Acceder
# Usuario: admin@test.com
# Contraseña: admin@test.com
```

## 🎯 Características Destacadas

- ✅ **Sistema de Reserva de Stock**: Previene overbooking con reservas de 24h
- ✅ **Gestión Multi-Rol**: 4 tipos de usuarios con permisos diferenciados
- ✅ **Catálogo Avanzado**: Filtros por categoría, talle, género y color
- ✅ **Dashboard Administrativo**: Paneles personalizados por rol
- ✅ **Sistema de Emails**: Notificaciones automáticas para pedidos y pagos
- ✅ **Validaciones en Tiempo Real**: Stock, formularios y estados

## 📄 Licencia

Proyecto académico desarrollado para ESBA (Escuela Superior de Buenos Aires).
Uso exclusivamente educativo e institucional.

---

**Última actualización**: Diciembre 2025 | Versión: 1.0.0
