 <?php session_start()?>

<div class="container p-5 my-5 justify-content-center rounded" style="width:500px;background-color:rgb(229, 229, 229)" >
<h3 class="text-center">Valores de la sesión actual</h3>
	<?php
		// esto me dice se se inició una session
		
		
		
		if (isset($_SESSION)){
			$apellidoSesion = $_SESSION['apellido'];
			$nombreSesion = $_SESSION['nombre'];
			$idSesion = $_SESSION['id'];
			$fotoInicio = $_SESSION['fotoInicio'];
			$id_sesion = session_id();
			$inicio_actividad = $_SESSION['inicio_actividad'];
			
			
			echo "apellidoSesion: ". $apellidoSesion. "<br>";
			echo "nombre Sesion: ". $nombreSesion. "<br>";
			echo "idSesion: ". $idSesion. "<br>";
			echo "foto Inicio: ". $fotoInicio. "<br>";
			echo "Mi matriz de sessión tiene lo siguiente: <br>";
			echo "El id de sesión es: ". $id_sesion. "<br>";
			echo "El inicio de sesión es: ". $inicio_actividad. "<br>";
		
			echo "El array completo es: ";
			
			// También puedo ver el array completo:
			
			print_r($_SESSION);
			
			echo "<br><br>";
			
			// Obtiene los parámetros de la cookie de sesión
			$parametros_cookie = session_get_cookie_params();

			// Obtiene el tiempo de expiración de la sesión
			  $tiempo_expiracion = $parametros_cookie['expires'];

			// Muestra el tiempo de expiración de la sesión. 
			echo "El tiempo de expiración de la sesión es:". $tiempo_expiracion;
			
			echo "<br> Explicación de google.bard a porqué no tengo tiempo de expiración:
			Como el valor de session.cookie_lifetime en php.ini está en 0.
			La sesión termina cuando el navegador se cierre.
			Si quiere tener un tiempo de sesión fijo configure ession.cookie_lifetime en xx segundos
			Luego de configurarlo hay que reiniciar el servidor web apache";
			
			
		}else{
			echo "Parece que no hay sesión iniciada";
		};
	?>



</div>
 


