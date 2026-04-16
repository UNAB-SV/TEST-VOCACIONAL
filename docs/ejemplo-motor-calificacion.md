# Ejemplo de cálculo del nuevo modelo de scoring

El motor ahora califica con la tupla:

`(bloque, índice_en_bloque, respuesta)`

Sin usar claves por texto de actividad.

## Ejemplo puntual (fuente: `test.xls` hoja `PRUEBA`)

Supongamos:

- Bloque: `B002`
- Actividad elegida como `MAS`: `A0004`
- `A0004` tiene `indice_en_bloque = 1`

El motor consulta:

- `scoring_rules.matriz_por_bloque.B002.1.mas.scales`

Valor configurado:

```json
{
  "servicio_social": 1
}
```

### Resultado de puntuación

- Se suma `+1` a la escala `servicio_social`.
- No hay inversión global por `MAS/MENOS`.
- Si la matriz trae más de una escala en esa celda, se suma `+1` a cada una.

## Ejemplo MENOS del mismo bloque

Si en el mismo bloque `B002` el usuario marca `MENOS = A0005`:

- `A0005` tiene `indice_en_bloque = 2`
- Ruta consultada: `scoring_rules.matriz_por_bloque.B002.2.menos.scales`
- Valor:

```json
{
  "persuasivo": 1,
  "servicio_social": 1
}
```

### Resultado

- `persuasivo += 1`
- `servicio_social += 1`

## Fórmula aplicada

`puntaje_bruto_escala = suma(pesos de la matriz para cada respuesta seleccionada)`

Donde cada marcador en el Excel vale `1`.
