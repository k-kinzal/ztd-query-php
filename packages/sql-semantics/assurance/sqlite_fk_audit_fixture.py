"""Independent SQLite foreign-key audit declarations and typed row oracle."""
from decimal import Decimal
import re

from run_sqlite_create_table import quoted
from run_sqlite_unique_keys import values_sql

ASCII = str.maketrans("ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")
NUMERIC = re.compile(r"[+-]?(?:[0-9]+(?:\.[0-9]*)?|\.[0-9]+)(?:[eE][+-]?[0-9]+)?\Z")


def at(schema, name):
    return quoted(schema) + "." + quoted(name)


def schemas(item):
    yield "main", 1
    if item["temp"]:
        yield "temp", 10
    yield item["attachment"], 20


def parent_rows(item, offset):
    word = {1: "Alpha", 10: "Beta", 20: "Gamma"}[offset]
    return (word, "Omega") if item["keyLayout"] == "single" else ((word, 1), ("Omega", 2))


def child_columns(item):
    if item["keyLayout"] == "single":
        return ("id", "first_name", "second_name")
    return ("id", "first_num", "first_name", "second_num", "second_name")


def child_values(item, offset):
    parent = parent_rows(item, offset)[0]
    if item["keyLayout"] == "single":
        word = parent
        return ((offset, word.upper(), "never"), (offset + 1, None, "unknown"),
                (offset + 2, word, word.lower()), (offset + 3, "missing", "unknown"))
    word = parent[0]
    return ((offset, "01", word.upper(), "2.0", "omega"), (offset + 1, "1", "wrong", "1", None),
            (offset + 2, "not-a-number", word, None, "invalid"), (offset + 3, "9", word, "9.0", "OMEGA"))


def ddl(item, schema):
    parent, child = item["parent"], item["child"]
    parent_name, child_name = at(schema, parent), at(schema, child)
    suffix = " WITHOUT ROWID" if item["storage"] else ""
    yield "CREATE TABLE " + at(schema, "sponsor") + " (id INTEGER PRIMARY KEY)"
    yield "CREATE TABLE " + at(schema, "secondary_child") + " (id INTEGER PRIMARY KEY, sid INTEGER REFERENCES sponsor)"
    if item["keyLayout"] == "single":
        definition = "code TEXT COLLATE NOCASE" + (" PRIMARY KEY" if item["parentMode"] == "valid" else "")
        clause = f"first_name TEXT REFERENCES {quoted(parent)}(code), second_name TEXT REFERENCES {quoted(parent)}(code)"
    else:
        definition = "code TEXT COLLATE NOCASE, seq INTEGER" + (", PRIMARY KEY(code,seq)" if item["parentMode"] == "valid" else "")
        clause = ("first_num TEXT, first_name TEXT, second_num TEXT, second_name TEXT,"
                  f" FOREIGN KEY (first_num, first_name) REFERENCES {quoted(parent)}(seq, code),"
                  f" FOREIGN KEY (second_num, second_name) REFERENCES {quoted(parent)}(seq, code)")
    if item["parentMode"] != "missing":
        yield "CREATE TABLE " + parent_name + " (" + definition + ")"
    yield "CREATE TABLE " + child_name + " (id INTEGER PRIMARY KEY, " + clause + ")" + suffix
    yield "CREATE VIEW " + at(schema, "audit_view") + " AS SELECT id FROM " + child_name


def declarations(item):
    for schema, _ in schemas(item):
        yield from ddl(item, schema)


def insertions(item):
    for schema, offset in schemas(item):
        yield "INSERT INTO " + at(schema, "sponsor") + " VALUES (7)"
        yield "INSERT INTO " + at(schema, "secondary_child") + f" VALUES ({offset + 50}, 9), ({offset + 51}, 7)"
        if item["parentMode"] != "missing":
            parent = parent_rows(item, offset)
            parent = tuple((value,) for value in parent) if item["keyLayout"] == "single" else parent
            yield "INSERT INTO " + at(schema, item["parent"]) + " VALUES " + values_sql(parent)
        yield "INSERT INTO " + at(schema, item["child"]) + " VALUES " + values_sql(child_values(item, offset))


def numeric(text):
    return Decimal(text) if NUMERIC.fullmatch(text) else text


def valid_key(item, child, parents):
    if any(cell is None for cell in child):
        return True
    if item["parentMode"] == "missing":
        return False
    if item["keyLayout"] == "single":
        return any(child[0].translate(ASCII) == data.translate(ASCII) for data in parents)
    number, text = child
    return any(numeric(number) == parent_number and text.translate(ASCII) == parent_text.translate(ASCII)
               for parent_text, parent_number in parents)


def child_violations(item, offset):
    parents = parent_rows(item, offset)
    for row_id, *values in child_values(item, offset):
        pairs = tuple(zip(values[::2], values[1::2])) if item["keyLayout"] == "composite" else ((values[0],), (values[1],))
        for ordinal, key in enumerate(reversed(pairs)):
            if not valid_key(item, key, parents):
                yield (item["child"], None if item["storage"] else row_id, item["parent"], ordinal)
