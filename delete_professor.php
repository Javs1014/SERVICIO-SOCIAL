<?php
// Inicia una sesión segura si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400, // Establece la duración de la cookie de sesión a 24 horas
        'cookie_secure' => true, // Cambiar a true en producción para HTTPS, asegura que la cookie solo se envíe por conexiones seguras
        'cookie_httponly' => true, // Previene acceso a la cookie desde JavaScript, aumentando la seguridad
        'use_strict_mode' => true // Activa el modo estricto para prevenir ataques de fijación de sesión
    ]);

    // Verifica si la sesión es nueva o ha expirado (1800 segundos = 30 minutos)
    if (!isset($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = time(); // Registra el momento de creación de la sesión
    } elseif (time() - $_SESSION['CREATED'] > 1800) {
        session_regenerate_id(true); // Regenera el ID de la sesión para mitigar ataques de fijación
        $_SESSION['CREATED'] = time(); // Actualiza el tiempo de creación
    }
}

// Registra el contenido de la sesión en el log de errores para depuración
error_log("Datos de sesión: " . print_r($_SESSION, true));

// Define roles permitidos para acceder a la eliminación de profesores
$allowed_roles = ['administrador', 'jefe', 'coordinador', 'admin'];
// Obtiene el rol del usuario actual en minúsculas, o cadena vacía si no está definido
$current_role = strtolower($_SESSION['rol'] ?? '');

// Verifica si el usuario está autenticado y tiene un rol permitido
if (empty($_SESSION['usuario_id']) || !in_array($current_role, $allowed_roles)) {
    $_SESSION['mensaje'] = "Acceso denegado: No tienes permisos para eliminar profesores."; // Mensaje de error
    $_SESSION['mensaje_tipo'] = "error"; // Tipo de mensaje
    header("Location: add_user.php"); // Redirige a la página de gestión de usuarios
    exit; // Termina la ejecución del script
}

// Incluye el archivo de conexión a la base de datos
require_once 'db.php';

// Obtiene el ID del profesor desde la URL, o null si no está presente
$id_profesor = $_GET['id'] ?? null;

// Verifica si se proporcionó un ID de profesor
if (!$id_profesor) {
    $_SESSION['mensaje'] = "ID de profesor no proporcionado."; // Mensaje de error
    $_SESSION['mensaje_tipo'] = "error"; // Tipo de mensaje
    header("Location: add_user.php"); // Redirige a la página de gestión de usuarios
    exit; // Termina la ejecución del script
}

try {
    // Inicia una transacción
    $conn->begin_transaction();

    // 1. Obtener imágenes para borrarlas después (antes de borrar los registros)
    $stmt = $conn->prepare("SELECT imagen FROM documentos WHERE id_profesor = ?");
    $stmt->bind_param("i", $id_profesor);
    $stmt->execute();
    $result = $stmt->get_result();

    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '../uploads/'; // Asegúrate que esta ruta sea correcta para tu servidor
    $files_to_delete = [];

    while ($row = $result->fetch_assoc()) {
        if (!empty($row['imagen'])) {
            $files_to_delete[] = $uploadDir . $row['imagen'];
        }
    }

    // 2. Eliminar documentos asociados
    $stmt = $conn->prepare("DELETE FROM documentos WHERE id_profesor = ?");
    $stmt->bind_param("i", $id_profesor);
    if (!$stmt->execute()) throw new Exception("Error al eliminar documentos asociados.");

    // 3. Eliminar formación asociada
    $stmt = $conn->prepare("DELETE FROM formacion WHERE id_profesor = ?");
    $stmt->bind_param("i", $id_profesor);
    if (!$stmt->execute()) throw new Exception("Error al eliminar formación asociada.");

    // 4. Desvincular usuarios (ESTA ES LA CORRECCIÓN CLAVE)
    // Pone en NULL el campo id_profesor en la tabla usuarios para evitar el error de restricción
    $stmt = $conn->prepare("UPDATE usuarios SET id_profesor = NULL WHERE id_profesor = ?");
    $stmt->bind_param("i", $id_profesor);
    if (!$stmt->execute()) throw new Exception("Error al desvincular usuarios asociados.");

    // 5. Eliminar al profesor
    $stmt = $conn->prepare("DELETE FROM profesores WHERE id_profesor = ?");
    $stmt->bind_param("i", $id_profesor);
    if (!$stmt->execute()) throw new Exception("Error al eliminar el profesor.");

    // 6. Eliminar archivos físicos del servidor
    foreach ($files_to_delete as $file_path) {
        if (file_exists($file_path) && !unlink($file_path)) {
            error_log("Error eliminando archivo: " . $file_path);
        }
    }

    // Si todo salió bien, confirma los cambios
    $conn->commit();

    $_SESSION['mensaje'] = "Profesor eliminado correctamente.";
    $_SESSION['mensaje_tipo'] = "success";
    header("Location: add_user.php");
    exit;

} catch (Exception $e) {
    // Si algo falla, revierte todos los cambios de la base de datos
    $conn->rollback();
    $_SESSION['mensaje'] = "Error: " . $e->getMessage();
    $_SESSION['mensaje_tipo'] = "error";
    header("Location: add_user.php");
    exit;
}
?>