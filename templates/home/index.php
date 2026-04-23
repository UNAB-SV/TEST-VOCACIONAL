<?php
/**
 * @var array<string, string> $errors
 * @var array<string, string> $formData
 * @var array{id: int, nombre: string, tipo_institucion: int|null}|null $selectedSchool
 * @var array<int, array{id: int, nombre: string}> $countries
 * @var array<int, array{id: int, nombre: string}> $departments
 * @var array<int, array{id: int, nombre: string}> $municipalities
 * @var int $elSalvadorCountryId
 */
$errors = $errors ?? [];
$formData = $formData ?? [];
$selectedSchool = is_array($selectedSchool ?? null) ? $selectedSchool : null;
$countries = is_array($countries ?? null) ? $countries : [];
$departments = is_array($departments ?? null) ? $departments : [];
$municipalities = is_array($municipalities ?? null) ? $municipalities : [];
$elSalvadorCountryId = (int) ($elSalvadorCountryId ?? 15);
$selectedCountryId = (int) ($formData['pais_id'] ?? 0);
$selectedDepartmentId = (int) ($formData['departamento_id'] ?? 0);

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

        <div class="field-grid">
            <div class="field">
                <label for="pais_id">País</label>
                <select id="pais_id" name="pais_id" required>
                    <option value="">Selecciona un país</option>
                    <?php foreach ($countries as $country): ?>
                        <option value="<?= (int) ($country['id'] ?? 0); ?>" <?= $selectedCountryId === (int) ($country['id'] ?? 0) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars((string) ($country['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['pais_id'])): ?><p class="error"><?= htmlspecialchars($errors['pais_id'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
            </div>
            <div class="field">
                <label for="departamento_id">Departamento</label>
                <select id="departamento_id" name="departamento_id">
                    <option value="">Selecciona un departamento</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?= (int) ($department['id'] ?? 0); ?>" <?= $selectedDepartmentId === (int) ($department['id'] ?? 0) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars((string) ($department['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['departamento_id'])): ?><p class="error"><?= htmlspecialchars($errors['departamento_id'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
            </div>
        </div>

        <div class="field">
            <label for="municipio_id">Municipio / Distrito</label>
            <select id="municipio_id" name="municipio_id">
                <option value="">Selecciona un municipio o distrito</option>
                <?php foreach ($municipalities as $municipality): ?>
                    <option value="<?= (int) ($municipality['id'] ?? 0); ?>" <?= ((int) ($formData['municipio_id'] ?? 0) === (int) ($municipality['id'] ?? 0)) ? 'selected' : ''; ?>>
                        <?= htmlspecialchars((string) ($municipality['nombre'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['municipio_id'])): ?><p class="error"><?= htmlspecialchars($errors['municipio_id'], ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
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
        const elSalvadorId = <?= $elSalvadorCountryId; ?>;
        const countrySelect = document.getElementById('pais_id');
        const departmentSelect = document.getElementById('departamento_id');
        const municipalitySelect = document.getElementById('municipio_id');
        if (!(countrySelect instanceof HTMLSelectElement)
            || !(departmentSelect instanceof HTMLSelectElement)
            || !(municipalitySelect instanceof HTMLSelectElement)
        ) {
            return;
        }

        const resetSelect = function (select, placeholder) {
            select.innerHTML = '';
            const option = document.createElement('option');
            option.value = '';
            option.textContent = placeholder;
            select.appendChild(option);
            select.value = '';
        };

        const fillSelect = function (select, items, placeholder) {
            resetSelect(select, placeholder);
            (items || []).forEach(function (item) {
                const option = document.createElement('option');
                option.value = String(item.id || '');
                option.textContent = String(item.nombre || '');
                select.appendChild(option);
            });
        };

        const toggleGeoRequired = function (isRequired) {
            departmentSelect.required = isRequired;
            municipalitySelect.required = isRequired;
        };

        const disableForForeignCountry = function () {
            toggleGeoRequired(false);
            resetSelect(departmentSelect, 'Selecciona un departamento');
            resetSelect(municipalitySelect, 'Selecciona un municipio o distrito');
            departmentSelect.disabled = true;
            municipalitySelect.disabled = true;
        };

        const loadDepartments = function () {
            return fetch('/catalogos/departamentos', { headers: { 'Accept': 'application/json' } })
                .then(function (response) { return response.json(); })
                .then(function (payload) {
                    fillSelect(departmentSelect, payload.items || [], 'Selecciona un departamento');
                    departmentSelect.disabled = false;
                    resetSelect(municipalitySelect, 'Selecciona un municipio o distrito');
                    municipalitySelect.disabled = true;
                });
        };

        const loadMunicipalities = function (departmentId) {
            return fetch('/catalogos/municipios?departamento_id=' + encodeURIComponent(String(departmentId)), { headers: { 'Accept': 'application/json' } })
                .then(function (response) { return response.json(); })
                .then(function (payload) {
                    fillSelect(municipalitySelect, payload.items || [], 'Selecciona un municipio o distrito');
                    municipalitySelect.disabled = false;
                });
        };

        const applyCountryState = function (selectedCountryValue) {
            const countryId = Number(selectedCountryValue || 0);
            if (countryId !== elSalvadorId) {
                disableForForeignCountry();
                return;
            }

            toggleGeoRequired(true);
            loadDepartments().catch(function () {
                departmentSelect.disabled = true;
                municipalitySelect.disabled = true;
            });
        };

        countrySelect.addEventListener('change', function () {
            applyCountryState(countrySelect.value);
        });

        departmentSelect.addEventListener('change', function () {
            const departmentId = Number(departmentSelect.value || 0);
            resetSelect(municipalitySelect, 'Selecciona un municipio o distrito');
            municipalitySelect.disabled = true;
            if (departmentId <= 0) {
                return;
            }
            loadMunicipalities(departmentId).catch(function () {
                municipalitySelect.disabled = true;
            });
        });

        if (Number(countrySelect.value || 0) !== elSalvadorId) {
            disableForForeignCountry();
        } else {
            toggleGeoRequired(true);
            departmentSelect.disabled = false;
            municipalitySelect.disabled = Number(departmentSelect.value || 0) <= 0;
        }

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
