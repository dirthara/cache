# PSR compliance tests

This isolated Composer project runs the unmodified `cache/integration-tests` 1.0.5
PSR-6 and PSR-16 suites with PHPUnit 12 and PSR interface packages 3.x. Its
lockfile pins the test environment. The package's normal development dependency
remains PHPUnit 13; the two autoloaders run in separate PHP processes.

From the repository root:

```sh
docker compose exec -T php composer --working-dir=tooling/psr install --no-interaction --no-progress
docker compose exec -T php composer --working-dir=tooling/psr validate --strict
docker compose exec -T php composer test-psr
```

`composer ci` includes `test-psr`, so install both Composer projects before running
it. CI installs and checks this environment explicitly. No tests are skipped or
modified. The pool fixtures share one memory store within each test so a new pool
can observe destructor commits. A real clock lets the upstream expiry tests
exercise elapsed time.

The external suites cover keys and invalid arguments, scalar/null/object and
binary round trips, expiry and non-positive TTL deletion, deferred visibility and
overwrite, destructor commit, and clear/delete behaviour. The normal PHPUnit 13
suite adds Dirthara's regression cases for numeric strings `123`, `0`, and `001`,
unserialisable expired replacements, retry after persistence failure, foreign
pool exceptions, and incomplete or corrupt object graphs.

The integration suite has no runtime role and is excluded from release archives.
