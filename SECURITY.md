# Security Policy

## Supported versions

| Branch | Releases | Status |
| --- | --- | --- |
| `0.1` | 0.1.x | Active |

While the package is pre-1.0, only the latest release line receives fixes.

## Reporting a vulnerability

Report vulnerabilities privately using GitHub's
[Report a vulnerability](https://github.com/dirthara/cache/security/advisories/new)
form. Do not disclose vulnerabilities in public issues or pull requests.

Include the affected version or commit, PHP version, a minimal reproduction,
and the impact and conditions needed to trigger the issue. Maintainers will
acknowledge and assess the report. Confirmed fixes are published with an
advisory crediting the reporter unless they prefer otherwise.

## Scope

Report security issues in the cache pool, simple cache adapter, drivers,
serialisers, exception handling, or development configuration.

The native serialiser trusts cached payloads and restores PHP objects, including
running their restoration hooks. Use it only with stores protected from
untrusted writers; see [serialisation](docs/serialisation.md). Rejecting malformed
payloads or missing classes does not make native unserialisation safe for
untrusted data. Shared backend access controls belong to the driver and the
application.

Bugs in PHP or third-party dependencies should also be reported upstream.
Application code and the sensitivity of data an application chooses to store
are the application's responsibility.
