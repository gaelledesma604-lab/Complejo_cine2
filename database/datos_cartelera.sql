-- ============================================================
-- datos_cartelera.sql
-- Carga la cartelera: las películas que hoy se proyectan en el Complejo Cine Gaumont (INCAA).
--
-- Ejecutar DESPUÉS de complejo_cines.sql (el esquema debe tener la columna
-- Peliculas.fecha_estreno). Se puede correr más de una vez sin duplicar nada.
--
-- fecha_estreno puede ser NULL (como en las películas sin fecha confirmada): en ese caso
-- la película figura "En cartelera" desde el día en que se instala la base.
--
-- PARA AGREGAR MÁS PELÍCULAS: sumá una línea "UNION ALL SELECT 'Título', 'Sinopsis',
-- 'AAAA-MM-DD', duración, 'Clasificación'" al final del primer bloque, y una línea
-- "UNION ALL SELECT 'Título', 'Género'" por cada género en el bloque de géneros.
-- El título tiene que estar escrito igual en los dos lugares. Un apóstrofo dentro de
-- un texto se escribe doble ('').
-- ============================================================
PRAGMA foreign_keys = ON;
BEGIN TRANSACTION;

-- ---------- Películas ----------
INSERT INTO Peliculas (titulo, sinopsis, fecha_estreno, fecha_carga_pelicula, duracion, ID_Clasificacion)
SELECT v.titulo, v.sinopsis, v.estreno, date('now'), v.duracion, c.ID_Clasificacion
FROM (
    SELECT '¿Querés ser mi hijo?' AS titulo, 'Lucía, de 42 años, atraviesa una mala racha: su pareja la engaña y pierde el trabajo. Para conseguir un puesto nuevo inventa que tiene un hijo adulto y le pide a su joven vecino Javier que finja serlo, a cambio de que pueda seguir haciendo sus fiestas.' AS sinopsis, '2026-10-22' AS estreno, 98 AS duracion, '+13' AS clasif
    UNION ALL SELECT '...Que Dios perdona a todos', 'Arturo, un agente inmobiliario siciliano que solo se destaca en su trabajo y en su pasión por los dulces, conoce a Flora, una pastelera muy católica. Para no perderla finge tener fe, y en ese camino cuenta con la ayuda inesperada del Papa.', NULL, 113, '+13'
    UNION ALL SELECT 'Carne', 'En un país que gira en torno a la carne, la desaparición del ganado provoca una crisis sin precedentes. Matías, un empresario ambicioso, propone una ley polémica para salvar la industria y, cuando es rechazada, lleva su plan a extremos cada vez más oscuros.', NULL, 115, '+16'
    UNION ALL SELECT 'El baño del Papa', 'En 1988, la visita de Juan Pablo II a Melo, un pueblo pobre de la frontera uruguaya, hace soñar a los vecinos con ganar dinero. Beto tiene una idea propia: construir baños frente a su casa para alquilárselos a los miles de visitantes.', NULL, 95, '+13'
    UNION ALL SELECT 'Los otros', 'Isla de Jersey, 1945. Grace vive en una casa aislada y en penumbra con sus dos hijos, que no pueden tolerar la luz del día, mientras espera a un marido que no regresa de la guerra. La llegada de tres nuevos sirvientes pondrá a prueba las estrictas reglas de la casa.', NULL, 104, '+13'
    UNION ALL SELECT 'Lo dejamos acá', 'Un psicoanalista desencantado con la terapia tradicional empieza a intervenir en secreto en la vida de sus pacientes. El equilibrio se rompe cuando llega a su consultorio un escritor con un bloqueo creativo.', '2026-10-08', 98, '+13'
    UNION ALL SELECT 'La casa', 'Tras ser despedido de una multinacional, Hugo Vaca acepta la oferta de un grupo de inversores que le comprará su casa a la mitad de su valor, con derecho a seguir viviendo en ella hasta su muerte. Lo que parecía una salida se complica más de lo previsto.', NULL, 95, '+13'
    UNION ALL SELECT 'Annemarie', 'Una cineasta retrata el vínculo con su bisabuela, la fotógrafa Annemarie Heinrich, y explora qué significa retratar y revelar a otra persona.', NULL, 74, 'ATP'
    UNION ALL SELECT 'Hospital Británico', 'Un hombre sale del hospital y emprende un viaje, quizás el último, entre paisajes místicos y versos del último libro del poeta Héctor Viel Temperley, junto a testimonios de quienes fueron marcados por su lectura.', NULL, 68, 'ATP'
    UNION ALL SELECT '4 historias increíbles', 'Un hombre de casi 50 años busca el amor mientras sospecha que no sabe reconocerlo. A lo largo de cuatro historias se enreda en citas desastrosas, secretos y mentiras, siempre inclinado a elegir a la persona equivocada.', NULL, 81, '+13'
    UNION ALL SELECT 'Recen por mí: la historia de Francisco', 'Documental sobre la vida del papa Francisco, desde su juventud en Argentina hasta su papel como líder espiritual mundial, con foco en su labor social y humanitaria, material de archivo y su propio testimonio.', NULL, 110, 'ATP'
    UNION ALL SELECT 'Indómita luz', 'Antu, una niña de 9 años que nació ciega, recorre Argentina con sus padres en un viejo camión proyectando películas en pueblos olvidados, mientras descubre el mundo con una sensibilidad poco común.', '2026-11-19', 75, 'ATP'
    UNION ALL SELECT 'Jeanne Dielman, 23, quai du Commerce, 1080 Bruxelles', 'Retrato minucioso de la rutina de una viuda y su hijo adolescente en Bruselas: tareas domésticas por la mañana y prostitución por la tarde, siguiendo un orden inmutable.', NULL, 193, '+16'
    UNION ALL SELECT 'El llanto del perro', 'Gaby, atormentada por la ansiedad, se instala en la casa aislada de Ema para recibir una terapia alternativa que poco a poco se convierte en un inquietante ritual de obediencia.', NULL, 72, '+16'
    UNION ALL SELECT 'Pepita la pistolera', 'En 1985, una madre mata a tiros a tres delincuentes que entran a su casa y se convierte en un fenómeno mediático. Margarita Di Tullio, la mujer detrás del mito, era además ladrona profesional y la madama más famosa de Mar del Plata.', '2026-09-03', 106, '+16'
) AS v
JOIN Clasificaciones c ON c.nombre_clasificacion = v.clasif
WHERE NOT EXISTS (SELECT 1 FROM Peliculas p WHERE p.titulo = v.titulo);

