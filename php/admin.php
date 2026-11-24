<?php
/* ==========================================================
   admin.php: panel de administración. Solo para usuarios con
   rol "admin". Permite cargar películas, programar funciones
   y cancelar funciones que todavía no vendieron entradas.
   ========================================================== */

// Minutos que se suponen para una película sin duración cargada (solo para calcular la hora de fin de una función)
const DURACION_ESTIMADA = 120;

function es_admin(): bool
{
    $usuario = usuario_actual();
    return $usuario !== null && ($usuario['rol'] ?? '') === 'admin';
}

/* Se llama al principio de la página de administración. Sin sesión manda a iniciar sesión
   (y vuelve aquí después); con sesión pero sin rol admin responde 403 (prohibido). */
function exigir_admin(): void
{
    if (!usuario_actual()) {
        $_SESSION['volver'] = 'pages/admin.php';
        header('Location: login.php');
        exit;
    }
    if (!es_admin()) {
        http_response_code(403);
        abrir_pagina('CineNova | Acceso denegado');
        echo '<article><p>Esta página es solo para administradores.</p>'
           . '<a class="volver" href="' . e(BASE . 'index.php') . '">← Volver a la cartelera</a></article>';
        cerrar_pagina();
        exit;
    }
}

/* ¿"2026-11-30" es una fecha real? (rechaza cosas como 2026-02-31) */
function fecha_iso_valida(string $texto): bool
{
    $fecha = DateTime::createFromFormat('Y-m-d', $texto);
    return $fecha !== false && $fecha->format('Y-m-d') === $texto;
}

/* ---------- Películas ---------- */

/* Lee y valida los datos de película del formulario (el alta y la edición comparten las reglas).
   Devuelve el mensaje de error (texto) o los datos limpios (arreglo). La duración es obligatoria al
   agregar; al editar puede quedar vacía, porque las películas importadas del INCAA no la traen. */
function admin_leer_pelicula(bool $duracionObligatoria)
{
    $titulo = trim((string)($_POST['titulo'] ?? ''));
    $sinopsis = trim((string)($_POST['sinopsis'] ?? ''));
    $textoDuracion = trim((string)($_POST['duracion'] ?? ''));
    $duracion = $textoDuracion === '' ? null : (int)$textoDuracion;
    $clasificacion = (int)($_POST['clasificacion'] ?? 0);
    $estreno = trim((string)($_POST['estreno'] ?? ''));

    if ($titulo === '' || largo_texto($titulo) > 120) return 'El título es obligatorio (máximo 120 caracteres).';
    if (largo_texto($sinopsis) > 1000) return 'La sinopsis puede tener hasta 1000 caracteres.';
    if ($duracion === null) {
        if ($duracionObligatoria) return 'La duración es obligatoria.';
    } elseif ($duracion < 1 || $duracion > 600) {
        return 'La duración debe estar entre 1 y 600 minutos.';
    }
    if ($estreno !== '' && !fecha_iso_valida($estreno)) return 'La fecha de estreno no es válida.';

    // Géneros: texto separado por comas, sin repetidos (aunque cambien las mayúsculas), hasta 5
    $generos = [];
    foreach (explode(',', (string)($_POST['generos'] ?? '')) as $genero) {
        $genero = trim($genero);
        if ($genero === '') continue;
        if (largo_texto($genero) > 30) return 'Cada género puede tener hasta 30 caracteres.';
        $generos[strtolower($genero)] = $genero;
    }
    if (count($generos) > 5) return 'Puedes cargar hasta 5 géneros por película.';

    $existe = conectar()->prepare('SELECT 1 FROM Clasificaciones WHERE ID_Clasificacion = :id');
    $existe->execute([':id' => $clasificacion]);
    if (!$existe->fetchColumn()) return 'Elige una clasificación válida.';

    return [
        'titulo'        => $titulo,
        'sinopsis'      => $sinopsis === '' ? null : $sinopsis,
        'duracion'      => $duracion,
        'clasificacion' => $clasificacion,
        'estreno'       => $estreno === '' ? null : $estreno,
        'generos'       => array_values($generos),
    ];
}

