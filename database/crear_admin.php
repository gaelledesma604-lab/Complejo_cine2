<?php
/* ==========================================================
   crear_admin.php: convierte en administrador a una cuenta que ya existe.
   Primero crea la cuenta desde la web (Crear cuenta) y después, desde la terminal:

       php database/crear_admin.php correo@ejemplo.com

   Hay que cerrar sesión y volver a iniciarla para que el cambio se note.
   ========================================================== */

if (PHP_SAPI !== 'cli') {
    exit('Este script se ejecuta desde la terminal.');
}

$correo = $argv[1] ?? '';
if ($correo === '') {
    exit("Uso: php database/crear_admin.php correo@ejemplo.com\n");
}

require __DIR__ . '/../php/conexion.php';

$consulta = conectar()->prepare(
    "UPDATE Usuarios SET ID_Rol = (SELECT ID_Rol FROM Roles WHERE nombre_rol = 'admin')
     WHERE correo_usuario = :correo"
);
$consulta->execute([':correo' => $correo]);

if ($consulta->rowCount() === 0) {
    exit("No hay ninguna cuenta con ese correo. Créala primero desde la web.\n");
}
echo "Listo: $correo ahora es administrador. Cierra sesión y vuelve a iniciarla.\n";
