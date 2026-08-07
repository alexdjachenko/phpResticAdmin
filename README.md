# phpResticAdmin

Веб-интерфейс и API для управления резервными копиями [restic](https://restic.net/).

Лёгкое PHP-приложение без фреймворков. Позволяет управлять несколькими restic-репозиториями: просматривать снепшоты и содержимое, скачивать файлы и целые снепшоты, выполнять обслуживание (check, prune, forget, unlock), управлять ключами шифрования. Поддерживает локальное, S3 и другие типы хранилищ restic. Работает в Docker, разворачивается через Docker Swarm.

---

Web UI and API for managing [restic](https://restic.net/) backups.

A lightweight PHP application with no framework dependencies. Manage multiple restic repositories: browse snapshots and file trees, download individual files or whole snapshots, run maintenance operations (check, prune, forget, unlock), manage encryption keys. Supports local, S3, and other restic backends. Ships as a Docker image, deployed via Docker Swarm.

## Docker Swarm

```bash
docker stack deploy -c docker/docker-compose.yml phpresticadmin
```

Open http://localhost:8080.
