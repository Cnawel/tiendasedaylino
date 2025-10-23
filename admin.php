<?php
session_start();

// ============================================================================
// VERIFICACIÓN DE ACCESO - SOLO ADMINISTRADORES
// ============================================================================

// Verificar que el usuario esté logueado
if (!isset($_SESSION['id_usuario'])) {
    header('Location: login.php');
    exit;
}

// Conectar a la base de datos (usar configuración centralizada)
require_once 'config/database.php';

// Verificar que el usuario tenga rol admin o email permitido
$id_usuario = $_SESSION['id_usuario'];
$stmt = $mysqli->prepare("SELECT email, rol FROM Usuarios WHERE id_usuario = ? LIMIT 1");
$stmt->bind_param('i', $id_usuario);
$stmt->execute();
$result = $stmt->get_result();
$usuario_actual = $result->fetch_assoc();

// Emails de admin permitidos explícitamente
$emails_admin_permitidos = ['admin@sedaylino.com','admin@test.com'];

$es_admin_por_rol = $usuario_actual && strtolower($usuario_actual['rol']) === 'admin';
$es_admin_por_email = $usuario_actual && in_array(strtolower($usuario_actual['email']), $emails_admin_permitidos, true);

if (!$es_admin_por_rol && !$es_admin_por_email) {
    header('Location: index.php');
    exit;
}

// ============================================================================
// PROCESAMIENTO DE FORMULARIOS (MENSAJES COMPARTIDOS)
// ============================================================================

$mensaje = '';
$mensaje_tipo = '';

// ============================================================================
// PROCESAR CREACIÓN DE USUARIOS STAFF (Ventas/Marketing)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_usuario_staff'])) {
    $nombre_staff = trim($_POST['nombre_staff'] ?? '');
    $apellido_staff = trim($_POST['apellido_staff'] ?? '');
    $email_staff = trim($_POST['email_staff'] ?? '');
    $rol_staff = $_POST['rol_staff'] ?? '';

    // Validar rol permitido
    $roles_staff_validos = ['ventas', 'marketing'];

    if ($nombre_staff === '' || $apellido_staff === '' || $email_staff === '' || !in_array($rol_staff, $roles_staff_validos, true)) {
        $mensaje = 'Datos inválidos. Completa todos los campos y selecciona un rol válido.';
        $mensaje_tipo = 'danger';
    } else {
        // Verificar email existente
        $stmt = $mysqli->prepare("SELECT 1 FROM Usuarios WHERE email = ? LIMIT 1");
        $stmt->bind_param('s', $email_staff);
        $stmt->execute();
        $existe = $stmt->get_result()->num_rows > 0;

        if ($existe) {
            $mensaje = 'El email ya está registrado en el sistema.';
            $mensaje_tipo = 'warning';
        } else {
            // Generar contraseña aleatoria segura
            $password_plano = substr(str_replace(['/', '+', '='], '', base64_encode(random_bytes(12))), 0, 12);
            $password_hash = password_hash($password_plano, PASSWORD_BCRYPT);

            $stmt = $mysqli->prepare("INSERT INTO Usuarios (nombre, apellido, email, contrasena, rol, fecha_registro) VALUES (?,?,?,?,?,NOW())");
            $stmt->bind_param('sssss', $nombre_staff, $apellido_staff, $email_staff, $password_hash, $rol_staff);

            if ($stmt->execute()) {
                $mensaje = 'Usuario de ' . strtoupper($rol_staff) . ' creado. Contraseña: ' . $password_plano;
                $mensaje_tipo = 'success';
            } else {
                $mensaje = 'Error al crear el usuario de staff.';
                $mensaje_tipo = 'danger';
            }
        }
    }
}

// ============================================================================
// PROCESAR CAMBIOS DE ROL
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_rol'])) {
    $usuario_id = intval($_POST['usuario_id']);
    $nuevo_rol = $_POST['nuevo_rol'];

    // Validar que el rol sea válido (coincidente con ENUM en BD)
    $roles_validos = ['cliente', 'ventas', 'marketing', 'admin'];

    if (in_array($nuevo_rol, $roles_validos, true)) {
        // No permitir que el admin se quite su propio rol de admin
        if ($usuario_id == $id_usuario && $nuevo_rol !== 'admin') {
            $mensaje = 'No puedes cambiar tu propio rol de administrador';
            $mensaje_tipo = 'warning';
        } else {
            $stmt = $mysqli->prepare("UPDATE Usuarios SET rol = ? WHERE id_usuario = ?");
            $stmt->bind_param('si', $nuevo_rol, $usuario_id);

            if ($stmt->execute()) {
                $mensaje = 'Rol actualizado correctamente';
                $mensaje_tipo = 'success';
            } else {
                $mensaje = 'Error al actualizar el rol';
                $mensaje_tipo = 'danger';
            }
        }
    } else {
        $mensaje = 'Rol no válido';
        $mensaje_tipo = 'danger';
    }
}

