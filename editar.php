<?php  
   include("config/database.example.php");

   //obtenemos los parametros enviados por GET
   $tabla = $_GET['tabla'];  //nombre de la tabla
   $pk = $_GET['pk'];  //nombre de la columna que es la clave primaria
   $id = $_GET['id'];  //valor que corresponde a esa clave primaria

   //traemos todos los datos de la tabla para editar que corresponda al registro que se selecciono, usando la clave primaria y el id 
   $consultar_datos = $conn->query("SELECT * FROM $tabla WHERE $pk = $id");

   //obtenemos la metadata de las columnas de la tabla
   $obtenerColumnas = $conn->query("SHOW COLUMNS FROM $tabla");
   //armamos un array asociativo con las columnas para poder acceder al tipo de dato que tiene
   $columnas = [];
   while($col = $obtenerColumnas->fetch_assoc()){
        $columnas[$col['Field']] = $col;
   }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar</title>
</head>
<body>
    
   <h1>Editar <?php echo "Tabla:".$tabla.".Registro n°:".$id ?></h1>

   <!-- Eviamos el nombre de la tabla, el nombre de la columna que es clave primaria y el valor de la clave por GET -->
   <form action="guardar_editar.php?tabla=<?php echo $tabla ?>&pk=<?php echo $pk ?>&id=<?php echo $id ?>" method="post">
      <?php 
        //recorremos la fila que se obtuvo en el SELECT de la variable $consultar_datos
         while($fila = $consultar_datos->fetch_assoc()){
            //Recorremos la columna y el valor que tiene ese registro. 
            foreach($fila as $columna => $valor){

                 //detectamos el tipo de dato que tiene esa columna para insertarlo en el input 
                  $type = $columnas[$columna]['Type'];
            
                  if(str_contains($type, "int") || str_contains($type, "decimal") || str_contains($type,"tinyint")){
                     $inputType = "number";
                  }else if(str_contains($type, "datetime")){
                     $inputType = "datetime";
                  }else if(str_contains($type, "date")){
                     $inputType = "date";
                  }else if(str_contains($type, "time")){
                     $inputType = "time";
                  }else{
                     $inputType = "text";
                  }

                 //si la columna es igual a la clave primaria no se edita
                  if($columna == $pk){
                       echo "<label for='$columna'>$columna</label><br>";
                       echo "<input type='number' name='$columna' id='$columna' value='$valor' disabled><br><br>";
                  } else{
                       echo "<label for='$columna'>$columna</label><br>";
                       echo "<input type='$inputType' name='$columna' id='$columna' value='$valor'><br><br>";
                  }
            }
         }
      ?>
      <button>Editar</button>
   </form>
</body>
</html>