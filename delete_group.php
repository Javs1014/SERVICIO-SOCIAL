<?php
// delete_group.php

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_secure' => true,
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
}

// Roles permitidos
$allowed_roles = ['administrador', 'jefe', 'coordinador', 'admin'];
$current_role = strtolower($_SESSION['rol'] ?? '');

if (empty($_SESSION['usuario_id']) || !in_array($current_role, $allowed_roles)) {
    $_SESSION['mensaje'] = "Acceso denegado.";
    $_SESSION['mensaje_tipo'] = "error";
    header("Location: add_user.php");
    exit;
}

require_once 'db.php';

$id_grupo = $_GET['id'] ?? null;

if (!$id_grupo) {
    $_SESSION['mensaje'] = "ID de grupo no proporcionado.";
    $_SESSION['mensaje_tipo'] = "error";
    header("Location: add_user.php");
    exit;
}

try {
    // Eliminar el grupo
    $stmt = $conn->prepare("DELETE FROM grupos WHERE id_grupo = ?");
    $stmt->bind_param("i", $id_grupo);
    
    if ($stmt->execute()) {
        $_SESSION['mensaje'] = "Grupo eliminado correctamente.";
        $_SESSION['mensaje_tipo'] = "success";
    } else {
        throw new Exception("Error al eliminar el grupo. Verifica si está en uso.");
    }
    $stmt->close();

} catch (Exception $e) {
    $_SESSION['mensaje'] = "Error: " . $e->getMessage();
    $_SESSION['mensaje_tipo'] = "error";
}

header("Location: add_user.php");
exit;
?>