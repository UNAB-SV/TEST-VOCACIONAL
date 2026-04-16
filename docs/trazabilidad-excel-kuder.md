# Trazabilidad Excel (`test.xls`) → motor PHP

Este documento registra cómo se refleja la lógica de la hoja de cálculo en el código del motor (`ScoreService`).

## 1) Asignación de puntajes por escala (modelo actual)

- **Excel (fuente única):** hoja `PRUEBA`, matriz de columnas `+/-` por escala.
- **Regla de lectura:** el puntaje depende de `(bloque, índice_en_bloque, respuesta)`:
  - `bloque`: `B001..B168`
  - `índice_en_bloque`: `1..3`
  - `respuesta`: `mas` / `menos`
- **PHP:** `applySideScore()` ya no usa `claves` por actividad. Busca en `scoring_rules.json` la ruta:
  - `scoring_rules.matriz_por_bloque.{bloque}.{indice}.{mas|menos}.scales`.
- **Traducción a puntaje:** por cada escala marcada en esa celda de la matriz se suma `+1` (sin multiplicador global).
- **Auditoría:** cada suma queda en `traza_calculo.asignacion_puntajes_por_escala`, incluyendo `indice_en_bloque`.

## 2) Suma de puntajes

- **Excel (fuente):** suma acumulada por escala del bloque completo.
- **PHP:** `calculateRawScores()` acumula todos los `delta` en `puntajes_brutos`.
- **Reglas especiales:** `applySpecialRules()` permite reproducir reglas no lineales de la planilla mediante configuración (`special_rules`) sin hardcode.

## 3) Lógica de validez

- **Excel (fuente):** hoja `Grafica de Resultados`, fórmula en `D7` con el texto final de validez.
- **Fórmula Excel (lógica):** `SI(B7<32,"PRUEBA NO VÁLIDA",SI(B7>35,"PRUEBA VÁLIDA","PRUEBA DUDOSA"))`.
- **Umbrales exactos derivados del Excel:**
  - `indice_validez < 32` → `PRUEBA NO VÁLIDA`
  - `32 <= indice_validez <= 35` → `PRUEBA DUDOSA`
  - `indice_validez > 35` → `PRUEBA VÁLIDA`
- **PHP:**
  - `calculateValidityMetrics()` calcula métricas configuradas.
  - `resolveValidityState()` evalúa `decision` en orden, sin cambio estructural en `ScoreService`.
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

## 6) Columnas de escala en el Excel (`PRUEBA`)

Definidas en `scoring_rules.escalas_columnas_excel`:

- `aire_libre` → código `0`: `MAS=G`, `CÓDIGO=H`, `MENOS=I`
- `mecanico` → código `1`: `MAS=J`, `CÓDIGO=K`, `MENOS=L`
- `calculo` → código `2`: `MAS=M`, `CÓDIGO=N`, `MENOS=O`
- `cientifico` → código `3`: `MAS=P`, `CÓDIGO=Q`, `MENOS=R`
- `persuasivo` → código `4`: `MAS=S`, `CÓDIGO=T`, `MENOS=U`
- `artistico` → código `5`: `MAS=V`, `CÓDIGO=W`, `MENOS=X`
- `literario` → código `6`: `MAS=Y`, `CÓDIGO=Z`, `MENOS=AA`
- `musical` → código `7`: `MAS=AB`, `CÓDIGO=AC`, `MENOS=AD`
- `servicio_social` → código `8`: `MAS=AE`, `CÓDIGO=AF`, `MENOS=AG`
- `oficina` → código `9`: `MAS=AH`, `CÓDIGO=AI`, `MENOS=AJ`
- `validez` → código `V`: `MAS=AK`, `CÓDIGO=AL`, `MENOS=AM`

## 6) Trazabilidad/auditoría del cálculo

El resultado del motor ahora incluye `traza_calculo` con 3 secciones:

1. `asignacion_puntajes_por_escala`
2. `reglas_especiales`
3. `conversion_percentiles`

Esto permite reconstruir cada operación del cálculo para auditoría técnica y psicométrica.
