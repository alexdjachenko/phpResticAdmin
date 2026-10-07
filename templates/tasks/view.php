<div class="breadcrumb">
    <a href="/tasks" id="task-back-link"><?= htmlspecialchars(__('tasks.back'), ENT_QUOTES, 'UTF-8') ?></a>
</div>

<h2>
    <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
    <span class="task-state"><?= htmlspecialchars(__('tasks.state_' . $state), ENT_QUOTES, 'UTF-8') ?></span>
</h2>

<pre id="task-output"
     class="task-output"
     data-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"
     data-error="<?= htmlspecialchars(__('tasks.stream_error'), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(__('tasks.loading'), ENT_QUOTES, 'UTF-8') ?></pre>

<script>
(function () {
    var pre = document.getElementById('task-output');
    var label = pre.dataset.label;

    var back = document.getElementById('task-back-link');
    if (document.referrer) {
        back.addEventListener('click', function (e) {
            e.preventDefault();
            history.back();
        });
    }

    if (!label) {
        return;
    }

    pre.textContent = '';

    fetch('/tasks/stream?label=' + encodeURIComponent(label))
        .then(function (resp) {
            if (!resp.ok || !resp.body) {
                throw new Error('stream failed');
            }
            var reader = resp.body.getReader();
            var decoder = new TextDecoder();
            function pump() {
                reader.read().then(function (chunk) {
                    if (chunk.done) {
                        return;
                    }
                    pre.textContent += decoder.decode(chunk.value, { stream: true });
                    pre.scrollTop = pre.scrollHeight;
                    pump();
                }, function () {
                    pre.textContent += '\n' + pre.dataset.error;
                });
            }
            pump();
        })
        .catch(function () {
            pre.textContent += '\n' + pre.dataset.error;
        });
})();
</script>
