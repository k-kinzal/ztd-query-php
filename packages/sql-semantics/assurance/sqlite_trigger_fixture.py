"""Observe native trigger installation, per-row writes and persistent lifetime."""

import ctypes
from pathlib import Path

from run import require
from run_sqlite_create_table import quoted
from sqlite_header_fixture import rows
from sqlite_trigger_cases import AUDIT, declarations, names, qualified

SCHEMA_TABLES = frozenset((b"sqlite_master", b"sqlite_schema", b"sqlite_temp_master", b"sqlite_temp_schema"))
HOOK = ctypes.CFUNCTYPE(None, ctypes.c_void_p, ctypes.c_int, ctypes.c_char_p, ctypes.c_char_p, ctypes.c_int64)


def fixture(db, item):
    db.execute("ATTACH 'attached.sqlite' AS " + quoted(item["attachment"]))
    for create in declarations(item):
        db.execute(create)
    for index, schema in enumerate(names(item)):
        base = base_table(item, schema)
        value = 10 + index + item["marker"]
        db.execute(f"INSERT INTO {qualified(schema, base)} VALUES(1,{value},'keep'),(2,NULL,'delete')")
        decoy = qualified(schema, "audit_decoy")
        db.execute(f"CREATE TABLE {decoy}(step,id,first,second)")
    if item["collision"]:
        watch = item["watched"]
        db.execute(f"CREATE TRIGGER {qualified(item['schema'], item['name'])} AFTER INSERT ON {qualified(*watch[:2])}"
                   " BEGIN SELECT 1; END")


def base_table(item, schema):
    return "internal_data" if schema == item["watched"][0] and len(item["watched"]) == 4 else item["watched"][1]


def snapshot(db, item, *, reopened=False):
    measurements = {}
    for schema in names(item):
        if reopened and schema == "temp":
            continue
        place = quoted(schema)
        watch = qualified(schema, base_table(item, schema))
        audit = qualified(schema, item["audit"])
        decoy = qualified(schema, "audit_decoy")
        measurements[schema] = {
            "declarations": rows(db, f"SELECT name,tbl_name,rootpage FROM {place}.sqlite_schema WHERE type='trigger' ORDER BY name"),
            "watched": rows(db, f"SELECT id,payload,marker FROM {watch} ORDER BY id"),
            "audit": rows(db, f"SELECT {','.join(AUDIT)} FROM {audit} ORDER BY rowid"),
            "decoy": rows(db, f"SELECT {','.join(AUDIT)} FROM {decoy} ORDER BY rowid"),
        }
    return measurements


def mutate(db, schema, event, slot, rowid, sql, *, view=None):
    hook = []

    @HOOK
    def observed(_context, action, location, name, row):
        hook.append([action, location.decode(), name.decode(), row])

    update = db.lib.sqlite3_update_hook
    update.argtypes, update.restype = (ctypes.c_void_p, ctypes.c_void_p, ctypes.c_void_p), ctypes.c_void_p
    update(db.db, observed, None)
    try:
        statement = db.observe(sql, {}, preserve_order=True, capture_evaluation_error=True)
    except RuntimeError as failure:
        if view is None or str(failure) != f"SQLite 1: cannot modify {view} because it is a view":
            raise
        statement = {"preparationError": str(failure)}
    finally:
        update(db.db, None, None)
    return {"scope": schema, "event": event, "slot": slot, "row": rowid, "hook": hook, "statement": statement}


def drive(db, item, *, definition_present=True):
    db.policy = "fire"
    observations = []
    for schema in names(item):
        table = qualified(schema, item["watched"][1])
        is_view = len(item["watched"]) == 4 and schema == item["watched"][0]
        value = 20 + item["marker"]
        update = 30 + item["marker"]
        operations = (
            ("INSERT", None, 3, f"INSERT INTO {table} VALUES(3,NULL,'new')"),
            ("INSERT", None, 4, f"INSERT INTO {table} VALUES(4,{value},'wide')"),
            ("UPDATE", 2, 1, f"UPDATE {table} SET marker='changed' WHERE id=1"),
            ("UPDATE", 1, 1, f"UPDATE {table} SET payload={update} WHERE id=1"),
            ("DELETE", None, 2, f"DELETE FROM {table} WHERE id=2"),
        )
        for operation in operations:
            if not is_view or definition_present and operation[0] == item["event"]:
                observations.append(mutate(db, schema, *operation, view=item["watched"][1] if is_view else None))
        if item["evolution"] and schema == item["watched"][0]:
            db.execute(f"ALTER TABLE {table} ADD COLUMN late")
            observations.append(mutate(db, schema, "UPDATE", 3, 1, f"UPDATE {table} SET late=5 WHERE id=1"))
    return observations


def reopen(db, item, factory):
    require(Path("primary.sqlite").is_file() and Path("attached.sqlite").is_file(), "sqlite-trigger-missing-fixture-files")
    connection = factory(db.library, b"primary.sqlite")
    try:
        connection.execute("ATTACH 'attached.sqlite' AS " + quoted(item["attachment"]))
        connection.configure(item)
        connection.policy = "fire"
        before = snapshot(connection, item, reopened=True)
        schema, table = item["watched"][:2]
        receipt = mutate(connection, schema, "INSERT", None, 7,
                         f"INSERT INTO {qualified(schema, table)} VALUES(7,73,'reopened')")
        return {"before": before, "event": receipt, "after": snapshot(connection, item, reopened=True)}
    finally:
        connection.check(connection.close(connection.db))
