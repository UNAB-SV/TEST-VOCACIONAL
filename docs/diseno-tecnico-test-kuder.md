# Diseño técnico — Plataforma web para Test Vocacional tipo Kuder (PHP 8.3 puro)

## 1) Propuesta de arquitectura de carpetas

```text
/
├─ public/                        # Único document root expuesto por web server
│  ├─ index.php                   # Front controller
│  ├─ assets/
│  │  ├─ css/
│  │  ├─ js/
│  │  └─ img/
│  └─ .htaccess                   # (si Apache) reescritura a index.php
│
├─ app/
│  ├─ Controllers/                # Orquestación HTTP (sin lógica de negocio pesada)
│  │  ├─ TestController.php
│  │  └─ ReportController.php
│  ├─ Services/                   # Casos de uso y lógica de negocio
│  │  ├─ TestSessionService.php
│  │  ├─ ValidationService.php
│  │  ├─ ScoringService.php
│  │  ├─ ValidityService.php
│  │  ├─ PercentileService.php
│  │  └─ ReportService.php
│  ├─ Domain/                     # Entidades y value objects del dominio
│  │  ├─ Question.php
│  │  ├─ Block.php
│  │  ├─ Answer.php
│  │  ├─ Scale.php
│  │  └─ TestResult.php
│  ├─ Repositories/               # Acceso a datos (PDO)
│  │  ├─ CandidateRepository.php
│  │  ├─ AttemptRepository.php
│  │  ├─ AnswerRepository.php
│  │  ├─ CatalogRepository.php
│  │  └─ PercentileRepository.php
│  ├─ Http/
│  │  ├─ Request.php
│  │  ├─ Response.php
│  │  └─ Router.php
│  ├─ View/                       # Vistas PHP con plantillas
│  │  ├─ layouts/
│  │  ├─ test/
│  │  └─ report/
│  ├─ Support/
│  │  ├─ JsonLoader.php
│  │  ├─ Logger.php
│  │  ├─ Csrf.php
│  │  └─ Validator.php
│  └─ Config/
│     ├─ app.php
│     ├─ database.php
│     └─ test_kuder.php           # fallback en arrays si no hay JSON
│
├─ config/
│  └─ test-kuder/
│     ├─ questions.json           # Banco de preguntas y bloques
│     ├─ scales.json              # Definición de escalas
│     ├─ scoring_rules.json       # Reglas de puntuación
│     ├─ validity_rules.json      # Reglas de validez
│     └─ percentiles/
│        ├─ male.json
│        └─ female.json
│
├─ database/
│  ├─ migrations/
│  ├─ seeds/
│  └─ schema.sql
│
├─ storage/
│  ├─ logs/
│  ├─ cache/
│  └─ exports/
│
├─ tests/                         # pruebas unitarias e integración (PHPUnit)
├─ bootstrap/
│  ├─ app.php                     # contenedor simple / wiring
│  └─ env.php
├─ vendor/
└─ composer.json
```

### Principios aplicados
- **Front Controller + Router propio** para centralizar seguridad y flujo.
- **Controladores delgados**: delegan a servicios.
- **Servicios de dominio**: cálculo, validez y percentiles desacoplados de HTTP.
- **Configuración externa** en JSON (y arrays PHP como respaldo) para evitar hardcode.
- **Repositorio + PDO** para persistencia y trazabilidad.

---

## 2) Módulos principales

1. **Módulo de Catálogo del Test**
   - Carga preguntas, bloques, escalas y reglas desde JSON.
   - Versiona catálogos para reproducibilidad histórica.

2. **Módulo de Aplicación del Test (Runtime)**
   - Presenta bloques de 3 opciones.
   - Gestiona progreso, sesión, guardado parcial y reanudación.

3. **Módulo de Validación de Respuestas**
   - Regla típica Kuder por bloque: 1 “más” y 1 “menos” (si aplica al instrumento).
   - Detecta omisiones, duplicidades y patrones inválidos.

