export default {
  extends: ['@commitlint/config-conventional'],
  rules: {
    'scope-empty': [2, 'never'],
    'scope-enum': [
      2,
      'always',
      [
        'models',
        'website',
        'media',
        'admin',
        'sandbox',
        'starter',
        'monorepo',
        'ci',
        'deps',
        'deps-dev',
        'release',
      ],
    ],
    // Squash-merged PR bodies and bot commits (e.g. Dependabot, semantic-release) can be long.
    'body-max-line-length': [0],
    'footer-max-line-length': [0],
  },
  ignores: [
    // semantic-release changelog commits: "chore(release): 13.1.0 [skip ci]"
    (message) => /^chore\(release\): .* \[skip ci\]/.test(message),
  ],
};
