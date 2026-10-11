# KusikusiCMS Starter

> **Read-only split.** This repository is automatically split from [`apps/starter`](https://github.com/kusikusicms/monorepo/tree/main/apps/starter) in the [kusikusicms/monorepo](https://github.com/kusikusicms/monorepo). Please open issues and pull requests in the monorepo; pull requests sent here are closed automatically.

The starter template for [KusikusiCMS](https://github.com/kusikusicms/monorepo): a ready-to-run Laravel 13 application with the [`kusikusicms/models`](https://github.com/kusikusicms/models), [`kusikusicms/website`](https://github.com/kusikusicms/website), [`kusikusicms/media`](https://github.com/kusikusicms/media) and [`kusikusicms/admin`](https://github.com/kusikusicms/admin) packages already installed and wired together, so you can start managing content right away instead of assembling the packages yourself.

## Creating a new project

```bash
composer create-project kusikusicms/starter my-site
cd my-site
```

`composer create-project` automatically copies `.env.example` to `.env`, generates the application key and runs the initial migrations (see the `post-create-project-cmd` scripts in `composer.json`). By default it uses SQLite, so there is nothing else to configure to get started.

Build the front-end assets and start the app:

```bash
npm install
npm run build
php artisan serve
```

Open `http://localhost:8000` and sign in to `/admin` to start creating content.

## What's included

| Package | Purpose |
| --- | --- |
| [`kusikusicms/models`](https://github.com/kusikusicms/models) | Core Eloquent models (`Entity`, contents, relations) and domain events |
| [`kusikusicms/website`](https://github.com/kusikusicms/website) | Public-facing website/router manager |
| [`kusikusicms/media`](https://github.com/kusikusicms/media) | Media library and file management |
| [`kusikusicms/admin`](https://github.com/kusikusicms/admin) | Admin UI for managing content |

Each package publishes its own configuration under `config/kusikusicms/<package>.php` and its own migrations. To customize a package (for example, to change the admin route prefix or disable a package's routes), publish its config:

```bash
php artisan vendor:publish --tag=kusikusicms-config
# or a single package
php artisan vendor:publish --tag=kusikusicms-admin-config
```

## Configuring the database

The starter ships with SQLite for a zero-configuration start. To use MySQL/PostgreSQL instead, update `.env` before running migrations:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=my_site
DB_USERNAME=root
DB_PASSWORD=
```

```bash
php artisan migrate
```

## Development

```bash
composer test   # run the test suite
composer lint   # fix code style with Pint (if configured)
npm run dev     # Vite dev server with hot reload
```

## Upgrading

New versions of the packages are released independently from this starter. Once published, update them like any other Composer dependency:

```bash
composer update kusikusicms/models kusikusicms/website kusikusicms/media kusikusicms/admin
php artisan migrate
```

## Getting help

- [KusikusiCMS monorepo](https://github.com/kusikusicms/monorepo) — source, issues and contributing guide for every package and this starter.
- [Laravel documentation](https://laravel.com/docs) — for everything that isn't KusikusiCMS-specific (routing, Eloquent, queues, etc.).

## Security

If you discover a security vulnerability, please follow the instructions in [SECURITY.md](https://github.com/kusikusicms/monorepo/blob/main/SECURITY.md) instead of opening a public issue.

## License

MIT License © Cuatromedios SC. See [LICENSE](https://github.com/kusikusicms/monorepo/blob/main/LICENSE).
