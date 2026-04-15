# Estrategia de pruebas para replicación PHP vs Excel (Test Kuder)

## Objetivo
Garantizar que el motor en PHP reproduzca exactamente las reglas del Excel original del test Kuder en:
- puntajes por escala,
- validez,
- percentiles por sexo,
- comportamiento ante bloques incompletos,
- manejo de respuestas inválidas.

---

## 1) Casos de prueba unitarios del motor de calificación

> Alcance principal: `ScoreService::calculate()` y validaciones/derivaciones internas del cálculo.

### 1.1 Casos base de puntajes por escala

1. **Suma simple más/menos (sin reglas especiales)**
   - **Dado**: respuestas completas y válidas por bloque.
   - **Verificar**:
     - cada selección en `mas` suma con multiplicador `+1`;
     - cada selección en `menos` suma con multiplicador `-1`.
   - **Esperado**: coincidencia exacta de puntajes brutos por escala.

2. **Escala con múltiples claves en una actividad**
   - **Dado**: actividad con más de una escala en claves `mas/menos` (ej. `calculo` y `oficina`).
   - **Verificar**: se actualizan todas las escalas afectadas por la actividad.

3. **Aplicación de override especial (validez)**
   - **Dado**: respuestas que impactan `validez` por `menos`.
   - **Verificar**: el override de `validez` con `menos = 0` neutraliza ese impacto.

4. **Ordenamiento de escalas**
   - **Dado**: múltiples escalas con puntajes variados.
   - **Verificar**: `escalas_ordenadas_de_mayor_a_menor` respeta orden descendente por bruto.

### 1.2 Casos unitarios de validez

1. **Validez “válido”**
   - Sin omisiones, sin colisión `mas == menos`, e índice de validez dentro del rango.

2. **Validez “inválido” por omisiones**
   - Al menos un bloque sin `mas` o `menos`.
   - Esperado: estado `invalido`.

3. **Validez “inválido” por colisión**
   - Al menos un bloque con misma actividad en `mas` y `menos`.
   - Esperado: estado `invalido`.

4. **Validez “dudoso” por índice de validez fuera de rango**
   - `validez < -3` o `validez > 3`.
   - Esperado: estado `dudoso`.

### 1.3 Casos unitarios de percentiles por sexo

1. **Lookup exacto en tabla**
   - bruto exacto existente en tabla masculina/femenina.
   - Esperado: percentil exacto del punto.

2. **Lookup por método `floor`**
   - bruto intermedio no exacto.
   - Esperado: usa el bruto inmediato inferior.

3. **Lookup por método `nearest`**
   - bruto intermedio no exacto.
   - Esperado: usa el punto más cercano.

4. **Tabla faltante por sexo**
   - sexo sin configuración.
   - Esperado: error controlado y mensaje explícito.

### 1.4 Casos unitarios de errores/entradas inválidas

1. Respuestas vacías (`answers = []`) -> excepción.
2. Bloque inexistente en respuestas -> excepción.
3. Actividad no perteneciente al bloque -> excepción.
4. Definición de bloques inválida -> excepción.
5. Reglas de puntuación inválidas -> excepción.
6. Tabla de percentiles inválida -> excepción.
7. Reglas de validez inválidas -> excepción.
8. Sexo vacío -> excepción.

---

## 2) Casos de prueba funcionales del flujo completo

> Alcance: flujo UI/API desde captura del participante hasta cierre y reporte.

### 2.1 Flujo feliz completo

1. Registrar participante válido.
2. Ingresar a instrucciones.
3. Iniciar prueba.
4. Guardar cada bloque con `mas`/`menos` válidos.
5. Finalizar prueba.
6. Verificar:
   - reporte generado,
   - persistencia de evaluación,
   - puntajes y percentiles esperados,
   - estado de validez esperado.

### 2.2 Flujo con respuestas inválidas por bloque

1. Intentar guardar bloque con `mas` vacío.
2. Intentar guardar bloque con `menos` vacío.
3. Intentar `mas == menos`.
4. Intentar actividad fuera del bloque.
5. Verificar HTTP 422 + mensajes de error por campo.

### 2.3 Flujo con bloques incompletos al finalizar

1. Responder parcialmente la prueba.
2. Ejecutar finalizar.
3. Verificar que no se califica ni persiste y que se muestran errores de bloques faltantes.

### 2.4 Flujo con percentiles por sexo

1. Ejecutar mismo patrón de respuestas para sexo `M` y `F`.
2. Verificar que los puntajes brutos no cambian por sexo.
3. Verificar que percentiles sí cambian según tabla `male`/`female`.

### 2.5 Flujo de robustez de sesión

1. Intentar guardar bloque sin sesión de prueba activa -> 403.
2. Intentar finalizar sin sesión válida -> redirección a inicio.

---

## 3) Estrategia para comparar resultados PHP vs Excel

## 3.1 Enfoque de “golden dataset”

1. Definir un conjunto fijo de casos (golden cases) con entradas representativas.
2. Calcular salida en Excel original (referencia) y congelar resultado esperado en CSV/JSON.
3. Ejecutar mismo input contra PHP.
4. Comparar automáticamente campo a campo.

