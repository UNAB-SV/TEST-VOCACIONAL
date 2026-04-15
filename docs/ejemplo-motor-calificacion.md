# Ejemplo de entrada y salida del `ScoreService`

## Entrada de ejemplo

```php
$answers = [
  'B001' => ['mas' => 'A0001', 'menos' => 'A0002'],
  'B002' => ['mas' => 'A0004', 'menos' => 'A0005'],
  'B003' => ['mas' => 'A0007', 'menos' => 'A0008'],
];

$result = $scoreService->calculate(
  $answers,
  $questionsDefinition,
  $scoringRules,
  $percentilesBySex,
  'M',
  $validityRules
);
```

## Salida de ejemplo (resumen)

```json
{
  "puntajes_brutos": {
    "aire_libre": 1,
    "mecanico": 1,
    "calculo": 1,
    "cientifico": -1,
    "persuasivo": 0,
    "artistico": 0,
    "literario": -1,
    "musical": -1,
    "servicio_social": 0,
    "oficina": 1,
    "validez": 0
  },
  "percentiles": {
    "aire_libre": 50,
    "mecanico": 55,
    "calculo": 60,
    "cientifico": null
  },
  "validez_puntaje": 0,
  "validez_estado": "valido",
  "escalas_ordenadas_de_mayor_a_menor": [
    {"escala": "aire_libre", "puntaje_bruto": 1},
    {"escala": "calculo", "puntaje_bruto": 1},
    {"escala": "mecanico", "puntaje_bruto": 1}
  ]
}
```

> Nota: la lógica de puntuación, validez y percentiles se toma de los JSON de configuración, no está codificada de forma rígida en el servicio.
