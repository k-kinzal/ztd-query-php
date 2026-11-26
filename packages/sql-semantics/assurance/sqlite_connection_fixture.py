"""Observe native attachments, open B-tree transactions and disposable file contents."""

import ctypes
from pathlib import Path

from run import require
from run_sqlite_create_table import quoted
from sqlite_connection_cases import initial_schemas
from sqlite_engine import Database
from sqlite_header_fixture import databases, rows


def create_marker(db, schema, value):
    name = quoted(schema)
    db.execute(f"CREATE TABLE {name}.marker(id); INSERT INTO {name}.marker VALUES ({value})")


def prepare(db, item):
    for schema, path, marker in initial_schemas(item):
        if schema in ("main", "temp"):
            create_marker(db, schema, item["seed"] + marker)
        else:
            source = path if path else ":memory:"
            db.execute("ATTACH '" + source.replace("'", "''") + "' AS " + quoted(schema))
            create_marker(db, schema, item["seed"] + marker)
    file = Database(db.library, item["filePath"].encode())
    try:
        create_marker(file, "main", item["seed"] + 19)
    finally:
        file.check(file.close(file.db))


def snapshot(db):
    names = databases(db)
    contents = {}
    for attachment in names:
        name, path = attachment[1][1], attachment[2][1]
        if path:
            current = Path.cwd()
            location = Path(path)
            require(location.parent.resolve() == current.resolve(), "sqlite-connection-unexpected-file", path)
            attachment[2][1] = location.name
        contents[name] = population(db, name)
    return {"attached": names, "contents": contents}


def population(db, name):
    quoted_name = quoted(name)
    found = rows(db, f"SELECT name FROM {quoted_name}.sqlite_schema WHERE type='table' ORDER BY name")
    require(found in ([], [[["text", "marker"]]]), "sqlite-connection-unexpected-schema", [name, found])
    return [] if not found else rows(db, f"SELECT id FROM {quoted_name}.marker ORDER BY id")


def existing_files(db, fixture):
    active = {}
    for entry in Path.cwd().iterdir():
        require(entry.name in (fixture["filePath"], fixture["knownPath"]), "sqlite-connection-extra-file", entry.name)
        other = Database(db.library, entry.name.encode())
        try:
            active[entry.name] = population(other, "main")
        finally:
            other.check(other.close(other.db))
    return active


def arm_transaction(db, item):
    if item["transaction"] != "none":
        db.execute("BEGIN")
    if item["transaction"] == "locked-alpha":
        population(db, item["existing"][0])
    return measure_transaction(db, item)


def measure_transaction(db, item):
    if db.get_autocommit(db.db):
        return "none"
    api = db.lib.sqlite3_txn_state
    api.argtypes, api.restype = (ctypes.c_void_p, ctypes.c_char_p), ctypes.c_int
    mode = api(db.db, item["existing"][0].encode())
    require(mode in (-1, 0, 1), "sqlite-connection-unexpected-transaction-mode", mode)
    return "locked-alpha" if mode == 1 else "independent"
