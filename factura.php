<?php
session_start();
include 'conexion.php';

// Verificar que el usuario haya iniciado sesión
if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['cliente_id'])) {
    header("Location: index.php");
    exit();
}

$id_cliente = $_SESSION['cliente_id'] ?? $_SESSION['usuario_id'];
$id_pedido = isset($_GET['id']) ? intval($_GET['id']) : 0;

try {
    // 1. Obtener los datos del cliente desde la base de datos
    $stmt_cliente = $conexion->prepare("SELECT * FROM clientes WHERE id = :id");
    $stmt_cliente->execute(['id' => $id_cliente]);
    $cliente = $stmt_cliente->fetch(PDO::FETCH_ASSOC);

    // 2. Obtener el pedido específico (si viene un ID por URL) o el último del cliente
    if ($id_pedido > 0) {
        $stmt_pedido = $conexion->prepare("SELECT * FROM pedidos WHERE id = :id AND cliente_id = :cliente_id");
        $stmt_pedido->execute(['id' => $id_pedido, 'cliente_id' => $id_cliente]);
    } else {
        $stmt_pedido = $conexion->prepare("SELECT * FROM pedidos WHERE cliente_id = :cliente_id ORDER BY id DESC LIMIT 1");
        $stmt_pedido->execute(['cliente_id' => $id_cliente]);
    }
    
    $pedido = $stmt_pedido->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        echo "No se encontró ningún pedido reciente o no tienes permisos para verlo.";
        exit();
    }

    // 3. Obtener los detalles de los productos comprados desde la base de datos (evitando usar el carrito vacío)
    $stmt_detalles = $conexion->prepare("
        SELECT dp.*, r.nombre 
        FROM detalle_pedidos dp 
        JOIN repuestos r ON dp.repuesto_id = r.id 
        WHERE dp.pedido_id = :pedido_id
    ");
    $stmt_detalles->execute(['pedido_id' => $pedido['id']]);
    $productos_factura = $stmt_detalles->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Error al cargar la factura: " . $e->getMessage();
    exit();
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura de Compra - AutoRepuestos EC</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, sans-serif; }
        body { background-color: #eaeded; padding: 20px; }
        .factura-container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 6px; box-shadow: 0 1px 5px rgba(0,0,0,0.1); }
        
        .factura-header { display: flex; justify-content: space-between; border-bottom: 2px solid #eee; padding-bottom: 20px; margin-bottom: 20px; }
        .logo { font-size: 24px; font-weight: bold; color: #ff9900; }
        .logo span { color: #111; font-size: 14px; display: block; }
        .factura-info { text-align: right; font-size: 13px; color: #555; }
        
        .cliente-seccion, .detalles-seccion { margin-bottom: 25px; }
        h3 { font-size: 16px; color: #111; margin-bottom: 10px; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
        p { font-size: 13px; color: #333; margin-bottom: 5px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; font-size: 13px; }
        th { background-color: #f8f9fa; }
        
        .total-container { text-align: right; margin-top: 20px; font-size: 16px; }
        .total-container span { font-weight: bold; color: #b12704; font-size: 20px; }
        
        .acciones { margin-top: 30px; display: flex; justify-content: space-between; }
        .btn { padding: 10px 20px; border-radius: 5px; font-weight: bold; text-decoration: none; font-size: 13px; cursor: pointer; }
        .btn-imprimir { background-color: #ffd814; border: 1px solid #fcd200; color: black; }
        .btn-imprimir:hover { background-color: #f7ca00; }
        .btn-volver { background-color: #e7e7e7; color: #111; border: 1px solid #ccc; }
        .btn-volver:hover { background-color: #d4d4d4; }

        @media print {
            body { background-color: white; padding: 0; }
            .factura-container { box-shadow: none; padding: 0; }
            .acciones { display: none; }
        }
    </style>
</head>
<body>

    <div class="factura-container">
        <!-- Cabecera de la factura -->
        <div class="factura-header">
            <div class="logo">
                🚗 AutoRepuestos<span>.ec</span>
            </div>
            <div class="factura-info">
                <strong>Factura Nº:</strong> #0000<?= $pedido['id'] ?><br>
                <strong>Fecha:</strong> <?= htmlspecialchars($pedido['fecha_pedido']) ?><br>
                <strong>Estado:</strong> <span style="color: #28a745; font-weight: bold;"><?= htmlspecialchars($pedido['estado']) ?></span>
            </div>
        </div>

        <!-- Información del cliente -->
        <div class="cliente-seccion">
            <h3>Datos del Cliente</h3>
            <p><strong>Nombre:</strong> <?= htmlspecialchars($cliente['nombre'] ?? 'Cliente') ?></p>
            <p><strong>Correo Electrónico:</strong> <?= htmlspecialchars($cliente['email'] ?? '') ?></p>
        </div>

        <!-- Información del pedido -->
        <div class="detalles-seccion">
            <h3>Detalle de la Compra</h3>
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cantidad</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($productos_factura)): ?>
                        <?php foreach ($productos_factura as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['nombre']) ?></td>
                                <td><?= $item['cantidad'] ?></td>
                                <td>$<?= number_format($item['precio_unitario'] * $item['cantidad'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" style="text-align: center;">No hay productos registrados en este pedido.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Total de la compra -->
        <div class="total-container">
            Total a Pagar: <span>$<?= number_format($pedido['total'], 2) ?></span>
        </div>

        <!-- Botones de acción -->
        <!-- Botones de acción -->
        <div class="acciones">
            <a href="pedidos.php" class="btn btn-volver">&larr; Ver mis pedidos</a>
            
            <button onclick="finalizarCompra()" class="btn btn-imprimir">Imprimir y Finalizar Compra</button>
        </div>

        <script>
        function finalizarCompra() {
            // 1. Imprimir la factura
            window.print();
            
            // 2. Mostrar mensaje de éxito
            alert("¡Compra exitosa! Gracias por preferir AutoRepuestos.ec");
            
            // 3. Redirigir al index
            window.location.href = "index.php";
        }
        </script>
    </div>

</body>
</html>