function admin_guardar_generos(PDO $pdo, int $idPelicula, array $generos): void
{
    $insertar = $pdo->prepare('INSERT INTO Generos (nombre_genero, ID_Pelicula) VALUES (:genero, :pelicula)');
    foreach ($generos as $genero) {
        $insertar->execute([':genero' => $genero, ':pelicula' => $idPelicula]);
    }
}

/* Agrega una película con sus géneros. Devuelve el mensaje de error; si todo sale bien, redirige. */
function admin_agregar_pelicula(): string
{
    $datos = admin_leer_pelicula(true);
    if (is_string($datos)) return $datos;

    $pdo = conectar();
    $repetida = $pdo->prepare('SELECT 1 FROM Peliculas WHERE lower(titulo) = lower(:titulo)');
    $repetida->execute([':titulo' => $datos['titulo']]);
    if ($repetida->fetchColumn()) return 'Ya existe una película con ese título.';

    try {
        $pdo->beginTransaction();
        $pdo->prepare(
            'INSERT INTO Peliculas (titulo, sinopsis, duracion, ID_Clasificacion, fecha_estreno)
             VALUES (:titulo, :sinopsis, :duracion, :clasificacion, :estreno)'
        )->execute([
            ':titulo'        => $datos['titulo'],
            ':sinopsis'      => $datos['sinopsis'],
            ':duracion'      => $datos['duracion'],
            ':clasificacion' => $datos['clasificacion'],
            ':estreno'       => $datos['estreno'],
        ]);
        admin_guardar_generos($pdo, (int)$pdo->lastInsertId(), $datos['generos']);
        $pdo->commit();
    } catch (PDOException $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return 'No se pudo guardar la película. Inténtalo de nuevo.';
    }

    $_SESSION['aviso'] = 'Película "' . $datos['titulo'] . '" agregada.';
    header('Location: admin.php');
    exit;
}

/* Edita una película: actualiza sus datos y reemplaza la lista de géneros por la enviada. */
function admin_editar_pelicula(int $id): string
{
    $pdo = conectar();
    $existe = $pdo->prepare('SELECT 1 FROM Peliculas WHERE ID_Pelicula = :id');
    $existe->execute([':id' => $id]);
    if (!$existe->fetchColumn()) return 'No encontramos esa película.';

    $datos = admin_leer_pelicula(false);
    if (is_string($datos)) return $datos;

    $repetida = $pdo->prepare('SELECT 1 FROM Peliculas WHERE lower(titulo) = lower(:titulo) AND ID_Pelicula <> :id');
    $repetida->execute([':titulo' => $datos['titulo'], ':id' => $id]);
    if ($repetida->fetchColumn()) return 'Ya existe otra película con ese título.';

    try {
        $pdo->beginTransaction();
        $pdo->prepare(
            'UPDATE Peliculas SET titulo = :titulo, sinopsis = :sinopsis, duracion = :duracion,
                    ID_Clasificacion = :clasificacion, fecha_estreno = :estreno
             WHERE ID_Pelicula = :id'
        )->execute([
            ':titulo'        => $datos['titulo'],
            ':sinopsis'      => $datos['sinopsis'],
            ':duracion'      => $datos['duracion'],
            ':clasificacion' => $datos['clasificacion'],
            ':estreno'       => $datos['estreno'],
            ':id'            => $id,
        ]);
        $pdo->prepare('DELETE FROM Generos WHERE ID_Pelicula = :id')->execute([':id' => $id]);
        admin_guardar_generos($pdo, $id, $datos['generos']);
        $pdo->commit();
    } catch (PDOException $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return 'No se pudo guardar la película. Inténtalo de nuevo.';
    }

    $_SESSION['aviso'] = 'Película "' . $datos['titulo'] . '" actualizada.';
    header('Location: admin.php');
    exit;
}

