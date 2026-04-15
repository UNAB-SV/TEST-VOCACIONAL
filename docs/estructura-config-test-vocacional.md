# Estructura de configuración desacoplada — Test vocacional

Esta propuesta separa completamente los datos del test de la lógica de aplicación, usando archivos JSON legibles y un `catalog.php` mínimo para ubicar rutas.

## 1) Estructura de archivos

```text
config/test-vocacional/
├─ catalog.php
├─ test.json
├─ scales.json
├─ questions_blocks.json
├─ scoring_rules.json
├─ validity_rules.json
├─ excel_mapping.json
└─ percentiles/
   ├─ male.json
   └─ female.json
```

## 2) Decisiones de diseño

1. **Un archivo por responsabilidad**
   - Facilita mantenimiento y versionado.
   - Permite cambiar percentiles, validez o preguntas sin tocar código.

2. **JSON como formato principal**
   - Es portable y fácil de importar/exportar desde Excel.
   - Se puede validar con esquemas en una etapa posterior.

3. **`catalog.php` como punto de entrada simple**
   - Evita hardcodear rutas en servicios/controladores.
   - Sigue siendo framework-agnóstico.

4. **Bloques explícitos de 3 actividades**
   - Representa tal cual el modelo psicométrico del test (1 más / 1 menos por bloque).

5. **Claves de puntuación por actividad y tipo de respuesta**
   - Soporta mapeos simples (1 escala) y avanzados (multiescala).

6. **Tablas de percentiles separadas por sexo**
   - Permite aplicar baremos distintos sin lógica condicional compleja en los datos.

7. **`excel_mapping.json` listo para carga masiva**
   - Deja preparado el contrato para importar todo el contenido del Excel.

## 3) Ejemplo real de bloques

Tomado de `questions_blocks.json`:

- **Bloque B001**: A0001 (mecánico), A0002 (literario), A0003 (persuasivo).
- **Bloque B002**: A0004 (cálculo + oficina), A0005 (científico), A0006 (servicio social).
- **Bloque B003**: A0007 (aire libre), A0008 (musical), A0009 (artístico).

Cada actividad define:
- `id`
- `texto`
- `bloque`
- `claves.mas` y `claves.menos` con pesos por escala.

## 4) Regla de puntuación (más / menos)

Configurada en `scoring_rules.json`:

- `mas`: multiplicador `+1`.
- `menos`: multiplicador `-1`.
- Fórmula general:
  - `puntaje_bruto_escala = suma(claves_mas * 1) + suma(claves_menos * -1)`

Además, el archivo soporta **overrides** para casos especiales (por ejemplo, ajustar cómo se acumula `validez`).

## 5) Ejemplo de tabla de percentiles

`percentiles/male.json` y `percentiles/female.json` guardan curvas por escala:

```json
{
  "sexo": "M",
  "percentiles": {
    "aire_libre": [
      { "bruto": -4, "percentil": 10 },
      { "bruto": 0, "percentil": 50 },
      { "bruto": 4, "percentil": 90 }
    ]
  }
}
```

Así el servicio solo debe:
1. elegir archivo por sexo,
2. buscar la escala,
3. convertir bruto a percentil (exacto o interpolado).

## 6) Reglas de validez

`validity_rules.json` separa:
- Reglas base de completitud por bloque.
- Métricas (omisiones, colisión más/menos, índice de validez).
- Decisión final (`valido`, `dudoso`, `invalido`) por condiciones declarativas.

## 7) Extensibilidad

Para cargar el Excel completo después:
1. Completar filas reales en hojas indicadas.
2. Mantener `excel_mapping.json` como contrato de importación.
3. Construir un importador que lea mapping + hojas y regenere JSON.
4. Versionar carpeta (`2026.04`, `2026.05`, etc.) cuando cambien baremos o ítems.

Con esto se logra una estructura **simple, robusta y desacoplada**, sin introducir frameworks ni dependencias innecesarias.
