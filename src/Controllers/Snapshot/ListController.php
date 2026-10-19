<?php

/**
 * phpResticAdmin — Web UI for restic backup repositories.
 * Copyright (c) 2026 Alex Djachenko (Алексей Дьяченко)
 * Licensed under the Apache License, Version 2.0.
 */

declare(strict_types=1);

namespace App\Controllers\Snapshot;

use App\Controllers\BaseController;
use App\Core\App;
use App\Storage\ContentTargets;

class ListController extends BaseController
{
    /**
     * GET /snapshots — список снепшотов.
     *
     * Только чтение: read() + reconcile() (постобработка УЖЕ готового вывода).
     * Старт задачи — отдельная операция POST /snapshots/load (кнопка). Раньше
     * страница сама блокирующе запускала задачу и ждала результат — из-за этого
     * зависал сервер на больших репозиториях.
     */
    public function list(): void
    {
        $user = $this->requireUser();
        if ($user === null) {
            return;
        }

        $repoId = $this->resolveRepoId($this->request());

        if ($repoId === null) {
            $this->render('snapshots/list.php', [
                'repo' => null,
                'view' => ['state' => 'absent'],
                'isLoggedIn' => App::auth()->isLoggedIn(),
                'username' => $user,
            ]);
            return;
        }

        $repo = $this->requireRepo($user, $repoId, 'read');
        if ($repo === null) {
            return;
        }

        $resolver = App::contentResolver();
        $target = ContentTargets::forSnapshots($repo);

        // Постобработка уже завершённого вывода (задач НЕ ставит).
        $resolver->reconcile($target);
        $view = $resolver->read($target);

        $snapshots = is_array($view['value'] ?? null) ? $view['value'] : [];
        $filter = $this->prepareList($snapshots);

        $this->render('snapshots/list.php', [
            'repo' => $repo,
            'view' => $view,
            'snapshots' => $filter['snapshots'],
            'snapTags' => $filter['tags'],
            'snapHosts' => $filter['hosts'],
            'snapPaths' => $filter['paths'],
            'isLoggedIn' => App::auth()->isLoggedIn(),
            'username' => $user,
            'csrfToken' => App::security()->csrfToken(),
        ]);
    }

    /**
     * Сортирует снепшоты (сначала новые) и собирает distinct-списки для фильтров.
     *
     * Представление только читает данные: порядок сортируется в памяти, в кеш
     * ничего не пишется. `strtotime` нормализует разные смещения таймзон restic,
     * поэтому сравнение по моменту времени надёжнее, чем `strcmp` по ISO-строке.
     *
     * @param array<int, array<string, mixed>> $snapshots
     * @return array{snapshots: array<int, array<string, mixed>>, tags: array<int, string>, hosts: array<int, string>, paths: array<int, string>}
     */
    private function prepareList(array $snapshots): array
    {
        usort($snapshots, static function (array $a, array $b): int {
            return (strtotime((string) ($b['time'] ?? '')) ?: 0)
                <=> (strtotime((string) ($a['time'] ?? '')) ?: 0);
        });

        $tags = $hosts = $paths = [];
        foreach ($snapshots as $snap) {
            foreach ((array) ($snap['tags'] ?? []) as $tag) {
                $tags[(string) $tag] = true;
            }
            $host = trim((string) ($snap['hostname'] ?? ''));
            if ($host !== '') {
                $hosts[$host] = true;
            }
            foreach ((array) ($snap['paths'] ?? []) as $path) {
                $paths[(string) $path] = true;
            }
        }

        $tags = array_keys($tags);
        $hosts = array_keys($hosts);
        $paths = array_keys($paths);
        sort($tags);
        sort($hosts);
        sort($paths);

        return ['snapshots' => $snapshots, 'tags' => $tags, 'hosts' => $hosts, 'paths' => $paths];
    }
}
