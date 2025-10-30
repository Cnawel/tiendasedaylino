# Paleta de Colores - Tienda Seda y Lino

## 📋 Sistema de Diseño Centralizado

Este proyecto utiliza un sistema de variables CSS para mantener consistencia en los colores en todo el sitio. Las variables están definidas en `css/colors.css` y se cargan automáticamente en todas las páginas.

---

## 🎨 Estructura del Sistema

El sistema está dividido en **dos paletas principales**:

### 1. **Colores Tienda (Clientes)** - `:root`
- **Estilo**: Elegancia moderna estilo francés
- **Paleta**: Grises y cremas suaves
- **Aplicación**: Todas las páginas públicas (index, catálogo, detalle-producto, carrito, login, registro)

### 2. **Colores Paneles Internos** - `.panel-theme`
- **Estilo**: Software profesional
- **Paleta**: Azules oscuros, verdes, rojos, amarillos
- **Aplicación**: Páginas administrativas (admin.php, ventas.php, marketing.php)

---

## 📖 Uso de Variables CSS

### Importar el archivo

El archivo `colors.css` se carga automáticamente en `includes/head.php` antes de `style.css`. No necesitas hacer nada adicional.

### Sintaxis básica

```css
/* ❌ ANTES - Colores hardcodeados */
.mi-elemento {
    background-color: #8B8B7A;
    color: #FFFFFF;
    border: 1px solid #E8E8E5;
}

/* ✅ DESPUÉS - Usando variables */
.mi-elemento {
    background: var(--color-btn-primary);
    color: var(--color-btn-text);
    border: 1px solid var(--color-border-primary);
}
```

---

## 🎯 Variables Disponibles - Tienda (Clientes)

### Fondos
```css
--color-bg-primary         /* #FFFFFF */
--color-bg-secondary       /* #FBFBF9 */
--color-bg-tertiary       /* #FAFAF8 */
--color-bg-gradient-start  /* #FAFAF8 */
--color-bg-gradient-end    /* #F5F5F0 */
--color-bg-hover           /* #F0F0ED */
--color-bg-section         /* Gradiente completo */
```

### Textos
```css
--color-text-primary    /* #6B6B6B - Texto principal */
--color-text-secondary  /* #8B8B7A - Texto secundario */
--color-text-tertiary   /* #9B9B8B - Texto terciario */
--color-text-dark       /* #4A4A4A - Texto oscuro */
--color-text-light      /* #FFFFFF - Texto claro */
--color-text-muted      /* #9B9B8B - Texto atenuado */
```

### Bordes
```css
--color-border-primary    /* #E8E8E5 - Borde principal */
--color-border-secondary  /* #D4D4D0 - Borde secundario */
--color-border-tertiary   /* #C4C4C0 - Borde terciario */
--color-border-dark       /* #6B6B5F - Borde oscuro */
```

### Botones
```css
--color-btn-primary        /* Gradiente gris elegante */
--color-btn-primary-hover  /* Gradiente hover */
--color-btn-text           /* #FFFFFF */
--color-btn-shadow         /* Sombra sutil */
```

### Inputs
```css
--color-input-bg            /* #FFFFFF */
--color-input-border        /* #E8E8E5 */
--color-input-border-focus  /* #8B8B7A */
--color-input-shadow        /* Sombra focus */
```

### Tarjetas
```css
--color-card-bg           /* Gradiente blanco/crema */
--color-card-border       /* #E8E8E5 */
--color-card-shadow       /* Sombra suave */
--color-card-shadow-hover /* Sombra hover */
```

### Estados
```css
--color-active-bg      /* Fondo activo */
--color-active-text    /* Texto activo */
--color-active-border  /* Borde activo */
--color-hover-bg       /* Fondo hover */
--color-hover-border   /* Borde hover */
```

---

## 🎯 Variables Disponibles - Paneles Internos

### Fondos
```css
--color-bg-primary         /* #2c3e50 - Azul oscuro */
--color-bg-secondary       /* #34495e - Azul medio */
--color-bg-tertiary        /* #ecf0f1 - Gris claro */
```

### Botones Especiales
```css
--color-btn-success        /* Verde - Acciones positivas */
--color-btn-success-hover  /* Verde hover */
--color-btn-danger         /* Rojo - Acciones destructivas */
--color-btn-danger-hover   /* Rojo hover */
--color-btn-warning        /* Amarillo - Advertencias */
--color-btn-warning-hover  /* Amarillo hover */
```

