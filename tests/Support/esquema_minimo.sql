CREATE TABLE rol (
    id INTEGER PRIMARY KEY,
    nombre TEXT NOT NULL
);

INSERT INTO rol (id, nombre) VALUES
    (1, 'Administrador'),
    (2, 'Instructor'),
    (3, 'Aprendiz');

CREATE TABLE usuario (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre TEXT NOT NULL,
    apellido TEXT NOT NULL,
    identificacion TEXT NOT NULL UNIQUE,
    email TEXT NOT NULL,
    password TEXT NOT NULL,
    Rol_id INTEGER NOT NULL,
    Ficha_id INTEGER DEFAULT NULL,
    codigo_llavero TEXT DEFAULT NULL,
    estado TEXT NOT NULL DEFAULT 'Activo'
);

CREATE TABLE programa (
    id INTEGER PRIMARY KEY,
    nombre TEXT NOT NULL,
    descripcion TEXT
);

CREATE TABLE jornada (
    id INTEGER PRIMARY KEY,
    nombre TEXT NOT NULL
);

CREATE TABLE ficha (
    id INTEGER PRIMARY KEY,
    codigo INTEGER NOT NULL,
    Programa_id INTEGER NOT NULL,
    jornada_id INTEGER,
    instructor_id INTEGER,
    estado TEXT NOT NULL DEFAULT 'Activo'
);

CREATE TABLE sesion (
    id INTEGER PRIMARY KEY,
    Ficha_id INTEGER,
    Instructor_id INTEGER
);

CREATE TABLE asignacion_instructor (
    id INTEGER PRIMARY KEY,
    ficha_id INTEGER,
    instructor_id INTEGER
);
