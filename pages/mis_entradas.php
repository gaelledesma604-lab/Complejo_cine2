<?php
/* MIS ENTRADAS: lista las entradas compradas por la persona con sesión abierta y permite cancelarlas. */
require __DIR__ . '/../php/main.php';
iniciar('../', 'mis_entradas');

$usuario = usuario_actual();
if (!$usuario) {
    $_SESSION['volver'] = 'pages/mis_entradas.php';
    header('Location: login.php');
    exit;
}

// Mensaje de una acción anterior (compra confirmada, entrada cancelada). Se muestra una sola vez.
$aviso = (string)($_SESSION['aviso'] ?? '');
unset($_SESSION['aviso']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar'])) {
    $_SESSION['aviso'] = cancelar_entrada((int)$_POST['cancelar'], (int)$usuario['id']);
    header('Location: mis_entradas.php'); // se vuelve a cargar la página para que actualizar no repita la acción
    exit;
}

abrir_pagina('Cine Rubí | Mis entradas');
echo '<section class="mis-entradas"><h1>Mis entradas</h1>';
if ($aviso !== '') {
    echo '<p class="aviso" role="status">' . e($aviso) . '</p>';
}
echo crear_lista_entradas(entradas_del_usuario((int)$usuario['id']));
echo '</section>';
cerrar_pagina();
