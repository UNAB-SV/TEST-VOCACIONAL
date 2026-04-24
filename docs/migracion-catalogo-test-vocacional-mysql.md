# Migración del catálogo del Test Vocacional a MySQL

## Alcance

Este diseño migra el catálogo del instrumento desde JSON a MySQL sin cambiar todavía el flujo de calificación. La aplicación actual sigue leyendo:

- `config/test-vocacional/questions_blocks.json`
- `config/test-vocacional/scoring_rules.json`
- `config/test-vocacional/validity_rules.json`
- `config/test-vocacional/scales.json`
- `config/test-vocacional/excel_mapping.json`
- `config/test-vocacional/percentiles/male.json`
- `config/test-vocacional/percentiles/female.json`

El objetivo de esta fase es dejar preparado el modelo relacional, versionado y estrategia de migración para que luego se pueda introducir un repositorio DB con fallback temporal a JSON.

## Modelo propuesto

### Versionado del catálogo

Tabla principal: `test_catalog_versions`.

Cada carga completa del instrumento debe pertenecer a una versión de catálogo. Esto evita mezclar preguntas de una versión con reglas o baremos de otra. Una evaluación futura debería guardar el `catalog_version_id` usado al calcularse.

Estados recomendados:

- `draft`: catálogo cargado, pendiente de validación.
- `active`: catálogo que usa producción.
- `retired`: catálogo histórico.

### Escalas

Tabla: `test_catalog_scales`.

Representa las escalas de `scales.json`, incluyendo la escala de control `validez`. Se conservan metadatos de origen Excel (`excel_code`, columnas `mas`, `menos`, `codigo`) porque hoy forman parte de la trazabilidad del instrumento y pueden ayudar en importaciones futuras.

Relación:

- Una versión de catálogo tiene muchas escalas.
- Las reglas de puntuación y percentiles referencian escalas por FK interna.

### Bloques y actividades

Tablas:

- `test_catalog_blocks`
- `test_catalog_activities`

Un bloque contiene actividades ordenadas por `position_in_block`. El modelo conserva las claves externas del JSON (`B001`, `A0001`) como campos `*_key`, pero usa IDs internos para relaciones.

Restricciones clave:

- `UNIQUE(catalog_version_id, block_key)`
- `UNIQUE(block_id, position_in_block)`
- `UNIQUE(catalog_version_id, activity_key)`

### Reglas de puntuación

Tabla: `test_catalog_scoring_rules`.

La regla actual depende de:

```text
bloque + posición dentro del bloque + respuesta (mas/menos) -> escala + peso
```

Por eso la tabla no debe depender directamente de `activity_id` como clave principal de búsqueda. La actividad se puede derivar por bloque y posición, pero la regla psicométrica actual está expresada por posición.

Ejemplo conceptual:

```text
B002, posición 1, mas   -> servicio_social +1
B002, posición 1, menos -> calculo +1
B002, posición 1, menos -> persuasivo +1
```

Restricción recomendada:

- `UNIQUE(catalog_version_id, block_id, position_in_block, response_side, scale_id)`

Se incluye `excel_row` para trazabilidad de la fila original.

### Requerimientos de respuesta

Tabla: `test_catalog_response_requirements`.

Actualmente `scoring_rules.json` declara:

```json
"respuesta_por_bloque": {
  "mas": { "requerido": 1 },
  "menos": { "requerido": 1 }
}
```

Se modela como una tabla simple por versión y lado de respuesta.

### Reglas de validez

Tablas:

- `test_catalog_validity_base_rules`
- `test_catalog_validity_metrics`
- `test_catalog_validity_decision_rules`

La validez actual es declarativa y el motor interpreta IDs conocidos (`omisiones`, `colision_mas_menos`, `indice_validez`) y condiciones simples (`indice_validez < 32`, `default`). Por compatibilidad temporal conviene conservar las fórmulas y condiciones como texto declarativo, no convertirlas todavía en lógica SQL.

Modelo:

- Reglas base: conteos requeridos por bloque y si se permite duplicar la misma actividad como `mas` y `menos`.
- Métricas: ID, fórmula declarativa y umbral inválido opcional.
- Decisiones: prioridad, condición, estado resultante.

La prioridad reemplaza el orden del array JSON. Es crítica porque `default` debe evaluarse al final.

### Percentiles

Tablas:

- `test_catalog_percentile_sets`
- `test_catalog_percentiles`

Los percentiles dependen de:

```text
sexo + escala + puntaje bruto -> percentil
```

`test_catalog_percentile_sets` guarda sexo, fuente y método de búsqueda (`floor` hoy). `test_catalog_percentiles` contiene las filas por escala y puntaje bruto.

Restricciones:

- `UNIQUE(catalog_version_id, sex)`
- `UNIQUE(percentile_set_id, scale_id, raw_score)`

