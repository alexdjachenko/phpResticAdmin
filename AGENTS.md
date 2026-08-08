# AGENTS.md для PHPResticAdmin

## Обзор проекта

PHPResticAdmin — **бесфреймворковое** PHP-приложение, предоставляющее веб-интерфейс и API для управления репозиториями [restic](https://restic.net/). Никаких Laravel, Symfony Framework или Slim — вся инфраструктура (роутер, DI, сессии, CSRF, шаблонизация, аутентификация) написана вручную в `src/Core/`.

- **Стек**: PHP 8.1+, Apache (mod_rewrite), Docker
- **Рантайм-зависимость**: `symfony/yaml` (автономный YAML-парсер, не фреймворк)
- **Dev-зависимости**: PHPUnit 10, PHPStan 1
- **CLI-инструмент**: `restic` (устанавливается в Docker-образ)
- **Репозиторий**: `alexdjachenko/PHPResticAdmin` на GitHub
- **VCS**: Git, хостинг GitHub. Работаем через feature-ветки и Pull Requests
- **CI**: GitHub Actions — тесты запускаются только там. Ориентируемся на статусы сборок на GitHub, локально проект не запускаем
- **Текущий этап**: Stage 4 (Снепшоты, Browse, страница репозитория, backup, механика текущего репозитория) — завершён, в доработке

---

## Воркфлоу разработки (Git/GitHub)

### Рекомендуемый процесс

```
1. git checkout main && git pull origin main        # актуализировать main
2. git checkout -b feature-branch                   # создать ветку
3. ... код, правки, тесты ...                       # разработка
4. git add -A && git commit -m "..."               # зафиксировать
5. git push -u origin feature-branch               # отправить на GitHub
6. Открыть Pull Request из ветки в main             # через ссылку из вывода git push
7. Дождаться зелёных проверок CI (GitHub Actions)   # lint, phpstan, unit, integration
8. Если проверки упали — правим, git push снова     # CI перезапускается автоматически
9. После зелёных проверок — "Merge pull request"   # на GitHub
10. git checkout main && git pull origin main       # подтянуть изменения локально
```

### Тестирование Docker-образа до мерджа

Не обязательно мерджить PR, чтобы получить образ для стенда — при каждом пуше в PR-ветку workflow автоматически собирает и пушит образ с тегом `pr-{номер}`:

1. `git push` в PR-ветку → CI собирает и пушит образ
2. Тег: `ghcr.io/alexdjachenko/phpresticadmin:pr-{номер}`
3. На стенде: `docker pull ghcr.io/alexdjachenko/phpresticadmin:pr-{номер}`
4. `release-please` при этом НЕ запускается (только на push в main)
5. После успешного тестирования — мерджить PR как обычно

### После мерджа не в main

Если продолжается работа в той же ветке после мерджа PR (ветка отстала от main):

```bash
git checkout feature-branch
git pull origin main
```

Это подтянет merge-коммит, и можно продолжать коммитить в ту же ветку.

### Правила завершения этапа

- В конце каждого этапа помощник **обязан** дать текст комментария для коммита (conventional commits: `feat/refactor/fix/test(...): краткое описание`) без напоминания со стороны пользователя.
- Перед этим помощник делает итоговый отчёт: что сделано, какие файлы изменены.
- Пользователь принимает/отклоняет правки, затем коммитит с предложенным сообщением.
- Локально проверяется только синтаксис (`php -l`). Все тесты — в CI.

- **Локально проект НЕ запускаем.** Нет локального веб-сервера, нет Docker-контейнера для разработки
- **Локально НЕ гоним тесты.** Исключение — быстрая проверка синтаксиса (`php -l`)
- **Все тесты проходят в GitHub Actions** — lint, phpstan, unit, integration
- При падении тестов смотрим логи в GitHub Actions (вкладка Checks в PR) и правим по логам
- Для получения CI-логов использовать **суб-агента GitHub**. Передавать ему номер PR и ссылку на Actions run. Если агент не находит логи — уточнить задачу: ему доступны PR checks, файлы ветки и workflow-определение, но НЕ прямой Actions API (логи джобов). В крайнем случае — попросить пользователя скинуть вывод.
- Интеграционные тесты требуют Docker с restic — они проходят ТОЛЬКО в CI

---

## Структура директорий

```
htdocs/                  # Apache DocumentRoot
  index.php              # Точка входа: autoload → App::boot() → App::run()
  .htaccess              # mod_rewrite: все запросы → index.php
  assets/
    css/style.css
src/
  Core/
    App.php              # Сервис-локатор + начальная загрузка + регистрация роутов
    Router.php           # Статический роутинг (метод + точный путь, без плейсхолдеров)
    Request.php          # Обёртка над $_SERVER, $_GET, $_POST
    Response.php         # redirect(), render(), json(), error()
    Session.php          # Обёртка PHP-сессий + flash-сообщения
    Security.php         # Генерация/проверка CSRF-токенов + помощник htmlspecialchars
  Helpers/
    View.php             # Статический рендерер шаблонов с опциональной обёрткой layout
    Lang.php             # i18n: загрузка переводов, setLocale(), get(), detectFromRequest()
    Format.php           # bytes(), date(), timeAgo(), truncate()
    functions.php        # Глобальная функция-хелпер __() для переводов
  Lang/
    en.php               # Английские переводы (ключ → строка)
    ru.php               # Русские переводы
  Auth/
    Authenticator.php    # Вход/выход, canUse/canEdit/canMove, guest_user, права по категориям
  Storage/
    ConfigStorage.php    # Чтение PHP-конфигов из data/cfg/ (users.php, settings.php)
    RepositoryStorage.php # CRUD (save/delete/move/update) + три категории: public/private/session
  Restic/
    CommandRunner.php    # run() + runStream() — обёртка proc_open()
    RepositoryService.php # testConnection(), init(), backup()
    SnapshotService.php   # listSnapshots(), getSnapshot(), addTag(), removeTag()
  Controllers/
    DashboardController.php  # GET / → дашборд, POST /cache/invalidate
    AuthController.php       # GET/POST /login, GET /logout
    RepositoryController.php # list, addForm/add, detail, editForm/edit, check, delete, move, backup, select
    SnapshotController.php   # GET /snapshots, POST /snapshots/tag (AJAX-теги)
    BrowseController.php     # GET /browse — дерево файлов + хлебные крошки
templates/
  layout.php             # Шапка, dropdown репозиториев, навигация, flash, <main>
  login.php              # Форма входа
  dashboard.php          # Дашборд: последние снепшоты выбранного репозитория
  repositories/
    list.php             # Лёгкая таблица-список (имя-ссылка, тип, категория)
    add.php              # Форма добавления: имя, тип, путь, пароль, backup_paths, S3, категория
    detail.php           # Страница деталей: инфо + Check/Backup/Snapshots/Edit/Move/Delete
    edit.php             # Форма редактирования с backup_paths и S3
  snapshots/
    list.php             # Таблица снепшотов + AJAX-тегирование + Browse
  browse/
    tree.php             # Дерево файлов/папок + хлебные крошки
data/
  cfg/
    users.php            # Новый формат: password, api_tokens, repos (use/edit по категориям)
    settings.php         # guest_user, debug, tmp_dir, log_dir, timezone
  data/
    repositories.yaml    # YAML public-репозиториев
    repositories_{user}.yaml  # YAML private-репозиториев (создаётся автоматически)
  tests/
  bootstrap.php          # composer autoload
  Unit/
    Core/SessionTest.php
    Core/SecurityTest.php
    Auth/AuthenticatorTest.php
    Storage/ConfigStorageTest.php
    Storage/RepositoryStorageTest.php
    Helpers/LangTest.php
    Helpers/FormatTest.php
  Integration/
    CanaryTest.php       # Дымовой тест: HTTP GET / → содержит "phpResticAdmin"
    ResticConnectionTest.php  # Требует restic: init + testConnection
    SnapshotServiceTest.php   # list, get, addTag, removeTag
    BackupServiceTest.php     # backup creates snapshot, multiple paths
    BrowseIntegrationTest.php # browse root + subdirectory
docker/
  Dockerfile             # php:8.1-apache + restic + composer install --no-dev
  docker-compose.yml     # Порт 8080:80, именованный том для /var/www/data
  apache-config.conf     # AllowOverride All для mod_rewrite
.github/workflows/
  test.yml               # 4 задачи: lint, static-analysis, unit-tests, integration-tests
  build-and-publish.yml  # Release-please + сборка/публикация Docker в GHCR
```

---

## Как работает приложение

### Последовательность загрузки

1. `htdocs/index.php` подключает `vendor/autoload.php`, вызывает `App::boot()`, затем `App::run()`
2. `App::boot()`:
   - Устанавливает `date_default_timezone_set` из `settings.php`
   - Кеширует уровень отладки из `'debug'` (0/1/2)
   - Запускает PHP-сессию через `Session::start()`
   - Вызывает `Authenticator::resolve()` (читает `auth_user` из сессии или подставляет `guest_user`)
   - Сбрасывает `current_repo` если репозиторий был удалён
   - Инициализирует язык: читает `lang` из сессии или определяет из `Accept-Language`
   - Вызывает `registerRoutes()` — регистрирует все роуты
3. `App::run()`: создаёт `Request`, передаёт его в `Router::dispatch()`

### Роуты (все статические, без параметров пути)

| Метод | Путь                   | Controller::method                | Требуется авторизация      |
|-------|------------------------|-----------------------------------|----------------------------|
| GET   | `/`                    | DashboardController::index         | Нет |
| GET   | `/login`               | AuthController::loginForm          | Нет |
| POST  | `/login`               | AuthController::login              | Нет |
| GET   | `/logout`              | AuthController::logout             | Нет |
| GET   | `/repositories`        | RepositoryController::list         | user != null |
| GET   | `/repositories/add`    | RepositoryController::addForm      | isLoggedIn + canEdit |
| POST  | `/repositories/add`    | RepositoryController::add          | isLoggedIn + canEdit |
| GET   | `/repositories/detail` | RepositoryController::detail       | user != null + canUse |
| GET   | `/repositories/edit`   | RepositoryController::editForm     | isLoggedIn + canEdit |
| POST  | `/repositories/edit`   | RepositoryController::edit         | isLoggedIn + canEdit |
| POST  | `/repositories/check`  | RepositoryController::check        | isLoggedIn |
| POST  | `/repositories/delete` | RepositoryController::delete       | isLoggedIn + canDelete |
| POST  | `/repositories/move`   | RepositoryController::move         | isLoggedIn + canMove |
| POST  | `/repositories/backup` | RepositoryController::backup       | isLoggedIn + canEdit |
| POST  | `/repositories/select` | RepositoryController::select       | user != null |
| GET   | `/snapshots`           | SnapshotController::list           | user != null + canUse |
| POST  | `/snapshots/tag`       | SnapshotController::tag            | isLoggedIn + canEdit |
| GET   | `/browse`              | BrowseController::tree             | user != null + canUse |
| POST  | `/language`            | App (inline handler)               | Нет |
| POST  | `/cache/invalidate`    | DashboardController::invalidateCache | isLoggedIn + debug |

### Логика аутентификации

- `Authenticator::login()` использует `password_verify()` для проверки bcrypt-хешей из `data/cfg/users.php`
- `Authenticator::resolve()` возвращает:
  - `auth_user` из сессии (вошедший пользователь), ИЛИ
  - `guest_user` из `settings.php` (анонимный гость), ИЛИ
  - `null` (неаутентифицирован)
- Если `guest_user` равен `null` и пользователь не вошёл → `RepositoryController::list()` редиректит на `/login`
- Если `guest_user` задан (например, `"guest"`) → неаутентифицированные пользователи могут видеть список репозиториев, но кнопка «Проверить» скрыта
- Только пользователи с `isLoggedIn()` могут делать POST на `/repositories/check`

### Категории репозиториев и модель прав

Три категории репозиториев:

| Категория  | Хранилище                         | Назначение |
|------------|-----------------------------------|------------|
| `public`   | `data/data/repositories.yaml`     | Общие репозитории, видны всем |
| `private`  | `data/data/repositories_{user}.yaml` | Личные репозитории пользователя |
| `session`  | `$_SESSION['session_repos']`      | Временные, живут пока жива сессия |

Модель прав (use/edit) для каждой категории задаётся в `users.php` в секции `repos`:

- `use` — видеть репозитории категории в списке (задаётся в `repos.{category}.use`)
- `edit` — добавлять, удалять, редактировать. Подразумевает `use` (задаётся в `repos.{category}.edit`)
- `init` — инициализировать новые restic-репозитории (глобальный флаг `can_init` на уровне пользователя)
- `delete` — удалять репозитории (глобальный флаг `can_delete` на уровне пользователя)
- `canMove(from, to)` — требует `edit` на обе категории

Fallback для `can_init`/`can_delete`: если флаг не указан — `isLoggedIn()` (true для вошедших, false для guest).

Fallback-правила:
- Пользователь с полной секцией `repos` — используются указанные права
- Пользователь без секции `repos`: logged-in → полные права, guest → defaultGuest (public.use, без edit)
- `edit: true` автоматически даёт `use: true`

### CSRF-защита

- `Security::csrfToken()` генерирует случайный токен и сохраняет в сессии
- `Security::validateCsrf()` сравнивает с токеном сессии через `hash_equals` и удаляет токен после первой проверки — токен ОДНОРАЗОВЫЙ
- Все формы содержат скрытое поле `_csrf_token`
- Все JSON-ответы ДОЛЖНЫ возвращать поле `_csrf_token` с новым токеном — фронтенд обновляет `data-csrf` на всех элементах
- Если JSON-ответ не вернул новый токен, следующий POST получит «Invalid security token»
- Исключение: `/repositories/select` — без CSRF, только меняет сессию, модификации данных нет

### Режим отладки и логирование

- В `settings.php`: `'debug' => 0` (0=выкл, 1=info, 2=verbose)
- В production ставить `0` — логируется только критическое (уровень 0)
- `App::log($message, $level)` — сообщение пишется только если `$level <= debugLevel`
  - Уровень 0: всегда (ошибки, вход в систему, инвалидация кеша)
  - Уровень 1: info (попытки входа, проверки CSRF, prefix хеша)
  - Уровень 2: verbose (загрузка каждого конфига)
- `App::isDebug()` возвращает `true` при уровне >= 1
- При включённой отладке в шапке появляется бейдж DEBUG, на странице репозиториев — панель с кнопкой инвалидации OPcache

### Инвалидация кешей

- `POST /cache/invalidate` — требует авторизации и включённой отладки, CSRF-токен
- `App::invalidateCaches()` вызывает `opcache_reset()` и `opcache_get_status()`
- Возвращает количество сброшенных скриптов, при уровне >= 2 — их список
- Кнопка в debug-панели на странице репозиториев

### Рендеринг шаблонов

- `View::render('template.php', $vars, 'layout.php')`:
  - `extract($vars)`, `require` шаблона, захват вывода в `$content`
  - Затем `require` layout.php, который выводит `$content` внутри `<main>`
- `Response::render()` автоматически добавляет `'debug' => App::isDebug()`, `repositories`, `currentRepoId`, `isLoggedIn`, `username`, `flash` в переменные шаблона
- Шаблоны — чистые PHP-файлы, без шаблонизатора

### i18n (интернационализация)

- `Lang::get($key, $replace)` — получить перевод по ключу, с опциональной подстановкой плейсхолдеров `{name}`
- `__()` — глобальная функция-хелпер, вызывает `Lang::get()`
- Файлы переводов: `src/Lang/{locale}.php`, возвращают `return [key => value]`
- `Lang::detectFromRequest()` — парсит `Accept-Language`, берёт первые 2 символа
- Язык сохраняется в сессию (`lang`). При первом заходе определяется из заголовка, затем можно переключить через POST `/language`
- Fallback: если ключа нет в текущем языке → английский → сам ключ
- Все строки в шаблонах и контроллерах (flash-сообщения, ошибки) ОБЯЗАНЫ идти через `__()`

### Интеграция с Restic

- `CommandRunner::run()` использует `proc_open()` с пайпами stdin/stdout/stderr
- `CommandRunner::runStream()` — стриминг вывода в браузер (`fread` в цикле + `flush()`) для backup
- `RepositoryService::testConnection()` выполняет `restic snapshots --json --repo <путь>`
- `RepositoryService::init()` выполняет `restic init --repo <путь>`
- `RepositoryService::backup()` выполняет `restic backup` со стримингом через `runStream()`
- `SnapshotService::listSnapshots()` — `restic snapshots --json`, парсит JSON
- Если пароль задан — передаёт `RESTIC_PASSWORD` в окружение; иначе добавляет `--insecure-no-password`
- `RepositoryController::edit()` при смене типа с `s3` на другой НЕ очищает `env` (AWS-ключи). Это осознанно: если пользователь передумает и вернётся к `s3`, данные не потеряются. При обратном переключении поля в форме будут предзаполнены старыми значениями.

### Механика текущего репозитория

- Dropdown в шапке: `<form>` POST `/repositories/select` без CSRF (только сессия)
- Хранение: `$_SESSION['current_repo']`
- Приоритет определения репозитория для `/snapshots`: `?repo=ID` → `current_repo` из сессии → ни один
- Сброс `current_repo` при удалении репозитория и при загрузке (если репо больше нет)

### Автосоздание `repositories.yaml`

- При первом обращении к `RepositoryStorage::loadPublic()` если файл не существует — создаётся с комментарием-шаблоном
- Private-файлы (`repositories_{user}.yaml`) создаются автоматически при первом `save('private', ...)`
- Session-репозитории живут в сессии, файлов не создают

---

## Основные команды

| Команда | Назначение | Контекст |
|---------|------------|----------|
| `composer install` | Установка зависимостей | Локальная разработка и CI |
| `composer dump-autoload` | Обновить автозагрузку после изменений в composer.json | После добавления files autoload |
| `vendor/bin/phpunit -c phpunit.xml --testsuite unit` | Запуск модульных тестов | CI (локально — только для отладки) |
| `vendor/bin/phpunit -c phpunit.xml --testsuite integration` | Запуск интеграционных тестов | CI (Docker + restic) |
| `vendor/bin/phpstan analyse -c phpstan.neon --no-progress` | Статический анализ | CI (level 1, src + htdocs) |
| `find . -name '*.php' -not -path './vendor/*' -print0 \| xargs -0 -n1 php -l` | Синтаксический линтинг всех PHP-файлов | CI |
| `docker build -t phpresticadmin -f docker/Dockerfile .` | Сборка образа | CI и локально |
| `docker run -d -p 8080:80 phpresticadmin` | Запуск контейнера | Локальное тестирование |
| `php -r "echo password_hash('admin', PASSWORD_DEFAULT);"` | Сгенерировать хеш пароля | Внутри контейнера, для users.php |

---

## Правила написания кода

См. отдельный файл **[CODING.md](CODING.md)** — именование, структура, i18n, архитектура, шаблоны.

---

## Конфигурационные файлы

### `data/cfg/users.php`

```php
return [
    'admin' => [
        'password' => '$2y$10$...',
        'api_tokens' => [],
        'can_init' => true,
        'can_delete' => true,
        'repos' => [
            'public'  => ['use' => true, 'edit' => true],
            'private' => ['use' => true, 'edit' => true],
            'session' => ['use' => true, 'edit' => true],
        ],
    ],
    'guest' => [
        'password' => null,
        'api_tokens' => [],
        'can_init' => false,
        'can_delete' => false,
        'repos' => [
            'public'  => ['use' => true,  'edit' => false],
            'private' => ['use' => false, 'edit' => false],
            'session' => ['use' => false, 'edit' => false],
        ],
    ],
];
```

Legacy-формат поддерживается.

### `data/cfg/settings.php`

```php
return [
    'guest_user' => 'guest',
    'debug' => 0,
    'tmp_dir' => __DIR__ . '/../../tmp',
    'log_dir' => __DIR__ . '/../logs',
    'timezone' => 'UTC',
    'repo_base_dir' => '/backups',
];
```

### `data/data/repositories.yaml`

```yaml
repositories:
  - id: "a1b2c3d4"
    name: "My Backup"
    type: "local"
    path: "/backups/repo"
    password: null
    backup_paths:
      - "/home"
      - "/etc"
    env:
      AWS_ACCESS_KEY_ID: "..."
      AWS_SECRET_ACCESS_KEY: "..."
```

---

## Архитектура Docker

- **Базовый образ**: `php:8.1-apache`
- **Бинарник**: `restic` устанавливается через apt
- **Сборка**: `composer install --no-dev` внутри образа
- **Данные в контейнере**: `data/` копируется при сборке И монтируется как Docker-том
- **Контекст сборки Dockerfile**: корень проекта
- **Порт**: 8080 на хосте → 80 в контейнере

---

## Процесс релиза

- **release-please** запускается при каждом пуше в `main`, создаёт релизный PR
- При слиянии релизного PR создаётся тег версии
- Тег версии запускает сборку Docker-образа и публикацию в `ghcr.io/alexdjachenko/phpresticadmin`
- Теги образов: `latest` (только на тег), `v{major}`, `v{major}.{minor}`, `pr-{номер}`, `sha-{short}`

---

## TODO / Технический долг

- [x] **Stage 3: CRUD репозиториев**
- [x] **Stage 4: Снепшоты, Browse, страница репозитория, backup**
- [ ] **Демо-данные**: при установке не создаётся `repositories.yaml` с примерами
- [ ] **Валидация `users.php`**: лишняя запятая в массиве ломает парсинг
- [ ] **Интеграционный тест `POST /cache/invalidate`**
- [ ] **Интеграционные тесты CRUD**: add/delete/move через HTTP
