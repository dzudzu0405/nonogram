# Nonogram Studio

Picture-to-Nonogram puzzle book builder for KDP and Etsy sellers. PHP 8.1+ and MySQL backend for cPanel hosting; the puzzle engine (grid generation, clues, logic solver, uniqueness check, auto-fix, difficulty, duplicate fingerprint) and PDF export run in the browser. No AI, no external APIs. Sold as a one-time WarriorPlus funnel; each product unlocks features through `lib/entitlements.php`.

- Deploy to cPanel: see [HUONG-DAN-CAI-DAT.md](HUONG-DAN-CAI-DAT.md) (Vietnamese). Build the upload package with `python tools/build_zip.py`.
- Early single-file prototypes live in `prototypes/`.

## Run locally

```bash
cp config.sample.php config.php   # then set 'db' => ['driver' => 'sqlite', 'path' => __DIR__ . '/storage/dev.sqlite'] and a random app_key
php tests/seed-users.php          # creates the tables and the test accounts below
php -S 127.0.0.1:8080 dev-router.php
```

Open http://127.0.0.1:8080 and log in.

## Test accounts (local SQLite only)

Defined in [tests/dev-accounts.json](tests/dev-accounts.json). They exist only in your local `storage/dev.sqlite`; the live site gets its own admin from `install.php`.

| Email | Password | What it can do |
|---|---|---|
| admin@nonogram.test | c938b39036f6cde6 | Admin, every feature |
| fe@nonogram.test | b1e4428dbe583670 | FE buyer: black & white, grids up to 30 |
| nolicense@nonogram.test | d3639afef15a4580 | Logged in but no license (blocked) |

## Tests

```bash
node tests/engine.test.js   # puzzle engine
node tests/engine.fuzz.js   # engine vs brute force on generated puzzles (run after solver changes)
node tests/api.test.js      # API smoke test against the local server
```

Dashboard browser test: paste [tests/dashboard.e2e.js](tests/dashboard.e2e.js) into the DevTools console on the local site (instructions at the top of the file). Test cases and the latest results are in [tests/TEST-CASES-dashboard.md](tests/TEST-CASES-dashboard.md).
