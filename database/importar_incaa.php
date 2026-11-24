<?php
/* ==========================================================
   importar_incaa.php: carga en la base las películas del "Informe Semanal" que publica
   el INCAA como dato abierto (licencia Creative Commons Attribution 4.0).
   Fuente: https://datos.gob.ar/dataset/incaa-informe-semanal

   Uso, desde la terminal:

       php database/importar_incaa.php                       usa database/informe_semanal.csv
       php database/importar_incaa.php ruta/al/informe.csv   usa ese archivo
       php database/importar_incaa.php https://.../x.csv     lo descarga de esa dirección

   - Las columnas se reconocen por su nombre, no por su posición. Hacen falta "Titulo" y
     "pelicula_cod"; "Fecha Estreno" es opcional. Todo lo demás se ignora.
   - El informe no trae género, duración, sinopsis ni clasificación, y no se inventan:
     las películas quedan con duración vacía y clasificación "Sin calificar".
   - Se puede ejecutar varias veces: cada película se reconoce por su código del INCAA, así que las
     que ya existen no se duplican ni se pisan (solo se completa la fecha de estreno si faltaba), así que lo que
     edites en el panel de administración se conserva. Las que dejan de aparecer en el informe NO se borran.
   - instalar.php usa este mismo código cuando existe database/informe_semanal.csv.
   ========================================================== */

const INFORME_LOCAL = __DIR__ . '/informe_semanal.csv';
const CLASIFICACION_SIN_DATO = 'Sin calificar';

/* ---------- Lectura del archivo ---------- */

/* Lee el informe desde un archivo o una dirección web y lo devuelve en UTF-8. */
function incaa_leer_informe(string $origen): string
{
    if (preg_match('#^https?://#i', $origen)) {
        $bytes = incaa_descargar($origen);
    } else {
        if (!is_file($origen)) {
            throw new RuntimeException('No existe el archivo: ' . $origen);
        }
        $bytes = file_get_contents($origen);
    }
    if ($bytes === false || $bytes === '') {
        throw new RuntimeException('El archivo está vacío o no se pudo leer.');
    }
    return incaa_a_utf8($bytes);
}

function incaa_descargar(string $url): string
{
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => 'CineNova/1.0',
        ]);
        $datos = curl_exec($curl);
        $codigo = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $motivo = curl_error($curl);
        curl_close($curl);
        if ($datos === false || $codigo >= 400) {
            throw new RuntimeException('No se pudo descargar ' . $url . ' (' . ($motivo !== '' ? $motivo : 'HTTP ' . $codigo) . ').');
        }
        return $datos;
    }

    $contexto = stream_context_create(['http' => ['timeout' => 30, 'user_agent' => 'CineNova/1.0']]);
    $datos = @file_get_contents($url, false, $contexto);
    if ($datos === false) {
        throw new RuntimeException('No se pudo descargar ' . $url . '.');
    }
    return $datos;
}

/* El informe del INCAA viene en UTF-16 (con BOM). Se detecta la codificación y se pasa a UTF-8. */
function incaa_a_utf8(string $bytes): string
{
    if (strncmp($bytes, "\xFF\xFE", 2) === 0) return incaa_convertir(substr($bytes, 2), 'UTF-16LE');
    if (strncmp($bytes, "\xFE\xFF", 2) === 0) return incaa_convertir(substr($bytes, 2), 'UTF-16BE');
    if (strncmp($bytes, "\xEF\xBB\xBF", 3) === 0) return substr($bytes, 3);

    // Sin BOM: muchos bytes nulos al principio indican UTF-16; si no es UTF-8 válido se asume ISO-8859-1
    if (strpos(substr($bytes, 0, 200), "\0") !== false) return incaa_convertir($bytes, 'UTF-16LE');
    return preg_match('//u', $bytes) ? $bytes : incaa_convertir($bytes, 'ISO-8859-1');
}

function incaa_convertir(string $texto, string $desde): string
{
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($texto, 'UTF-8', $desde);
    }
    if (function_exists('iconv')) {
        $convertido = iconv($desde, 'UTF-8//IGNORE', $texto);
        if ($convertido !== false) return $convertido;
    }
    throw new RuntimeException('Hace falta la extensión mbstring (o iconv) de PHP para leer este archivo. Actívala en php.ini.');
}

