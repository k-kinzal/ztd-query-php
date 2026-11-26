"""Read SQLite VACUUM fixture effects through separate row, header and file observations."""

from pathlib import Path

from run import require
from run_sqlite_create_table import quoted
from sqlite_engine import Database
from sqlite_header_fixture import databases, rows, serialized_header


def prepare(db, item):
    attachment = item["attachment"]
    db.execute("ATTACH ':memory:' AS " + quoted(attachment))
    scopes = {"main": 0, attachment: 2}
    if item["temp"]:
        scopes["temp"] = 1
    for name, slot in scopes.items():
        schema = quoted(name)
        label = "entry" + str(item["seed"] + slot)
        db.execute(f"CREATE TABLE {schema}.entries(id INTEGER PRIMARY KEY,name TEXT); "
                   f"INSERT INTO {schema}.entries VALUES ({slot + 3},'{label}'),({slot + 7},NULL); "
                   f"CREATE TABLE {schema}.released(val BLOB); "
                   f"INSERT INTO {schema}.released VALUES (zeroblob(8192)); DROP TABLE {schema}.released; "
                   f"PRAGMA {schema}.application_id={300 + slot}; PRAGMA {schema}.user_version={47 + slot}")
    if item["transaction"]:
        db.execute("BEGIN")
    path = Path(item["path"])
    require(path.parent == Path(".") and not path.is_absolute(), "sqlite-vacuum-unreviewed-fixture-path", str(path))
    if item["output"] == "empty":
        path.touch()
    elif item["output"] == "populated":
        occupied = Database(db.library, str(path).encode())
        try:
            occupied.execute("CREATE TABLE kept(id INTEGER PRIMARY KEY); INSERT INTO kept VALUES(17); PRAGMA user_version=90")
        finally:
            occupied.check(occupied.close(occupied.db))


def table_names(db, schema):
    return rows(db, f"SELECT name FROM {quoted(schema)}.sqlite_schema WHERE type='table' ORDER BY name")


def contents(db, name):
    scope = quoted(name)
    page_count = rows(db, f"PRAGMA {scope}.page_count")
    require(len(page_count) == 1 and page_count[0][0][0] == "integer", "sqlite-vacuum-page-count", page_count)
    return {"header": serialized_header(db, name), "tables": table_names(db, name),
            "rows": rows(db, f"SELECT id,name FROM {scope}.entries ORDER BY id"), "pages": int(page_count[0][0][1])}


def destination(library, path):
    path = Path(path)
    if not path.exists():
        return {"state": "missing"}
    if path.stat().st_size == 0:
        return {"state": "empty"}
    db = Database(library, str(path).encode())
    try:
        names = table_names(db, "main")
        if names == [[["text", "kept"]]]:
            require(rows(db, "SELECT id FROM main.kept ORDER BY id") == [[["integer", "17"]]]
                    and serialized_header(db, "main")["user_version"] == 90,
                    "sqlite-vacuum-preexisting-file-modified", names)
            return {"state": "populated"}
        require(names == [[["text", "entries"]]], "sqlite-vacuum-unexpected-destination-schema", names)
        return {"state": "copied", "database": contents(db, "main")}
    finally:
        db.check(db.close(db.db))


def fixture(db, item):
    attached = databases(db)
    return {"attached": attached, "transaction": not bool(db.get_autocommit(db.db)),
            "destination": destination(db.library, item["path"]),
            "schemata": {record[1][1]: contents(db, record[1][1]) for record in attached}}
