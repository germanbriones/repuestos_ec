<?php
session_start();
include 'conexion.php';

// Verificar que el cliente o usuario haya iniciado sesión
if (!isset($_SESSION['usuario_id']) && !isset($_SESSION['cliente_id'])) {
    header("Location: index.php");
    exit();
}

// Obtenemos el ID del cliente actual desde la sesión
$id_cliente = $_SESSION['cliente_id'] ?? $_SESSION['usuario_id'];
$mensaje_exito = '';

// Procesar la devolución cuando se confirma
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_devolucion'])) {
    $id_pedido = intval($_POST['id_pedido']);
    
    try {
        // Cambiamos el estado a 'Devuelto' para que la consulta lo oculte de inmediato
        $stmt = $conexion->prepare("UPDATE pedidos SET estado = 'Devuelto' WHERE id = :id");
        $stmt->execute(['id' => $id_pedido]);

        // Redireccionar para limpiar el POST y actualizar la vista
        header("Location: pedidos.php");
        exit();

    } catch (PDOException $e) {
        $mensaje_exito = "Error al procesar la devolución: " . $e->getMessage();
    }
}

// Detectar si es administrador (Ajusta esta condición según tu sistema de roles, ej: $_SESSION['rol'] === 'admin')
$es_admin = isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'; 
// O si identificas al admin por un ID específico, puedes usar: $es_admin = ($id_cliente == 1);

