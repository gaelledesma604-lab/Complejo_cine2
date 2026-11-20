<?php
/* ==========================================================
   conexion.php: conecta PHP con la base de datos SQLite.
   Todos los demás archivos PHP piden la conexión llamando
   a la función conectar(); nadie más abre la base por su cuenta.
   ========================================================== */

// Ruta del archivo de la base. __DIR__ es la carpeta de ESTE archivo (php/),
// por eso subimos un nivel (..) y entramos a db/.
// El archivo .db no se sube a GitHub (está en .gitignore): se crea con db/instalar.php
const RUTA_DB = __DIR__ . '/../database/complejo_cine.sql';

/* Devuelve la conexión (objeto PDO). La primera vez la crea y las siguientes
   reutiliza la misma, gracias a "static". */
function conectar(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    // Mensajes claros para los dos problemas más comunes al empezar
    if (!extension_loaded('pdo_sqlite')) {
        http_response_code(500);
        exit('Falta la extensión pdo_sqlite de PHP. Actívala en php.ini (línea extension=pdo_sqlite).');
    }
    if (!is_file(RUTA_DB)) {
        http_response_code(500);
        exit('Todavía no existe la base de datos. Créala ejecutando en la terminal: php db/instalar.php');
    }

    $pdo = new PDO('sqlite:' . RUTA_DB);
    // Si una consulta falla, PHP lanza una excepción en vez de seguir en silencio
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Cada fila llega como arreglo asociativo: $fila['titulo']
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // SQLite NO controla las claves foráneas si no se le pide en cada conexión
    $pdo->exec('PRAGMA foreign_keys = ON');

    return $pdo;
}
