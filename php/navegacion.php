<?php
/* ==========================================================
   navegacion.php: reemplaza a js/navegacion.js.
   Dibuja la cabecera, los menús desplegables (Cines, Ubicación,
   Películas) y el buscador. Sin JavaScript, cada opción de un menú
   es un ENLACE: al hacer clic, el navegador pide la página de nuevo
   y PHP la dibuja con la nueva elección.
   ========================================================== */

/* Ubicaciones y cines salen de la base (tablas Ubicaciones y Cines). Se leen una sola vez. */
function lista_ubicaciones(): array
{
    static $lista = null;
    if ($lista === null) {
        $lista = conectar()
            ->query('SELECT ID_Ubicacion AS id, nombre_ubicacion AS nombre FROM Ubicaciones ORDER BY nombre_ubicacion')
            ->fetchAll();
    }
    return $lista;
}

function lista_cines(): array
{
    static $lista = null;
    if ($lista === null) {
        $lista = conectar()->query(
            'SELECT c.ID_Cine AS id, c.nombre_cine AS nombre, c.ID_Ubicacion AS ubicacion_id, u.nombre_ubicacion AS ubicacion
             FROM Cines c JOIN Ubicaciones u ON u.ID_Ubicacion = c.ID_Ubicacion
             ORDER BY c.nombre_cine'
        )->fetchAll();
    }
    return $lista;
}

/* Busca en una lista (de ubicaciones o de cines) el elemento con ese id. Devuelve null si no está. */
function buscar_en_lista(array $lista, int $id): ?array
{
    foreach ($lista as $elemento) {
        if ((int)$elemento['id'] === $id) return $elemento;
    }
    return null;
}

/* Lo que la persona eligió (se recuerda entre páginas en la sesión de PHP). 0 = "todas / todos". */
function seleccion(): array
{
    return [
        'ubicacion' => (int)($_SESSION['ubicacion'] ?? 0),
        'cine'      => (int)($_SESSION['cine'] ?? 0),
    ];
}

/* Arma la dirección de la página actual, conservando los parámetros que ya tiene
   y aplicando los cambios indicados. Los parámetros vacíos se descartan.
   Ej.: si estamos en pelicula.php?id=3 y cambios = ['cine' => 2]  ->  "pelicula.php?id=3&cine=2" */
function url_actual(array $cambios = []): string
{
    $parametros = array_merge($_GET, $cambios);
    $parametros = array_filter($parametros, fn($valor) => $valor !== '' && $valor !== null);
    return basename($_SERVER['SCRIPT_NAME']) . ($parametros ? '?' . http_build_query($parametros) : '');
}

/* Dirección del inicio con filtros de género y/o búsqueda. */
function url_inicio(string $genero, string $texto): string
{
    $parametros = array_filter(['genero' => $genero, 'buscar' => $texto], fn($valor) => $valor !== '');
    return BASE . 'index.php' . ($parametros ? '?' . http_build_query($parametros) : '') . '#cartelera';
}

/* Si la dirección trae ?ubicacion=ID o ?cine=ID, guarda la elección en la sesión y vuelve a
   cargar la misma página SIN esos datos en la dirección (así al actualizar no se repite). */
function procesar_seleccion(): void
{
    $hayCambio = false;

    if (isset($_GET['ubicacion'])) {
        $id = (int)$_GET['ubicacion'];
        if ($id === 0 || buscar_en_lista(lista_ubicaciones(), $id)) {
            $_SESSION['ubicacion'] = $id;
            // Si el cine elegido no pertenece a la nueva ubicación, se quita la selección de cine
            $cine = buscar_en_lista(lista_cines(), (int)($_SESSION['cine'] ?? 0));
            if ($id !== 0 && $cine && (int)$cine['ubicacion_id'] !== $id) {
                $_SESSION['cine'] = 0;
            }
        }
        unset($_GET['ubicacion']);
        $hayCambio = true;
    }

    if (isset($_GET['cine'])) {
        $id = (int)$_GET['cine'];
        if ($id === 0 || buscar_en_lista(lista_cines(), $id)) {
            $_SESSION['cine'] = $id;
        }
        unset($_GET['cine']);
        $hayCambio = true;
    }

    if ($hayCambio) {
        header('Location: ' . url_actual());
        exit;
    }
}

/* Género y texto de búsqueda activos. Solo existen en la página de inicio. */
function leer_filtros(): array
{
    $genero = $_GET['genero'] ?? '';
    $texto = $_GET['buscar'] ?? '';
    if (!is_string($genero) || !in_array($genero, obtener_generos(), true)) $genero = '';
    if (!is_string($texto)) $texto = '';
    return ['genero' => $genero, 'texto' => trim($texto)];
}