/* ---------- Funciones ---------- */

/* Programa una función: valida todo, calcula la hora de fin con la duración de la película y
   evita que dos funciones se pisen en la misma sala. */
function admin_agregar_funcion(): string
{
    $idPelicula = (int)($_POST['pelicula'] ?? 0);
    $idSala = (int)($_POST['sala'] ?? 0);
    $idTipo = (int)($_POST['tipo_funcion'] ?? 0);
    $fecha = trim((string)($_POST['fecha'] ?? ''));
    $hora = trim((string)($_POST['hora'] ?? ''));

    if (!fecha_iso_valida($fecha)) return 'La fecha no es válida.';
    if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $hora)) return 'La hora no es válida.';
    if ($fecha . ' ' . $hora <= date('Y-m-d H:i')) return 'La función tiene que ser en el futuro.';

    $pdo = conectar();

    $consulta = $pdo->prepare('SELECT duracion FROM Peliculas WHERE ID_Pelicula = :id');
    $consulta->execute([':id' => $idPelicula]);
    $duracion = $consulta->fetchColumn();
    if ($duracion === false) return 'Elige una película válida.';

    $consulta = $pdo->prepare('SELECT 1 FROM Salas WHERE ID_Sala = :id AND estado = 1');
    $consulta->execute([':id' => $idSala]);
    if (!$consulta->fetchColumn()) return 'Elige una sala válida.';

    $consulta = $pdo->prepare('SELECT 1 FROM Tipo_funcion WHERE ID_Tipo_funcion = :id');
    $consulta->execute([':id' => $idTipo]);
    if (!$consulta->fetchColumn()) return 'Elige un tipo de función válido.';

    // La base guarda solo la hora (HH:MM), así que la función debe terminar el mismo día
    $inicio = DateTime::createFromFormat('Y-m-d H:i', $fecha . ' ' . $hora);
    $minutos = $duracion === null ? DURACION_ESTIMADA : (int)$duracion;
    $fin = (clone $inicio)->modify('+' . $minutos . ' minutes');
    if ($fin->format('Y-m-d') !== $fecha) return 'La función terminaría después de medianoche. Elige un horario más temprano.';
    $horaFin = $fin->format('H:i');

    // Dos funciones se pisan si una empieza antes de que termine la otra
    $consulta = $pdo->prepare(
        'SELECT 1 FROM Funciones
         WHERE ID_Sala = :sala AND fecha = :fecha AND estado <> 0
           AND hora_inicio < :fin AND hora_fin > :inicio LIMIT 1'
    );
    $consulta->execute([':sala' => $idSala, ':fecha' => $fecha, ':fin' => $horaFin, ':inicio' => $hora]);
    if ($consulta->fetchColumn()) return 'Esa sala ya tiene una función que se superpone con ese horario.';

    try {
        $pdo->beginTransaction();

        // Funciones.ID_Programacion es obligatorio: se usa la programación que cubra esa fecha o se crea una
        $consulta = $pdo->prepare('SELECT ID_Programacion FROM Programaciones WHERE fecha_inicio <= :f1 AND fecha_fin >= :f2 LIMIT 1');
        $consulta->execute([':f1' => $fecha, ':f2' => $fecha]);
        $idProgramacion = $consulta->fetchColumn();
        if ($idProgramacion === false) {
            $pdo->prepare('INSERT INTO Programaciones (fecha_inicio, fecha_fin, fecha_carga) VALUES (:inicio, :fin, :carga)')
                ->execute([':inicio' => $fecha, ':fin' => $fecha, ':carga' => date('Y-m-d')]);
            $idProgramacion = (int)$pdo->lastInsertId();
        }

        $pdo->prepare(
            'INSERT INTO Funciones (estado, fecha, hora_inicio, hora_fin, ID_Sala, ID_Programacion, ID_Tipo_funcion, ID_Pelicula)
             VALUES (1, :fecha, :inicio, :fin, :sala, :programacion, :tipo, :pelicula)'
        )->execute([
            ':fecha' => $fecha, ':inicio' => $hora, ':fin' => $horaFin, ':sala' => $idSala,
            ':programacion' => (int)$idProgramacion, ':tipo' => $idTipo, ':pelicula' => $idPelicula,
        ]);
        $pdo->commit();
    } catch (PDOException $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (strpos($error->getMessage(), 'UNIQUE') !== false) {
            return 'Esa sala ya tiene una función a esa hora.';
        }
        return 'No se pudo guardar la función. Inténtalo de nuevo.';
    }

    $_SESSION['aviso'] = 'Función programada: ' . etiqueta_fecha($fecha) . ' a las ' . $hora . '.';
    header('Location: admin.php');
    exit;
}

