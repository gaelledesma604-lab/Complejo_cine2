<?php
/* ==========================================================
   funciones.php: horarios de las películas, mapa de butacas,
   compra de entradas y "Mis entradas".
   Usa las tablas Funciones, Salas, Entradas, Tarifas,
   Tipo_entrada y Clasificaciones_tipo_entrada.
   ========================================================== */

const DIAS_SEMANA = ['', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];
const MAX_BUTACAS_POR_COMPRA = 8;

/* ---------- Fechas y precios ---------- */

/* "2026-11-23" -> "lunes" (date('N') da 1 = lunes ... 7 = domingo). */
function nombre_dia(string $iso): string
{
    return DIAS_SEMANA[(int)date('N', strtotime($iso))];
}

/* "2026-11-23" -> "Hoy · Lunes 23 de noviembre" (o "Mañana · ..." o solo "Martes 24 de noviembre"). */
function etiqueta_fecha(string $iso): string
{
    [, $mes, $dia] = explode('-', $iso);
    $texto = ucfirst(nombre_dia($iso)) . ' ' . (int)$dia . ' de ' . MESES[(int)$mes - 1];
    if ($iso === date('Y-m-d')) return 'Hoy · ' . $texto;
    if ($iso === date('Y-m-d', strtotime('+1 day'))) return 'Mañana · ' . $texto;
    return $texto;
}

/* 6000 -> "$6.000" */
function formatear_precio(int $precio): string
{
    return '$' . number_format($precio, 0, ',', '.');
}

/* ---------- Consultas ---------- */

/* Funciones a la venta de una película que todavía no empezaron.
   Respeta lo que la persona eligió en los menús: si eligió un cine o una ubicación, solo esas. */
function listar_funciones(int $idPelicula): array
{
    $sel = seleccion();
    $sql = "SELECT f.ID_funcion AS id, f.fecha, f.hora_inicio, f.hora_fin, s.numero AS sala,
                   c.ID_cine AS cine_id, c.nombre_cine AS cine, tf.nombre_TF AS tipo,
                   s.capacidad - (SELECT COUNT(*) FROM Entradas e
                                  WHERE e.ID_funcion = f.ID_funcion AND e.estado = 1) AS libres
            FROM Funciones f
            JOIN Salas s ON s.ID_Sala = f.ID_Sala
            JOIN Cines c ON c.ID_cine = s.ID_cine
            JOIN Tipo_funcion tf ON tf.ID_Tipo_funcion = f.ID_Tipo_funcion
            WHERE f.ID_Pelicula = :pelicula AND f.estado = 1 AND s.estado = 1 AND f.fecha >= :hoy";
    $params = [':pelicula' => $idPelicula, ':hoy' => date('Y-m-d')];

    if ($sel['cine'] !== 0) {
        $sql .= ' AND c.ID_cine = :cine';
        $params[':cine'] = $sel['cine'];
    }
    if ($sel['ubicacion'] !== 0) {
        $sql .= ' AND c.ID_Ubicacion = :ubicacion';
        $params[':ubicacion'] = $sel['ubicacion'];
    }
    $sql .= ' ORDER BY f.fecha, c.nombre_cine, f.hora_inicio';

    $consulta = conectar()->prepare($sql);
    $consulta->execute($params);

    // Las de hoy que ya empezaron se descartan aquí (la consulta solo compara la fecha)
    $ahora = date('Y-m-d H:i');
    return array_values(array_filter(
        $consulta->fetchAll(),
        fn($f) => $f['fecha'] . ' ' . $f['hora_inicio'] > $ahora
    ));
}

