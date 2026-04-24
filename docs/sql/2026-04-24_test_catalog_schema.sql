-- Catalog schema for Test Vocacional.
-- This creates only catalog tables. It does not change the current JSON runtime.

CREATE TABLE IF NOT EXISTS test_catalog_versions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    version_key VARCHAR(60) NOT NULL,
    name VARCHAR(160) NOT NULL,
    source_name VARCHAR(160) NULL,
    source_hash CHAR(64) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    active_catalog_slot TINYINT
        GENERATED ALWAYS AS (CASE WHEN status = 'active' THEN 1 ELSE NULL END) STORED,
    notes TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    activated_at DATETIME NULL,
    retired_at DATETIME NULL,
    UNIQUE KEY uq_test_catalog_versions_key (version_key),
    UNIQUE KEY uq_test_catalog_versions_single_active (active_catalog_slot),
    KEY idx_test_catalog_versions_status (status),
    CONSTRAINT chk_test_catalog_versions_status
        CHECK (status IN ('draft', 'active', 'retired'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_catalog_source_files (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    catalog_version_id BIGINT UNSIGNED NOT NULL,
    logical_name VARCHAR(80) NOT NULL,
    source_path VARCHAR(255) NOT NULL,
    sha256_hash CHAR(64) NOT NULL,
    imported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_test_catalog_source_file (catalog_version_id, logical_name),
    CONSTRAINT fk_test_catalog_source_files_version
        FOREIGN KEY (catalog_version_id) REFERENCES test_catalog_versions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_catalog_scales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    catalog_version_id BIGINT UNSIGNED NOT NULL,
    scale_key VARCHAR(40) NOT NULL,
    name VARCHAR(160) NOT NULL,
    scale_group VARCHAR(40) NOT NULL,
    display_order SMALLINT UNSIGNED NOT NULL,
    excel_code VARCHAR(10) NULL,
    excel_code_column VARCHAR(10) NULL,
    excel_mas_column VARCHAR(10) NULL,
    excel_menos_column VARCHAR(10) NULL,
    marker_weight SMALLINT NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_test_catalog_scales_key (catalog_version_id, scale_key),
    UNIQUE KEY uq_test_catalog_scales_order (catalog_version_id, display_order),
    UNIQUE KEY uq_test_catalog_scales_id_version (id, catalog_version_id),
    KEY idx_test_catalog_scales_group (catalog_version_id, scale_group),
    CONSTRAINT fk_test_catalog_scales_version
        FOREIGN KEY (catalog_version_id) REFERENCES test_catalog_versions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_catalog_blocks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    catalog_version_id BIGINT UNSIGNED NOT NULL,
    block_key VARCHAR(30) NOT NULL,
    display_order SMALLINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_test_catalog_blocks_key (catalog_version_id, block_key),
    UNIQUE KEY uq_test_catalog_blocks_order (catalog_version_id, display_order),
    UNIQUE KEY uq_test_catalog_blocks_id_version (id, catalog_version_id),
    CONSTRAINT fk_test_catalog_blocks_version
        FOREIGN KEY (catalog_version_id) REFERENCES test_catalog_versions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_catalog_activities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    catalog_version_id BIGINT UNSIGNED NOT NULL,
    block_id BIGINT UNSIGNED NOT NULL,
    activity_key VARCHAR(30) NOT NULL,
    activity_text TEXT NOT NULL,
    position_in_block TINYINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_test_catalog_activities_key (catalog_version_id, activity_key),
    UNIQUE KEY uq_test_catalog_activities_position (block_id, position_in_block),
    KEY idx_test_catalog_activities_version_block (catalog_version_id, block_id),
    CONSTRAINT fk_test_catalog_activities_version
        FOREIGN KEY (catalog_version_id) REFERENCES test_catalog_versions(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_test_catalog_activities_block
        FOREIGN KEY (block_id, catalog_version_id) REFERENCES test_catalog_blocks(id, catalog_version_id)
        ON DELETE CASCADE,
    CONSTRAINT chk_test_catalog_activities_position
        CHECK (position_in_block >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_catalog_response_requirements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    catalog_version_id BIGINT UNSIGNED NOT NULL,
    response_side VARCHAR(10) NOT NULL,
    required_count TINYINT UNSIGNED NOT NULL DEFAULT 1,
    UNIQUE KEY uq_test_catalog_response_requirements (catalog_version_id, response_side),
    CONSTRAINT fk_test_catalog_response_requirements_version
        FOREIGN KEY (catalog_version_id) REFERENCES test_catalog_versions(id)
        ON DELETE CASCADE,
    CONSTRAINT chk_test_catalog_response_requirements_side
        CHECK (response_side IN ('mas', 'menos'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_catalog_scoring_metadata (
    catalog_version_id BIGINT UNSIGNED PRIMARY KEY,
    model VARCHAR(80) NOT NULL,
    source_name VARCHAR(255) NULL,
    formula TEXT NULL,
    CONSTRAINT fk_test_catalog_scoring_metadata_version
        FOREIGN KEY (catalog_version_id) REFERENCES test_catalog_versions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_catalog_scoring_positions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    catalog_version_id BIGINT UNSIGNED NOT NULL,
    block_id BIGINT UNSIGNED NOT NULL,
    position_in_block TINYINT UNSIGNED NOT NULL,
    excel_row INT UNSIGNED NULL,
    mas_rule_type VARCHAR(60) NOT NULL DEFAULT 'sumar_peso_directo',
    menos_rule_type VARCHAR(60) NOT NULL DEFAULT 'sumar_peso_directo',
    UNIQUE KEY uq_test_catalog_scoring_position (
        catalog_version_id,
        block_id,
        position_in_block
    ),
    KEY idx_test_catalog_scoring_positions_lookup (
        catalog_version_id,
        block_id,
        position_in_block
    ),
    CONSTRAINT fk_test_catalog_scoring_positions_version
        FOREIGN KEY (catalog_version_id) REFERENCES test_catalog_versions(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_test_catalog_scoring_positions_block
        FOREIGN KEY (block_id, catalog_version_id) REFERENCES test_catalog_blocks(id, catalog_version_id)
        ON DELETE CASCADE,
    CONSTRAINT chk_test_catalog_scoring_positions_position
        CHECK (position_in_block >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_catalog_scoring_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    catalog_version_id BIGINT UNSIGNED NOT NULL,
    block_id BIGINT UNSIGNED NOT NULL,
    position_in_block TINYINT UNSIGNED NOT NULL,
    response_side VARCHAR(10) NOT NULL,
    scale_id BIGINT UNSIGNED NOT NULL,
    weight SMALLINT NOT NULL DEFAULT 1,
    rule_type VARCHAR(60) NOT NULL DEFAULT 'sumar_peso_directo',
    excel_row INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_test_catalog_scoring_rule (
        catalog_version_id,
        block_id,
        position_in_block,
        response_side,
        scale_id
    ),
    KEY idx_test_catalog_scoring_lookup (
        catalog_version_id,
        block_id,
        position_in_block,
        response_side
    ),
    KEY idx_test_catalog_scoring_scale (catalog_version_id, scale_id),
    CONSTRAINT fk_test_catalog_scoring_rules_version
        FOREIGN KEY (catalog_version_id) REFERENCES test_catalog_versions(id)
        ON DELETE CASCADE,
    CONSTRAINT fk_test_catalog_scoring_rules_block
        FOREIGN KEY (block_id, catalog_version_id) REFERENCES test_catalog_blocks(id, catalog_version_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_test_catalog_scoring_rules_scale
        FOREIGN KEY (scale_id, catalog_version_id) REFERENCES test_catalog_scales(id, catalog_version_id)
        ON DELETE RESTRICT,
    CONSTRAINT chk_test_catalog_scoring_rules_side
        CHECK (response_side IN ('mas', 'menos')),
    CONSTRAINT chk_test_catalog_scoring_rules_position
        CHECK (position_in_block >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_catalog_validity_base_rules (
    catalog_version_id BIGINT UNSIGNED PRIMARY KEY,
    mas_per_block TINYINT UNSIGNED NOT NULL DEFAULT 1,
    menos_per_block TINYINT UNSIGNED NOT NULL DEFAULT 1,
    allow_duplicate_in_block BOOLEAN NOT NULL DEFAULT FALSE,
    status_note VARCHAR(120) NULL,
    notes TEXT NULL,
    CONSTRAINT fk_test_catalog_validity_base_rules_version
        FOREIGN KEY (catalog_version_id) REFERENCES test_catalog_versions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_catalog_validity_metrics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    catalog_version_id BIGINT UNSIGNED NOT NULL,
    metric_key VARCHAR(60) NOT NULL,
    formula TEXT NOT NULL,
    invalid_threshold INT NULL,
    display_order SMALLINT UNSIGNED NOT NULL,
    UNIQUE KEY uq_test_catalog_validity_metrics_key (catalog_version_id, metric_key),
    UNIQUE KEY uq_test_catalog_validity_metrics_order (catalog_version_id, display_order),
    CONSTRAINT fk_test_catalog_validity_metrics_version
        FOREIGN KEY (catalog_version_id) REFERENCES test_catalog_versions(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_catalog_validity_decision_rules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    catalog_version_id BIGINT UNSIGNED NOT NULL,
    rule_key VARCHAR(100) NOT NULL,
    condition_expression VARCHAR(255) NOT NULL,
    resulting_state VARCHAR(30) NOT NULL,
    priority SMALLINT UNSIGNED NOT NULL,
    notes TEXT NULL,
    UNIQUE KEY uq_test_catalog_validity_decision_key (catalog_version_id, rule_key),
    UNIQUE KEY uq_test_catalog_validity_decision_priority (catalog_version_id, priority),
    KEY idx_test_catalog_validity_decision_state (catalog_version_id, resulting_state),
    CONSTRAINT fk_test_catalog_validity_decision_rules_version
        FOREIGN KEY (catalog_version_id) REFERENCES test_catalog_versions(id)
        ON DELETE CASCADE,
    CONSTRAINT chk_test_catalog_validity_decision_state
        CHECK (resulting_state IN ('valido', 'dudoso', 'invalido', 'desconocido'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_catalog_percentile_sets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    catalog_version_id BIGINT UNSIGNED NOT NULL,
    sex CHAR(1) NOT NULL,
    lookup_method VARCHAR(30) NOT NULL DEFAULT 'floor',
    source_name VARCHAR(160) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_test_catalog_percentile_sets_sex (catalog_version_id, sex),
    UNIQUE KEY uq_test_catalog_percentile_sets_id_version (id, catalog_version_id),
    CONSTRAINT fk_test_catalog_percentile_sets_version
        FOREIGN KEY (catalog_version_id) REFERENCES test_catalog_versions(id)
        ON DELETE CASCADE,
    CONSTRAINT chk_test_catalog_percentile_sets_sex
        CHECK (sex IN ('M', 'F')),
    CONSTRAINT chk_test_catalog_percentile_sets_lookup
        CHECK (lookup_method IN ('floor', 'exact', 'nearest'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS test_catalog_percentiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    catalog_version_id BIGINT UNSIGNED NOT NULL,
    percentile_set_id BIGINT UNSIGNED NOT NULL,
    scale_id BIGINT UNSIGNED NOT NULL,
    raw_score SMALLINT NOT NULL,
    percentile_value SMALLINT UNSIGNED NOT NULL,
    UNIQUE KEY uq_test_catalog_percentiles_lookup (percentile_set_id, scale_id, raw_score),
    KEY idx_test_catalog_percentiles_scale_raw (catalog_version_id, scale_id, raw_score),
    CONSTRAINT fk_test_catalog_percentiles_set
        FOREIGN KEY (percentile_set_id, catalog_version_id) REFERENCES test_catalog_percentile_sets(id, catalog_version_id)
        ON DELETE CASCADE,
    CONSTRAINT fk_test_catalog_percentiles_scale
        FOREIGN KEY (scale_id, catalog_version_id) REFERENCES test_catalog_scales(id, catalog_version_id)
        ON DELETE RESTRICT,
    CONSTRAINT chk_test_catalog_percentiles_value
        CHECK (percentile_value BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
