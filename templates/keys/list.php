<div class="breadcrumb">
    <a href="/repositories/detail?repo=<?= htmlspecialchars(urlencode($repo['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">&larr; <?= htmlspecialchars(__('maint.back_repo'), ENT_QUOTES, 'UTF-8') ?></a>
</div>

<h2><?= htmlspecialchars(__('keys.list'), ENT_QUOTES, 'UTF-8') ?>: <?= htmlspecialchars($repo['name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h2>

<p class="keys-hint"><?= htmlspecialchars(__('keys.hint'), ENT_QUOTES, 'UTF-8') ?></p>

<?php if (!empty($credentialsMismatch)): ?>
    <p class="flash flash-error"><?= htmlspecialchars(__('keys.credentials_mismatch'), ENT_QUOTES, 'UTF-8') ?></p>
<?php elseif (empty($hasPassword) && !empty($keys)): ?>
    <p class="muted"><?= htmlspecialchars(__('keys.no_password'), ENT_QUOTES, 'UTF-8') ?></p>
<?php endif ?>

<?php if (!empty($keys)): ?>
<table class="key-table" id="keys-table">
    <thead>
        <tr>
            <th><?= htmlspecialchars(__('keys.id'), ENT_QUOTES, 'UTF-8') ?></th>
            <th><?= htmlspecialchars(__('keys.user'), ENT_QUOTES, 'UTF-8') ?></th>
            <th><?= htmlspecialchars(__('keys.created'), ENT_QUOTES, 'UTF-8') ?></th>
            <th><?= htmlspecialchars(__('keys.role'), ENT_QUOTES, 'UTF-8') ?></th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($keys as $key): ?>
        <?php
        $keyId = (string) ($key['id'] ?? '');
        $shortId = substr($keyId, 0, 8);
        $isWorking = !empty($workingKeyId) && $workingKeyId === $keyId;
        ?>
        <tr data-key-id="<?= htmlspecialchars($keyId, ENT_QUOTES, 'UTF-8') ?>">
            <td><code><?= htmlspecialchars($shortId, ENT_QUOTES, 'UTF-8') ?></code></td>
            <td><?= htmlspecialchars($key['userName'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($key['created'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
            <td>
                <?php if ($isWorking): ?>
                    <span class="key-badge key-badge-source"><?= htmlspecialchars(__('keys.role_source'), ENT_QUOTES, 'UTF-8') ?></span>
                <?php else: ?>
                    <span class="key-badge"><?= htmlspecialchars(__('keys.role_extra'), ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif ?>
            </td>
            <td>
                <?php if (!empty($canWrite)): ?>
                    <button type="button" class="btn-danger-sm key-remove-id"
                            data-key-id="<?= htmlspecialchars($keyId, ENT_QUOTES, 'UTF-8') ?>"
                            data-working="<?= $isWorking ? '1' : '0' ?>"><?= htmlspecialchars(__('keys.remove'), ENT_QUOTES, 'UTF-8') ?></button>
                <?php endif ?>
            </td>
        </tr>
        <?php endforeach ?>
    </tbody>
</table>
<?php else: ?>
    <p><?= htmlspecialchars(__('keys.none'), ENT_QUOTES, 'UTF-8') ?></p>
<?php endif ?>

<?php if (!empty($canWrite)): ?>
<div class="maintenance-section">
    <h3><?= htmlspecialchars(__('keys.verify'), ENT_QUOTES, 'UTF-8') ?></h3>
    <div class="form-group">
        <label><?= htmlspecialchars(__('keys.password'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="password" id="verify-password">
    </div>
    <button type="button" class="btn-primary" id="keys-verify-btn"><?= htmlspecialchars(__('keys.verify_button'), ENT_QUOTES, 'UTF-8') ?></button>
    <div id="keys-verify-result" class="keys-result"></div>
</div>

<div class="maintenance-section">
    <h3><?= htmlspecialchars(__('keys.add_key'), ENT_QUOTES, 'UTF-8') ?></h3>
    <div class="form-group">
        <label><?= htmlspecialchars(__('keys.new_password'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="password" id="keys-add-password">
    </div>
    <button type="button" class="btn-primary" id="keys-add-btn"><?= htmlspecialchars(__('keys.add_key'), ENT_QUOTES, 'UTF-8') ?></button>
    <div id="keys-add-result" class="keys-result"></div>
</div>

<div class="maintenance-section">
    <h3><?= htmlspecialchars(__('keys.remove_by_password'), ENT_QUOTES, 'UTF-8') ?></h3>
    <div class="form-group">
        <label><?= htmlspecialchars(__('keys.password'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="password" id="keys-remove-password">
    </div>
    <button type="button" class="btn-primary" id="keys-remove-btn"><?= htmlspecialchars(__('keys.remove'), ENT_QUOTES, 'UTF-8') ?></button>
    <div id="keys-remove-result" class="keys-result"></div>
</div>

<div class="maintenance-section">
    <h3><?= htmlspecialchars(__('keys.change_pass'), ENT_QUOTES, 'UTF-8') ?></h3>
    <div class="form-group">
        <label><?= htmlspecialchars(__('keys.old_password'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="password" id="keys-passwd-old">
    </div>
    <div class="form-group">
        <label><?= htmlspecialchars(__('keys.new_password'), ENT_QUOTES, 'UTF-8') ?></label>
        <input type="password" id="keys-passwd-new">
    </div>
    <?php if (!empty($canEdit)): ?>
    <div class="form-group">
        <label>
            <input type="checkbox" id="keys-passwd-update" checked>
            <?= htmlspecialchars(__('keys.update_credentials'), ENT_QUOTES, 'UTF-8') ?>
        </label>
    </div>
    <?php endif ?>
    <button type="button" class="btn-primary" id="keys-passwd-btn"><?= htmlspecialchars(__('keys.change_pass'), ENT_QUOTES, 'UTF-8') ?></button>
    <div id="keys-passwd-result" class="keys-result"></div>
</div>

<script>
(function () {
    var repoId = <?= json_encode($repo['id'] ?? '') ?>;
    var body = document.body;

    function csrf() { return body ? body.dataset.csrf : ''; }

    function post(url, params) {
        var data = new URLSearchParams();
        data.append('repo_id', repoId);
        data.append('_csrf_token', csrf());
        for (var k in params) { data.append(k, params[k]); }
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: data.toString()
        })
        .then(function (r) { return r.json(); })
        .then(function (json) {
            if (json && json._csrf_token && body) { body.dataset.csrf = json._csrf_token; }
            return json;
        });
    }

    function show(id, json, extra) {
        var el = document.getElementById(id);
        if (!el) { return; }
        if (json.ok) {
            el.className = 'keys-result keys-result-ok';
            el.textContent = (json.message || <?= json_encode(__('keys.ok')) ?>) + (extra || '');
        } else {
            el.className = 'keys-result keys-result-error';
            el.textContent = json.error || <?= json_encode(__('keys.add_error')) ?>;
        }
    }

    var verifyBtn = document.getElementById('keys-verify-btn');
    if (verifyBtn) {
        verifyBtn.addEventListener('click', function () {
            var pw = document.getElementById('verify-password').value;
            post('/keys/verify', { password: pw }).then(function (json) {
                // Подсветка строки по ответу AJAX (на бейдж не влияет).
                document.querySelectorAll('#keys-table tbody tr').forEach(function (tr) { tr.classList.remove('key-row-match'); });
                if (json.ok && json.key_id) {
                    var row = document.querySelector('#keys-table tr[data-key-id="' + json.key_id + '"]');
                    if (row) { row.classList.add('key-row-match'); }
                }
                show('keys-verify-result', json, json.ok ? ' (' + (json.short_id || '') + ')' : '');
            });
        });
    }

    var addBtn = document.getElementById('keys-add-btn');
    if (addBtn) {
        addBtn.addEventListener('click', function () {
            var pw = document.getElementById('keys-add-password').value;
            post('/keys/add', { new_password: pw }).then(function (json) {
                show('keys-add-result', json, json.ok ? ' (' + (json.short_id || '') + ')' : '');
                if (json.ok || json.error_code === 'duplicate') { setTimeout(function () { window.location.reload(); }, 1500); }
            });
        });
    }

    var removeBtn = document.getElementById('keys-remove-btn');
    if (removeBtn) {
        removeBtn.addEventListener('click', function () {
            var pw = document.getElementById('keys-remove-password').value;
            post('/keys/remove', { password: pw }).then(function (json) {
                show('keys-remove-result', json);
                if (json.ok) { setTimeout(function () { window.location.reload(); }, 1000); }
            });
        });
    }

    document.querySelectorAll('.key-remove-id').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!window.confirm(<?= json_encode(__('keys.confirm_remove')) ?>)) { return; }
            post('/keys/remove', { key_id: btn.dataset.keyId }).then(function (json) {
                if (json.ok) { window.location.reload(); }
                else { window.alert(json.error || <?= json_encode(__('keys.add_error')) ?>); }
            });
        });
    });

    var passwdBtn = document.getElementById('keys-passwd-btn');
    if (passwdBtn) {
        passwdBtn.addEventListener('click', function () {
            var oldPw = document.getElementById('keys-passwd-old').value;
            var newPw = document.getElementById('keys-passwd-new').value;
            var upd = document.getElementById('keys-passwd-update');
            var params = { old_password: oldPw, new_password: newPw };
            if (upd && upd.checked) { params.update_credentials = '1'; }
            post('/keys/passwd', params).then(function (json) {
                if (json.ok && json.credentials_updated) {
                    show('keys-passwd-result', { ok: true, message: <?= json_encode(__('keys.credentials_updated')) ?> });
                } else if (json.ok && json.is_working_key === false) {
                    show('keys-passwd-result', { ok: true, message: <?= json_encode(__('keys.credentials_not_touched')) ?> });
                } else if (json.ok) {
                    show('keys-passwd-result', json);
                } else {
                    show('keys-passwd-result', json);
                }
                if (json.new_password) {
                    var el = document.getElementById('keys-passwd-result');
                    el.className = 'keys-result keys-result-error';
                    el.textContent += ' ' + <?= json_encode(__('keys.save_new_password')) ?>.replace('{password}', json.new_password);
                }
            });
        });
    }
})();
</script>
<?php endif ?>
