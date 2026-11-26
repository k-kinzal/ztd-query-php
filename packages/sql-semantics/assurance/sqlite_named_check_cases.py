"""Inputs and independent scalar and write rules for named SQLite conditions."""

import itertools
import random

from sqlite_engine import typed

TABLE = "ir_checked"
MARKER = "main.ir_check_marker"
FIXTURE = f"CREATE TABLE {MARKER} (value INTEGER);"
VECTORS = [(4, 2), (-2, 4), (3, -1), (0, 2), (2, 0), (None, 4), (4, None), (None, None), (-2, 0), (0, None)]
MODES = ("ABORT", "IGNORE", "FAIL")


def integer(value):
    return ["integer", str(value)]


def compare(operation, left, right):
    return ["comparison", operation, left, right]


CONDITIONS = {
    "first-positive": compare(">", ["column", 0], integer(0)),
    "second-nonzero": compare("<>", ["column", 1], integer(0)),
    "difference-positive": compare(">", ["arithmetic", "-", ["column", 0], ["column", 1]], integer(0)),
    "nullable-and": ["logical", "AND", compare(">", ["column", 0], integer(0)),
                     compare(">=", ["column", 1], integer(0))],
}


def check(label, condition, column=None):
    return {"label": label, "column": column, "condition": condition}


def table(checks, *, schema="main", strict=False, width=2, not_null=(), keys=(), lifecycle=False):
    columns = [[f"c{slot}", "INTEGER", slot in not_null] for slot in range(width)] + [["seq", "INTEGER", False]]
    return {"operation": "create-table", "table": TABLE, "schema": schema, "columns": columns, "strict": strict,
            "ifNotExists": False, "withoutRowid": False, "keys": list(keys), "checks": checks, "lifecycle": lifecycle}


def finite_ids(domain):
    return set(itertools.product(domain["schemas"], domain["strict"], domain["ownership"],
                                 domain["labels"], domain["conditions"]))


def finite_id(result, domain):
    clause, = result["checks"]
    condition = next(name for name in domain["conditions"] if CONDITIONS[name] == clause["condition"])
    return (result["schema"], result["strict"], "table" if clause["column"] is None else "column",
            clause["label"], condition)


def explore(rng, ordinal):
    width = 2 + ordinal % 4
    schema, strict = rng.choice(("main", "temp")), rng.choice((False, True))
    placements = sorted(rng.choices(range(width + 1), k=1 + ordinal % 5))
    clauses = []
    for owner in placements:
        slot = rng.randrange(width)
        label = rng.choice(("", "limit", 'bound"ary', "境界 حد", "rem; SELECT '--"))
        boundary = rng.choice(range(-3, 5)) if ordinal % 5 else rng.choice((-9223372036854775808, 9223372036854775807))
        operation = rng.choice((">", ">=", "<", "<=", "=", "<>"))
        predicate = compare(operation, ["column", slot], integer(boundary))
        for _ in range(rng.randrange(4)):
            connective = rng.choice(("AND", "OR"))
            alternate = compare(rng.choice((">", "<>")), ["column", rng.randrange(width)], integer(rng.randrange(-3, 4)))
            predicate = ["logical", connective, predicate, alternate]
        clauses.append(check(label, predicate, owner if owner < width else None))
    return table(clauses, schema=schema, strict=strict, width=width)


def analyzed():
    first = CONDITIONS["first-positive"]
    second = CONDITIONS["second-nonzero"]
    yield (f"CREATE TABLE {TABLE}(c0 INTEGER CONSTRAINT chosen CHECK({TABLE}.c1 <> 0), c1 INTEGER, seq INTEGER)",
           table([check("chosen", second, 0)]))
    yield (f"CREATE TABLE {TABLE}(c0 INTEGER CONSTRAINT shared NOT NULL CHECK(c0>0) CHECK(c0<3), c1 INTEGER, seq INTEGER)",
           table([check("shared", first, 0), check("shared", compare("<", ["column", 0], integer(3)), 0)], not_null=(0,)))
    yield (f'CREATE TABLE {TABLE}(c0 INTEGER, c1 INTEGER, seq INTEGER, CONSTRAINT "a""b" CHECK(c0>0) CHECK(c1<>0))',
           table([check('a"b', first), check('a"b', second)]))
    yield (f'CREATE TABLE {TABLE}(c0 INTEGER, c1 INTEGER, seq INTEGER, CONSTRAINT "" CHECK(c0>0) ON CONFLICT IGNORE)',
           table([check("", first)]))
    yield (f"CREATE TEMP TABLE {TABLE}(c0 INTEGER, c1 INTEGER CONSTRAINT other CHECK(c0>0), seq INTEGER) STRICT",
           table([check("other", first, 1)], schema="temp", strict=True))
    yield (f"CREATE TABLE {TABLE}(c0 INTEGER CONSTRAINT keep CHECK(c0>0), c1 INTEGER, seq INTEGER)",
           table([check("keep", first, 0)], lifecycle=True))
    yield (f"CREATE TABLE {TABLE}(c0 INTEGER, c1 INTEGER, seq INTEGER, CONSTRAINT keep CHECK(c0>0))",
           table([check("keep", first)], lifecycle=True))
    unique = {"kind": "unique", "inline": False, "terms": [[2, False, "BINARY"]], "conflict": None}
    yield (f"CREATE TABLE {TABLE}(c0 INTEGER CONSTRAINT filter CHECK(c0>0), c1 INTEGER NOT NULL, seq INTEGER, UNIQUE(seq))",
           table([check("filter", first, 0)], not_null=(1,), keys=(unique,)))


