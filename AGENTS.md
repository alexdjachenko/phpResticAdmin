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
- **Текущий этап**: Stage 5 (Export, Maintenance, Keys) — реализован

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
    CommandRunner.php    # run() + runStream() + runStreamWithHeaders() — обёртка proc_open()
    RepositoryService.php # testConnection(), init(), backup(), backupSync()
    SnapshotService.php   # listSnapshots(), listLatestSnapshots(), getStats(), getSnapshot(), addTag(), removeTag(), copy()
    MaintenanceService.php # check(), prune(), rebuildIndex(), unlock(), forget(), stats()
    KeyService.php         # listKeys(), addKey(), removeKey(), changePassword()
  Controllers/
    DashboardController.php  # GET / → дашборд, POST /cache/invalidate
    AuthController.php       # GET/POST /login, GET /logout
    RepositoryController.php # list, addForm/add, detail, editForm/edit, check, delete, move, backup, select
    SnapshotController.php   # GET /snapshots, POST /snapshots/tag (AJAX-теги)
    BrowseController.php     # GET /browse — дерево файлов + хлебные крошки
    ExportController.php     # GET /download, GET /export — скачивание файлов и снепшотов
    MaintenanceController.php # GET /maintenance, POST /maintenance/* — обслуживание
    KeyController.php        # GET /keys, POST /keys/* — управление ключами
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
    list.php             # Таблица снепшотов + AJAX-тегирование + Browse + Export
  browse/
    tree.php             # Дерево файлов/папок + хлебные крошки + Download
  maintenance/
    index.php            # Формы обслуживания (connection/check/stats/prune/rebuild-index/unlock/forget)
    result.php           # Результат операции обслуживания
  keys/
    list.php             # Таблица ключей + формы add/remove/passwd
data/
  cfg/
    users.php            # Новый формат: password, api_tokens, repos (use/edit по категориям)
    settings.php         # guest_user, debug, tmp_dir, log_dir, timezone
  data/
    repositories.yaml    # YAML public-репозиториев
    repositories_{user}.yaml  # YAML private-репозиториев (создаётся автоматически)
  tests/Unit/Restic/MaintenanceServiceTest.php
  tests/Unit/Restic/KeyServiceTest.php
  tests/Integration/ExportEndToEndTest.php
  tests/Integration/MaintenanceEndToEndTest.php
  tests/Integration/KeyEndToEndTest.php
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
| GET   | `/repositories/detail` | RepositoryController::detail       | user != null + canUseRead |
| GET   | `/repositories/edit`   | RepositoryController::editForm     | isLoggedIn + canEdit |
| POST  | `/repositories/edit`   | RepositoryController::edit         | isLoggedIn + canEdit |
| POST  | `/repositories/check`  | RepositoryController::check        | isLoggedIn |
| POST  | `/repositories/delete` | RepositoryController::delete       | isLoggedIn + canDelete |
| POST  | `/repositories/move`   | RepositoryController::move         | isLoggedIn + canMove |
| POST  | `/repositories/backup` | RepositoryController::backup       | isLoggedIn + canUseWrite |
| POST  | `/repositories/select` | RepositoryController::select       | user != null |
| GET   | `/snapshots`           | SnapshotController::list           | user != null + canUseRead |
| GET   | `/snapshots/detail`    | SnapshotController::detail         | user != null + canUseRead |
| POST  | `/snapshots/stats`     | SnapshotController::stats          | user != null + canUseRead |
| POST  | `/snapshots/tag`       | SnapshotController::tag            | isLoggedIn + canUseWrite |
| POST  | `/snapshots/copy`      | SnapshotController::copy           | isLoggedIn + canUseRead(src) + canUseWrite(dest) |
| GET   | `/browse`              | BrowseController::tree             | user != null + canUseRead |
| POST  | `/language`            | App (inline handler)               | Нет |
| POST  | `/cache/invalidate`    | DashboardController::invalidateCache | isLoggedIn + debug |
| GET   | `/download`            | ExportController::file             | user != null + canUseRead |
| GET   | `/export`              | ExportController::snapshot         | user != null + canUseRead |
| GET   | `/maintenance`         | MaintenanceController::index       | isLoggedIn + canUseWrite |
| POST  | `/maintenance/check`   | MaintenanceController::check       | isLoggedIn + canUseWrite |
| POST  | `/maintenance/prune`   | MaintenanceController::prune       | isLoggedIn + canUseWrite |
| POST  | `/maintenance/rebuild-index` | MaintenanceController::rebuildIndex | isLoggedIn + canUseWrite |
| POST  | `/maintenance/unlock`  | MaintenanceController::unlock      | isLoggedIn + canUseWrite |
| POST  | `/maintenance/forget`  | MaintenanceController::forget      | isLoggedIn + canUseWrite |
| POST  | `/maintenance/connection` | MaintenanceController::connection | isLoggedIn + canUseWrite |
| POST  | `/maintenance/stats`   | MaintenanceController::stats       | isLoggedIn + canUseWrite |
| GET   | `/keys`                | KeyController::list                | isLoggedIn + canUseRead |
| POST  | `/keys/add`            | KeyController::add                 | isLoggedIn + canUseWrite |
| POST  | `/keys/remove`         | KeyController::remove              | isLoggedIn + canUseWrite |
| POST  | `/keys/passwd`         | KeyController::passwd              | isLoggedIn + canUseWrite |

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

