<h2><?= htmlspecialchars(__('users.title'), ENT_QUOTES, 'UTF-8') ?></h2>

<p><a href="/users/add" class="btn-snapshots"><?= htmlspecialchars(__('users.add'), ENT_QUOTES, 'UTF-8') ?></a></p>

<h3><?= htmlspecialchars(__('users.php_users'), ENT_QUOTES, 'UTF-8') ?></h3>
<table class="snapshot-table">
    <thead>
        <tr>
            <th><?= htmlspecialchars(__('auth.username'), ENT_QUOTES, 'UTF-8') ?></th>
            <th><?= htmlspecialchars(__('users.manage_users'), ENT_QUOTES, 'UTF-8') ?></th>
            <th><?= htmlspecialchars(__('users.manage_processes'), ENT_QUOTES, 'UTF-8') ?></th>
            <th><?= htmlspecialchars(__('users.source'), ENT_QUOTES, 'UTF-8') ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($phpUsers as $name => $data): ?>
            <tr>
                <td><code><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></code></td>
                <td><?= !empty($data['can_manage_users']) ? '&#10003;' : '—' ?></td>
                <td><?= !empty($data['can_manage_processes']) ? '&#10003;' : '—' ?></td>
                <td>PHP</td>
            </tr>
        <?php endforeach ?>
    </tbody>
</table>

<h3><?= htmlspecialchars(__('users.yaml_users'), ENT_QUOTES, 'UTF-8') ?></h3>
<table class="snapshot-table">
    <thead>
        <tr>
            <th><?= htmlspecialchars(__('auth.username'), ENT_QUOTES, 'UTF-8') ?></th>
            <th><?= htmlspecialchars(__('users.manage_users'), ENT_QUOTES, 'UTF-8') ?></th>
            <th><?= htmlspecialchars(__('users.manage_processes'), ENT_QUOTES, 'UTF-8') ?></th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($yamlUsers as $name => $data): ?>
            <tr>
                <td><code><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></code></td>
                <td><?= !empty($data['can_manage_users']) ? '&#10003;' : '—' ?></td>
                <td><?= !empty($data['can_manage_processes']) ? '&#10003;' : '—' ?></td>
                <td>
                    <a href="/users/edit?username=<?= htmlspecialchars(urlencode($name), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('repo.edit'), ENT_QUOTES, 'UTF-8') ?></a>
                    <?php if ($name !== $currentUser): ?>
                    <form method="post" action="/users/delete" style="display:inline"
                          onsubmit="return confirm(<?= htmlspecialchars(json_encode(__('users.confirm_delete')), ENT_QUOTES, 'UTF-8') ?>);">
                        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="username" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
                        <button type="submit" class="btn-delete"><?= htmlspecialchars(__('repo.delete'), ENT_QUOTES, 'UTF-8') ?></button>
                    </form>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach ?>
    </tbody>
</table>
