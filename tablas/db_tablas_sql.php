<?php 

   include("config/database.example.php");

   //obtenemos los nombres de las tablas que hay en nuestra base de datos
   $tablas = $mysqli->query("SHOW TABLES");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tablas SQL</title>
</head>
<body>

    <h1>Tablas de la base: tiendasedaylino_db</h1>

    <?php 
       //recorremos el array que contiene los nombres de nuestras tablas
       while($fila = $tablas->fetch_array()){
          //guardamos el nombre de las tablas en una variable
          $tabla = $fila[0];
          //generamos un boton para cada una de nuestras tablas
          echo "<a href='db_mostrar_tabla.php?tabla=$tabla'>
               <button>$tabla</button>
          </a>";
       }
    ?>
    
</body>
</html>