if ($es_admin) {
    // Consulta para el ADMINISTRADOR: Trae todos los pedidos de todos los clientes con su nombre
    $stmt_pedidos = $conexion->prepare("
        SELECT p.*, c.nombre AS nombre_cliente 
        FROM pedidos p 
        JOIN clientes c ON p.cliente_id = c.id 
        WHERE p.estado != 'Devuelto' 
        ORDER BY p.id DESC
    ");
    $stmt_pedidos->execute();
} else {
    // Consulta para el CLIENTE: Solo trae sus propios pedidos
    $stmt_pedidos = $conexion->prepare("
        SELECT p.*, c.nombre AS nombre_cliente 
        FROM pedidos p 
        JOIN clientes c ON p.cliente_id = c.id 
        WHERE p.cliente_id = :cliente_id AND p.estado != 'Devuelto' 
        ORDER BY p.id DESC
    ");
    $stmt_pedidos->execute(['cliente_id' => $id_cliente]);
}

$mis_pedidos = $stmt_pedidos->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pedidos - AutoRepuestos EC</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: Arial, sans-serif; }
        body { background-color: #eaeded; }
        header { background-color: #131921; color: white; padding: 10px 20px; display: flex; align-items: center; justify-content: space-between; }
        .logo { font-size: 22px; font-weight: bold; color: #ff9900; text-decoration: none; }
        .logo span { color: white; font-size: 14px; }
        
        .container { max-width: 1000px; margin: 20px auto; padding: 0 15px; }
        h1 { font-size: 24px; color: #111; margin-bottom: 20px; }
        
        .alerta-exito { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 12px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; }
        
        .sin-pedidos { background: white; padding: 30px; text-align: center; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); color: #555; }
        .sin-pedidos p { font-size: 15px; margin-bottom: 15px; }

        .card-pedido { background: white; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); overflow: hidden; }
        .pedido-header { background: #f0f2f2; padding: 12px 20px; display: flex; justify-content: space-between; font-size: 13px; color: #555; border-bottom: 1px solid #ddd; }
        .pedido-body { padding: 20px; display: flex; justify-content: space-between; align-items: center; }
        
        .pedido-info h3 { font-size: 16px; color: #007185; margin-bottom: 5px; }
        .pedido-info p { font-size: 13px; color: #333; margin-bottom: 3px; }
        
        .badge-pagado { background: #28a745; color: white; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; }
        
        .acciones-pedido { display: flex; gap: 10px; align-items: center; }

        .btn-verificar { background-color: #ffd814; border: 1px solid #fcd200; padding: 8px 15px; border-radius: 6px; font-weight: bold; text-decoration: none; color: black; font-size: 13px; display: inline-block; }
        .btn-verificar:hover { background-color: #f7ca00; }

        .btn-devolucion { background-color: #e7e7e7; border: 1px solid #ccc; padding: 8px 15px; border-radius: 6px; font-weight: bold; cursor: pointer; text-decoration: none; color: #333; font-size: 13px; }
        .btn-devolucion:hover { background-color: #d4d4d4; }

        /* Estilo del Modal */
        .modal { display: none; position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); align-items: center; justify-content: center; }
        .modal-content { background: white; padding: 25px; border-radius: 6px; width: 350px; text-align: center; box-shadow: 0 4px 10px rgba(0,0,0,0.2); }
        .modal-content h3 { margin-bottom: 15px; font-size: 18px; color: #111; }
        .modal-content p { font-size: 14px; color: #555; margin-bottom: 20px; }
        
        .btn-modal-si { background: #b12704; color: white; border: none; padding: 8px 20px; border-radius: 4px; font-weight: bold; cursor: pointer; margin-right: 10px; }
        .btn-modal-si:hover { background: #902003; }
        .btn-modal-no { background: #e7e7e7; color: #111; border: 1px solid #ccc; padding: 8px 20px; border-radius: 4px; font-weight: bold; cursor: pointer; }
        .btn-modal-no:hover { background: #d4d4d4; }

        .btn-volver { display: inline-block; margin-top: 15px; color: #0066c0; text-decoration: none; font-size: 13px; }
        .btn-volver:hover { text-decoration: underline; color: #c45500; }
        
        .btn-comprar-ahora { background-color: #ffd814; border: 1px solid #fcd200; padding: 10px 20px; border-radius: 8px; font-weight: bold; text-decoration: none; color: black; display: inline-block; }
        .btn-comprar-ahora:hover { background-color: #f7ca00; }
    </style>
</head>
<body>

    <header>
        <a href="index.php" class="logo">🚗 AutoRepuestos<span>.ec</span></a>
        <div style="color: #ccc; font-size: 14px;"><?= $es_admin ? 'Panel de Administración - Pedidos' : 'Mis Pedidos Realizados' ?></div>
    </header>

    <div class="container">
        <h1><?= $es_admin ? 'Todos los Pedidos de los Clientes' : 'Pedidos Ya Realizados' ?></h1>

        <?php if (!empty($mensaje_exito)): ?>
            <div class="alerta-exito"><?= $mensaje_exito ?></div>
        <?php endif; ?>

        <?php if (empty($mis_pedidos)): ?>
            <!-- Mensaje cuando NO hay pedidos registrados -->
            <div class="sin-pedidos">
                <p>📦 Todavía no hay pedidos registrados en la tienda.</p>
                <a href="index.php" class="btn-comprar-ahora">Ir al inicio</a>
            </div>
        <?php else: ?>
            <!-- Bucle para mostrar los pedidos -->
            <?php foreach ($mis_pedidos as $pedido): ?>
                <div class="card-pedido">
                    <div class="pedido-header">
                        <div><strong>Nº de Pedido:</strong> #<?= $pedido['id'] ?></div>
                        <div><strong>Total:</strong> $<?= number_format($pedido['total'] ?? 0, 2) ?> | <span class="badge-pagado"><?= htmlspecialchars($pedido['estado']) ?></span></div>
                    </div>
                    <div class="pedido-body">
                        <div class="pedido-info">
                            <h3>Pedido #<?= $pedido['id'] ?></h3>
                            <?php if ($es_admin): ?>
                                <p><strong>Cliente:</strong> <?= htmlspecialchars($pedido['nombre_cliente']) ?></p>
                            <?php endif; ?>
                            <p>Fecha: <?= htmlspecialchars($pedido['fecha_pedido'] ?? 'Reciente') ?></p>
                        </div>
                        <div class="acciones-pedido">
                            <!-- Botón Verificar para ver lo que compró (Factura) -->
                            <a href="factura.php?id=<?= $pedido['id'] ?>" class="btn-verificar">Verificar</a>
                            
                            <!-- Botón para iniciar devolución -->
                            <button class="btn-devolucion" onclick="confirmarDevolucion(<?= $pedido['id'] ?>, 'Pedido #<?= $pedido['id'] ?>')">Devolver</button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <br>
        <a href="index.php" class="btn-volver">&larr; Volver al inicio</a>
    </div>

    <!-- Modal de confirmación -->
    <div id="modalConfirmacion" class="modal">
        <div class="modal-content">
            <h3>¿Estás seguro?</h3>
            <p id="texto-producto">¿Seguro que desea devolver este producto?</p>
            
            <form action="pedidos.php" method="POST">
                <input type="hidden" name="id_pedido" id="input_id_pedido">
                <button type="submit" name="confirmar_devolucion" class="btn-modal-si">Sí, devolver</button>
                <button type="button" class="btn-modal-no" onclick="cerrarModal()">Cancelar</button>
            </form>
        </div>
    </div>

    <script>
        function confirmarDevolucion(idPedido, nombreProducto) {
            document.getElementById('input_id_pedido').value = idPedido;
            document.getElementById('texto-producto').innerText = '¿Seguro que desea devolver el ' + nombreProducto + '?';
            document.getElementById('modalConfirmacion').style.display = 'flex';
        }

        function cerrarModal() {
            document.getElementById('modalConfirmacion').style.display = 'none';
        }
    </script>

</body>
</html>