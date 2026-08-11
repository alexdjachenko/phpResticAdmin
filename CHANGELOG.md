# Changelog

## [0.6.1](https://github.com/alexdjachenko/phpResticAdmin/compare/v0.6.0...v0.6.1) (2026-08-11)


### Bug Fixes

* dry run flag always used for cleaning ([5356809](https://github.com/alexdjachenko/phpResticAdmin/commit/53568099c38f31280d1f895d96544852cb8cdaca))
* dry run flag always used for cleaning ([77467aa](https://github.com/alexdjachenko/phpResticAdmin/commit/77467aa81b06e288820fd9d2a41ee7c49bfa5042))

## [0.6.0](https://github.com/alexdjachenko/phpResticAdmin/compare/v0.5.0...v0.6.0) (2026-08-11)


### Features

* **keys:** add verifyKey core method, UI button, and duplicate detection ([890add7](https://github.com/alexdjachenko/phpResticAdmin/commit/890add72f41450e0e176aa1b852ce7470941bb2c))
* tool for init at maintenance page ([897a904](https://github.com/alexdjachenko/phpResticAdmin/commit/897a9046541c7c5b9cee6d7878dd4c773980765a))


### Bug Fixes

* fix:  ([85494c9](https://github.com/alexdjachenko/phpResticAdmin/commit/85494c91cd8f0573ebbf891af6d81872554844cc))
* fix:  ([716931a](https://github.com/alexdjachenko/phpResticAdmin/commit/716931aa800cd03e6e2df8aedec1a8317c4d2523))
* absolute path to the console utils ([fe63444](https://github.com/alexdjachenko/phpResticAdmin/commit/fe63444f0eb57b37ceddc6728225dc04f4cc667c))
* change shell utils to php ([75b31b4](https://github.com/alexdjachenko/phpResticAdmin/commit/75b31b45f82fcc34d3ff04be1f1ed139cf4f8499))
* **core:** use proc_get_status exitcode instead of proc_close in CommandRunner ([0e522cf](https://github.com/alexdjachenko/phpResticAdmin/commit/0e522cfdf49f216965783f192fb01a2fbe7d55f9))
* refactirong auth test ([6cb7ac9](https://github.com/alexdjachenko/phpResticAdmin/commit/6cb7ac97177dd1875e19e1159d782d9c7cb4b1e4))
* **restic:** correct flag order in RepositoryService, add buildCommand/buildEnv ([32c4666](https://github.com/alexdjachenko/phpResticAdmin/commit/32c466613782ababf8aeb76c6023d6847ad14a0a))
* **restic:** provide meaningful error when restic init fails with empty stderr ([9f727b7](https://github.com/alexdjachenko/phpResticAdmin/commit/9f727b7a8a4433b54cd526740d336b032e7240be))


## [0.5.0](https://github.com/alexdjachenko/phpResticAdmin/compare/v0.4.8...v0.5.0) (2026-08-10)


### Features

* **auth,snapshots:** implement snapshot copy between repositories with new permission model ([8df857d](https://github.com/alexdjachenko/phpResticAdmin/commit/8df857d64913b012402ebc164a210ed1a3ce427d))


### Bug Fixes

* --from-insecure-no-password ([071e62b](https://github.com/alexdjachenko/phpResticAdmin/commit/071e62bf65b99f19b4054037473f28aaf9469996))
* App\Tests\Unit\Auth\AuthenticatorTest::testLegacyAdminHasNoUseWrite ([8bf5e3c](https://github.com/alexdjachenko/phpResticAdmin/commit/8bf5e3cfee4f0b95187a7d78bbbb997d30b68bb8))
* **SnapshotService:** add --from-insecure-no-password after "copy" subcommand ([4d74a2c](https://github.com/alexdjachenko/phpResticAdmin/commit/4d74a2ca202b56ba2966248418cb86bca8ea9cbc))

## [0.4.8](https://github.com/alexdjachenko/phpResticAdmin/compare/v0.4.7...v0.4.8) (2026-08-10)


### Bug Fixes

* debugging release workflow ([7039788](https://github.com/alexdjachenko/phpResticAdmin/commit/7039788d49102f4185419d73c2678d061ce737ae))

## [0.4.7](https://github.com/alexdjachenko/phpResticAdmin/compare/v0.4.6...v0.4.7) (2026-08-10)


### Bug Fixes

* next trying to fix image tags ([6b7fdc4](https://github.com/alexdjachenko/phpResticAdmin/commit/6b7fdc405c295bb8863710fd16daf5136ef15c17))

## [0.4.6](https://github.com/alexdjachenko/phpResticAdmin/compare/v0.4.5...v0.4.6) (2026-08-09)


### Bug Fixes

* пытаюсь наладить билдер ([f957ec1](https://github.com/alexdjachenko/phpResticAdmin/commit/f957ec14569579037c0b0791ab84a90f39005ae8))

## [0.4.5](https://github.com/alexdjachenko/phpResticAdmin/compare/v0.4.4...v0.4.5) (2026-08-09)


### Bug Fixes

* опечатка в тегах сборки ([afccf8a](https://github.com/alexdjachenko/phpResticAdmin/commit/afccf8ae35fdec40cbdd8194514eb7c3016d0324))

## [0.4.4](https://github.com/alexdjachenko/phpResticAdmin/compare/v0.4.3...v0.4.4) (2026-08-09)


### Bug Fixes

* очередная попытка добиться тега на релизном образе ([851fd12](https://github.com/alexdjachenko/phpResticAdmin/commit/851fd12b5b674b54a68fc4be570c0a413875b0a0))
* прошлым фиксом поломал сборку совсем ([f0cf22c](https://github.com/alexdjachenko/phpResticAdmin/commit/f0cf22c442ec87d02b7bbc09674745bc62e5b030))
* пытаюсь починить теперь сборку :) ([420509a](https://github.com/alexdjachenko/phpResticAdmin/commit/420509a133a347578e46b0772ae5da97cd3b3a22))

## [0.4.3](https://github.com/alexdjachenko/phpResticAdmin/compare/v0.4.2...v0.4.3) (2026-08-09)


### Bug Fixes

* еще одна попытка добиться авто-проставления тегов релизова ([9743619](https://github.com/alexdjachenko/phpResticAdmin/commit/974361986b6557d26bdcf472b7549c585777ab4e))

## [0.4.2](https://github.com/alexdjachenko/phpResticAdmin/compare/v0.4.1...v0.4.2) (2026-08-09)


### Bug Fixes

* вторая попытка добиться, чтобы образ метился тегом релиза ([c392095](https://github.com/alexdjachenko/phpResticAdmin/commit/c392095043dd761e20440fef2920cb010f703526))

## [0.4.1](https://github.com/alexdjachenko/phpResticAdmin/compare/v0.4.0...v0.4.1) (2026-08-09)


### Bug Fixes

* пытаюсь добиться простановки тегов для релизов и не поламать сборки и теги для pr ([0d320d1](https://github.com/alexdjachenko/phpResticAdmin/commit/0d320d125fff1ac82fff537c8c2fbd4aded0f01b))

## [0.4.0](https://github.com/alexdjachenko/phpResticAdmin/compare/v0.3.0...v0.4.0) (2026-08-09)


### Features

* **stage5:** export, maintenance, keys, copyrights, and comprehensiv… ([0a9b930](https://github.com/alexdjachenko/phpResticAdmin/commit/0a9b930e2f41c59b886a3f7f59bd782904669713))
* **stage5:** export, maintenance, keys, copyrights, and comprehensive tests ([d29dc7e](https://github.com/alexdjachenko/phpResticAdmin/commit/d29dc7e11988ebd76ebbde063df401788fbbd9dd))


### Bug Fixes

* fixed skipping autotest ([207e938](https://github.com/alexdjachenko/phpResticAdmin/commit/207e938dd0189d511a8997cb9ced37fe08defc0a))
* **restic:** update snapshot ID after tag operations ([11bc098](https://github.com/alexdjachenko/phpResticAdmin/commit/11bc098a0a6cb757c1b8f41ef6433a4f5007460b))
* **restic:** use --add=/--remove=&lt;tag&gt; syntax for tag operations ([7969a2e](https://github.com/alexdjachenko/phpResticAdmin/commit/7969a2e85de79f7dabfc7886357dbec5c2689f5f))
* **stage5:** call restic stats per-snapshot, not multi-ID ([6694a5f](https://github.com/alexdjachenko/phpResticAdmin/commit/6694a5fbc165ac02727a6cd0034c0f1b7bc97a8b))
* **stage5:** normalize IDs to short form before comparing in stats tests ([8ae25cd](https://github.com/alexdjachenko/phpResticAdmin/commit/8ae25cd44ce1158bfe1f2864b010c5ef9071e962))
* **stage5:** relax stats assertions, fix tag test, and improve maintenance UX ([da784ce](https://github.com/alexdjachenko/phpResticAdmin/commit/da784ce7f259f122c3311efee8822b105a830563))
* **stage5:** resolve 12 test failures and 3 production bugs found in CI ([8876178](https://github.com/alexdjachenko/phpResticAdmin/commit/88761789dcaf7b214d557bcda330be6eea1a8443))
* **stage5:** resolve remaining integration test failures from restic 0.19 ([73a79a7](https://github.com/alexdjachenko/phpResticAdmin/commit/73a79a758b171e0817259d90ef815b1360194ec0))
* **stage5:** restic 0.19 tag expects snapshot ID before --add/--remove flags ([90075a5](https://github.com/alexdjachenko/phpResticAdmin/commit/90075a5b7e227a7d9877bf9604e42a2034167b44))
* **stage5:** three root-cause fixes for remaining integration test failures ([87c7272](https://github.com/alexdjachenko/phpResticAdmin/commit/87c72727bd7e96ad31cc7469995a9cc56719ff41))
* **stage5:** use decoded[0] pattern from SnapshotService for restic stats parsing ([1a3b5cb](https://github.com/alexdjachenko/phpResticAdmin/commit/1a3b5cb0504911e10c835e52e8097cf04da3c4db))
* testAddAndRemoveTag ([558966f](https://github.com/alexdjachenko/phpResticAdmin/commit/558966ff3098532f7fb90c474b0c61599c51a8ee))

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