/* Una función con todo lo que hace falta para venderla (o null si no existe). */
function buscar_funcion(int $id): ?array
{
    $consulta = conectar()->prepare(
        'SELECT f.ID_funcion AS id, f.estado, f.fecha, f.hora_inicio, f.hora_fin,
                s.numero AS sala, s.capacidad, s.estado AS sala_estado,
                c.ID_cine AS cine_id, c.nombre_cine AS cine,
                p.ID_Pelicula AS pelicula_id, p.titulo,
                p.ID_Clasificacion AS clasificacion_id, cl.nombre_clasificacion AS clasificacion,
                tf.nombre_TF AS tipo
         FROM Funciones f
         JOIN Salas s ON s.ID_Sala = f.ID_Sala
         JOIN Cines c ON c.ID_cine = s.ID_cine
         JOIN Peliculas p ON p.ID_Pelicula = f.ID_Pelicula
         JOIN Clasificaciones cl ON cl.ID_Clasificacion = p.ID_Clasificacion
         JOIN Tipo_funcion tf ON tf.ID_Tipo_funcion = f.ID_Tipo_funcion
         WHERE f.ID_funcion = :id'
    );
    $consulta->execute([':id' => $id]);
    $fila = $consulta->fetch();
    return $fila ?: null;
}

/* ¿Se pueden vender entradas? La función debe estar activa (estado 1), la sala habilitada
   y la función no puede haber empezado. */
function funcion_vendible(array $f): bool
{
    return (int)$f['estado'] === 1
        && (int)$f['sala_estado'] === 1
        && $f['fecha'] . ' ' . $f['hora_inicio'] > date('Y-m-d H:i');
}

/* Butacas ya vendidas de una función, como ['A-5' => true, ...]. Las entradas canceladas (estado 0) no cuentan. */
function butacas_ocupadas(int $idFuncion): array
{
    $consulta = conectar()->prepare('SELECT fila, numero_butaca FROM Entradas WHERE ID_funcion = :id AND estado = 1');
    $consulta->execute([':id' => $idFuncion]);
    $ocupadas = [];
    foreach ($consulta->fetchAll() as $fila) {
        $ocupadas[$fila['fila'] . '-' . $fila['numero_butaca']] = true;
    }
    return $ocupadas;
}

/* Tipos de entrada que admite la clasificación de la película (tabla Clasificaciones_tipo_entrada). */
function tipos_permitidos(int $idClasificacion): array
{
    $consulta = conectar()->prepare(
        'SELECT te.ID_tipo_entrada AS id, te.nombre_tipo_entrada AS nombre, te.descripcion
         FROM Clasificaciones_tipo_entrada ct
         JOIN Tipo_entrada te ON te.ID_tipo_entrada = ct.ID_Tipo_entrada
         WHERE ct.ID_Clasificacion = :clasificacion
         ORDER BY te.ID_tipo_entrada'
    );
    $consulta->execute([':clasificacion' => $idClasificacion]);
    return $consulta->fetchAll();
}

/* Precio de un tipo de entrada para esa función: la tarifa del cine, del día de la semana
   y de la franja horaria que contiene la hora de inicio. Devuelve null si no hay tarifa cargada. */
function precio_entrada(array $f, int $idTipo): ?int
{
    $consulta = conectar()->prepare(
        'SELECT precio FROM Tarifas
         WHERE ID_cine = :cine AND ID_Tipo_entrada = :tipo AND ID_dia_semana = :dia
           AND hora_desde <= :hora_a AND hora_hasta >= :hora_b
         ORDER BY hora_desde DESC LIMIT 1'
    );
    $consulta->execute([
        ':cine'   => (int)$f['cine_id'],
        ':tipo'   => $idTipo,
        ':dia'    => (int)date('N', strtotime($f['fecha'])), // 1 = lunes ... 7 = domingo, igual que Dia_semana
        ':hora_a' => $f['hora_inicio'],
        ':hora_b' => $f['hora_inicio'],
    ]);
    $precio = $consulta->fetchColumn();
    return $precio === false ? null : (int)$precio;
}

/* ---------- Butacas ----------
   La base solo guarda la capacidad de la sala, no su plano. El plano se calcula:
   filas A, B, C... con 10 butacas cada una (o más si la sala es muy grande, para no pasar de la Z). */

