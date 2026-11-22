"""Convert the phpMyAdmin export into per-project SQLite snapshots."""

import argparse
from contextlib import closing
from decimal import Decimal
from pathlib import Path
import re
import shutil
import sqlite3
import tempfile


GROUPS = {
    "fuel": ("fuel_data",),
    "search": ("searcher_*",),
    "lexicon": ("mbbs_*",),
    "edfed": ("cutabove", "cutabove_*"),
    "patients": ("dhruv_*",),
    "media": ("media_*",),
    "twitter": ("twitter_*",),
    "diary": ("secdiary_users",),
    "saturnalia": ("saturnalia_*",),
    "learning": ("names", "users"),
}
ESCAPES = dict(zip("0bnrtZ", "\0\b\n\r\t\x1a"))
QUOTED = r"'(?:[^'\\]|\\.|'')*'"
SQL_TOKEN = re.compile(
    r"--[^\n]*|/\*.*?\*/|" + QUOTED + r'|"(?:[^"\\]|\\.|"")*"|`[^`]+`|;',
    re.S,
)
VALUE = re.compile(QUOTED + r"|NULL\b|[-+]?\d+(?:\.\d+)?(?:[eE][-+]?\d+)?", re.S | re.I)
IDENTIFIER = r"`([^`]+)`"


def statements(source):
    """Split on semicolons outside MySQL literals, skipping dump comments."""
    start = 0
    parts = []
    for token in SQL_TOKEN.finditer(source):
        value = token.group()
        if value.startswith(("--", "/*")):
            parts.append(source[start:token.start()])
            start = token.end()
        elif value == ";":
            parts.append(source[start:token.start()])
            statement = "".join(parts).strip()
            if statement:
                yield statement
            start = token.end()
            parts.clear()
    if ("".join(parts) + source[start:]).strip():
        raise ValueError("The export ends with an incomplete statement.")


def literal(token):
    if token.startswith("'"):
        return re.sub(
            r"''|\\(.)",
            lambda match: "'" if match.group() == "''" else
            ESCAPES.get(match[1], match.group() if match[1] in "%_" else match[1]),
            token[1:-1],
            flags=re.S,
        )
    if token.upper() == "NULL":
        return None
    return Decimal(token) if any(marker in token.lower() for marker in ".e") else int(token)


def rows(values):
    position = 0
    while position < len(values):
        opening = re.compile(r"\s*\(\s*").match(values, position)
        if not opening:
            raise ValueError("Expected an INSERT tuple.")
        position = opening.end()
        row = []
        while True:
            token = VALUE.match(values, position)
            if not token:
                raise ValueError("Unsupported INSERT value.")
            row.append(literal(token.group()))
            delimiter = re.compile(r"\s*([,)])\s*").match(values, token.end())
            if not delimiter:
                raise ValueError("Unterminated INSERT value.")
            position = delimiter.end()
            if delimiter[1] == ")":
                break
        yield tuple(row)
        if position < len(values):
            if values[position] != "," or not values[position + 1:].strip():
                raise ValueError("Expected another INSERT tuple.")
            position += 1


def identifier(name):
    return '"' + name.replace('"', '""') + '"'


def group_for(name):
    matches = [
        group for group, patterns in GROUPS.items()
        if any(name.startswith(p[:-1]) if p.endswith("*") else name == p for p in patterns)
    ]
    if len(matches) != 1:
        raise ValueError("Each table must belong to exactly one project.")
    return matches[0]


