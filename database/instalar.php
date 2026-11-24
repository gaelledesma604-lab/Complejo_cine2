<?php
/* ==========================================================
   instalar.php: crea la base de datos database/cinenova.db a partir de
   los scripts SQL. Se ejecuta UNA vez, desde la terminal:

       php database/instalar.php

   Corre complejo_cines.sql (tablas y datos de prueba), después
   datos_cartelera.sql (las películas) y por último datos_funciones.sql (horarios de la semana).
   También crea la cuenta admin@cinenova.local con una contraseña aleatoria que muestra al terminar.
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

    foreach (['complejo_cines.sql', 'datos_cartelera.sql', 'datos_funciones.sql'] as $archivo) {
        $pdo->exec(file_get_contents(__DIR__ . '/' . $archivo)); // exec ejecuta todas las sentencias del archivo
        echo "Ejecutado: $archivo\n";
    }

    // Cuenta de administración inicial. La contraseña es aleatoria y se muestra UNA sola vez aquí:
    // así no hay una contraseña fija escrita en el repositorio.
    $password = bin2hex(random_bytes(6));
    $pdo->prepare(
        "INSERT INTO Usuarios (nombre_usuario, correo_usuario, password_hash, ID_Rol)
         VALUES ('admin', 'admin@cinenova.local', :hash, (SELECT ID_Rol FROM Roles WHERE nombre_rol = 'admin'))"
    )->execute([':hash' => password_hash($password, PASSWORD_DEFAULT)]);

    echo "Listo: se creó database/cinenova.db\n\n";
    echo "Cuenta de administrador (guarda esta contraseña, no se vuelve a mostrar):\n";
    echo "  correo:     admin@cinenova.local\n";
    echo "  contraseña: $password\n";
} catch (Throwable $error) {
    $pdo = null;             // cierra la conexión antes de borrar el archivo a medio crear
    @unlink($destino);
    exit('Error: ' . $error->getMessage() . "\n");
}