Модель прав для каждой категории задаётся в `users.php` в секции `repos`:

| Право | Метод | Смысл |
|-------|-------|-------|
| `use` | `canUse(cat)` | Видимость репозитория в интерфейсе, базовые мета-данные (старый ключ) |
| `use_read` | `canUseRead(cat)` | Чтение контента: browse, download, export, список ключей (новый ключ) |
| `use_write` | `canUseWrite(cat)` | Запись в restic: backup, tag, maintenance, keys, copy-цель (новый ключ) |
| `edit` | `canEdit(cat)` | CRUD записи о репозитории: имя, путь, пароль, удалить (старый ключ) |

Права независимы. Единственная импликация:
- `use_write` ⇒ `use_read` (кто может писать, тот может и читать контент)

Обратная совместимость и правила:
- Старый ключ `use` — только видимость. НЕ даёт `use_read`.
- Старый ключ `edit` — только CRUD. НЕ даёт `use_read`, НЕ даёт `use_write`.
- `use_read` — без fallback'а. Не задан явно → `false`.
- `use_write` — без fallback'а. Не задан явно → `false`.
- `init` — глобальный флаг `can_init` на уровне пользователя
- `delete` — удалять репозитории (глобальный флаг `can_delete` на уровне пользователя)
- `canMove(from, to)` — требует `use_read(source)` + `use_write(dest)`

Fallback для `can_init`/`can_delete`: если флаг не указан — `isLoggedIn()` (true для вошедших, false для guest).

Fallback-правила для пользователей без секции `repos`:
- logged-in → полные права (use/use_read/use_write/edit = true)
- guest → public: use/use_read = true, use_write/edit = false; остальные категории: всё false

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
- `RepositoryService::testConnection()` выполняет `restic cat config` — быстрая проверка, что репозиторий существует и доступен (без перебора снепшотов)
- `RepositoryService::init()` выполняет `restic init --repo <путь>`
- `RepositoryService::backup()` выполняет `restic backup` со стримингом через `runStream()`
- `SnapshotService::listSnapshots()` — `restic snapshots --json`, парсит JSON (таймаут 120с)
- `SnapshotService::listLatestSnapshots($repo, $n)` — `restic snapshots --json --latest N` для дашборда и страницы репозитория (не тянет полный список с больших удалённых репозиториев)
- `MaintenanceService::stats()` — `restic stats --json` для общей статистики репозитория
- `MaintenanceService::rebuildIndex()` — `restic repair index` (команда `rebuild-index` устарела в restic)
- Если пароль задан — передаёт `RESTIC_PASSWORD` в окружение; иначе добавляет `--insecure-no-password`
- `RepositoryController::edit()` при смене типа с `s3` на другой НЕ очищает `env` (AWS-ключи). Это осознанно: если пользователь передумает и вернётся к `s3`, данные не потеряются. При обратном переключении поля в форме будут предзаполнены старыми значениями.
- **Секретные поля в форме редактирования — «пустое поле = оставить как есть».** Пароль репозитория (`password`) и S3-секрет (`s3_secret`) перезаписываются только при непустом вводе; существующие значения берутся из сохранённого репозитория. Заполнение одного поля не сбрасывает другое.

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