def read_dump(source):
    schemas, inserts, keys, uniques, sequences = {}, {}, {}, {}, {}
    for statement in statements(source):
        create = re.fullmatch(r"CREATE TABLE " + IDENTIFIER + r"\s*\(\n(.*?)\n\) ENGINE=\w+ .*", statement, re.S)
        insert = re.fullmatch(r"INSERT INTO " + IDENTIFIER + r"\s*\((.*?)\) VALUES\s*(.*)", statement, re.S)
        alter = re.fullmatch(r"ALTER TABLE " + IDENTIFIER + r"\s+(.*)", statement, re.S)
        if create:
            name = create[1]
            if name in schemas:
                raise ValueError("Duplicate table definition.")
            columns = []
            for definition in create[2].splitlines():
                column = re.fullmatch(
                    r"\s*" + IDENTIFIER
                    + r" (tinyint|bigint|int|text|longtext|varchar|date|datetime|float)"
                    + r"(?:\(\d+(?:,\d+)?\))?(?: UNSIGNED)?( NOT NULL)?"
                    + r"(?: DEFAULT (NULL|'[01]'))?(?: COMMENT " + QUOTED + r")?,?",
                    definition,
                )
                if not column:
                    raise ValueError(f"Unsupported column definition in {name}.")
                columns.append(column.groups())
            schemas[name] = columns
        elif insert:
            cols = re.findall(IDENTIFIER, insert[2])
            if ", ".join(f"`{col}`" for col in cols) != insert[2]:
                raise ValueError("Unsupported INSERT column list.")
            inserts.setdefault(insert[1], []).append((cols, insert[3]))
        elif alter:
            name = alter[1]
            for action in alter[2].split(","):
                action = action.strip()
                key = re.fullmatch(r"ADD PRIMARY KEY \(" + IDENTIFIER + r"\)", action)
                unique = re.fullmatch(r"ADD UNIQUE KEY " + IDENTIFIER + r" \(" + IDENTIFIER + r"\)", action)
                sequence = re.fullmatch(r"MODIFY " + IDENTIFIER + r" int\(\d+\) NOT NULL AUTO_INCREMENT", action)
                next_id = re.fullmatch(r"AUTO_INCREMENT=(\d+)", action)
                if key:
                    keys[name] = key[1]
                elif unique:
                    uniques.setdefault(name, []).append(unique.groups())
                elif sequence and keys.get(name) == sequence[1]:
                    sequences[name] = 1
                elif next_id and name in sequences:
                    sequences[name] = int(next_id[1])
                elif name == "secdiary_users" and re.fullmatch(
                    r"ADD FULLTEXT KEY `diary(?:_2)?` \(`diary`\)", action
                ):
                    # The PHP diary does direct lookups and does not query either full-text index.
                    continue
                else:
                    raise ValueError(f"Unsupported index or sequence in {name}.")
        elif not re.fullmatch(r'SET (?:SQL_MODE|time_zone) = "[^"]*"|START TRANSACTION|COMMIT', statement):
            raise ValueError("Unexpected statement in the export.")
    if not schemas or set(schemas) != set(keys) or set(schemas) != set(sequences):
        raise ValueError("Missing table definitions, primary keys or sequences.")
    if (set(inserts) | set(uniques)) - set(schemas):
        raise ValueError("The export references an undefined table.")
    return schemas, inserts, keys, uniques, sequences


def affinity(mysql_type):
    if mysql_type.endswith("int"):
        return "INTEGER"
    return "REAL" if mysql_type == "float" else "TEXT"


def converted(value, storage):
    if value is None:
        return None
    if storage == "INTEGER":
        result = int(value)
        if isinstance(value, Decimal) and result != value:
            raise ValueError("A fractional value cannot be stored as an integer.")
        if not -(2**63) <= result < 2**63:
            raise ValueError("An integer is outside SQLite's signed integer range.")
        return result
    if storage == "REAL":
        return float(value)
    return str(value)