// ============================================================================
// ACTUALIZAR USUARIO (nombre, apellido, email, rol)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_usuario'])) {
    $edit_user_id = intval($_POST['edit_user_id'] ?? 0);
    $edit_nombre = trim($_POST['edit_nombre'] ?? '');
    $edit_apellido = trim($_POST['edit_apellido'] ?? '');
    $edit_email = trim($_POST['edit_email'] ?? '');
    $edit_rol = $_POST['edit_rol'] ?? '';

    $roles_validos = ['cliente', 'ventas', 'marketing', 'admin'];

    if ($edit_user_id <= 0 || $edit_nombre === '' || $edit_apellido === '' || $edit_email === '' || !in_array($edit_rol, $roles_validos, true)) {
        $mensaje = 'Datos inválidos al actualizar usuario';
        $mensaje_tipo = 'danger';
    } else {
        // Evitar quitarse el rol de admin a sí mismo
        if ($edit_user_id == $id_usuario && $edit_rol !== 'admin') {
            $mensaje = 'No puedes quitarte tu rol de ADMIN';
            $mensaje_tipo = 'warning';
        } else {
            // Verificar email único (excluyendo el mismo usuario)
            $stmt = $mysqli->prepare("SELECT 1 FROM Usuarios WHERE email = ? AND id_usuario <> ? LIMIT 1");
            $stmt->bind_param('si', $edit_email, $edit_user_id);
            $stmt->execute();
            $dup = $stmt->get_result()->num_rows > 0;
            
            if ($dup) {
                $mensaje = 'El email ya está en uso por otro usuario';
                $mensaje_tipo = 'warning';
            } else {
                $stmt = $mysqli->prepare("UPDATE Usuarios SET nombre = ?, apellido = ?, email = ?, rol = ? WHERE id_usuario = ?");
                $stmt->bind_param('ssssi', $edit_nombre, $edit_apellido, $edit_email, $edit_rol, $edit_user_id);
                if ($stmt->execute()) {
                    $mensaje = 'Usuario actualizado correctamente';
                    $mensaje_tipo = 'success';
                } else {
                    $mensaje = 'Error al actualizar el usuario';
                    $mensaje_tipo = 'danger';
                }
            }
        }
    }
}

// ============================================================================
// ELIMINAR USUARIO (con validaciones de referencias)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_usuario'])) {
    $del_user_id = intval($_POST['del_user_id'] ?? 0);
    if ($del_user_id <= 0) {
        $mensaje = 'Usuario inválido para eliminar';
        $mensaje_tipo = 'danger';
    } elseif ($del_user_id == $id_usuario) {
        $mensaje = 'No puedes eliminar tu propio usuario';
        $mensaje_tipo = 'warning';
    } else {
        // Verificar si tiene pedidos
        $stmt = $mysqli->prepare("SELECT 1 FROM Pedidos WHERE id_usuario = ? LIMIT 1");
        $stmt->bind_param('i', $del_user_id);
        $stmt->execute();
        $tiene_pedidos = $stmt->get_result()->num_rows > 0;

        if ($tiene_pedidos) {
            $mensaje = 'No se puede eliminar: el usuario tiene pedidos asociados';
            $mensaje_tipo = 'danger';
        } else {
            // Desasociar movimientos de stock
            $stmt = $mysqli->prepare("UPDATE Movimientos_Stock SET id_usuario = NULL WHERE id_usuario = ?");
            $stmt->bind_param('i', $del_user_id);
            $stmt->execute();

            $stmt = $mysqli->prepare("DELETE FROM Usuarios WHERE id_usuario = ?");
            $stmt->bind_param('i', $del_user_id);
            if ($stmt->execute()) {
                $mensaje = 'Usuario eliminado correctamente';
                $mensaje_tipo = 'success';
            } else {
                $mensaje = 'Error al eliminar usuario';
                $mensaje_tipo = 'danger';
            }
        }
    }
}

