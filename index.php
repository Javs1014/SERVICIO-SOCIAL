<?php
// Iniciar sesión
session_start();

// Incluir conexión a la base de datos
require_once 'db.php';
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Verificar si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit();
}

// Define roles permitidos para acceder a la gestión de usuarios y verifica el rol del usuario actual
$allowed_roles = ['administrador', 'jefe', 'coordinador', 'admin', 'visitante'];
$current_role = strtolower($_SESSION['rol'] ?? '');
$is_logged_in = isset($_SESSION['usuario_id']);
$is_authorized = $is_logged_in && in_array($current_role, $allowed_roles);

// Verifica si el usuario está autenticado y tiene un rol permitido
if (!$is_authorized) {
    $_SESSION['mensaje'] = "Acceso denegado: No tienes permisos para estar aquí.";
    $_SESSION['mensaje_tipo'] = "error";
    header("Location: login.php");
    exit;
}

// Obtener la URL solicitada
$request = isset($_GET['url']) ? rtrim($_GET['url'], '/') : 'home';

// Enrutador
switch ($request) {
    case 'home':
        // Renderizar la página de inicio (HTML de abajo)
        break;
    case 'auth':
        require 'login.php';
        exit;
    case 'search':
        require 'search_Document.php';
        exit;
    case 'upload':
        require 'upload_form.php';
        exit;
    case 'manage':
        require 'add_user.php';
        exit;
    case 'profiles':
        require 'all_Profiles.php';
        exit;
    case 'logout':
        require 'logout.php';
        exit;
    case preg_match('/^doc\/([a-f0-9]{64})$/', $request, $matches) ? $request : false:
        $_GET['token'] = $matches[1];
        require 'serve_file.php';
        exit;
    default:
        http_response_code(404);
        echo "Página no encontrada";
        exit;
}

// Manejar POST para upload.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $request === 'upload') {
    require 'upload.php';
    exit;
}

// Cerrar conexión
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Gestión de Imágenes</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js" crossorigin="anonymous"></script>
    
    <!-- Archivo CSS externo -->
    <link rel="stylesheet" href="../styles/styles_Index.css">
    <link rel="stylesheet" href="../styles/normalize.css">
 
</head>
<body>
    <div class="sidebar">
        <div>
            <h2>DRWSC-B</h2>
            <p>Desarrollo de repositorio web de <br>sistemas y computación beta</p>
        </div>
        <div class="menu">
            <a href="index.php"><i class="fas fa-home"></i> INICIO</a>
            <a href="search_Document.php"><i class="fas fa-search"></i> BUSCAR</a>
            <a href="upload_form.php"><i class="fas fa-upload"></i> SUBIR</a>
            <a href="add_user.php"><i class="fas fa-folder-open"></i>GESTION</a>
            <a href="all_Profiles.php"><i class="fas fa-user"></i> PERFILES</a>
        </div>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> CERRAR</a>
    </div>
    
    <div class="content">
        <div class="presentation">
            <h1>
                <img src="logo/logoDos.png" alt="Logo ITP" class="logo">
                Tecnológico Nacional de México
                <img src="logo/logoUno.png" alt="Logo TecNM" class="logo">
            </h1>
            <h2>Instituto Tecnológico de Puebla</h2>
            <p class="highlight">Departamento de Sistemas y Computación</p>
            <p>Ingeniería en Tecnologías de la Información y Comunicaciones</p>
            <div class="sub-box">
                <p><strong>Sistema de Gestión Documental para el Control y Seguimiento de Documentos Académicos</strong></p>
                <p>Desarrollo de repositorio web de sistemas y computación beta</p>
            </div>


            <div style="text-align: center;">
                <a href="Manual_de_usuario.pdf" download class="download-button">
                    <i class="fas fa-file-download"></i> Descargar Manual de Usuario
                </a>
            </div>

        </div>
        


    </div>
</body>
</html>
