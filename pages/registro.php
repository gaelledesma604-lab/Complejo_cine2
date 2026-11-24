<?php
/* CREAR CUENTA: igual que login.php, pero guarda a la persona en la tabla Usuarios. */
require __DIR__ . '/../php/main.php';
iniciar('../', 'registro');

if (usuario_actual()) {
    header('Location: ../index.php');
    exit;
}

$error = '';
$nombre = '';
$correo = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $correo = trim((string)($_POST['email'] ?? ''));
    $error = procesar_registro(
        $nombre,
        $correo,
        (string)($_POST['password'] ?? ''),
        (string)($_POST['password2'] ?? '')
    );
}

abrir_pagina('Cine Rubí | Crear cuenta');
echo formulario_registro($error, $nombre, $correo);
cerrar_pagina();
