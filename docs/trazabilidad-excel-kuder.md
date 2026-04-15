# Trazabilidad Excel (`test.xls`) → motor PHP

Este documento registra cómo se refleja la lógica de la hoja de cálculo en el código del motor (`ScoreService`).

## 1) Asignación de puntajes por escala

- **Excel (fuente):** columnas de claves por actividad para `más` y `menos`.
- **PHP:** `applySideScore()` aplica cada clave por actividad/escala usando:
  - `peso` de la actividad.
  - `multiplicador` de `scoring_rules.json` (`más=+1`, `menos=-1` por defecto).
  - `overrides` por escala (por ejemplo `validez`).
- **Auditoría:** se agrega una entrada en `traza_calculo.asignacion_puntajes_por_escala` por cada suma parcial.

## 2) Suma de puntajes

- **Excel (fuente):** suma acumulada por escala del bloque completo.
- **PHP:** `calculateRawScores()` acumula todos los `delta` en `puntajes_brutos`.
- **Reglas especiales:** `applySpecialRules()` permite reproducir reglas no lineales de la planilla mediante configuración (`special_rules`) sin hardcode.

## 3) Lógica de validez

- **Excel (fuente):** reglas de omisiones, colisiones y rango de índice de validez.
- **PHP:**
  - `calculateValidityMetrics()` calcula métricas configuradas.
  - `resolveValidityState()` evalúa reglas declarativas (`and` / `or`) en el orden definido.
- **Resultado:** `validez_puntaje`, `detalles_validez` y `validez_estado`.

## 4) Conversión a percentiles por sexo

- **Excel (fuente):** tablas separadas por sexo.
- **PHP:** `calculatePercentiles()` usa la tabla correspondiente y método configurable (`lookup_method`).
- **Compatibilidad Excel clásica:** método `floor` (aproximación por valor inferior) disponible para emular `BUSCARV` aproximado en tablas ordenadas.

## 5) Reglas especiales y ambigüedades

- Se incorporó extensión `special_rules` para conservar reglas particulares del Excel como datos.
- TODOs explícitos en código para aclarar:
  - método final de lookup percentilar usado en `test.xls`.
  - tipos adicionales de acciones especiales si aparecen en la matriz final.

## 6) Trazabilidad/auditoría del cálculo

El resultado del motor ahora incluye `traza_calculo` con 3 secciones:

1. `asignacion_puntajes_por_escala`
2. `reglas_especiales`
3. `conversion_percentiles`

Esto permite reconstruir cada operación del cálculo para auditoría técnica y psicométrica.