## 3.2 Campos mínimos de comparación

- `puntajes_brutos.*` por escala,
- `validez_puntaje`,
- `validez_estado`,
- `percentiles.*` por escala,
- ranking de escalas (orden y empates).

## 3.3 Tolerancias y criterios de aceptación

- **Puntajes brutos**: tolerancia 0 (igual exacto).
- **Validez**: tolerancia 0 (igual exacto).
- **Percentiles**: tolerancia 0 (igual exacto).
- **Ranking**: misma secuencia para no-empates; en empates, mismo conjunto en posición empatada.

## 3.4 Automatización recomendada

1. Exportar casos de Excel a `tests/fixtures/excel_reference_cases.csv`.
2. Crear script PHP CLI (`tests/compare_excel_vs_php.php`) que:
   - lea casos,
   - ejecute `ScoreService`,
   - emita diff por campo.
3. Integrar a CI con condición de falla ante cualquier diferencia.

## 3.5 Matriz mínima de cobertura para comparación

- Sexo: `M`, `F`.
- Validez: `valido`, `dudoso`, `invalido`.
- Respuesta: completa, incompleta, inválida.
- Puntajes: bajos, medios, altos, cercanos a cortes percentilares.

---

## 4) Ejemplos de datasets de prueba

> Formato sugerido: JSON de entrada + JSON esperado (snapshot).

### Dataset A — válido base (control)

```json
{
  "participant": { "sexo": "M" },
  "answers": {
    "B001": { "mas": "A0001", "menos": "A0002" },
    "B002": { "mas": "A0004", "menos": "A0005" },
    "B003": { "mas": "A0007", "menos": "A0008" }
  }
}
```

**Qué valida**: puntajes por escala, ranking, percentiles masculinos y validez `valido`.

### Dataset B — mismo patrón pero sexo femenino

Igual al Dataset A, cambiando `sexo: "F"`.

**Qué valida**: diferencia de percentiles por sexo con mismo bruto.

### Dataset C — colisión más/menos (inválido)

```json
{
  "participant": { "sexo": "M" },
  "answers": {
    "B001": { "mas": "A0001", "menos": "A0001" },
    "B002": { "mas": "A0004", "menos": "A0005" },
    "B003": { "mas": "A0007", "menos": "A0008" }
  }
}
```

**Qué valida**: métrica `colision_mas_menos` y estado `invalido`.

### Dataset D — bloque incompleto

```json
{
  "participant": { "sexo": "F" },
  "answers": {
    "B001": { "mas": "A0001", "menos": "A0002" },
    "B002": { "mas": "A0004", "menos": "" },
    "B003": { "mas": "A0007", "menos": "A0008" }
  }
}
```

**Qué valida**: omisión, rechazo funcional en validación y/o estado inválido según nivel de prueba.

### Dataset E — actividad inválida

```json
{
  "participant": { "sexo": "M" },
  "answers": {
    "B001": { "mas": "A9999", "menos": "A0002" },
    "B002": { "mas": "A0004", "menos": "A0005" },
    "B003": { "mas": "A0007", "menos": "A0008" }
  }
}
```

**Qué valida**: detección de respuesta fuera de catálogo.

### Dataset F — frontera de percentiles

Construir casos donde bruto caiga:
- exacto en punto de tabla,
- entre dos puntos,
- por debajo del mínimo,
- por encima del máximo.

**Qué valida**: lookup exacto, `floor`/`nearest`, extremos.

---

## 5) Checklist final de validación antes de producción

## 5.1 Checklist funcional

- [ ] Todos los bloques requieren `mas` y `menos`.
- [ ] No se permite `mas == menos`.
- [ ] No se permiten actividades fuera del bloque.
- [ ] Finalizar test sin bloques completos está bloqueado.
- [ ] Se guarda evaluación solo cuando la entrada completa es válida.

## 5.2 Checklist de replicación Excel

- [ ] 100% de golden cases sin diferencias en `puntajes_brutos`.
- [ ] 100% de golden cases sin diferencias en `validez_puntaje` y `validez_estado`.
- [ ] 100% de golden cases sin diferencias en `percentiles` por sexo.
- [ ] Ranking de escalas coincide con Excel en todos los casos.

## 5.3 Checklist de calidad técnica

- [ ] Pruebas unitarias del motor ejecutadas en CI.
- [ ] Pruebas funcionales críticas ejecutadas en CI/CD.
- [ ] Reporte de diffs PHP vs Excel archivado por versión.
- [ ] Versionado de tablas de percentiles y reglas de validez controlado.
- [ ] Trazabilidad de cálculo habilitada para auditoría (`traza_calculo`).

## 5.4 Criterio de salida (Go/No-Go)

- **Go**: 0 diferencias contra Excel en datasets aprobados + 0 pruebas críticas fallidas.
- **No-Go**: cualquier desviación en bruto/validez/percentil o regresión de validaciones.

---

## Nota de implementación práctica

Como siguiente paso, conviene convertir este plan en:
1. nuevos tests de `ScoreService` (unitarios),
2. pruebas funcionales HTTP del controlador,
3. comparador automatizado `PHP vs Excel` sobre fixtures versionados.
