"""Generate rowid table declarations and model the fixture's strict storage rules."""

from itertools import product
from random import Random

from run_sqlite_create_table import EXISTING, FIXTURE, case
from run_sqlite_scalars import evaluate
from sqlite_default_cases import columns_of, encoded, value_at_affinity
from sqlite_engine import typed

TABLE = "ir_strict"
SAMPLES = {
    "integer": 7, "integral-real": 7.0, "fractional-real": 7.5, "numeric-text": "0007",
    "fractional-text": "7.5", "nonnumeric-text": "a'b", "blob": b"\x00\xff", "null": None,
}
DIMENSIONS = ("schemas", "types", "modes", "nullability", "guards", "defaults")
TYPE_STORAGE = {"INT": "INTEGER", "INTEGER": "INTEGER", "REAL": "REAL", "TEXT": "TEXT", "BLOB": "BLOB"}
EXCEPTION = "SqlSemantics\\Platform\\Sqlite\\Model\\Diagnostic\\"


def declaration(columns, strict=False, schema="main", name=TABLE, guard=False):
    return {**case(columns, schema, name, guard), "strict": strict}


def finite_cases(domain):
    for schema, kind, mode, not_null, guard, operand in product(*(domain[key] for key in DIMENSIONS)):
        if mode == "strict" and not kind:
            continue
        yield declaration([encoded("value", kind, not_null, operand)], mode == "strict", schema, guard=guard)


def finite_ids(domain):
    sizes = [range(len(domain[key])) for key in DIMENSIONS]
    untyped = domain["types"].index("")
    strict = domain["modes"].index("strict")
    return {dimensions for dimensions in product(*sizes) if dimensions[1:3] != (untyped, strict)}


def finite_id(reply, domain):
    _, kind, not_null, default = next(columns_of(reply))
    mode = "strict" if reply["strict"] else "ordinary"
    values = (reply["schema"], kind, mode, not_null, reply["ifNotExists"], default)
    return tuple(domain[key].index(value) for key, value in zip(DIMENSIONS, values))


def seeded(domain, seed, count):
    rng = Random(seed)
    suffixes = ("A B", 'double "quote"', "列", "é")
    default_set = domain["defaults"] + [["null"], ["integer", "-7"], ["text", "other"]]
    for _ in range(count):
        strict = rng.choice(domain["modes"]) == "strict"
        kinds = domain["types"][1:] if strict else domain["types"]
        columns = [encoded(f"c{index} {rng.choice(suffixes)}", rng.choice(kinds), bool(rng.getrandbits(1)), rng.choice(default_set))
                   for index in range(rng.randrange(1, 6))]
        exists = rng.randrange(5) == 0
        yield declaration(columns, strict, rng.choice(domain["schemas"]), EXISTING if exists else TABLE,
                          guard=exists or bool(rng.getrandbits(1)))


def analyzed():
    yield "CREATE TABLE ir_strict (v ANY DEFAULT '0007') STRICT", declaration(
        [["v", "ANY", False, ["text", "0007"]]], True)
    yield "CREATE TABLE ir_strict (v ANY DEFAULT '0007')", declaration(
        [["v", "ANY", False, ["text", "0007"]]])
    yield 'CREATE TEMP TABLE ir_strict ("n ""x" int NOT NULL, b BLOB DEFAULT X\'FF\') STRICT, STRICT', declaration(
        [['n "x', "INT", True], ["b", "BLOB", False, ["blob", "FF"]]], True, "temp")
    yield f"CREATE TABLE IF NOT EXISTS temp.{EXISTING} (v INTEGER) STRICT", declaration(
        [["v", "INTEGER", False]], True, "temp", EXISTING, True)
    yield "CREATE TABLE main.ir_strict (a INTEGER DEFAULT NULL, b REAL, c TEXT) STRICT", declaration(
        [["a", "INTEGER", False, ["null"]], ["b", "REAL", False], ["c", "TEXT", False]], True)
    yield "CREATE TABLE ir_strict (x INT, y, z ANY)", declaration(
        [["x", "INT", False], ["y", "", False], ["z", "ANY", False]])


def requests(specification):
    domain, budget = specification["finite"], specification["exploration"]
    for index, item in enumerate(finite_cases(domain)):
        yield f"finite/{index}", item, item
    for seed in budget["seeds"]:
        for index, item in enumerate(seeded(domain, seed, budget["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item
    declared = [f"CREATE TEMP TABLE {EXISTING} (saved TEXT)", f"CREATE TABLE {EXISTING} (saved TEXT)"]
    for index, (sql, item) in enumerate(analyzed()):
        yield f"analysis/{index}", {"sql": sql, "declarations": declared}, item


def negatives():
    yield declaration([["value", "", False]], True), "rejected", "requires every column to declare a datatype"
    yield {"sql": "CREATE TABLE t (v) STRICT", "declarations": []}, "analysis-rejected", "STRICT"
    yield {"sql": "CREATE TABLE t (v VARCHAR) STRICT", "declarations": []}, "analysis-rejected", "STRICT"
    yield {"sql": "CREATE TABLE t (v INT) WITHOUT ROWID", "declarations": []}, "analysis-rejected", "needs a primary key"


def stored(value, kind, strict):
    if kind == "ANY":
        return value if strict else value_at_affinity(value, "INTEGER")
    return value_at_affinity(value, "INTEGER" if kind == "INT" else kind)


def assign(value, column, item):
    name, kind, not_null, _ = column
    value = stored(value, kind, item["strict"])
    target = item["table"] + "." + name
    if value is None:
        return value, f"NOT NULL constraint failed: {target}" if not_null else None
    storage = typed(value)[0].upper()
    if item["strict"] and kind != "ANY" and storage != TYPE_STORAGE[kind]:
        error_type = "INT" if storage == "INTEGER" else storage
        return value, f"cannot store {error_type} value in {kind} column {target}"
    return value, None


def effects(item, columns):
    supplied = {"omitted": [evaluate(default, {}) if default else None for _, _, _, default in columns]}
    supplied.update((name, [value] * len(columns)) for name, value in SAMPLES.items())
    results, rows = {}, []
    for name, values in supplied.items():
        assignments = [assign(value, field, item) for value, field in zip(values, columns)]
        errors = [error for _, error in assignments if error is not None]
        error = next((error for error in errors if error.startswith("NOT NULL")), errors[0] if errors else None)
        if error is None:
            rows.append([typed(len(rows) + 1), *(typed(value) for value, _ in assignments)])
        results[name] = {"error": {"code": 19, "message": error} if error is not None else None,
                         "changes": int(error is None)}
    return results, rows


def expected(item):
    name, schema = item["table"], item["schema"]
    exists = name == EXISTING
    columns = [("saved", "TEXT", False, None)] if exists else list(columns_of(item))
    mode = int(item["strict"] and not exists)
    catalog = [[typed(index), typed(label), typed(kind), typed(int(not_null)), operand is not None, typed(0), typed(0)]
               for index, (label, kind, not_null, operand) in enumerate(columns)]
    catalogs = {scope: [[typed(EXISTING)]] for scope in ("main", "temp")}
    if not exists:
        catalogs[schema] = sorted([*catalogs[schema], [typed(name)]])
    writes, rows = ({}, [[typed(1), typed(schema + " sentinel")]]) if exists else effects(item, columns)
    return {"table": [[typed(field) for field in (schema, name, "table", len(columns), 0, mode)]],
            "columns": catalog, "catalogs": catalogs, "writes": writes, "rows": rows}