function disposicion_sala(int $capacidad): array
{
    $porFila = max(10, (int)ceil($capacidad / 26));
    return ['por_fila' => $porFila, 'filas' => (int)ceil($capacidad / $porFila)];
}

/* ¿Existe esa butaca en una sala de esa capacidad? */
function butaca_valida(string $fila, int $numero, int $capacidad): bool
{
    $d = disposicion_sala($capacidad);
    $indiceFila = ord($fila) - ord('A');
    if ($indiceFila < 0 || $indiceFila >= $d['filas'] || $numero < 1 || $numero > $d['por_fila']) {
        return false;
    }
    return $indiceFila * $d['por_fila'] + $numero <= $capacidad;
}

/* ---------- Compra ---------- */

/* COMPRA: valida todo en el servidor (nunca se confía en lo que manda el formulario),
   guarda una entrada por butaca dentro de una transacción y redirige a "Mis entradas".
   Devuelve el mensaje de error si algo falla. */
function procesar_compra(array $f, int $idUsuario): string
{
    if (!csrf_valido()) return 'La sesión del formulario venció. Inténtalo de nuevo.';
    if (!funcion_vendible($f)) return 'Esta función ya no está disponible para la venta.';

    $tipo = (int)($_POST['tipo'] ?? 0);
    $permitidos = array_map('intval', array_column(tipos_permitidos((int)$f['clasificacion_id']), 'id'));
    if (!in_array($tipo, $permitidos, true)) return 'Elige un tipo de entrada válido.';

    $precio = precio_entrada($f, $tipo);
    if ($precio === null) return 'No hay una tarifa cargada para ese tipo de entrada en este horario.';

    $elegidas = $_POST['butacas'] ?? [];
    if (!is_array($elegidas)) $elegidas = [];
    $elegidas = array_values(array_unique(array_filter($elegidas, 'is_string')));
    if (!$elegidas) return 'Elige al menos una butaca.';
    if (count($elegidas) > MAX_BUTACAS_POR_COMPRA) {
        return 'Puedes comprar hasta ' . MAX_BUTACAS_POR_COMPRA . ' entradas por compra.';
    }

    $butacas = [];
    foreach ($elegidas as $texto) {
        if (!preg_match('/^([A-Z])-([0-9]{1,3})$/', $texto, $partes)
            || !butaca_valida($partes[1], (int)$partes[2], (int)$f['capacidad'])) {
            return 'Una de las butacas elegidas no existe.';
        }
        $butacas[] = [$partes[1], (int)$partes[2]];
    }

    $pdo = conectar();
    try {
        $pdo->beginTransaction(); // o se guardan todas las entradas o ninguna
        $insertar = $pdo->prepare(
            'INSERT INTO Entradas (fecha_venta, precio_cobrado, estado, fila, numero_butaca, ID_funcion, ID_tipo_entrada, ID_Usuario)
             VALUES (:fecha, :precio, 1, :fila, :numero, :funcion, :tipo, :usuario)'
        );
        foreach ($butacas as [$fila, $numero]) {
            $insertar->execute([
                ':fecha'   => date('Y-m-d'),
                ':precio'  => $precio,
                ':fila'    => $fila,
                ':numero'  => $numero,
                ':funcion' => (int)$f['id'],
                ':tipo'    => $tipo,
                ':usuario' => $idUsuario,
            ]);
        }
        $pdo->commit();
    } catch (PDOException $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('CineNova, compra fallida: ' . $error->getMessage()); // con php -S se ve en la terminal
        if (strpos($error->getMessage(), 'ID_Usuario') !== false) {
            return 'La base de datos es de una versión anterior. Bórrala (database/cinenova.db) y ejecuta de nuevo: php database/instalar.php';
        }
        // El índice único ux_entrada_butaca impide vender dos veces la misma butaca (por ejemplo, si dos
        // personas la eligen casi a la vez): la segunda compra falla con un error que contiene "UNIQUE".
        if (strpos($error->getMessage(), 'UNIQUE') !== false) {
            return 'Alguna de las butacas se vendió mientras elegías. Elige otras e inténtalo de nuevo.';
        }
        return 'No se pudo completar la compra. Inténtalo de nuevo.';
    }

    $_SESSION['aviso'] = count($butacas) === 1
        ? 'Compra confirmada: 1 entrada.'
        : 'Compra confirmada: ' . count($butacas) . ' entradas.';
    header('Location: ' . BASE . 'pages/mis_entradas.php');
    exit;
}

