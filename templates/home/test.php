<?php
/**
 * @var array<string, string> $participant
 * @var array<int, array<string, mixed>> $blocks
 * @var array<string, array{mas: string, menos: string}> $savedAnswers
 * @var array<int, string> $serverErrors
 */
$displayName = trim(($participant['nombres'] ?? '') . ' ' . ($participant['apellido_paterno'] ?? ''));
$blocks = $blocks ?? [];
$savedAnswers = $savedAnswers ?? [];
$serverErrors = $serverErrors ?? [];
?>
<section class="card test-card" id="test-app"
         data-blocks='<?= htmlspecialchars((string) json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>'
         data-saved='<?= htmlspecialchars((string) json_encode($savedAnswers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8'); ?>'>
    <h2>Prueba vocacional</h2>
    <p class="subtitle">Responde cada bloque, <?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>.</p>

    <?php if ($serverErrors !== []): ?>
        <div class="alert" role="alert">
            <strong>La validación del servidor detectó errores:</strong>
            <ul>
                <?php foreach ($serverErrors as $error): ?>
                    <li><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="progress-wrap">
        <p id="block-progress" class="progress-label" aria-live="polite"></p>
        <div class="progress-bar" aria-hidden="true">
            <div id="progress-bar-fill" class="progress-bar-fill"></div>
        </div>
    </div>

    <div id="client-alert" class="alert test-alert" hidden></div>

    <div id="block-container"></div>

    <div class="test-actions">
        <button type="button" class="btn-secondary" id="btn-prev">Anterior</button>
        <button type="button" class="btn-primary" id="btn-next">Guardar y continuar</button>
    </div>

    <form method="post" action="/prueba/finalizar" id="finish-form" class="finish-form" hidden>
        <button type="submit" class="btn-primary">Finalizar y enviar respuestas</button>
    </form>
</section>

