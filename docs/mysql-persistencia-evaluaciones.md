# Persistencia MySQL (PDO) para historial de evaluaciones

## 1) Script SQL (diseño normalizado y práctico)

El esquema se encuentra en `database_schema_mysql.sql` y separa:

- `participants`: datos del evaluado.
- `evaluations`: evento de aplicación de prueba (fecha, validez, snapshot JSON útil para auditoría).
- `evaluation_answers`: respuestas por bloque.
- `evaluation_scale_scores`: puntajes brutos por escala.
- `evaluation_percentiles`: percentiles por escala.

## 2) Repositorio PDO

Se implementó el contrato `EvaluationRepository` con dos implementaciones:

- `PdoEvaluationRepository`: persiste en MySQL con prepared statements.
- `NullEvaluationRepository`: no-op cuando la base de datos está deshabilitada.

El factory `PdoConnectionFactory` crea una conexión PDO segura con:

- `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`
- `PDO::ATTR_EMULATE_PREPARES => false`

## 3) Qué se guarda

Cada finalización del test persiste:

- datos del evaluado (`participants`)
- respuestas (`evaluation_answers`)
- puntajes por escala (`evaluation_scale_scores`)
- validez (`evaluations.validity_score`, `evaluations.validity_state`, `evaluations.validity_details_json`)
- percentiles (`evaluation_percentiles`)
- fecha de aplicación (`evaluations.applied_at`)

## 4) Consulta de evaluaciones anteriores

Se agregó endpoint:

- `GET /evaluaciones/anteriores`

Retorna JSON con historial del participante en sesión.

## 5) Ejemplos de inserción y consulta

### Inserción (internamente en `finishTest`)

```php
$appliedAt = gmdate('Y-m-d H:i:s');
$this->evaluationRepository->saveEvaluation(
    $participant,
    $answers,
    is_array($result['resultado'] ?? null) ? $result['resultado'] : [],
    $appliedAt
);
```

### Consulta de historial (endpoint)

```php
$history = $this->evaluationRepository->findPreviousEvaluationsByParticipant($participant, 25);
```

### Prepared statement manual (ejemplo aislado)

```php
$sql = 'SELECT e.id, e.applied_at, e.validity_state
        FROM evaluations e
        INNER JOIN participants p ON p.id = e.participant_id
        WHERE p.first_name = :first_name
        ORDER BY e.applied_at DESC
        LIMIT :limit';

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':first_name', 'ANA');
$stmt->bindValue(':limit', 10, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

## Configuración

En `config/app.php` activar:

```php
'database' => [
    'enabled' => true,
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'test_vocacional',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
],
```
