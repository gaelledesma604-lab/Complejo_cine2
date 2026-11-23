-- ============================================================
-- datos_cartelera.sql
-- Carga la cartelera: las películas del INCAA.
--
-- Ejecutar DESPUÉS de complejo_cines.sql (el esquema debe tener la columna
-- Peliculas.fecha_estreno). Se puede correr más de una vez sin duplicar nada.
--
-- PARA AGREGAR MÁS PELÍCULAS: sumá una línea "UNION ALL SELECT 'Título', 'Sinopsis',
-- 'AAAA-MM-DD', duración, 'Clasificación'" al final del bloque que corresponda, y una
-- línea "UNION ALL SELECT 'Título', 'Género'" por cada género en el bloque de géneros.
-- El título tiene que estar escrito igual en los dos lugares. Un apóstrofo dentro de
-- un texto se escribe doble ('').
-- ============================================================
PRAGMA foreign_keys = ON;
BEGIN TRANSACTION;

-- ---------- Películas del INCAA ----------
INSERT INTO Peliculas (titulo, sinopsis, fecha_estreno, fecha_carga_pelicula, duracion, ID_Clasificacion)
SELECT v.titulo, v.sinopsis, v.estreno, date('now'), v.duracion, c.ID_Clasificacion
FROM (
    SELECT 'Gigantes: Una aventura extraordinaria' AS titulo, 'Alfonso y sus amigos se embarcan en una misión para salvar su pueblo de unas misteriosas tormentas que amenazan con destruir sus hogares.' AS sinopsis, '2024-08-08' AS estreno, 87 AS duracion, 'ATP' AS clasif
    UNION ALL SELECT 'Martín García', 'Un joven llega a la isla Martín García para trabajar como guardaparque y descubre los secretos históricos y naturales de este aislado territorio.', '2024-05-16', 77, '+13'
    UNION ALL SELECT 'El Jockey', 'Remo Manfredini es un jockey legendario cuyo comportamiento autodestructivo empieza a eclipsar su talento y pone en riesgo su relación con Abril.', '2024-09-26', 96, '+16'
    UNION ALL SELECT 'Maldita Eva', 'Un retrato íntimo sobre las pasiones, contradicciones y conflictos de una mujer decidida a romper con los mandatos impuestos por su entorno.', '2025-03-20', 85, '+16'
    UNION ALL SELECT 'Los impactados', 'Tras sobrevivir a la caída de un rayo, Ada sufre secuelas físicas y psicológicas que la llevan a integrarse a un grupo de personas que atravesaron lo mismo.', '2024-04-11', 91, '+13'
    UNION ALL SELECT 'Vladimir', 'La historia de un hombre atrapado entre los recuerdos de su pasado y las tensiones políticas y personales de su presente.', '2024-07-18', 82, '+13'
    UNION ALL SELECT 'El tiempo de la tierra, historias originarias', 'Un recorrido documental por diversas comunidades indígenas de Argentina que luchan por preservar su lengua, su cultura y sus territorios.', '2024-06-13', 75, 'ATP'
    UNION ALL SELECT 'Trabajadores de la imprenta', 'Documental que registra el día a día, la organización y la resistencia de los obreros gráficos frente a las crisis del sector.', '2024-05-02', 78, 'ATP'
    UNION ALL SELECT 'Riders', 'Un grupo de jóvenes repartidores recorre las calles de la ciudad enfrentando la precarización laboral y buscando un futuro mejor.', '2024-08-22', 84, '+13'
    UNION ALL SELECT 'Estepa', 'En la inmensidad desolada de la estepa patagónica, la llegada de un forastero altera la rutina silenciosa de una familia aislada.', '2024-09-05', 89, '+13'
    UNION ALL SELECT 'Descansar en paz', 'Acorralado por las deudas, un hombre de familia decide aprovechar una circunstancia imprevisible para desaparecer y empezar de cero lejos de todos.', '2024-03-21', 105, '+13'
    UNION ALL SELECT 'Un tal Mario', 'A partir de viejas cintas y recuerdos familiares, un director reconstruye la enigmática vida de su padre en la Argentina de los años setenta.', '2024-04-25', 90, 'ATP'
    UNION ALL SELECT 'Entremedio', 'Dos personas coinciden en un espacio de tránsito y comparten una jornada que los obliga a replantearse el rumbo de sus vidas.', '2024-07-04', 76, '+13'
    UNION ALL SELECT 'El pelado', 'Comedia dramática sobre un hombre común que, tras sufrir un cambio repentino en su apariencia y su rutina, intenta redefinir su identidad.', '2024-06-27', 83, '+13'
    UNION ALL SELECT 'Continente', 'Un viaje sensorial y poético por distintos paisajes sudamericanos que explora la relación entre el territorio y quienes lo habitan.', '2024-08-15', 80, 'ATP'
    UNION ALL SELECT 'Naufragios', 'En un paraje costero fuera de temporada, un grupo de personajes solitarios cruza sus caminos mientras intenta sanar heridas del pasado.', '2024-05-30', 94, '+13'
    UNION ALL SELECT 'Cuando todo arde', 'En medio de una crisis social y ambiental, una mujer debe tomar decisiones límite para proteger a los suyos.', '2024-10-10', 88, '+16'
    UNION ALL SELECT 'Cantando nuestras raíces', 'Un viaje musical por el interior del país al encuentro de cantores populares que mantienen vivas las tradiciones folklóricas.', '2024-09-12', 74, 'ATP'
    UNION ALL SELECT 'VINCI / Cuando los cuerpos chocan', 'Una exploración sobre el movimiento, la física y la expresión corporal a través de un grupo de bailarines y artistas escénicos.', '2024-10-03', 81, 'ATP'
    UNION ALL SELECT 'Después de Un buen día', 'Documental que investiga el fenómeno de culto alrededor de la película argentina Un buen día y el destino de sus protagonistas.', '2024-05-09', 86, '+13'
    UNION ALL SELECT 'Bajo Naranja', 'Un joven estadounidense viaja a Buenos Aires buscando historias sobre la crisis argentina y termina involucrado con una banda de jóvenes artistas.', '2024-08-29', 95, '+16'
    UNION ALL SELECT 'Reas', 'En la antigua cárcel de Caseros, mujeres y personas trans reconstruyen sus historias de encierro y libertad a través de escenas musicales y coreografías.', '2024-04-18', 82, '+16'
    UNION ALL SELECT 'El rectángulo de los ángeles', 'Un thriller psicológico ambientado en un entorno cerrado donde las sospechas entre los protagonistas escalan sin control.', '2024-10-17', 92, '+16'
    UNION ALL SELECT 'Tú me abrasas', 'Adaptación libre de los Diálogos con Leucó de Cesare Pavese que entrelaza el deseo, la muerte y el mito en un entorno natural.', '2024-09-19', 64, '+16'
    UNION ALL SELECT 'El agrónomo', 'Un ingeniero agrónomo del interior comienza a cuestionar el modelo de producción agroquímica cuando la enfermedad golpea de cerca a su entorno.', '2024-04-04', 73, '+13'
    UNION ALL SELECT 'Último recurso', 'Un periodista en decadencia ve en un caso policial sin resolver la última oportunidad para salvar su carrera.', '2023-11-16', 98, '+16'
    UNION ALL SELECT 'Continuum, la playa', 'Observación contemplativa sobre el paso del tiempo, el mar y las pequeñas historias cotidianas de quienes habitan la costa.', '2023-10-12', 72, 'ATP'
    UNION ALL SELECT 'Los tonos mayores', 'Tras un accidente, Ana descubre que la placa metálica que tiene en su brazo recibe un extraño mensaje en código morse.', '2023-09-07', 101, 'ATP'
    UNION ALL SELECT 'El filo de las tijeras', 'Documental que retrata el oficio, la paciencia y las historias de vida detrás de una tradicional sastrería porteña.', '2023-08-24', 75, 'ATP'
    UNION ALL SELECT 'La práctica', 'Gustavo y Vanessa son profesores de yoga que atraviesan una crisis matrimonial justo cuando una serie de lesiones complica su vida diaria en Chile.', '2023-11-02', 90, '+13'
    UNION ALL SELECT 'Feliz Kitschmas', 'Una cena navideña familiar se sale completamente de control cuando salen a la luz viejos rencores y secretos absurdos.', '2023-12-14', 84, '+13'
    UNION ALL SELECT 'Granizo', 'Un famoso meteorólogo de la televisión cae en desgracia tras fallar en el pronóstico de una terrible tormenta y debe refugiarse en su Córdoba natal.', '2022-03-30', 118, '+13'
    UNION ALL SELECT 'Argentina, 1985', 'Inspirada en la historia real de los fiscales Julio Strassera y Luis Moreno Ocampo, quienes encabezaron el juicio a las juntas militares de la última dictadura.', '2022-09-29', 140, '+13'
) AS v
JOIN Clasificaciones c ON c.nombre_clasificacion = v.clasif
WHERE NOT EXISTS (SELECT 1 FROM Peliculas p WHERE p.titulo = v.titulo);

