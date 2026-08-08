# Changelog

## [0.1.1](https://github.com/alexdjachenko/phpResticAdmin/compare/v0.1.0...v0.1.1) (2026-08-08)


### Bug Fixes

* add error_log diagnostics for login debugging, update AGENTS.md with TODO section ([c366f1a](https://github.com/alexdjachenko/phpResticAdmin/commit/c366f1a988b443da0b074b195b24be89f4f5edc6))
* add error_log diagnostics to login() ([d52bec5](https://github.com/alexdjachenko/phpResticAdmin/commit/d52bec593f202100d2f21682b563884e1fe17d8c))

## [0.1.0](https://github.com/alexdjachenko/phpResticAdmin/compare/v0.0.2...v0.1.0) (2026-08-07)


### Features

* **phase2:** authentication, repository list, and connectivity check ([f675357](https://github.com/alexdjachenko/phpResticAdmin/commit/f6753573a3aeb367835797ccf5d19154c91441c0))


### Bug Fixes

* **tests:** use var_export instead of addslashes for password hash in AuthenticatorTest ([92d7c1c](https://github.com/alexdjachenko/phpResticAdmin/commit/92d7c1c012b0cfbe95949a27f5adf03ad68f41eb))

## [0.0.2](https://github.com/alexdjachenko/phpResticAdmin/compare/v0.0.1...v0.0.2) (2026-08-07)


### Bug Fixes

* remove phpresticadmin/ prefix, fix Dockerfile composer install" && git push origin mai ([c462bb3](https://github.com/alexdjachenko/phpResticAdmin/commit/c462bb31674986c5f47c7296a6ee8f641a4e8612))
* rename release-please-manifest.json to .release-please-manifest.json ([988ef7e](https://github.com/alexdjachenko/phpResticAdmin/commit/988ef7ee9fa8f9e1641ba2714d73a96063895426))

## 0.0.1 (initial)

- Bootstrapped project skeleton
- Dockerized PHP 8.1 + Apache setup
- CI/CD pipeline with lint, static analysis, unit tests, integration tests
- Canary autotests