## Gotchas (неочевидные ловушки, найденные при отладке)

### restic CLI

- **`restic ls --json` выдаёт NDJSON (JSON Lines), а не массив.** Каждая строка — отдельный JSON-объект. `json_decode($stdout, true)` на всём выводе падает, нужно парсить построчно.
- **`restic snapshots --json` в старых версиях (0.14, Debian bookworm) не содержит `summary.total_size`.** Для получения размеров использовать `restic stats --json --mode raw-data <ids...>`. В коде: `SnapshotService::enrichWithSizes()`.
- **`--insecure-no-password` — глобальный флаг, должен стоять ДО подкоманды** (`restic --insecure-no-password --repo /x snapshots`), а не после позиционных аргументов (иначе restic примет его за snapshot ID). В коде: `ResticCommandBuilder::buildCommand()` обеспечивает правильный порядок для всех сервисов и контроллеров. **Важно:** `RepositoryService` был исправлен (Stage 5 regression) — до исправления флаги ставились после подкоманды (`restic init --repo /path --insecure-no-password`), что ломало init в restic 0.19+.
- **`restic ls` возвращает записи `.` и `..`** среди вывода. `empty('..')` → false, поэтому фильтровать явно: `$name === '.' || $name === '..'`.
- **restic требует `$HOME` для кеша** (`.cache/restic`). В Docker-контейнере переменная не задана → падает `ls`, `find` и др. `CommandRunner::ensureEnv()` проставляет `HOME=/tmp`.
- **`restic ls` не рекурсивен.** Показывает только прямых детей каталога. Каждый клик по папке в browse делает отдельный HTTP-запрос.

### Docker / права

- **Apache работает под `www-data`.** `mkdir('/tmp/phpresticadmin')` падает с Permission denied. Создавать директорию на этапе сборки в `Dockerfile`: `RUN mkdir -p /tmp/phpresticadmin/restic-cache && chown -R www-data:www-data /tmp/phpresticadmin`.
- **`RESTIC_CACHE_DIR`** указывает на `/tmp/phpresticadmin/restic-cache` (создаётся в Dockerfile). `CommandRunner::ensureEnv()` пробует использовать её; при недоступности — fallback на `HOME/.cache/restic`.
- **`tmp_dir` в `settings.php`** — `/tmp/phpresticadmin` (системный tmp, не проектный). Не требует Docker volume.

### PHP

- **`round(1.0, 2)` → `1` (без конечных нулей), а не `"1.00"`.** Для форматирования с сохранением десятичных знаков использовать `number_format()`. Исключение: байты (unitIndex=0) — целые без десятичных.
- **`empty('..')` → false.** Строка `".."` не считается empty. Явные сравнения надёжнее.

### CI / GitHub Actions

- **GitHub-агент НЕ имеет доступа к Actions API** (логи джобов, `gh run view`). Он работает через GitHub REST API (PR checks, files, статусы коммитов). Commit status endpoint (`/commits/{sha}/status`) возвращает только legacy-статусы, НЕ check runs от GitHub Actions.
- **Как ставить задачу GitHub-агенту для проверки CI:**
  1. Просить **прочитать PR** (`GET /repos/{owner}/{repo}/pulls/{number}`) — состояние (open/closed/merged)
  2. Просить **список файлов PR** (`GET /repos/{owner}/{repo}/pulls/{number}/files`) — какие файлы изменены. **Важно:** API возвращает максимум 30 файлов по умолчанию, для больших PR просить `per_page=100`
  3. Для статуса проверок — просить пользователя дать вывод из вкладки Checks, НЕ пытаться получить через агента. Агент не может достучаться до Check Runs API
  4. **НИКОГДА не домысливать результат CI.** Если агент не дал статусов — так и говорить: «статусы CI недоступны агенту, попроси пользователя скинуть вывод»
