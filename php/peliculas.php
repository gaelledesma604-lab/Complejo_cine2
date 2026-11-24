<?php
/* ==========================================================
   peliculas.php: reemplaza a js/peliculas.js.
   ========================================================== */

const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
               'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

// Consulta base: trae una fila por película. Los géneros viven en otra tabla (Generos),
// así que se juntan en un solo texto separado por "|" (ej.: "Acción|Aventura").
// fecha_efectiva = la fecha de estreno o, si no se cargó, la fecha en que se cargó la película.
const SQL_PELICULAS = "
    SELECT p.ID_Pelicula AS id, p.titulo, p.sinopsis, p.duracion,
           c.nombre_clasificacion AS clasificacion,
           p.fecha_estreno,
           COALESCE(p.fecha_estreno, p.fecha_carga_pelicula) AS fecha_efectiva,
           (SELECT GROUP_CONCAT(g.nombre_genero, '|') FROM Generos g WHERE g.ID_Pelicula = p.ID_Pelicula) AS generos
    FROM Peliculas p
    JOIN Clasificaciones c ON c.ID_Clasificacion = p.ID_Clasificacion";

/* Lista de géneros para el menú "Películas": los que existan en la base, sin repetir. */
function obtener_generos(): array
{
    static $generos = null;
    if ($generos === null) {
        $generos = conectar()
            ->query('SELECT DISTINCT nombre_genero FROM Generos ORDER BY nombre_genero')
            ->fetchAll(PDO::FETCH_COLUMN);
    }
    return $generos;
}

/* Quita tildes y mayúsculas para que "accion" encuentre "Acción". */
function normalizar(string $texto): string
{
    $sinTildes = strtr($texto, [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'Á' => 'a', 'À' => 'a', 'Ä' => 'a', 'Â' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e', 'É' => 'e', 'È' => 'e', 'Ë' => 'e', 'Ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'Í' => 'i', 'Ì' => 'i', 'Ï' => 'i', 'Î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'Ó' => 'o', 'Ò' => 'o', 'Ö' => 'o', 'Ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'Ú' => 'u', 'Ù' => 'u', 'Ü' => 'u', 'Û' => 'u',
        'ñ' => 'n', 'Ñ' => 'n',
    ]);
    return strtolower(trim($sinTildes));
}

/* Convierte minutos en texto: 128 -> "2 h 8 min". */
function formatear_duracion(int $minutos): string
{
    return intdiv($minutos, 60) . ' h ' . ($minutos % 60) . ' min';
}

/* Convierte "2026-10-30" en "30 de octubre de 2026". */
function formatear_fecha(string $iso): string
{
    [$anio, $mes, $dia] = explode('-', $iso);
    return (int)$dia . ' de ' . MESES[(int)$mes - 1] . ' de ' . $anio;
}

/* Ordena los datos de una fila de la base: números como números, géneros como lista
   y el estado ("cartelera" o "estreno"), que ya no se guarda: se calcula comparando
   la fecha de estreno con el día de hoy. */
function preparar_pelicula(array $fila): array
{
    $fila['id'] = (int)$fila['id'];
    $fila['duracion'] = $fila['duracion'] === null ? null : (int)$fila['duracion']; // puede faltar (películas importadas del INCAA)
    $fila['generos'] = $fila['generos'] === null ? [] : explode('|', $fila['generos']);
    $fila['estado'] = $fila['fecha_efectiva'] <= date('Y-m-d') ? 'cartelera' : 'estreno';
    return $fila;
}

/* Devuelve la película con ese id (o null si no existe). */
function buscar_pelicula(int $id): ?array
{
    $consulta = conectar()->prepare(SQL_PELICULAS . ' WHERE p.ID_Pelicula = :id');
    $consulta->execute([':id' => $id]); // el valor viaja aparte de la consulta: evita inyección SQL
    $fila = $consulta->fetch();
    return $fila ? preparar_pelicula($fila) : null;
}

/* FILTRADO Y BÚSQUEDA: se queda solo con las películas que cumplen las tres condiciones:
   estado, género (si se eligió uno) y texto (si se escribió algo, debe estar en el título). */
function filtrar_peliculas(string $estado, string $genero, string $texto): array
{
    $buscado = normalizar($texto);
    $resultado = [];
    $filas = conectar()->query(SQL_PELICULAS . ' ORDER BY p.ID_Pelicula')->fetchAll();

    foreach ($filas as $fila) {
        $p = preparar_pelicula($fila);
        if ($p['estado'] !== $estado) continue;
        if ($genero !== '' && !in_array($genero, $p['generos'], true)) continue;
        if ($buscado !== '' && strpos(normalizar($p['titulo']), $buscado) === false) continue;
        $resultado[] = $p;
    }
    return $resultado;
}

