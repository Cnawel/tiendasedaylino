<?php

   include(__DIR__ . '/../config/database.php');

   //obtenemos el nombre de la tabla por el metodo GET
   $tabla = $_GET['tabla'];

   //obtenemos el nombre de las columnas que tiene nuestra tabla
   $columnas_result = $mysqli->query("SHOW COLUMNS FROM $tabla");

   //consultamos el nombre de nuestra clave primaria
   $consulta_pk = $mysqli->query("SHOW KEYS FROM $tabla WHERE Key_name = 'PRIMARY'");
   $pk_result = $consulta_pk->fetch_assoc();
   $pk = $pk_result ? $pk_result['Column_name'] : 'id';

   // Parámetros de ordenamiento
   $sort_column = isset($_GET['sort']) ? $_GET['sort'] : $pk; // Por defecto ordena por clave primaria
   $sort_direction = isset($_GET['dir']) && $_GET['dir'] === 'asc' ? 'asc' : 'desc'; // Por defecto descendente (más nuevos primero)

   // Validar que la columna de ordenamiento existe
   $columnas_validas = [];
   $columnas_result->data_seek(0);
   while($col = $columnas_result->fetch_assoc()){
       $columnas_validas[] = $col['Field'];
   }
   if (!in_array($sort_column, $columnas_validas)) {
       $sort_column = $pk; // Fallback a clave primaria si la columna no es válida
   }

   //traemos los datos que contiene nuestra tabla con ordenamiento
   $query = "SELECT * FROM $tabla ORDER BY `$sort_column` $sort_direction";
   $filas_result = $mysqli->query($query);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tabla: <?php echo $tabla ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <style>
        .sortable { cursor: pointer; user-select: none; }
        .sortable:hover { background-color: rgba(0,0,0,.075) !important; }
        .sort-indicator {
            margin-left: 5px;
            opacity: 0.5;
        }
        .sort-asc .sort-indicator { opacity: 1; }
        .sort-desc .sort-indicator { opacity: 1; }
        .actions-column { width: 160px; }
        .table-responsive { margin-top: 20px; }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid py-4">

        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-1 text-dark">📊 Tabla: <?php echo htmlspecialchars($tabla) ?></h1>
                        <small class="text-muted">Haz click en cualquier columna para cambiar el orden</small>
                    </div>
                    <a href="db_tablas_sql.php" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-left"></i> Volver
                    </a>
                </div>
            </div>
        </div>

        <!-- Alertas -->
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong>Advertencia:</strong> <?php echo htmlspecialchars(urldecode($_GET['error'])); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Tabla -->
        <div class="table-responsive">
            <table class="table table-striped table-hover table-bordered">
                <thead class="table-dark">
                    <tr>
                        <!-- aca estan las columnas de la tabla de la base (dentro de etiquetas th) -->
                        <?php
                           //recorremos el array e imprimimos el nombre de cada una de las columnas
                           $columnas_result->data_seek(0); // Reiniciar puntero
                           while($col = $columnas_result->fetch_assoc()){
                               $col_name = $col['Field'];
                               $is_current_sort = ($col_name === $sort_column);
                               $sort_class = $is_current_sort ? 'sort-' . $sort_direction : 'sortable';
                               $url_params = http_build_query(array_merge($_GET, [
                                   'sort' => $col_name,
                                   'dir' => $is_current_sort && $sort_direction === 'desc' ? 'asc' : 'desc'
                               ]));

                               $icon_class = '';
                               if ($is_current_sort) {
                                   $icon_class = $sort_direction === 'desc' ? 'bi-chevron-down' : 'bi-chevron-up';
                               } else {
                                   $icon_class = 'bi-chevron-expand';
                               }

                               echo "<th class=\"$sort_class fw-bold\" onclick=\"window.location.href='?$url_params'\">";
                               echo htmlspecialchars($col_name);
                               echo "<i class=\"bi $icon_class sort-indicator ms-1\"></i>";
                               echo "</th>";
                           }
                           //columna extra para los botones Editar/Eliminar
                           echo "<th class=\"actions-column text-center\">Acciones</th>";
                        ?>
                    </tr>
                </thead>

                <tbody>
                    <!-- aca estan las filas con los datos de la base (dentro de etiquetas td) -->
                    <?php
                       //recorremos el array e imprimimos los datos que contienen las filas
                       while($fila = $filas_result->fetch_assoc()){
                          echo "<tr>";

                          //aca imprimimos cada valor de la fila
                          foreach($fila as $valor){
                             $display_value = $valor === null ? '<em class="text-muted">NULL</em>' : htmlspecialchars($valor);
                             echo "<td>$display_value</td>";
                          }

                          //obtenemos el valor de la clave primaria de cada fila
                          $id = $fila[$pk];

                          //creamos los botones de accion Editar/Eliminar
                          echo "<td class=\"actions-column text-center\">
                                 <div class=\"btn-group btn-group-sm\" role=\"group\">
                                     <a href='db_editar.php?tabla=$tabla&pk=$pk&id=$id' class=\"btn btn-outline-primary\">
                                         <i class=\"bi bi-pencil\"></i> Editar
                                     </a>
                                     <a href='db_eliminar.php?tabla=$tabla&pk=$pk&id=$id' class=\"btn btn-outline-danger\"
                                        onclick=\"return confirm('¿Estás seguro de que quieres eliminar este registro?')\">
                                         <i class=\"bi bi-trash\"></i> Eliminar
                                     </a>
                                 </div>
                          </td>";

                          echo "</tr>";
                       }
                    ?>
                        </tbody>
            </table>
        </div>

    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>