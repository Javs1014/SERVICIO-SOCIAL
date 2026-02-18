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

// Define roles permitidos para acceder a la eliminación de documentos
$allowed_roles = ['administrador', 'jefe', 'coordinador', 'admin'];
// Obtiene el rol del usuario actual en minúsculas, o cadena vacía si no está definido
$current_role = strtolower($_SESSION['rol'] ?? '');

// Verifica si el usuario está autenticado y tiene un rol permitido
if (empty($_SESSION['usuario_id']) || !in_array($current_role, $allowed_roles)) {
    $_SESSION['mensaje'] = "Acceso denegado: No tienes permisos para eliminar documentos."; // Mensaje de error
    $_SESSION['mensaje_tipo'] = "error"; // Tipo de mensaje
    header("Location: ../search_Document.php"); // Redirige a la página de búsqueda
    exit; // Termina la ejecución del script
}

// Incluye el archivo de conexión a la base de datos
require_once 'db.php';

// Obtiene el ID del documento desde la URL, o null si no está presente
$id_documento = $_GET['id'] ?? null;

// Verifica si se proporcionó un ID de documento
if (!$id_documento) {
    $_SESSION['mensaje'] = "ID de documento no proporcionado."; // Mensaje de error
    $_SESSION['mensaje_tipo'] = "error"; // Tipo de mensaje
    header("Location: ../search_Document.php"); // Redirige a la página de búsqueda
    exit; // Termina la ejecución del script
}

try {
    // Prepara una consulta para obtener la información del documento
    $stmt = $conn->prepare("SELECT imagen FROM documentos WHERE id_documento = ?");
    $stmt->bind_param("i", $id_documento); // Vincula el ID del documento como entero
    $stmt->execute(); // Ejecuta la consulta
    $result = $stmt->get_result(); // Obtiene el resultado

    // Verifica si el documento existe
    if ($result->num_rows === 0) {
        throw new Exception("Documento no encontrado."); // Lanza una excepción si no se encuentra el documento
    }

    $documento = $result->fetch_assoc(); // Obtiene los datos del documento
    $uploadDir = $_SERVER['DOCUMENT_ROOT'] . './uploads/'; // Define el directorio donde están los archivos subidos

    // Prepara una consulta para eliminar el documento de la base de datos
    $stmt = $conn->prepare("DELETE FROM documentos WHERE id_documento = ?");
    $stmt->bind_param("i", $id_documento); // Vincula el ID del documento
    if (!$stmt->execute()) { // Ejecuta la consulta
        throw new Exception("Error al eliminar de la base de datos."); // Lanza una excepción si falla
    }

    // Crea un arreglo para almacenar los archivos que se deben eliminar
    $files_to_delete = [];
    if (!empty($documento['imagen'])) {
        $files_to_delete[] = $uploadDir . $documento['imagen']; // Agrega la ruta del archivo al arreglo
    }

    // Intenta eliminar los archivos físicos del servidor
    foreach ($files_to_delete as $file_path) {
        if (file_exists($file_path) && !unlink($file_path)) {
            error_log("Error eliminando archivo: " . $file_path); // Registra un error si no se puede eliminar el archivo
        }
    }

    // Establece un mensaje de éxito para mostrar al usuario
    $_SESSION['mensaje'] = "Documento eliminado correctamente.";
    $_SESSION['mensaje_tipo'] = "success";
    header("Location: ../search_Document.php"); // Redirige a la página de búsqueda
    exit; // Termina la ejecución del script

} catch (Exception $e) {
    // Captura cualquier excepción y establece un mensaje de error
    $_SESSION['mensaje'] = $e->getMessage();
    $_SESSION['mensaje_tipo'] = "error";
    header("Location: ../search_Document.php"); // Redirige a la página de búsqueda
    exit; // Termina la ejecución del script
}
?>