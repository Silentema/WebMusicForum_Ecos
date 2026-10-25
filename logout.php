<?php
//Reanudar la sesión existente
session_start();

//Destruir toda la información guardada
session_destroy();

//Redirigir al usuario de vuelta a la pantalla de inicio de sesión
header("Location: 2inicioSesion.html");
exit();
?>