ALTER TABLE participants
    ADD COLUMN IF NOT EXISTS pais_id INT NULL AFTER colegio_nombre,
    ADD COLUMN IF NOT EXISTS pais_nombre VARCHAR(120) NULL AFTER pais_id,
    ADD COLUMN IF NOT EXISTS departamento_id INT NULL AFTER pais_nombre,
    ADD COLUMN IF NOT EXISTS departamento_nombre VARCHAR(120) NULL AFTER departamento_id,
    ADD COLUMN IF NOT EXISTS municipio_id INT NULL AFTER departamento_nombre,
    ADD COLUMN IF NOT EXISTS municipio_nombre VARCHAR(120) NULL AFTER municipio_id;

ALTER TABLE evaluations
    ADD COLUMN IF NOT EXISTS pais_id INT NULL AFTER colegio_nombre,
    ADD COLUMN IF NOT EXISTS pais_nombre VARCHAR(120) NULL AFTER pais_id,
    ADD COLUMN IF NOT EXISTS departamento_id INT NULL AFTER pais_nombre,
    ADD COLUMN IF NOT EXISTS departamento_nombre VARCHAR(120) NULL AFTER departamento_id,
    ADD COLUMN IF NOT EXISTS municipio_id INT NULL AFTER departamento_nombre,
    ADD COLUMN IF NOT EXISTS municipio_nombre VARCHAR(120) NULL AFTER municipio_id;
