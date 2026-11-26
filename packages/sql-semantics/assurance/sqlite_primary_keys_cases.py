"""Build declared primary keys and independently model their write and catalog effects."""

from itertools import product
from random import Random

from sqlite_engine import typed
from sqlite_strict_tables_cases import declaration as table_declaration, stored

TABLE = "ir_primary"
FIXTURE = "CREATE TABLE ir_key_marker (value INTEGER)"
MODES = {"rowid": (False, False), "strict-rowid": (True, False),
         "without-rowid": (False, True), "strict-without-rowid": (True, True)}
LAYOUTS = {
    "inline-integer-ascending": ("INTEGER", True, [[0, False, "BINARY"]]),
    "inline-integer-descending": ("INTEGER", True, [[0, True, "BINARY"]]),
    "table-integer-ascending": ("INTEGER", False, [[0, False, "BINARY"]]),
    "table-integer-descending": ("INTEGER", False, [[0, True, "BINARY"]]),
    "inline-int": ("INT", True, [[0, False, "BINARY"]]),
    "inline-text": ("TEXT", True, [[0, False, "BINARY"]]),
    "table-text-nocase": ("TEXT", False, [[0, False, "NOCASE"]]),
    "table-text-rtrim": ("TEXT", False, [[0, True, "RTRIM"]]),
    "table-pair-forward": ("INTEGER", False, [[0, False, "BINARY"], [1, True, "NOCASE"]]),
    "table-pair-reversed": ("INTEGER", False, [[1, True, "NOCASE"], [0, False, "BINARY"]]),
}
DIMENSIONS = ("schemas", "layouts", "modes", "conflicts", "nullableDeclaration")


def declaration(columns, terms, inline, mode="rowid", conflict="ABORT", schema="main", name=TABLE, guard=False, automatic=False):
    strict, without_rowid = MODES[mode]
    return {**table_declaration(columns, strict, schema, name, guard),
            "withoutRowid": without_rowid, "primaryKey": {"inline": inline, "terms": terms, "conflict": conflict, "autoIncrement": automatic}}


def finite_cases(domain):
    for schema, layout, mode, conflict, not_null in product(*(domain[key] for key in DIMENSIONS)):
        kind, inline, terms = LAYOUTS[layout]
        columns = [["first", kind, not_null], ["second", "TEXT", False], ["stamp", "INTEGER", False]]
        yield declaration(columns, terms, inline, mode, conflict, schema)


def mode_of(reply):
    return next(name for name, flags in MODES.items() if flags == (reply["strict"], reply["withoutRowid"]))


def finite_ids(domain):
    return set(product(*(range(len(domain[key])) for key in DIMENSIONS)))


def finite_id(reply, domain):
    key = reply["primaryKey"]
    column = reply["columns"][0]
    layout = next(name for name, roles in LAYOUTS.items() if roles == (column[1], key["inline"], key["terms"]))
    values = (reply["schema"], layout, mode_of(reply), key["conflict"], column[2])
    return tuple(domain[name].index(value) for name, value in zip(DIMENSIONS, values))


def seeded(domain, seed, count):
    rng = Random(seed)
    for _ in range(count):
        width = rng.randint(2, 6)
        mode = rng.choice(domain["modes"])
        possible = ["INT", "INTEGER", "REAL", "ANY", "TEXT", "BLOB"] + ([] if MODES[mode][0] else [""])
        columns = [[f'col{slot} {rng.choice(("space", "é", "列"))}', rng.choice(possible), bool(rng.getrandbits(1))]
                   for slot in range(width - 1)]
        columns.append(["stamp", "INTEGER", False])
        inline = bool(rng.getrandbits(1))
        ordered = rng.sample(range(width - 1), 1 if inline else rng.randint(1, width - 1))
        terms = [[slot, bool(rng.getrandbits(1)), "BINARY" if inline else rng.choice(("BINARY", "NOCASE", "RTRIM"))]
                 for slot in ordered]
        yield declaration(columns, terms, inline, mode, rng.choice(domain["conflicts"]), rng.choice(domain["schemas"]),
                          guard=bool(rng.getrandbits(1)))


