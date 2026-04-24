# Validación de paridad JSON vs MySQL

## Objetivo

Confirmar que el catálogo importado a MySQL es funcionalmente equivalente al catálogo JSON actual antes de considerar la migración lista para producción.

## Script

```bash
php scripts/validate_catalog_parity.php --version-key=current-json
```

El script compara:

- Escalas.
- Bloques.
- Actividades.
- Reglas de scoring.
- Reglas de validez.
- Percentiles por sexo `M` y `F`.

Además ejecuta escenarios de cálculo con `ScoreService` usando ambas fuentes y compara:

- `puntajes_brutos`
- `percentiles`
- `validez_puntaje`
- `validez_estado`
- `escalas_ordenadas_de_mayor_a_menor`

## Reporte

El reporte queda en:

```text
storage/logs/catalog_parity_report.json
```

Estados posibles:

- `equivalent`: no se encontraron diferencias estructurales ni funcionales.
- `different`: existe al menos una diferencia. En ese caso el script retorna exit code `1`.

Cada diferencia incluye una ruta tipo JSON path y el valor observado en JSON y MySQL.

## Prueba automatizada

```bash
php tests/CatalogParityTest.php
```

Esta prueba falla si el script de paridad detecta diferencias.

## Criterio de producción

No se debe cambiar `config/app.php` a `catalog.source = mysql` sin que:

```bash
php scripts/import_test_catalog.php --apply-schema --version-key=current-json
php scripts/validate_catalog_parity.php --version-key=current-json
```

termine con `Resultado: equivalent`.

