# AGENTS.md для PHPResticAdmin

## Обзор проекта

PHPResticAdmin — **бесфреймворковое** PHP-приложение, предоставляющее веб-интерфейс и API для управления репозиториями [restic](https://restic.net/). Никаких Laravel, Symfony Framework или Slim — вся инфраструктура (роутер, DI, сессии, CSRF, шаблонизация, аутентификация) написана вручную в `src/Core/`.

- **Стек**: PHP 8.1+, Apache (mod_rewrite), Docker
- **Рантайм-зависимость**: `symfony/yaml` (автономный YAML-парсер, не фреймворк)
- **Dev-зависимости**: PHPUnit 10, PHPStan 1
- **CLI-инструмент**: `restic` (устанавливается в Docker-образ)
- **Репозиторий**: `alexdjachenko/PHPResticAdmin` на GitHub
- **Триггер CI**: push/PR в `main`

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
  Auth/
    Authenticator.php    # Вход/выход, поддержка guest_user, password_verify
  Storage/
    ConfigStorage.php    # Чтение PHP-конфигов из data/cfg/ (users.php, settings.php)
    RepositoryStorage.php # Чтение YAML из data/data/repositories.yaml
  Restic/
    CommandRunner.php    # Обёртка proc_open() для выполнения restic CLI
    RepositoryService.php # testConnection(): выполняет "restic snapshots --json"
  Controllers/
    DashboardController.php  # GET / → редирект на /repositories
    AuthController.php       # GET/POST /login, GET /logout
    RepositoryController.php # GET /repositories, POST /repositories/check (AJAX)
templates/
  layout.php             # HTML-оболочка: шапка с username/logout, flash-сообщения, <main>
  login.php              # Форма входа (username + password + CSRF-токен)
  dashboard.php          # Заглушка, не рендерится — / редиректит на /repositories
  repositories/
    list.php             # Таблица репозиториев + кнопки «Проверить» (AJAX через fetch)
data/
  cfg/
    users.php            # return ['username' => ['password' => 'bcrypt-хеш']]
    settings.php         # return ['guest_user' => null|string, 'timezone', ...]
  data/
    repositories.yaml    # YAML-список репозиториев (id, name, type, path, password, env)
tests/
  bootstrap.php          # composer autoload
  Unit/
    Core/SessionTest.php
    Core/SecurityTest.php
    Auth/AuthenticatorTest.php
    Storage/ConfigStorageTest.php
    Storage/RepositoryStorageTest.php
  Integration/
    CanaryTest.php       # Дымовой тест: HTTP GET / → содержит "phpresticadmin"
    ResticConnectionTest.php  # Требует restic: init репозитория → testConnection
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
   - Запускает PHP-сессию через `Session::start()`
   - Вызывает `Authenticator::resolve()` (читает `auth_user` из сессии или подставляет `guest_user`)
   - Вызывает `registerRoutes()` — регистрирует 6 статических роутов
3. `App::run()`: создаёт `Request`, передаёт его в `Router::dispatch()`

### Роуты (все статические, без параметров пути)

| Метод | Путь                 | Controller::method         | Требуется авторизация |
|-------|----------------------|----------------------------|------------------------|
| GET   | `/`                  | DashboardController::index | Нет |
| GET   | `/login`             | AuthController::loginForm  | Нет |
| POST  | `/login`             | AuthController::login      | Нет |
| GET   | `/logout`            | AuthController::logout     | Нет |
| GET   | `/repositories`      | RepositoryController::list | user != null |
| POST  | `/repositories/check`| RepositoryController::check| isLoggedIn |

### Логика аутентификации

- `Authenticator::login()` использует `password_verify()` для проверки bcrypt-хешей из `data/cfg/users.php`
- `Authenticator::resolve()` возвращает:
  - `auth_user` из сессии (вошедший пользователь), ИЛИ
  - `guest_user` из `settings.php` (анонимный гость), ИЛИ
  - `null` (неаутентифицирован)
- Если `guest_user` равен `null` и пользователь не вошёл → `RepositoryController::list()` редиректит на `/login`
- Если `guest_user` задан (например, `"guest"`) → неаутентифицированные пользователи могут видеть список репозиториев, но кнопка «Проверить» скрыта
- Только пользователи с `isLoggedIn()` могут делать POST на `/repositories/check`

### CSRF-защита

- `Security::csrfToken()` генерирует случайный токен и сохраняет в сессии
- Форма входа и кнопка «Проверить» содержат скрытое поле `_csrf_token`
- При POST `Security::validateCsrf()` сравнивает с токеном сессии (hash_equals, токен одноразовый — сбрасывается после первой проверки)

### Рендеринг шаблонов

- `View::render('template.php', $vars, 'layout.php')`:
  - `extract($vars)`, `require` шаблона, захват вывода в `$content`
  - Затем `require` layout.php, который выводит `$content` внутри `<main>`
- Шаблоны — чистые PHP-файлы, без шаблонизатора

### Интеграция с Restic

- `CommandRunner::run()` использует `proc_open()` с пайпами stdin/stdout/stderr
- `RepositoryService::testConnection()` выполняет `restic snapshots --json --repo <путь>`
- Если пароль задан — передаёт `RESTIC_PASSWORD` в окружение; иначе добавляет `--insecure-no-password`

---

## Основные команды

| Команда | Назначение | Контекст |
|---------|------------|----------|
| `composer install` | Установка зависимостей | Локальная разработка и CI |
| `vendor/bin/phpunit -c phpunit.xml --testsuite unit` | Запуск модульных тестов | Локально или CI |
| `vendor/bin/phpunit -c phpunit.xml --testsuite integration` | Запуск интеграционных тестов | CI (Docker + restic) |
| `vendor/bin/phpstan analyse -c phpstan.neon --no-progress` | Статический анализ | CI (level 1, src + htdocs) |
| `find . -name '*.php' -not -path './vendor/*' -print0 \| xargs -0 -n1 php -l` | Синтаксический линтинг всех PHP-файлов | CI |
| `docker build -t phpresticadmin -f docker/Dockerfile .` | Сборка образа | CI и локально |
| `docker run -d -p 8080:80 phpresticadmin` | Запуск контейнера | Локальное тестирование |
| `php -r "echo password_hash('admin', PASSWORD_DEFAULT);"` | Сгенерировать хеш пароля | Внутри контейнера, для users.php |

---

## Конфигурационные файлы

### `data/cfg/users.php`

```php
return [
    'admin' => [
        'password' => '$2y$10$...',  // bcrypt-хеш из password_hash()
    ],
];
```

### `data/cfg/settings.php`

```php
return [
    'guest_user' => null,  // Установите 'guest', чтобы разрешить анонимный доступ только для чтения
    'tmp_dir' => __DIR__ . '/../../tmp',
    'log_dir' => __DIR__ . '/../logs',
    'timezone' => 'UTC',
];
```

### `data/data/repositories.yaml`

```yaml
repositories:
  - id: "уникальный-id"
    name: "Отображаемое имя"
    type: "local"           # local, sftp, s3 и т.д.
    path: "/backups/repo"   # путь к restic-репозиторию
    password: null           # RESTIC_PASSWORD, null для insecure-no-password
    # env:                   # опциональные дополнительные переменные окружения
    #   AWS_ACCESS_KEY_ID: "..."
```

---

## Архитектура Docker

- **Базовый образ**: `php:8.1-apache`
- **Бинарник**: `restic` устанавливается через apt
- **Сборка**: `composer install --no-dev` внутри образа
- **Данные в контейнере**: `data/` копируется при сборке И монтируется как Docker-том `phpresticadmin_data`
- **Контекст сборки Dockerfile**: корень проекта (не директория `docker/`)
- **Порт**: 8080 на хосте → 80 в контейнере

---

## Решение проблем

### Не удаётся войти даже с правильным паролем

1. **OPcache**: модуль Apache PHP кеширует подключаемые через `require` файлы. `ConfigStorage::loadPhpFile()` вызывает `opcache_invalidate()` перед `require`, но если вы отредактировали `users.php` после того, как APCu/OPcache уже закешировал старую версию — перезапустите Apache внутри контейнера: `docker exec <контейнер> apache2ctl restart` или просто перезапустите контейнер.

2. **Формат хеша**: Хеш пароля в `users.php` должен быть валидным bcrypt-хешем из `password_hash('пароль', PASSWORD_DEFAULT)`. НЕ используйте хеш-заглушку из шаблона — это фиктивная строка, которая никогда не пройдёт проверку для пароля `'admin'`.

3. **Видимость файлов**: Если используется bind mount для `data/`, убедитесь, что файл читаем для `www-data` внутри контейнера.

### Тесты проходят локально, но падают в CI

- `AuthenticatorTest` использует `session_start()` в CLI-режиме. CI должен иметь поддержку сессий в PHP CLI.
- `ResticConnectionTest` требует бинарник `restic` в PATH. Тест инициализирует временный репозиторий, выполняет `snapshots --json`, затем очищает. Если `restic init` завершился с ошибкой, тест пропускается через `markTestSkipped()`.
- Интеграционный `CanaryTest` ожидает, что приложение ответит на `http://localhost:8080` и содержит "phpresticadmin" в теле ответа. Контейнер должен быть запущен до запуска тестов.

### Ошибка «Invalid security token» при входе

CSRF-токен одноразовый. Если вы отправили форму, нажали «Назад» в браузере и отправили снова — токен уже использован. Обновите страницу входа, чтобы получить новый токен.

---

## Правила написания кода

- **Версия PHP**: 8.1+, типизированные свойства, именованные аргументы
- **Пространства имён**: PSR-4, `App\` → `src/`, `App\Tests\` → `tests/`
- **Без фреймворков**: Вся инфраструктура находится в `src/Core/`. Не добавлять фреймворковые зависимости.
- **Статический сервис-локатор**: Статические методы `App::*()` выступают в роли сервис-контейнера. Не использовать `new` напрямую — всегда обращаться через `App`.
- **Тестировать каждый новый класс**: Создавать соответствующий тест в `tests/Unit/` до или сразу после написания класса.
- **PHPStan level 1**: Весь код в `src/` и `htdocs/` должен проходить проверку.

---

## Процесс релиза

- **release-please** (автоматический инструмент от Google) запускается при каждом пуше в `main`
- Он создаёт/обновляет релизный PR с увеличением версии и списком изменений
- При слиянии релизного PR создаётся тег новой версии (например, v0.1.0)
- Тег версии запускает сборку Docker-образа и публикацию в `ghcr.io/alexdjachenko/phpresticadmin`
- Docker-образы тегируются как: `latest`, `v{major}`, `v{major}.{minor}`, `sha-{short}`

---

## TODO / Технический долг

- [ ] **Демо-данные**: при установке не создаётся `repositories.yaml` с примерами репозиториев. Гостевой вход показывает «No repositories configured». Добавить демо-репозиторий в этапе 3.
- [ ] **Отладка входа**: `AuthController::login()` временно содержит `error_log()` для диагностики. Убрать после завершения отладки.
- [ ] **Валидация `users.php`**: лишняя запятая между элементами массива вызывает «Cannot use empty array elements in arrays». Нужна валидация конфигов при загрузке.