-- ---------- Géneros ----------
-- Cada fila une un título con un género. INSERT OR IGNORE evita repetir un par que ya existe
-- (la tabla Generos tiene UNIQUE (nombre_genero, ID_Pelicula)).
INSERT OR IGNORE INTO Generos (nombre_genero, ID_Pelicula)
SELECT v.genero, p.ID_Pelicula
FROM (
    SELECT 'Gigantes: Una aventura extraordinaria' AS titulo, 'Animación' AS genero
    UNION ALL SELECT 'Gigantes: Una aventura extraordinaria', 'Aventura'
    UNION ALL SELECT 'Martín García', 'Drama'
    UNION ALL SELECT 'El Jockey', 'Drama'
    UNION ALL SELECT 'El Jockey', 'Comedia'
    UNION ALL SELECT 'Maldita Eva', 'Drama'
    UNION ALL SELECT 'Los impactados', 'Drama'
    UNION ALL SELECT 'Vladimir', 'Drama'
    UNION ALL SELECT 'El tiempo de la tierra, historias originarias', 'Documental'
    UNION ALL SELECT 'Trabajadores de la imprenta', 'Documental'
    UNION ALL SELECT 'Riders', 'Drama'
    UNION ALL SELECT 'Estepa', 'Drama'
    UNION ALL SELECT 'Descansar en paz', 'Comedia'
    UNION ALL SELECT 'Descansar en paz', 'Drama'
    UNION ALL SELECT 'Un tal Mario', 'Documental'
    UNION ALL SELECT 'Entremedio', 'Drama'
    UNION ALL SELECT 'Entremedio', 'Romance'
    UNION ALL SELECT 'El pelado', 'Comedia'
    UNION ALL SELECT 'El pelado', 'Drama'
    UNION ALL SELECT 'Continente', 'Documental'
    UNION ALL SELECT 'Naufragios', 'Drama'
    UNION ALL SELECT 'Cuando todo arde', 'Drama'
    UNION ALL SELECT 'Cuando todo arde', 'Thriller'
    UNION ALL SELECT 'Cantando nuestras raíces', 'Documental'
    UNION ALL SELECT 'Cantando nuestras raíces', 'Musical'
    UNION ALL SELECT 'VINCI / Cuando los cuerpos chocan', 'Documental'
    UNION ALL SELECT 'Después de Un buen día', 'Documental'
    UNION ALL SELECT 'Bajo Naranja', 'Drama'
    UNION ALL SELECT 'Reas', 'Documental'
    UNION ALL SELECT 'Reas', 'Musical'
    UNION ALL SELECT 'El rectángulo de los ángeles', 'Thriller'
    UNION ALL SELECT 'Tú me abrasas', 'Drama'
    UNION ALL SELECT 'Tú me abrasas', 'Romance'
    UNION ALL SELECT 'El agrónomo', 'Documental'
    UNION ALL SELECT 'Último recurso', 'Thriller'
    UNION ALL SELECT 'Último recurso', 'Drama'
    UNION ALL SELECT 'Continuum, la playa', 'Documental'
    UNION ALL SELECT 'Los tonos mayores', 'Drama'
    UNION ALL SELECT 'El filo de las tijeras', 'Documental'
    UNION ALL SELECT 'La práctica', 'Drama'
    UNION ALL SELECT 'La práctica', 'Comedia'
    UNION ALL SELECT 'Feliz Kitschmas', 'Comedia'
    UNION ALL SELECT 'Granizo', 'Comedia'
    UNION ALL SELECT 'Argentina, 1985', 'Drama'
    UNION ALL SELECT 'Argentina, 1985', 'Histórico'
) AS v
JOIN Peliculas p ON p.titulo = v.titulo;

COMMIT;