/* Cancela una función (estado 0: deja de mostrarse y de venderse).
   Si ya tiene entradas vendidas no se permite, para no dejar a nadie con una entrada inválida. */
function admin_cancelar_funcion(int $id): string
{
    $pdo = conectar();
    $consulta = $pdo->prepare(
        'SELECT f.estado, (SELECT COUNT(*) FROM Entradas e WHERE e.ID_funcion = f.ID_funcion AND e.estado = 1) AS vendidas
         FROM Funciones f WHERE f.ID_funcion = :id'
    );
    $consulta->execute([':id' => $id]);
    $fila = $consulta->fetch();

    if (!$fila) return 'No encontramos esa función.';
    if ((int)$fila['estado'] !== 1) return 'Esa función ya estaba cancelada.';
    if ((int)$fila['vendidas'] > 0) return 'No se puede cancelar: tiene entradas vendidas.';

    $pdo->prepare('UPDATE Funciones SET estado = 0 WHERE ID_funcion = :id')->execute([':id' => $id]);
    $_SESSION['aviso'] = 'Función cancelada.';
    header('Location: admin.php');
    exit;
}

/* ---------- Cuentas ---------- */

/* Da o quita el rol de administrador a otra cuenta. Nadie puede cambiarse el rol a sí mismo:
   así siempre queda al menos un administrador (el que está haciendo el cambio). */
function admin_cambiar_rol(int $idCuenta, string $rol): string
{
    if (!in_array($rol, ['admin', 'usuario'], true)) return 'Rol no válido.';

    $yo = usuario_actual();
    if ((int)$yo['id'] === $idCuenta) return 'No puedes cambiar tu propio rol.';

    $pdo = conectar();
    $consulta = $pdo->prepare('SELECT nombre_usuario FROM Usuarios WHERE ID_Usuario = :id');
    $consulta->execute([':id' => $idCuenta]);
    $nombre = $consulta->fetchColumn();
    if ($nombre === false) return 'No encontramos esa cuenta.';

    $pdo->prepare('UPDATE Usuarios SET ID_Rol = (SELECT ID_Rol FROM Roles WHERE nombre_rol = :rol) WHERE ID_Usuario = :id')
        ->execute([':rol' => $rol, ':id' => $idCuenta]);

    $_SESSION['aviso'] = $rol === 'admin'
        ? $nombre . ' ahora es administrador.'
        : $nombre . ' ya no es administrador.';
    header('Location: admin.php');
    exit;
}

/* ---------- HTML ---------- */

/* Convierte filas de la base en <option>. $texto es una función que arma el texto de cada fila. */
function opciones_admin(array $filas, string $campoId, callable $texto, int $elegido): string
{
    $html = '';
    foreach ($filas as $fila) {
        $seleccion = (int)$fila[$campoId] === $elegido ? ' selected' : '';
        $html .= '<option value="' . (int)$fila[$campoId] . '"' . $seleccion . '>' . e($texto($fila)) . '</option>';
    }
    return $html;
}

