<?php
// Inicia una sesión segura si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400,
        'cookie_secure' => true, // Cambiar a true en producción para HTTPS
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);

    if (!isset($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = time();
    } elseif (time() - $_SESSION['CREATED'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['CREATED'] = time();
    }
}

// Registra la sesión para depuración
error_log("Datos de sesión: " . print_r($_SESSION, true));

// Define roles permitidos
$allowed_roles = ['administrador', 'jefe', 'coordinador', 'admin'];
$current_role = strtolower($_SESSION['rol'] ?? '');
$is_logged_in = isset($_SESSION['usuario_id']);
$is_authorized = $is_logged_in && in_array($current_role, $allowed_roles);

// Verifica si el usuario está autenticado y tiene un rol permitido
if (!$is_authorized) {
    $_SESSION['mensaje'] = "Acceso denegado: No tienes permisos para estar aquí.";
    $_SESSION['mensaje_tipo'] = "error";
    header("Location: index.php");
    exit;
}

// Incluye el archivo de conexión a la base de datos
require_once 'db.php';

// Obtiene el ID del usuario a editar
$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Obtiene los datos del usuario
$user = null;
if ($user_id > 0) {
    $stmt = $conn->prepare("SELECT id_usuario, usuario, rol FROM usuarios WHERE id_usuario = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
}

if (!$user) {
    $_SESSION['mensaje'] = "Usuario no encontrado.";
    $_SESSION['mensaje_tipo'] = "error";
    header("Location: add_user.php");
    exit;
}

// Procesa el formulario para actualizar la contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_password'])) {
    $contrasena = trim($_POST['contrasena'] ?? '');

    // Validación
    if (empty($contrasena)) {
        $_SESSION['mensaje'] = "La contraseña es obligatoria.";
        $_SESSION['mensaje_tipo'] = "error";
    } elseif (strlen($contrasena) < 8) {
        $_SESSION['mensaje'] = "La contraseña debe tener al menos 8 caracteres.";
        $_SESSION['mensaje_tipo'] = "error";
    } else {
        try {
            // Hashea la nueva contraseña
            $contrasena_hash = password_hash($contrasena, PASSWORD_BCRYPT);

            // Actualiza la contraseña en la base de datos
            $stmt = $conn->prepare("UPDATE usuarios SET contraseña = ? WHERE id_usuario = ?");
            $stmt->bind_param("si", $contrasena_hash, $user_id);
            if ($stmt->execute()) {
                $_SESSION['mensaje'] = "Contraseña actualizada correctamente.";
                $_SESSION['mensaje_tipo'] = "success";
                header("Location: add_user.php");
                exit;
            } else {
                throw new Exception("Error al actualizar la contraseña.");
            }
            $stmt->close();
        } catch (Exception $e) {
            $_SESSION['mensaje'] = $e->getMessage();
            $_SESSION['mensaje_tipo'] = "error";
        }
    }
    header("Location: edit_user.php?id=$user_id");
    exit;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Contraseña</title>
    <link rel="stylesheet" href="../styles/styles_addUser.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js"></script>
</head>
<body>
    <button id="sidebar-toggle" class="sidebar-toggle">
        <i class="fas fa-bars"></i>
    </button>

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

    <main class="content">
        <h1>Editar Contraseña de <?= htmlspecialchars($user['usuario']) ?></h1>

        <?php if (isset($_SESSION['mensaje'])): ?>
            <div class="mensaje <?= $_SESSION['mensaje_tipo'] === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($_SESSION['mensaje']) ?>
            </div>
            <?php unset($_SESSION['mensaje'], $_SESSION['mensaje_tipo']); ?>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="update_password" value="1">
            <div class="form-group">
                <label for="contrasena">Nueva Contraseña:</label>
                <input type="password" id="contrasena" name="contrasena" required>
            </div>
            <button type="submit" class="btn">Actualizar Contraseña</button>
            <a href="add_user.php" class="btn">Cancelar</a>
        </form>
    </main>

    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const sidebarToggle = document.getElementById("sidebar-toggle");
            const sidebar = document.querySelector(".sidebar");
            const content = document.querySelector(".content");

            if (sidebarToggle) {
                sidebarToggle.addEventListener("click", function () {
                    sidebar.classList.toggle("collapsed");
                    content.classList.toggle("collapsed");
                });
            }
        });
    </script>
</body>
</html>