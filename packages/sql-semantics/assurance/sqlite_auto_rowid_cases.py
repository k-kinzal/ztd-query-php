"""Define checked rowid declarations and model allocation across committed histories."""

from itertools import product
from random import Random

from sqlite_primary_keys_cases import catalog, declaration, sample, typed_rows
from sqlite_strict_tables_cases import stored

TABLE = "ir_autokey"
MAXIMUM = 9223372036854775807
FIXTURE = ""
PLACEMENT = {"inline": (True, False), "table-ascending": (False, False), "table-descending": (False, True)}
DIMENSIONS = ("schemas", "strict", "placement", "automatic", "transactionRollback", "historicalHigh", "input")


def item(schema="main", strict=False, placement="inline", automatic=True, rollback=False, high=40,
         input_role="omitted", columns=None, slot=0, conflict="ABORT", name=TABLE):
    inline, descending = PLACEMENT[placement]
    if columns is None:
        columns = [["id", "INTEGER", False], ["stamp", "TEXT", False]]
    mode = "strict-rowid" if strict else "rowid"
    record = declaration(columns, [[slot, descending, "BINARY"]], inline, mode, conflict, schema, name,
                         automatic=automatic)
    return {**record, "history": {"rollback": rollback, "high": high, "input": input_role}}


def finite_cases(domain):
    yield from (item(*profile) for profile in product(*(domain[name] for name in DIMENSIONS)))


def finite_ids(domain):
    return set(product(*(range(len(domain[name])) for name in DIMENSIONS)))


def placement_of(reply):
    key = reply["primaryKey"]
    found = (key["inline"], key["terms"][0][1])
    return next(name for name, roles in PLACEMENT.items() if found == roles)


def finite_id(observed, domain):
    reply, measurement = observed
    history = measurement["history"]
    values = (reply["schema"], reply["strict"], placement_of(reply), reply["primaryKey"]["autoIncrement"],
              history["rollback"], history["high"], history["input"])
    return tuple(domain[name].index(value) for name, value in zip(DIMENSIONS, values))


def seeded(domain, seed, count):
    rng = Random(seed)
    suffixes = ("with space", 'with"quote', "列", "é")
    for _ in range(count):
        strict = bool(rng.getrandbits(1))
        width = rng.randint(1, 5)
        keys = ["INT", "INTEGER", "TEXT", "BLOB", "REAL", "ANY"] + ([] if strict else [""])
        columns = [[f"c{idx} {rng.choice(suffixes)}", rng.choice(keys), bool(rng.getrandbits(1))] for idx in range(width)]
        slot = rng.randrange(width)
        columns[slot][1] = "INTEGER"
        yield item(rng.choice(domain["schemas"]), strict, rng.choice(domain["placement"]), bool(rng.getrandbits(1)),
                   bool(rng.getrandbits(1)), rng.choice([MAXIMUM, rng.randint(25, 500)]), rng.choice(domain["input"]),
                   columns, slot, rng.choice(("ABORT", "FAIL", "IGNORE", "REPLACE", "ROLLBACK")))


def analyzed():
    yield "CREATE TABLE t (a INTEGER PRIMARY KEY AUTOINCREMENT)", item(
        columns=[["a", "INTEGER", False]], name="t")
    yield "CREATE TABLE ir_autokey (code TEXT, key INTEGER PRIMARY KEY ON CONFLICT FAIL AUTOINCREMENT) STRICT", item(
        strict=True, columns=[["code", "TEXT", False], ["key", "INTEGER", False]], slot=1, conflict="FAIL")
    yield ("CREATE TEMP TABLE ir_autokey (id INTEGER, x REAL, "
           "PRIMARY KEY (id DESC AUTOINCREMENT) ON CONFLICT REPLACE) STRICT"), item(
        "temp", True, "table-descending", rollback=True, columns=[["id", "INTEGER", False], ["x", "REAL", False]],
        conflict="REPLACE")
    yield "CREATE TABLE ir_autokey (id INTEGER, stamp TEXT, PRIMARY KEY (id))", item(
        placement="table-ascending", automatic=False, input_role="null")
    yield ("CREATE TABLE ir_autokey (id INTEGER, stamp TEXT, PRIMARY KEY (id ASC AUTOINCREMENT))"), item(
        placement="table-ascending", high=MAXIMUM)