def requests(contract):
    domain, exploration = contract["finite"], contract["exploration"]
    for index, (schema, strict, ownership, label, condition) in enumerate(itertools.product(
            domain["schemas"], domain["strict"], domain["ownership"], domain["labels"], domain["conditions"])):
        owner = 0 if ownership == "column" else None
        item = table([check(label, CONDITIONS[condition], owner)], schema=schema, strict=strict,
                     lifecycle=condition == "first-positive")
        yield f"finite/{index}", item, item
    for seed in exploration["seeds"]:
        rng = random.Random(seed)
        for index in range(exploration["casesPerSeed"]):
            item = explore(rng, index)
            yield f"seed/{seed}/{index}", item, item
    for index, (sql, item) in enumerate(analyzed()):
        yield f"analyze/{index}", {"sql": sql, "declarations": []}, item


def negatives():
    condition = CONDITIONS["first-positive"]
    yield table([check("scope", ["column", 5])]), "rejected", "visible occurrence"
    yield table([check("owner", condition, 5)]), "rejected", "visible owning column"
    yield table([check("table", condition), check("inline", condition, 0)]), "rejected", "declaration order"
    yield table([check("unbound", compare(">", ["column", 0], ["parameter", "1"]))]), "rejected", "contain a parameter"
    for sql, status, reason in (
        (f"CREATE TABLE {TABLE}(c0 INTEGER CONSTRAINT L CHECK(unknown>0))", "analysis-rejected", "Column unknown does not exist."),
        (f"CREATE TABLE {TABLE}(c0 INTEGER CONSTRAINT L CHECK((SELECT 1)))", "analysis-rejected", "Subqueries"),
        (f"CREATE TABLE {TABLE}(c0 INTEGER CONSTRAINT L CHECK(c0>?1))", "analysis-rejected", "Parameters"),
        (f"CREATE TABLE {TABLE}(c0 INTEGER, CONSTRAINT L CHECK(c0>0), CHECK(/* label */ c0<10))", "rejected", "explicit CHECK label"),
    ):
        yield {"sql": sql, "declarations": []}, status, reason


def values(item):
    width = len(item["columns"]) - 1
    for index, pair in enumerate(VECTORS, start=1):
        extra = tuple((index + slot) % 5 - 2 if (index + slot) % 4 else None for slot in range(2, width))
        seq = 2 if item["keys"] and index == 3 else index
        yield (*pair, *extra, seq)
    if item["keys"]:
        yield (5, 5, 2)


def evaluate(expression, row):
    role = expression[0]
    if role == "integer":
        return int(expression[1])
    if role == "column":
        return row[expression[1]]
    operator, first, second = expression[1:]
    left, right = evaluate(first, row), evaluate(second, row)
    if role == "logical":
        if operator == "AND":
            return 0 if left == 0 or right == 0 else None if left is None or right is None else 1
        if operator == "OR":
            return 1 if left == 1 or right == 1 else None if left is None or right is None else 0
    if left is None or right is None:
        return None
    if role == "arithmetic" and operator == "-":
        return left - right
    if role == "comparison":
        return int({"=": left == right, "<>": left != right, ">": left > right, "<": left < right,
                    ">=": left >= right, "<=": left <= right}[operator])
    raise ValueError(f"Unknown fixture expression kind {role!r} with {operator!r}")


def failed(item, row, accepted):
    for slot, column in enumerate(item["columns"]):
        if column[2] and row[slot] is None:
            return f"NOT NULL constraint failed: {item['table']}.{column[0]}"
    for clause in item["checks"]:
        if evaluate(clause["condition"], row) == 0:
            return f"CHECK constraint failed: {clause['label']}"
    if item["keys"] and any(other[-1] == row[-1] for other in accepted):
        return f"UNIQUE constraint failed: {item['table']}.seq"
    return None


def transition(item, rows, mode):
    accepted = []
    error = None
    for row in rows:
        problem = failed(item, row, accepted)
        if problem is None:
            accepted.append(row)
        elif mode != "IGNORE":
            error = {"code": 19, "message": problem}
            if mode == "ABORT":
                accepted = []
            break
    return {"changes": len(accepted), "error": error,
            "rows": [[typed(value) for value in row] for row in sorted(accepted, key=lambda row: row[-1])]}


def expected(item):
    fields = [column[0] for column in item["columns"]]
    columns = [[typed(v) for v in (slot, field, kind, int(nullability), None, 0, 0)]
               for slot, (field, kind, nullability) in enumerate(item["columns"])]
    rows = list(values(item))
    results = {policy: {**transition(item, rows, policy), "transaction": True, "marker": [[typed(73)]]}
               for policy in MODES}
    observations = {"columns": columns, "fields": fields, "separate": [transition(item, [row], "ABORT") for row in rows],
                    "policies": results}
    if item["lifecycle"]:
        label = item["checks"][0]["label"]
        renamed = {"fields": ["renamed", *fields[1:]], "error": {"code": 19, "message": f"CHECK constraint failed: {label}"}}
        if item["checks"][0]["column"] == 0:
            dropped = {"error": None, "fields": fields[1:], "rows": [[typed(-5), typed(1)]]}
        else:
            dropped = {"error": {"code": 1, "table": TABLE, "column": "c0"},
                       "fields": fields, "rows": []}
        observations["lifecycle"] = {"rename": renamed, "drop": dropped}
    return observations