def analyzed():
    yield "CREATE TABLE t (v INT PRIMARY KEY) WITHOUT ROWID", declaration(
        [["v", "INT", False]], [[0, False, "BINARY"]], True, "without-rowid", name="t")
    yield "CREATE TABLE ir_primary (id INTEGER PRIMARY KEY DESC, stamp INTEGER)", declaration(
        [["id", "INTEGER", False], ["stamp", "INTEGER", False]], [[0, True, "BINARY"]], True)
    yield "CREATE TABLE ir_primary (id INTEGER, stamp INTEGER, PRIMARY KEY (ID DESC))", declaration(
        [["id", "INTEGER", False], ["stamp", "INTEGER", False]], [[0, True, "BINARY"]], False)
    yield ("CREATE TEMP TABLE ir_primary (k TEXT, x INTEGER, stamp INTEGER, "
           "PRIMARY KEY (x DESC, (k COLLATE RTRIM) COLLATE NOCASE) ON CONFLICT FAIL) STRICT, WITHOUT ROWID"), declaration(
        [["k", "TEXT", False], ["x", "INTEGER", False], ["stamp", "INTEGER", False]],
        [[1, True, "BINARY"], [0, False, "NOCASE"]], False, "strict-without-rowid", "FAIL", "temp")
    yield ("CREATE TABLE ir_primary (k TEXT, x ANY, stamp INT, PRIMARY KEY (k DESC, k, x) ON CONFLICT REPLACE)"
           " WITHOUT ROWID"), declaration(
        [["k", "TEXT", False], ["x", "ANY", False], ["stamp", "INT", False]],
        [[0, True, "BINARY"], [0, False, "BINARY"], [1, False, "BINARY"]], False, "without-rowid", "REPLACE")
    yield "CREATE TABLE ir_primary (k TEXT, stamp INT, PRIMARY KEY (k DESC, k))", declaration(
        [["k", "TEXT", False], ["stamp", "INT", False]], [[0, True, "BINARY"], [0, False, "BINARY"]], False)
    yield "CREATE TABLE ir_primary (k TEXT, stamp INT, PRIMARY KEY (k COLLATE RTRIM, k COLLATE NOCASE)) WITHOUT ROWID", declaration(
        [["k", "TEXT", False], ["stamp", "INT", False]], [[0, False, "RTRIM"], [0, False, "NOCASE"]], False, "without-rowid")


