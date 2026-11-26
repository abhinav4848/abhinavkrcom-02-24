# Legacy PHP projects

These copies use PHP 8.4 with PDO SQLite. Smart Search's importer also uses PHP's DOM extension. Preview the apps locally with the included router. Projects and the homepage publish through separate GitHub Actions workflows.

## Fresh installation

From a new checkout, run:

```sh
python scripts/init-project-databases.py
```

The script creates all ten empty SQLite databases from the checked-in `schemas/` files and adds missing local `.env` settings. Run it again if needed. Existing records and settings are retained. To use a different data folder, pass `--data-dir /path/to/private-data` and set `LEGACY_PROJECT_DATA` to that directory for PHP.

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

`private-data/databases/` holds ten one-file SQLite databases. The databases and live configuration are ignored by Git. Lexicon and Media attachments use the applications' existing ignored `files/` and `storage/` paths. Back up attachments separately from their databases. Saturnalia has a database and sample configuration only. Its PHP source was absent from the reviewed folders.

EDFED's optional administrator override uses `ADMIN_OVERRIDE` in its private `.env` and defaults to disabled. Diary and Twitter keep support for their old password hashes. New signups use PHP's password hashing API.

## Database backups

To back up a database while it is open, use the SQLite backup API or the SQLite command-line shell's `.backup` command. Restore a backup into a new directory, then point the appropriate configuration at the restored file. Restore uploaded attachments alongside their database records.

## Staging deployment

The **Publish personal projects** workflow updates the existing password-protected `public_html/projects/` folder. It runs the initializer against `projects-data/` beside the web root. The workflow uploads committed app files and schema definitions. Existing SQLite files, `.env` settings, server access settings and attachments stay in place. The same hosting settings and deployment key used for the homepage also serve this workflow.

On a new host, set up the protected projects folder first and set Apache's `LEGACY_PROJECT_DATA` environment variable to the absolute path of your private `projects-data` directory. Then run the workflow. To move existing records or attachments to a new host, restore them separately from private backups.

In Smart Search, use **Export engines** to download the engine settings and **Import engines** to restore them. The import accepts older exports too and skips matching engine identifiers.
