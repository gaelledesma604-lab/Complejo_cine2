-- ============================================================
-- datos_cartelera.sql
-- Pasa a la base de datos las 14 películas que antes estaban escritas
-- dentro de js/peliculas.js (el arreglo PELICULAS).
--
-- Ejecutar DESPUÉS de complejo_cines.sql (el esquema debe tener la columna
-- Peliculas.fecha_estreno). Se puede correr más de una vez sin duplicar nada.
-- ============================================================
PRAGMA foreign_keys = ON;
BEGIN TRANSACTION;

-- ---------- Películas ----------
-- Se arma una tabla temporal "v" con los datos y se insertan solo las películas
-- cuyo título todavía no existe. La clasificación se busca por su nombre (ATP, +13...).
INSERT INTO Peliculas (titulo, sinopsis, fecha_estreno, fecha_carga_pelicula, duracion, ID_Clasificacion)
SELECT v.titulo, v.sinopsis, v.estreno, date('now'), v.duracion, c.ID_Clasificacion
FROM (
    SELECT 'Eclipse Carmesí' AS titulo, 'Cuando el sol se apaga durante tres minutos, una piloto de rescate debe cruzar un desierto helado para entregar una carga que podría devolver la luz al mundo.' AS sinopsis, '2026-10-30' AS estreno, 128 AS duracion, '+13' AS clasif
    UNION ALL SELECT 'Risas en el Ascensor', 'Cinco desconocidos quedan atrapados en un ascensor la noche de fin de año. Lo que empieza como un desastre termina siendo la mejor fiesta de sus vidas.', '2026-11-06', 98, 'ATP'
    UNION ALL SELECT 'La Casa del Silencio', 'Una familia se muda a una casa donde nadie logra hablar en voz alta. Pronto descubren que algo escucha cada susurro.', '2026-10-16', 104, '+16'
    UNION ALL SELECT 'Órbita Cero', 'La tripulación de una estación espacial pierde contacto con la Tierra y debe decidir si regresar a un planeta que quizás ya no los espera.', '2026-11-13', 142, '+13'
    UNION ALL SELECT 'Cartas a Elena', 'Un cartero jubilado encuentra cartas de amor que nunca llegaron a destino y decide recorrer el país para entregarlas, treinta años tarde.', '2026-11-06', 112, '+13'
    UNION ALL SELECT 'Pingüino Ninja', 'Pipo es el pingüino más torpe de la Antártida, pero sueña con ser guardián del hielo. Un maestro inesperado le enseñará que el valor no depende de la altura.', '2026-10-23', 91, 'ATP'
    UNION ALL SELECT 'Furia en la Autopista', 'Una conductora de camiones descubre que su carga es buscada por una banda armada. Tiene 600 kilómetros de ruta para sobrevivir.', '2026-11-13', 119, '+16'
    UNION ALL SELECT 'El Último Faro', 'El último farero de la costa sur recibe la visita de su hija, a quien no ve hace veinte años, justo cuando el gobierno decide automatizar el faro.', '2026-10-09', 126, '+13'
    UNION ALL SELECT 'Mundo Mínimo', 'Dentro de un jardín, una hormiga inventora y un caracol perezoso se unen para salvar su vecindario de una regadera gigante.', '2026-11-06', 95, 'ATP'
    UNION ALL SELECT 'Marea Negra', 'Una plataforma petrolera en alta mar extrae algo que no es petróleo. La tripulación tiene que escapar antes de que suba la marea.', '2026-10-30', 109, '+16'
    UNION ALL SELECT 'Corazón de Neón', 'En una ciudad futurista donde las emociones se compran, una vendedora de recuerdos se enamora de un cliente que no tiene ninguno.', '2026-12-11', 117, '+13'
    UNION ALL SELECT 'Los Guardianes del Valle', 'Tres hermanos descubren que el bosque detrás de su casa está protegido por criaturas antiguas, y que una sombra quiere despertarlas.', '2026-12-18', 101, 'ATP'
    UNION ALL SELECT 'Noche de Cobre', 'Un exbombero regresa a su pueblo minero para enfrentar a quienes provocaron el derrumbe que cambió su vida.', '2027-01-08', 133, '+16'
    UNION ALL SELECT 'La Fiesta Equivocada', 'Lucía entra por error a una fiesta de disfraces de una familia millonaria y, para no ser descubierta, se hace pasar por la prima que nadie conoce.', '2027-01-22', 99, '+13'
) AS v
JOIN Clasificaciones c ON c.nombre_clasificacion = v.clasif
WHERE NOT EXISTS (SELECT 1 FROM Peliculas p WHERE p.titulo = v.titulo);

-- ---------- Géneros ----------
-- Cada fila une un título con un género. INSERT OR IGNORE evita repetir un par que ya existe
-- (la tabla Generos tiene UNIQUE (nombre_genero, ID_Pelicula)).
INSERT OR IGNORE INTO Generos (nombre_genero, ID_Pelicula)
SELECT v.genero, p.ID_Pelicula
FROM (
    SELECT 'Eclipse Carmesí' AS titulo, 'Acción' AS genero
    UNION ALL SELECT 'Eclipse Carmesí', 'Aventura'
    UNION ALL SELECT 'Risas en el Ascensor', 'Comedia'
    UNION ALL SELECT 'La Casa del Silencio', 'Terror'
    UNION ALL SELECT 'Órbita Cero', 'Ciencia ficción'
    UNION ALL SELECT 'Órbita Cero', 'Drama'
    UNION ALL SELECT 'Cartas a Elena', 'Romance'
    UNION ALL SELECT 'Cartas a Elena', 'Drama'
    UNION ALL SELECT 'Pingüino Ninja', 'Animación'
    UNION ALL SELECT 'Pingüino Ninja', 'Aventura'
    UNION ALL SELECT 'Furia en la Autopista', 'Acción'
    UNION ALL SELECT 'El Último Faro', 'Drama'
    UNION ALL SELECT 'Mundo Mínimo', 'Animación'
    UNION ALL SELECT 'Mundo Mínimo', 'Comedia'
    UNION ALL SELECT 'Marea Negra', 'Terror'
    UNION ALL SELECT 'Marea Negra', 'Ciencia ficción'
    UNION ALL SELECT 'Corazón de Neón', 'Romance'
    UNION ALL SELECT 'Corazón de Neón', 'Ciencia ficción'
    UNION ALL SELECT 'Los Guardianes del Valle', 'Aventura'
    UNION ALL SELECT 'Los Guardianes del Valle', 'Animación'
    UNION ALL SELECT 'Noche de Cobre', 'Acción'
    UNION ALL SELECT 'Noche de Cobre', 'Drama'
    UNION ALL SELECT 'La Fiesta Equivocada', 'Comedia'
) AS v
JOIN Peliculas p ON p.titulo = v.titulo;

COMMIT;
