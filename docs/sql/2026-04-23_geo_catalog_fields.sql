ALTER TABLE participants
    ADD COLUMN pais_id INT NULL AFTER colegio_nombre,
    ADD COLUMN pais_nombre VARCHAR(120) NULL AFTER pais_id,
    ADD COLUMN departamento_id INT NULL AFTER pais_nombre,
    ADD COLUMN departamento_nombre VARCHAR(120) NULL AFTER departamento_id,
    ADD COLUMN municipio_id INT NULL AFTER departamento_nombre,
    ADD COLUMN municipio_nombre VARCHAR(120) NULL AFTER municipio_id;

ALTER TABLE evaluations
    ADD COLUMN pais_id INT NULL AFTER colegio_nombre,
    ADD COLUMN pais_nombre VARCHAR(120) NULL AFTER pais_id,
    ADD COLUMN departamento_id INT NULL AFTER pais_nombre,
    ADD COLUMN departamento_nombre VARCHAR(120) NULL AFTER departamento_id,
    ADD COLUMN municipio_id INT NULL AFTER departamento_nombre,
    ADD COLUMN municipio_nombre VARCHAR(120) NULL AFTER municipio_id;
