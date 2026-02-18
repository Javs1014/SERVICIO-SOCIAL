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

// Define roles permitidos para acceder a la eliminación de usuarios
$allowed_roles = ['administrador', 'jefe', 'coordinador', 'admin'];
// Obtiene el rol del usuario actual en minúsculas, o cadena vacía si no está definido
$current_role = strtolower($_SESSION['rol'] ?? '');

// Verifica si el usuario está autenticado y tiene un rol permitido
if (empty($_SESSION['usuario_id']) || !in_array($current_role, $allowed_roles)) {
    $_SESSION['mensaje'] = "Acceso denegado: No tienes permisos para eliminar usuarios."; // Mensaje de error
    $_SESSION['mensaje_tipo'] = "error"; // Tipo de mensaje
    header("Location: add_user.php"); // Redirige a la página de gestión de usuarios
    exit; // Termina la ejecución del script
}

// Incluye el archivo de conexión a la base de datos
require_once 'db.php';

// Obtiene el ID del usuario desde la URL, o null si no está presente
$id_usuario = $_GET['id'] ?? null;

// Verifica si se proporcionó un ID de usuario
if (!$id_usuario) {
    $_SESSION['mensaje'] = "ID de usuario no proporcionado."; // Mensaje de error
    $_SESSION['mensaje_tipo'] = "error"; // Tipo de mensaje
    header("Location: add_user.php"); // Redirige a la página de gestión de usuarios
    exit; // Termina la ejecución del script
}

try {
    // Prepara una consulta para verificar si el usuario existe
    $stmt = $conn->prepare("SELECT id_usuario FROM usuarios WHERE id_usuario = ?");
    $stmt->bind_param("i", $id_usuario); // Vincula el ID del usuario como entero
    $stmt->execute(); // Ejecuta la consulta
    if ($stmt->get_result()->num_rows === 0) {
        throw new Exception("Usuario no encontrado."); // Lanza una excepción si el usuario no existe
    }

    // Prepara una consulta para eliminar el usuario de la base de datos
    $stmt = $conn->prepare("DELETE FROM usuarios WHERE id_usuario = ?");
    $stmt->bind_param("i", $id_usuario); // Vincula el ID del usuario
    if (!$stmt->execute()) { // Ejecuta la consulta
        throw new Exception("Error al eliminar el usuario."); // Lanza una excepción si falla
    }

    // Establece un mensaje de éxito para mostrar al usuario
    $_SESSION['mensaje'] = "Usuario eliminado correctamente.";
    $_SESSION['mensaje_tipo'] = "success";
    header("Location: add_user.php"); // Redirige a la página de gestión de usuarios
    exit; // Termina la ejecución del script

} catch (Exception $e) {
    // Captura cualquier excepción y establece un mensaje de error
    $_SESSION['mensaje'] = $e->getMessage();
    $_SESSION['mensaje_tipo'] = "error";
    header("Location: add_user.php"); // Redirige a la página de gestión de usuarios
    exit; // Termina la ejecución del script
}
?>