def requests(specification):
    domain, budget = specification["finite"], specification["exploration"]
    for index, item in enumerate(finite_cases(domain)):
        yield f"finite/{index}", item, item
    for seed in budget["seeds"]:
        for index, item in enumerate(seeded(domain, seed, budget["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item
    for index, (sql, item) in enumerate(analyzed()):
        yield f"analysis/{index}", {"sql": sql, "declarations": []}, item


def negatives():
    columns = [["a", "INTEGER", False], ["stamp", "INT", False]]
    yield declaration(columns, [[-1, False, "BINARY"]], False), "rejected", "nonnegative column slot"
    yield declaration(columns, [[2, False, "BINARY"]], False), "rejected", "belongs to the declared column layout"
    yield declaration(columns, [[0, False, "BINARY"], [1, False, "BINARY"]], True), "rejected", "exactly one term"
    yield declaration(columns, [[0, False, "NOCASE"]], True), "rejected", "default column collation"
    direct = {**table_declaration(columns), "withoutRowid": True}
    yield direct, "rejected", "needs a primary key"
    yield {"sql": "CREATE TABLE t (a INT PRIMARY KEY AUTOINCREMENT)", "declarations": []}, "analysis-rejected", "AUTOINCREMENT"
    yield {"sql": "CREATE TABLE t (a INT PRIMARY KEY, b INT PRIMARY KEY)", "declarations": []}, "analysis-rejected", "more than one primary key"


def effective_terms(item):
    terms = item["primaryKey"]["terms"]
    if not item["withoutRowid"]:
        return terms
    observed, result = set(), []
    for slot, descending, collation in terms:
        identity = (slot, collation)
        if identity not in observed:
            observed.add(identity)
            result.append([slot, descending, collation])
    return result


def rowid_alias(item):
    key = item["primaryKey"]
    term = key["terms"][0]
    if item["withoutRowid"] or len(key["terms"]) != 1 or (key["inline"] and term[1]):
        return None
    return term[0] if item["columns"][term[0]][1] == "INTEGER" else None


def sample(kind, token):
    if kind in ("", "TEXT"):
        return f"key-{token:03d}"
    if kind == "REAL":
        return float(token) + 0.5
    if kind == "BLOB":
        return token.to_bytes(2, "big")
    return token


def inserted(item, token):
    return [sample(column[1], token + slot) for slot, column in enumerate(item["columns"])]


def fixture_rows(item):
    terms = effective_terms(item)
    base, first, duplicate, last, nulls, omitted = [inserted(item, number) for number in (100, 200, 300, 400, 500, 600)]
    first_slot = terms[0][0]
    nulls[first_slot] = omitted[first_slot] = None
    if len({slot for slot, _, _ in terms}) > 1:
        first[first_slot] = base[first_slot]
    for slot, _, collation in terms:
        same = base[slot]
        if type(same) is str and sum(other == slot for other, _, _ in terms) == 1:
            if collation == "NOCASE":
                same = same.upper()
            elif collation == "RTRIM":
                same += " "
        duplicate[slot] = same
    return base, first, duplicate, last, nulls, omitted


def typed_rows(rows):
    return [[typed(value) for value in row] for row in rows]


def catalog(item):
    alias, terms, columns = rowid_alias(item), effective_terms(item), item["columns"]
    pk_columns = [slot for slot, _, _ in terms]
    required_key = item["strict"] or item["withoutRowid"]
    fields = [
        [slot, name, kind, int(not_null or (required_key and slot in pk_columns and slot != alias)), False,
         pk_columns.index(slot) + 1 if slot in pk_columns else 0, 0]
        for slot, (name, kind, not_null) in enumerate(columns)
    ]
    index = []
    if alias is None:
        index = [[slot, columns[slot][0], int(descending), collation, 1] for slot, descending, collation in terms]
        if item["withoutRowid"]:
            index.extend([slot, name, 0, "BINARY", 0] for slot, (name, _, _) in enumerate(columns) if slot not in pk_columns)
        else:
            index.append([-1, None, 0, "BINARY", 0])
    return {
        "table": typed_rows([[item["schema"], item["table"], "table", len(columns), int(item["withoutRowid"]), int(item["strict"])]]),
        "columns": [[*map(typed, field[:4]), field[4], *map(typed, field[5:])] for field in fields],
        "index": typed_rows([[index_number, *field] for index_number, field in enumerate(index)]),
    }


def write_result(changes, message=None):
    return {"changes": changes, "error": None if message is None else {"code": 19, "message": message}}


def allocate(item, rows, inputs):
    existing = [rowid for rowid, _ in rows if rowid is not None]
    alias = rowid_alias(item)
    values = [stored(value, field[1], item["strict"]) for field, value in zip(item["columns"], inputs)]
    if item["withoutRowid"]:
        return None, values
    rowid = values[alias] if alias is not None else None
    if rowid is None:
        rowid = max(existing, default=0) + 1
        if alias is not None:
            values[alias] = rowid
    return rowid, values


def snapshot(item, rows):
    alias = rowid_alias(item)
    names = [column[0] for column in item["columns"]]
    values = sorted(rows, key=lambda row: row[1][-1])
    if item["withoutRowid"]:
        return {"names": names, "rows": typed_rows([row for _, row in values]), "rowid": "SQLite 1: no such column: rowid"}
    return {"names": [names[alias] if alias is not None else "rowid", *names],
            "rows": typed_rows([[rowid, *row] for rowid, row in values]), "rowid": "available"}


def expected(item, explicit):
    key, table = item["primaryKey"], item["table"]
    mode, alias, terms = item["withoutRowid"] or item["strict"], rowid_alias(item), effective_terms(item)
    active = terms[0][0]
    label = item["columns"][active][0]
    base, first, duplicate, last, nulls, omitted = fixture_rows(item)
    initial = [allocate(item, [], base)]
    null_error = alias != active and (mode or bool(item["columns"][active][2]))
    for values in (nulls, omitted):
        if not null_error:
            initial.append(allocate(item, initial, values))
    null_write = write_result(int(not null_error), f"NOT NULL constraint failed: {table}.{label}" if null_error else None)
    rows = list(initial)
    policy = key["conflict"]
    conflict_names = ", ".join(f"{table}.{item['columns'][slot][0]}" for slot, _, _ in terms)
    failure = f"UNIQUE constraint failed: {conflict_names}"
    if policy in ("FAIL", "IGNORE", "REPLACE"):
        rows.append(allocate(item, rows, first))
    if policy == "REPLACE":
        replaced = allocate(item, rows, duplicate)
        rows.remove(initial[0])
        rows.append(replaced)
    if policy in ("IGNORE", "REPLACE"):
        rows.append(allocate(item, rows, last))
    rolled_back = policy == "ROLLBACK" and explicit
    writes = {"null": null_write, "omitted": null_write,
              "duplicate": write_result({"ABORT": 0, "ROLLBACK": 0, "FAIL": 1, "IGNORE": 2, "REPLACE": 3}[policy],
                                        failure if policy in ("ABORT", "ROLLBACK", "FAIL") else None)}
    return {"catalog": catalog(item), "state": snapshot(item, rows), "writes": writes,
            "active": explicit and not rolled_back, "marker": typed_rows([[73]]) if explicit and not rolled_back else []}
