"""Build distinguishable native catalog fixtures for SQLite table introspection."""
from typing import NamedTuple

from run_sqlite_create_table import quoted


class Relation(NamedTuple):
    schema: str
    name: str
    kind: str
    definition: str
    fields: tuple
    indexes: tuple = ()
    foreign_keys: tuple = ()
    without_rowid: int = 0
    strict: int = 0


def at(schema, name):
    return f"{quoted(schema)}.{quoted(name)}"


def ordinary(schema, name, fields, declaration=None, **attributes):
    columns = ", ".join(f"{quoted(col)} {typ}" for col, typ, *_ in fields)
    return Relation(schema, name, "table", f"CREATE TABLE {at(schema, name)} ({declaration or columns})", fields, **attributes)


def field(name, kind, *, not_null=0, default=None, primary=0, hidden=0):
    return name, kind, not_null, default, primary, hidden


def shadow(item):
    name, text = item["shadow"], item["marker"]
    source = "'" + text.replace("'", "''") + "'"
    edited = f"CAST({source} AS TEXT)"
    yield ordinary("main", name, (
        field("virtual_val", "REAL", hidden=2), field("id", "INTEGER", primary=1),
        field("code", "TEXT", not_null=1, default=edited), field("generated_text", "ANY", hidden=3),
        field("field name", "INT", default="NULL"),
    ), f"virtual_val REAL AS (id + 0.5) VIRTUAL, id INTEGER PRIMARY KEY, code TEXT NOT NULL DEFAULT ({edited}),"
       ' generated_text ANY AS (code) STORED, [field name] INT DEFAULT NULL',
       indexes=(("index_ordinal", 1, "c", 1), ("index_label", 0, "c", 0)))
    if item["temp"]:
        yield ordinary("temp", name, (field("temp_key", "INTEGER", primary=1), field("extra", "TEXT")),
                       "temp_key INTEGER PRIMARY KEY, extra TEXT")
    yield ordinary(item["attachment"], name, (field("other", "INTEGER", primary=1), field("weight", "TEXT")),
                   "other INTEGER PRIMARY KEY, weight TEXT")


def fixture(item):
    yield from shadow(item)
    yield ordinary("main", "parent", (field("key", "TEXT", primary=1), field("another", "INT")), "key TEXT PRIMARY KEY, another INT UNIQUE",
                   indexes=(("sqlite_autoindex_parent_2", 1, "u", 0), ("sqlite_autoindex_parent_1", 1, "pk", 0)))
    yield ordinary("main", "child", (field("a", "INT"), field("b", "TEXT")),
                   "a INT, b TEXT, FOREIGN KEY (a) REFERENCES parent(another),"
                   " FOREIGN KEY (b) REFERENCES parent ON UPDATE CASCADE ON DELETE SET NULL",
                   foreign_keys=((0, 0, "parent", "b", None, "CASCADE", "SET NULL", "NONE"),
                                 (1, 0, "parent", "a", "another", "NO ACTION", "NO ACTION", "NONE")))
    yield Relation("main", "watch", "view", f"CREATE VIEW main.watch AS SELECT id, code AS alias FROM main.{quoted(item['shadow'])}",
                   (field("id", "INTEGER"), field("alias", "TEXT")))
    if item["temp"]:
        yield Relation("temp", "watch", "view", f"CREATE VIEW temp.watch AS SELECT code AS temp_alias FROM main.{quoted(item['shadow'])}",
                       (field("temp_alias", "TEXT"),))
    yield Relation(item["attachment"], "norow", "table",
                   f"CREATE TABLE {at(item['attachment'], 'norow')}(a TEXT, b INT, c ANY, PRIMARY KEY(b,a)) WITHOUT ROWID, STRICT",
                   (field("a", "TEXT", not_null=1, primary=2), field("b", "INT", not_null=1, primary=1), field("c", "ANY")),
                   (("sqlite_autoindex_norow_1", 1, "pk", 0),), without_rowid=1, strict=1)
    yield ordinary(item["attachment"], item["quoted"], (field("only column", "TEXT"),))
    for name, column in (("1", "number"), ("+1", "signed text"), ("-1", "negative")):
        yield ordinary("main", name, (field(column, "TEXT"),))


def declarations(item):
    yield from (table.definition for table in fixture(item))
    table = quoted(item["shadow"])
    yield f"CREATE INDEX main.index_label ON {table}(code)"
    yield f"CREATE UNIQUE INDEX main.index_ordinal ON {table}(id, code DESC) WHERE code IS NOT NULL"


def system_tables(attachment):
    for schema, name in (("main", "sqlite_master"), ("temp", "sqlite_temp_master"), (attachment, "sqlite_master")):
        yield Relation(schema, name, "catalog", "", (field("type", "TEXT"), field("name", "TEXT"), field("tbl_name", "TEXT"),
                                                       field("rootpage", "INT"), field("sql", "TEXT")))
