# Reporte técnico de normativas y validez (test vocacional)

Fecha de actualización: 2026-04-16

## 1) Fuente y alcance

- **Fuente de percentiles**: hoja `ESTTENES Y PERFIL` del archivo `test.xls`.
- **Extracción aplicada**: `tools/extract_kuder_from_xls.py`.
- **Alcance**: se completaron tablas para las 10 escalas de interés (`0..9`) por sexo.

## 2) Qué se tomó directamente del Excel

- Códigos de escala del encabezado de la hoja:
  - Masculino: columnas de código `D,G,J,M,P,S,V,Y,AB,AE`.
  - Femenino: columnas de código `AI,AL,AO,AR,AU,AX,BA,BD,BG,BJ`.
- Valores percentilares por escala:
  - Masculino: columnas `E,H,K,N,Q,T,W,Z,AC,AF`.
  - Femenino: columnas `AJ,AM,AP,AS,AV,AY,BB,BE,BH,BK`.
- Método de lookup configurado: `floor` (aproxima al bruto inferior, compatible con `BUSCARV` aproximado).

## 3) Supuesto operativo explícito (trazable)

Para convertir fila de Excel a `bruto`, se usa:

- `fila_base = fila donde A == "muj" y B == 0`
- `bruto = fila_actual - fila_base`

Este supuesto se registra en `storage/logs/extraction_report.json` dentro de `percentiles_extraccion.raw_formula`.

## 4) Estado de reglas de validez

Se confirmó la regla directamente desde el Excel (`Grafica de Resultados`, fórmula en `D7`):

`SI(B7<32,"PRUEBA NO VÁLIDA",SI(B7>35,"PRUEBA VÁLIDA","PRUEBA DUDOSA"))`

Mapeo aplicado en `config/test-vocacional/validity_rules.json`:

1. `invalido` si `indice_validez < 32`
2. `valido` si `indice_validez > 35`
3. `dudoso` en caso contrario (`32..35`)

Nota: no se cambió el cálculo de `indice_validez`; solo la interpretación final (`decision`).

## 5) Manejo de percentiles faltantes

Si alguna escala no trae percentil (valor `null` desde motor), el presentador:

- la registra en `alertas_tecnicas.escalas_sin_percentil`
- muestra `N/D` en la vista, evitando convertir silenciosamente a `0`

Esto permite identificar faltantes de forma explícita en el reporte técnico de resultados.
