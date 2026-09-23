# Terravue – Travel Risk Monitor (working name)

WordPress plugin + PWA: official travel advice (UK FCDO, Auswärtiges Amt, Dutch MFA) and recent security
news for the countries you follow. English UI by default, with German and Dutch.

- Plugin: [`travel-risk/`](travel-risk/) — see [`travel-risk/readme.txt`](travel-risk/readme.txt) for installation.
- Build the upload zip: `bin/build-zip.sh` → `dist/travel-risk-<version>.zip`
- Tests: `php travel-risk/tests/unit.php`, and `wp eval-file .../tests/wp-smoke.php` in a test WordPress.
- Project notes and backlog: [`CLAUDE.md`](CLAUDE.md)
