<?php
session_start();
require_once 'db.php';
global $conn;
date_default_timezone_set('America/Mexico_City'); 

$anio_actual = date("Y");
$anio_limite = $anio_actual;

// Roles autorizados
$allowed_roles = ['administrador', 'jefe', 'coordinador', 'admin', 'visitante'];
$current_role = strtolower($_SESSION['rol'] ?? '');
$is_authorized = isset($_SESSION['usuario_id']) && in_array($current_role, $allowed_roles);

if ($is_authorized) {
    // Consultas para selects
    $materias = $conn->query("SELECT clave_materia, nombre FROM materias ORDER BY clave_materia")->fetch_all(MYSQLI_ASSOC);
    $profesores = $conn->query("SELECT id_profesor, nombre FROM profesores ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
    $grupos = $conn->query("SELECT nombre FROM grupos ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);
} else {
    $materias = $profesores = $grupos = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir Archivo - DRWSC-B</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/js/all.min.js" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="../styles/styles_UploadForm.css">
    <link rel="stylesheet" href="../styles/label.css">
    <link rel="stylesheet" href="../styles/normalize.css">
</head>
<body>
    <button id="sidebar-toggle" class="sidebar-toggle"><i class="fas fa-bars"></i></button>

    <div class="sidebar">
        <div>
            <h2>DRWSC-B</h2>
            <p>Repositorio Sistemas y Computación</p>
        </div>
        <div class="menu">
            <a href="index.php"><i class="fas fa-home"></i> INICIO</a>
            <a href="search_Document.php"><i class="fas fa-search"></i> BUSCAR</a>
            <a href="upload_form.php"><i class="fas fa-upload"></i> SUBIR</a>
        </div>
        <a href="logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> CERRAR</a>
    </div>

    <div class="content">
        <?php if ($is_authorized): ?>
        <div class="upload-box">
            <h2>Subir Archivo</h2>
            <form id="uploadForm" enctype="multipart/form-data">
                <div class="form-container">
                    <div class="form-fields">
                        <div class="input-group">
                            <label for="id_profesor">Nombre del profesor:</label>
                            <select id="id_profesor" name="id_profesor" required>
                                <option value="">Selecciona un profesor</option>
                                <?php foreach ($profesores as $p): ?>
                                    <option value="<?= $p['id_profesor'] ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="input-group">
                            <label>Tipo:</label>
                            <div class="button-group" id="button-group">
                                <button type="button" class="type-button" data-type="acta">Acta</button>
                                <button type="button" class="type-button" data-type="horario">Horario</button>
                                <button type="button" class="type-button" data-type="otros">Otros</button>
                            </div>
                            <input type="hidden" name="tipo_documento" id="tipo_documento" required>

                            <div id="otros-tipo-container" style="display: none; margin-top: 10px;">
                                <label>Especificar tipo:</label>
                                <select id="otros_tipo" name="otros_tipo" class="form-control">
                                    <option value="licenciatura">Licenciatura</option>
                                    <option value="maestria">Maestría</option>
                                    <option value="doctorado">Doctorado</option>
                                    <option value="cursos">Cursos</option>
                                    <option value="otros">Otros</option>
                                </select>
                            </div>

                            <div id="horario-tipo-container" style="display: none; margin-top: 10px;">
                                <label>Tipo de Horario:</label>
                                <select id="horario_tipo" name="horario_tipo" class="form-control">
                                    <option value="clases">Clases</option>
                                    <option value="actividades">Actividades</option>
                                </select>
                            </div>
                        </div>

                        <div class="campos-academicos">
                            <div class="input-group">
                                <label for="periodo">Periodo:</label>
                                <select id="periodo" name="periodo" required>
                                    <option value="">Selecciona un periodo</option>
                                    <option value="ENE-JUN">ENE-JUN</option>
                                    <option value="AGO-DIC">AGO-DIC</option>
                                </select>
                            </div>
                            <div class="input-group">
                                <label for="anio">Año:</label>
                                <select id="anio" name="anio" required>
                                    <?php for ($y = 2010; $y <= $anio_limite; $y++): ?>
                                        <option value="<?= $y ?>" <?= ($y == $anio_actual) ? 'selected' : '' ?>><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div id="materia-grupo-fields">
                                <div class="input-group">
                                    <label for="clave_materia">Clave Materia:</label>
                                    <select id="clave_materia" name="clave_materia" required>
                                        <option value="">Selecciona materia</option>
                                        <?php foreach ($materias as $m): ?>
                                            <option value="<?= $m['clave_materia'] ?>"><?= $m['clave_materia'] ?> - <?= $m['nombre'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="input-group">
                                    <label for="grupo">Grupo:</label>
                                    <select id="grupo" name="grupo" required>
                                        <option value="">Selecciona grupo</option>
                                        <?php foreach ($grupos as $g): ?>
                                            <option value="<?= $g['nombre'] ?>"><?= $g['nombre'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="file-upload">
                        <div class="file-drop-area" id="file-drop-area">
                            <input type="file" id="fileInput" name="imagen" accept="image/*" hidden>
                            <i class="fas fa-upload"></i>
                            <p>Subir archivo o arrastrar</p>
                            <div id="preview-container"></div>
                        </div>
                    </div>
                </div>

                <div class="button-container">
                    <button type="button" id="deletePreviewBtn" style="display:none;">Eliminar</button>
                    <button type="submit" class="button-save">Guardar</button>
                </div>
            </form>
        </div>
        <?php else: ?>
            <div class="error-message"><h2>Acceso Denegado</h2><a href="login.php">Iniciar Sesión</a></div>
        <?php endif; ?>
    </div>

    <script>
    document.addEventListener("DOMContentLoaded", function () {
        const uploadForm = document.getElementById("uploadForm");
        const typeButtons = document.querySelectorAll(".type-button");
        const otrosContainer = document.getElementById("otros-tipo-container");
        const horarioContainer = document.getElementById("horario-tipo-container");
        const academicos = document.querySelector(".campos-academicos");
        const materiaGrupo = document.getElementById("materia-grupo-fields");

        // Lógica de visibilidad de campos
        typeButtons.forEach(btn => {
            btn.addEventListener("click", function() {
                typeButtons.forEach(b => b.classList.remove("active"));
                this.classList.add("active");
                const type = this.dataset.type;
                document.getElementById("tipo_documento").value = type;

                // Reset de visibilidad
                otrosContainer.style.display = (type === 'otros') ? "block" : "none";
                horarioContainer.style.display = (type === 'horario') ? "block" : "none";
                academicos.style.display = (type === 'otros') ? "none" : "block";
                materiaGrupo.style.display = (type === 'horario') ? "none" : "block";

                // Ajustar 'required' dinámicamente
                const isOtros = (type === 'otros');
                const isHorario = (type === 'horario');

                document.getElementById("periodo").required = !isOtros;
                document.getElementById("anio").required = !isOtros;
                document.getElementById("clave_materia").required = (!isOtros && !isHorario);
                document.getElementById("grupo").required = (!isOtros && !isHorario);
            });
        });

        // Evento de envío AJAX
        uploadForm.addEventListener("submit", function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch("upload.php", { method: "POST", body: formData })
            .then(response => response.text()) // Capturamos como texto para depurar si hay errores
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    alert(data.message);
                    if (data.status === 'success') {
                        location.reload(); 
                    }
                } catch (e) {
                    console.error("Error parseando JSON. El servidor envió:", text);
                    alert("⚠️ Error en la respuesta del servidor.");
                }
            })
            .catch(err => {
                console.error(err);
                alert("❌ Error de conexión.");
            });
        });

        // Manejo de Preview (Simplificado)
        const fileInput = document.getElementById("fileInput");
        const fileDrop = document.getElementById("file-drop-area");
        fileDrop.onclick = () => fileInput.click();
        fileInput.onchange = () => {
            const file = fileInput.files[0];
            if(file) {
                const reader = new FileReader();
                reader.onload = e => {
                    document.getElementById("preview-container").innerHTML = `<img src="${e.target.result}" style="max-width:100%">`;
                    document.getElementById("deletePreviewBtn").style.display = "block";
                }
                reader.readAsDataURL(file);
            }
        };
    });
    </script>
</body>
</html>