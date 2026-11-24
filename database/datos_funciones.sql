-- ============================================================
-- datos_funciones.sql
-- Crea funciones de ejemplo para los próximos 7 días (desde hoy), así siempre
-- hay horarios por delante para probar la compra de entradas.
--
-- Ejecutar DESPUÉS de complejo_cines.sql y datos_cartelera.sql.
-- Reparto: cada sala tiene 3 turnos por día (15:00, 18:00 y 21:00) y las películas
-- que ya están en cartelera rotan entre salas y turnos. Cada día se avanza tantos lugares como
-- turnos hay en total, así que en la semana pasan casi todas las películas.
-- Si una película no tiene duración cargada, se usa 120 minutos solo para calcular la hora de fin.
-- Todas son funciones "Normal" (estado 1 = a la venta).
-- ============================================================
PRAGMA foreign_keys = ON;
BEGIN TRANSACTION;

-- Una programación que cubre la semana (Funciones.ID_Programacion es obligatorio).
INSERT INTO Programaciones (fecha_inicio, fecha_fin, fecha_carga)
SELECT date('now', 'localtime'), date('now', 'localtime', '+6 days'), date('now', 'localtime')
WHERE NOT EXISTS (
    SELECT 1 FROM Programaciones WHERE fecha_inicio = date('now', 'localtime')
);

WITH RECURSIVE
dias(n) AS (
    SELECT 0 UNION ALL SELECT n + 1 FROM dias WHERE n < 6
),
turnos(t, hora) AS (
    VALUES (0, '15:00'), (1, '18:00'), (2, '21:00')
),
salas_n AS (
    SELECT ID_Sala, ROW_NUMBER() OVER (ORDER BY ID_Sala) - 1 AS s
    FROM Salas WHERE estado = 1
),
cartelera AS (
    SELECT ID_Pelicula, duracion, ROW_NUMBER() OVER (ORDER BY ID_Pelicula) - 1 AS i
    FROM Peliculas
    WHERE COALESCE(fecha_estreno, fecha_carga_pelicula) <= date('now', 'localtime')
),
total AS (
    SELECT COUNT(*) AS k FROM cartelera
),
turnos_por_dia AS (
    SELECT COUNT(*) * 3 AS n FROM salas_n
)
INSERT OR IGNORE INTO Funciones
    (estado, fecha, hora_inicio, hora_fin, ID_Sala, ID_Programacion, ID_Tipo_funcion, ID_Pelicula)
SELECT 1,
       date('now', 'localtime', '+' || d.n || ' days'),
       t.hora,
       strftime('%H:%M', t.hora, '+' || COALESCE(c.duracion, 120) || ' minutes'),
       sn.ID_Sala,
       (SELECT MAX(ID_Programacion) FROM Programaciones WHERE fecha_inicio = date('now', 'localtime')),
       (SELECT ID_Tipo_funcion FROM Tipo_funcion WHERE nombre_TF = 'Normal'),
       c.ID_Pelicula
FROM dias d
CROSS JOIN turnos t
CROSS JOIN salas_n sn
CROSS JOIN total
CROSS JOIN turnos_por_dia
JOIN cartelera c ON c.i = (sn.s * 3 + t.t + d.n * turnos_por_dia.n) % total.k
WHERE total.k > 0;

COMMIT;