/* Valor de un campo del formulario, escapado, para no perder lo escrito si hubo un error. */
function valor_post(string $campo): string
{
    return e(trim((string)($_POST[$campo] ?? '')));
}

/* Sección "Películas" del panel: formulario de edición (si se pidió con ?editar=ID) y lista con un botón
   "Editar" en cada película. */
function crear_seccion_peliculas(string $error): string
{
    $pdo = conectar();
    $csrf = e(token_csrf());
    $idEditar = (int)($_GET['editar'] ?? 0);

    $editor = '';
    if ($idEditar > 0) {
        $consulta = $pdo->prepare(
            "SELECT p.ID_Pelicula AS id, p.titulo, p.sinopsis, p.duracion, p.ID_Clasificacion AS clasificacion,
                    p.fecha_estreno AS estreno,
                    (SELECT GROUP_CONCAT(g.nombre_genero, ', ') FROM Generos g WHERE g.ID_Pelicula = p.ID_Pelicula) AS generos
             FROM Peliculas p WHERE p.ID_Pelicula = :id"
        );
        $consulta->execute([':id' => $idEditar]);
        $fila = $consulta->fetch();

        if ($fila) {
            // Si se envió el formulario y hubo un error se muestra lo escrito; si no, lo que hay guardado
            $enviado = ($_POST['accion'] ?? '') === 'editar_pelicula';
            $valor = fn(string $campo) => e($enviado ? trim((string)($_POST[$campo] ?? '')) : (string)($fila[$campo] ?? ''));

            $clasificaciones = $pdo->query('SELECT ID_Clasificacion AS id, nombre_clasificacion AS nombre FROM Clasificaciones ORDER BY edad_minima, ID_Clasificacion')->fetchAll();
            $elegida = $enviado ? (int)($_POST['clasificacion'] ?? 0) : (int)$fila['clasificacion'];
            $opciones = opciones_admin($clasificaciones, 'id', fn($f) => $f['nombre'], $elegida);

            $accion = e(BASE . 'pages/admin.php?editar=' . (int)$fila['id'] . '#editar');
            $cancelar = e(BASE . 'pages/admin.php');
            $id = (int)$fila['id'];
            $titulo = $valor('titulo');
            $sinopsis = $valor('sinopsis');
            $duracion = $valor('duracion');
            $estreno = $valor('estreno');
            $generos = $valor('generos');
            $textoError = e($error);

            $editor = <<<HTML
                <form class="tarjeta-admin editor-pelicula" method="post" action="{$accion}">
                  <h3>Editar película</h3>
                  <input type="hidden" name="csrf" value="{$csrf}">
                  <input type="hidden" name="accion" value="editar_pelicula">
                  <input type="hidden" name="id" value="{$id}">
                  <div class="campo-admin"><label for="e-titulo">Título</label>
                    <input id="e-titulo" name="titulo" type="text" maxlength="120" value="{$titulo}" required></div>
                  <div class="campo-admin"><label for="e-sinopsis">Sinopsis</label>
                    <textarea id="e-sinopsis" name="sinopsis" rows="4" maxlength="1000">{$sinopsis}</textarea></div>
                  <div class="campo-admin"><label for="e-duracion">Duración (minutos, opcional)</label>
                    <input id="e-duracion" name="duracion" type="number" min="1" max="600" value="{$duracion}"></div>
                  <div class="campo-admin"><label for="e-clasificacion">Clasificación</label>
                    <select id="e-clasificacion" name="clasificacion">{$opciones}</select></div>
                  <div class="campo-admin"><label for="e-estreno">Fecha de estreno (opcional)</label>
                    <input id="e-estreno" name="estreno" type="date" value="{$estreno}"></div>
                  <div class="campo-admin"><label for="e-generos">Géneros, separados por comas (máx. 5)</label>
                    <input id="e-generos" name="generos" type="text" placeholder="Acción, Drama" value="{$generos}"></div>
                  <p class="error" role="alert">{$textoError}</p>
                  <button type="submit" class="btn-prim">Guardar cambios</button>
                  <a class="btn-sec" href="{$cancelar}">Cancelar</a>
                </form>
                HTML;
        }
    }

    $filas = '';
    $consulta = $pdo->query(
        "SELECT p.ID_Pelicula AS id, p.titulo, c.nombre_clasificacion AS clasificacion, p.duracion,
                (SELECT GROUP_CONCAT(g.nombre_genero, ', ') FROM Generos g WHERE g.ID_Pelicula = p.ID_Pelicula) AS generos
         FROM Peliculas p JOIN Clasificaciones c ON c.ID_Clasificacion = p.ID_Clasificacion
         ORDER BY p.titulo LIMIT 200"
    );
    foreach ($consulta->fetchAll() as $f) {
        $enlace = e(BASE . 'pages/admin.php?editar=' . (int)$f['id'] . '#editar');
        $filas .= '<tr><td>' . e($f['titulo']) . '</td><td>' . e($f['clasificacion']) . '</td>'
                . '<td>' . ($f['duracion'] === null ? '—' : (int)$f['duracion'] . ' min') . '</td>'
                . '<td>' . ($f['generos'] === null ? '—' : e($f['generos'])) . '</td>'
                . '<td><a class="btn-sec" href="' . $enlace . '">Editar</a></td></tr>';
    }
    $tabla = $filas !== ''
        ? '<div class="tabla-admin"><table><thead><tr><th>Título</th><th>Clasificación</th><th>Duración</th><th>Géneros</th><th></th></tr></thead>'
          . '<tbody>' . $filas . '</tbody></table></div>'
        : '<p class="texto-filtro">Todavía no hay películas.</p>';

    return '<h2 id="editar">Películas</h2>' . $editor . $tabla;
}