- **Проверено экспериментально:** даже с конкретными run ID и job ID из URL Actions (например, `.../actions/runs/31315459347/job/93249843228`) агент НЕ может получить статусы — у него нет MCP-инструмента для Actions REST API. Единственный способ узнать результат CI — пользователь смотрит вкладку Checks и передаёт вывод.
- **Интеграционные тесты требуют restic в PATH.** В workflow `test.yml` restic устанавливается на раннер отдельным шагом (через curl). Без этого все restic-зависимые тесты скипаются через `markTestSkipped`
- **PR-образы тегируются `pr-{номер}`** при каждом пуше (workflow `build-and-publish.yml`). `latest` и версионные теги (`vX`, `vX.Y`) ставятся при публикации релиза (триггер `release: published`), а не на push в main
- **Релизный workflow (`build-and-publish.yml`)** использует триггер `release: types: [published]` вместо `tags: ['v*']`, потому что `release-please` создаёт тег через `GITHUB_TOKEN`, а GitHub Actions не пропускает триггеры от собственного токена (защита от бесконечных циклов)

### Интерфейс

- **`current_repo` в сессии переживает между вкладками.** При открытии новой вкладки дашборд показывает данные последнего выбранного репо. Дашборд делает `redirect('/repositories')`, сбрасывая `current_repo`.
- **Стриминг (`Content-Type: text/plain`) даёт чёрную страницу без навигации.** Для backup используется синхронный `backupSync()` + рендеринг в шаблоне `repositories/backup.php`.
- **`restic dump /` создаёт tar-архив**, который нужно отдавать с `Content-Type: application/x-tar`
- **`restic key add` и `restic key passwd` требуют подтверждения пароля через stdin** (два ввода)
- **`restic check` может быть долгим** — для CI использовать `--read-data-subset=1/100`

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

Legacy-формат (только ключи `use`/`edit`, без `use_read`/`use_write`) поддерживается.

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

### `data/data/users.yaml`

Дополнительный источник пользователей (формат — map логин → массив настроек,
как в `users.php`). При совпадении логина побеждает `users.php`.

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

### `data/data/repositories.yaml`

Расположение хранится в поле, зависящем от типа: `local_path` (local), `s3_bucket` (s3),
`sftp_path` (sftp), `rest_url` (rest). S3-endpoint хранится в `env.AWS_ENDPOINT`.

```yaml
repositories:
  - id: "a1b2c3d4"
    name: "My Backup"
    type: "local"
    local_path: "/backups/repo"
    password: null
    backup_paths:
      - "/home"
      - "/etc"
  - id: "e5f6a7b8"
    name: "S3 Backup"
    type: "s3"
    s3_bucket: "my-bucket/restic"
    password: "secret"
    env:
      AWS_ACCESS_KEY_ID: "..."
      AWS_SECRET_ACCESS_KEY: "..."
      AWS_ENDPOINT: "https://s3.example.com"
  - id: "c9d0e1f2"
    name: "SFTP Backup"
    type: "sftp"
    sftp_path: "user@host:/srv/repo"
    password: "secret"
  - id: "f3a4b5c6"
    name: "REST Backup"
    type: "rest"
    rest_url: "http://host:8000/"
    password: "secret"
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
- [x] **Stage 5: Export, Maintenance, Keys, копирайты**
- [ ] **Демо-данные**: при установке не создаётся `repositories.yaml` с примерами
- [ ] **Валидация `users.php`**: лишняя запятая в массиве ломает парсинг
- [ ] **Интеграционный тест `POST /cache/invalidate`**
- [ ] **Интеграционные тесты CRUD**: add/delete/move через HTTP

---

## Правила написания и оформления тестов

> **Правила написания кода** (именование, структура, i18n, архитектура, шаблоны) —
> см. отдельный файл **[CODING.md](CODING.md)**. Ознакомьтесь с ним перед любыми
> операциями, связанными с правкой кода.

### Структура тестового файла

Каждый тестовый класс должен начинаться с PHPDoc-комментария, описывающего:

1. **Цель теста** — что именно тестируется и почему.
2. **Сценарий тестирования** — пошагово, что происходит в тестах.
3. **Критерий успеха** — какие утверждения должны выполниться.
4. **Требования к окружению** (например, "Требует: restic в PATH").

```php
/**
 * Интеграционный тест резервного копирования (restic backup).
 *
 * Цель: проверить, что операция backup создаёт снапшоты в restic-репозитории
 *       и что SnapshotService способен их обнаружить и прочитать.
 *
 * Сценарий:
 *   1. Инициализируется временный restic-репозиторий без пароля.
 *   2. Создаются тестовые файлы/директории.
 *   3. Выполняется restic backup через CommandRunner.
 *   4. Через SnapshotService::listSnapshots() проверяется наличие снапшота.
 *
 * Критерий успеха:
 *   - exitCode backup = 0.
 *   - SnapshotService возвращает непустой массив снапшотов.
 *   - Каждый снапшот содержит ключевые поля: short_id, paths, summary.
 *
 * Требует: restic в PATH (тест запускается только в CI).
 */
