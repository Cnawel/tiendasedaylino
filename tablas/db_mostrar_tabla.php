<?php 

   include("config/database.example.php");

   //obtenemos el nombre de la tabla por el metodo GET
   $tabla = $_GET['tabla'];

   //obtenemos el nombre de las columnas que tiene nuestra tabla
   $columnas = $mysqli->query("SHOW COLUMNS FROM $tabla");
   //traemos los datos que contiene nuestra tabla
   $filas = $mysqli->query("SELECT * FROM $tabla");

   //consultamos el nombre de nuestra clave primaria
   $consulta_pk = $mysqli->query("SHOW KEYS FROM $tabla WHERE Key_name = 'PRIMARY'");
   $pk = $consulta_pk->fetch_assoc()['Column_name'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tabla: <?php echo $tabla ?></title>
</head>
<body>

    <h1>Tabla: <?php echo $tabla ?></h1>

    <a href="db_tablas_sql.php">
        <button>← Volver</button>
    </a>

    <table>
        <thead>
            <tr>
                <!-- aca estan las columnas de la tabla de la base (dentro de etiquetas th) -->
                <?php 
                   //recorremos el array e imprimimos el nombre de cada una de las columnas
                   while($col = $columnas->fetch_assoc()){
                       echo "<th>".$col['Field']."</th>";
                   }
                   //columna extra para los botones Editar/Eliminar
                   echo "<th>Acciones</th>";
                ?>
            </tr>
        </thead>

        <tbody>
            <!-- aca estan las filas con los datos de la base (dentro de etiquetas td) -->
            <?php 
               //recorremos el array e imprimimos los datos que contienen las filas
               while($fila = $filas->fetch_assoc()){
                  echo "<tr>";
                
                  //aca imprimimos cada valor de la fila
                  foreach($fila as $valor){
                    echo "<td>$valor</td>";
                  }

                  //obtenemos el valor de la clave primaria de cada fila
                  $id = $fila[$pk];

                  //creamos los botones de accion Editar/Eliminar
                  echo "<td>
                         <button>
                            <a href='db_editar.php?tabla=$tabla&pk=$pk&id=$id'>Editar</a>
                         </button>
                         <button>
                            <a href='db_eliminar.php?tabla=$tabla&pk=$pk&id=$id'>Eliminar</a>
                         </button>
                  </td>";

                  echo "</tr>";
               }
            ?>
        </tbody>
    </table>
    
</body>
</html>