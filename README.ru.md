[English](README.md)

# phpResticAdmin

Лёгкий веб-интерфейс на PHP без фреймворков для управления резервными копиями [restic](https://restic.net/).

- Без Laravel, Symfony Framework и Slim — вся инфраструктура (роутер, DI, сессии, CSRF, шаблоны, аутентификация) написана вручную в `src/Core/`.
- Рантайм-зависимость: `symfony/yaml` (автономный YAML-парсер).
- Поставляется как Docker-образ; разворачивается через Docker Swarm или `docker compose`.

## Возможности

- Просмотр снепшотов и дерева файлов, скачивание отдельных файлов и целых снепшотов (tar).
- Обслуживание: `check`, `prune`, `forget`, `rebuild-index`, `unlock`, `init`.
- Управление ключами шифрования: список, добавление, удаление, смена пароля.
- Бэкенды: локальный, Amazon S3, S3-совместимые, SFTP, REST-сервер.
- Гибкие права по категориям (`public` / `private` / `session`).
- Ограничение источников резервного копирования и локальных репозиториев разрешёнными корнями.
- Пользователи из `users.php` и/или `users.yaml`.

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

## CI/CD

- GitHub Actions (`test.yml`) запускает lint, PHPStan (level 1), unit-тесты и интеграционные тесты при каждом пуше. Интеграционные тесты требуют restic и выполняются только в CI.
- Каждый пуш в PR-ветку собирает и публикует образ с тегом `ghcr.io/alexdjachenko/phpresticadmin:pr-{номер}` для тестирования до мерджа.
- Релизы управляются release-please; теги релизов собирают образы `latest` и версионные.

Разработчикам: см. [AGENTS.md](AGENTS.md) — структура проекта, соглашения и воркфлоу разработки.
