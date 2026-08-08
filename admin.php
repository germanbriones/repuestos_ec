<?php
session_start();
include 'conexion.php';

$mensaje = '';

// Crear carpeta de imágenes automáticamente si no existe
$carpeta_imagenes = 'uploads/';
if (!file_exists($carpeta_imagenes)) {
    mkdir($carpeta_imagenes, 0777, true);
}

// Procesar eliminación de un repuesto
if (isset($_GET['eliminar'])) {
    $id_eliminar = intval($_GET['eliminar']);
    try {
        $stmt_img = $conexion->prepare("SELECT imagen FROM repuestos WHERE id = :id");
        $stmt_img->execute(['id' => $id_eliminar]);
        $repuesto_actual = $stmt_img->fetch(PDO::FETCH_ASSOC);
        
        if ($repuesto_actual && !empty($repuesto_actual['imagen']) && file_exists($repuesto_actual['imagen'])) {
            unlink($repuesto_actual['imagen']);
        }

        $stmt = $conexion->prepare("DELETE FROM repuestos WHERE id = :id");
        $stmt->execute(['id' => $id_eliminar]);
        
        header("Location: admin.php");
        exit();
    } catch (PDOException $e) {
        $mensaje = "Error al eliminar el repuesto: " . $e->getMessage();
    }
}

// Procesar acción de hacer pedido / reabastecer stock automáticamente
if (isset($_GET['pedir'])) {
    $id_pedir = intval($_GET['pedir']);
    try {
        $stmt_info = $conexion->prepare("SELECT stock, demanda_predicha FROM repuestos WHERE id = :id");
        $stmt_info->execute(['id' => $id_pedir]);
        $rep = $stmt_info->fetch(PDO::FETCH_ASSOC);

        if ($rep) {
            $cantidad_a_pedir = max(10, $rep['demanda_predicha']);
            $nuevo_stock = $rep['stock'] + $cantidad_a_pedir;

            $stmt_update = $conexion->prepare("UPDATE repuestos SET stock = :stock WHERE id = :id");
            $stmt_update->execute(['stock' => $nuevo_stock, 'id' => $id_pedir]);

            $mensaje = "¡Pedido realizado con éxito! Se han sumado $cantidad_a_pedir unidades al stock del repuesto #$id_pedir.";
        }
    } catch (PDOException $e) {
        $mensaje = "Error al procesar el pedido: " . $e->getMessage();
    }
}

// Procesar formulario cuando se envía (Agregar o Modificar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_repuesto'])) {
    $id_repuesto = intval($_POST['id_repuesto']);
    $nombre = trim($_POST['nombre']);
    $categoria = trim($_POST['categoria']);
    $precio = floatval($_POST['precio']);
    $stock = intval($_POST['stock']);
    $demanda_predicha = intval($_POST['demanda_predicha'] ?? 5);
    $imagen_ruta = trim($_POST['imagen_actual'] ?? '');

    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $tipo_archivo = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        $extensiones_validas = ['jpg', 'jpeg', 'png', 'webp'];

        if (in_array($tipo_archivo, $extensiones_validas)) {
            $nombre_archivo = time() . '_' . bin2hex(random_bytes(4)) . '.' . $tipo_archivo;
            $ruta_destino = $carpeta_imagenes . $nombre_archivo;
            
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], $ruta_destino)) {
                // Eliminar la imagen anterior si se actualiza por una nueva
                if (!empty($_POST['imagen_actual']) && file_exists($_POST['imagen_actual'])) {
                    @unlink($_POST['imagen_actual']);
                }
                $imagen_ruta = $ruta_destino;
            }
        }
    }

    try {
        if ($id_repuesto > 0) {
            $stmt = $conexion->prepare("UPDATE repuestos SET nombre = :nombre, categoria = :categoria, precio = :precio, stock = :stock, demanda_predicha = :demanda_predicha, imagen = :imagen WHERE id = :id");
            $stmt->execute([
                'nombre' => $nombre,
                'categoria' => $categoria,
                'precio' => $precio,
                'stock' => $stock,
                'demanda_predicha' => $demanda_predicha,
                'imagen' => $imagen_ruta,
                'id' => $id_repuesto
            ]);
            $mensaje = "¡Repuesto actualizado exitosamente!";
        } else {
            $stmt = $conexion->prepare("INSERT INTO repuestos (nombre, categoria, precio, stock, demanda_predicha, imagen) VALUES (:nombre, :categoria, :precio, :stock, :demanda_predicha, :imagen)");
            $stmt->execute([
                'nombre' => $nombre,
                'categoria' => $categoria,
                'precio' => $precio,
                'stock' => $stock,
                'demanda_predicha' => $demanda_predicha,
                'imagen' => $imagen_ruta
            ]);
            $mensaje = "¡Repuesto agregado exitosamente!";
        }
        
        header("Location: admin.php");
        exit();

    } catch (PDOException $e) {
        $mensaje = "Error al guardar el repuesto: " . $e->getMessage();
    }
}

