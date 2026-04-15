<?php
/**
 * @var array<string, string> $errors
 * @var array<string, string> $formData
 */
$errors = $errors ?? [];
$formData = $formData ?? [];

$sexOptions = [
    'F' => 'Femenino',
    'M' => 'Masculino',
    'X' => 'Prefiero no especificar',
];
?>
<section class="card">
    <h2>Datos iniciales del participante</h2>
    <p class="subtitle">Completa la información para comenzar el test vocacional.</p>
    <p class="session-note no-print"><a href="/admin/evaluaciones">Ir al módulo administrativo</a></p>

    <?php if ($errors !== []): ?>
        <div class="alert" role="alert">
            <strong>Corrige los campos marcados para continuar.</strong>
        </div>
    <?php endif; ?>

    <form method="post" action="/" id="start-form" novalidate>
        <div class="field">
            <label for="nombres">Nombres</label>
            <input type="text" id="nombres" name="nombres" maxlength="80" required
                   pattern="[A-Za-zÁÉÍÓÚáéíóúÑñ ]+"
                   value="<?= htmlspecialchars($formData['nombres'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <small class="hint">Escribe todos tus nombres.</small>
            <?php if (isset($errors['nombres'])): ?><p class="error"><?= htmlspecialchars($errors['nombres'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        </div>

        <div class="field-grid">
            <div class="field">
                <label for="apellido_paterno">Apellido paterno</label>
                <input type="text" id="apellido_paterno" name="apellido_paterno" maxlength="60" required
                       pattern="[A-Za-zÁÉÍÓÚáéíóúÑñ ]+"
                       value="<?= htmlspecialchars($formData['apellido_paterno'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <?php if (isset($errors['apellido_paterno'])): ?><p class="error"><?= htmlspecialchars($errors['apellido_paterno'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
            </div>
            <div class="field">
                <label for="apellido_materno">Apellido materno</label>
                <input type="text" id="apellido_materno" name="apellido_materno" maxlength="60" required
                       pattern="[A-Za-zÁÉÍÓÚáéíóúÑñ ]+"
                       value="<?= htmlspecialchars($formData['apellido_materno'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <?php if (isset($errors['apellido_materno'])): ?><p class="error"><?= htmlspecialchars($errors['apellido_materno'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
            </div>
        </div>

        <div class="field-grid">
            <div class="field">
                <label for="edad">Edad</label>
                <input type="number" id="edad" name="edad" min="10" max="99" required
                       value="<?= htmlspecialchars($formData['edad'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <?php if (isset($errors['edad'])): ?><p class="error"><?= htmlspecialchars($errors['edad'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
            </div>

            <div class="field">
                <label for="sexo">Sexo</label>
                <select id="sexo" name="sexo" required>
                    <option value="">Selecciona una opción</option>
                    <?php foreach ($sexOptions as $value => $label): ?>
                        <option value="<?= $value; ?>" <?= (($formData['sexo'] ?? '') === $value) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['sexo'])): ?><p class="error"><?= htmlspecialchars($errors['sexo'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
            </div>
        </div>

        <div class="field">
            <label for="grupo">Grupo</label>
            <input type="text" id="grupo" name="grupo" maxlength="20" required pattern="[A-Za-z0-9\- ]+"
                   value="<?= htmlspecialchars($formData['grupo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <small class="hint">Ejemplo: 6A, 5B-M, 3 C.</small>
            <?php if (isset($errors['grupo'])): ?><p class="error"><?= htmlspecialchars($errors['grupo'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        </div>

        <button class="btn-primary" type="submit">Continuar a instrucciones</button>
    </form>
</section>

<script>
    document.getElementById('start-form')?.addEventListener('submit', function (event) {
        const form = event.currentTarget;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (!form.checkValidity()) {
            event.preventDefault();
            const firstInvalid = form.querySelector(':invalid');
            if (firstInvalid instanceof HTMLElement) {
                firstInvalid.focus();
            }
            alert('Por favor completa correctamente los campos requeridos.');
        }
    });
</script>
