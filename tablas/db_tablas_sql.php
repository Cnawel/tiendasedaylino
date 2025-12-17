<?php 

   include(__DIR__ . '/../config/database.php');

   //obtenemos los nombres de las tablas que hay en nuestra base de datos
   $tablas = $mysqli->query("SHOW TABLES");

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tablas SQL - Gestión de Base de Datos</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container-fluid py-4">

        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="h3 text-dark mb-3">
                    <i class="bi bi-database"></i> Gestión de Tablas SQL
                </h1>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <strong>Base de datos:</strong> <?php echo htmlspecialchars($dbname); ?>
                    <span class="badge bg-<?php echo $entorno === 'hosting' ? 'warning' : 'success' ?> ms-2">
                        <?php echo htmlspecialchars($entorno); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Lista de tablas -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-table"></i> Tablas Disponibles
                        <span class="badge bg-secondary float-end">
                            <?php echo $tablas->num_rows; ?> tablas
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php
                               //recorremos el array que contiene los nombres de nuestras tablas
                               $tablas->data_seek(0); // Reiniciar puntero
                               while($fila = $tablas->fetch_array()){
                                  //guardamos el nombre de las tablas en una variable
                                  $tabla = $fila[0];
                            ?>
                                <div class="col-md-4 col-lg-3">
                                    <a href="db_mostrar_tabla.php?tabla=<?php echo urlencode($tabla); ?>"
                                       class="text-decoration-none">
                                        <div class="card h-100 border-primary hover-card">
                                            <div class="card-body text-center">
                                                <i class="bi bi-table h1 text-primary mb-2"></i>
                                                <h6 class="card-title"><?php echo htmlspecialchars($tabla); ?></h6>
                                            </div>
                                        </div>
                                    </a>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        .hover-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
    </style>
</body>
</html>