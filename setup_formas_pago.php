<?php
/**
 * ========================================================================
 * SETUP FORMAS DE PAGO - Tienda Seda y Lino
 * ========================================================================
 * Script de instalación para insertar formas de pago iniciales
 * Ejecutar una sola vez accediendo a: http://localhost/tiendasedaylino-master/setup_formas_pago.php
 * 
 * @author Tienda Seda y Lino
 * @version 1.0
 */

require_once __DIR__ . '/config/database.php';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Formas de Pago - Seda y Lino</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-credit-card me-2"></i>
                            Setup Formas de Pago
                        </h4>
                    </div>
                    <div class="card-body">
                        
                        <?php
                        try {
                            // Verificar si ya existen formas de pago
                            $stmt_check = $pdo->query("SELECT COUNT(*) as total FROM Forma_Pagos");
                            $check = $stmt_check->fetch(PDO::FETCH_ASSOC);
                            
                            if ($check['total'] > 0) {
                                echo '<div class="alert alert-info">';
                                echo '<i class="fas fa-info-circle me-2"></i>';
                                echo '<strong>Ya existen formas de pago en la base de datos.</strong><br>';
                                echo 'Total: ' . $check['total'] . ' forma(s) de pago registrada(s).';
                                echo '</div>';
                                
                                // Mostrar formas de pago existentes
                                $stmt_list = $pdo->query("SELECT * FROM Forma_Pagos ORDER BY id_forma_pago");
                                $formas_existentes = $stmt_list->fetchAll(PDO::FETCH_ASSOC);
                                
                                echo '<h5 class="mt-4">Formas de pago existentes:</h5>';
                                echo '<ul class="list-group mb-4">';
                                foreach ($formas_existentes as $forma) {
                                    echo '<li class="list-group-item">';
                                    echo '<strong>ID ' . $forma['id_forma_pago'] . ':</strong> ' . htmlspecialchars($forma['nombre']);
                                    if ($forma['descripcion']) {
                                        echo '<br><small class="text-muted">' . htmlspecialchars($forma['descripcion']) . '</small>';
                                    }
                                    echo '</li>';
                                }
                                echo '</ul>';
                            }
                            
                            // Insertar o actualizar formas de pago
                            $formas_pago = [
                                [1, 'Transferencia Bancaria', 'Transferencia o depósito bancario. Te enviaremos los datos de la cuenta por email.'],
                                [2, 'Mercado Pago', 'Pago en línea con tarjeta de crédito, débito o saldo en Mercado Pago.'],
                                [3, 'Efectivo contra entrega', 'Paga en efectivo al recibir tu pedido. Solo disponible en CABA y GBA.'],
                                [4, 'Tarjeta de Crédito', 'Pago directo con tarjeta de crédito Visa, MasterCard o American Express.']
                            ];
                            
                            $sql_insert = "
                                INSERT INTO Forma_Pagos (id_forma_pago, nombre, descripcion) 
                                VALUES (:id, :nombre, :descripcion)
                                ON DUPLICATE KEY UPDATE 
                                    nombre = VALUES(nombre),
                                    descripcion = VALUES(descripcion)
                            ";
                            
                            $stmt_insert = $pdo->prepare($sql_insert);
                            
                            $insertadas = 0;
                            $actualizadas = 0;
                            
                            foreach ($formas_pago as $forma) {
                                $stmt_insert->execute([
                                    ':id' => $forma[0],
                                    ':nombre' => $forma[1],
                                    ':descripcion' => $forma[2]
                                ]);
                                
                                if ($stmt_insert->rowCount() > 0) {
                                    $insertadas++;
                                }
                            }
                            
                            echo '<div class="alert alert-success">';
                            echo '<i class="fas fa-check-circle me-2"></i>';
                            echo '<strong>¡Setup completado exitosamente!</strong><br>';
                            echo 'Se han configurado ' . count($formas_pago) . ' formas de pago en el sistema.';
                            echo '</div>';
                            
                            // Mostrar todas las formas de pago después de la inserción
                            $stmt_final = $pdo->query("SELECT * FROM Forma_Pagos ORDER BY id_forma_pago");
                            $formas_finales = $stmt_final->fetchAll(PDO::FETCH_ASSOC);
                            
                            echo '<h5 class="mt-4">Formas de pago configuradas:</h5>';
                            echo '<ul class="list-group mb-4">';
                            foreach ($formas_finales as $forma) {
                                echo '<li class="list-group-item">';
                                echo '<strong>ID ' . $forma['id_forma_pago'] . ':</strong> ' . htmlspecialchars($forma['nombre']);
                                if ($forma['descripcion']) {
                                    echo '<br><small class="text-muted">' . htmlspecialchars($forma['descripcion']) . '</small>';
                                }
                                echo '</li>';
                            }
                            echo '</ul>';
                            
                        } catch (PDOException $e) {
                            echo '<div class="alert alert-danger">';
                            echo '<i class="fas fa-exclamation-triangle me-2"></i>';
                            echo '<strong>Error:</strong> ' . htmlspecialchars($e->getMessage());
                            echo '</div>';
                        }
                        ?>
                        
                        <div class="mt-4">
                            <a href="index.php" class="btn btn-primary">
                                <i class="fas fa-home me-2"></i>
                                Volver al Inicio
                            </a>
                            <a href="admin.php" class="btn btn-outline-secondary">
                                <i class="fas fa-cog me-2"></i>
                                Panel Admin
                            </a>
                        </div>
                        
                        <div class="alert alert-warning mt-4 mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Nota:</strong> Por seguridad, considera eliminar este archivo después de ejecutarlo.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

