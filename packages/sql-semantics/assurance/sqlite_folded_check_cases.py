"""Schedules and independent expectations for CHECK with inert SQLite bindings."""

import itertools
import random

from sqlite_engine import typed
from sqlite_named_check_cases import TABLE, check, compare, evaluate, integer, table

VECTORS = [(-1, 10), (7, -2), (None, 0), (0, None), (2, 3), (2, 3)]


def folded(decision, bindings):
    return ["folded-membership", decision, list(bindings)]


def condition(decision, bindings, combined=False, slot=0):
    fixed = folded(decision, bindings)
    if combined:
        return ["logical", "AND", fixed, compare(">", ["column", slot], integer(0))]
    return fixed


def placed(schema, matches, labels, placement, combined):
    owner = {"first": 0, "second": 1, "table": None}[placement]
    predicate = condition(matches, labels, combined == "and-positive-slot")
    return table([check("folded", predicate, owner)], schema=schema)


def identity_axes(finite):
    return (finite["schemas"], finite["matches"], [tuple(labels) for labels in finite["footprints"]],
            finite["placements"], finite["combination"])


def finite_ids(domain):
    return set(itertools.product(*identity_axes(domain)))


def finite_id(reply, _domain):
    clause, = reply["checks"]
    expression = clause["condition"]
    combined = expression[0] == "logical"
    folded = expression[2] if combined else expression
    return (reply["schema"], folded[1], tuple(folded[2]), {0: "first", 1: "second", None: "table"}[clause["column"]],
            "and-positive-slot" if combined else "alone")


def seeded(seed, count):
    generator = random.Random(seed)
    for index in range(count):
        owners = sorted(generator.choices(range(3), k=1 + index % 5))
        clauses = []
        for ordinal, owner in enumerate(owners):
            labels = [f"{slot:0{generator.randint(1, 4)}d}" for slot in generator.choices((1, 2, 7, 45, 82), k=ordinal + index % 6)]
            value = condition(bool(generator.getrandbits(1)), labels, bool(generator.getrandbits(1)), generator.randrange(2))
            for depth in range(generator.randrange(4)):
                other = condition(bool(generator.getrandbits(1)), labels[:depth + 1], True, generator.randrange(2))
                value = ["logical", generator.choice(("AND", "OR")), value, other]
            clauses.append(check(f"condition {ordinal}", value, owner if owner < 2 else None))
        yield table(clauses, schema=generator.choice(("main", "temp")), strict=bool(generator.getrandbits(1)))


def analyzed():
    yield f"CREATE TABLE {TABLE}(c0 INTEGER, c1 INTEGER, seq INTEGER, CONSTRAINT folded CHECK(?1 NOT IN ()))", placed(
        "main", True, ["1"], "table", "alone")
    yield (f"CREATE TEMP TABLE {TABLE}(c0 INTEGER CONSTRAINT folded CHECK((?07 + ?1 + ?007) NOT IN ()), c1 INTEGER, seq INTEGER)",
           placed("temp", True, ["07", "1", "007"], "first", "alone"))
    yield (f"CREATE TABLE {TABLE}(c0 INTEGER, c1 INTEGER CONSTRAINT folded CHECK((SELECT ?07 + ?1) NOT IN ()), seq INTEGER)",
           placed("main", True, ["07", "1"], "second", "alone"))
    yield (f"CREATE TABLE {TABLE}(c0 INTEGER, c1 INTEGER, seq INTEGER, CONSTRAINT folded CHECK(missing NOT IN ()))",
           placed("main", True, [], "table", "alone"))
    yield (f"CREATE TABLE {TABLE}(c0 INTEGER, c1 INTEGER, seq INTEGER, CONSTRAINT folded CHECK((?07 IN ()) AND c0 > 0))",
           placed("main", False, ["07"], "table", "and-positive-slot"))
    yield (f"CREATE TABLE {TABLE}(c0 INTEGER CONSTRAINT first CHECK(?02 NOT IN ()), "
           "c1 INTEGER CONSTRAINT second CHECK(?2 NOT IN ()), seq INTEGER, CONSTRAINT third CHECK(?3 NOT IN ()))",
           table([check("first", folded(True, ["02"]), 0), check("second", folded(True, ["2"]), 1),
                  check("third", folded(True, ["3"]))]))
    yield (f"CREATE TABLE {TABLE}(c0 INTEGER, c1 INTEGER, seq INTEGER, CONSTRAINT first CHECK(?2 NOT IN ()), "
           "CONSTRAINT second CHECK(?002 IN ()))",
           table([check("first", folded(True, ["2"])), check("second", folded(False, ["002"]))]))


def requests(contract):
    domain, exploration = contract["finite"], contract["exploration"]
    for index, values in enumerate(itertools.product(*identity_axes(domain))):
        item = placed(*values)
        yield f"finite/{index}", item, item
    for seed in exploration["seeds"]:
        for index, item in enumerate(seeded(seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item
    for index, (sql, item) in enumerate(analyzed()):
        yield f"analyze/{index}", {"sql": sql, "declarations": []}, item


def negatives():
    live = compare(">", ["column", 0], ["parameter", "3"])
    yield table([check("boundary", ["logical", "AND", folded(True, ["1"]), live])]), "rejected", "contain a parameter"
    for expression, expected, fragment in (
        ("(?1 NOT IN ()) AND (SELECT 1)", "analysis-rejected", "Subqueries"),
        ("(?1 NOT IN ()) AND c0 > ?02", "analysis-rejected", "Parameters"),
        ("?0 NOT IN ()", "rejected", "Parameter slot"),
    ):
        sql = f"CREATE TABLE {TABLE}(c0 INTEGER, c1 INTEGER, seq INTEGER, CONSTRAINT boundary CHECK({expression}))"
        yield {"sql": sql, "declarations": []}, expected, fragment


def registrations(expression):
    if expression[0] == "folded-membership":
        yield from expression[2]
    elif expression[0] == "logical":
        yield from registrations(expression[2])
        yield from registrations(expression[3])


def parameter_map(item):
    positions = {}
    for check in item["checks"]:
        for label in registrations(check["condition"]):
            positions.setdefault(int(label), "?" + label)
    return {"parameterCount": max(positions, default=0), "parameters": {str(slot): tag for slot, tag in positions.items()}}


def substitute(expression):
    if expression[0] == "folded-membership":
        return integer(int(expression[1]))
    if expression[0] == "logical":
        return [*expression[:2], substitute(expression[2]), substitute(expression[3])]
    return expression


def expected(item):
    fields = [column[0] for column in item["columns"]]
    columns = [[typed(value) for value in (slot, name, kind, int(not_null), None, 0, 0)]
               for slot, (name, kind, not_null) in enumerate(item["columns"])]
    checks = [(clause["label"], substitute(clause["condition"])) for clause in item["checks"]]
    writes, stored = [], []
    for ordinal, pair in enumerate(VECTORS, start=1):
        data = (*pair, ordinal)
        failure = next((label for label, predicate in checks if evaluate(predicate, data) == 0), None)
        if failure is None:
            stored.append([typed(value) for value in data])
            writes.append({"error": None, "changes": 1})
        else:
            writes.append({"error": {"code": 19, "message": f"CHECK constraint failed: {failure}"}, "changes": 0})
    return {"create": {**parameter_map(item), "names": [], "rows": [], "changes": 0, "readOnly": False,
                       "evaluationError": None},
            "columns": columns, "names": fields, "writes": writes, "rows": stored}
