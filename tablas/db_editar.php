<?php  
   include(__DIR__ . '/../config/database.php');

   //obtenemos los parametros enviados por GET
   $tabla = $_GET['tabla'];  //nombre de la tabla
   $pk = $_GET['pk'];  //nombre de la columna que es la clave primaria
   $id = $_GET['id'];  //valor que corresponde a esa clave primaria

   //traemos todos los datos de la tabla para editar que corresponda al registro que se selecciono, usando la clave primaria y el id 
   $consultar_datos = $mysqli->query("SELECT * FROM $tabla WHERE $pk = $id");

   //obtenemos la metadata de las columnas de la tabla
   $obtenerColumnas = $mysqli->query("SHOW COLUMNS FROM $tabla");
   //armamos un array asociativo con las columnas para poder acceder al tipo de dato que tiene
   $columnas = [];
   while($col = $obtenerColumnas->fetch_assoc()){
        $columnas[$col['Field']] = $col;
   }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Registro</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">

                <!-- Header -->
                <div class="card mb-4">
                    <div class="card-header bg-primary text-white">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-pencil-square me-2"></i>
                            <div>
                                <h5 class="mb-0">Editar Registro</h5>
                                <small>Tabla: <strong><?php echo htmlspecialchars($tabla) ?></strong> | Registro ID: <strong><?php echo htmlspecialchars($id) ?></strong></small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            Los campos marcados con <span class="badge bg-secondary">[PK]</span> son clave primaria y no se pueden editar.
                        </div>
                    </div>
                </div>

                <!-- Formulario -->
                <div class="card">
                    <div class="card-body">
                        <form action="db_guardar_editar.php?tabla=<?php echo urlencode($tabla) ?>&pk=<?php echo urlencode($pk) ?>&id=<?php echo urlencode($id) ?>" method="post">
                            <div class="row g-3">
                                <?php
                                //recorremos la fila que se obtuvo en el SELECT de la variable $consultar_datos
                                 while($fil = $consultar_datos->fetch_assoc()){
                                    //Recorremos la columna y el valor que tiene ese registro.
                                    foreach($fil as $columna => $valor){

                                         //detectamos el tipo de dato que tiene esa columna para insertarlo en el input
                                          $type = $columnas[$columna]['Type'];

                                          if(str_contains($type, "int") || str_contains($type, "decimal") || str_contains($type,"tinyint")){
                                             $inputType = "number";
                                          }else if(str_contains($type, "datetime") || str_contains($type, "timestamp")){
                                             $inputType = "datetime-local";
                                          }else if(str_contains($type, "date")){
                                             $inputType = "date";
                                          }else if(str_contains($type, "time")){
                                             $inputType = "time";
                                          }else{
                                             $inputType = "text";
                                          }

                                         //si la columna es igual a la clave primaria no se edita
                                          if($columna == $pk){
                                               echo "<div class=\"col-md-6\">";
                                               echo "<label for='$columna' class=\"form-label\">";
                                               echo htmlspecialchars($columna) . " <span class=\"badge bg-secondary\">[PK]</span>";
                                               echo "</label>";
                                               echo "<input type=\"text\" class=\"form-control\" id=\"$columna\" value=\"" . htmlspecialchars($valor) . "\" disabled readonly>";
                                               echo "</div>";
                                          } else{
                                               echo "<div class=\"col-md-6\">";
                                               echo "<label for=\"$columna\" class=\"form-label\">" . htmlspecialchars($columna) . "</label>";
                                               echo "<input type=\"$inputType\" class=\"form-control\" name=\"$columna\" id=\"$columna\" value=\"" . htmlspecialchars($valor) . "\">";
                                               echo "</div>";
                                          }
                                    }
                                 }
                              ?>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <a href="db_mostrar_tabla.php?tabla=<?php echo urlencode($tabla) ?>" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-left"></i> Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-lg"></i> Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>