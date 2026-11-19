<?php
/* ==========================================================
   main.php: reemplaza a js/main.js. Es el punto de entrada:
   cada página (index.php, pelicula.php, login.php...) empieza
   con require de este archivo, que carga todos los demás,
   abre la sesión y deja listas las funciones comunes.
   ========================================================== */

// Zona horaria: se usa para saber qué es "hoy" (una película pasa de "estreno" a "cartelera" sola)
date_default_timezone_set('America/Argentina/Buenos_Aires');

// La sesión permite recordar a la persona entre una página y otra. Debe abrirse antes de escribir cualquier HTML.
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

/* Escapa un texto antes de ponerlo en el HTML: convierte < > & " ' en códigos seguros.
   Sin esto, un título o un nombre con etiquetas HTML podría romper la página o ejecutar código ajeno (XSS). */
function e(?string $texto): string
{
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/peliculas.php';
require_once __DIR__ . '/usuarios.php';
require_once __DIR__ . '/navegacion.php';

/* Cada página lo llama una vez al principio.
   $base   = ruta hacia la raíz del proyecto ("" en index.php, "../" en pages/)
   $pagina = "inicio", "pelicula", "login" o "registro" */
function iniciar(string $base, string $pagina): void
{
    define('BASE', $base);
    define('PAGINA', $pagina);
    procesar_seleccion(); // guarda ?cine= / ?ubicacion= en la sesión (puede redirigir)
}

/* Escribe el comienzo de la página: <head>, cabecera y apertura de <main>. */
function abrir_pagina(string $titulo): void
{
    $titulo = e($titulo);
    $base = e(BASE);
    $cabecera = construir_cabecera();

    echo <<<HTML
        <!DOCTYPE html>
        <html lang="es">
        <head>
          <meta charset="UTF-8">
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <title>{$titulo}</title>
          <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
          <link rel="stylesheet" href="{$base}css/styles.css">
          <link rel="stylesheet" href="{$base}css/peliculas.css">
          <link rel="stylesheet" href="{$base}css/responsive.css">
          <link rel="stylesheet" href="{$base}css/php.css">
        </head>
        <body>
          <header id="cabecera">
        {$cabecera}
          </header>
          <main>

        HTML;
}

/* Escribe el final de la página: cierre de <main>, pie y cierre del documento. */
function cerrar_pagina(): void
{
    echo <<<HTML

          </main>
          <footer class="pie">CineNova es un proyecto de ejemplo: no vende entradas reales.</footer>
        </body>
        </html>

        HTML;
}
