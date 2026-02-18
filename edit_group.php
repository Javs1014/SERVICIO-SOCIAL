<?php
session_start();
require_once 'db.php';

// Verificación de permisos
$allowed_roles = ['administrador', 'jefe', 'coordinador', 'admin'];
$current_role = strtolower($_SESSION['rol'] ?? '');
if (empty($_SESSION['usuario_id']) || !in_array($current_role, $allowed_roles)) {
    header("Location: add_user.php");
    exit;
}

$id_grupo = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$id_grupo) {
    header("Location: add_user.php");
    exit;
}

// Obtener datos del grupo
$stmt = $conn->prepare("SELECT nombre FROM grupos WHERE id_grupo = ?");
$stmt->bind_param("i", $id_grupo);
$stmt->execute();
$result = $stmt->get_result();
$grupo = $result->fetch_assoc();

if (!$grupo) {
    $_SESSION['mensaje'] = "Grupo no encontrado.";
    $_SESSION['mensaje_tipo'] = "error";
    header("Location: add_user.php");
    exit;
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    
    if (empty($nombre)) {
        $error = "El nombre es obligatorio.";
    } else {
        try {
            $stmt = $conn->prepare("UPDATE grupos SET nombre = ? WHERE id_grupo = ?");
            $stmt->bind_param("si", $nombre, $id_grupo);
            
            if ($stmt->execute()) {
                $_SESSION['mensaje'] = "Grupo actualizado correctamente.";
                $_SESSION['mensaje_tipo'] = "success";
                header("Location: add_user.php");
                exit;
            } else {
                throw new Exception("Error al actualizar.");
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Grupo</title>
    <link rel="stylesheet" href="../styles/styles_addUser.css">
</head>
<body>
    <div class="content" style="margin: 0 auto; max-width: 600px; padding-top: 50px;">
        <h2>Editar Grupo</h2>
        <?php if (isset($error)): ?>
            <div class="mensaje error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Nombre del Grupo:</label>
                <input type="text" name="nombre" value="<?= htmlspecialchars($grupo['nombre']) ?>" required>
            </div>
            <button type="submit" class="btn">Guardar Cambios</button>
            <a href="add_user.php" class="btn cancel-btn" style="background-color: #999;">Cancelar</a>
        </form>
    </div>
</body>
</html>