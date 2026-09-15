<?php

  // Inicia la sesión
  session_start();

  // Destruye la sesión
  session_destroy();

  // Elimina todas las variables de sesión
  $_SESSION = array();

  // Redirecciona a la página de inicio
  header("Location: index.php");
?>