```

### Оформление методов теста

- Каждый тестовый метод должен иметь PHPDoc-комментарий, описывающий что именно проверяется.
- В теле теста использовать комментарии `// Arrange`, `// Act`, `// Assert` (или их русские аналоги) для визуального разделения фаз теста.
- Вспомогательные методы (хелперы) должны иметь `@return` в PHPDoc.
- Свойства класса должны иметь `@var` с описанием назначения.

### Содержательные требования

1. **assert должен проверять заявленную цель.** Если тест называется `testSnapshotSizesIncreaseWithNewData`, в нём ОБЯЗАН быть assert, проверяющий рост размера (например, `assertGreaterThan($size1, $size3)`). Вычисление размеров без assert-сравнения — ошибка.

2. **Название теста должно соответствовать тому, что он проверяет.** Если тест называется `testGetFallsBackToEnglishWhenRussianKeyMissing`, он должен использовать ключ, которого НЕТ в русской локали. Если такого ключа нет — переименовать тест.

3. **Строгие assert'ы предпочтительнее слабых.** Вместо `assertGreaterThanOrEqual(2, count($paths))` использовать `assertCount(2, $paths)` плюс `assertContains` для каждого конкретного пути.

4. **Проверять не только ok/fail, но и содержимое ошибки.** Если тест проверяет негативный сценарий, добавлять `assertNotEmpty($result['error'])` и, где возможно, `assertStringContainsString` для ключевых слов в ошибке.

5. **Команды restic: проверять порядок флагов.** Глобальные флаги (`--repo`, `--insecure-no-password`) должны идти ДО подкоманды. В юнит-тестах сервисов использовать `array_search` для проверки позиций флагов относительно подкоманды.

6. **Интеграционные тесты должны проходить через те же сервисы, что и веб-интерфейс.** Если веб-интерфейс вызывает `RepositoryService::init()`, интеграционный тест должен вызывать именно его, а не `CommandRunner` напрямую. Прямой вызов `CommandRunner` в обход сервиса маскирует ошибки в построении команд (например, неверный порядок глобальных флагов restic).

7. **Часто используемые операции должны иметь выделенный метод ядра.** Построение сложной команды restic (глобальные флаги, переменные окружения, пароль/--insecure-no-password) не должно дублироваться в контроллерах и тестах — оно инкапсулируется в сервисном методе (например, `RepositoryService::init()`). При этом важен разумный баланс: не стоит создавать отдельный метод для каждой однострочной команды, если она вызывается один раз. Критерий: если логика построения команды используется более чем в одном месте ИЛИ содержит нетривиальные правила порядка флагов — выносить в метод ядра.

### Чего следует избегать

- TODO в коде тестов, указывающих на недоделки. Либо сразу исправлять, либо фиксировать в соответствующей задаче.
- assertTrue/assertFalse без сообщения об ошибке (второй аргумент).
- Дублирование одного и того же сценария в интеграционных и юнит-тестах без разницы в проверках.
