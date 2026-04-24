# Importación del catálogo del Test Vocacional a MySQL

## Entregables

- DDL: `docs/sql/2026-04-24_test_catalog_schema.sql`
- Importador: `scripts/import_test_catalog.php`
- Reporte generado: `storage/logs/catalog_import_report.json`

El importador usa como fuente de verdad los JSON actuales definidos en `config/test-vocacional/catalog.php`.

## Qué importa

- Escalas desde `scales.json`, enriquecidas con columnas Excel desde `scoring_rules.json`.
- Bloques y actividades desde `questions_blocks.json`.
- Requerimientos por respuesta y reglas de scoring desde `scoring_rules.json`.
- Reglas base, métricas y decisiones de validez desde `validity_rules.json`.
- Percentiles `M` y `F` desde `percentiles/male.json` y `percentiles/female.json`.
- Hashes SHA-256 de cada archivo fuente en `test_catalog_source_files`.

## Ejecución

Crear tablas e importar:

```bash
php scripts/import_test_catalog.php --apply-schema --version-key=current-json --name="Catalogo actual JSON"
```

Crear tablas, importar y marcar la versión como activa:

```bash
php scripts/import_test_catalog.php --apply-schema --activate --version-key=current-json --name="Catalogo actual JSON"
```

Ver ayuda:

```bash
php scripts/import_test_catalog.php --help
```

La conexión se toma de `config/app.php`. Se puede sobrescribir con variables de entorno:

```bash
DB_HOST=127.0.0.1 \
DB_PORT=3306 \
DB_DATABASE=test_vocacional \
DB_USERNAME=usr_test \
DB_PASSWORD='T3st121*' \
php scripts/import_test_catalog.php --apply-schema --version-key=current-json
```

## Idempotencia

El proceso usa claves naturales y `ON DUPLICATE KEY UPDATE`.

Claves estables principales:

- Versión: `test_catalog_versions.version_key`
- Escalas: `catalog_version_id + scale_key`
- Bloques: `catalog_version_id + block_key`
- Actividades: `catalog_version_id + activity_key`
- Scoring: `catalog_version_id + block_id + position_in_block + response_side + scale_id`
- Percentiles: `percentile_set_id + scale_id + raw_score`

En cada ejecución se eliminan filas obsoletas de esa misma versión si ya no existen en los JSON actuales. No se modifican las tablas de evaluaciones ni la lectura funcional del sistema.

## Validación automática

Al finalizar, el importador imprime conteos y escribe:

```text
storage/logs/catalog_import_report.json
```

La última importación validada dejó estos conteos:

```text
scales: 11
blocks: 168
activities: 504
response_requirements: 2
scoring_metadata: 1
scoring_positions: 504
scoring_rules: 935
validity_metrics: 3
validity_decision_rules: 3
percentile_sets: 2
percentiles_total: 1105
percentiles_male: 527
percentiles_female: 578
scoring_rules_by_side: mas=351, menos=584
blocks_with_activity_count_not_equal_3: 0
```

## Consultas de validación manual

Obtener la versión:

```sql
SELECT id, version_key, status, source_hash
FROM test_catalog_versions
WHERE version_key = 'current-json';
```

Conteos principales:

```sql
SELECT COUNT(*) AS total_escalas
FROM test_catalog_scales
WHERE catalog_version_id = 1;

SELECT COUNT(*) AS total_bloques
FROM test_catalog_blocks
WHERE catalog_version_id = 1;

SELECT COUNT(*) AS total_actividades
FROM test_catalog_activities
WHERE catalog_version_id = 1;

SELECT COUNT(*) AS total_reglas_scoring
FROM test_catalog_scoring_rules
WHERE catalog_version_id = 1;
```

Percentiles por sexo:

```sql
SELECT s.sex, COUNT(*) AS total_percentiles
FROM test_catalog_percentile_sets s
INNER JOIN test_catalog_percentiles p
    ON p.percentile_set_id = s.id
WHERE s.catalog_version_id = 1
GROUP BY s.sex
ORDER BY s.sex;
```

Bloques con cantidad distinta de 3 actividades:

```sql
SELECT b.block_key, COUNT(a.id) AS total_actividades
FROM test_catalog_blocks b
LEFT JOIN test_catalog_activities a
    ON a.block_id = b.id
WHERE b.catalog_version_id = 1
GROUP BY b.id, b.block_key
HAVING COUNT(a.id) <> 3;
```

Reglas de scoring por lado:

```sql
SELECT response_side, COUNT(*) AS total_reglas
FROM test_catalog_scoring_rules
WHERE catalog_version_id = 1
GROUP BY response_side
ORDER BY response_side;
```
