<?php
// Inicia la sesión para poder almacenar datos de usuario
session_start();
// Incluye el archivo de conexión a la base de datos
include 'db.php';

// Constantes para el control de intentos fallidos
define('MAX_INTENTOS', 5); // Máximo número de intentos permitidos
define('TIEMPO_BLOQUEO_MINUTOS', 15); // Tiempo de bloqueo en minutos después de exceder intentos

// Verifica si se ha enviado el formulario de login
if (isset($_POST['submit'])) {
    // Obtiene y limpia el nombre de usuario
    $usuario = trim($_POST['usuario']);
    // Obtiene la contraseña sin modificar (se verificará contra hash)
    $contraseña = $_POST['contraseña'];

    // Prepara la consulta SQL para buscar el usuario
    $sql = "SELECT * FROM usuarios WHERE usuario = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $usuario); // "s" indica que es un string
    $stmt->execute();
    $result = $stmt->get_result();

    // Verifica si se encontró el usuario en la base de datos
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc(); // Obtiene los datos del usuario

        // Comprueba si la cuenta está temporalmente bloqueada
        if ($row['bloqueado_until'] !== null && strtotime($row['bloqueado_until']) > time()) {
            // Calcula el tiempo restante de bloqueo
            $error = "Cuenta bloqueada hasta " . date("H:i:s", strtotime($row['bloqueado_until']));
        } else {
            // Verifica la contraseña proporcionada contra el hash almacenado
            if (password_verify($contraseña, $row['contraseña'])) {
                // Si la contraseña es correcta, reinicia los intentos fallidos
                $sqlReset = "UPDATE usuarios SET intentos_fallidos = 0, bloqueado_until = NULL WHERE id_usuario = ?";
                $stmtReset = $conn->prepare($sqlReset);
                $stmtReset->bind_param("i", $row['id_usuario']);
                $stmtReset->execute();

                // Establece las variables de sesión con los datos del usuario
                $_SESSION['usuario_id'] = $row['id_usuario'];
                $_SESSION['usuario'] = $row['usuario']; 
                $_SESSION['rol'] = $row['rol']; 
                
                // Redirige al usuario a la página principal
                header("Location: index.php");
                exit();
            } else {
                // Si la contraseña es incorrecta, incrementa el contador de intentos fallidos
                $nuevos_intentos = $row['intentos_fallidos'] + 1;
                $bloqueo = null; // Por defecto no hay bloqueo

                // Verifica si se excedió el número máximo de intentos
                if ($nuevos_intentos >= MAX_INTENTOS) {
                    // Calcula la fecha/hora de desbloqueo
                    $bloqueo = date("Y-m-d H:i:s", strtotime("+".TIEMPO_BLOQUEO_MINUTOS." minutes"));
                    $error = "Demasiados intentos. Cuenta bloqueada durante ".TIEMPO_BLOQUEO_MINUTOS." minutos.";
                } else {
                    $error = "Contraseña incorrecta. Intento $nuevos_intentos de ".MAX_INTENTOS;
                }

                // Actualiza el registro del usuario con los nuevos intentos y posible bloqueo
                $sqlUpdate = "UPDATE usuarios SET intentos_fallidos = ?, bloqueado_until = ? WHERE id_usuario = ?";
                $stmtUpdate = $conn->prepare($sqlUpdate);
                $stmtUpdate->bind_param("isi", $nuevos_intentos, $bloqueo, $row['id_usuario']);
                $stmtUpdate->execute();
            }
        }
    } else {
        $error = "Usuario no encontrado";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>

    <!-- Enlaces a hojas de estilo -->
    <link rel="stylesheet" href="../styles/styles_Login.css">
    <link rel="stylesheet" href="../styles/normalize.css">
</head>
<body>
    <!-- Contenedor principal del formulario -->
    <div class="container">
        <!-- Encabezado con mensaje de bienvenida -->
        <div class="header">Bienvenido<br><small>Por favor inicie sesión para continuar</small></div>
        
        <!-- Contenedor del formulario -->
        <div class="form-container">
            <!-- Muestra mensajes de error si existen -->
            <?php if (isset($error)): ?>
                <p class="error"><?php echo $error; ?></p>
            <?php endif; ?>
        
            <!-- Formulario de login -->
            <form action="login.php" method="POST">
                <!-- Campo para el nombre de usuario -->
                <div class="form-group">
                    <label for="usuario">Usuario:</label>
                    <input type="text" name="usuario" placeholder="Ingrese su usuario" required>
                </div>

                <!-- Campo para la contraseña -->
                <div class="form-group">
                    <label for="contraseña">Contraseña:</label>
                    <input type="password" name="contraseña" placeholder="Ingrese su contraseña" required>
                </div>

                <!-- Botón de envío del formulario -->
                <div class="form-group">
                    <button type="submit" name="submit">Iniciar Sesión →</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>