// ============================================================================
// ELIMINAR PEDIDO (borra detalle y pagos)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_pedido'])) {
    $del_pedido_id = intval($_POST['del_pedido_id'] ?? 0);
    if ($del_pedido_id <= 0) {
        $mensaje = 'Pedido inválido para eliminar';
        $mensaje_tipo = 'danger';
    } else {
        // Usar transacción simple con MySQLi
        $mysqli->begin_transaction();
        try {
            $stmt = $mysqli->prepare("DELETE FROM Detalle_Pedido WHERE id_pedido = ?");
            $stmt->bind_param('i', $del_pedido_id);
            $stmt->execute();

            $stmt = $mysqli->prepare("DELETE FROM Pagos WHERE id_pedido = ?");
            $stmt->bind_param('i', $del_pedido_id);
            $stmt->execute();

            $stmt = $mysqli->prepare("DELETE FROM Pedidos WHERE id_pedido = ?");
            $stmt->bind_param('i', $del_pedido_id);
            $stmt->execute();

            $mysqli->commit();
            $mensaje = 'Pedido eliminado correctamente';
            $mensaje_tipo = 'success';
        } catch (Exception $e) {
            $mysqli->rollback();
            $mensaje = 'Error al eliminar el pedido';
            $mensaje_tipo = 'danger';
        }
    }
}

// ============================================================================
// ACTUALIZAR PRODUCTO (nombre, precio, categoría, genero)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_producto'])) {
    $edit_producto_id = intval($_POST['edit_producto_id'] ?? 0);
    $edit_nombre_producto = trim($_POST['edit_nombre_producto'] ?? '');
    $edit_precio_actual = trim($_POST['edit_precio_actual'] ?? '');
    $edit_id_categoria = intval($_POST['edit_id_categoria'] ?? 0);
    $edit_genero = $_POST['edit_genero'] ?? '';

    $generos_validos = ['hombre','mujer','unisex'];

    if ($edit_producto_id <= 0 || $edit_nombre_producto === '' || !is_numeric($edit_precio_actual) || $edit_id_categoria <= 0 || !in_array($edit_genero, $generos_validos, true)) {
        $mensaje = 'Datos inválidos al actualizar producto';
        $mensaje_tipo = 'danger';
    } else {
        $stmt = $mysqli->prepare("UPDATE Productos SET nombre_producto = ?, precio_actual = ?, id_categoria = ?, genero = ? WHERE id_producto = ?");
        $precio_decimal = (float)$edit_precio_actual;
        $stmt->bind_param('sdisi', $edit_nombre_producto, $precio_decimal, $edit_id_categoria, $edit_genero, $edit_producto_id);
        if ($stmt->execute()) {
            $mensaje = 'Producto actualizado correctamente';
            $mensaje_tipo = 'success';
        } else {
            $mensaje = 'Error al actualizar el producto';
            $mensaje_tipo = 'danger';
        }
    }
}

// ============================================================================
// ELIMINAR PRODUCTO (si no tiene ventas)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_producto'])) {
    $del_producto_id = intval($_POST['del_producto_id'] ?? 0);
    if ($del_producto_id <= 0) {
        $mensaje = 'Producto inválido para eliminar';
        $mensaje_tipo = 'danger';
    } else {
        // Verificar si alguna variante fue vendida (detalle de pedido)
        $sql_check = "SELECT 1 FROM Detalle_Pedido dp INNER JOIN Stock_Variantes sv ON sv.id_variante = dp.id_variante WHERE sv.id_producto = ? LIMIT 1";
        $stmt = $mysqli->prepare($sql_check);
        $stmt->bind_param('i', $del_producto_id);
        $stmt->execute();
        $tiene_ventas = $stmt->get_result()->num_rows > 0;

        if ($tiene_ventas) {
            $mensaje = 'No se puede eliminar: el producto tiene ventas asociadas';
            $mensaje_tipo = 'danger';
        } else {
            $mysqli->begin_transaction();
            try {
                // Borrar fotos y variantes primero
                $stmt = $mysqli->prepare("DELETE FROM Fotos_Producto WHERE id_producto = ?");
                $stmt->bind_param('i', $del_producto_id);
                $stmt->execute();

                $stmt = $mysqli->prepare("DELETE FROM Stock_Variantes WHERE id_producto = ?");
                $stmt->bind_param('i', $del_producto_id);
                $stmt->execute();

                $stmt = $mysqli->prepare("DELETE FROM Productos WHERE id_producto = ?");
                $stmt->bind_param('i', $del_producto_id);
                $stmt->execute();

                $mysqli->commit();
                $mensaje = 'Producto eliminado correctamente';
                $mensaje_tipo = 'success';
            } catch (Exception $e) {
                $mysqli->rollback();
                $mensaje = 'Error al eliminar el producto';
                $mensaje_tipo = 'danger';
            }
        }
    }
}

