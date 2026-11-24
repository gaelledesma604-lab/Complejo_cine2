<?php
/* ==========================================================
   instalar.php: crea la base de datos database/cinenova.db a partir de
   los scripts SQL. Se ejecuta UNA vez, desde la terminal:

       php database/instalar.php

   Corre complejo_cines.sql (tablas y datos base), importa las películas del informe del INCAA
   (database/informe_semanal.csv, ver importar_incaa.php) y por último datos_funciones.sql (horarios de la semana).
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

    $ejecutar = function (string $archivo) use ($pdo) {
        $pdo->exec(file_get_contents(__DIR__ . '/' . $archivo)); // exec ejecuta todas las sentencias del archivo
        echo "Ejecutado: $archivo\n";
    };

    $ejecutar('complejo_cines.sql');

    // Las películas salen del informe semanal del INCAA, no están escritas a mano. Si falta el archivo o
    // no se puede leer, la instalación sigue igual (con la cartelera vacía) y se avisa cómo completarla.
    require_once __DIR__ . '/importar_incaa.php';
    if (is_file(INFORME_LOCAL)) {
        try {
            echo "Importando películas de database/informe_semanal.csv\n";
            incaa_mostrar(incaa_importar($pdo, incaa_leer_informe(INFORME_LOCAL)));
        } catch (Throwable $error) {
            echo 'AVISO: no se pudieron importar las películas (' . $error->getMessage() . ")\n";
        }
    } else {
        echo "AVISO: no hay películas cargadas. Copia el CSV del Informe Semanal del INCAA en database/informe_semanal.csv\n";
        echo "       y ejecuta: php database/importar_incaa.php\n";
    }

    $ejecutar('datos_funciones.sql');

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