/* ---------- Mis entradas ---------- */

function entradas_del_usuario(int $idUsuario): array
{
    $consulta = conectar()->prepare(
        'SELECT e.ID_entrada AS id, e.fila, e.numero_butaca, e.precio_cobrado, e.estado, e.fecha_venta,
                te.nombre_tipo_entrada AS tipo,
                f.fecha, f.hora_inicio, s.numero AS sala, c.nombre_cine AS cine,
                p.titulo, p.ID_Pelicula AS pelicula_id
         FROM Entradas e
         JOIN Funciones f ON f.ID_funcion = e.ID_funcion
         JOIN Salas s ON s.ID_Sala = f.ID_Sala
         JOIN Cines c ON c.ID_cine = s.ID_cine
         JOIN Peliculas p ON p.ID_Pelicula = f.ID_Pelicula
         JOIN Tipo_entrada te ON te.ID_tipo_entrada = e.ID_tipo_entrada
         WHERE e.ID_Usuario = :usuario
         ORDER BY f.fecha DESC, f.hora_inicio DESC, e.fila, e.numero_butaca'
    );
    $consulta->execute([':usuario' => $idUsuario]);
    return $consulta->fetchAll();
}

/* CANCELAR: solo se puede cancelar una entrada propia, activa, de una función que no empezó.
   Al pasar a estado 0 la butaca queda libre otra vez. Devuelve un mensaje para mostrar. */
function cancelar_entrada(int $idEntrada, int $idUsuario): string
{
    if (!csrf_valido()) return 'La sesión del formulario venció. Inténtalo de nuevo.';

    $pdo = conectar();
    $consulta = $pdo->prepare(
        'SELECT e.estado, f.fecha, f.hora_inicio
         FROM Entradas e JOIN Funciones f ON f.ID_funcion = e.ID_funcion
         WHERE e.ID_entrada = :id AND e.ID_Usuario = :usuario'
    );
    $consulta->execute([':id' => $idEntrada, ':usuario' => $idUsuario]);
    $fila = $consulta->fetch();

    if (!$fila) return 'No encontramos esa entrada.';
    if ((int)$fila['estado'] !== 1) return 'Esa entrada ya estaba cancelada.';
    if ($fila['fecha'] . ' ' . $fila['hora_inicio'] <= date('Y-m-d H:i')) {
        return 'No se puede cancelar una entrada de una función que ya empezó.';
    }

    $pdo->prepare('UPDATE Entradas SET estado = 0 WHERE ID_entrada = :id AND ID_Usuario = :usuario')
        ->execute([':id' => $idEntrada, ':usuario' => $idUsuario]);
    return 'Entrada cancelada.';
}

/* ---------- HTML ---------- */

