# Contributing

This repository holds the DMARCo backend: the Symfony API, the workers and the
report processing pipeline. Pull requests for that code belong here.

**Issues belong in [`dmarcoapp/dmarcoapp`](https://github.com/dmarcoapp/dmarcoapp/issues),**
together with every other DMARCo issue, so nobody has to guess which component
a problem comes from.

The full contribution guide lives in the main repository:
[`CONTRIBUTING.md`](https://github.com/dmarcoapp/dmarcoapp/blob/main/CONTRIBUTING.md).

Before you push, run what CI runs:

```bash
vendor/bin/phpunit
vendor/bin/php-cs-fixer fix --dry-run --diff
bin/console cache:warmup && vendor/bin/psalm
```

Coding standards for this repository are in [`AGENTS.md`](AGENTS.md). Generate
migrations only with `bin/console make:migration`.