function crear_panel_admin(array $errores, string $aviso): string
{
    $pdo = conectar();
    $csrf = e(token_csrf());
    $accion = e(BASE . 'pages/admin.php');

    $clasificaciones = $pdo->query('SELECT ID_Clasificacion AS id, nombre_clasificacion AS nombre FROM Clasificaciones ORDER BY edad_minima, ID_Clasificacion')->fetchAll();
    $peliculas = $pdo->query('SELECT ID_Pelicula AS id, titulo FROM Peliculas ORDER BY titulo')->fetchAll();
    $salas = $pdo->query(
        'SELECT s.ID_Sala AS id, c.nombre_cine AS cine, s.numero FROM Salas s
         JOIN Cines c ON c.ID_cine = s.ID_cine WHERE s.estado = 1 ORDER BY c.nombre_cine, s.numero'
    )->fetchAll();
    $tiposFuncion = $pdo->query('SELECT ID_Tipo_funcion AS id, nombre_TF AS nombre FROM Tipo_funcion ORDER BY ID_Tipo_funcion')->fetchAll();

    $opcClasificacion = opciones_admin($clasificaciones, 'id', fn($f) => $f['nombre'], (int)($_POST['clasificacion'] ?? 0));
    $opcPelicula = opciones_admin($peliculas, 'id', fn($f) => $f['titulo'], (int)($_POST['pelicula'] ?? 0));
    $opcSala = opciones_admin($salas, 'id', fn($f) => $f['cine'] . ' · Sala ' . $f['numero'], (int)($_POST['sala'] ?? 0));
    $opcTipo = opciones_admin($tiposFuncion, 'id', fn($f) => $f['nombre'], (int)($_POST['tipo_funcion'] ?? 0));

    $textoAviso = $aviso !== '' ? '<p class="aviso" role="status">' . e($aviso) . '</p>' : '';
    $errorGeneral = e($errores['general']);
    $errorPelicula = e($errores['pelicula']);
    $errorFuncion = e($errores['funcion']);
    $titulo = valor_post('titulo');
    $sinopsis = valor_post('sinopsis');
    $duracion = valor_post('duracion');
    $estreno = valor_post('estreno');
    $generos = valor_post('generos');
    $fecha = valor_post('fecha');
    $hora = valor_post('hora');
    $hoy = date('Y-m-d');
    $seccionPeliculas = crear_seccion_peliculas($errores['editar'] ?? '');

    // Tabla de las próximas funciones
    $consulta = $pdo->prepare(
        'SELECT f.ID_funcion AS id, f.fecha, f.hora_inicio, p.titulo, c.nombre_cine AS cine, s.numero AS sala,
                (SELECT COUNT(*) FROM Entradas e WHERE e.ID_funcion = f.ID_funcion AND e.estado = 1) AS vendidas
         FROM Funciones f
         JOIN Peliculas p ON p.ID_Pelicula = f.ID_Pelicula
         JOIN Salas s ON s.ID_Sala = f.ID_Sala
         JOIN Cines c ON c.ID_cine = s.ID_cine
         WHERE f.estado = 1 AND f.fecha >= :hoy
         ORDER BY f.fecha, f.hora_inicio, c.nombre_cine LIMIT 60'
    );
    $consulta->execute([':hoy' => $hoy]);
    $filas = '';
    foreach ($consulta->fetchAll() as $f) {
        $celdaAccion = (int)$f['vendidas'] > 0
            ? '<span class="texto-filtro">Con entradas vendidas</span>'
            : '<form method="post" action="' . $accion . '">'
              . '<input type="hidden" name="csrf" value="' . $csrf . '">'
              . '<input type="hidden" name="accion" value="cancelar_funcion">'
              . '<input type="hidden" name="id" value="' . (int)$f['id'] . '">'
              . '<button type="submit" class="btn-sec">Cancelar</button></form>';
        $filas .= '<tr><td>' . e($f['fecha']) . '</td><td>' . e($f['hora_inicio']) . '</td><td>' . e($f['titulo']) . '</td>'
                . '<td>' . e($f['cine'] . ' · Sala ' . $f['sala']) . '</td><td>' . (int)$f['vendidas'] . '</td>'
                . '<td>' . $celdaAccion . '</td></tr>';
    }
    $tabla = $filas !== ''
        ? '<div class="tabla-admin"><table><thead><tr><th>Fecha</th><th>Hora</th><th>Película</th><th>Sala</th><th>Vendidas</th><th></th></tr></thead>'
          . '<tbody>' . $filas . '</tbody></table></div>'
        : '<p class="texto-filtro">No hay funciones próximas.</p>';

    // Tabla de cuentas: el botón da o quita el rol de administrador (no se muestra en la propia cuenta)
    $yoId = (int)usuario_actual()['id'];
    $filasCuentas = '';
    foreach ($pdo->query(
        'SELECT u.ID_Usuario AS id, u.nombre_usuario AS nombre, u.correo_usuario AS correo, r.nombre_rol AS rol
         FROM Usuarios u JOIN Roles r ON r.ID_Rol = u.ID_Rol
         ORDER BY (r.nombre_rol = \'admin\') DESC, u.nombre_usuario LIMIT 100'
    )->fetchAll() as $cuenta) {
        $esAdmin = $cuenta['rol'] === 'admin';
        if ((int)$cuenta['id'] === $yoId) {
            $celda = '<span class="texto-filtro">Tu cuenta</span>';
        } else {
            $celda = '<form method="post" action="' . $accion . '">'
                   . '<input type="hidden" name="csrf" value="' . $csrf . '">'
                   . '<input type="hidden" name="accion" value="rol">'
                   . '<input type="hidden" name="id" value="' . (int)$cuenta['id'] . '">'
                   . '<input type="hidden" name="rol" value="' . ($esAdmin ? 'usuario' : 'admin') . '">'
                   . '<button type="submit" class="btn-sec">' . ($esAdmin ? 'Quitar admin' : 'Hacer admin') . '</button></form>';
        }
        $filasCuentas .= '<tr><td>' . e($cuenta['nombre']) . '</td><td>' . e($cuenta['correo']) . '</td>'
                       . '<td>' . ($esAdmin ? 'Administrador' : 'Usuario') . '</td><td>' . $celda . '</td></tr>';
    }
    $tablaCuentas = '<div class="tabla-admin"><table><thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th></th></tr></thead>'
                  . '<tbody>' . $filasCuentas . '</tbody></table></div>';

    return <<<HTML
        <section class="admin">
          <h1>Administración</h1>
          {$textoAviso}
          <p class="error" role="alert">{$errorGeneral}</p>

          <div class="admin-grid">
            <form class="tarjeta-admin" method="post" action="{$accion}">
              <h2>Agregar película</h2>
              <input type="hidden" name="csrf" value="{$csrf}">
              <input type="hidden" name="accion" value="pelicula">
              <div class="campo-admin"><label for="a-titulo">Título</label>
                <input id="a-titulo" name="titulo" type="text" maxlength="120" value="{$titulo}" required></div>
              <div class="campo-admin"><label for="a-sinopsis">Sinopsis (opcional)</label>
                <textarea id="a-sinopsis" name="sinopsis" rows="4" maxlength="1000">{$sinopsis}</textarea></div>
              <div class="campo-admin"><label for="a-duracion">Duración (minutos)</label>
                <input id="a-duracion" name="duracion" type="number" min="1" max="600" value="{$duracion}" required></div>
              <div class="campo-admin"><label for="a-clasificacion">Clasificación</label>
                <select id="a-clasificacion" name="clasificacion">{$opcClasificacion}</select></div>
              <div class="campo-admin"><label for="a-estreno">Fecha de estreno (opcional)</label>
                <input id="a-estreno" name="estreno" type="date" value="{$estreno}"></div>
              <div class="campo-admin"><label for="a-generos">Géneros, separados por comas (máx. 5)</label>
                <input id="a-generos" name="generos" type="text" placeholder="Acción, Drama" value="{$generos}"></div>
              <p class="error" role="alert">{$errorPelicula}</p>
              <button type="submit" class="btn-prim">Guardar película</button>
            </form>

            <form class="tarjeta-admin" method="post" action="{$accion}">
              <h2>Programar función</h2>
              <input type="hidden" name="csrf" value="{$csrf}">
              <input type="hidden" name="accion" value="funcion">
              <div class="campo-admin"><label for="f-pelicula">Película</label>
                <select id="f-pelicula" name="pelicula">{$opcPelicula}</select></div>
              <div class="campo-admin"><label for="f-sala">Sala</label>
                <select id="f-sala" name="sala">{$opcSala}</select></div>
              <div class="campo-admin"><label for="f-fecha">Fecha</label>
                <input id="f-fecha" name="fecha" type="date" min="{$hoy}" value="{$fecha}" required></div>
              <div class="campo-admin"><label for="f-hora">Hora de inicio</label>
                <input id="f-hora" name="hora" type="time" value="{$hora}" required></div>
              <div class="campo-admin"><label for="f-tipo">Tipo de función</label>
                <select id="f-tipo" name="tipo_funcion">{$opcTipo}</select></div>
              <p class="texto-filtro">La hora de fin se calcula con la duración de la película.</p>
              <p class="error" role="alert">{$errorFuncion}</p>
              <button type="submit" class="btn-prim">Programar función</button>
            </form>
          </div>

          {$seccionPeliculas}

          <h2>Próximas funciones</h2>
          {$tabla}

          <h2>Cuentas</h2>
          {$tablaCuentas}
        </section>
        HTML;
}
