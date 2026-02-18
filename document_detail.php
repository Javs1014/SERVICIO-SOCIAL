<?php
// Inicia una sesión segura si no está activa
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_lifetime' => 86400, // Establece la duración de la cookie de sesión a 24 horas
        'cookie_secure' => true, // Cambiar a true en producción para HTTPS
        'cookie_httponly' => true, // Previene acceso a la cookie desde JavaScript
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
// Define roles permitidos para acceder a la gestión de usuarios y verifica el rol del usuario actual
$allowed_roles = ['administrador', 'jefe', 'coordinador', 'admin'];
$current_role = strtolower($_SESSION['rol'] ?? '');
$is_logged_in = isset($_SESSION['usuario_id']);
$is_authorized = $is_logged_in && in_array($current_role, $allowed_roles);

// Verifica si el usuario está autenticado y tiene un rol permitido
if (empty($_SESSION['usuario_id']) || !in_array($current_role, $allowed_roles)) {
    $_SESSION['mensaje'] = "Acceso denegado: No tienes permisos para estar aquí.";
    $_SESSION['mensaje_tipo'] = "error";
    header("Location: index.php"); // Redirige al inicio si no tiene permisos
    exit; // Termina la ejecución del script
}
// Incluye el archivo de conexión a la base de datos
include './db.php';

// Obtiene el ID del documento desde la URL, o null si no está presente
$id_documento = $_GET['id'] ?? null;

