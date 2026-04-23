-- Tabla maestra de instituciones.
CREATE TABLE IF NOT EXISTS colegios (
    id INT NOT NULL PRIMARY KEY,
    nombre VARCHAR(255) NULL,
    tipo_institucion INT NULL COMMENT '1: pública, 2: privada'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Compatibilidad histórica: mantenemos group_name y agregamos nuevas columnas para institución.
ALTER TABLE participants
    ADD COLUMN IF NOT EXISTS colegio_id INT NULL AFTER group_name,
    ADD COLUMN IF NOT EXISTS colegio_nombre VARCHAR(255) NULL AFTER colegio_id;

ALTER TABLE evaluations
    ADD COLUMN IF NOT EXISTS colegio_id INT NULL AFTER group_name,
    ADD COLUMN IF NOT EXISTS colegio_nombre VARCHAR(255) NULL AFTER colegio_id;

-- Índices recomendados para filtros y autocompletado.
CREATE INDEX idx_colegios_nombre ON colegios(nombre);
CREATE INDEX idx_evaluations_colegio_nombre ON evaluations(colegio_nombre);