def convert(source, destination):
    schemas, inserts, keys, uniques, sequences = read_dump(source)
    membership = {table: group_for(table) for table in schemas}
    if set(membership.values()) != set(GROUPS):
        raise ValueError("At least one expected project is absent from the export.")
    targets = {group: destination / f"{group}.sqlite" for group in GROUPS}
    if any(path.exists() for path in targets.values()):
        raise FileExistsError("An output database already exists. Choose a new output folder.")
    destination.mkdir(parents=True, exist_ok=True)
    totals = {}
    with tempfile.TemporaryDirectory(dir=destination) as working:
        for group, target in targets.items():
            tables = [name for name, owner in membership.items() if group == owner]
            count = 0
            with closing(sqlite3.connect(Path(working) / target.name)) as db, db:
                for name in tables:
                    table = identifier(name)
                    key = keys[name]
                    columns = schemas[name]
                    types = {col: affinity(mysql_type) for col, mysql_type, _, _ in columns}
                    definitions = []
                    for col, _, required, default in columns:
                        definition = f"{identifier(col)} {types[col]}"
                        if col == key:
                            definition += " PRIMARY KEY AUTOINCREMENT"
                        else:
                            # SQLite carries the imported text without MySQL's latin1 restrictions.
                            if types[col] == "TEXT":
                                definition += " COLLATE NOCASE"
                            definition += required or ""
                            if default is not None:
                                definition += " DEFAULT " + default
                        definitions.append(definition)
                    db.execute(f"CREATE TABLE {table} ({', '.join(definitions)})")
                    for index, col in uniques.get(name, []):
                        db.execute(f"CREATE UNIQUE INDEX {identifier(name + '_' + index)} ON {table} ({identifier(col)})")
                    original = {}
                    schema_order = list(types)
                    for cols, payload in inserts.get(name, []):
                        if set(cols) != set(types) or len(cols) != len(types):
                            raise ValueError(f"Unexpected INSERT columns in {name}.")
                        values = []
                        for row in rows(payload):
                            if len(row) != len(cols):
                                raise ValueError(f"Unexpected row width in {name}.")
                            typed = tuple(converted(value, types[col]) for col, value in zip(cols, row))
                            by_column = dict(zip(cols, typed))
                            if by_column[key] in original:
                                raise ValueError(f"Duplicate source key in {name}.")
                            original[by_column[key]] = tuple(by_column[col] for col in schema_order)
                            values.append(typed)
                        columns_sql = ", ".join(map(identifier, cols))
                        placeholders = ", ".join("?" for _ in cols)
                        db.executemany(f"INSERT INTO {table} ({columns_sql}) VALUES ({placeholders})", values)
                    key_index = schema_order.index(key)
                    actual = {row[key_index]: row for row in db.execute(f"SELECT * FROM {table}")}
                    if actual != original:
                        raise ValueError(f"Converted rows differ from the source in {name}.")
                    count += len(actual)
                    high_water_mark = max(sequences[name] - 1, max(original, default=0))
                    db.execute("DELETE FROM sqlite_sequence WHERE name = ?", (name,))
                    db.execute("INSERT INTO sqlite_sequence (name, seq) VALUES (?, ?)", (name, high_water_mark))
                if db.execute("PRAGMA integrity_check").fetchall() != [("ok",)]:
                    raise ValueError(f"SQLite integrity check failed for {group}.")
            totals[group] = (len(tables), count)
        created = []
        try:
            for target in targets.values():
                # Exclusive creation protects databases made by another import.
                with target.open("xb") as output:
                    created.append(target)
                    with (Path(working) / target.name).open("rb") as staged:
                        shutil.copyfileobj(staged, output)
        except BaseException:
            for target in created:
                target.unlink()
            raise
    return totals


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("export", type=Path)
    parser.add_argument("destination", type=Path)
    args = parser.parse_args()
    try:
        totals = convert(args.export.read_text(encoding="utf-8-sig"), args.destination)
    except (ValueError, OSError, sqlite3.Error) as error:
        # Database errors can include record values. Keep them out of shared logs.
        parser.exit(1, f"Import stopped ({type(error).__name__}).\n" if isinstance(error, sqlite3.Error)
                    else f"Import stopped: {error}\n")
    for group, (tables, count) in totals.items():
        print(f"{group}.sqlite: {tables} tables, {count} verified rows")


if __name__ == "__main__":
    main()