<script>
(function () {
    const app = document.getElementById('test-app');
    if (!(app instanceof HTMLElement)) {
        return;
    }

    const blocks = JSON.parse(app.dataset.blocks || '[]');
    const answers = JSON.parse(app.dataset.saved || '{}');

    const container = document.getElementById('block-container');
    const alertBox = document.getElementById('client-alert');
    const progressLabel = document.getElementById('block-progress');
    const progressBarFill = document.getElementById('progress-bar-fill');
    const prevButton = document.getElementById('btn-prev');
    const nextButton = document.getElementById('btn-next');
    const finishForm = document.getElementById('finish-form');

    if (!Array.isArray(blocks) || blocks.length === 0 || !(container instanceof HTMLElement)) {
        return;
    }

    let currentIndex = 0;

    function showAlert(message) {
        if (!(alertBox instanceof HTMLElement)) {
            return;
        }
        alertBox.textContent = message;
        alertBox.hidden = false;
    }

    function clearAlert() {
        if (!(alertBox instanceof HTMLElement)) {
            return;
        }
        alertBox.hidden = true;
        alertBox.textContent = '';
    }

    function getCurrentBlock() {
        return blocks[currentIndex] || null;
    }

    function getSelectedValue(name) {
        const checked = container.querySelector('input[name="' + name + '"]:checked');
        return checked instanceof HTMLInputElement ? checked.value : '';
    }

    function validateClientSelection() {
        const mas = getSelectedValue('mas');
        const menos = getSelectedValue('menos');

        if (!mas || !menos) {
            return 'Debes marcar exactamente una opción como "más" y una como "menos".';
        }

        if (mas === menos) {
            return 'No puedes seleccionar la misma actividad como "más" y "menos".';
        }

        return '';
    }

    function renderBlock() {
        clearAlert();
        const block = getCurrentBlock();
        if (!block) {
            return;
        }

        const blockId = block.id || '';
        const selected = answers[blockId] || { mas: '', menos: '' };
        const activities = Array.isArray(block.actividades) ? block.actividades : [];

        const escapeHtml = function (value) {
            return String(value)
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('\"', '&quot;')
                .replaceAll("'", '&#039;');
        };

        const rows = activities.map(function (activity) {
            const activityId = escapeHtml(activity.id || '');
            const text = escapeHtml(activity.texto || '');
            const checkedMas = selected.mas === activityId ? 'checked' : '';
            const checkedMenos = selected.menos === activityId ? 'checked' : '';

            return '<tr>' +
                '<td>' + text + '</td>' +
                '<td class="center"><input type="radio" name="mas" value="' + activityId + '" ' + checkedMas + '></td>' +
                '<td class="center"><input type="radio" name="menos" value="' + activityId + '" ' + checkedMenos + '></td>' +
                '</tr>';
        }).join('');

        container.innerHTML =
            '<div class="block-card" data-block-id="' + blockId + '">' +
                '<h3>Bloque ' + (currentIndex + 1) + '</h3>' +
                '<table class="activities-table">' +
                    '<thead><tr><th>Actividad</th><th>Más (+)</th><th>Menos (-)</th></tr></thead>' +
                    '<tbody>' + rows + '</tbody>' +
                '</table>' +
            '</div>';

        if (progressLabel instanceof HTMLElement) {
            progressLabel.textContent = 'Bloque ' + (currentIndex + 1) + ' de ' + blocks.length;
        }

        if (progressBarFill instanceof HTMLElement) {
            progressBarFill.style.width = (((currentIndex + 1) / blocks.length) * 100) + '%';
        }

        if (prevButton instanceof HTMLButtonElement) {
            prevButton.disabled = currentIndex === 0;
        }

        if (nextButton instanceof HTMLButtonElement) {
            nextButton.textContent = currentIndex === blocks.length - 1 ? 'Guardar último bloque' : 'Guardar y continuar';
        }

        if (finishForm instanceof HTMLFormElement) {
            finishForm.hidden = currentIndex !== blocks.length - 1;
        }
    }

    function persistLocal() {
        window.sessionStorage.setItem('vocacional_answers', JSON.stringify(answers));
    }

    function recoverLocal() {
        const raw = window.sessionStorage.getItem('vocacional_answers');
        if (!raw) {
            return;
        }

        try {
            const parsed = JSON.parse(raw);
            if (parsed && typeof parsed === 'object') {
                Object.assign(answers, parsed);
            }
        } catch (error) {
            // Ignorar datos inválidos en sessionStorage.
        }
    }

    async function saveCurrentBlockOnServer() {
        const block = getCurrentBlock();
        if (!block) {
            return { ok: false, message: 'No existe bloque actual.' };
        }

        const validationMessage = validateClientSelection();
        if (validationMessage) {
            return { ok: false, message: validationMessage };
        }

        const blockId = block.id || '';
        const mas = getSelectedValue('mas');
        const menos = getSelectedValue('menos');

        answers[blockId] = { mas: mas, menos: menos };
        persistLocal();

        const body = new URLSearchParams({ block_id: blockId, mas: mas, menos: menos });
        const response = await fetch('/prueba/bloque/guardar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
            body: body.toString()
        });

        const payload = await response.json();

        if (!response.ok || payload.status !== 'ok') {
            return { ok: false, message: payload.message || 'No se pudo guardar el bloque.' };
        }

        return { ok: true, message: payload.message || '' };
    }

    prevButton?.addEventListener('click', function () {
        clearAlert();
        if (currentIndex > 0) {
            currentIndex -= 1;
            renderBlock();
        }
    });

    nextButton?.addEventListener('click', async function () {
        clearAlert();
        const result = await saveCurrentBlockOnServer();
        if (!result.ok) {
            showAlert(result.message || 'Debes corregir el bloque antes de continuar.');
            return;
        }

        if (currentIndex < blocks.length - 1) {
            currentIndex += 1;
            renderBlock();
            return;
        }

        showAlert('Último bloque guardado. Ahora puedes finalizar y enviar respuestas.');
    });

    recoverLocal();
    renderBlock();
})();
</script>
