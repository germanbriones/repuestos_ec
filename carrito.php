<?php
session_start();
include 'conexion.php';

// Inicializar el carrito en sesión si no existe
if (!isset($_SESSION['carrito'])) {
    $_SESSION['carrito'] = [];
}

// Agregar producto al carrito
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['agregar_carrito'])) {
    $id_repuesto = intval($_POST['id_repuesto']);
    $precio_aplicado = floatval($_POST['precio_final']);

    if (isset($_SESSION['carrito'][$id_repuesto])) {
        $_SESSION['carrito'][$id_repuesto]['cantidad'] += 1;
    } else {
        $stmt = $conexion->prepare("SELECT * FROM repuestos WHERE id = :id");
        $stmt->execute(['id' => $id_repuesto]);
        $repuesto = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($repuesto) {
            $_SESSION['carrito'][$id_repuesto] = [
                'id' => $repuesto['id'],
                'nombre' => $repuesto['nombre'],
                'precio' => $precio_aplicado,
                'imagen' => $repuesto['imagen'],
                'cantidad' => 1
            ];
        }
    }
    header("Location: carrito.php");
    exit();
}

// Modificar cantidades o eliminar desde los botones de la vista
if (isset($_GET['accion']) && isset($_GET['id'])) {
    $_accion = $_GET['accion'];
    $id_item = intval($_GET['id']);
    
    if ($_accion === 'sumar' && isset($_SESSION['carrito'][$id_item])) {
        $_SESSION['carrito'][$id_item]['cantidad'] += 1;
    } elseif ($_accion === 'restar' && isset($_SESSION['carrito'][$id_item])) {
        $_SESSION['carrito'][$id_item]['cantidad'] -= 1;
        // Si la cantidad baja a 0, lo eliminamos automáticamente
        if ($_SESSION['carrito'][$id_item]['cantidad'] <= 0) {
            unset($_SESSION['carrito'][$id_item]);
        }
    } elseif ($_accion === 'eliminar') {
        unset($_SESSION['carrito'][$id_item]);
    }
    
    header("Location: carrito.php");
    exit();
}
    
  

$es_cliente_prime = isset($_SESSION['es_prime']) && ($_SESSION['es_prime'] == 1 || $_SESSION['es_prime'] === true || $_SESSION['es_prime'] === 't');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Carrito de Compras - AutoRepuestos EC</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, sans-serif; }
        body { background-color: #eaeded; }
        header { background-color: #131921; color: white; padding: 10px 20px; display: flex; align-items: center; justify-content: space-between; }
        .logo { font-size: 22px; font-weight: bold; color: #ff9900; text-decoration: none; }
        .logo span { color: white; font-size: 14px; }
        
        .container { max-width: 1200px; margin: 20px auto; padding: 0 15px; display: flex; gap: 20px; }
        .cart-left { flex: 2; background: white; padding: 20px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .cart-right { flex: 1; background: white; padding: 20px; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); height: fit-content; }
        
        h1 { font-size: 24px; border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-bottom: 15px; color: #111; }
        
        .cart-item { display: flex; gap: 15px; border-bottom: 1px solid #eee; padding: 15px 0; align-items: center; }
        .cart-item img { width: 80px; height: 80px; object-fit: cover; border-radius: 4px; background: #f9f9f9; }
        .cart-item-details { flex-grow: 1; }
        .cart-item-details h3 { font-size: 16px; color: #007185; margin-bottom: 5px; }
        .precio-item { font-weight: bold; color: #B12704; font-size: 16px; }
        
        .cantidad-control { display: flex; align-items: center; gap: 8px; margin-top: 8px; }
        .cantidad-control a { background: #f0f2f2; border: 1px solid #d5d9d9; padding: 2px 8px; text-decoration: none; color: #0f1111; border-radius: 3px; font-weight: bold; }
        .cantidad-control a:hover { background: #e3e6e6; }
        
        .btn-eliminar { color: #0066c0; background: none; border: none; cursor: pointer; font-size: 12px; text-decoration: none; margin-left: 10px; }
        .btn-eliminar:hover { text-decoration: underline; color: #c45500; }
        
        .subtotal-box { font-size: 18px; margin-bottom: 15px; color: #111; }
        .btn-pagar { background-color: #ffd814; border: 1px solid #fcd200; width: 100%; padding: 10px; border-radius: 8px; font-weight: bold; cursor: pointer; text-align: center; display: block; text-decoration: none; color: black; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .btn-pagar:hover { background-color: #f7ca00; }
        
        .btn-volver { display: inline-block; margin-top: 15px; color: #0066c0; text-decoration: none; font-size: 13px; }
        .btn-volver:hover { text-decoration: underline; color: #c45500; }
    </style>
</head>
<body>

    <header>
        <a href="index.php" class="logo">🚗 AutoRepuestos<span>.ec</span></a>
        <div style="color: #ccc; font-size: 14px;">Carrito de Compras</div>
    </header>

    <div class="container">
        <div class="cart-left">
            <h1>Tu Carrito de Compras</h1>
            
            <?php if (empty($_SESSION['carrito'])): ?>
                <p style="color: #555; padding: 20px 0;">Tu carrito está vacío.</p>
                <a href="index.php" class="btn-volver">&larr; Volver a la tienda a comprar repuestos</a>
            <?php else: ?>
                <?php 
                $total_general = 0;
                foreach ($_SESSION['carrito'] as $item): 
                    $subtotal = $item['precio'] * $item['cantidad'];
                    $total_general += $subtotal;
                ?>
                    <div class="cart-item">
                        <div>
                            <?php if (!empty($item['imagen'])): ?>
                                <img src="<?= htmlspecialchars($item['imagen']) ?>" alt="<?= htmlspecialchars($item['nombre']) ?>">
                            <?php else: ?>
                                <div style="width: 80px; height: 80px; display: flex; align-items: center; justify-content: center; background: #eee; font-size: 24px;">⚙️</div>
                            <?php endif; ?>
                        </div>
                        <div class="cart-item-details">
                            <h3><?= htmlspecialchars($item['nombre']) ?></h3>
                            <div class="cantidad-control">
                                <span>Cantidad:</span>
                                <a href="carrito.php?accion=restar&id=<?= $item['id'] ?>">-</a>
                                <strong><?= $item['cantidad'] ?></strong>
                                <a href="carrito.php?accion=sumar&id=<?= $item['id'] ?>">+</a>
                                <a href="carrito.php?accion=eliminar&id=<?= $item['id'] ?>" class="btn-eliminar">Eliminar</a>
                            </div>
                        </div>
                        <div class="precio-item">
                            $<?= number_format($subtotal, 2) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <a href="index.php" class="btn-volver">&larr; Seguir comprando</a>
            <?php endif; ?>
        </div>

        <?php if (!empty($_SESSION['carrito'])): ?>
            <div class="cart-right">
                <h3>Resumen de la orden</h3><br>
                <div class="subtotal-box">
                    Subtotal (<?= array_sum(array_column($_SESSION['carrito'], 'cantidad')) ?> productos): <br>
                    <strong style="font-size: 22px; color: #B12704;">$<?= number_format($total_general, 2) ?></strong>
                </div>
                
                <form action="procesar_pago.php" method="POST">
                    <input type="hidden" name="total_pago" value="<?= $total_general ?>">
                    <button type="submit" name="procesar_pago" class="btn-pagar">Proceder al pago</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>