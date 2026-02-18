<?php
// ==============================================
// CONFIGURACIÓN MEJORADA DE SESIÓN Y CONEXIÓN BD
// ==============================================

/**
 * Configuración del entorno (development/production)
 * - 'development' muestra errores detallados (usar en desarrollo)
 * - 'production' oculta errores internos (usar en servidor real)
 * Esto afecta cómo se muestran los mensajes de error al usuario
 */
define('ENVIRONMENT', 'development');

// ======================
// 1. CONFIGURACIÓN SESIÓN
// ======================

// Verifica si la sesión no está activa antes de iniciarla
if (session_status() === PHP_SESSION_NONE) {
    // Inicia sesión con parámetros de seguridad mejorados
    session_start([
        'name' => 'SESSID', // Nombre personalizado para la cookie (no el predeterminado PHPSESSID)
        'cookie_lifetime' => 86400, // 24 horas de duración de la cookie (en segundos)
        'cookie_secure' => true,    // Solo envía la cookie sobre conexiones HTTPS
        'cookie_httponly' => true,  // Previene acceso a la cookie via JavaScript (protección XSS)
        'cookie_samesite' => 'Strict', // Política SameSite estricta (protección CSRF)
        'use_strict_mode' => true,  // Habilita modo estricto para IDs de sesión
        'gc_maxlifetime' => 1800    // 30 minutos de vida de los datos de sesión en el servidor
    ]);

    // Protección contra session fixation (ataque donde un atacante fija el ID de sesión)
    if (!isset($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = time(); // Marca de tiempo de creación de sesión
        session_regenerate_id(true); // Genera un nuevo ID de sesión y elimina el anterior
    }
    // Regeneración periódica del ID de sesión (cada 30 minutos)
    elseif (time() - $_SESSION['CREATED'] > 1800) {
        session_regenerate_id(true); // true elimina el archivo de sesión antiguo
        $_SESSION['CREATED'] = time(); // Actualiza el timestamp
    }
}

// ==========================
// 2. CONTROL DE INACTIVIDAD
// ==========================

$inactividad = 900; // 15 minutos de inactividad permitidos (en segundos)

// Verifica si hay registro de última actividad
if (isset($_SESSION['LAST_ACTIVITY'])) {
    $tiempo_inactivo = time() - $_SESSION['LAST_ACTIVITY'];

    // Si excede el tiempo permitido de inactividad
    if ($tiempo_inactivo > $inactividad) {
        // Limpieza segura de la sesión
        session_unset();    // Elimina todas las variables de sesión
        session_destroy();  // Destruye completamente la sesión
        // Elimina la cookie de sesión (configura tiempo en pasado)
        setcookie('SESSID', '', time() - 3600, '/');

        // Manejo de respuesta según el tipo de solicitud
        if (php_sapi_name() !== 'cli') { // Solo si no es interfaz de línea de comandos
            if (strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
                // Si el cliente acepta JSON, devuelve respuesta JSON
                header('Content-Type: application/json');
                die(json_encode([
                    'status' => 'error',
                    'message' => 'Sesión expirada por inactividad',
                    'redirect' => '/login.php'
                ]));
            } else {
                // Redirección normal para navegadores
                header('Location: /login.php?timeout=1');
                exit;
            }
        }
    }
}

// Actualiza la marca de tiempo de última actividad en cada carga
$_SESSION['LAST_ACTIVITY'] = time();

// ========================
// 3. CONEXIÓN BASE DE DATOS
// ========================

/*
 * Configuración de conexión a la base de datos
 * EN PRODUCCIÓN:
 * - Usar credenciales con permisos mínimos necesarios
 * - Almacenar credenciales en variables de entorno
 * - Nunca usar usuario 'root' en producción
 */
$db_config = [
    'host' => '189.199.68.86',  // Servidor de base de datos
    'username' => 'admindocs',   // Usuario (¡CAMBIAR en producción!)
    'password' => 'D0c5157_2025',       // Contraseña (¡DEBE ser compleja en producción!)
    'database' => 'docssist', // Nombre de la base de datos
    'port' => 3306,         // Puerto MySQL default
    'charset' => 'utf8mb4'  // Charset para soportar todos los caracteres Unicode
];

try {
    // Crear nueva conexión MySQLi (orientado a objetos)
    $conn = new mysqli(
        $db_config['host'],
        $db_config['username'],
        $db_config['password'],
        $db_config['database'],
        $db_config['port']
    );

    // Verificar si hubo error en la conexión
    if ($conn->connect_error) {
        throw new RuntimeException(
            "Error de conexión MySQL: [" . $conn->connect_errno . "] " .
            $conn->connect_error
        );
    }

    // Configurar el charset de la conexión para prevenir inyecciones
    if (!$conn->set_charset($db_config['charset'])) {
        throw new RuntimeException(
            "Error al configurar charset: " . $conn->error
        );
    }

    // Configurar zona horaria para las fechas en la base de datos
    $conn->query("SET time_zone = '-06:00'"); // Ajustar según la zona horaria necesaria
} catch (RuntimeException $e) {
    // Registro del error en logs del servidor
    error_log('Error de base de datos: ' . $e->getMessage());

    // Manejo diferente según el entorno
    if (ENVIRONMENT === 'development') {
        // En desarrollo: mostrar error detallado
        die("Error de sistema: " . $e->getMessage());
    } else {
        // En producción: mensaje genérico al usuario
        die("Error en el sistema. Por favor intente más tarde.");
    }
}

// ===================================
// 4. FUNCIONES ÚTILES PARA LA SESIÓN
// ===================================

/**
 * Verifica si el usuario tiene un rol específico
 * 
 * @param string $rol_requerido El rol que se quiere verificar
 * @return bool True si el usuario tiene el rol, False si no
 */
function tiene_rol($rol_requerido)
{
    // Obtiene los roles del usuario desde la sesión (array vacío si no existe)
    $roles_usuario = $_SESSION['roles'] ?? [];
    // Verifica si el rol requerido está en el array de roles del usuario
    return in_array($rol_requerido, $roles_usuario);
}

/**
 * Cierra la sesión de manera segura y completa
 * - Limpia todas las variables de sesión
 * - Destruye la sesión
 * - Elimina la cookie de sesión del cliente
 */
function cerrar_sesion()
{
    // Vacía el array de sesión
    $_SESSION = [];
    
    // Si está configurado el uso de cookies, elimina la cookie de sesión
    if (ini_get("session.use_cookies")) {
        // Obtiene los parámetros de la cookie de sesión
        $params = session_get_cookie_params();
        // Establece la cookie con tiempo expirado (en el pasado)
        setcookie(
            session_name(), // Nombre de la cookie de sesión
            '',             // Valor vacío
            time() - 42000, // Tiempo en pasado para expirar
            $params["path"],    // Misma ruta original
            $params["domain"],  // Mismo dominio original
            $params["secure"],  // Mismo parámetro secure
            $params["httponly"] // Mismo parámetro httponly
        );
    }
    // Destruye la sesión en el servidor
    session_destroy();
}