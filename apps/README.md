# Legacy PHP projects

These copies use PHP 8.4 with PDO SQLite. Preview them locally with the included router. The homepage workflow publishes only `site/`.

From the repository in PowerShell, use the downloaded local PHP runtime if present:

```powershell
& .deploy/php-runtime/php.exe -n -d extension_dir=.deploy/php-runtime/ext -d extension=pdo_sqlite -d extension=mbstring -d extension=gd -S 127.0.0.1:8001 -t apps scripts/preview-legacy-apps.php
```

With PHP already installed and those extensions enabled, run:

```sh
php -S 127.0.0.1:8001 -t apps scripts/preview-legacy-apps.php
```

Open <http://127.0.0.1:8001/fuel/> to start. Other routes include `/search/`, `/lexicon/`, `/edfed/`, `/patients/`, `/patients-legacy/`, `/media/`, `/twitter/`, `/diary/`, `/learning/210-mini-signup.php`, and `/learning-names/`.

Keep these previews on loopback. The original projects include old authentication, file upload and session code and would need a separate review before being exposed on a public server.

## Configuration and data

Copy the project's `.env.example` to `private-data/config/<database-name>/.env` if setting up another checkout. Set `DB_DATABASE` to an absolute path or a path relative to that `.env` file. On a server, use a private directory outside its document root and set the PHP process environment variable `LEGACY_PROJECT_DATA` to that directory. Both patient versions use `config/patients/.env`. The learning pages and names example share `config/learning/.env`.

`private-data/imports/` holds the unchanged SQL export. `private-data/databases/` holds ten one-file SQLite databases. Both locations and the live configuration are ignored by Git. Lexicon and Media attachments use the applications' existing ignored `files/` and `storage/` paths. Back up attachments separately from their databases. Saturnalia has a database and sample configuration only. Its PHP source was absent from the reviewed folders.

EDFED's optional administrator override uses `ADMIN_OVERRIDE` in its private `.env` and defaults to disabled. Diary and Twitter keep support for their old password hashes. New signups use PHP's password hashing API. The SQL export and existing password fields are kept as private migration data.

## Rebuild from the SQL export

```sh
python scripts/import-legacy-databases.py private-data/imports/spsdafxt_users.sql private-data/restored
```

The importer compares each converted row with the source and runs an SQLite integrity check. It preserves table and column names, primary IDs, unique indexes and the next auto-increment values. The diary's two duplicate MySQL full-text indexes are omitted because its code looks up individual records. SQLite's built-in case-insensitive comparisons differ from MySQL's for some accented characters.

Import into a new directory, then point the appropriate configuration at the resulting file. To back up a database while it is open, use the SQLite backup API or the SQLite command-line shell's `.backup` command.
