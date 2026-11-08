# Repository setup checklist

Everything that cannot live in the repository files and must be configured on GitHub and Packagist for the release pipeline to work. See [CONTRIBUTING.md](../CONTRIBUTING.md) for the day-to-day workflow.

## 1. GitHub — monorepo `kusikusicms/monorepo`

1. **Settings → General → Pull Requests**
   - Allow only *squash merging*.
   - Default commit message: *Pull request title* (or title + description).
   - Enable *Always suggest updating pull request branches* and *Automatically delete head branches*.
2. **Settings → Rules → Rulesets → `main`**
   - Require a pull request.
   - Require linear history.
   - Block force pushes.
   - Required status checks: `CI`, `PR Title` (job *Validate PR title (Conventional Commits)*), `commitlint`, `dependency-review`.
   - Bypass list: the release GitHub App only.
3. **Create a GitHub App** (for example `kusikusicms-release`) with *Contents: write*. Install it on the monorepo and store its credentials as repository secrets:
   - `RELEASE_APP_ID`
   - `RELEASE_APP_PRIVATE_KEY`
4. **`SPLIT_TOKEN` secret**: a fine-grained personal access token with *Contents: write* and *Workflows: write* on the split repositories only (`models`, `routes`, `media`, `admin`, `starter`), or an App token with the same scope. *Workflows: write* (classic PATs: the `workflow` scope) is required because the split repositories ship `.github/workflows/close-pull-request.yml`; without it GitHub rejects the push with `refusing to allow a Personal Access Token to create or update workflow ... without workflow scope`. The default `GITHUB_TOKEN` cannot push to other repositories.
5. Optional secrets `PACKAGIST_USERNAME` and `PACKAGIST_TOKEN` (API token from your Packagist profile) so the release workflow explicitly notifies Packagist. Without them, the Packagist GitHub hook is enough.
6. **Settings → Code security**: enable Dependabot alerts and security updates, secret scanning with push protection, private vulnerability reporting, and the dependency graph. CodeQL is managed by `.github/workflows/security.yml`.
7. **Settings → Actions → General**: default workflow permissions *Read repository contents*; do not allow GitHub Actions to create pull requests.
8. **One-time baseline** (the only existing tag is `v1.0.0`):

   ```bash
   git tag -a v13.0.0 -m "Baseline for Laravel 13" && git push origin v13.0.0
   ```

   Then run **Actions → Monorepo Split → Run workflow** with `tag: v13.0.0` so Packagist gets `13.0.0` for the packages. Future versions are created by the manual **Release** workflow.
