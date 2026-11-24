<?php
/* PÁGINA PRINCIPAL de Cine Rubí (reemplaza a index.html + la parte de inicio de main.js).
   PHP lee los filtros de la dirección, consulta la base y escribe las tarjetas ya armadas. */
require __DIR__ . '/php/main.php';
iniciar('', 'inicio');

$filtros = leer_filtros(); // género y texto que vienen en la dirección (?genero=Drama&buscar=luz)
$enCartelera = filtrar_peliculas('cartelera', $filtros['genero'], $filtros['texto']);
$proximos = filtrar_peliculas('estreno', $filtros['genero'], $filtros['texto']);

// Resumen de filtros activos junto al título
$partes = [];
if ($filtros['genero'] !== '') $partes[] = 'Género: ' . $filtros['genero'];
if ($filtros['texto'] !== '') $partes[] = 'Búsqueda: "' . $filtros['texto'] . '"';

abrir_pagina('Cine Rubí | Cartelera');
?>
    <!-- SECCIÓN "EN CARTELERA" -->
    <section id="cartelera" aria-labelledby="titulo-cartelera">
      <div class="encabezado-seccion">
        <h2 id="titulo-cartelera">En cartelera</h2>
        <p id="estado-filtros" class="texto-filtro"><?= e(implode('. ', $partes)) ?></p>
      </div>
      <div id="grid-cartelera" class="grid-peliculas">
        <?php if ($enCartelera): ?>
          <?php foreach ($enCartelera as $p): ?>
            <?= crear_tarjeta($p) ?>
          <?php endforeach; ?>
        <?php else: ?>
          <!-- Si no hay coincidencias se muestra un mensaje con un enlace para quitar filtros -->
          <div class="vacio">
            <p>No encontramos películas en cartelera con esos filtros.</p>
            <a class="btn-prim" href="index.php#cartelera">Ver toda la cartelera</a>
          </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- SECCIÓN "PRÓXIMOS ESTRENOS": se omite entera si no hay ninguna -->
    <?php if ($proximos): ?>
    <section id="estrenos" aria-labelledby="titulo-estrenos">
      <div class="encabezado-seccion">
        <h2 id="titulo-estrenos">Próximos estrenos</h2>
      </div>
      <div id="grid-estrenos" class="grid-peliculas">
        <?php foreach ($proximos as $p): ?>
          <?= crear_tarjeta($p) ?>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>
<?php
cerrar_pagina();