4. **Módulo de Puntuación por Escalas**
   - Convierte respuestas en puntajes brutos por escala.
   - Aplica reglas parametrizadas (sumas, inversiones, pesos).

5. **Módulo de Validez**
   - Índices de consistencia/atipicidad según reglas configuradas.
   - Etiqueta intento como válido, dudoso o inválido.

6. **Módulo de Conversión a Percentiles**
   - Usa sexo del evaluado para elegir tabla normativa.
   - Mapea puntaje bruto → percentil con interpolación opcional.

7. **Módulo de Reporte**
   - Resumen global, detalle por escala, percentiles e interpretación.
   - Exportable a PDF/HTML imprimible.

8. **Módulo de Auditoría y Seguridad**
   - Log de eventos clave (inicio, envío, recalculo, exportación).
   - CSRF, validación server-side, sanitización de salida.

---

## 3) Flujo funcional completo del usuario

1. **Ingreso**
   - El evaluado accede a una URL segura del test.
   - Completa datos iniciales mínimos (identificador, sexo, edad, consentimiento).

2. **Inicio de intento**
   - Se crea `attempt` con estado `in_progress`.
   - Se fija versión del catálogo (preguntas + reglas + percentiles).

3. **Resolución del test**
   - UI muestra bloque por bloque (3 opciones).
   - JavaScript valida en cliente reglas básicas de completitud.
   - Backend vuelve a validar al guardar cada bloque.

4. **Envío final**
   - Se verifica que no haya bloques incompletos o inválidos.
   - Se cierra intento (`submitted_at`).

5. **Cálculo**
   - `ScoringService` calcula puntajes brutos por escala.
   - `ValidityService` calcula índices de validez.
   - `PercentileService` convierte bruto a percentil por sexo.

6. **Generación de reporte**
   - `ReportService` arma estructura final de resultados.
   - Se renderiza reporte claro (gráficas opcionales con JS).

7. **Consulta y exportación**
   - Psicólogo/administrador consulta historial.
   - Exporta PDF o imprime versión amigable.

---

## 4) Propuesta de modelo de datos (MySQL)

### Tablas núcleo

- `candidates`
  - `id`, `external_code`, `full_name`, `sex` (`M|F|X`), `birth_date`, `created_at`

- `test_catalog_versions`
  - `id`, `code`, `description`, `is_active`, `created_at`

- `attempts`
  - `id`, `candidate_id`, `catalog_version_id`, `status` (`in_progress|submitted|scored|invalid`),
  - `started_at`, `submitted_at`, `scored_at`, `validity_status`, `created_at`

- `attempt_answers`
  - `id`, `attempt_id`, `block_id`, `question_id`, `choice_type` (`MOST|LEAST|SINGLE`), `answer_value`, `created_at`

- `attempt_scale_scores`
  - `id`, `attempt_id`, `scale_code`, `raw_score`, `percentile`, `interpretation_level`, `created_at`

- `attempt_validity_metrics`
  - `id`, `attempt_id`, `metric_code`, `metric_value`, `threshold_low`, `threshold_high`, `status`, `created_at`

- `audit_logs`
  - `id`, `actor_type`, `actor_id`, `event_type`, `entity_type`, `entity_id`, `payload_json`, `created_at`

### Tablas opcionales (si se desea 100% en BD)
- `catalog_questions`, `catalog_blocks`, `catalog_scales`, `catalog_scoring_rules`, `catalog_percentiles`.
- Alternativa híbrida recomendada: **catálogo en JSON versionado + resultados en MySQL**.

---

## 5) Estrategia de representación

### a) Preguntas
- JSON con identificador estable y texto:

```json
{
  "question_id": "Q001",
  "text": "Me interesa reparar equipos electrónicos",
  "active": true
}
```

### b) Bloques de 3 opciones
- Cada bloque referencia 3 preguntas:

```json
{
  "block_id": "B001",
  "items": ["Q001", "Q014", "Q089"],
  "response_mode": "MOST_LEAST"
}
```

### c) Respuestas
- Guardar **granularmente por pregunta y tipo de elección**.
- Ejemplo por bloque MOST/LEAST:
  - (`block_id=B001`, `question_id=Q014`, `choice_type=MOST`)
  - (`block_id=B001`, `question_id=Q089`, `choice_type=LEAST`)

### d) Escalas
- Definir escalas por código y metadatos:

```json
{
  "scale_code": "TEC",
  "name": "Interés Tecnológico",
  "description": "Preferencia por tareas técnicas y de ingeniería"
}
```

### e) Reglas de puntuación
- Regla declarativa por escala y tipo de respuesta:

```json
{
  "scale_code": "TEC",
  "rules": [
    {"question_id": "Q001", "choice_type": "MOST", "points": 1},
    {"question_id": "Q001", "choice_type": "LEAST", "points": -1}
  ]
}
```

### f) Tablas de percentiles por sexo
- Estructura separada por sexo para resolver rápido:

```json
{
  "sex": "M",
  "scale_code": "TEC",
  "norms": [
    {"raw_min": 0, "raw_max": 3, "percentile": 10},
    {"raw_min": 4, "raw_max": 6, "percentile": 25},
    {"raw_min": 7, "raw_max": 9, "percentile": 50}
  ]
}
```

> Recomendación: incluir `catalog_version`, checksum y fecha de vigencia en cada archivo de configuración.

---

## 6) Riesgos técnicos y mitigación

1. **Riesgo: Reglas mal transcritas desde Excel**
   - Mitigar con doble carga (4 ojos), pruebas unitarias por escala y dataset de contraste.

2. **Riesgo: Inconsistencia entre frontend y backend**
   - Mitigar haciendo la validación **autoritativa en backend**; frontend solo UX.

3. **Riesgo: Hardcode creciente en controladores**
   - Mitigar centralizando reglas en JSON + servicios de dominio.

4. **Riesgo: Cambios de normativas percentilares**
   - Mitigar con versionado de catálogos y trazabilidad por intento.

5. **Riesgo: Seguridad de datos sensibles**
   - Mitigar con HTTPS, prepared statements (PDO), escape de salida, control de sesión, logs y permisos por rol.

6. **Riesgo: Dificultad de auditoría clínica**
   - Mitigar guardando respuestas crudas, versión de regla usada y snapshot de resultados.

7. **Riesgo: Errores silenciosos en cálculo**
   - Mitigar con pruebas automatizadas, fixtures conocidos y validaciones de rango.

---

## 7) Recomendación final (entre “app con MySQL”)

### Recomendación: **Sí, app con base de datos MySQL**, con enfoque híbrido

- **MySQL** para:
  - candidatos,
  - intentos,
  - respuestas,
  - resultados,
  - auditoría.

- **JSON versionado en repositorio** para:
  - preguntas,
  - bloques,
  - escalas,
  - reglas,
  - percentiles.

### ¿Por qué híbrido?
- Reduce hardcode y facilita mantenimiento sin tocar código para ajustes psicométricos.
- Permite trazabilidad histórica (qué versión evaluó a cada persona).
- Acelera iteración al migrar desde Excel.

### Cuándo pasar todo a MySQL
- Cuando necesites editor administrativo de catálogos en backoffice,
- multi-sede con gobernanza central,
- o cambios muy frecuentes hechos por usuarios no técnicos.

---

## Siguiente paso sugerido (fase 0)

1. Congelar versión base del Excel.
2. Definir diccionario de datos y mapeo Excel → JSON.
3. Construir motor de cálculo (CLI + pruebas) antes de UI.
4. Validar 20–30 casos reales contra Excel.
5. Luego implementar flujo web completo.