// Si se proporcionó un ID de documento, realiza la consulta
if ($id_documento) {
    // Prepara una consulta para obtener los detalles del documento y el nombre del profesor
    $stmt = $conn->prepare("SELECT 
        d.*, 
        p.nombre AS profesor_nombre 
        FROM documentos d
        JOIN profesores p ON d.id_profesor = p.id_profesor
        WHERE d.id_documento = ?");
    
    // Vincula el ID del documento como entero para prevenir inyección SQL
    $stmt->bind_param("i", $id_documento);
    
    // Ejecuta la consulta
    $stmt->execute();
    
    // Obtiene el resultado
    $result = $stmt->get_result();
    
    // Almacena los datos del documento como un arreglo asociativo
    $documento = $result->fetch_assoc();
    
    // Cierra el statement
    $stmt->close();
}

// Cierra la conexión a la base de datos
$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"> <!-- Define la codificación de caracteres -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Configura el diseño responsivo -->
    <title>Detalle del Documento</title> <!-- Título de la página -->
    <!-- Carga Font Awesome para iconos -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js" crossorigin="anonymous"></script>
    <!-- Enlaces a hojas de estilo -->
    <link rel="stylesheet" href="../styles/styles_documentdetail.css">
    <link rel="stylesheet" href="../styles/normalize.css">
</head>
<body>
    <!-- Botón de hamburguesa para mostrar/ocultar la barra lateral -->
    <button id="sidebar-toggle" class="sidebar-toggle">
        <i class="fas fa-bars"></i> <!-- Icono de hamburguesa -->
    </button>

    <!-- Barra lateral de navegación -->
    <div class="sidebar" id="sidebar">
        <div>
            <h2>DRWSC-B</h2> <!-- Título del sistema -->
            <p>Desarrollo de repositorio web de <br>sistemas y computación beta</p> <!-- Descripción del sistema -->
        </div>
        <div class="menu">
            <a href="index.php"><i class="fas fa-home"></i> INICIO</a> <!-- Enlace a la página principal -->
            <a href="search_Document.php"><i class="fas fa-search"></i> BUSCAR</a> <!-- Enlace a la búsqueda -->
            <a href="upload_form.php"><i class="fas fa-upload"></i> SUBIR</a> <!-- Enlace para subir archivos -->
            <a href="add_user.php"><i class="fas fa-folder-open"></i>GESTION</a> <!-- Enlace a gestión de usuarios/profesores -->
            <a href="all_Profiles.php"><i class="fas fa-user"></i> PERFILES</a> <!-- Enlace a los perfiles -->
        </div>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> CERRAR</a> <!-- Enlace para cerrar sesión -->
    </div>

    <!-- Contenido principal -->
    <div class="content">
        <div class="document-detail-container">
            <?php if ($documento): ?> <!-- Verifica si se encontró el documento -->
                <!-- Enlace para regresar a la página de búsqueda -->
                <a href="search_Document.php" class="back-arrow">
                    <i class="fas fa-arrow-left"></i> <!-- Icono de flecha hacia atrás -->
                </a>

                <h2>Detalles del Documento</h2> <!-- Título de la sección -->

                <div class="document-content">
                    <!-- Sección con la información del documento -->
                    <div class="document-info">
                        <!-- Muestra los detalles del documento, escapados para prevenir XSS -->
                        <p><strong>Profesor:</strong> <?= htmlspecialchars($documento['profesor_nombre']) ?></p>
                        <p><strong>Tipo:</strong> <?= htmlspecialchars($documento['tipo_documento']) ?></p>
                        <p><strong>Periodo:</strong> <?= htmlspecialchars($documento['periodo']) ?></p>
                        <p><strong>Clave:</strong> <?= htmlspecialchars($documento['clave_materia']) ?></p>
                        <p><strong>Grupo:</strong> <?= htmlspecialchars($documento['grupo']) ?></p>
                        <p><strong>Año:</strong> <?= htmlspecialchars($documento['anio']) ?></p>
                    </div>
                    
                    <!-- Sección para visualizar el documento o imagen -->
                    <div class="document-image">
                        <?php
                        // Construye la ruta completa del archivo
                        $rutaImagen = '../uploads/' . $documento['imagen'];
                        // Obtiene la extensión del archivo en minúsculas
                        $extension = strtolower(pathinfo($documento['imagen'], PATHINFO_EXTENSION));
                        
                        // Muestra el contenido según el tipo de archivo
                        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                            <!-- Muestra imágenes soportadas -->
                            <img src="<?= $rutaImagen ?>" alt="Documento" class="zoomable-image" id="documentImage">
                        <?php elseif ($extension === 'pdf'): ?>
                            <!-- Muestra PDFs usando un elemento embed -->
                            <embed src="<?= $rutaImagen ?>" type="application/pdf" width="100%" height="600px">
                        <?php else: ?>
                            <!-- Muestra un icono genérico para otros tipos de archivo -->
                            <div class="file-preview">
                                <i class="fas fa-file-alt fa-5x"></i>
                                <p>Documento: <?= htmlspecialchars($documento['imagen']) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Modal para vista ampliada de imágenes -->
                    <div id="imageModal" class="modal">
                        <span class="close" title="Cerrar (Esc)">×</span>
                        <div class="modal-content-container">
                            <img class="modal-content" id="modalImage">
                        </div>
                        <div class="caption"></div>
                    </div>
                </div>

                <!-- Acciones disponibles para el documento -->
                <div class="document-actions">
                    <!-- Enlace para descargar el documento -->
                    <a href="../download.php?id=<?= $id_documento ?>" class="download-btn">
                        <i class="fas fa-download"></i> Descargar documento
                    </a>
                    
                    <!-- Botón de eliminación, visible solo si el usuario ha iniciado sesión -->
                    <button class="delete-btn" id="deleteBtn" style="display: <?= isset($_SESSION['rol']) ? 'block' : 'none' ?>;">
                        <i class="fas fa-trash-alt"></i> Eliminar documento
                    </button>

                    <!-- Modal de confirmación para eliminación -->
                    <div id="confirmModal" class="confirm-modal" style="display: none;">
                        <div class="confirm-content">
                            <h3>¿Estás seguro de eliminar este documento?</h3>
                            <p>Esta acción no se puede deshacer.</p>
                            <div class="confirm-buttons">
                                <button class="confirm-btn confirm-delete" id="confirmDelete">Eliminar</button>
                                <button class="confirm-btn confirm-cancel" id="confirmCancel">Cancelar</button>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- Mensaje de error si no se encuentra el documento -->
                <p class="error-message">❌ Documento no encontrado.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- JavaScript para funcionalidades interactivas -->
    <script>
        // Controla el mostrar/ocultar de la barra lateral
        document.getElementById("sidebar-toggle").addEventListener("click", function () {
            document.getElementById("sidebar").classList.toggle("collapsed"); // Alterna la clase 'collapsed' en la barra lateral
            document.querySelector(".content").classList.toggle("collapsed"); // Alterna la clase 'collapsed' en el contenido
        });

        // Configura la funcionalidad del modal para ampliar imágenes
        document.addEventListener("DOMContentLoaded", function() {
            const modal = document.getElementById("imageModal");
            const modalImg = document.getElementById("modalImage");
            const captionText = document.querySelector(".caption");
            const documentImage = document.getElementById("documentImage");
            const closeBtn = document.querySelector(".close");

            // Función para abrir el modal con la imagen ampliada
            function openModal() {
                modal.style.display = "block"; // Muestra el modal
                modalImg.src = this.src; // Establece la fuente de la imagen
                captionText.innerHTML = this.alt; // Establece el texto de la descripción
                document.body.style.overflow = "hidden"; // Deshabilita el desplazamiento de la página
            }

            // Función para cerrar el modal
            function closeModal() {
                modal.style.display = "none"; // Oculta el modal
                document.body.style.overflow = "auto"; // Habilita el desplazamiento
                modalImg.style.transform = "scale(1)"; // Restablece el zoom
                scale = 1; // Restablece la escala
            }

            // Configura eventos solo si existe la imagen del documento
            if (documentImage) {
                // Abre el modal al hacer clic en la imagen
                documentImage.addEventListener('click', openModal);
                
                // Cierra el modal al hacer clic en el botón de cerrar
                closeBtn.addEventListener('click', closeModal);
                
                // Cierra el modal al hacer clic fuera del contenido
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        closeModal();
                    }
                });
                
                // Cierra el modal con la tecla Escape
                document.addEventListener('keydown', function(e) {
                    if (e.key === "Escape" && modal.style.display === "block") {
                        closeModal();
                    }
                });

                // Implementa la funcionalidad de zoom con la rueda del ratón
                let scale = 1;
                modalImg.onwheel = function(e) {
                    e.preventDefault(); // Evita el comportamiento de desplazamiento predeterminado
                    const delta = e.deltaY || e.detail || e.wheelDelta;
                    
                    // Ajusta la escala según la dirección del desplazamiento
                    if (delta < 0) {
                        scale *= 1.1; // Zoom in
                    } else {
                        scale /= 1.1; // Zoom out
                    }
                    
                    // Limita la escala entre 0.5 y 5
                    scale = Math.min(Math.max(0.5, scale), 5);
                    this.style.transform = `scale(${scale})`; // Aplica la escala a la imagen
                };

                // Restablece el zoom al hacer doble clic
                modalImg.ondblclick = function() {
                    scale = 1;
                    this.style.transform = `scale(${scale})`; // Restablece la escala
                };
            }
        });

        // Configura la funcionalidad del modal de confirmación de eliminación
        document.addEventListener('DOMContentLoaded', function() {
            const deleteBtn = document.getElementById('deleteBtn');
            const confirmModal = document.getElementById('confirmModal');
            const confirmDelete = document.getElementById('confirmDelete');
            const confirmCancel = document.getElementById('confirmCancel');

            // Muestra el modal de confirmación al hacer clic en el botón de eliminar
            if (deleteBtn) {
                deleteBtn.addEventListener('click', function(e) {
                    e.preventDefault(); // Evita el comportamiento predeterminado
                    confirmModal.style.display = "block"; // Muestra el modal
                    document.body.style.overflow = "hidden"; // Deshabilita el desplazamiento
                });
            }

            // Cierra el modal al hacer clic en "Cancelar"
            if (confirmCancel) {
                confirmCancel.addEventListener('click', function() {
                    confirmModal.style.display = "none"; // Oculta el modal
                    document.body.style.overflow = "auto"; // Habilita el desplazamiento
                });
            }

            // Cierra el modal al hacer clic fuera del contenido
            if (confirmModal) {
                confirmModal.addEventListener('click', function(e) {
                    if (e.target === confirmModal) {
                        confirmModal.style.display = "none"; // Oculta el modal
                        document.body.style.overflow = "auto"; // Habilita el desplazamiento
                    }
                });
            }

            // Redirige al script de eliminación al confirmar
            if (confirmDelete) {
                confirmDelete.addEventListener('click', function() {
                    window.location.href = "../delete_document.php?id=<?= $id_documento ?>"; // Redirige a la página de eliminación
                });
            }

            // Cierra el modal con la tecla Escape
            document.addEventListener('keydown', function(e) {
                if (e.key === "Escape" && confirmModal.style.display === "block") {
                    confirmModal.style.display = "none"; // Oculta el modal
                    document.body.style.overflow = "auto"; // Habilita el desplazamiento
                }
            });
        });
    </script>
</body>
</html>