9. **Dependabot lock file fix-ups**: `.github/workflows/dependabot-lockfile.yml` reuses the release GitHub App token to push a corrected `package-lock.json` on Dependabot's own npm pull requests, working around a known `dependabot-core` limitation (dependabot/dependabot-core#14110) that otherwise fails `commitlint` and `CI` with an `npm ci` "lock file out of sync" error. For pull requests opened before this workflow existed, comment `@dependabot recreate` on them so they pick it up.
   - **Required setup**: add `RELEASE_APP_ID` and `RELEASE_APP_PRIVATE_KEY` (same values as step 3) under **Settings → Secrets and variables → Dependabot** too. Runs triggered by `dependabot[bot]` on `pull_request` can only read Dependabot secrets, not Actions secrets.
   - It deliberately uses `pull_request`, not `pull_request_target`. Checking out PR code in a `pull_request_target` run (which has the base repository's secrets) is flagged by CodeQL as *Checkout of untrusted code in a privileged context*. PR-controlled values such as the branch name are passed through environment variables, never interpolated into `run:` scripts, to avoid CodeQL's *Code injection* alert.

## 2. GitHub — split repositories

1. Create `kusikusicms/starter` (and any future app repository). Make sure `models`, `routes`, `media` and `admin` exist, each with a `main` branch.
2. In each split repository: set the description to "Read-only split of kusikusicms/monorepo", disable Issues, Wiki and Projects. Pull requests are closed automatically by the `close-pull-request.yml` workflow shipped from the monorepo.

## 3. Packagist

1. Submit each repository: `kusikusicms/models`, `routes`, `media`, `admin` and, when ready, `starter`.
2. Enable the GitHub integration (webhook) so Packagist updates automatically when tags are pushed. `type: project` packages such as the starter work with `composer create-project`.
3. Clean up or abandon old `v1.x` versions if needed. No `12.x` line was ever published.
4. Publish `models`, `media` and `admin` with the baseline (`v13.0.0`). `routes` is not part of the baseline because `packages/routes` did not exist in `v13.0.0`, so `kusikusicms/routes` is first published with the next regular release (`v13.2.0`, see section 5). `routes` requires `kusikusicms/models ^13.0`, so the models package must already be published at `13.0.0` before the routes release. Only then set `"publish": true` for `starter` in `monorepo.json`, because the starter requires `kusikusicms/* ^13.0`.

## 4. AI code review (optional)

- **GitHub Copilot**: the Free plan does **not** include code review. Automatic review on every PR (ruleset → *Automatically request Copilot code review*) needs Copilot Pro, Pro+, Business or Enterprise. Copilot Pro is free for verified students, teachers and maintainers of popular open-source projects; check eligibility at github.com/settings/copilot.
- **Free alternatives**: CodeRabbit (free for public repositories) and Qodo Merge (free tier). Both are installed as GitHub Apps outside the repository.

## 5. First release checklist

1. All checks above are in place and the `v13.0.0` baseline is pushed and split.
2. Run **Release** with `dry_run` enabled and check the computed version and notes in the log.
3. Run **Release** without `dry_run`.
4. Confirm the new tag and versions on the split repositories and Packagist.
5. First release only: this release publishes `kusikusicms/routes` for the first time. Check that the `routes` split repository exists with a `main` branch, that its `SPLIT_TOKEN` access is in place, and that `kusikusicms/routes` is registered on Packagist. Then confirm the `routes` 13.2.0 version and that it resolves `kusikusicms/models` from Packagist. If the `routes` split fails, fix the external setup and re-run **Monorepo Split** with `tag: v13.2.0`.


## 6. Publishing a new package (reference for future packages)

Step-by-step guide for the manual actions outside the repository when a new package (or app) is added to the monorepo. Examples use a package called `<name>` (for instance `routes`). Do the external steps **before** merging anything that makes the split workflow run for it, otherwise the split job fails.

### 6.1 In the repository

1. Create `packages/<name>` with a valid `composer.json`: name `kusikusicms/<name>`, license, `php` constraint, `kusikusicms/* ^13.0` dependencies only on **published** packages, and the service provider under `extra.laravel.providers`.
2. Add `.gitattributes` (export-ignore `/tests`, `/docs`, `/phpunit.xml`, `/.gitattributes`), a `README.md` with an installation section and the "read-only split" notice, and `.github/workflows/close-pull-request.yml` (copy it from another package).
3. Register it in `monorepo.json` with `"split": true`. Use `"publish": true` only when its dependencies are already published (`publish` requires `split`). Keep `"publish": false` for apps such as the starter until their packages are on Packagist.
4. Validate: `composer validate --strict --no-check-lock --no-check-publish --working-dir=packages/<name>`, then the quality gate (`composer test`, `composer lint -- --test`, `composer analyse -- --no-progress`, `composer audit`).
5. Do not edit `split.yml` or `release.yml`: they read `monorepo.json`.

### 6.2 GitHub: create the split repository

1. Go to **github.com/organizations/kusikusicms/repositories/new** (or **New repository** in the organization).
2. Name: `<name>` (must equal `repository` in `monorepo.json`). Visibility: public. Description: `Read-only split of kusikusicms/monorepo`.
3. Create it **with an initial commit on `main`** (tick *Add a README file*, or push an empty commit). The split action pushes to `main` and needs the branch to exist. The first split replaces the content with the package files, so `composer.json` ends up at the repository root.
4. **Settings → General → Features**: disable *Issues*, *Wiki* and *Projects*. Do not enable Discussions.
5. Do not add branch protection that blocks pushes from the split token, and do not add workflows to this repository by hand; they ship from the monorepo.

### 6.3 GitHub: give `SPLIT_TOKEN` access

1. If `SPLIT_TOKEN` is a fine-grained PAT scoped to selected repositories: **github.com/settings/personal-access-tokens** → open the token → **Repository access** → add `kusikusicms/<name>`. Permissions must include *Contents: write* and *Workflows: write*. If the token is owned by an organization, an organization owner may need to approve the change.
2. If it is a GitHub App token: **Organization settings → GitHub Apps → Configure** the app and add the repository.
3. If the token expires, rotate it and update the secret in **monorepo → Settings → Secrets and variables → Actions → `SPLIT_TOKEN`**.
4. Nothing else changes in the monorepo secrets: `RELEASE_APP_ID`, `RELEASE_APP_PRIVATE_KEY` and the optional `PACKAGIST_*` secrets are shared by all projects.

### 6.4 Packagist: register the package

1. Sign in at **packagist.org** with the account that owns the `kusikusicms` vendor and click **Submit**.
2. Enter the repository URL `https://github.com/kusikusicms/<name>` and submit. Packagist reads the name (`kusikusicms/<name>`) from `composer.json`, so the split repository must already contain it (run the **Monorepo Split** workflow, or push to `main`, first).
3. Make sure updates are automatic, with one of these:
   - The GitHub integration/webhook (**packagist.org → Profile → Connect with GitHub**, then enable the hook for the package). Check **Settings → Webhooks** of the split repository.
   - The `PACKAGIST_USERNAME` and `PACKAGIST_TOKEN` secrets in the monorepo (already used by the release workflow for every `publish` project).
4. If the package has several maintainers, add them in the package page → **Maintainers**.
5. Packagist only shows versions created by tags. Until the first release, only `dev-main` exists.

### 6.5 First release and verification

1. Run **Actions → Release → Run workflow** with `dry_run` enabled. Check that the computed version is the expected one and that the new project appears in the *Publish* matrix (only `"publish": true` projects).
2. Run **Release** without `dry_run` from `main`.
3. Verify:
   - The split repository has the new tag, and `composer.json` is at its root.
   - Packagist shows the new version (use **Update** on the package page if it lags).
   - In an empty folder: `composer require kusikusicms/<name>` resolves its `kusikusicms/*` dependencies from Packagist.
4. Add the package name to the `SPLIT_TOKEN` scope list and the split repository list in sections 1 and 2 of this document, and to the Packagist list in section 3.

### 6.6 Troubleshooting

- **Split fails with `Repository not found` or `Permission denied`**: the repository does not exist, or `SPLIT_TOKEN` has no access to it (section 6.3).
- **`refusing to allow a Personal Access Token to create or update workflow`**: the token lacks *Workflows: write* (classic PAT: `workflow` scope).
- **Split fails because the `main` branch does not exist**: initialize the repository with a commit (section 6.2).
- **Release tagged the monorepo but the split failed**: fix the external setup, then run **Monorepo Split** manually with `tag: <vX.Y.Z>` to republish.
- **Packagist does not show the version**: check the webhook deliveries on the split repository, or trigger **Update** manually on Packagist.
- **`composer install` of a published package fails on a missing sibling**: the sibling is not published. The release workflow warns about this; publish it first.
