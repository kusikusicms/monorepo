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
4. **`SPLIT_TOKEN` secret**: a fine-grained personal access token with *Contents: write* and *Workflows: write* on the split repositories only (`models`, `website`, `media`, `admin`, `starter`), or an App token with the same scope. *Workflows: write* (classic PATs: the `workflow` scope) is required because the split repositories ship `.github/workflows/close-pull-request.yml`; without it GitHub rejects the push with `refusing to allow a Personal Access Token to create or update workflow ... without workflow scope`. The default `GITHUB_TOKEN` cannot push to other repositories.
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

1. Create `kusikusicms/starter` (and any future app repository). Make sure `models`, `website`, `media` and `admin` exist, each with a `main` branch.
2. In each split repository: set the description to "Read-only split of kusikusicms/monorepo", disable Issues, Wiki and Projects. Pull requests are closed automatically by the `close-pull-request.yml` workflow shipped from the monorepo.

## 3. Packagist

1. Submit each repository: `kusikusicms/models`, `website`, `media`, `admin` and, when ready, `starter`.
2. Enable the GitHub integration (webhook) so Packagist updates automatically when tags are pushed. `type: project` packages such as the starter work with `composer create-project`.
3. Clean up or abandon old `v1.x` versions if needed. No `12.x` line was ever published.
4. Publish the four packages first (baseline `v13.0.0`). Only then set `"publish": true` for `starter` in `monorepo.json`, because the starter requires `kusikusicms/* ^13.0`.

## 4. AI code review (optional)

- **GitHub Copilot**: the Free plan does **not** include code review. Automatic review on every PR (ruleset → *Automatically request Copilot code review*) needs Copilot Pro, Pro+, Business or Enterprise. Copilot Pro is free for verified students, teachers and maintainers of popular open-source projects; check eligibility at github.com/settings/copilot.
- **Free alternatives**: CodeRabbit (free for public repositories) and Qodo Merge (free tier). Both are installed as GitHub Apps outside the repository.

## 5. First release checklist

1. All checks above are in place and the `v13.0.0` baseline is pushed and split.
2. Run **Release** with `dry_run` enabled and check the computed version and notes in the log.
3. Run **Release** without `dry_run`.
4. Confirm the new tag and versions on the split repositories and Packagist.
