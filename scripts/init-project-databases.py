"""Create missing project databases from the checked-in SQLite schemas."""

import argparse
from contextlib import closing
import os
from pathlib import Path
import sqlite3
import tempfile


def initialize_database(schema, database):
    if database.exists():
        return False
    with tempfile.TemporaryDirectory(dir=database.parent) as staging:
        candidate = Path(staging) / database.name
        with closing(sqlite3.connect(str(candidate))) as connection:
            connection.executescript(schema.read_text(encoding="utf-8"))
            if connection.execute("PRAGMA integrity_check").fetchone() != ("ok",):
                raise RuntimeError("Schema failed SQLite integrity check.")
        candidate.chmod(0o600)
        # The same filesystem permits an atomic create without replacing a live database.
        try:
            os.link(candidate, database)
        except FileExistsError:
            return False
    return True


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    root = Path(__file__).resolve().parent.parent
    parser.add_argument("--data-dir", type=Path, default=root / "private-data")
    args = parser.parse_args()
    sources = sorted((root / "schemas").glob("*.sql"))
    if not sources:
        parser.error("No SQLite schema files found.")
    data = args.data_dir
    data.mkdir(mode=0o700, parents=True, exist_ok=True)
    databases = data / "databases"
    databases.mkdir(mode=0o700, exist_ok=True)
    for schema in sources:
        name = schema.stem
        database = databases / (name + ".sqlite")
        created = initialize_database(schema, database)
        config_directory = data / "config" / name
        config_directory.mkdir(mode=0o700, parents=True, exist_ok=True)
        try:
            descriptor = os.open(str(config_directory / ".env"), os.O_WRONLY | os.O_CREAT | os.O_EXCL, 0o600)
        except FileExistsError:
            pass
        else:
            with os.fdopen(descriptor, "w", encoding="utf-8") as config:
                config.write("DB_CONNECTION=sqlite\nDB_DATABASE=../../databases/" + database.name + "\n")
        print(database.name + (": created" if created else ": kept existing"))


if __name__ == "__main__":
    main()
