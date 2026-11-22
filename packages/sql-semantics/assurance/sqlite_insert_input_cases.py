"""Declare INPUT cases and assign their rows in the independent effect model."""

from itertools import product
from random import Random

from run_sqlite_insert import DECLARATIONS as BASE, FIXTURE as SCHEMA, case as values_case, expected as values_effect, expected_assignment
from sqlite_insert_query_cases import BASELINE_STATE, case as query_case, expected as query_effect, query, shape
from sqlite_result_cases import integer

DECLARATIONS = [source.replace("id INTEGER UNIQUE", f"id INTEGER DEFAULT {value} UNIQUE")
                for source, value in zip(BASE, (1, 7))]
FIXTURE = SCHEMA.replace(BASE[0], DECLARATIONS[0]).replace(BASE[1], DECLARATIONS[1])
ROWS = [[integer(1), ["text", "collision"]], [integer(6), ["text", "7"]], [["null"], ["null"]]]


def defaults(schema="main", conflict="inherit", columns=()):
    return {"operation": "insert", "target": [schema, "ir_insert", list(columns)], "conflict": conflict,
            "bindings": {}, "defaults": True}


def selected(schema, conflict, row, where=None, bindings=None, labels=("p0", "p1")):
    source = query(None, [list(pair) for pair in zip(row, labels)], where=where, bindings=bindings)
    return query_case(source, schema=schema, conflict=conflict)


def finite_cases(domain):
    for schema, conflict in product(domain["schemas"], domain["policies"]):
        yield defaults(schema, conflict)
        for row in ROWS:
            yield values_case([row], schema, conflict=conflict)
            yield selected(schema, conflict, row)


def finite_ids(domain):
    sources = [("defaults", 0), *((role, index) for role in domain["inputs"][1:] for index in range(len(domain["rowChoices"])))]
    return {(schema, policy, role, identity) for schema, policy, (role, identity) in
            product(domain["schemas"], domain["policies"], sources)}


def finite_id(reply):
    schema = reply["target"][0]
    policy = reply["conflict"]
    if reply.get("defaults"):
        return schema, policy, "defaults", 0
    if "rows" in reply:
        return schema, policy, "values", ROWS.index(reply["rows"][0])
    return schema, policy, "select", ROWS.index([value for value, _ in reply["query"]["columns"]])


def seeded(domain, seed, count):
    rng = Random(seed)
    keys = [integer(1), integer(3), integer(7), ["parameter", "02"], ["null"]]
    values = [integer(12), ["text", "007"], ["parameter", "02"], ["null"]]
    for _ in range(count):
        schema = rng.choice(domain["schemas"])
        conflict = rng.choice(domain["policies"])
        role = rng.choice(domain["inputs"])
        if role == "defaults":
            yield defaults(schema, conflict)
            continue
        row = [rng.choice(keys), rng.choice(values)]
        bindings = {"2": rng.choice((None, 1, 5, "3", "text"))} if ["parameter", "02"] in row else {}
        if role == "values":
            yield values_case([row], schema, conflict=conflict, bindings=bindings)
        else:
            yield selected(schema, conflict, row, rng.choice((None, integer(0), integer(1))), bindings)


def analyzed():
    yield "INSERT INTO main.ir_insert DEFAULT VALUES", defaults()
    yield "INSERT OR ABORT INTO main.ir_insert DEFAULT VALUES", defaults(conflict="abort")
    yield "INSERT OR IGNORE INTO ir_insert DEFAULT VALUES", defaults("temp", "ignore")
    yield "INSERT INTO main.ir_insert VALUES (7, 'p')", values_case([[integer(7), ["text", "p"]]])
    yield ("INSERT OR IGNORE INTO temp.ir_insert SELECT ?02 AS p0, 15 AS p1 WHERE 1",
           selected("temp", "ignore", [["parameter", "02"], integer(15)], integer(1), {"2": 8}))


def negatives():
    yield defaults(columns=("id",)), "rejected", "has no supplied target columns"
    yield ({"sql": "INSERT INTO main.ir_insert(id) DEFAULT VALUES", "declarations": DECLARATIONS},
           "rejected", "does not admit a written column list")
    yield ({"sql": "INSERT INTO main.ir_insert VALUES (7)", "declarations": DECLARATIONS},
           "analysis-rejected", "1 values for 2 columns")
    yield ({"sql": "INSERT INTO main.ir_insert(computed) VALUES (7)", "declarations": DECLARATIONS},
           "analysis-rejected", "cannot INSERT into generated column")


def expected(item, after_main=None):
    omitted = 1 if item["target"][0] == "main" else 7
    if item.get("defaults"):
        effect = expected_assignment(item, [()], {}, omitted, after_main)
    elif "query" in item:
        return query_effect(item, omitted, after_main)
    else:
        effect = values_effect(item, omitted, after_main)
    return {**effect, "restored": BASELINE_STATE}


def input_shape(item):
    if item.get("defaults"):
        return "defaults", True
    if "query" in item:
        return "query", shape(item)
    return "rows", item["rows"]