/* ---------- Interpretación de los datos ---------- */

/* Nombre de columna comparable: sin tildes ni mayúsculas ("Fecha Estreno" = "fecha estreno"). */
function incaa_clave(string $texto): string
{
    $sinTildes = strtr(trim($texto), [
        'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ü' => 'u', 'Ñ' => 'n',
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
    ]);
    return strtolower($sinTildes);
}

/* "29/10/2026" -> "2026-10-29". Devuelve null si está vacía o no es una fecha real. */
function incaa_fecha(string $texto): ?string
{
    if (!preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})#', trim($texto), $m)) return null;
    if (!checkdate((int)$m[2], (int)$m[1], (int)$m[3])) return null;
    return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
}

/* El informe escribe los títulos en MAYÚSCULAS ("EL GATO CON SOMBRERO"). Se pasan a formato título
   ("El Gato con Sombrero"). Un título que ya tiene minúsculas se deja tal cual. */
function incaa_titulo(string $titulo): string
{
    $titulo = trim((string)preg_replace('/\s+/u', ' ', $titulo));
    if (!function_exists('mb_convert_case') || $titulo !== mb_strtoupper($titulo, 'UTF-8')) {
        return $titulo;
    }

    $chicas = ['de', 'del', 'la', 'el', 'los', 'las', 'y', 'e', 'o', 'u', 'a', 'al', 'en', 'un', 'una', 'por', 'con', 'para', 'sin'];
    $palabras = explode(' ', $titulo);
    foreach ($palabras as $i => $palabra) {
        $minuscula = mb_strtolower($palabra, 'UTF-8');
        $anterior = $i > 0 ? $palabras[$i - 1] : '';
        $inicioDeFrase = $i === 0 || substr($anterior, -1) === ':'; // la primera palabra y la que sigue a ":" van con mayúscula

        if (preg_match('/^(II|III|IV|VI|VII|VIII|IX|X|XI|XII)$/', $palabra)) {
            continue; // números romanos: se dejan en mayúsculas
        }
        if (!$inicioDeFrase && in_array($minuscula, $chicas, true)) {
            $palabras[$i] = $minuscula;
        } else {
            $palabras[$i] = mb_convert_case($minuscula, MB_CASE_TITLE, 'UTF-8');
        }
    }
    return implode(' ', $palabras);
}

/* Convierte el texto del informe en una lista de ['codigo', 'titulo', 'estreno'].
   Busca la fila de encabezados (el archivo del INCAA trae una fila de relleno antes) y
   prueba tabulador, punto y coma y coma como separador. */
function incaa_parsear(string $utf8): array
{
    $columnas = null;
    $separador = "\t";
    $filas = [];

    foreach (preg_split('/\r\n|\r|\n/', $utf8) as $linea) {
        if (trim($linea) === '') continue;

        if ($columnas === null) {
            foreach (["\t", ';', ','] as $prueba) {
                $claves = array_map('incaa_clave', str_getcsv($linea, $prueba, '"', ''));
                if (in_array('titulo', $claves, true) && in_array('pelicula_cod', $claves, true)) {
                    $columnas = array_flip($claves);
                    $separador = $prueba;
                    break;
                }
            }
            continue; // esta línea era el encabezado o relleno anterior a él
        }

        $celdas = str_getcsv($linea, $separador, '"', '');
        $colEstreno = $columnas['fecha estreno'] ?? $columnas['fecha_estreno'] ?? null;
        $filas[] = [
            'codigo'  => trim($celdas[$columnas['pelicula_cod']] ?? ''),
            'titulo'  => trim($celdas[$columnas['titulo']] ?? ''),
            'estreno' => $colEstreno === null ? '' : trim($celdas[$colEstreno] ?? ''),
        ];
    }

    if ($columnas === null) {
        throw new RuntimeException('No parece el "Informe Semanal" del INCAA: no se encontraron las columnas "Titulo" y "pelicula_cod".');
    }
    return $filas;
}

/* ---------- Carga en la base ---------- */

