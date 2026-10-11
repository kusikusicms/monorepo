# Security Policy

## Supported versions

Only the latest `13.x` release (aligned with Laravel 13) receives security fixes.

| Version | Supported |
| ------- | --------- |
| 13.x    | Yes       |
| < 13    | No        |

## Reporting a vulnerability

**Please do not open a public issue for security vulnerabilities.**

Report them privately through GitHub: go to the [Security tab](https://github.com/kusikusicms/monorepo/security/advisories/new) of the [kusikusicms/monorepo](https://github.com/kusikusicms/monorepo) repository and choose **Report a vulnerability**. The split repositories (`kusikusicms/models`, `website`, `media`, `admin`, `starter`) are read-only; always report in the monorepo.

Please include:

- the affected package and version,
- steps to reproduce or a proof of concept,
- the impact you expect.

You can expect an acknowledgement within a few days. Once a fix is ready we will release a patched version and publish a security advisory crediting the reporter, unless you prefer to stay anonymous.

## Automated checks

The repository runs Dependabot, `composer audit`, `npm audit`, dependency review on pull requests, CodeQL for the workflow files and OpenSSF Scorecard.