def requests(specification):
    domain, settings = specification["finite"], specification["exploration"]
    for index, record in enumerate(finite_cases(domain)):
        yield f"finite/{index}", record, record
    for seed in settings["seeds"]:
        for index, record in enumerate(seeded(domain, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", record, record
    for index, (sql, record) in enumerate(analyzed()):
        yield f"analysis/{index}", {"sql": sql, "declarations": []}, record


def negatives():
    columns = [["id", "INTEGER", False], ["stamp", "INTEGER", False]]
    entries = [
        (declaration(columns, [[0, False, "BINARY"], [1, False, "BINARY"]], False, automatic=True),
         "AUTOINCREMENT needs exactly one key term."),
        (declaration(columns, [[0, True, "BINARY"]], True, automatic=True),
         "AUTOINCREMENT requires an inline rowid alias."),
        (declaration([["id", "INT", False]], [[0, False, "BINARY"]], True, automatic=True),
         "AUTOINCREMENT requires one INTEGER rowid alias."),
        (declaration(columns, [[0, False, "BINARY"]], False, "without-rowid", automatic=True),
         "AUTOINCREMENT requires one INTEGER rowid alias."),
    ]
    for record, reason in entries:
        yield record, "rejected", reason
    yield {"sql": "CREATE TABLE t (id INTEGER PRIMARY KEY DESC AUTOINCREMENT)", "declarations": []}, (
        "analysis-rejected"), "AUTOINCREMENT is only allowed on an INTEGER PRIMARY KEY."


def values(record, token, rowid):
    slot = record["primaryKey"]["terms"][0][0]
    result = [sample(field[1], token + index) for index, field in enumerate(record["columns"])]
    result[slot] = rowid
    return result


def write_result(changes=1, exhausted=False):
    return {"changes": changes, "error": {"code": 13, "message": "database or disk is full"} if exhausted else None}


def expected(record):
    history, key = record["history"], record["primaryKey"]
    slot, automatic = key["terms"][0][0], key["autoIncrement"]
    high = history["high"]
    first = high + 1 if automatic else 3
    after_bump = (max(first, 1000) if not history["rollback"] else first) + 1 if automatic else 4
    exhausted = automatic and high == MAXIMUM
    outcomes = {phase: write_result() for phase in ("seed", "highest", "delete-highest", "bump", "delete-bump")}
    outcomes["first"] = outcomes["second"] = write_result(0, True) if exhausted else write_result()
    rows = [values(record, 100, 2)]
    if not exhausted:
        rows += [values(record, 300, first), values(record, 600, after_bump)]
    coerced = [[stored(value, field[1], record["strict"]) for value, field in zip(row, record["columns"])] for row in rows]

    def sequence(value):
        return typed_rows([[record["table"], value]]) if automatic else []

    return {
        "catalog": catalog(record), "writes": outcomes, "sequenceExists": automatic,
        "sequence": {
            "created": [], "deleted": sequence(high), "first": sequence(min(first, MAXIMUM)),
            "insideBump": sequence(max(min(first, MAXIMUM), 1000)),
            "afterTransaction": sequence(min(after_bump - 1, MAXIMUM)), "final": sequence(min(after_bump, MAXIMUM)),
        },
        "state": {"names": [record["columns"][slot][0], *(field[0] for field in record["columns"])],
                  "rows": typed_rows([[row[slot], *row] for row in coerced])},
        "history": history,
        "autocommit": True,
    }
