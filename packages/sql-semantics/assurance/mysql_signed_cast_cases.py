"""Finite and seeded checks for native MySQL signed conversion projections."""

from itertools import product
from random import Random

from server_cases import ROWS, analyzed, case, evaluate, node_key, observation, source

EXCEPTION = "SqlSemantics\\Platform\\MySql\\Model\\Diagnostic\\InvalidConstruction"
MYSQL_BIGINT = 8


def leaf(value):
    if value is None:
        return ["null"]
    if type(value) is bool:
        return ["boolean", value]
    return ["integer", str(value)]


def cast(value):
    return ["signed-cast", value]


def role_of(table):
    return "unit" if table is None else "table"


def project(tag, expressions, names, role="unit", distinct=False):
    table = None if role == "unit" else source("mysql")
    return {**case("mysql", table, expressions, names), "release": tag, "where": None, "distinct": distinct}


def single(tag, inner, role="unit", distinct=False):
    return project(tag, [cast(inner)], ["result"], role, distinct)


def arithmetic(operator):
    return ["operation", operator, leaf(2), leaf(3)]


def finite(tag, data):
    values = [*map(leaf, data["operands"]), *map(arithmetic, data["arithmetic"])]
    for role, inner, distinct in product(data["sources"], values, data["distinct"]):
        yield single(tag, inner, role, distinct)
    for slot, distinct in product(data["referenceSlots"], data["distinct"]):
        yield single(tag, ["slot", slot], "table", distinct)


def finite_id(_, response, _item):
    value = response["columns"][0][0]
    assert value[0] == "signed-cast"
    return role_of(response["source"]), node_key(value[1]), response["distinct"]


def finite_ids(_, specification, _tag):
    data = specification["finite"]
    result = set()
    for role in data["sources"]:
        keys = {node_key(leaf(value)) for value in data["operands"]}
        keys.update(node_key(arithmetic(operator)) for operator in data["arithmetic"])
        if role == "table":
            keys.update(node_key(["slot", slot]) for slot in data["referenceSlots"])
        result.update((role, key, option) for option in data["distinct"] for key in keys)
    return result


def seeded(tag, data, seed, count):
    rng = Random(seed)
    for _ in range(count):
        role = rng.choice(data["sources"])
        refs = [["slot", slot] for slot in data["referenceSlots"]] if role == "table" else []
        terms = [leaf(value) for value in (-7, -2, 0, 2, 5)] + refs
        atoms = list(map(leaf, data["operands"])) + refs
        names = rng.choices(("value", "quoted`field", "型", "value"), k=rng.randrange(1, 7))
        values = []
        for _ in names:
            value = rng.choice(atoms)
            if rng.randrange(3) == 0:
                value = ["operation", rng.choice(data["arithmetic"]), rng.choice(terms), rng.choice(terms)]
            for _ in range(rng.randrange(1, 7)):
                value = cast(value)
            values.append(value)
        yield project(tag, values, names, role, rng.choice(data["distinct"]))


def original_cases(tag):
    yield ("SELECT CAST( Id AS SIGNED ), convert( delta, signed integer ) FROM ir_fixture.ir_evidence_accounts",
           project(tag, [cast(["slot", 0]), cast(["slot", 1])], ["CAST( Id AS SIGNED )", "convert( delta, signed integer )"], "table"))
    yield "SELECT CONVERT(NULL, SIGNED INT), CAST(TRUE AS SIGNED)", project(
        tag, [cast(leaf(None)), cast(leaf(True))], ["CONVERT(NULL, SIGNED INT)", "CAST(TRUE AS SIGNED)"])
    yield "SELECT CAST((CAST(-2147483648 AS SIGNED)) AS SIGNED) AS result", single(tag, cast(leaf(-2147483648)))
    yield "SELECT DISTINCT CAST(2147483647 * 2147483647 AS SIGNED) AS result", single(
        tag, ["operation", "*", leaf(2147483647), leaf(2147483647)], distinct=True)
    yield "SELECT CAST(001 AS SIGNED), CAST(-000 AS SIGNED)", project(
        tag, [cast(["integer", "1", 2, False]), cast(["integer", "0", 2, True])], ["CAST(001 AS SIGNED)", "CAST(-000 AS SIGNED)"])
    yield "SELECT CAST((2147483647 * 2147483647) * 2147483647 AS SIGNED) AS result", single(
        tag, ["operation", "*", ["operation", "*", leaf(2147483647), leaf(2147483647)], leaf(2147483647)])


def negatives(tag):
    yield single(tag, ["slot", 0, 1], "table"), "Column slot must belong to the SELECT source."
    yield single(tag, ["slot", 2], "table"), "Column slot must belong to the SELECT source."
    nested = leaf(None)
    for _ in range(32):
        nested = cast(nested)
    yield single(tag, nested), "Expression height or expanded node count exceeds"
    yield analyzed("mysql", "SELECT CAST(1 AS DECIMAL)", version=tag), "A projection cast requires a checked scalar integer target."
    yield analyzed("mysql", "SELECT CAST('hello' AS SIGNED)", version=tag), "The operand has no complete MySQL projection representation."


def requests(_engine, tag, scope):
    data, settings = scope["finite"], scope["exploration"]
    for index, value in enumerate(finite(tag, data)):
        yield f"finite/{index}", value, value, None
    for seed in settings["seeds"]:
        for index, value in enumerate(seeded(tag, data, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", value, value, None
    for index, (sql, value) in enumerate(original_cases(tag)):
        yield f"analysis/{index}", analyzed("mysql", sql, version=tag), value, None
    for index, (value, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", value, None, {"exception": EXCEPTION, "message": reason}


def operand(value, row, release):
    while value[0] == "signed-cast":
        value = value[1]
    return evaluate(value, row, "mysql", release)


def expected(item):
    source_rows = [None] if item["source"] is None else ROWS
    columns = item["columns"]
    try:
        rows = [tuple(operand(expression, row, item["release"]) for expression, _ in columns) for row in source_rows]
    except OverflowError:
        return {"status": "db-error", "state": "22003"}
    if item["distinct"]:
        rows = list(set(rows))
    fields = [[name, MYSQL_BIGINT, False] for _, name in columns]
    return observation({"status": "rows", "fields": fields, "rows": [list(row) for row in rows]})
