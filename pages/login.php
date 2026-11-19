<?php
/* INICIO DE SESIÓN: antes era una ventana <dialog> manejada por usuarios.js; ahora es una página.
   Si llegan datos por POST (el formulario fue enviado), se procesan; si no, solo se muestra el formulario. */
require __DIR__ . '/../php/main.php';
iniciar('../', 'login');

if (usuario_actual()) { // si ya hay sesión abierta no tiene sentido iniciar otra
    header('Location: ../index.php');
    exit;
}

$error = '';
$correo = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim((string)($_POST['email'] ?? ''));
    $error = procesar_login($correo, (string)($_POST['password'] ?? '')); // si sale bien, redirige y termina aquí
}

abrir_pagina('CineNova | Iniciar sesión');
echo formulario_login($error, $correo);
cerrar_pagina();
