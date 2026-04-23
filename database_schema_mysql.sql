CREATE TABLE IF NOT EXISTS participants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(120) NOT NULL,
    last_name VARCHAR(120) NOT NULL,
    middle_name VARCHAR(120) NOT NULL,
    age TINYINT UNSIGNED NOT NULL,
    sex CHAR(1) NOT NULL,
    group_name VARCHAR(120) NOT NULL,
    colegio_id INT NULL,
    colegio_nombre VARCHAR(255) NULL,
    pais_id INT NULL,
    pais_nombre VARCHAR(120) NULL,
    departamento_id INT NULL,
    departamento_nombre VARCHAR(120) NULL,
    municipio_id INT NULL,
    municipio_nombre VARCHAR(120) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_participant_identity (first_name, last_name, middle_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS colegios (
    id INT NOT NULL PRIMARY KEY,
    nombre VARCHAR(255) NULL,
    tipo_institucion INT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    participant_id BIGINT UNSIGNED NOT NULL,
    applied_at DATETIME NOT NULL,
    sex CHAR(1) NOT NULL,
    group_name VARCHAR(120) NOT NULL,
    colegio_id INT NULL,
    colegio_nombre VARCHAR(255) NULL,
    pais_id INT NULL,
    pais_nombre VARCHAR(120) NULL,
    departamento_id INT NULL,
    departamento_nombre VARCHAR(120) NULL,
    municipio_id INT NULL,
    municipio_nombre VARCHAR(120) NULL,
    validity_score SMALLINT NOT NULL,
    validity_state VARCHAR(20) NOT NULL,
    validity_details_json JSON NOT NULL,
    raw_scores_json JSON NOT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_evaluations_participant_applied (participant_id, applied_at),
    CONSTRAINT fk_evaluations_participant FOREIGN KEY (participant_id) REFERENCES participants(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluation_answers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    evaluation_id BIGINT UNSIGNED NOT NULL,
    block_id VARCHAR(30) NOT NULL,
    selected_mas_activity_id VARCHAR(30) NOT NULL,
    selected_menos_activity_id VARCHAR(30) NOT NULL,
    UNIQUE KEY uq_answer_block (evaluation_id, block_id),
    CONSTRAINT fk_answers_evaluation FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluation_scale_scores (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    evaluation_id BIGINT UNSIGNED NOT NULL,
    scale_id VARCHAR(40) NOT NULL,
    raw_score SMALLINT NOT NULL,
    UNIQUE KEY uq_scale_score (evaluation_id, scale_id),
    CONSTRAINT fk_scale_scores_evaluation FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluation_percentiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    evaluation_id BIGINT UNSIGNED NOT NULL,
    scale_id VARCHAR(40) NOT NULL,
    percentile_value SMALLINT UNSIGNED NOT NULL,
    UNIQUE KEY uq_percentile (evaluation_id, scale_id),
    CONSTRAINT fk_percentiles_evaluation FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
