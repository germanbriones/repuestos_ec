<?php
session_start();

// Limpiar todas las variables de sesión posibles de clientes y administradores
unset($_SESSION['usuario_id']);
unset($_SESSION['cliente_id']);
unset($_SESSION['nombre']);
unset($_SESSION['rol']);
unset($_SESSION['es_prime']);

// Destruir la sesión por completo
session_destroy();

// Redirigir al index principal
header("Location: index.php");
exit();
?>