// ============================================================================
// OBTENER TODOS LOS USUARIOS
// ============================================================================

$sql = "SELECT id_usuario, nombre, apellido, email, rol, fecha_registro 
        FROM Usuarios 
        ORDER BY 
            CASE rol 
                WHEN 'admin' THEN 1 
                WHEN 'ventas' THEN 2 
                WHEN 'marketing' THEN 3 
                WHEN 'cliente' THEN 4 
            END,
            apellido, nombre";

$usuarios = $mysqli->query($sql);

// Contar usuarios por rol
$sql_stats = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN rol = 'admin' THEN 1 ELSE 0 END) as admins,
    SUM(CASE WHEN rol = 'ventas' THEN 1 ELSE 0 END) as ventas,
    SUM(CASE WHEN rol = 'marketing' THEN 1 ELSE 0 END) as marketing,
    SUM(CASE WHEN rol = 'cliente' THEN 1 ELSE 0 END) as clientes
FROM Usuarios";

$stats = $mysqli->query($sql_stats)->fetch_assoc();

// ============================================================================
// OBTENER PEDIDOS EN CURSO (pendiente, pagado, enviado)
// ============================================================================
$sql_pedidos_en_curso = "
    SELECT p.id_pedido,
           u.nombre,
           u.apellido,
           p.fecha_pedido,
           p.estado_pedido,
           SUM(dp.cantidad * dp.precio_unitario) AS total
    FROM Pedidos p
    INNER JOIN Usuarios u ON u.id_usuario = p.id_usuario
    INNER JOIN Detalle_Pedido dp ON dp.id_pedido = p.id_pedido
    WHERE p.estado_pedido IN ('pendiente','pagado','enviado')
    GROUP BY p.id_pedido, u.nombre, u.apellido, p.fecha_pedido, p.estado_pedido
    ORDER BY p.fecha_pedido DESC
    LIMIT 100
";
$pedidos_en_curso = $mysqli->query($sql_pedidos_en_curso);

// ============================================================================
// OBTENER PRODUCTOS (lista de artículos)
// ============================================================================
$sql_productos = "
    SELECT p.id_producto,
           p.nombre_producto,
           p.precio_actual,
           p.genero,
           p.id_categoria,
           c.nombre_categoria AS categoria
    FROM Productos p
    INNER JOIN Categorias c ON c.id_categoria = p.id_categoria
    ORDER BY p.id_producto DESC
    LIMIT 200
";
$lista_productos = $mysqli->query($sql_productos);

