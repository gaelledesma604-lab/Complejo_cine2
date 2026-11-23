<?php
/* FICHA DE PELÍCULA (reemplaza a pages/pelicula.html + iniciarFicha de main.js).
   Una sola página sirve para todas: la película se elige con la dirección (pelicula.php?id=3). */
require __DIR__ . '/../php/main.php';
iniciar('../', 'pelicula');

$id = (int)($_GET['id'] ?? 0);
$pelicula = buscar_pelicula($id);

if ($pelicula) {
    abrir_pagina('CineNova | ' . $pelicula['titulo']);
    echo '<article id="ficha">' . crear_ficha($pelicula) . crear_horarios(listar_funciones($pelicula['id'])) . '</article>';
} else {
    http_response_code(404); // el navegador y los buscadores saben que esa película no existe
    abrir_pagina('CineNova | Película no encontrada');
    echo '<article id="ficha"><p>No encontramos esa película.</p>'
       . '<a class="volver" href="' . e(BASE . 'index.php') . '">← Volver a la cartelera</a></article>';
}

cerrar_pagina();