try {
    $query = $conexion->query("SELECT * FROM repuestos ORDER BY id ASC");
    $repuestos = $query->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $repuestos = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - AutoRepuestos EC</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Arial, sans-serif; }
        body { background-color: #f4f6f9; color: #333; padding: 20px; }
        
        header { background-color: #131921; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        header h1 { font-size: 20px; color: #ff9900; }
        .nav-links a { color: #ddd; text-decoration: none; margin-left: 20px; font-size: 14px; font-weight: 500; }
        .nav-links a:hover { color: white; text-decoration: underline; }

        .container { max-width: 1350px; margin: 25px auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        
        h2 { margin-bottom: 8px; color: #111; font-size: 22px; }
        
        .alerta { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; padding: 12px 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; font-weight: 500; }

        .form-card { background: #ffffff; padding: 25px; border-radius: 8px; border: 1px solid #e1e4e8; margin-bottom: 30px; box-shadow: 0 2px 6px rgba(0,0,0,0.02); }
        .form-card h3 { margin-bottom: 18px; font-size: 16px; color: #232f3e; }
        
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { font-size: 11px; font-weight: bold; margin-bottom: 6px; color: #555; text-transform: uppercase; letter-spacing: 0.5px; }
        .form-group input, .form-group select { padding: 10px 12px; border: 1px solid #ced4da; border-radius: 6px; font-size: 14px; transition: border-color 0.2s; background: #fff; width: 100%; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #ff9900; box-shadow: 0 0 0 3px rgba(255, 153, 0, 0.15); }
        
        .acciones-form { display: flex; gap: 10px; justify-content: flex-start; align-items: center; border-top: 1px solid #eaeaea; padding-top: 15px; }
        
        .btn-guardar { background-color: #ffd814; border: 1px solid #fcd200; padding: 10px 25px; font-weight: bold; border-radius: 6px; cursor: pointer; color: #111; font-size: 14px; transition: background 0.2s; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .btn-guardar:hover { background-color: #f7ca00; }
        
        .btn-cancelar { background-color: #e2e8f0; border: none; padding: 10px 20px; font-weight: bold; border-radius: 6px; cursor: pointer; color: #4a5568; text-decoration: none; display: inline-block; font-size: 14px; }
        .btn-cancelar:hover { background-color: #cbd5e0; }

        .table-responsive { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid #eaeaea; font-size: 13px; vertical-align: middle; }
        th { background-color: #f8f9fa; color: #444; font-weight: 600; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
        tr:hover { background-color: #fafbfc; }

        .img-tabla { width: 42px; height: 42px; object-fit: cover; border-radius: 6px; border: 1px solid #ddd; background: #eee; }

        .badge-stock { background: #e3f2fd; color: #0d47a1; padding: 5px 10px; border-radius: 20px; font-weight: 600; font-size: 12px; display: inline-block; white-space: nowrap; }
        .badge-alerta { background: #ffebee; color: #c62828; padding: 5px 10px; border-radius: 20px; font-weight: 600; font-size: 11px; display: inline-block; }
        .badge-ok { background: #e8f5e9; color: #2e7d32; padding: 5px 10px; border-radius: 20px; font-weight: 600; font-size: 11px; display: inline-block; white-space: nowrap; }
        .badge-demanda { background: #f1f3f5; color: #495057; padding: 5px 8px; border-radius: 20px; font-weight: 500; font-size: 12px; display: inline-block; white-space: nowrap; }
        
        .grupo-acciones { display: flex; gap: 5px; align-items: center; }
        .btn-editar { background-color: #007185; color: white; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 11px; transition: background 0.2s; white-space: nowrap; }
        .btn-editar:hover { background-color: #005f6b; }

        .btn-eliminar { background-color: #d9534f; color: white; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 11px; transition: background 0.2s; white-space: nowrap; text-decoration: none; display: inline-block; }
        .btn-eliminar:hover { background-color: #c9302c; }

        .btn-pedido { background-color: #f0ad4e; color: white; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 11px; transition: background 0.2s; white-space: nowrap; text-decoration: none; display: inline-block; }
        .btn-pedido:hover { background-color: #ec971f; }

        .btn-volver { display: inline-block; margin-bottom: 20px; color: #0066c0; text-decoration: none; font-weight: 600; font-size: 13px; }
        .btn-volver:hover { text-decoration: underline; color: #004080; }
    </style>
</head>
<body>

    <div class="container">
        <a href="index.php" class="btn-volver">&larr; Volver a la tienda (Vista Cliente)</a>
        
        <header>
            <h1>🛠️ Panel de Control & Inventario</h1>
            <div class="nav-links">
                <a href="index.php">Ver Tienda</a>
                <span><strong>Admin</strong></span>
            </div>
        </header>

        <br>

        <?php if (!empty($mensaje)): ?>
            <div class="alerta"><?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <div class="form-card">
            <h3 id="titulo-form">➕ Agregar Nuevo Repuesto</h3>
            <form action="admin.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id_repuesto" id="form_id" value="0">
                <input type="hidden" name="imagen_actual" id="form_imagen_actual" value="">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="form_nombre">Nombre del Repuesto</label>
                        <input type="text" name="nombre" id="form_nombre" placeholder="Ej. Pastillas de freno" required>
                    </div>
                    <div class="form-group">
                        <label for="form_categoria">Categoría</label>
                        <input type="text" name="categoria" id="form_categoria" placeholder="Ej. Frenos, Motor..." required>
                    </div>
                    <div class="form-group">
                        <label for="form_precio">Precio ($)</label>
                        <input type="number" step="0.01" name="precio" id="form_precio" placeholder="0.00" required>
                    </div>
                    <div class="form-group">
                        <label for="form_stock">Stock</label>
                        <input type="number" name="stock" id="form_stock" placeholder="0" required>
                    </div>
                    <div class="form-group">
                        <label for="form_demanda">Demanda Predicha</label>
                        <input type="number" name="demanda_predicha" id="form_demanda" value="5" placeholder="Ej. 15" required>
                    </div>
                    <div class="form-group">
                        <label for="form_imagen">Imagen</label>
                        <input type="file" name="imagen" id="form_imagen" accept="image/*" style="padding: 5px;">
                    </div>
                </div>
                
                <div class="acciones-form">
                    <button type="submit" name="guardar_repuesto" class="btn-guardar" id="btn-accion">Guardar Repuesto</button>
                    <div id="contenedor-cancelar" style="display: none;">
                        <button type="button" class="btn-cancelar" onclick="resetearFormulario()">Cancelar Edición</button>
                    </div>
                </div>
            </form>
        </div>

        <h2>Gestión de Inventario y Control de Stock</h2>
        <p style="color: #666; margin-bottom: 20px; font-size: 13px;">
            Control de stock en tiempo real y predicción de demanda para reabastecimiento automático.
        </p>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Categoría</th>
                        <th>Repuesto</th>
                        <th>Precio</th>
                        <th>Stock</th>
                        <th>Demanda Predicha</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($repuestos)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: #777; padding: 30px;">No hay repuestos registrados actualmente.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($repuestos as $item): ?>
                            <tr>
                                <td>#<?= $item['id'] ?></td>
                                <td><span style="background: #e2e8f0; color: #334155; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;"><?= htmlspecialchars($item['categoria'] ?? 'General') ?></span></td>
                                <td><strong><?= htmlspecialchars($item['nombre']) ?></strong></td>
                                <td><span style="color: #2e7d32; font-weight: 600;">$<?= number_format($item['precio'], 2) ?></span></td>
                                <td>
                                    <span class="badge-stock"><?= $item['stock'] ?> unids.</span>
                                </td>
                                <td>
                                    <span class="badge-demanda">📈 ~<?= $item['demanda_predicha'] ?? 5 ?> unids.</span>
                                </td>
                                <td>
                                    <?php if ($item['stock'] < 10): ?>
                                        <span class="badge-alerta">⚠️ Reponer</span>
                                    <?php else: ?>
                                        <span class="badge-ok">✔ Suficiente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="grupo-acciones">
                                        <button type="button" class="btn-editar" onclick="cargarParaEditar(<?= $item['id'] ?>, '<?= htmlspecialchars($item['nombre'], ENT_QUOTES) ?>', '<?= htmlspecialchars($item['categoria'] ?? '', ENT_QUOTES) ?>', <?= $item['precio'] ?>, <?= $item['stock'] ?>, <?= $item['demanda_predicha'] ?? 5 ?>, '<?= htmlspecialchars($item['imagen'] ?? '', ENT_QUOTES) ?>')">
                                            Editar
                                        </button>
                                        <a href="admin.php?eliminar=<?= $item['id'] ?>" class="btn-eliminar" onclick="return confirm('¿Estás seguro de eliminar este repuesto?');">
                                            Eliminar
                                        </a>
                                        <a href="admin.php?pedir=<?= $item['id'] ?>" class="btn-pedido" onclick="return confirm('¿Deseas realizar un pedido de reabastecimiento para este repuesto?');" title="Hacer pedido al proveedor">
                                            🛒 Pedir
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function cargarParaEditar(id, nombre, categoria, precio, stock, demanda, imagen) {
            document.getElementById('form_id').value = id;
            document.getElementById('form_nombre').value = nombre;
            document.getElementById('form_categoria').value = categoria;
            document.getElementById('form_precio').value = precio;
            document.getElementById('form_stock').value = stock;
            document.getElementById('form_demanda').value = demanda;
            document.getElementById('form_imagen_actual').value = imagen;
            
            document.getElementById('titulo-form').innerText = '✏️ Modificar Repuesto (ID: #' + id + ')';
            document.getElementById('btn-accion').innerText = 'Actualizar Cambios';
            document.getElementById('contenedor-cancelar').style.display = 'block';
            
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function resetearFormulario() {
            document.getElementById('form_id').value = '0';
            document.getElementById('form_nombre').value = '';
            document.getElementById('form_categoria').value = '';
            document.getElementById('form_precio').value = '';
            document.getElementById('form_stock').value = '';
            document.getElementById('form_demanda').value = '5';
            document.getElementById('form_imagen_actual').value = '';
            document.getElementById('form_imagen').value = '';
            
            document.getElementById('titulo-form').innerText = '➕ Agregar Nuevo Repuesto';
            document.getElementById('btn-accion').innerText = 'Guardar Repuesto';
            document.getElementById('contenedor-cancelar').style.display = 'none';
        }
    </script>

</body>
</html>