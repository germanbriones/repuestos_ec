<?php
session_start();
include 'conexion.php';

$error_login = '';

// Procesar el login unificado desde el index
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_general'])) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // 1. Buscar en Administradores
    $stmt_admin = $conexion->prepare("SELECT * FROM usuarios WHERE email = :email");
    $stmt_admin->execute(['email' => $email]);
    $admin = $stmt_admin->fetch(PDO::FETCH_ASSOC);

    if ($admin && $password === $admin['password']) {
        $_SESSION['usuario_id'] = $admin['id'];
        $_SESSION['nombre'] = $admin['nombre'];
        $_SESSION['rol'] = $admin['rol'];
        header("Location: index.php");
        exit();
    }

    // 2. Buscar en Clientes
    $stmt_cliente = $conexion->prepare("SELECT * FROM clientes WHERE email = :email");
    $stmt_cliente->execute(['email' => $email]);
    $cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);

    if ($cliente && $password === $cliente['password']) {
        $_SESSION['cliente_id'] = $cliente['id'];
        $_SESSION['nombre'] = $cliente['nombre'];
        $_SESSION['es_prime'] = $cliente['es_prime']; // Guardamos si es prime (t/f o 1/0)
        header("Location: index.php");
        exit();
    }

    $error_login = "Correo o contraseña incorrectos.";
}

// Capturar filtros de categoría y búsqueda de forma segura
$categoria_filtro = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';
$busqueda_filtro = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

// Consulta SQL dinámica para filtrar por categoría y/o barra de búsqueda
$sql = "SELECT * FROM repuestos WHERE 1=1";
$params = [];

if ($categoria_filtro !== '') {
    $sql .= " AND categoria = :categoria";
    $params['categoria'] = $categoria_filtro;
}

if ($busqueda_filtro !== '') {
    $sql .= " AND (nombre ILIKE :busqueda OR descripcion ILIKE :busqueda)";
    $params['busqueda'] = "%" . $busqueda_filtro . "%";
}

$sql .= " ORDER BY id ASC";