/* Importa las películas. Devuelve ['nuevas' => n, 'actualizadas' => n, 'omitidas' => [descripciones]]. */
function incaa_importar(PDO $pdo, string $utf8): array
{
    $filas = incaa_parsear($utf8);

    try {
        $consulta = $pdo->prepare('SELECT ID_Clasificacion FROM Clasificaciones WHERE nombre_clasificacion = :nombre');
        $consulta->execute([':nombre' => CLASIFICACION_SIN_DATO]);
        $idClasificacion = $consulta->fetchColumn();
        $buscar = $pdo->prepare('SELECT ID_Pelicula FROM Peliculas WHERE codigo_incaa = :codigo');
    } catch (PDOException $error) {
        throw new RuntimeException('La base es de una versión anterior. Bórrala (database/cinenova.db) y ejecuta de nuevo: php database/instalar.php');
    }
    if ($idClasificacion === false) {
        throw new RuntimeException('Falta la clasificación "' . CLASIFICACION_SIN_DATO . '" en la base. Bórrala (database/cinenova.db) y ejecuta de nuevo: php database/instalar.php');
    }

    $actualizar = $pdo->prepare(
        'UPDATE Peliculas SET fecha_estreno = COALESCE(fecha_estreno, :estreno) WHERE ID_Pelicula = :id'
    );
    $insertar = $pdo->prepare(
        'INSERT INTO Peliculas (titulo, duracion, ID_Clasificacion, fecha_estreno, codigo_incaa)
         VALUES (:titulo, NULL, :clasificacion, :estreno, :codigo)'
    );

    $resultado = ['nuevas' => 0, 'actualizadas' => 0, 'omitidas' => []];
    $vistos = [];

    $pdo->beginTransaction(); // o se importa todo o nada
    try {
        foreach ($filas as $fila) {
            $titulo = incaa_titulo($fila['titulo']);
            if ($titulo === '' && $fila['codigo'] === '') continue; // fila vacía

            if ($fila['codigo'] === '' || $titulo === '') {
                $resultado['omitidas'][] = ($titulo !== '' ? $titulo : '(sin título)') . ': falta el código o el título';
                continue;
            }
            // En el informe también figuran programaciones de salas que no son una película
            if (preg_match('/^programaci[oó]n\b/iu', $titulo)) {
                $resultado['omitidas'][] = $titulo . ': es una programación, no una película';
                continue;
            }
            if (isset($vistos[$fila['codigo']])) continue; // código repetido en el archivo
            $vistos[$fila['codigo']] = true;

            $estreno = incaa_fecha($fila['estreno']);
            $buscar->execute([':codigo' => $fila['codigo']]);
            $idExistente = $buscar->fetchColumn();

            if ($idExistente !== false) {
                $actualizar->execute([':estreno' => $estreno, ':id' => (int)$idExistente]);
                $resultado['actualizadas']++;
            } else {
                $insertar->execute([
                    ':titulo' => $titulo, ':clasificacion' => (int)$idClasificacion,
                    ':estreno' => $estreno, ':codigo' => $fila['codigo'],
                ]);
                $resultado['nuevas']++;
            }
        }
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    return $resultado;
}

/* Imprime en la terminal lo que hizo la importación. */
function incaa_mostrar(array $r): void
{
    echo 'Películas nuevas: ' . $r['nuevas'] . ' | ya existían: ' . $r['actualizadas'] . ' | omitidas: ' . count($r['omitidas']) . "\n";
    foreach ($r['omitidas'] as $motivo) {
        echo '  omitida - ' . $motivo . "\n";
    }
}

/* ---------- Ejecución desde la terminal ---------- */

function incaa_main(array $argv): void
{
    if (!extension_loaded('pdo_sqlite')) {
        exit("Falta la extensión pdo_sqlite de PHP. Actívala en php.ini (línea extension=pdo_sqlite).\n");
    }
    require_once __DIR__ . '/../php/conexion.php'; // define conectar() (y avisa si la base no existe)

    $origen = $argv[1] ?? INFORME_LOCAL;
    try {
        $resultado = incaa_importar(conectar(), incaa_leer_informe($origen));
    } catch (Throwable $error) {
        exit('Error: ' . $error->getMessage() . "\n");
    }
    echo 'Informe importado: ' . $origen . "\n";
    incaa_mostrar($resultado);
    echo "Las películas nuevas no tienen funciones: prográmalas desde el panel de administración.\n";
}

// Solo se ejecuta si el script se llamó directamente (no cuando lo carga instalar.php)
if (PHP_SAPI === 'cli' && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    incaa_main($argv);
}
