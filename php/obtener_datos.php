<?php
// php/obtener_datos.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conexion.php';

try {
    $pdo = obtenerConexion();

    // Paleta de degradados para pósters sin imagen física
    $paletas = [
        ['#8a1630', '#1d0b2e'],
        ['#0d2a5c', '#6a2fb0'],
        ['#1a1a2e', '#5b0f1d'],
        ['#1d8fb8', '#2b2a6e'],
        ['#d98e1f', '#4a1d5c'],
    ];

    // 1. Obtener películas con su clasificación y fecha de función/programación
    $sqlPeliculas = "
        SELECT 
            p.ID_Pelicula AS id,
            p.titulo,
            p.sinopsis,
            p.duracion,
            c.nombre AS clasificacion,
            COALESCE(MIN(f.fecha), MIN(pr.fecha_inicio), DATE('now')) AS estreno,
            MAX(CASE WHEN f.estado = 1 THEN 1 ELSE 0 END) AS tiene_funcion_activa
        FROM Peliculas p
        INNER JOIN Clasificaciones c ON p.ID_Clasificacion = c.ID_clasificaciones
        LEFT JOIN Funciones f ON f.ID_Pelicula = p.ID_Pelicula
        LEFT JOIN Programaciones pr ON f.ID_Programacion = pr.ID_Programacion
        GROUP BY p.ID_Pelicula
        ORDER BY p.ID_Pelicula ASC
    ";
    $filasPeliculas = $pdo->query($sqlPeliculas)->fetchAll();

    // Preparar consulta de géneros por película
    $stmtGeneros = $pdo->prepare("SELECT nombre_genero FROM Generos WHERE ID_Pelicula = ?");

    $peliculas = [];
    foreach ($filasPeliculas as $i => $row) {
        $stmtGeneros->execute([$row['id']]);
        $generosPeli = $stmtGeneros->fetchAll(PDO::FETCH_COLUMN);

        $peliculas[] = [
            'id'            => (int) $row['id'],
            'titulo'        => $row['titulo'],
            'generos'       => $generosPeli ?: ['General'],
            'duracion'      => (int) $row['duracion'],
            'clasificacion' => $row['clasificacion'],
            'estreno'       => $row['estreno'],
            // Si tiene funciones activas (estado = 1) va a "cartelera", si no a "estreno"
            'estado'        => ((int) $row['tiene_funcion_activa'] === 1) ? 'cartelera' : 'estreno',
            'colores'       => $paletas[$i % count($paletas)],
            'imagen'        => null,
            'sinopsis'      => $row['sinopsis'] ?? 'Sin sinopsis disponible.'
        ];
    }

    // 2. Obtener lista única de géneros para el menú desplegable
    $generos = $pdo->query("SELECT DISTINCT nombre_genero FROM Generos ORDER BY nombre_genero ASC")
                   ->fetchAll(PDO::FETCH_COLUMN);

    // 3. Obtener ubicaciones y cines
    $ubicaciones = $pdo->query("SELECT Ubicacion FROM Ubicaciones ORDER BY Ubicacion ASC")
                       ->fetchAll(PDO::FETCH_COLUMN);

    $sqlCines = "
        SELECT 
            ci.nombre_cine AS nombre,
            u.Ubicacion AS ciudad,
            ('Tel: ' || ci.telefono) AS direccion
        FROM Cines ci
        INNER JOIN Ubicaciones u ON ci.ID_Ubicacion = u.ID_ubicacion
        ORDER BY ci.nombre_cine ASC
    ";
    $cines = $pdo->query($sqlCines)->fetchAll();

    echo json_encode([
        'ok'          => true,
        'peliculas'   => $peliculas,
        'generos'     => $generos,
        'ciudades'    => $ubicaciones,
        'cines'       => $cines
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'error' => 'Error al consultar la base de datos: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}