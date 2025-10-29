<?php
session_start();

// ============================================================================
// VERIFICACIÓN DE ACCESO - SOLO USUARIOS MARKETING
// ============================================================================

// Cargar sistema de autenticación centralizado
require_once 'includes/auth_check.php';

// Verificar que el usuario esté logueado y tenga rol marketing
requireRole('marketing');

// Obtener información del usuario actual
$id_usuario = getCurrentUserId();
$usuario_actual = getCurrentUser();

// Conectar a la base de datos
require_once 'config/database.php';

// ============================================================================
// PROCESAMIENTO DE FORMULARIOS
// ============================================================================

$mensaje = '';
$mensaje_tipo = '';

// Verificar que hay datos CSV en sesión
if (!isset($_SESSION['productos_csv_pendientes']) || empty($_SESSION['productos_csv_pendientes'])) {
    header('Location: marketing.php');
    exit;
}

$productos_csv = $_SESSION['productos_csv_pendientes'];
$nombre_archivo = $_SESSION['nombre_archivo_csv'] ?? 'archivo.csv';

// ============================================================================
// CONFIRMAR CARGA MASIVA
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_carga'])) {
    $productos_agrupados = agruparProductosCSV($productos_csv);
    
    $productos_insertados = 0;
    $variantes_insertadas = 0;
    $errores = [];
    
    // Iniciar transacción global
    $mysqli->begin_transaction();
    
    try {
        foreach ($productos_agrupados as $producto) {
            // Insertar producto principal
            $stmt = $mysqli->prepare("INSERT INTO Productos (nombre_producto, descripcion_producto, precio_actual, id_categoria, genero) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param('ssdis', 
                $producto['nombre_producto'], 
                $producto['descripcion_producto'], 
                $producto['precio_actual'], 
                $producto['id_categoria'], 
                $producto['genero']
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Error al insertar producto: ' . $producto['nombre_producto']);
            }
            
            $id_producto_nuevo = $mysqli->insert_id;
            $productos_insertados++;
            
            // Insertar registro de fotos vacío (se pueden agregar después)
            $stmt_fotos = $mysqli->prepare("INSERT INTO Fotos_Producto (id_producto) VALUES (?)");
            $stmt_fotos->bind_param('i', $id_producto_nuevo);
            $stmt_fotos->execute();
            
            // Insertar variantes
            foreach ($producto['variantes'] as $variante) {
                $stmt_variante = $mysqli->prepare("INSERT INTO Stock_Variantes (id_producto, talle, color, stock) VALUES (?, ?, ?, ?)");
                $stmt_variante->bind_param('issi', 
                    $id_producto_nuevo, 
                    $variante['talle'], 
                    $variante['color'], 
                    $variante['stock']
                );
                
                if (!$stmt_variante->execute()) {
                    throw new Exception('Error al insertar variante: ' . $variante['talle'] . ' ' . $variante['color']);
                }
                
                $id_variante_nueva = $mysqli->insert_id;
                $variantes_insertadas++;
                
                // Registrar movimiento de stock inicial
                $stmt_movimiento = $mysqli->prepare("INSERT INTO Movimientos_Stock (id_variante, tipo_movimiento, cantidad, id_usuario, observaciones) VALUES (?, 'ingreso', ?, ?, 'Stock inicial - Carga masiva CSV')");
                $stmt_movimiento->bind_param('iii', $id_variante_nueva, $variante['stock'], $id_usuario);
                $stmt_movimiento->execute();
            }
        }
        
        $mysqli->commit();
        
        // Limpiar datos de sesión
        unset($_SESSION['productos_csv_pendientes']);
        unset($_SESSION['nombre_archivo_csv']);
        unset($_SESSION['errores_csv']);
        
        $mensaje = "Carga masiva completada exitosamente: $productos_insertados productos y $variantes_insertadas variantes insertadas.";
        $mensaje_tipo = 'success';
        
    } catch (Exception $e) {
        $mysqli->rollback();
        $mensaje = 'Error durante la carga masiva: ' . $e->getMessage();
        $mensaje_tipo = 'danger';
    }
}

// ============================================================================
// CANCELAR CARGA MASIVA
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar_carga'])) {
    // Limpiar datos de sesión
    unset($_SESSION['productos_csv_pendientes']);
    unset($_SESSION['nombre_archivo_csv']);
    unset($_SESSION['errores_csv']);
    
    header('Location: marketing.php');
    exit;
}

// ============================================================================
// FUNCIÓN PARA AGRUPAR PRODUCTOS CSV
// ============================================================================
function agruparProductosCSV($productos_csv) {
    $productos_agrupados = [];
    
    foreach ($productos_csv as $fila) {
        $nombre_producto = $fila['nombre_producto'];
        
        // Si el producto ya existe, agregar variante
        if (isset($productos_agrupados[$nombre_producto])) {
            $productos_agrupados[$nombre_producto]['variantes'][] = [
                'talle' => $fila['talle'],
                'color' => $fila['color'],
                'stock' => $fila['stock']
            ];
        } else {
            // Crear nuevo producto
            $productos_agrupados[$nombre_producto] = [
                'nombre_producto' => $fila['nombre_producto'],
                'descripcion_producto' => $fila['descripcion_producto'],
                'precio_actual' => $fila['precio_actual'],
                'id_categoria' => $fila['id_categoria'],
                'genero' => $fila['genero'],
                'variantes' => [
                    [
                        'talle' => $fila['talle'],
                        'color' => $fila['color'],
                        'stock' => $fila['stock']
                    ]
                ]
            ];
        }
    }
    
    return $productos_agrupados;
}