La estrategia `floor` no requiere duplicar rangos. Para resolver en SQL:

```sql
SELECT percentile_value
FROM test_catalog_percentiles
WHERE percentile_set_id = ?
  AND scale_id = ?
  AND raw_score <= ?
ORDER BY raw_score DESC
LIMIT 1;
```

### Metadatos de fuente

Tabla: `test_catalog_source_files`.

Guarda nombre lógico, ruta JSON, hash SHA-256 y fecha de importación. Esto permite validar que una versión DB fue generada exactamente desde los JSON esperados y facilita rollback.

## Relación con el flujo actual

Código que hoy lee JSON:

- `src/repositories/QuestionsBlockRepository.php`
- `src/services/CalculationEngine.php`
- `src/services/ScoreService.php`
- `config/test-vocacional/catalog.php`

En esta fase no se modifican. En la fase siguiente se recomienda introducir una capa de compatibilidad:

- `CatalogRepository` como interfaz de lectura del catálogo completo.
- `JsonCatalogRepository` que reproduce las estructuras actuales.
- `MysqlCatalogRepository` que lee tablas y reconstruye arrays compatibles con `ScoreService`.
- `FallbackCatalogRepository` que intenta DB y cae a JSON si no hay versión activa válida.
- `LazyCatalogRepository` que evita abrir la conexión MySQL hasta la primera lectura, para que el fallback pueda actuar si MySQL no está disponible.

Con esto `ScoreService` puede permanecer intacto inicialmente.

La fuente activa se configura en `config/app.php`:

```php
'catalog' => [
    'source' => 'mysql_with_json_fallback',
    'version_key' => 'current-json',
],
```

Valores soportados:

- `mysql`: solo MySQL.
- `json`: solo JSON.
- `mysql_with_json_fallback`: MySQL como principal y JSON como respaldo temporal.

## Estrategia de migración segura

1. Crear tablas nuevas sin tocar las existentes de evaluaciones.
2. Importar JSON a una versión `draft`.
3. Validar conteos y checks:
   - 11 escalas.
   - 168 bloques.
   - 504 actividades.
   - Cada bloque tiene 3 actividades.
   - Cada bloque tiene requerimiento `mas = 1` y `menos = 1`.
   - Las reglas de puntuación DB reconstruidas producen el mismo resultado que JSON para respuestas de prueba.
   - Hay percentiles para sexo `M` y `F`.
4. Marcar versión como `active` solo después de validación.
5. Mantener JSON como fuente de fallback temporal.
6. Agregar feature flag en configuración:
   - `CATALOG_SOURCE=json`
   - `CATALOG_SOURCE=mysql`
   - `CATALOG_SOURCE=mysql_with_json_fallback`
7. Registrar en cada evaluación el `catalog_version_id` usado cuando el cálculo venga de DB.

## Rollback

Rollback funcional:

- Cambiar el feature flag a `json`.
- Mantener los JSON actuales intactos.
- No eliminar tablas ni datos de catálogo.

Rollback de datos:

- Marcar la versión DB defectuosa como `retired`.
- Activar una versión previa validada.
- Si la aplicación todavía usa fallback, no hay impacto en la calificación.

Rollback de código:

- La primera integración debe limitarse a una nueva capa de repositorio. Si hay falla, se vuelve a inyectar `JsonCatalogRepository`.
- `ScoreService` no debe depender de PDO durante la fase de compatibilidad.

## Archivos a modificar en la fase de implementación

No se modifican en esta fase, pero estos son los puntos esperados:

- `config/app.php`: agregar fuente de catálogo y versión activa opcional.
- `config/test-vocacional/catalog.php`: mantener rutas JSON y agregar metadatos de fallback si conviene.
- `src/helpers/ServiceContainer.php`: inyectar el repositorio de catálogo elegido.
- `src/services/CalculationEngine.php`: pedir el catálogo al repositorio en vez de cargar JSON directamente.
- `src/repositories/QuestionsBlockRepository.php`: reemplazar o adaptar a `CatalogRepository`.
- `src/repositories/PdoConnectionFactory.php`: reutilizar conexión PDO existente.
- `src/repositories/`: agregar `CatalogRepository.php`, `JsonCatalogRepository.php`, `MysqlCatalogRepository.php` y opcionalmente `FallbackCatalogRepository.php`.
- `tests/`: agregar pruebas de paridad JSON vs DB.
- `database_schema_mysql.sql` o scripts bajo `docs/sql/`: incluir o referenciar el DDL definitivo.

## SQL

El DDL propuesto está en:

```text
docs/sql/2026-04-24_test_catalog_schema.sql
```

## Importador

La implementación de carga inicial está documentada en:

```text
docs/importacion-catalogo-test-vocacional-mysql.md
```

Script:

```text
scripts/import_test_catalog.php
```