// ============================================================================
// OBTENER CATEGORÍAS (para editar productos)
// ============================================================================
$sql_categorias = "SELECT id_categoria, nombre_categoria FROM Categorias ORDER BY nombre_categoria";
$res_categorias = $mysqli->query($sql_categorias);
$categorias_list = [];
if ($res_categorias) {
    while ($cat = $res_categorias->fetch_assoc()) {
        $categorias_list[] = $cat;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración | Seda y Lino</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .admin-page {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .admin-header {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .stats-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .stats-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .users-table {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .badge-rol {
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .badge-ADMIN {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }
        
        .badge-VENTAS {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }
        
        .badge-MARKETING {
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
        }
        
        .badge-CLIENTE {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
        }
        
        .btn-cambiar-rol {
            font-size: 0.85rem;
            padding: 0.4rem 0.8rem;
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3498db, #2ecc71);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            margin-right: 0.5rem;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg bg-body-tertiary">
            <div class="container-fluid">
                <a class="navbar-brand nombre-tienda" href="index.php">SEDA Y LINO</a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                    <ul class="navbar-nav lista-nav">
                        <li class="nav-item">
                            <a class="nav-link link-tienda" href="index.php">INICIO</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link link-tienda" href="nosotros.php">NOSOTROS</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link link-tienda" href="index.php#productos">PRODUCTOS</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link link-tienda" href="index.php#contacto">CONTACTO</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="perfil.php" title="Mi Perfil">
                                <img src="iconos/avatar-usuario.png" alt="icono de avatar de usuario">
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="admin-page">
        <div class="container">
            <!-- Header -->
            <div class="admin-header">
                <h1><i class="fas fa-shield-alt me-3"></i>Panel de Administración</h1>
                <p class="mb-0">Gestión de usuarios, pedidos y productos</p>
            </div>
            
            <!-- Mensajes -->
            <?php if ($mensaje): ?>
            <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show animate-in" role="alert">
                <?php if ($mensaje_tipo === 'success'): ?>
                    <i class="fas fa-check-circle me-2"></i>
                <?php elseif ($mensaje_tipo === 'warning'): ?>
                    <i class="fas fa-exclamation-triangle me-2"></i>
                <?php else: ?>
                    <i class="fas fa-exclamation-circle me-2"></i>
                <?php endif; ?>
                <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
            
            <!-- Accesos rápidos -->
            <div class="mb-4">
                <a href="https://php-myadmin.net/db_structure.php?db=if0_40082852_tiendasedaylino_db" target="_blank" rel="noopener noreferrer" class="btn btn-warning me-2">
                    <i class="fas fa-database me-2"></i>phpMyAdmin (BD)
                </a>
            </div>
            
            <!-- Estadísticas -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card text-center">
                        <div class="stats-icon text-primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3><?= $stats['total'] ?></h3>
                        <p class="text-muted mb-0">Total Usuarios</p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card text-center">
                        <div class="stats-icon text-danger">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3><?= $stats['admins'] ?></h3>
                        <p class="text-muted mb-0">Administradores</p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card text-center">
                        <div class="stats-icon text-info">
                            <i class="fas fa-briefcase"></i>
                        </div>
                        <h3><?= $stats['ventas'] + $stats['marketing'] ?></h3>
                        <p class="text-muted mb-0">Staff (Ventas + Marketing)</p>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="stats-card text-center">
                        <div class="stats-icon text-secondary">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <h3><?= $stats['clientes'] ?></h3>
                        <p class="text-muted mb-0">Clientes</p>
                    </div>
                </div>
            </div>
            
            <!-- Crear usuarios de Staff -->
            <div class="users-table mb-4">
                <h3 class="mb-3"><i class="fas fa-user-plus me-2"></i>Crear usuario de Staff</h3>
                <form method="POST" class="row g-3">
                    <input type="hidden" name="crear_usuario_staff" value="1">
                    <div class="col-md-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" class="form-control" name="nombre_staff" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Apellido</label>
                        <input type="text" class="form-control" name="apellido_staff" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email_staff" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Rol</label>
                        <select class="form-select" name="rol_staff" required>
                            <option value="ventas">Ventas</option>
                            <option value="marketing">Marketing</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>Crear usuario
                        </button>
                        <small class="text-muted ms-2">La contraseña se generará automáticamente y se mostrará una vez creado.</small>
                    </div>
                </form>
            </div>
            
            <!-- Tabla de Usuarios -->
            <div class="users-table">
                <h3 class="mb-4"><i class="fas fa-list me-2"></i>Gestión de Usuarios</h3>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Rol Actual</th>
                                <th>Fecha Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $usuarios->fetch_assoc()): ?>
                            <tr>
                                <td><?= $user['id_usuario'] ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar">
                                            <?= strtoupper(substr($user['nombre'], 0, 1) . substr($user['apellido'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <strong><?= htmlspecialchars($user['nombre'] . ' ' . $user['apellido']) ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <?php $rol_lower = strtolower($user['rol']); ?>
                                    <span class="badge badge-rol badge-<?= strtoupper($rol_lower) ?>">
                                        <?= strtoupper($rol_lower) ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($user['fecha_registro'])) ?></td>
                                <td>
                                    <button type="button" 
                                            class="btn btn-primary btn-sm btn-cambiar-rol me-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#cambiarRolModal<?= $user['id_usuario'] ?>"
                                            <?= ($user['id_usuario'] == $id_usuario) ? 'title="No puedes cambiar tu propio rol"' : '' ?>>
                                        <i class="fas fa-pen me-1"></i>Modificar
                                    </button>
                                    <button type="button" 
                                            class="btn btn-outline-danger btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#eliminarUsuarioModal<?= $user['id_usuario'] ?>">
                                        <i class="fas fa-trash me-1"></i>Eliminar
                                    </button>
                                    
                                    <!-- Modal para modificar usuario/rol -->
                                    <div class="modal fade" id="cambiarRolModal<?= $user['id_usuario'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Modificar Usuario</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="usuario_id" value="<?= $user['id_usuario'] ?>">
                                                        <input type="hidden" name="edit_user_id" value="<?= $user['id_usuario'] ?>">
                                                        
                                                        <div class="row g-2 mb-3">
                                                            <div class="col-md-6">
                                                                <label class="form-label">Nombre</label>
                                                                <input type="text" class="form-control" name="edit_nombre" value="<?= htmlspecialchars($user['nombre']) ?>" required>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <label class="form-label">Apellido</label>
                                                                <input type="text" class="form-control" name="edit_apellido" value="<?= htmlspecialchars($user['apellido']) ?>" required>
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <label class="form-label">Email</label>
                                                            <input type="email" class="form-control" name="edit_email" value="<?= htmlspecialchars($user['email']) ?>" required>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label"><strong>Rol Actual:</strong></label>
                                                            <p><span class="badge badge-rol badge-<?= strtoupper($rol_lower) ?>"><?= strtoupper($rol_lower) ?></span></p>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="nuevo_rol<?= $user['id_usuario'] ?>" class="form-label"><strong>Nuevo Rol:</strong></label>
                                                            <select class="form-select" name="nuevo_rol" id="nuevo_rol<?= $user['id_usuario'] ?>" required>
                                                                <option value="cliente" <?= $rol_lower == 'cliente' ? 'selected' : '' ?>>CLIENTE</option>
                                                                <option value="ventas" <?= $rol_lower == 'ventas' ? 'selected' : '' ?>>VENTAS</option>
                                                                <option value="marketing" <?= $rol_lower == 'marketing' ? 'selected' : '' ?>>MARKETING</option>
                                                                <option value="admin" <?= $rol_lower == 'admin' ? 'selected' : '' ?>>ADMIN</option>
                                                            </select>
                                                            <small class="text-muted">
                                                                <i class="fas fa-info-circle me-1"></i>
                                                                <?php if ($user['id_usuario'] == $id_usuario): ?>
                                                                    <strong class="text-warning">No puedes cambiar tu propio rol de ADMIN</strong>
                                                                <?php else: ?>
                                                                    Selecciona el nuevo rol para este usuario
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>
                                                        
                                                        <div class="alert alert-info">
                                                            <small>
                                                                <strong>Descripción de roles:</strong><br>
                                                                <strong>CLIENTE:</strong> Usuario estándar con acceso básico<br>
                                                                <strong>VENTAS:</strong> Acceso a gestión de ventas<br>
                                                                <strong>MARKETING:</strong> Acceso a herramientas de marketing<br>
                                                                <strong>ADMIN:</strong> Acceso total al sistema
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" name="actualizar_usuario" class="btn btn-primary"><i class="fas fa-save me-1"></i>Guardar Cambios</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Modal eliminar usuario -->
                                    <div class="modal fade" id="eliminarUsuarioModal<?= $user['id_usuario'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Eliminar Usuario</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <div class="modal-body">
                                                        <p>¿Confirmas eliminar al usuario <strong><?= htmlspecialchars($user['nombre'] . ' ' . $user['apellido']) ?></strong>?</p>
                                                        <input type="hidden" name="del_user_id" value="<?= $user['id_usuario'] ?>">
                                                        <div class="alert alert-warning">
                                                            <small>Si el usuario tiene pedidos asociados, no se podrá eliminar.</small>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" name="eliminar_usuario" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Eliminar</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Pedidos en curso -->
            <div class="users-table mt-4">
                <h3 class="mb-4"><i class="fas fa-receipt me-2"></i>Pedidos en curso</h3>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID Pedido</th>
                                <th>Cliente</th>
                                <th>Fecha</th>
                                <th>Estado</th>
                                <th>Total</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($pedidos_en_curso && $pedidos_en_curso->num_rows): ?>
                                <?php while ($p = $pedidos_en_curso->fetch_assoc()): ?>
                                <tr>
                                    <td>#<?= $p['id_pedido'] ?></td>
                                    <td><?= htmlspecialchars($p['nombre'] . ' ' . $p['apellido']) ?></td>
                                    <td><?= date('d/m/Y H:i', strtotime($p['fecha_pedido'])) ?></td>
                                    <td><span class="badge text-bg-secondary"><?= ucfirst($p['estado_pedido']) ?></span></td>
                                    <td>$<?= number_format($p['total'], 2, ',', '.') ?></td>
                                    <td>
                                        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#eliminarPedidoModal<?= $p['id_pedido'] ?>">
                                            <i class="fas fa-trash me-1"></i>Eliminar
                                        </button>
                                        <div class="modal fade" id="eliminarPedidoModal<?= $p['id_pedido'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Eliminar Pedido #<?= $p['id_pedido'] ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST" action="">
                                                        <div class="modal-body">
                                                            <p>Esta acción eliminará el pedido y sus detalles/pagos asociados. ¿Confirmas?</p>
                                                            <input type="hidden" name="del_pedido_id" value="<?= $p['id_pedido'] ?>">
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                            <button type="submit" name="eliminar_pedido" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Eliminar</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center text-muted">No hay pedidos en curso</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Lista de productos -->
            <div class="users-table mt-4">
                <h3 class="mb-4"><i class="fas fa-boxes-stacked me-2"></i>Artículos en la base de datos</h3>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Producto</th>
                                <th>Categoría</th>
                                <th>Género</th>
                                <th>Precio</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($lista_productos && $lista_productos->num_rows): ?>
                                <?php while ($prod = $lista_productos->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $prod['id_producto'] ?></td>
                                    <td><?= htmlspecialchars($prod['nombre_producto']) ?></td>
                                    <td><?= htmlspecialchars($prod['categoria']) ?></td>
                                    <td><?= ucfirst($prod['genero']) ?></td>
                                    <td>$<?= number_format($prod['precio_actual'], 2, ',', '.') ?></td>
                                    <td>
                                        <button type="button" class="btn btn-primary btn-sm me-1" data-bs-toggle="modal" data-bs-target="#editarProductoModal<?= $prod['id_producto'] ?>">
                                            <i class="fas fa-pen me-1"></i>Modificar
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#eliminarProductoModal<?= $prod['id_producto'] ?>">
                                            <i class="fas fa-trash me-1"></i>Eliminar
                                        </button>

                                        <!-- Modal editar producto -->
                                        <div class="modal fade" id="editarProductoModal<?= $prod['id_producto'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Modificar Producto #<?= $prod['id_producto'] ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST" action="">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="edit_producto_id" value="<?= $prod['id_producto'] ?>">
                                                            <div class="mb-3">
                                                                <label class="form-label">Nombre</label>
                                                                <input type="text" name="edit_nombre_producto" class="form-control" value="<?= htmlspecialchars($prod['nombre_producto']) ?>" required>
                                                            </div>
                                                            <div class="row g-2">
                                                                <div class="col-md-6">
                                                                    <label class="form-label">Precio</label>
                                                                    <input type="number" step="0.01" min="0" name="edit_precio_actual" class="form-control" value="<?= htmlspecialchars($prod['precio_actual']) ?>" required>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <label class="form-label">Género</label>
                                                                    <select class="form-select" name="edit_genero" required>
                                                                        <option value="hombre" <?= $prod['genero']=='hombre'?'selected':'' ?>>Hombre</option>
                                                                        <option value="mujer" <?= $prod['genero']=='mujer'?'selected':'' ?>>Mujer</option>
                                                                        <option value="unisex" <?= $prod['genero']=='unisex'?'selected':'' ?>>Unisex</option>
                                                                    </select>
                                                                </div>
                                                            </div>
                                                            <div class="mt-3">
                                                                <label class="form-label">Categoría</label>
                                                                <select class="form-select" name="edit_id_categoria" required>
                                                                    <?php foreach ($categorias_list as $cat): ?>
                                                                        <option value="<?= $cat['id_categoria'] ?>" <?= ($cat['id_categoria']==$prod['id_categoria'])?'selected':'' ?>><?= htmlspecialchars($cat['nombre_categoria']) ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                            <button type="submit" class="btn btn-primary" name="actualizar_producto"><i class="fas fa-save me-1"></i>Guardar Cambios</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Modal eliminar producto -->
                                        <div class="modal fade" id="eliminarProductoModal<?= $prod['id_producto'] ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Eliminar Producto #<?= $prod['id_producto'] ?></h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST" action="">
                                                        <div class="modal-body">
                                                            <p>¿Confirmas eliminar el producto <strong><?= htmlspecialchars($prod['nombre_producto']) ?></strong>?</p>
                                                            <input type="hidden" name="del_producto_id" value="<?= $prod['id_producto'] ?>">
                                                            <div class="alert alert-warning"><small>No se eliminará si tiene ventas asociadas.</small></div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                            <button type="submit" class="btn btn-danger" name="eliminar_producto"><i class="fas fa-trash me-1"></i>Eliminar</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center text-muted">No hay productos cargados</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Botones de acción -->
            <div class="text-center mt-4">
                <a href="perfil.php" class="btn btn-outline-primary me-2">
                    <i class="fas fa-user me-2"></i>Mi Perfil
                </a>
                <a href="index.php" class="btn btn-outline-secondary me-2">
                    <i class="fas fa-home me-2"></i>Volver al Inicio
                </a>
                <a href="logout.php" class="btn btn-outline-danger">
                    <i class="fas fa-sign-out-alt me-2"></i>Cerrar Sesión
                </a>
            </div>
        </div>
    </main>

    <footer class="footer-completo mt-5">
        <div class="container">
            <div class="row py-5">
                <!-- Columna 1: Sobre Nosotros -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">SEDA Y LINO</h5>
                    <p class="footer-texto">Elegancia que viste tus momentos. Prendas únicas de seda y lino con calidad artesanal.</p>
                    <div class="footer-redes mt-3">
                        <a href="https://www.facebook.com/?locale=es_LA" target="_blank" rel="noopener noreferrer" class="footer-red-social me-2" title="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://www.instagram.com/" target="_blank" rel="noopener noreferrer" class="footer-red-social me-2" title="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="https://x.com/?lang=es" target="_blank" rel="noopener noreferrer" class="footer-red-social" title="X (Twitter)">
                            <i class="fab fa-x-twitter"></i>
                        </a>
                    </div>
                </div>

                <!-- Columna 2: Navegación -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">Navegación</h5>
                    <ul class="footer-lista list-unstyled">
                        <li class="mb-2">
                            <a href="index.php" class="footer-link">
                                <i class="fas fa-home me-2"></i>Inicio
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="nosotros.php" class="footer-link">
                                <i class="fas fa-users me-2"></i>Nosotros
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=todos" class="footer-link">
                                <i class="fas fa-shopping-bag me-2"></i>Catálogo
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="index.php#contacto" class="footer-link">
                                <i class="fas fa-envelope me-2"></i>Contacto
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Columna 3: Categorías -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">Productos</h5>
                    <ul class="footer-lista list-unstyled">
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=Camisas" class="footer-link">
                                <i class="fas fa-angle-right me-2"></i>Camisas
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=Blusas" class="footer-link">
                                <i class="fas fa-angle-right me-2"></i>Blusas
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=Pantalones" class="footer-link">
                                <i class="fas fa-angle-right me-2"></i>Pantalones
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="catalogo.php?categoria=Shorts" class="footer-link">
                                <i class="fas fa-angle-right me-2"></i>Shorts
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Columna 4: Información de Contacto -->
                <div class="col-lg-3 col-md-6 mb-4">
                    <h5 class="footer-titulo mb-3">Contacto</h5>
                    <ul class="footer-lista list-unstyled">
                        <li class="mb-3">
                            <i class="fas fa-map-marker-alt me-2"></i>
                            <span class="footer-texto-small">Buenos Aires, Argentina</span>
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-phone me-2"></i>
                            <a href="tel:+541112345678" class="footer-link">+54 11 1234-5678</a>
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-envelope me-2"></i>
                            <a href="mailto:info@sedaylino.com" class="footer-link">info@sedaylino.com</a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Línea divisoria -->
            <hr class="footer-divider">

            <!-- Footer inferior -->
            <div class="row py-3">
                <div class="col-md-6 text-center text-md-start mb-2 mb-md-0">
                    <p class="footer-copyright mb-0">
                        <i class="fas fa-copyright me-1"></i> 2025 Seda y Lino. Todos los derechos reservados
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a href="terminos.php" class="footer-link-small me-3">Términos y Condiciones</a>
                    <a href="privacidad.php" class="footer-link-small">Política de Privacidad</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>