$stmt = $conexion->prepare($sql);
$stmt->execute($params);
$repuestos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verificar si el usuario actual es cliente Prime (según su sesión)
$es_cliente_prime = isset($_SESSION['es_prime']) && ($_SESSION['es_prime'] == 1 || $_SESSION['es_prime'] === true || $_SESSION['es_prime'] === 't');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>AutoRepuestos EC - Tienda Online</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, sans-serif; }
        body { background-color: #eaeded; }
        
        /* Estilo Cabecera Amazon */
        header { background-color: #131921; color: white; padding: 10px 15px; display: flex; align-items: center; justify-content: space-between; gap: 15px; }
        .logo { font-size: 22px; font-weight: bold; color: #ff9900; text-decoration: none; display: flex; align-items: center; gap: 5px; }
        .logo span { color: white; font-size: 14px; }

        /* Buscador */
        .search-box { display: flex; flex-grow: 1; max-width: 600px; background: white; border-radius: 4px; overflow: hidden; }
        .search-box select { background: #f3f3f3; border: none; padding: 10px; font-size: 12px; color: #555; border-right: 1px solid #cdcdcd; outline: none; cursor: pointer; }
        .search-box input { flex-grow: 1; border: none; padding: 10px; font-size: 14px; outline: none; }
        .search-box button { background: #febd69; border: none; padding: 0 15px; cursor: pointer; font-size: 16px; }
        .search-box button:hover { background: #f3a847; }

        /* Sección Derecha */
        .nav-right { display: flex; align-items: center; gap: 20px; font-size: 12px; }
        .dropdown { position: relative; display: inline-block; cursor: pointer; padding: 5px 0; }
        .dropdown-content { display: none; position: absolute; right: 0; background: white; min-width: 260px; box-shadow: 0px 8px 16px rgba(0,0,0,0.2); z-index: 10; color: #111; border-radius: 4px; border: 1px solid #ddd; padding: 15px; text-align: center; }
        .dropdown:hover .dropdown-content { display: block; }
        
        .btn-identificar { background-color: #ffd814; border: 1px solid #fcd200; padding: 8px 0; border-radius: 8px; font-weight: bold; text-decoration: none; color: black; display: block; width: 100%; text-align: center; margin-bottom: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .btn-identificar:hover { background-color: #f7ca00; }
        .nuevo-cliente { font-size: 11px; color: #565959; }
        .nuevo-cliente a { color: #0066c0; text-decoration: none; }
        .nuevo-cliente a:hover { text-decoration: underline; color: #c45500; }

        .nav-line-1 { color: #ccc; font-size: 11px; }
        .nav-line-2 { font-weight: bold; font-size: 13px; color: white; }

        /* Órdenes y Carrito */
        .orders-section { color: white; text-decoration: none; display: flex; flex-direction: column; justify-content: center; padding: 5px 0; }
        .orders-section:hover { color: #ccc; }

        .cart-section { display: flex; align-items: center; color: white; text-decoration: none; font-weight: bold; padding: 5px 0; }
        .cart-section span { color: #ff9900; font-size: 16px; margin-right: 3px; }

        /* Banner de autenticación */
        .amazon-banner-auth { background: white; border: 1px solid #ddd; padding: 20px; margin: 20px auto; border-radius: 4px; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.1); max-width: 1200px; }
        .amazon-banner-auth h3 { color: #111; margin-bottom: 8px; font-size: 18px; }
        .amazon-banner-auth p { color: #565959; font-size: 13px; margin-bottom: 15px; }

        /* Banner Prime Exclusivo */
        .prime-promo-banner { background: linear-gradient(135deg, #232f3e, #131921); color: white; padding: 12px 20px; margin-bottom: 20px; border-radius: 6px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 8px rgba(0,0,0,0.15); border-left: 5px solid #ff9900; }
        .prime-promo-banner span { font-size: 14px; }
        .prime-tag { background-color: #ff9900; color: #111; font-weight: bold; padding: 2px 6px; border-radius: 3px; font-size: 12px; margin-right: 5px; }

        /* Catálogo y Tarjetas */
        .container { max-width: 1200px; margin: 20px auto; padding: 0 15px; }
        h1 { margin-bottom: 20px; color: #131921; font-size: 22px; }
        
        .grid-productos { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 20px; }
        .card-producto { background: white; padding: 15px; border-radius: 4px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.2s; min-height: 400px; }
        .card-producto:hover { box-shadow: 0 4px 10px rgba(0,0,0,0.15); }
        
        .img-container { width: 100%; height: 160px; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; background: #fff; border-radius: 4px; overflow: hidden; }
        .img-container img { width: 100%; height: 100%; object-fit: cover; }

        .card-producto h3 { font-size: 15px; margin-bottom: 6px; color: #007185; }
        .card-producto p { font-size: 12px; color: #555; margin-bottom: 10px; line-height: 1.3; }
        
        /* Precios y Descuentos */
        .precio-original { font-size: 14px; color: #565959; text-decoration: line-through; }
        .precio-final { font-size: 18px; font-weight: bold; color: #B12704; }
        .precio-normal { font-size: 18px; font-weight: bold; color: #B12704; margin-bottom: 10px; }
        
        .btn-comprar { background-color: #ffd814; border: 1px solid #fcd200; padding: 8px; border-radius: 8px; text-align: center; font-weight: bold; cursor: pointer; text-decoration: none; color: black; display: block; margin-top: 10px; }
        .btn-comprar:hover { background-color: #f7ca00; }
        
        .admin-banner { background: #fff3cd; border: 1px solid #ffeeba; padding: 10px 20px; margin-bottom: 15px; border-radius: 4px; display: flex; justify-content: space-between; align-items: center; }
        .admin-banner a { background: #856404; color: white; padding: 5px 10px; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 13px; }
        
        .quick-login-form input { width: 100%; padding: 7px; margin-bottom: 8px; border: 1px solid #a6a6a6; border-radius: 3px; font-size: 12px; }
        .quick-login-btn { background: #ffd814; border: 1px solid #fcd200; width: 100%; padding: 6px; border-radius: 3px; font-weight: bold; cursor: pointer; }
        .error-msj { color: #c45500; font-size: 11px; margin-bottom: 8px; text-align: left; }
    </style>
</head>
<body>

    <header>
        <a href="index.php" class="logo">🚗 AutoRepuestos<span>.ec</span></a>

        <form action="index.php" method="GET" class="search-box">
            <select name="categoria" onchange="this.form.submit()">
                <option value="">Todo</option>
                <option value="Motor" <?= ($categoria_filtro === 'Motor') ? 'selected' : '' ?>>Motor</option>
                <option value="Frenos" <?= ($categoria_filtro === 'Frenos') ? 'selected' : '' ?>>Frenos</option>
                <option value="Filtros" <?= ($categoria_filtro === 'Filtros') ? 'selected' : '' ?>>Filtros</option>
                <option value="Suspensión" <?= ($categoria_filtro === 'Suspensión') ? 'selected' : '' ?>>Suspensión</option>
            </select>
            <input type="text" name="busqueda" placeholder="Buscar repuestos, marcas o modelos..." value="<?= htmlspecialchars($busqueda_filtro) ?>">
            <button type="submit">🔍</button>
        </form>

        <div class="nav-right">
            <div class="dropdown">
                <?php if (isset($_SESSION['usuario_id']) || isset($_SESSION['cliente_id'])): ?>
                    <div class="nav-line-1">Hola, <?= htmlspecialchars($_SESSION['nombre'] ?? 'Usuario') ?></div>
                    <div class="nav-line-2">Tu Cuenta ▼</div>
                    
                    <div class="dropdown-content" style="text-align: left;">
                        <p style="font-weight: bold; margin-bottom: 8px; color: #111;">¡Bienvenido de nuevo!</p>
                        <?php if ($es_cliente_prime): ?>
                            <p style="color: #ff9900; font-weight: bold; font-size: 11px; margin-bottom: 8px;">👑 Miembro Prime Activo</p>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                            <a href="admin.php" style="color: #0066c0; display: block; margin-bottom: 8px; text-decoration: none;">🛠️ Panel de Administración</a>
                        <?php endif; ?>
                        <a href="logout.php" style="color: #c45500; text-decoration: none; font-weight: bold;">Cerrar sesión</a>
                    </div>
                <?php else: ?>
                    <div class="nav-line-1">Hola, Identifícate</div>
                    <div class="nav-line-2">Cuenta y Listas ▼</div>

                    <div class="dropdown-content">
                        <a href="#" onclick="document.getElementById('form-login-fly').style.display='block'; return false;" class="btn-identificar">Identifícate</a>
                        <div class="nuevo-cliente">
                            ¿Eres un cliente nuevo? <a href="registro_cliente.php">Empieza aquí.</a>
                        </div>
                        
                        <div id="form-login-fly" style="margin-top: 12px; border-top: 1px solid #eee; padding-top: 10px; display: none;">
                            <form action="index.php" method="POST" class="quick-login-form">
                                <?php if (!empty($error_login)): ?><div class="error-msj"><?= $error_login ?></div><?php endif; ?>
                                <input type="email" name="email" required placeholder="Correo electrónico">
                                <input type="password" name="password" required placeholder="Contraseña">
                                <button type="submit" name="login_general" class="quick-login-btn">Entrar</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (isset($_SESSION['usuario_id']) || isset($_SESSION['cliente_id'])): ?>
               <a href="pedidos.php" class="orders-section">
    <div class="nav-line-1">Devoluciones</div>
    <div class="nav-line-2">y pedidos</div>
</a>
            <?php endif; ?>

            <a href="carrito.php" class="cart-section">
                <span>🛒</span> Carrito
            </a>
        </div>
    </header>

    <div class="container">
        
        <?php if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['cliente_id'])): ?>
            <div class="amazon-banner-auth">
                <h3>Encuentra repuestos automotrices garantizados para tu vehículo en Ecuador</h3>
                <p>Inicia sesión o regístrate para acceder a compras seguras y beneficios exclusivos.</p>
                <a href="registro_cliente.php" class="btn-comprar" style="display: inline-block; padding: 8px 25px; width: auto;">Regístrate gratis</a>
            </div>
        <?php endif; ?>

        <?php if ($es_cliente_prime): ?>
            <div class="prime-promo-banner">
                <span><span class="prime-tag">PRIME</span> ¡Hola, <?= htmlspecialchars($_SESSION['nombre']) ?>! Tienes un **10% de descuento automático** aplicado en todo nuestro catálogo por ser cliente Prime.</span>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['usuario_id']) && $_SESSION['rol'] === 'admin'): ?>
            <div class="admin-banner">
                <span>🛠️ Estás conectado como Administrador con acceso al control de inventario y demanda.</span>
                <a href="admin.php">Ir al Panel Admin &rarr;</a>
            </div>
        <?php endif; ?>

        <h1>Catálogo de Repuestos Automotrices</h1>

        <div class="grid-productos">
            <?php if (empty($repuestos)): ?>
                <p style="grid-column: 1 / -1; text-align: center; color: #555; padding: 30px;">No se encontraron repuestos en esta categoría.</p>
            <?php else: ?>
                <?php foreach ($repuestos as $item): ?>
                    <div class="card-producto">
                        <div>
                            <div class="img-container">
                                <?php if (!empty($item['imagen'])): ?>
                                    <img src="<?= htmlspecialchars($item['imagen']) ?>" alt="<?= htmlspecialchars($item['nombre']) ?>">
                                <?php else: ?>
                                    <span style="font-size: 30px;">⚙️</span>
                                <?php endif; ?>
                            </div>

                            <small style="color: #007185; font-weight: bold;"><?= htmlspecialchars($item['categoria']) ?></small>
                            <h3><?= htmlspecialchars($item['nombre']) ?></h3>
                            <p><?= htmlspecialchars($item['descripcion']) ?></p>
                        </div>
                        <div>
                            <?php if ($es_cliente_prime): ?>
                                <!-- Si es Prime, calculamos el 10% de descuento y mostramos ambos precios -->
                                <?php 
                                    $precio_original = $item['precio'];
                                    $precio_con_descuento = $precio_original * 0.90; // 10% de descuento
                                ?>
                                <div style="margin-bottom: 10px;">
                                    <span class="precio-original">$<?= number_format($precio_original, 2) ?></span><br>
                                    <span class="precio-final">$<?= number_format($precio_con_descuento, 2) ?></span> 
                                    <span style="color: #007600; font-weight: bold; font-size: 12px;"><span class="prime-tag" style="padding: 1px 3px;">Prime</span> -10%</span>
                                </div>
                            <?php else: ?>
                                <!-- Precio estándar si no ha iniciado sesión o no es prime -->
                                <div class="precio-normal">$<?= number_format($item['precio'], 2) ?></div>
                            <?php endif; ?>
                            
                            <!-- EL BOTÓN SOLO APARECE SI HAY SESIÓN ACTIVA DE CLIENTE O ADMIN -->
                            <?php if (isset($_SESSION['cliente_id']) || (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin')): ?>
    <form action="carrito.php" method="POST" style="margin-top: 10px;">
        <input type="hidden" name="id_repuesto" value="<?= $item['id'] ?>">
        <input type="hidden" name="precio_final" value="<?= $es_cliente_prime ? ($item['precio'] * 0.90) : $item['precio'] ?>">
        <button type="submit" name="agregar_carrito" class="btn-comprar" style="width: 100%; border: 1px solid #fcd200;">Agregar al Carrito</button>
    </form>
<?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>