<?php
session_start();
include 'conexion.php';

// Validar que el usuario haya iniciado sesión
if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['cliente_id'])) {
    header("Location: index.php");
    exit();
}

$id_cliente = intval($_SESSION['cliente_id'] ?? $_SESSION['usuario_id']);
$total_pago = isset($_POST['total_pago']) ? floatval($_POST['total_pago']) : 0;

if ($total_pago <= 0 || empty($_SESSION['carrito'])) {
    die("Error: El carrito está vacío o el total no es válido.");
}

try {
    // Iniciar transacción en la base de datos
    $conexion->beginTransaction();

    // 1. Insertar el pedido principal
    $stmt_pedido = $conexion->prepare("INSERT INTO pedidos (cliente_id, total, estado, metodo_pago, fecha_pedido) VALUES (:cliente_id, :total, 'Pagado', 'Efectivo/Tarjeta', NOW()) RETURNING id");
    $stmt_pedido->execute([
        'cliente_id' => $id_cliente,
        'total' => $total_pago
    ]);
    
    $id_pedido = $stmt_pedido->fetchColumn();

    // 2. Guardar los productos en 'detalle_pedidos' y actualizar el stock
    $stmt_detalle = $conexion->prepare("INSERT INTO detalle_pedidos (pedido_id, repuesto_id, cantidad, precio_unitario) VALUES (:pedido_id, :repuesto_id, :cantidad, :precio_unitario)");
    
    $stmt_stock = $conexion->prepare("UPDATE repuestos SET stock = stock - :cantidad WHERE id = :id AND stock >= :cantidad");

    foreach ($_SESSION['carrito'] as $item) {
        // Insertar en detalle
        $stmt_detalle->execute([
            'pedido_id' => $id_pedido,
            'repuesto_id' => $item['id'],
            'cantidad' => $item['cantidad'],
            'precio_unitario' => $item['precio']
        ]);

        // Actualizar y descontar el stock validando que haya unidades suficientes
        $stmt_stock->execute([
            'cantidad' => $item['cantidad'],
            'id' => $item['id']
        ]);

        if ($stmt_stock->rowCount() === 0) {
            throw new Exception("Stock insuficiente para el repuesto con ID: " . $item['id']);
        }
    }

    // 3. Confirmar transacción
    $conexion->commit();

    // Limpiamos el carrito para que desaparezcan los productos comprados
    unset($_SESSION['carrito']);

    // Redirigir a la factura
    header("Location: factura.php?id=" . $id_pedido);
    exit();

} catch (Exception $e) {
    // Revertir si hay error
    if ($conexion->inTransaction()) {
        $conexion->rollBack();
    }
    echo "Error al procesar el pago: " . $e->getMessage();
}
?>