/* Colores del póster generado: salen del título, así cada película siempre tiene los mismos.
   (La base no guarda colores; si algún día quieres elegirlos a mano, habría que agregar una columna.) */
function colores_poster(string $titulo): array
{
    $numero = abs(crc32($titulo));
    $tono1 = $numero % 360;
    $tono2 = ($tono1 + 40 + intdiv($numero, 360) % 80) % 360;
    return ["hsl($tono1, 65%, 35%)", "hsl($tono2, 55%, 14%)"];
}

/* Si pones un archivo assets/img/ID.jpg (por ejemplo 4.jpg para la película 4),
   se muestra en lugar del degradado. Devuelve el nombre del archivo o null. */
function imagen_poster(int $id): ?string
{
    foreach (['jpg', 'png', 'webp'] as $extension) {
        if (is_file(__DIR__ . "/../assets/img/$id.$extension")) {
            return "$id.$extension";
        }
    }
    return null;
}

/* Devuelve el HTML del póster: imagen real si existe, o degradado con el título. */
function crear_poster(array $p): string
{
    $titulo = e($p['titulo']);
    $imagen = imagen_poster($p['id']);
    if ($imagen !== null) {
        return '<div class="poster"><img src="' . e(BASE . 'assets/img/' . $imagen) . '" alt="Póster de ' . $titulo . '"></div>';
    }
    [$color1, $color2] = colores_poster($p['titulo']);
    return '<div class="poster" role="img" aria-label="Póster de ' . $titulo . '"'
         . ' style="background: linear-gradient(160deg, ' . $color1 . ', ' . $color2 . ')"><span>' . $titulo . '</span></div>';
}

/* Devuelve el HTML de una tarjeta. BASE ayuda a armar el enlace a pages/pelicula.php
   (vacío desde index.php, "../" desde pages/). */
function crear_tarjeta(array $p): string
{
    $enlace = e(BASE . 'pages/pelicula.php?id=' . $p['id']);
    $titulo = e($p['titulo']);
    $poster = crear_poster($p);
    $generos = e(implode(', ', $p['generos']));
    $duracion = $p['duracion'] === null ? '' : '<p class="meta">' . e(formatear_duracion($p['duracion'])) . '</p>';
    $estreno = $p['estado'] === 'estreno'
        ? '<p class="meta estreno-fecha">Estreno: ' . e(formatear_fecha($p['fecha_efectiva'])) . '</p>'
        : '';

    return <<<HTML
        <article class="tarjeta">
          <a href="{$enlace}" aria-label="Ver información de {$titulo}">{$poster}</a>
          <div class="info">
            <h3>{$titulo}</h3>
            <p class="meta">{$generos}</p>
            {$duracion}
            {$estreno}
            <a class="ver-mas" href="{$enlace}">Ver más información</a>
          </div>
        </article>
        HTML;
}

/* Devuelve el HTML completo de la pantalla de información de una película. */
function crear_ficha(array $p): string
{
    $volver = e(BASE . 'index.php#cartelera');
    $poster = crear_poster($p);
    $titulo = e($p['titulo']);
    $chips = '';
    foreach ($p['generos'] as $genero) {
        $chips .= '<span class="chip">' . e($genero) . '</span>';
    }
    // Si la duración no está cargada, esa fila no se muestra
    $duracion = $p['duracion'] === null ? '' : '<div><dt>Duración</dt><dd>' . e(formatear_duracion($p['duracion'])) . '</dd></div>';
    $clasificacion = e($p['clasificacion']);
    // Si la película no tiene fecha de estreno cargada, esa fila simplemente no se muestra
    $estreno = $p['fecha_estreno']
        ? '<div><dt>Fecha de estreno</dt><dd>' . e(formatear_fecha($p['fecha_estreno'])) . '</dd></div>'
        : '';
    $sinopsis = trim((string)$p['sinopsis']) !== '' ? e((string)$p['sinopsis']) : 'Sin sinopsis disponible.';

    return <<<HTML
        <a class="volver" href="{$volver}">← Volver a la cartelera</a>
        <div class="ficha">
          {$poster}
          <div>
            <h1>{$titulo}</h1>
            <div class="chips">{$chips}</div>
            <dl class="datos">
              {$duracion}
              <div><dt>Clasificación</dt><dd>{$clasificacion}</dd></div>
              {$estreno}
            </dl>
            <section class="sinopsis"><h2>Sinopsis</h2><p>{$sinopsis}</p></section>
          </div>
        </div>
        HTML;
}