// Obtener productos agrupados para mostrar
$productos_agrupados = agruparProductosCSV($productos_csv);

// Obtener nombres de categorías para mostrar
$sql_categorias = "SELECT id_categoria, nombre_categoria FROM Categorias";
$categorias_result = $mysqli->query($sql_categorias);
$categorias = [];
while ($cat = $categorias_result->fetch_assoc()) {
    $categorias[$cat['id_categoria']] = $cat['nombre_categoria'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmar Carga Masiva | Seda y Lino</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .confirm-page {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .confirm-header {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        
        .product-preview {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .variante-badge {
            background: #e3f2fd;
            color: #1976d2;
            padding: 0.25rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            margin: 0.125rem;
            display: inline-block;
        }
        
        .stats-summary {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <?php include 'includes/navigation.php'; ?>

    <main class="confirm-page">
        <div class="container">
            <!-- Header -->
            <div class="confirm-header">
                <h1><i class="fas fa-check-circle me-3"></i>Confirmar Carga Masiva</h1>
                <p class="mb-0">Revisa los productos antes de confirmar la carga</p>
                <div class="mt-3">
                    <span class="badge bg-primary me-2">Archivo: <?= htmlspecialchars($nombre_archivo) ?></span>
                    <span class="badge bg-info">Usuario: <?= htmlspecialchars($usuario_actual['nombre'] . ' ' . $usuario_actual['apellido']) ?></span>
                </div>
            </div>
            
            <!-- Mensajes -->
            <?php if ($mensaje): ?>
            <div class="alert alert-<?= $mensaje_tipo ?> alert-dismissible fade show" role="alert">
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
            
            <!-- Errores CSV si los hay -->
            <?php if (isset($_SESSION['errores_csv']) && !empty($_SESSION['errores_csv'])): ?>
            <div class="alert alert-warning">
                <h5><i class="fas fa-exclamation-triangle me-2"></i>Errores encontrados en el CSV:</h5>
                <ul class="mb-0">
                    <?php foreach ($_SESSION['errores_csv'] as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <!-- Resumen estadístico -->
            <div class="stats-summary">
                <div class="row text-center">
                    <div class="col-md-3">
                        <h3 class="text-primary"><?= count($productos_agrupados) ?></h3>
                        <p class="text-muted mb-0">Productos Únicos</p>
                    </div>
                    <div class="col-md-3">
                        <h3 class="text-success"><?= count($productos_csv) ?></h3>
                        <p class="text-muted mb-0">Variantes Totales</p>
                    </div>
                    <div class="col-md-3">
                        <h3 class="text-info"><?= array_sum(array_column($productos_csv, 'stock')) ?></h3>
                        <p class="text-muted mb-0">Stock Total</p>
                    </div>
                    <div class="col-md-3">
                        <h3 class="text-warning">$<?= number_format(array_sum(array_column($productos_csv, 'precio_actual')), 2, ',', '.') ?></h3>
                        <p class="text-muted mb-0">Valor Total</p>
                    </div>
                </div>
            </div>
            
            <!-- Vista previa de productos -->
            <div class="row">
                <?php foreach ($productos_agrupados as $producto): ?>
                <div class="col-lg-6 mb-3">
                    <div class="product-preview">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h5 class="mb-0"><?= htmlspecialchars($producto['nombre_producto']) ?></h5>
                            <span class="badge bg-primary"><?= count($producto['variantes']) ?> variantes</span>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <small class="text-muted">Descripción:</small>
                                <p class="mb-1"><?= htmlspecialchars($producto['descripcion_producto']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">Categoría:</small>
                                <p class="mb-1"><?= htmlspecialchars($categorias[$producto['id_categoria']] ?? 'ID: ' . $producto['id_categoria']) ?></p>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <small class="text-muted">Precio:</small>
                                <p class="mb-1 fw-bold text-success">$<?= number_format($producto['precio_actual'], 2, ',', '.') ?></p>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Género:</small>
                                <p class="mb-1"><?= ucfirst($producto['genero']) ?></p>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Stock Total:</small>
                                <p class="mb-1 fw-bold"><?= array_sum(array_column($producto['variantes'], 'stock')) ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-2">
                            <small class="text-muted">Variantes:</small>
                            <div>
                                <?php foreach ($producto['variantes'] as $variante): ?>
                                    <span class="variante-badge">
                                        <?= htmlspecialchars($variante['talle']) ?> - <?= htmlspecialchars($variante['color']) ?> (<?= $variante['stock'] ?>)
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Botones de acción -->
            <div class="text-center mt-4">
                <form method="POST" class="d-inline me-3">
                    <button type="submit" name="confirmar_carga" class="btn btn-success btn-lg">
                        <i class="fas fa-check me-2"></i>Confirmar Carga
                    </button>
                </form>
                
                <form method="POST" class="d-inline me-3">
                    <button type="submit" name="cancelar_carga" class="btn btn-outline-danger btn-lg">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                </form>
                
                <a href="marketing.php" class="btn btn-outline-secondary btn-lg">
                    <i class="fas fa-arrow-left me-2"></i>Volver a Marketing
                </a>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>
