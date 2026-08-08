<?php
include 'conexion.php';

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    try {
        // Verificar si el correo ya existe en clientes
        $stmt_check = $conexion->prepare("SELECT id FROM clientes WHERE email = :email");
        $stmt_check->execute(['email' => $email]);
        
        if ($stmt_check->rowCount() > 0) {
            $error = "Este correo electrónico ya está registrado.";
        } else {
            // Registrar nuevo cliente
            $stmt = $conexion->prepare("INSERT INTO clientes (nombre, email, password, es_prime) VALUES (:nombre, :email, :password, FALSE)");
            $stmt->execute([
                'nombre' => $nombre,
                'email' => $email,
                'password' => $password
            ]);
            $mensaje = "¡Cuenta creada con éxito! Ya puedes iniciar sesión.";
        }
    } catch (PDOException $e) {
        $error = "Error al registrar: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Crear Cuenta - AutoRepuestos EC</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, sans-serif; }
        body { background-color: #eaeded; display: flex; justify-content: center; align-items: center; height: 100vh; }
        .auth-container { background: white; padding: 30px; border-radius: 8px; border: 1px solid #ddd; width: 100%; max-width: 350px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        h2 { margin-bottom: 20px; color: #111; font-size: 24px; font-weight: normal; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-size: 13px; font-weight: bold; color: #111; }
        input { width: 100%; padding: 8px 10px; border: 1px solid #a6a6a6; border-radius: 3px; font-size: 14px; }
        input:focus { border-color: #e77600; box-shadow: 0 0 3px 2px rgb(228 121 17 / 50%); outline: none; }
        .btn-continue { background: linear-gradient(to bottom,#f7dfa5,#f0c14b); border: 1px solid; border-color: #a88734 #9c7e31 #846a29; width: 100%; padding: 8px; border-radius: 3px; font-size: 13px; cursor: pointer; font-weight: bold; margin-top: 10px; }
        .btn-continue:hover { background: linear-gradient(to bottom,#f5d78e,#eeb933); }
        .error { color: #c45500; font-size: 12px; margin-bottom: 10px; }
        .exito { color: #008000; font-size: 12px; margin-bottom: 10px; font-weight: bold; }
        .login-link { text-align: center; margin-top: 20px; font-size: 12px; border-top: 1px solid #e7e7e7; padding-top: 15px; }
        .login-link a { color: #0066c0; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; color: #c45500; }
    </style>
</head>
<body>

    <div class="auth-container">
        <h2>Crear cuenta</h2>

        <?php if (!empty($error)): ?><div class="error"><?= $error ?></div><?php endif; ?>
        <?php if (!empty($mensaje)): ?><div class="exito"><?= $mensaje ?></div><?php endif; ?>

        <form action="registro_cliente.php" method="POST">
            <div class="form-group">
                <label>Tu nombre</label>
                <input type="text" name="nombre" required placeholder="Nombre y apellido">
            </div>
            <div class="form-group">
                <label>Correo electrónico</label>
                <input type="email" name="email" required placeholder="correo@ejemplo.com">
            </div>
            <div class="form-group">
                <label>Contraseña</label>
                <input type="password" name="password" required placeholder="Al menos 6 caracteres">
            </div>
            <button type="submit" class="btn-continue">Continuar</button>
        </form>

        <div class="login-link">
            ¿Ya tienes una cuenta? <a href="index.php">Inicia sesión en la tienda</a>
        </div>
    </div>

</body>
</html>