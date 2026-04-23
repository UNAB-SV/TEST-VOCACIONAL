<?php
/**
 * @var array<string, string> $errors
 * @var array<string, string> $formData
 * @var array{id: int, nombre: string, tipo_institucion: int|null}|null $selectedSchool
 */
$errors = $errors ?? [];
$formData = $formData ?? [];
$selectedSchool = is_array($selectedSchool ?? null) ? $selectedSchool : null;

$sexOptions = [
    'F' => 'Femenino',
    'M' => 'Masculino',
    'X' => 'Prefiero no especificar',
];
?>
<section class="card">
    <h2>Datos iniciales del participante</h2>
    <p class="subtitle">Completa la información para comenzar el test vocacional.</p>
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
            <label for="colegio_search">Institución de procedencia</label>
            <input type="hidden" id="colegio_id" name="colegio_id" required value="<?= htmlspecialchars((string) ($formData['colegio_id'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
            <input type="text" id="colegio_search" autocomplete="off" placeholder="Escribe para buscar tu institución"
                   value="<?= htmlspecialchars((string) (($selectedSchool['nombre'] ?? '') !== '' ? $selectedSchool['nombre'] : ($formData['colegio_nombre'] ?? '')), ENT_QUOTES, 'UTF-8'); ?>">
            <small class="hint">Selecciona una institución existente de la lista.</small>
            <ul id="colegio_results" class="autocomplete-list" hidden></ul>
            <?php if (isset($errors['colegio_id'])): ?><p class="error"><?= htmlspecialchars($errors['colegio_id'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
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

    (function () {
        const searchInput = document.getElementById('colegio_search');
        const hiddenIdInput = document.getElementById('colegio_id');
        const resultsNode = document.getElementById('colegio_results');
        if (!(searchInput instanceof HTMLInputElement) || !(hiddenIdInput instanceof HTMLInputElement) || !(resultsNode instanceof HTMLUListElement)) {
            return;
        }

        let lastTerm = '';

        const typeLabel = function (value) {
            if (Number(value) === 1) return 'Pública';
            if (Number(value) === 2) return 'Privada';
            return '';
        };

        const renderResults = function (items) {
            resultsNode.innerHTML = '';
            if (!Array.isArray(items) || items.length === 0) {
                resultsNode.hidden = true;
                return;
            }

            items.forEach(function (item) {
                const option = document.createElement('li');
                option.className = 'autocomplete-item';
                option.tabIndex = 0;
                option.setAttribute('role', 'button');

                const tag = typeLabel(item.tipo_institucion);
                option.textContent = tag ? item.nombre + ' (' + tag + ')' : item.nombre;
                option.addEventListener('click', function () {
                    hiddenIdInput.value = String(item.id || '');
                    searchInput.value = String(item.nombre || '');
                    resultsNode.hidden = true;
                });
                resultsNode.appendChild(option);
            });

            resultsNode.hidden = false;
        };

        searchInput.addEventListener('input', function () {
            const term = searchInput.value.trim();
            hiddenIdInput.value = '';
            if (term.length < 2) {
                resultsNode.hidden = true;
                resultsNode.innerHTML = '';
                return;
            }

            lastTerm = term;
            fetch('/colegios/buscar?q=' + encodeURIComponent(term), {
                headers: { 'Accept': 'application/json' }
            })
                .then(function (response) { return response.json(); })
                .then(function (payload) {
                    if (term !== lastTerm) {
                        return;
                    }
                    renderResults(payload.items || []);
                })
                .catch(function () {
                    resultsNode.hidden = true;
                });
        });

        document.addEventListener('click', function (event) {
            if (!(event.target instanceof Node)) return;
            if (!resultsNode.contains(event.target) && event.target !== searchInput) {
                resultsNode.hidden = true;
            }
        });
    })();
</script>
