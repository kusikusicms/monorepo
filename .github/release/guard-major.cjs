'use strict';

/**
 * semantic-release plugin that locks the major version to the Laravel major
 * configured as `laravelMajor` in monorepo.json. Releases never bump the major
 * automatically: moving to a new Laravel major is a deliberate, manual change.
 */
const fs = require('node:fs');
const path = require('node:path');

function readLaravelMajor(cwd) {
  const file = path.resolve(cwd || process.cwd(), 'monorepo.json');
  const config = JSON.parse(fs.readFileSync(file, 'utf8'));
  const major = Number(config.laravelMajor);
  if (!Number.isInteger(major) || major < 1) {
    throw new Error(`monorepo.json: "laravelMajor" must be a positive integer, got "${config.laravelMajor}".`);
  }
  return major;
}

function assertAllowedVersion(version, laravelMajor) {
  const actual = Number(String(version).split('.')[0]);
  if (actual !== laravelMajor) {
    throw new Error(
      `Release aborted: the next version ${version} does not belong to Laravel major ${laravelMajor} ` +
      `(monorepo.json "laravelMajor"). Make sure the v${laravelMajor}.0.0 baseline tag exists on main, ` +
      `and bump "laravelMajor" manually when moving to a new Laravel major.`
    );
  }
}

async function verifyRelease(pluginConfig, context) {
  const { nextRelease, cwd, logger } = context;
  const laravelMajor = readLaravelMajor(cwd);
  assertAllowedVersion(nextRelease.version, laravelMajor);
  logger.log('Version %s is allowed by the major lock (Laravel %s).', nextRelease.version, laravelMajor);
}

module.exports = { verifyRelease, readLaravelMajor, assertAllowedVersion };
