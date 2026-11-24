<?php
/* ADMINISTRACIÓN: solo para usuarios con rol "admin". Cada formulario envía un campo "accion"
   que dice qué hacer: pelicula, editar_pelicula, funcion o cancelar_funcion. */
require __DIR__ . '/../php/main.php';
iniciar('../', 'admin');
exigir_admin(); // sin sesión -> login; sin rol admin -> 403

$errores = ['general' => '', 'pelicula' => '', 'funcion' => '', 'editar' => ''];

// Mensaje de una acción anterior (película agregada, función cancelada...). Se muestra una sola vez.
$aviso = (string)($_SESSION['aviso'] ?? '');
unset($_SESSION['aviso']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = (string)($_POST['accion'] ?? '');
    if (!csrf_valido()) {
        $errores['general'] = 'La sesión del formulario venció. Inténtalo de nuevo.';
    } elseif ($accion === 'pelicula') {
        $errores['pelicula'] = admin_agregar_pelicula();   // si sale bien, redirige y termina aquí
    } elseif ($accion === 'editar_pelicula') {
        $errores['editar'] = admin_editar_pelicula((int)($_POST['id'] ?? 0)); // ídem
    } elseif ($accion === 'funcion') {
        $errores['funcion'] = admin_agregar_funcion();     // ídem
    } elseif ($accion === 'cancelar_funcion') {
        $errores['general'] = admin_cancelar_funcion((int)($_POST['id'] ?? 0)); // ídem
    }
}

abrir_pagina('Cine Rubí | Administración');
echo crear_panel_admin($errores, $aviso);
cerrar_pagina();
