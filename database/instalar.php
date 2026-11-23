<?php
/* ==========================================================
   instalar.php: crea la base de datos database/cinenova.db a partir de
   los dos scripts SQL. Se ejecuta UNA vez, desde la terminal:

       php database/instalar.php

   Primero corre complejo_cines.sql (tablas y datos de prueba) y después
   datos_cartelera.sql (las películas que antes estaban en peliculas.js).
   Si la base ya existe no hace nada, para no borrar tus datos.
   ========================================================== */

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

$destino = __DIR__ . '/cinenova.db';

if (file_exists($destino)) {
    exit("La base ya existe (database/cinenova.db). Si quieres crearla de cero, borra ese archivo y vuelve a ejecutar este script.\n");
}
if (!extension_loaded('pdo_sqlite')) {
    exit("Falta la extensión pdo_sqlite de PHP. Actívala en php.ini (línea extension=pdo_sqlite).\n");
}

try {
    $pdo = new PDO('sqlite:' . $destino);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    foreach (['complejo_cines.sql', 'datos_cartelera.sql'] as $archivo) {
        $pdo->exec(file_get_contents(__DIR__ . '/' . $archivo)); // exec ejecuta todas las sentencias del archivo
        echo "Ejecutado: $archivo\n";
    }
    echo "Listo: se creó database/cinenova.db\n";
} catch (Throwable $error) {
    $pdo = null;             // cierra la conexión antes de borrar el archivo a medio crear
    @unlink($destino);
    exit('Error: ' . $error->getMessage() . "\n");
}