function filtros_activos(): array
{
    return PAGINA === 'inicio' ? leer_filtros() : ['genero' => '', 'texto' => ''];
}

/* Una opción de menú: <li><a>texto <small>detalle</small></a></li>. */
function crear_opcion(string $texto, string $detalle, bool $activa, string $enlace): string
{
    return '<li><a class="opcion' . ($activa ? ' activa' : '') . '" href="' . e($enlace) . '">'
         . e($texto) . ($detalle !== '' ? '<small>' . e($detalle) . '</small>' : '')
         . '</a></li>';
}

/* Un menú desplegable. <details> es una etiqueta de HTML que se abre y se cierra sola al hacer
   clic en <summary>, sin necesidad de JavaScript. El atributo name hace que al abrir un menú
   se cierren los otros. */
function crear_menu(string $titulo, string $etiqueta, string $opciones): string
{
    return '<details class="item-menu" name="menu-nav">'
         . '<summary class="btn-menu">' . e($titulo) . ' <span class="etq">' . e($etiqueta) . '</span></summary>'
         . '<ul class="desplegable">' . $opciones . '</ul>'
         . '</details>';
}

/* Dibuja la cabecera: apartado de usuario arriba y la barra de navegación debajo. */
function construir_cabecera(): string
{
    $filtros = filtros_activos();
    $sel = seleccion();
    $ubicacion = buscar_en_lista(lista_ubicaciones(), $sel['ubicacion']);
    $cine = buscar_en_lista(lista_cines(), $sel['cine']);

    // MENÚ CINES: si hay una ubicación elegida, solo muestra los cines de esa ubicación.
    $opcionesCines = crear_opcion('Todos los cines', '', $sel['cine'] === 0, url_actual(['cine' => 0]));
    foreach (lista_cines() as $c) {
        if ($sel['ubicacion'] !== 0 && (int)$c['ubicacion_id'] !== $sel['ubicacion']) continue;
        $opcionesCines .= crear_opcion($c['nombre'], $c['ubicacion'], $sel['cine'] === (int)$c['id'], url_actual(['cine' => $c['id']]));
    }

    // MENÚ UBICACIÓN
    $opcionesUbicaciones = crear_opcion('Todas las ubicaciones', '', $sel['ubicacion'] === 0, url_actual(['ubicacion' => 0]));
    foreach (lista_ubicaciones() as $u) {
        $opcionesUbicaciones .= crear_opcion($u['nombre'], '', $sel['ubicacion'] === (int)$u['id'], url_actual(['ubicacion' => $u['id']]));
    }

    // MENÚ PELÍCULAS (géneros): siempre lleva al inicio con el filtro aplicado.
    // Si ya estamos en el inicio, se conserva el texto de búsqueda.
    $opcionesGeneros = crear_opcion('Todos los géneros', '', $filtros['genero'] === '', url_inicio('', $filtros['texto']));
    foreach (obtener_generos() as $genero) {
        $opcionesGeneros .= crear_opcion($genero, '', $filtros['genero'] === $genero, url_inicio($genero, $filtros['texto']));
    }

    $menus = crear_menu('Cines', $cine['nombre'] ?? '', $opcionesCines)
           . crear_menu('Ubicación', $ubicacion['nombre'] ?? '', $opcionesUbicaciones)
           . crear_menu('Películas', $filtros['genero'], $opcionesGeneros);

    // BUSCADOR: es un formulario GET; al enviarlo la dirección queda index.php?buscar=texto
    $accion = e(BASE . 'index.php#cartelera');
    $texto = e($filtros['texto']);
    $generoOculto = $filtros['genero'] !== ''
        ? '<input type="hidden" name="genero" value="' . e($filtros['genero']) . '">'
        : '';
    $inicio = e(BASE . 'index.php');
    $usuario = pintar_sesion();

    return <<<HTML
        <div class="barra-usuario">
          <a class="logo" href="{$inicio}">CineNova</a>
          <div class="acciones-usuario" id="acciones-usuario">{$usuario}</div>
        </div>
        <nav class="barra-nav" aria-label="Navegación principal">
          {$menus}
          <form class="buscador" id="form-buscar" role="search" method="get" action="{$accion}">
            {$generoOculto}
            <input type="search" name="buscar" value="{$texto}" placeholder="Buscar película" aria-label="Buscar película">
            <button type="submit" class="btn-sec">Buscar</button>
          </form>
        </nav>
        HTML;
}
