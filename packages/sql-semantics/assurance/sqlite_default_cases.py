"""Construct SQLite declaration cases and predict literal-default effects."""

from itertools import product
from random import Random
import re

from run_sqlite_create_table import EXISTING, case
from run_sqlite_scalars import evaluate
from sqlite_engine import typed

KEYS = ("schemas", "types", "defaults", "notNull", "guards")
NUMBER = re.compile(r"[+-]?[0-9]+(?:\.[0-9]+)?\Z", re.ASCII)
TABLE = "ir_create_new"


def columns_of(record):
    for column in record["columns"]:
        name, kind, not_null = column[:3]
        yield name, kind, not_null, column[3] if len(column) == 4 else None


def encoded(name, kind, not_null, operand):
    return [name, kind, not_null] if operand is None else [name, kind, not_null, operand]


def finite_cases(domain):
    for schema, kind, default, not_null, guard in product(*(domain[key] for key in KEYS)):
        yield case([encoded("value", kind, not_null, default)], schema, table=TABLE, guard=guard)


def finite_ids(domain):
    return set(product(*(range(len(domain[key])) for key in KEYS)))


def finite_id(response, domain):
    _, kind, not_null, default = next(columns_of(response))
    return tuple(domain[key].index(value) for key, value in zip(
        KEYS, (response["schema"], kind, default, not_null, response["ifNotExists"])))


def seeded(domain, seed, count):
    rng = Random(seed)
    defaults = domain["defaults"] + [["text", 'it\'s "quoted"'], ["blob", "00FF"], ["hex", "A", True],
                                     ["integer", "0"], ["real", "3", "0", None, False]]
    for _ in range(count):
        columns = [encoded(f"c{n} {rng.choice(('a', 'é', '日'))}", rng.choice(domain["types"]),
                           bool(rng.getrandbits(1)), rng.choice(defaults))
                   for n in range(rng.randint(1, 5))]
        yield case(columns, rng.choice(domain["schemas"]), table=TABLE, guard=bool(rng.getrandbits(1)))


def analyzed():
    x = [
        ["a", "BLOB", False, ["blob", "FF"]], ["b", "TEXT", True, ["text", "x"]],
        ["c", "INTEGER", False, ["hex", "2", True]], ["d", "", False, ["integer", "1"]],
        ["e", "", False, ["text", "unquoted"]], ["f", "", False, ["text", "true"]],
        ["g", "", False, ["real", "3", "5", None, False]], ["h", "", False, ["real", "2", "5", None, True]],
    ]
    yield ("CREATE TEMP TABLE ir_create_new (a BLOB DEFAULT X'ff', b TEXT NOT NULL DEFAULT 'x', "
           "c INTEGER DEFAULT -0x2, d DEFAULT TRUE, e DEFAULT unquoted, f DEFAULT \"true\", g DEFAULT (3.5), h DEFAULT -2.5)",
           case(x, "temp", table=TABLE))
    yield "CREATE TABLE ir_create_new (a BLOB DEFAULT NULL, b BLOB)", case(
        [["a", "BLOB", False, ["null"]], ["b", "BLOB", False]], table=TABLE)
    yield "CREATE TABLE ir_create_new (a DEFAULT 0xFFFFFFFFFFFFFFFF, b REAL DEFAULT +007, c DEFAULT FALSE)", case(
        [["a", "", False, ["hex", "FFFFFFFFFFFFFFFF", False]], ["b", "REAL", False, ["integer", "7"]],
         ["c", "", False, ["integer", "0"]]], table=TABLE)
    yield "CREATE TABLE ir_create_new (a INTEGER DEFAULT (NULL) NOT NULL)", case(
        [["a", "INTEGER", True, ["null"]]], table=TABLE)
    yield f"CREATE TABLE IF NOT EXISTS temp.{EXISTING} (unused TEXT DEFAULT 'x')", case(
        [["unused", "TEXT", False, ["text", "x"]]], "temp", EXISTING, True)


def negatives():
    yield "CREATE TABLE t (a DEFAULT CURRENT_TIMESTAMP)", "rejected", "no complete SQLite scalar representation"
    yield "CREATE TABLE t (a DEFAULT (?2))", "analysis-rejected", "default value of column a is not constant"
    yield "CREATE TABLE t (a DEFAULT 1 DEFAULT 2)", "rejected", "can declare one default"
    yield "CREATE TABLE t (a CHECK (/* label */ a > 0) DEFAULT 1)", "rejected", "column constraint has no complete"
    yield "CREATE TABLE t (a DEFAULT -'7')", "rejected", "signed default requires a numeric literal"


def value_at_affinity(value, kind):
    if kind == "TEXT" and type(value) in (int, float):
        return str(value)
    if kind in ("INTEGER", "REAL"):
        if type(value) is str and NUMBER.fullmatch(value):
            value = int(value) if "." not in value else float(value)
        if type(value) in (int, float):
            if kind == "REAL":
                return float(value)
            if type(value) is float and value.is_integer():
                return int(value)
    return value


def expected(item):
    exists = item["table"] == EXISTING
    columns = list(columns_of(item)) if not exists else [("saved", "TEXT", False, None)]
    catalog = [[typed(index), typed(name), typed(kind), typed(int(not_null)), default is not None, typed(0), typed(0)]
               for index, (name, kind, not_null, default) in enumerate(columns)]
    result = {
        "columns": catalog, "catalogs": {scope: [[typed(EXISTING)]] for scope in ("main", "temp")},
        "writes": None, "rows": None, "restored": {scope: [[typed(EXISTING)]] for scope in ("main", "temp")},
        "existing": {scope: [[typed(scope + " sentinel")]] for scope in ("main", "temp")},
    }
    if exists:
        return result
    result["catalogs"][item["schema"]] = sorted([[typed(EXISTING)], [typed(item["table"])]])
    defaults = [value_at_affinity(None if default is None else evaluate(default, {}), kind)
                for _, kind, _, default in columns]
    overrides = [value_at_affinity("7", kind) for _, kind, _, _ in columns]
    nulls = [None] * len(columns)
    writes, rows = {}, []
    for label, values in (("omitted", defaults), ("explicit", overrides), ("nulls", nulls)):
        failed = next((name for (name, _, not_null, _), value in zip(columns, values) if not_null and value is None), None)
        error = None if failed is None else {"code": 19, "message": f"NOT NULL constraint failed: {item['table']}.{failed}"}
        if not error:
            rows.append([typed(len(rows) + 1), *map(typed, values)])
        writes[label] = {"error": error, "changes": int(error is None)}
    return {**result, "writes": writes, "rows": rows}
