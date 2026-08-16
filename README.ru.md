[English](README.md)

# phpResticAdmin

Лёгкий веб-интерфейс на PHP без фреймворков для управления резервными копиями [restic](https://restic.net/).

- Без Laravel, Symfony Framework и Slim — вся инфраструктура (роутер, DI, сессии, CSRF, шаблоны, аутентификация) написана вручную в `src/Core/`.
- Рантайм-зависимость: `symfony/yaml` (автономный YAML-парсер).
- Поставляется как Docker-образ; разворачивается через Docker Swarm или `docker compose`.

## Возможности

- Просмотр снепшотов и дерева файлов, скачивание отдельных файлов и целых снепшотов (tar).
- Обслуживание: `check`, `prune`, `forget`, `repair index`, `unlock`, `init`.
- Управление ключами шифрования: список, добавление, удаление, смена пароля.
- Бэкенды: локальный, Amazon S3, S3-совместимые, SFTP, REST-сервер.
- Гибкие права по категориям (`public` / `private` / `session`).
- Ограничение источников резервного копирования и локальных репозиториев разрешёнными корнями.
- Пользователи из `users.php` и/или `users.yaml`.
- Фоновые задачи обслуживания/бекапа через `tsp` (task spooler) с живым стримингом вывода.
- Дашборд со статистикой репозиториев и мониторингом фоновых задач.
- Управление YAML-пользователями и self-service смена пароля.
- Пароль пользователя из Docker-секрета или переменной окружения (`password_var`).

## Быстрый старт

Docker Swarm:

```bash
docker stack deploy -c docker/docker-compose.yml phpresticadmin
```

Docker Compose:

```bash
docker compose -f docker/docker-compose.yml up -d
```

Обычный docker run:

```bash
docker run -d -p 8080:80 ghcr.io/alexdjachenko/phpresticadmin:latest
```

Откройте <http://localhost:8080>.

Приложение слушает порт **80** внутри контейнера, на хосте по умолчанию пробрасывается порт **8080**. Конфигурация файловая (см. ниже); обязательных переменных окружения нет.

## Тома и данные

Образ объявляет именованный том `phpresticadmin_data:/var/www/data`. Всё в `/var/www/data` — рантайм-данные, которые переживают пересоздание контейнера:

| Путь             | Назначение                                                          |
|------------------|---------------------------------------------------------------------|
| `data/cfg/`      | `users.php`, `settings.php`                                          |
| `data/data/`     | `repositories.yaml`, `repositories_{user}.yaml`, `users.yaml`        |
| `data/lang/`     | зарезервировано для переопределения переводов                        |

Образ также создаёт и владеет следующими каталогами (владелец `www-data`, пользователь Apache):

| Путь            | Назначение                                                          |
|-----------------|---------------------------------------------------------------------|
| `/sources`      | разрешённый корень для источников бекапа (`backup_paths_roots`)      |
| `/backups`      | разрешённый корень для локальных репозиториев (`repo_paths_roots`)   |
| `/var/www/data` | рантайм-данные (см. выше)                                            |

Рекомендуется монтировать `/sources` и `/backups` как тома, чтобы данные бекапов переживали пересоздание контейнера. Входящий в комплект `docker/docker-compose.yml` уже маппит их на `phpresticadmin_sources` и `phpresticadmin_backups`.

## Начальная настройка

### Пользователи

Пользователи задаются в `data/cfg/users.php`:

```php
return [
    'admin' => [
        'password' => '$2y$10$...',
        'api_tokens' => [],
        'can_init' => true,
        'can_delete' => true,
        'repos' => [
            'public'  => ['use' => true, 'use_read' => true, 'use_write' => true, 'edit' => true],
            'private' => ['use' => true, 'use_read' => true, 'use_write' => true, 'edit' => true],
            'session' => ['use' => true, 'use_read' => true, 'use_write' => true, 'edit' => true],
        ],
    ],
    'guest' => [
        'password' => null,
        'api_tokens' => [],
        'can_init' => false,
        'can_delete' => false,
        'repos' => [
            'public'  => ['use' => true, 'use_read' => true,  'use_write' => false, 'edit' => false],
            'private' => ['use' => false, 'use_read' => false, 'use_write' => false, 'edit' => false],
            'session' => ['use' => false, 'use_read' => false, 'use_write' => false, 'edit' => false],
        ],
    ],
];
```

Пароль — bcrypt-хеш. Сгенерируйте его командой:

```bash
php -r "echo password_hash('admin', PASSWORD_DEFAULT);"
```

Пользователей также можно задать в `data/data/users.yaml` в той же структуре (map логин → настройки). При совпадении логина побеждает `users.php`.

```yaml
viewer:
    password: null
    api_tokens: []
    can_init: false
    can_delete: false
    repos:
        public:   { use: true, use_read: true, use_write: false, edit: false }
        private:  { use: false, use_read: false, use_write: false, edit: false }
        session:  { use: false, use_read: false, use_write: false, edit: false }
```

YAML-пользователями также можно управлять из веб-интерфейса на `/users` (требуется `can_manage_users`).

### Пароль через секрет / переменную окружения

Любой пользователь (PHP или YAML) может задать `password_var` — **имя** переменной,
содержащей bcrypt-хеш пароля. Хеш разрешается в порядке:

1. Docker-секрет `/run/secrets/<name>`;
2. переменная окружения `<name>`;
3. поле `password` учётки.

Для учётки `admin` используется дефолтное имя `PHPRESTICADMIN_ADMIN_PASSWORD_HASH`;
плейсхолдер-хеш в `users.php` намеренно неверный, поэтому `admin` может войти только
при заданном секрете/переменной.

```bash
# Сгенерировать хеш один раз
php -r "echo password_hash('MyPassword', PASSWORD_DEFAULT);"

# Вариант A: переменная окружения (admin)
docker run -d -p 8080:80 -e PHPRESTICADMIN_ADMIN_PASSWORD_HASH='$2y$10$...' ghcr.io/alexdjachenko/phpresticadmin:latest

# Вариант B: Docker-секрет (admin)
echo -n '$2y$10$...' | docker secret create phpresticadmin_admin_password_hash -
```

### Первый вход (admin2)

При первом старте контейнер создаёт `admin2` (полные права, включая `can_manage_users`
и `can_manage_processes`) со случайным паролем и печатает его в лог контейнера:

```bash
docker logs <container>
# Created initial admin2 user. Login: admin2, Password: ...
```

`admin2` создаётся только если `data/data/users.yaml` ещё не существует.

### Настройки

`data/cfg/settings.php`:

```php
return [
    'guest_user' => 'guest',
    'debug' => 0,
    'tmp_dir' => '/tmp/phpresticadmin',
    'log_dir' => __DIR__ . '/../logs',
    'timezone' => 'UTC',
    'repo_base_dir' => '/backups',
    'backup_paths_roots' => ['/sources'],
    'repo_paths_roots' => ['/backups'],
];
```

| Ключ                  | Назначение                                                             |
|-----------------------|------------------------------------------------------------------------|
| `guest_user`          | Пользователь для анонимных посетителей; `null` отключает гостевой доступ |
| `debug`               | `0` = выкл, `1` = info, `2` = verbose                                   |
| `tmp_dir`             | Системный tmp-каталог для кеша restic                                    |
| `log_dir`             | Каталог логов                                                            |
| `timezone`            | Часовой пояс PHP                                                         |
| `repo_base_dir`       | Базовая директория для относительных локальных путей (по умолчанию `/backups`) |
| `backup_paths_roots`  | Разрешённые корни для источников бекапа; пустой массив = без ограничений  |
| `repo_paths_roots`    | Разрешённые корни для локальных репозиториев; пустой массив = без ограничений |
| `tsp_binary`          | Путь к бинарнику `tsp` (по умолчанию `tsp`)                                  |
| `tsp_slots`           | Количество слотов очереди фоновых задач (по умолчанию `1`)                    |
| `snapshot_cache_ttl`  | TTL (секунды) кеша списка снепшотов в сессии (по умолчанию `600`)             |

## Типы репозиториев и поля расположения

Расположение репозитория хранится в поле, зависящем от типа. В интерфейсе для выбранного типа показывается соответствующее поле.

| Тип   | Поле         | Пример                       | Превращается в restic `--repo`              |
|-------|--------------|------------------------------|---------------------------------------------|
| local | `local_path` | `/backups/repo` (абсолютный) или `my-repo` (относительный) | `/backups/repo` (относительные получают префикс `repo_base_dir`) |
| s3    | `s3_bucket`  | `my-bucket/restic`           | `s3:s3.amazonaws.com/my-bucket/restic` (AWS) или `s3:https://endpoint/my-bucket/restic` |
| sftp  | `sftp_path`  | `user@host:/srv/repo`        | `sftp:user@host:/srv/repo`                  |
| rest  | `rest_url`   | `http://host:8000/`          | `rest:http://host:8000/`                    |

Для S3 endpoint хранится отдельно (`AWS_ENDPOINT` в `env` репозитория). Пусто = AWS S3; для S3-совместимых серверов укажите URL (схема `https://` добавляется автоматически, если отсутствует). Учётные данные — `AWS_ACCESS_KEY_ID` и `AWS_SECRET_ACCESS_KEY` в `env` репозитория.

## Модель прав

Права задаются по категориям (`public`, `private`, `session`) в секции `repos` настроек пользователя.

| Право       | Метод             | Уровень     | Что разрешает                                                        |
|-------------|-------------------|-------------|----------------------------------------------------------------------|
| `use`       | `canUse()`        | Категория   | Видимость в интерфейсе и базовые мета-данные (старый ключ)            |
| `use_read`  | `canUseRead()`    | Категория   | Чтение контента: browse, download, export, список ключей              |
| `use_write` | `canUseWrite()`   | Категория   | Запись в restic: backup, tag, maintenance, keys, цель копирования     |
| `edit`      | `canEdit()`       | Категория   | CRUD записи о репозитории: имя, путь, пароль, удалить                 |
| `init`      | `canInit()`       | Пользователь| Инициализация новых restic-репозиториев                               |
| `delete`    | `canDelete()`     | Пользователь| Удаление репозиториев                                                 |

Правила:

- `use_write` подразумевает `use_read`.
- `use` НЕ даёт `use_read`; `edit` НЕ даёт `use_read` и `use_write`.
- `use_read` / `use_write` без fallback: не заданы явно = `false`.
- `canMove(from, to)` требует `use_read` в исходной категории и `use_write` в целевой.
- `canInit` / `canDelete` по умолчанию равны `isLoggedIn()`, если не заданы.

Два дополнительных глобальных (уровень пользователя) права:

| Право                  | Метод                  | Что разрешает                                          |
|------------------------|------------------------|--------------------------------------------------------|
| `can_manage_users`     | `canManageUsers()`     | Управление YAML-пользователями на `/users`              |
| `can_manage_processes` | `canManageProcesses()` | Видеть фоновые задачи всех пользователей                |

Оба по умолчанию `false`; у `admin` и автосозданного `admin2` включены.

## Фоновые задачи (tsp)

Тяжёлые операции restic (backup, `check`, `prune`, `repair index`, `unlock`, `forget`,
`stats`, `init`, копирование снепшота и статистика снепшота) выполняются в фоне через
[task-spooler](https://manpages.ubuntu.com/manpages/jammy/man1/tsp.1.html) `tsp`.
После старта задачи интерфейс редиректит на `/tasks/stream?label=...`, где вывод
стримится в реальном времени. Активные и последние задачи видны на дашборде.

Для standalone-установки (без Docker) требуется `tsp`:

```bash
sudo apt-get install task-spooler
```

## CI/CD

- GitHub Actions (`test.yml`) запускает lint, PHPStan (level 1), unit-тесты и интеграционные тесты при каждом пуше. Интеграционные тесты требуют restic и выполняются только в CI.
- Каждый пуш в PR-ветку собирает и публикует образ с тегом `ghcr.io/alexdjachenko/phpresticadmin:pr-{номер}` для тестирования до мерджа.
- Релизы управляются release-please; теги релизов собирают образы `latest` и версионные.

Разработчикам: см. [AGENTS.md](AGENTS.md) — структура проекта, соглашения и воркфлоу разработки.