/* Sección "Funciones" de la ficha de una película: días, cines y horarios como enlaces a la compra. */
function crear_horarios(array $funciones): string
{
    if (!$funciones) {
        return '<section class="horarios"><h2>Funciones</h2>'
             . '<p class="texto-filtro">No hay funciones programadas con el cine y la ubicación elegidos.</p></section>';
    }

    $porDia = [];
    foreach ($funciones as $f) {
        $porDia[$f['fecha']][$f['cine']][] = $f;
    }

    $html = '<section class="horarios"><h2>Funciones</h2>';
    foreach ($porDia as $fecha => $cines) {
        $html .= '<div class="dia-funciones"><h3>' . e(etiqueta_fecha((string)$fecha)) . '</h3>';
        foreach ($cines as $cine => $lista) {
            $html .= '<p class="cine-funciones">' . e((string)$cine) . '</p><ul class="lista-horarios">';
            foreach ($lista as $f) {
                $libres = (int)$f['libres'];
                $detalle = 'Sala ' . $f['sala'] . ' · ' . $f['tipo']
                         . ($libres > 0 ? ' · ' . $libres . ' libres' : ' · Agotada');
                $contenido = e($f['hora_inicio']) . '<small>' . e($detalle) . '</small>';
                if ($libres > 0) {
                    $enlace = e(BASE . 'pages/comprar.php?funcion=' . $f['id']);
                    $html .= '<li><a class="horario" href="' . $enlace . '">' . $contenido . '</a></li>';
                } else {
                    $html .= '<li><span class="horario agotada">' . $contenido . '</span></li>';
                }
            }
            $html .= '</ul>';
        }
        $html .= '</div>';
    }
    return $html . '</section>';
}

/* Mapa de butacas: cada butaca libre es una casilla (checkbox) y las ocupadas no se pueden marcar. */
function crear_mapa_butacas(array $f, array $ocupadas, array $marcadas): string
{
    $capacidad = (int)$f['capacidad'];
    $d = disposicion_sala($capacidad);
    $html = '<div class="pantalla">PANTALLA</div><div class="sala-mapa">';

    for ($i = 0; $i < $d['filas']; $i++) {
        $fila = chr(ord('A') + $i);
        $html .= '<div class="fila-butacas"><span class="letra-fila">' . $fila . '</span>';
        for ($n = 1; $n <= $d['por_fila']; $n++) {
            if ($i * $d['por_fila'] + $n > $capacidad) break;
            $codigo = $fila . '-' . $n;
            if (isset($ocupadas[$codigo])) {
                $html .= '<span class="butaca ocupada" title="Ocupada" aria-label="Butaca ' . $codigo . ' ocupada">' . $n . '</span>';
            } else {
                $marca = in_array($codigo, $marcadas, true) ? ' checked' : '';
                $html .= '<label class="butaca libre" title="Butaca ' . $codigo . '">'
                       . '<input type="checkbox" name="butacas[]" value="' . $codigo . '"' . $marca . '>'
                       . '<span>' . $n . '</span></label>';
            }
        }
        $html .= '</div>';
    }
    return $html . '</div>';
}

/* Pantalla de compra: resumen de la función, tipo de entrada y mapa de butacas. */
function formulario_compra(array $f, string $error, array $marcadas, int $tipoElegido): string
{
    $volver = e(BASE . 'pages/pelicula.php?id=' . $f['pelicula_id']);
    $titulo = e($f['titulo']);
    $resumen = e(etiqueta_fecha($f['fecha']) . ' · ' . $f['hora_inicio'] . ' h · ' . $f['cine']
               . ' · Sala ' . $f['sala'] . ' · ' . $f['tipo'] . ' · ' . $f['clasificacion']);

    if (!funcion_vendible($f)) {
        return <<<HTML
            <a class="volver" href="{$volver}">← Volver a la película</a>
            <section class="compra">
              <h1>{$titulo}</h1>
              <p class="texto-filtro">{$resumen}</p>
              <p class="error" role="alert">Esta función ya no está disponible para la venta.</p>
            </section>
            HTML;
    }

    $opciones = '';
    foreach (tipos_permitidos((int)$f['clasificacion_id']) as $tipo) {
        $precio = precio_entrada($f, (int)$tipo['id']);
        if ($precio === null) continue; // sin tarifa no se puede vender ese tipo
        $texto = $tipo['nombre'] . ' — ' . formatear_precio($precio);
        $elegida = (int)$tipo['id'] === $tipoElegido ? ' selected' : '';
        $opciones .= '<option value="' . (int)$tipo['id'] . '"' . $elegida . '>' . e($texto) . '</option>';
    }

    $mapa = crear_mapa_butacas($f, butacas_ocupadas((int)$f['id']), $marcadas);
    $csrf = e(token_csrf());
    $error = e($error);
    $accion = e(BASE . 'pages/comprar.php?funcion=' . $f['id']);
    $maximo = MAX_BUTACAS_POR_COMPRA;

    return <<<HTML
        <a class="volver" href="{$volver}">← Volver a la película</a>
        <section class="compra">
          <h1>{$titulo}</h1>
          <p class="texto-filtro">{$resumen}</p>
          <form method="post" action="{$accion}">
            <input type="hidden" name="csrf" value="{$csrf}">
            <div class="campo-tipo">
              <label for="tipo">Tipo de entrada</label>
              <select id="tipo" name="tipo">{$opciones}</select>
              <small>El tipo elegido se aplica a todas las butacas de esta compra (máximo {$maximo}).</small>
            </div>
            {$mapa}
            <p class="leyenda">
              <span class="muestra libre"></span> Libre
              <span class="muestra marcada"></span> Elegida
              <span class="muestra ocupada"></span> Ocupada
            </p>
            <p class="error" role="alert">{$error}</p>
            <button type="submit" class="btn-prim">Confirmar compra</button>
          </form>
          <p class="texto-filtro nota">CineNova es un proyecto de ejemplo: no se cobra ninguna entrada real.</p>
        </section>
        HTML;
}

