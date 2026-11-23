BEGIN TRANSACTION;
CREATE TABLE IF NOT EXISTS "Cines" (
	"ID_cine"	INTEGER,
	"nombre_cine"	TEXT NOT NULL UNIQUE,
	"correo"	TEXT NOT NULL CHECK("correo" LIKE '%_@_%._%'),
	"telefono"	TEXT NOT NULL,
	"cantidad_salas"	INTEGER NOT NULL DEFAULT 0 CHECK("cantidad_salas" >= 0),
	"ID_Ubicacion"	INTEGER NOT NULL,
	PRIMARY KEY("ID_cine" AUTOINCREMENT),
	FOREIGN KEY("ID_Ubicacion") REFERENCES "Ubicaciones"("ID_ubicacion")
);
CREATE TABLE IF NOT EXISTS "Clasificaciones" (
	"ID_Clasificacion"	INTEGER,
	"edad_minima"	INTEGER NOT NULL CHECK("edad_minima" >= 0),
	"nombre_clasificacion"	TEXT NOT NULL UNIQUE,
	"descripcion"	TEXT,
	PRIMARY KEY("ID_Clasificacion" AUTOINCREMENT)
);
CREATE TABLE IF NOT EXISTS "Clasificaciones_tipo_entrada" (
	"ID_Clasificaciones_tipo_entrada"	INTEGER,
	"ID_Clasificacion"	INTEGER NOT NULL,
	"ID_Tipo_entrada"	INTEGER NOT NULL,
	UNIQUE("ID_Clasificacion","ID_Tipo_entrada"),
	PRIMARY KEY("ID_Clasificaciones_tipo_entrada" AUTOINCREMENT),
	FOREIGN KEY("ID_Clasificacion") REFERENCES "Clasificaciones"("ID_Clasificacion"),
	FOREIGN KEY("ID_Tipo_entrada") REFERENCES "Tipo_entrada"("ID_tipo_entrada")
);
CREATE TABLE IF NOT EXISTS "Dia_semana" (
	"ID_dia_semana"	INTEGER,
	"dia"	TEXT NOT NULL UNIQUE,
	PRIMARY KEY("ID_dia_semana" AUTOINCREMENT)
);
CREATE TABLE IF NOT EXISTS "Entradas" (
	"ID_entrada"	INTEGER,
	"fecha_venta"	TEXT NOT NULL CHECK(date("fecha_venta") IS "fecha_venta"),
	"precio_cobrado"	INTEGER NOT NULL CHECK("precio_cobrado" >= 0),
	"estado"	INTEGER NOT NULL DEFAULT 1 CHECK("estado" IN (0, 1)),
	"fila"	TEXT NOT NULL CHECK("fila" GLOB '[A-Z]'),
	"numero_butaca"	INTEGER NOT NULL CHECK("numero_butaca" > 0),
	"ID_funcion"	INTEGER NOT NULL,
	"ID_tipo_entrada"	INTEGER NOT NULL,
	"ID_Usuario"	INTEGER,
	PRIMARY KEY("ID_entrada" AUTOINCREMENT),
	FOREIGN KEY("ID_funcion") REFERENCES "Funciones"("ID_funcion"),
	FOREIGN KEY("ID_tipo_entrada") REFERENCES "Tipo_entrada"("ID_tipo_entrada"),
	FOREIGN KEY("ID_Usuario") REFERENCES "Usuarios"("ID_Usuario")
);
CREATE TABLE IF NOT EXISTS "Funciones" (
	"ID_funcion"	INTEGER,
	"estado"	INTEGER NOT NULL DEFAULT 0 CHECK("estado" IN (0, 1, 2)),
	"fecha"	TEXT NOT NULL CHECK(date("fecha") IS "fecha"),
	"hora_inicio"	TEXT NOT NULL CHECK("hora_inicio" GLOB '[0-2][0-9]:[0-5][0-9]'),
	"hora_fin"	TEXT NOT NULL CHECK("hora_fin" GLOB '[0-2][0-9]:[0-5][0-9]'),
	"ID_Sala"	INTEGER NOT NULL,
	"ID_Programacion"	INTEGER NOT NULL,
	"ID_Tipo_funcion"	INTEGER NOT NULL,
	"ID_Pelicula"	INTEGER NOT NULL,
	UNIQUE("ID_Sala","fecha","hora_inicio"),
	PRIMARY KEY("ID_funcion" AUTOINCREMENT),
	FOREIGN KEY("ID_Pelicula") REFERENCES "Peliculas"("ID_Pelicula"),
	FOREIGN KEY("ID_Programacion") REFERENCES "Programaciones"("ID_Programacion"),
	FOREIGN KEY("ID_Sala") REFERENCES "Salas"("ID_Sala"),
	FOREIGN KEY("ID_Tipo_funcion") REFERENCES "Tipo_funcion"("ID_Tipo_funcion")
);
CREATE TABLE IF NOT EXISTS "Generos" (
	"ID_Genero"	INTEGER,
	"nombre_genero"	TEXT NOT NULL,
	"ID_Pelicula"	INTEGER NOT NULL,
	PRIMARY KEY("ID_Genero" AUTOINCREMENT),
	UNIQUE("nombre_genero","ID_Pelicula"),
	FOREIGN KEY("ID_Pelicula") REFERENCES "Peliculas"("ID_Pelicula")
);
CREATE TABLE IF NOT EXISTS "Idioma" (
	"ID_idioma"	INTEGER,
	"nombre_idioma"	TEXT NOT NULL UNIQUE,
	"codigo_iso"	TEXT NOT NULL UNIQUE,
	PRIMARY KEY("ID_idioma" AUTOINCREMENT)
);
CREATE TABLE IF NOT EXISTS "Pelicula_idioma" (
	"ID_pelicula_idioma"	INTEGER,
	"ID_Pelicula"	INTEGER NOT NULL,
	"ID_idioma"	INTEGER NOT NULL,
	UNIQUE("ID_Pelicula","ID_idioma"),
	PRIMARY KEY("ID_pelicula_idioma" AUTOINCREMENT),
	FOREIGN KEY("ID_Pelicula") REFERENCES "Peliculas"("ID_Pelicula"),
	FOREIGN KEY("ID_idioma") REFERENCES "Idioma"("ID_idioma")
);
CREATE TABLE IF NOT EXISTS "Peliculas" (
	"ID_Pelicula"	INTEGER,
	"titulo"	TEXT NOT NULL,
	"sinopsis"	TEXT,
	"duracion"	INTEGER NOT NULL CHECK("duracion" > 0),
	"ID_Clasificacion"	INTEGER NOT NULL,
	"fecha_estreno"	TEXT CHECK("fecha_estreno" IS NULL OR date("fecha_estreno") IS "fecha_estreno"),
	"fecha_carga_pelicula"	TEXT NOT NULL DEFAULT (date('now')) CHECK(date("fecha_carga_pelicula") IS "fecha_carga_pelicula"),
	PRIMARY KEY("ID_Pelicula" AUTOINCREMENT),
	FOREIGN KEY("ID_Clasificacion") REFERENCES "Clasificaciones"("ID_Clasificacion")
);
CREATE TABLE IF NOT EXISTS "Programaciones" (
	"ID_Programacion"	INTEGER,
	"fecha_inicio"	TEXT NOT NULL CHECK(date("fecha_inicio") IS "fecha_inicio"),
	"fecha_fin"	TEXT NOT NULL CHECK(date("fecha_fin") IS "fecha_fin"),
	"fecha_carga"	TEXT NOT NULL CHECK(date("fecha_carga") IS "fecha_carga"),
	PRIMARY KEY("ID_Programacion" AUTOINCREMENT),
	CHECK("fecha_inicio" <= "fecha_fin")
);
CREATE TABLE IF NOT EXISTS "Salas" (
	"ID_Sala"	INTEGER,
	"estado"	INTEGER NOT NULL DEFAULT 1 CHECK("estado" IN (0, 1)),
	"numero"	INTEGER NOT NULL CHECK("numero" > 0),
	"capacidad"	INTEGER NOT NULL CHECK("capacidad" > 0),
	"ID_cine"	INTEGER NOT NULL,
	PRIMARY KEY("ID_Sala" AUTOINCREMENT),
	UNIQUE("ID_cine","numero"),
	FOREIGN KEY("ID_cine") REFERENCES "Cines"("ID_cine")
);
CREATE TABLE IF NOT EXISTS "Tarifas" (
	"ID_Tarifa"	INTEGER,
	"hora_desde"	TEXT NOT NULL CHECK("hora_desde" GLOB '[0-2][0-9]:[0-5][0-9]'),
	"hora_hasta"	TEXT NOT NULL CHECK("hora_hasta" GLOB '[0-2][0-9]:[0-5][0-9]'),
	"precio"	INTEGER NOT NULL CHECK("precio" >= 0),
	"ID_dia_semana"	INTEGER NOT NULL,
	"ID_cine"	INTEGER NOT NULL,
	"ID_Tipo_entrada"	INTEGER NOT NULL,
	PRIMARY KEY("ID_Tarifa" AUTOINCREMENT),
	UNIQUE("ID_cine","ID_Tipo_entrada","ID_dia_semana","hora_desde"),
	FOREIGN KEY("ID_Tipo_entrada") REFERENCES "Tipo_entrada"("ID_tipo_entrada"),
	FOREIGN KEY("ID_cine") REFERENCES "Cines"("ID_cine"),
	FOREIGN KEY("ID_dia_semana") REFERENCES "Dia_semana"("ID_dia_semana"),
	CHECK("hora_desde" < "hora_hasta")
);
CREATE TABLE IF NOT EXISTS "Tipo_entrada" (
	"ID_tipo_entrada"	INTEGER,
	"nombre_tipo_entrada"	TEXT NOT NULL UNIQUE,
	"descripcion"	TEXT,
	PRIMARY KEY("ID_tipo_entrada" AUTOINCREMENT)
);
CREATE TABLE IF NOT EXISTS "Tipo_funcion" (
	"ID_Tipo_funcion"	INTEGER,
	"nombre_TF"	TEXT NOT NULL UNIQUE,
	"descripcion"	TEXT,
	PRIMARY KEY("ID_Tipo_funcion" AUTOINCREMENT)
);
CREATE TABLE IF NOT EXISTS "Roles" (
	"ID_Rol"	INTEGER,
	"nombre_rol"	TEXT NOT NULL UNIQUE,
	PRIMARY KEY("ID_Rol" AUTOINCREMENT)
);
CREATE TABLE IF NOT EXISTS "Usuarios" (
	"ID_Usuario"	INTEGER,
	"nombre_usuario"	TEXT NOT NULL UNIQUE CHECK(length("nombre_usuario") >= 3),
	"correo_usuario"	TEXT NOT NULL UNIQUE CHECK("correo_usuario" LIKE '%_@_%._%'),
	"password_hash"	TEXT NOT NULL,
	"estado_usuario"	INTEGER NOT NULL DEFAULT 1 CHECK("estado_usuario" IN (0, 1)),
	"ID_Rol"	INTEGER NOT NULL,
	PRIMARY KEY("ID_Usuario" AUTOINCREMENT),
	FOREIGN KEY("ID_Rol") REFERENCES "Roles"("ID_Rol")
);
CREATE TABLE IF NOT EXISTS "Ubicaciones" (
	"ID_ubicacion"	INTEGER,
	"nombre_ubicacion"	TEXT NOT NULL UNIQUE,
	PRIMARY KEY("ID_ubicacion" AUTOINCREMENT)
);
INSERT INTO "Cines" VALUES (1,'Cine Centro','centro@cines.com','+54 11 4000-0001',2,1);
INSERT INTO "Cines" VALUES (2,'Cine Norte','norte@cines.com','+54 11 4000-0002',2,2);
INSERT INTO "Clasificaciones" VALUES (1,0,'ATP','Apta para todo publico');
INSERT INTO "Clasificaciones" VALUES (2,13,'+13','Mayores de 13 anios');
INSERT INTO "Clasificaciones" VALUES (3,16,'+16','Mayores de 16 anios');
INSERT INTO "Clasificaciones" VALUES (4,18,'+18','Mayores de 18 anios');
INSERT INTO "Clasificaciones_tipo_entrada" VALUES (1,1,1);
INSERT INTO "Clasificaciones_tipo_entrada" VALUES (2,1,2);
INSERT INTO "Clasificaciones_tipo_entrada" VALUES (3,1,3);
INSERT INTO "Clasificaciones_tipo_entrada" VALUES (4,2,1);
INSERT INTO "Clasificaciones_tipo_entrada" VALUES (5,2,2);
INSERT INTO "Clasificaciones_tipo_entrada" VALUES (6,2,3);
INSERT INTO "Clasificaciones_tipo_entrada" VALUES (7,3,1);
INSERT INTO "Clasificaciones_tipo_entrada" VALUES (8,3,3);
INSERT INTO "Clasificaciones_tipo_entrada" VALUES (9,4,1);
INSERT INTO "Clasificaciones_tipo_entrada" VALUES (10,4,3);
INSERT INTO "Dia_semana" VALUES (1,'Lunes');
INSERT INTO "Dia_semana" VALUES (2,'Martes');
INSERT INTO "Dia_semana" VALUES (3,'Miercoles');
INSERT INTO "Dia_semana" VALUES (4,'Jueves');
INSERT INTO "Dia_semana" VALUES (5,'Viernes');
INSERT INTO "Dia_semana" VALUES (6,'Sabado');
INSERT INTO "Dia_semana" VALUES (7,'Domingo');
INSERT INTO "Entradas" VALUES (1,'2026-11-19',6000,1,'A',1,1,1,NULL);
INSERT INTO "Entradas" VALUES (2,'2026-11-19',4500,1,'A',2,1,2,NULL);
INSERT INTO "Entradas" VALUES (3,'2026-11-19',4000,1,'B',5,2,3,NULL);
INSERT INTO "Entradas" VALUES (4,'2026-11-21',7500,1,'C',3,4,1,NULL);
INSERT INTO "Funciones" VALUES (1,1,'2026-11-19','18:00','20:10',1,1,2,1);
INSERT INTO "Funciones" VALUES (2,1,'2026-11-19','21:00','23:00',2,1,2,2);
INSERT INTO "Funciones" VALUES (3,0,'2026-11-20','20:00','22:15',3,1,1,3);
INSERT INTO "Funciones" VALUES (4,1,'2026-11-21','16:00','18:10',4,1,3,1);
INSERT INTO "Generos" VALUES (1,'Aventura',1);
INSERT INTO "Generos" VALUES (2,'Familiar',1);
INSERT INTO "Generos" VALUES (3,'Thriller',2);
INSERT INTO "Generos" VALUES (4,'Drama',3);
INSERT INTO "Idioma" VALUES (1,'Espanol','es');
INSERT INTO "Idioma" VALUES (2,'Ingles','en');
INSERT INTO "Pelicula_idioma" VALUES (1,1,1);
INSERT INTO "Pelicula_idioma" VALUES (2,2,1);
INSERT INTO "Pelicula_idioma" VALUES (3,3,1);
INSERT INTO "Pelicula_idioma" VALUES (4,3,2);
INSERT INTO "Peliculas" ("ID_Pelicula","titulo","sinopsis","duracion","ID_Clasificacion","fecha_estreno") VALUES (1,'Aventura en el Sur','Una familia recorre la Patagonia.',130,1,'2026-11-19');
INSERT INTO "Peliculas" ("ID_Pelicula","titulo","sinopsis","duracion","ID_Clasificacion","fecha_estreno") VALUES (2,'La Noche Larga','Un thriller policial en Buenos Aires.',120,3,'2026-11-19');
INSERT INTO "Peliculas" ("ID_Pelicula","titulo","sinopsis","duracion","ID_Clasificacion","fecha_estreno") VALUES (3,'Historias Ocultas','Drama para adultos sobre secretos.',135,4,'2026-11-20');
INSERT INTO "Programaciones" VALUES (1,'2026-11-19','2026-11-25','2026-11-16');
INSERT INTO "Salas" VALUES (1,1,1,120,1);
INSERT INTO "Salas" VALUES (2,1,2,80,1);
INSERT INTO "Salas" VALUES (3,1,1,200,2);
INSERT INTO "Salas" VALUES (4,1,2,60,2);
INSERT INTO "Tarifas" VALUES (1,'00:00','23:59',4000,1,1,3);
INSERT INTO "Tarifas" VALUES (2,'00:00','23:59',4000,2,1,3);
INSERT INTO "Tarifas" VALUES (3,'00:00','23:59',4000,3,1,3);
INSERT INTO "Tarifas" VALUES (4,'00:00','23:59',4000,4,1,3);
INSERT INTO "Tarifas" VALUES (5,'00:00','23:59',4000,5,1,3);
INSERT INTO "Tarifas" VALUES (6,'00:00','23:59',5000,6,1,3);
INSERT INTO "Tarifas" VALUES (7,'00:00','23:59',5000,7,1,3);
INSERT INTO "Tarifas" VALUES (8,'00:00','23:59',6000,1,1,1);
INSERT INTO "Tarifas" VALUES (9,'00:00','23:59',6000,2,1,1);
INSERT INTO "Tarifas" VALUES (10,'00:00','23:59',6000,3,1,1);
INSERT INTO "Tarifas" VALUES (11,'00:00','23:59',6000,4,1,1);
INSERT INTO "Tarifas" VALUES (12,'00:00','23:59',6000,5,1,1);
INSERT INTO "Tarifas" VALUES (13,'00:00','23:59',7000,6,1,1);
INSERT INTO "Tarifas" VALUES (14,'00:00','23:59',7000,7,1,1);
INSERT INTO "Tarifas" VALUES (15,'00:00','23:59',4500,1,1,2);
INSERT INTO "Tarifas" VALUES (16,'00:00','23:59',4500,2,1,2);
INSERT INTO "Tarifas" VALUES (17,'00:00','23:59',4500,3,1,2);
INSERT INTO "Tarifas" VALUES (18,'00:00','23:59',4500,4,1,2);
INSERT INTO "Tarifas" VALUES (19,'00:00','23:59',4500,5,1,2);
INSERT INTO "Tarifas" VALUES (20,'00:00','23:59',5500,6,1,2);
INSERT INTO "Tarifas" VALUES (21,'00:00','23:59',5500,7,1,2);
INSERT INTO "Tarifas" VALUES (22,'00:00','23:59',4500,1,2,3);
INSERT INTO "Tarifas" VALUES (23,'00:00','23:59',4500,2,2,3);
INSERT INTO "Tarifas" VALUES (24,'00:00','23:59',4500,3,2,3);
INSERT INTO "Tarifas" VALUES (25,'00:00','23:59',4500,4,2,3);
INSERT INTO "Tarifas" VALUES (26,'00:00','23:59',4500,5,2,3);
INSERT INTO "Tarifas" VALUES (27,'00:00','23:59',5500,6,2,3);
INSERT INTO "Tarifas" VALUES (28,'00:00','23:59',5500,7,2,3);
INSERT INTO "Tarifas" VALUES (29,'00:00','23:59',6500,1,2,1);
INSERT INTO "Tarifas" VALUES (30,'00:00','23:59',6500,2,2,1);
INSERT INTO "Tarifas" VALUES (31,'00:00','23:59',6500,3,2,1);
INSERT INTO "Tarifas" VALUES (32,'00:00','23:59',6500,4,2,1);
INSERT INTO "Tarifas" VALUES (33,'00:00','23:59',6500,5,2,1);
INSERT INTO "Tarifas" VALUES (34,'00:00','23:59',7500,6,2,1);
INSERT INTO "Tarifas" VALUES (35,'00:00','23:59',7500,7,2,1);
INSERT INTO "Tarifas" VALUES (36,'00:00','23:59',5000,1,2,2);
INSERT INTO "Tarifas" VALUES (37,'00:00','23:59',5000,2,2,2);
INSERT INTO "Tarifas" VALUES (38,'00:00','23:59',5000,3,2,2);
INSERT INTO "Tarifas" VALUES (39,'00:00','23:59',5000,4,2,2);
INSERT INTO "Tarifas" VALUES (40,'00:00','23:59',5000,5,2,2);
INSERT INTO "Tarifas" VALUES (41,'00:00','23:59',6000,6,2,2);
INSERT INTO "Tarifas" VALUES (42,'00:00','23:59',6000,7,2,2);
INSERT INTO "Roles" VALUES (1,'usuario');
INSERT INTO "Roles" VALUES (2,'admin');
INSERT INTO "Tipo_entrada" VALUES (1,'Mayor','Entrada general');
INSERT INTO "Tipo_entrada" VALUES (2,'Menor','Menores de edad');
INSERT INTO "Tipo_entrada" VALUES (3,'Jubilado','Jubilados y pensionados');
INSERT INTO "Tipo_funcion" VALUES (1,'Preestreno','Funcion previa al estreno');
INSERT INTO "Tipo_funcion" VALUES (2,'Estreno','Primera semana en cartelera');
INSERT INTO "Tipo_funcion" VALUES (3,'Normal','Funcion regular');
INSERT INTO "Ubicaciones" VALUES (1,'Shopping Centro');
INSERT INTO "Ubicaciones" VALUES (2,'Shopping Norte');
CREATE UNIQUE INDEX IF NOT EXISTS "ux_entrada_butaca" ON "Entradas" (
	"ID_funcion",
	"fila",
	"numero_butaca"
) WHERE "estado" = 1;
COMMIT;
