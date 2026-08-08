# Changelog

## [0.3.0](https://github.com/alexdjachenko/phpResticAdmin/compare/v0.2.0...v0.3.0) (2026-08-08)


### Features

* **stage4:** snapshots, browse, repository detail page, backup strea… ([0bd1a8f](https://github.com/alexdjachenko/phpResticAdmin/commit/0bd1a8f8542c87b5ab95f1474eebc5bfbbde4ebb))
* **stage4:** snapshots, browse, repository detail page, backup streaming ([074eb68](https://github.com/alexdjachenko/phpResticAdmin/commit/074eb68c3fef60b3ba6c406df5abcb9113dd8967))


### Bug Fixes

* **stage4:** create restic cache dir at build time with www-data ownership ([4ace885](https://github.com/alexdjachenko/phpResticAdmin/commit/4ace885c4e84f8b0428be52a00a3fd12537b72bf))
* **stage4:** dashboard session leak, backup black page, snapshot 0 B, broken nav ([6687c72](https://github.com/alexdjachenko/phpResticAdmin/commit/6687c72d95b9c859577fd1500312f168dc58b994))
* **stage4:** filter self-referencing directory node from restic ls output ([19d57fe](https://github.com/alexdjachenko/phpResticAdmin/commit/19d57fe0fcc3cc9ca0023dacff8d8a7d43fc9493))
* **stage4:** fix --insecure-no-password placement in stats, filter .. in browse ([34fd6f9](https://github.com/alexdjachenko/phpResticAdmin/commit/34fd6f9b289375ae68a93cbdbc6c40a54727ce03))
* **stage4:** fix Format::bytes trailing zeros, add PR image tags, update AGENTS.md ([5149a6e](https://github.com/alexdjachenko/phpResticAdmin/commit/5149a6e8e5f20495d13e827be9b3db442495aa5a))
* **stage4:** install restic 0.19.1 binary instead of apt 0.14 ([c3a4b9f](https://github.com/alexdjachenko/phpResticAdmin/commit/c3a4b9fe1fdc321a488dc31c1ec435b3a7026ac1))
* **stage4:** move tmp_dir to system /tmp, set RESTIC_CACHE_DIR for restic ([fafa7b8](https://github.com/alexdjachenko/phpResticAdmin/commit/fafa7b8e5c7c32eed5a470ab56cec5a56796be7d))
* **stage4:** NDJSON parsing for restic ls, stats-based snapshot sizes, E2E test ([9bbbcbf](https://github.com/alexdjachenko/phpResticAdmin/commit/9bbbcbf71e51547f0537fd03492f77d917066932))
* **stage4:** parse restic ls NDJSON output, enrich snapshots with stats for size ([a9c787e](https://github.com/alexdjachenko/phpResticAdmin/commit/a9c787e22b4670539fca2796408287708147d658))
* **stage4:** restore repo list, fix browse empty, add back snapshot size, filter cache noise ([40f5fd3](https://github.com/alexdjachenko/phpResticAdmin/commit/40f5fd3a1be0e4f317be5e7640c04b9689f93a12))
* **stage4:** special-case bytes range in Format::bytes to avoid trailing .00 ([d8e27b9](https://github.com/alexdjachenko/phpResticAdmin/commit/d8e27b9e7bfe1c20a3c438f541a99e11c9825720))
* **stage4:** use number_format instead of round in Format::bytes ([6b76bf7](https://github.com/alexdjachenko/phpResticAdmin/commit/6b76bf7d986f705a1f264b5e83fc85d987f48b45))

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
