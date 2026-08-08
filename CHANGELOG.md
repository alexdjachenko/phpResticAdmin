# Changelog

## [0.2.0](https://github.com/alexdjachenko/phpResticAdmin/compare/v0.1.1...v0.2.0) (2026-08-08)


### Features

* Stage 3: CRUD repositories with categories and i18n ([51f4190](https://github.com/alexdjachenko/phpResticAdmin/commit/51f41909b883c0c306af1e9ba603da60f12f2226))
* Stage 3: CRUD repositories with categories and i18n ([e757b3f](https://github.com/alexdjachenko/phpResticAdmin/commit/e757b3f93fb2b9b52a9f794e10f04e6661e86862))


### Bug Fixes

* canarytest expects capital letters ([93ba3ea](https://github.com/alexdjachenko/phpResticAdmin/commit/93ba3eaaa909cdfc3593ae885f269e3f999978ce))
* make init and delete permissions global, not per category ([b4ea353](https://github.com/alexdjachenko/phpResticAdmin/commit/b4ea35327611f096924dff8ca3883af1440b40d9))
* move translations to src/Lang/, add init/delete rights, repo_base_dir ([39f5e43](https://github.com/alexdjachenko/phpResticAdmin/commit/39f5e436e115b1b7789bf5f63df6757d80eaadeb))
* unit tests - LangTest real keys, AuthenticatorTest admin login ([dd2d8ce](https://github.com/alexdjachenko/phpResticAdmin/commit/dd2d8ce6e9836051ef2932e82ab5bc19c7003c5c))

## [0.1.1](https://github.com/alexdjachenko/phpResticAdmin/compare/v0.1.0...v0.1.1) (2026-08-08)


### Bug Fixes

* add error_log diagnostics for login debugging, update AGENTS.md with TODO section ([c366f1a](https://github.com/alexdjachenko/phpResticAdmin/commit/c366f1a988b443da0b074b195b24be89f4f5edc6))
* add error_log diagnostics to login() ([d52bec5](https://github.com/alexdjachenko/phpResticAdmin/commit/d52bec593f202100d2f21682b563884e1fe17d8c))
* debug mode, structured logging, cache invalidation, auto-create repositories.yaml ([1246ab4](https://github.com/alexdjachenko/phpResticAdmin/commit/1246ab4a6a2dda56637821888221f4dd56bf3ecd))
* invalidata opcacke button js incorrect order ([ad03af8](https://github.com/alexdjachenko/phpResticAdmin/commit/ad03af84fa0116b32691f5c5fb8574b3e2912840))
* show invalidate opcache status ([b23e7d1](https://github.com/alexdjachenko/phpResticAdmin/commit/b23e7d129c8e7cff6584c445982b8d0e85e44429))

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
