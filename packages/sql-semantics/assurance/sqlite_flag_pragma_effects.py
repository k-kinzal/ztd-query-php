"""Fixtures and independent observations for selected SQLite flag consequences."""
from sqlite_flag_pragma_cases import result


PLAIN_TABLE = "CREATE TABLE mark (x INTEGER)"
POPULATED_TABLE = PLAIN_TABLE + "; INSERT INTO mark VALUES (7)"
RENAME_IN_CATALOG = (
    "CREATE TABLE old (id INTEGER); INSERT INTO old VALUES (7); PRAGMA writable_schema=1;"
    "UPDATE sqlite_schema SET name='fresh', tbl_name='fresh', sql='CREATE TABLE fresh (id INTEGER)' WHERE name='old'"
)
FIXTURES = {
    "count_changes": (PLAIN_TABLE, ("INSERT INTO mark VALUES (7)", "SELECT * FROM mark")),
    "query_only": (PLAIN_TABLE, ("INSERT INTO mark VALUES (7)", "SELECT * FROM mark")),
    "ignore_check_constraints": ("CREATE TABLE mark (x INTEGER CHECK (x > 0))", ("INSERT INTO mark VALUES (-7)", "SELECT * FROM mark")),
    "foreign_keys": ("CREATE TABLE parent (id PRIMARY KEY); CREATE TABLE mark (x REFERENCES parent)",
                     ("INSERT INTO mark VALUES (7)", "SELECT * FROM mark")),
    "defer_foreign_keys": ("CREATE TABLE parent (id PRIMARY KEY); CREATE TABLE mark (x REFERENCES parent); PRAGMA foreign_keys=1",
                           ("INSERT INTO mark VALUES (7)", "SELECT * FROM mark")),
    "reverse_unordered_selects": (PLAIN_TABLE + "; INSERT INTO mark VALUES (7), (8), (9)", ("SELECT x FROM mark",)),
    "full_column_names": (POPULATED_TABLE, ("SELECT x FROM mark",)),
    "short_column_names": (POPULATED_TABLE + "; PRAGMA full_column_names=0", ("SELECT mark.x FROM mark",)),
    "recursive_triggers": (PLAIN_TABLE + "; CREATE TRIGGER branch AFTER INSERT ON mark WHEN NEW.x < 3"
                           " BEGIN INSERT INTO mark VALUES (NEW.x + 1); END",
                           ("INSERT INTO mark VALUES (1)", "SELECT x FROM mark ORDER BY x")),
    "writable_schema": (RENAME_IN_CATALOG, ("SELECT id FROM old", "SELECT id FROM fresh")),
}


def row(value):
    return (value,)


def error(code, message):
    return {"code": code, "message": message}


def insertion(value, problem=None, *, count=False, changes=1):
    write = result(("rows inserted",) if count else (), ((1,),) if count else (),
                   read_only=False, error=problem, changes=0 if problem is not None else changes)
    rows = () if problem is not None else (row(value),)
    return [write, result(("x",), rows)]


def expected_effects(name, enabled, change, transaction):
    if name == "count_changes":
        return insertion(7, count=enabled)
    if name == "query_only":
        return insertion(7, error(8, "attempt to write a readonly database") if enabled else None)
    if name == "ignore_check_constraints":
        return insertion(-7, None if enabled else error(19, "CHECK constraint failed: x > 0"))
    if name in ("foreign_keys", "defer_foreign_keys"):
        reject = enabled if name == "foreign_keys" else not (enabled and transaction)
        return insertion(7, error(19, "FOREIGN KEY constraint failed") if reject else None)
    if name == "reverse_unordered_selects":
        return [result(("x",), ((9,), (8,), (7,)) if enabled else ((7,), (8,), (9,)))]
    if name == "full_column_names":
        return [result(("mark.x" if enabled else "x",), ((7,),))]
    if name == "short_column_names":
        return [result(("x" if enabled else "mark.x",), ((7,),))]
    if name == "recursive_triggers":
        return [result(read_only=False, changes=3 if enabled else 2),
                result(("x",), ((1,), (2,), (3,)) if enabled else ((1,), (2,)))]
    if name == "writable_schema":
        visible = result(("id",), ((7,),))
        if change == "RESET":
            return [{"prepareError": "SQLite 1: no such table: old"}, visible]
        return [visible, {"prepareError": "SQLite 1: no such table: fresh"}]
    return []
