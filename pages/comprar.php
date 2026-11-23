<?php
/* COMPRAR ENTRADAS: muestra el mapa de butacas de una función (comprar.php?funcion=3) y procesa la compra.
   Hace falta tener la sesión abierta: si no, se manda a iniciar sesión y se vuelve aquí después. */
require __DIR__ . '/../php/main.php';
iniciar('../', 'comprar');

$id = (int)($_GET['funcion'] ?? 0);
$funcion = buscar_funcion($id);

if (!$funcion) {
    http_response_code(404);
    abrir_pagina('CineNova | Función no encontrada');
    echo '<article><p>No encontramos esa función.</p>'
       . '<a class="volver" href="' . e(BASE . 'index.php') . '">← Volver a la cartelera</a></article>';
    cerrar_pagina();
    exit;
}

$usuario = usuario_actual();
if (!$usuario) {
    $_SESSION['volver'] = 'pages/comprar.php?funcion=' . $id; // login.php lo usa para volver aquí
    header('Location: login.php');
    exit;
}

$error = '';
$marcadas = [];
$tipoElegido = 0;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = procesar_compra($funcion, (int)$usuario['id']); // si sale bien, redirige y termina aquí
    // Si hubo error se vuelve a mostrar el formulario con lo que la persona ya había elegido
    $tipoElegido = (int)($_POST['tipo'] ?? 0);
    $marcadas = is_array($_POST['butacas'] ?? null) ? array_values(array_filter($_POST['butacas'], 'is_string')) : [];
}

abrir_pagina('CineNova | Comprar entradas');
echo formulario_compra($funcion, $error, $marcadas, $tipoElegido);
cerrar_pagina();
