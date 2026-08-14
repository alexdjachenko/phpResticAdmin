[Русский](README.ru.md)

# phpResticAdmin

A lightweight, framework-free PHP web UI for managing [restic](https://restic.net/) backup repositories.

- No Laravel, Symfony Framework or Slim — all infrastructure (router, DI, sessions, CSRF, templates, auth) is hand-written in `src/Core/`.
- Runtime dependency: `symfony/yaml` (standalone YAML parser).
- Ships as a Docker image; deploy with Docker Swarm or `docker compose`.

## Features

- Browse snapshots and file trees, download individual files or whole snapshots (tar).
- Run maintenance operations: `check`, `prune`, `forget`, `rebuild-index`, `unlock`, `init`.
- Manage encryption keys: list, add, remove, change password.
- Backends: local, Amazon S3, S3-compatible, SFTP, REST server.
- Fine-grained per-category permissions (`public` / `private` / `session`).
- Restriction of backup sources and local repository paths to allowed roots.
- Users from `users.php` and/or `users.yaml`.

## Quick start

Docker Swarm:

```bash
docker stack deploy -c docker/docker-compose.yml phpresticadmin
```

Docker Compose:

```bash
docker compose -f docker/docker-compose.yml up -d
```

Plain docker run:

```bash
docker run -d -p 8080:80 ghcr.io/alexdjachenko/phpresticadmin:latest
```

Open <http://localhost:8080>.

The application listens on port **80** inside the container and is mapped to port **8080** on the host by default. Configuration is file-based (see below); there are no required environment variables.

## Volumes and data

The image declares the named volume `phpresticadmin_data:/var/www/data`. Everything under `/var/www/data` is runtime data and survives container recreation:

| Path             | Purpose                                                            |
|------------------|--------------------------------------------------------------------|
| `data/cfg/`      | `users.php`, `settings.php`                                        |
| `data/data/`     | `repositories.yaml`, `repositories_{user}.yaml`, `users.yaml`      |
| `data/lang/`     | reserved for language overrides                                    |

The image also creates and owns the following directories (owner `www-data`, the Apache user):

| Path         | Purpose                                                    |
|--------------|------------------------------------------------------------|
| `/sources`   | allowed root for backup source paths (`backup_paths_roots`)|
| `/backups`   | allowed root for local repositories (`repo_paths_roots`)   |
| `/var/www/data` | runtime data (see above)                                |

It is recommended to mount `/sources` and `/backups` as volumes so that backup data survives container recreation. The included `docker/docker-compose.yml` already maps them to `phpresticadmin_sources` and `phpresticadmin_backups`.

## Initial setup

### Users

Users are defined in `data/cfg/users.php`:

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

The password is a bcrypt hash. Generate one with:

```bash
php -r "echo password_hash('admin', PASSWORD_DEFAULT);"
```

Users can also be defined in `data/data/users.yaml` using the same structure (a map of username to settings). On login-name collision, `users.php` wins.

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

### Settings

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

| Key                   | Purpose                                                                 |
|-----------------------|-------------------------------------------------------------------------|
| `guest_user`          | Username used for anonymous visitors; `null` disables guest access      |
| `debug`               | `0` = off, `1` = info, `2` = verbose                                    |
| `tmp_dir`             | System temp dir used for the restic cache                               |
| `log_dir`             | Log directory                                                           |
| `timezone`            | PHP timezone                                                             |
| `repo_base_dir`       | Base dir for relative local repository paths (default `/backups`)        |
| `backup_paths_roots`  | Allowed roots for backup source paths; empty array = no restriction      |
| `repo_paths_roots`    | Allowed roots for local repository paths; empty array = no restriction   |

## Repository types and location fields

The repository location is stored in a field that depends on the repository type. The UI shows the appropriate field for the selected type.

| Type   | Field        | Example                     | Becomes restic `--repo`                    |
|--------|--------------|-----------------------------|--------------------------------------------|
| local  | `local_path` | `/backups/repo` (absolute) or `my-repo` (relative) | `/backups/repo` (relative paths get `repo_base_dir` prefix) |
| s3     | `s3_bucket`  | `my-bucket/restic`          | `s3:s3.amazonaws.com/my-bucket/restic` (AWS), or `s3:https://endpoint/my-bucket/restic` |
| sftp   | `sftp_path`  | `user@host:/srv/repo`       | `sftp:user@host:/srv/repo`                 |
| rest   | `rest_url`   | `http://host:8000/`         | `rest:http://host:8000/`                   |

For S3, the endpoint is stored separately (`AWS_ENDPOINT` in the repository `env`). Leave it empty for AWS S3; for S3-compatible servers set the endpoint URL (an `https://` scheme is added automatically if missing). Credentials are `AWS_ACCESS_KEY_ID` and `AWS_SECRET_ACCESS_KEY` in the repository `env`.

## Permission model

Rights are configured per category (`public`, `private`, `session`) in the `repos` section of the user settings.

| Right      | Method            | Level     | Meaning                                                    |
|------------|-------------------|-----------|------------------------------------------------------------|
| `use`      | `canUse()`        | Category  | Visibility in the UI and basic metadata (legacy key)        |
| `use_read` | `canUseRead()`    | Category  | Read content: browse, download, export, list keys           |
| `use_write`| `canUseWrite()`   | Category  | Write to restic: backup, tag, maintenance, keys, copy target |
| `edit`     | `canEdit()`       | Category  | CRUD of the repository record: name, path, password, delete |
| `init`     | `canInit()`       | User      | Initialize new restic repositories                          |
| `delete`   | `canDelete()`     | User      | Delete repositories                                         |

Rules:

- `use_write` implies `use_read`.
- `use` does **not** imply `use_read`; `edit` does **not** imply `use_read` or `use_write`.
- `use_read` / `use_write` have no fallback: not set explicitly = `false`.
- `canMove(from, to)` requires `use_read` on the source category and `use_write` on the destination category.
- `canInit` / `canDelete` default to `isLoggedIn()` when not set.

## CI/CD

- GitHub Actions (`test.yml`) runs lint, PHPStan (level 1), unit tests and integration tests on every push. Integration tests require restic and run only in CI.
- Every push to a PR branch builds and publishes an image tagged `ghcr.io/alexdjachenko/phpresticadmin:pr-{number}` for testing before merge.
- Releases are managed by release-please; release tags build `latest` and versioned images.

Developers: see [AGENTS.md](AGENTS.md) for project structure, conventions and development workflow.