/* Lista de entradas del usuario, separada en próximas y anteriores/canceladas. */
function crear_lista_entradas(array $entradas): string
{
    if (!$entradas) {
        return '<div class="vacio"><p>Todavía no compraste entradas.</p>'
             . '<a class="btn-prim" href="' . e(BASE . 'index.php#cartelera') . '">Ver la cartelera</a></div>';
    }

    $ahora = date('Y-m-d H:i');
    $proximas = '';
    $anteriores = '';
    $csrf = e(token_csrf());
    $accion = e(BASE . 'pages/mis_entradas.php');

    foreach ($entradas as $en) {
        $activa = (int)$en['estado'] === 1;
        $futura = $en['fecha'] . ' ' . $en['hora_inicio'] > $ahora;
        $titulo = e($en['titulo']);
        $enlace = e(BASE . 'pages/pelicula.php?id=' . $en['pelicula_id']);
        $donde = e($en['cine'] . ' · Sala ' . $en['sala']);
        $cuando = e(etiqueta_fecha($en['fecha']) . ' · ' . $en['hora_inicio'] . ' h');
        $butaca = e('Butaca ' . $en['fila'] . $en['numero_butaca'] . ' · ' . $en['tipo'] . ' · ' . formatear_precio((int)$en['precio_cobrado']));
        $estado = $activa ? '' : '<span class="etiqueta-cancelada">Cancelada</span>';

        $boton = '';
        if ($activa && $futura) {
            $boton = '<form method="post" action="' . $accion . '">'
                   . '<input type="hidden" name="csrf" value="' . $csrf . '">'
                   . '<input type="hidden" name="cancelar" value="' . (int)$en['id'] . '">'
                   . '<button type="submit" class="btn-sec">Cancelar entrada</button></form>';
        }

        $tarjeta = <<<HTML
            <article class="entrada">
              <div>
                <h3><a href="{$enlace}">{$titulo}</a> {$estado}</h3>
                <p>{$cuando}</p>
                <p class="meta">{$donde}</p>
                <p class="meta">{$butaca}</p>
              </div>
              {$boton}
            </article>
            HTML;

        if ($activa && $futura) {
            $proximas .= $tarjeta;
        } else {
            $anteriores .= $tarjeta;
        }
    }

    $html = '';
    if ($proximas !== '') $html .= '<h2>Próximas funciones</h2><div class="lista-entradas">' . $proximas . '</div>';
    if ($anteriores !== '') $html .= '<h2>Anteriores y canceladas</h2><div class="lista-entradas">' . $anteriores . '</div>';
    return $html;
}
