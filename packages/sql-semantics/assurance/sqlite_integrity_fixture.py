"""An independent integrity witness built from ordinary CHECK constraints and an index collation."""

import ctypes

from run_sqlite_create_table import quoted
from sqlite_flag_pragma_cases import result

COLLATION = ctypes.CFUNCTYPE(ctypes.c_int, ctypes.c_void_p, ctypes.c_int, ctypes.c_void_p, ctypes.c_int, ctypes.c_void_p)
NUMERIC_NAMES = ("1", ".5", "2147483648", "0x80000000", "-2147483648")
CHECKED = "ordered_entries"
INDEX = "integrity_order_test"


def at(schema, name):
    return quoted(schema) + "." + quoted(name)


def guards(item):
    main, attached = item["child"], item["attachment"]
    yield "main", main, item["counts"][0]
    if item["temp"]:
        yield "temp", main, item["counts"][1]
    yield attached, main, 0
    yield attached, main + "_archive", item["counts"][2]


def declarations(item):
    names = [(schema, table) for schema, table, _ in guards(item)]
    names += [("main", name) for name in NUMERIC_NAMES]
    for schema, name in names:
        yield f"CREATE TABLE {at(schema, name)} (id INTEGER PRIMARY KEY, value INT CHECK (value >= 0))"
    yield f"CREATE TABLE main.{CHECKED} (id INTEGER PRIMARY KEY, code TEXT)"
    yield f"CREATE VIEW main.guard_view AS SELECT id FROM {at('main', item['child'])}"


def guard_rows(item, count):
    values = [-index for index in range(1, count + 1)] if item["data"] == "check-violations" else []
    return tuple(enumerate([*values, 5, 10, None], 1))


def fixture_rows(item):
    population = {at(schema, name): result(rows=guard_rows(item, count))["rows"] for schema, name, count in guards(item)}
    population[at("main", CHECKED)] = result(rows=((1, "A"), (2, "a")))["rows"]
    return population


def populate(db, item):
    db.execute("ATTACH ':memory:' AS " + quoted(item["attachment"]))
    db.execute("PRAGMA ignore_check_constraints = 1")
    db.execute("; ".join(declarations(item)))
    for schema, name, count in guards(item):
        values = ", ".join(f"({index}, {'NULL' if val is None else val})" for index, val in guard_rows(item, count))
        db.execute(f"INSERT INTO {at(schema, name)} VALUES {values}")
    db.execute(f"CREATE UNIQUE INDEX main.{INDEX} ON {CHECKED}(code COLLATE integrity_order)")
    db.execute(f"INSERT INTO main.{CHECKED} VALUES (1,'A'),(2,'a')")
    db.execute(f"PRAGMA ignore_check_constraints = {int(item['ignore'])}")


def register(db, fold):
    def order(_, left_length, left_ptr, right_length, right_ptr):
        left = ctypes.string_at(left_ptr, left_length)
        right = ctypes.string_at(right_ptr, right_length)
        if fold:
            left, right = left.lower(), right.lower()
        return (left > right) - (left < right)

    implementation = COLLATION(order)
    call = db.lib.sqlite3_create_collation_v2
    call.argtypes = (ctypes.c_void_p, ctypes.c_char_p, ctypes.c_int, ctypes.c_void_p, COLLATION, ctypes.c_void_p)
    call.restype = ctypes.c_int
    db.check(call(db.db, b"integrity_order", 1, None, implementation, None))
    return implementation