-- ---------- Géneros ----------
-- Cada fila une un título con un género. INSERT OR IGNORE evita repetir un par que ya existe
-- (la tabla Generos tiene UNIQUE (nombre_genero, ID_Pelicula)).
INSERT OR IGNORE INTO Generos (nombre_genero, ID_Pelicula)
SELECT v.genero, p.ID_Pelicula
FROM (
    SELECT '¿Querés ser mi hijo?' AS titulo, 'Comedia' AS genero
    UNION ALL SELECT '¿Querés ser mi hijo?', 'Romance'
    UNION ALL SELECT '...Que Dios perdona a todos', 'Comedia'
    UNION ALL SELECT '...Que Dios perdona a todos', 'Romance'
    UNION ALL SELECT 'Carne', 'Drama'
    UNION ALL SELECT 'Carne', 'Thriller'
    UNION ALL SELECT 'El baño del Papa', 'Comedia'
    UNION ALL SELECT 'El baño del Papa', 'Drama'
    UNION ALL SELECT 'Los otros', 'Terror'
    UNION ALL SELECT 'Los otros', 'Thriller'
    UNION ALL SELECT 'Lo dejamos acá', 'Comedia'
    UNION ALL SELECT 'La casa', 'Drama'
    UNION ALL SELECT 'La casa', 'Thriller'
    UNION ALL SELECT 'Annemarie', 'Documental'
    UNION ALL SELECT 'Hospital Británico', 'Documental'
    UNION ALL SELECT '4 historias increíbles', 'Comedia'
    UNION ALL SELECT '4 historias increíbles', 'Romance'
    UNION ALL SELECT 'Recen por mí: la historia de Francisco', 'Documental'
    UNION ALL SELECT 'Recen por mí: la historia de Francisco', 'Biográfico'
    UNION ALL SELECT 'Indómita luz', 'Documental'
    UNION ALL SELECT 'Jeanne Dielman, 23, quai du Commerce, 1080 Bruxelles', 'Drama'
    UNION ALL SELECT 'El llanto del perro', 'Thriller'
    UNION ALL SELECT 'El llanto del perro', 'Drama'
    UNION ALL SELECT 'Pepita la pistolera', 'Drama'
    UNION ALL SELECT 'Pepita la pistolera', 'Thriller'
) AS v
JOIN Peliculas p ON p.titulo = v.titulo;

COMMIT;
