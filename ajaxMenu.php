<?php
 session_start();
    include_once('conexion.php');
	$conn=conectar();

    if(isset($_GET['opcion'])){
        $opcion = $_GET['opcion'];
    };


    switch ($opcion){

	case 'agregarMenu':

		/*
			- se toman los valores que vienen del botón guardar y se crea un registro nuevo en menu
			- se toma el valor del último registro y se inserta en permisos el valor del nuevo idMenu y el del idUsuario que lo creo


		*/
				$nombre = $_GET['nombre'];
				$pagina = $_GET['pagina'];
				$padre = $_GET['padre'];

                                $idUsuario = $_SESSION['id'];
                        //        $idPerfil = $_SESSION['idPerfil'];

                                echo "nombre:". $nombre . "<br>";
                                echo "pagina:". $pagina . "<br>";
                                echo "padre:". $padre . "<br>";
                                echo "idUsuario:". $idUsuario . "<br>";
                      //              echo "idPerfil:". $idPerfil . "<br>";


                                $stmt = mysqli_prepare($conn, "CALL agregarMenu(?, ?, ?, ?)");

                             //   echo "stmat=". $stmt . "<br>";

                                 /*
                                    Esta línea prepara la consulta para llamar al procedimiento almacenado agregarMenu(). La función mysqli_prepare() toma dos parámetros:
                                    $conn: La conexión a la base de datos.
                                    'CALL agregarMenu(?, ?, ?, ?)': La cadena SQL que contiene el nombre del procedimiento almacenado y los marcadores de posición para los parámetros.
                                 */
                           //     echo "mysqli_stmt_bind_param(".$stmt. ", sssi". ",". $nombre. ",". $pagina .",". $padre. " ,". $idUsuario. ");";

                                mysqli_stmt_bind_param($stmt, "sssi", $nombre, $pagina, $padre, $idUsuario);


                                 /*
                                    Esta línea asigna los valores de los parámetros a los marcadores de posición en la consulta preparada. La función mysqli_stmt_bind_param() toma los
                                    siguientes parámetros:

                                    $stmt: La consulta preparada.
                                    'sssi': Los tipos de datos de los parámetros. En este caso, son dos cadenas (s), un entero (s) y otro entero (i).
                                    $nombre: El valor del primer parámetro.
                                    $pagina: El valor del segundo parámetro.
                                    $padre: El valor del tercer parámetro.
                                    $idUsuario: El valor del cuarto parámetro.
                                 */


                                mysqli_stmt_execute($stmt);

                                 /*
                                    Esta línea ejecuta el procedimiento almacenado. La función mysqli_stmt_execute() toma un parámetro:
                                    $stmt: La consulta preparada.
                                 */

                                mysqli_stmt_close($stmt);
                                 /*
                                    Esta línea cierra la consulta preparada. La función *
                                    $stmt: La consulta preparada.
                                 */
                                mysqli_close($conn);



		//	$result = mysqli_query($conn,$sql);

/*
Stored procedure;

DELIMITER //


CREATE PROCEDURE agregarMenu(
  IN nombre VARCHAR(255),
  IN pagina VARCHAR(255),
  IN padre INT,
  IN idUsuario INT
)
BEGIN
  DECLARE idMenu INT;

  START TRANSACTION;

  # Insertar el nuevo menú
  INSERT INTO menu (nombre, pagina, padre)
  VALUES (nombre, pagina, padre);

  # Obtener el último ID insertado
  SET idMenu = LAST_INSERT_ID();

  # Insertar el permiso para el usuario
  INSERT INTO permisos (idMenu, idUsuario)
  VALUES (idMenu, idUsuario);

  COMMIT;
END;
//

DELIMITER ;

<?php

$conn = mysqli_connect("localhost", "root", "", "mi_base_de_datos");

$nombre = $_POST["nombre"];
$pagina = $_POST["pagina"];
$padre = $_POST["padre"];
$idUsuario = $_SESSION["idUsuario"];

$stmt = mysqli_prepare($conn, "CALL agregarMenu(?, ?, ?, ?)");

mysqli_stmt_bind_param($stmt, "sssi", $nombre, $pagina, $padre, $idUsuario);

mysqli_stmt_execute($stmt);

mysqli_stmt_close($stmt);

mysqli_close($conn);

?>

 */
    break;

    case 'borrarMenu':

            $id= $_GET['id'];

            $sql = "delete from menu
                    where id=$id";
            $result = mysqli_query($conn,$sql);

    break;

    case 'actualizarMenu':

                    $nombre = $_GET['nombre'];
                    $pagina = $_GET['pagina'];
                    $padre = $_GET['padre'];
                    $id = $_GET['id'];

                $sql = "update menu set
                            nombre = '$nombre',
                            pagina = '$pagina',
                            padre = $padre
                        where id = $id";
            echo $sql;

                $result = mysqli_query($conn,$sql);


       break;

    };
?>
