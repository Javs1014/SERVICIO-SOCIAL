<?php
// Incluye el archivo de conexión a la base de datos
// Este archivo debe contener la configuración de conexión a MySQL
include 'db.php';

// Obtiene el ID del documento desde los parámetros GET de la URL
// Usa el operador de fusión de null (??) para asignar null si no existe el parámetro
$id_documento = $_GET['id'] ?? null;

// Verifica si se proporcionó un ID de documento
if ($id_documento) {
    // Prepara una consulta SQL para obtener la ruta de la imagen del documento
    // Usa una consulta preparada para prevenir inyecciones SQL
    $stmt = $conn->prepare("SELECT imagen FROM documentos WHERE id_documento = ?");
    
    // Asocia el parámetro a la consulta (i = integer)
    $stmt->bind_param("i", $id_documento);
    
    // Ejecuta la consulta preparada
    $stmt->execute();
    
    // Obtiene el resultado de la consulta
    $result = $stmt->get_result();
    
    // Obtiene la fila de resultados como un array asociativo
    $documento = $result->fetch_assoc();
    
    // Cierra el statement para liberar recursos
    $stmt->close();
    
    // Verifica si se encontró el documento en la base de datos
    if ($documento) {
        // Construye la ruta completa al archivo en el servidor
        // $_SERVER['DOCUMENT_ROOT'] da la ruta base del servidor web
        $rutaArchivo = $_SERVER['DOCUMENT_ROOT'] . './uploads/' . $documento['imagen'];
        
        // Verifica si el archivo físico existe en el servidor
        if (file_exists($rutaArchivo)) {
            // Configura las cabeceras HTTP para forzar la descarga del archivo
            
            // Descripción del tipo de transferencia
            header('Content-Description: File Transfer');
            
            // Tipo MIME genérico para archivos binarios
            header('Content-Type: application/octet-stream');
            
            // Cabecera que indica que debe descargarse como archivo adjunto
            // y especifica el nombre del archivo (usa el nombre base del archivo)
            header('Content-Disposition: attachment; filename="'.basename($rutaArchivo).'"');
            
            // Indica que el contenido no debe ser cacheado
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            
            // Indica el tamaño del archivo en bytes
            header('Content-Length: ' . filesize($rutaArchivo));
            
            // Lee el archivo y lo envía al cliente
            readfile($rutaArchivo);
            
            // Termina la ejecución del script
            exit;
        }
    }
}

// Si llega a este punto, significa que hubo un error en la descarga
// Envía un código de estado HTTP 404 (No encontrado)
header("HTTP/1.0 404 Not Found");

// Muestra un mensaje de error al usuario
echo "Archivo no encontrado";
?>