### Estados y Badges
```css
--color-success        /* Verde éxito */
--color-success-light  /* Verde claro fondo */
--color-danger         /* Rojo peligro */
--color-danger-light   /* Rojo claro fondo */
--color-warning        /* Amarillo advertencia */
--color-warning-light  /* Amarillo claro fondo */
--color-info           /* Azul información */
--color-info-light     /* Azul claro fondo */
```

### Tablas
```css
--color-table-header       /* Encabezado tabla */
--color-table-header-text  /* Texto encabezado */
--color-table-row-hover    /* Hover fila */
--color-table-border       /* Bordes tabla */
```

---

## 📝 Ejemplos de Uso

### Ejemplo 1: Botón Principal
```css
.btn-primary {
    background: var(--color-btn-primary);
    color: var(--color-btn-text);
    border: none;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: var(--color-btn-primary-hover);
    box-shadow: 0 4px 12px var(--color-btn-shadow);
}
```

### Ejemplo 2: Input de Formulario
```css
.form-control {
    background-color: var(--color-input-bg);
    border: 1.5px solid var(--color-input-border);
    color: var(--color-text-primary);
}

.form-control:focus {
    border-color: var(--color-input-border-focus);
    box-shadow: 0 0 0 0.2rem var(--color-input-shadow);
}
```

### Ejemplo 3: Tarjeta
```css
.card {
    background: var(--color-card-bg);
    border: 1.5px solid var(--color-card-border);
    box-shadow: 0 4px 12px var(--color-card-shadow);
}

.card:hover {
    box-shadow: 0 8px 20px var(--color-card-shadow-hover);
    border-color: var(--color-hover-border);
}
```

### Ejemplo 4: Panel Administrativo con Botón de Éxito
```css
/* Solo funciona en .panel-theme */
.panel-theme .btn-success {
    background: var(--color-btn-success);
    color: var(--color-btn-text);
}

.panel-theme .btn-success:hover {
    background: var(--color-btn-success-hover);
}
```

---

## 🔄 Cómo Cambiar Colores

### Para cambiar un color globalmente:

1. Abre `css/colors.css`
2. Encuentra la variable que necesitas cambiar
3. Modifica el valor hexadecimal
4. Todos los elementos que usen esa variable se actualizarán automáticamente

**Ejemplo:**
```css
/* Cambiar el color del texto principal */
:root {
    --color-text-primary: #5A5A5A; /* Cambiar de #6B6B6B */
}
```

### Para agregar un nuevo color:

1. Agrega la variable en `css/colors.css`
2. En `:root` para tienda o `.panel-theme` para paneles
3. Usa un nombre descriptivo siguiendo la convención

**Ejemplo:**
```css
:root {
    --color-accent-new: #D4A574; /* Nuevo color acento */
}
```

---

## ✅ Buenas Prácticas

1. ✅ **Siempre usar variables** en lugar de colores hardcodeados
2. ✅ **Usar nombres descriptivos** que indiquen el propósito del color
3. ✅ **Mantener consistencia** usando las variables existentes antes de crear nuevas
4. ✅ **Documentar** cualquier variable nueva que agregues
5. ✅ **Probar** en ambos temas (tienda y panel) si el componente se usa en ambos

---

## ❌ Errores Comunes

### ❌ Error: Color hardcodeado
```css
.mi-elemento {
    background-color: #8B8B7A; /* ❌ No hacer esto */
}
```

### ✅ Solución: Usar variable
```css
.mi-elemento {
    background: var(--color-btn-primary); /* ✅ Correcto */
}
```

---

## 📚 Referencia Rápida

| Propósito | Variable Tienda | Variable Panel |
|-----------|----------------|----------------|
| Fondo principal | `--color-bg-primary` | `--color-bg-primary` |
| Texto principal | `--color-text-primary` | `--color-text-primary` |
| Botón principal | `--color-btn-primary` | `--color-btn-primary` |
| Botón éxito | N/A | `--color-btn-success` |
| Botón peligro | N/A | `--color-btn-danger` |
| Borde | `--color-border-primary` | `--color-border-primary` |

---

## 🔍 Detección Automática de Tema

El sistema detecta automáticamente si estás en una página de panel (admin.php, ventas.php, marketing.php) y aplica la clase `panel-theme` al `<body>`. Esto permite que las variables de panel se activen automáticamente.

**Archivo**: `includes/header.php` (líneas 26-33)

---

## 📞 Soporte

Si tienes dudas sobre qué variable usar o cómo implementar un color específico, consulta:
1. Este documento
2. `css/colors.css` - Lista completa de variables
3. `css/style.css` - Ejemplos de implementación

---

**Última actualización**: 2025
**Versión**: 1.0

