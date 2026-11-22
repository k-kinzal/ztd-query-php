"""Direct casts and an independent model of the PostgreSQL int4 and bool conversions."""

from itertools import product
from random import Random

from postgres_int4_cases import EXCEPTION, integer, projection, source_for, source_role
from server_cases import CHILD_ROWS, ROWS, analyzed, node_key, observation


def leaf(value):
    if value is None:
        return ["null"]
    if type(value) is bool:
        return ["boolean", value]
    return integer(value)


def cast(target, operand):
    return ["builtin-cast", target, operand]


def project(tag, value, role="unit", distinct=False, label="result"):
    return {**projection(tag, value, source_for(role), label), "distinct": distinct}


def union(left, right):
    assert left["release"] == right["release"]
    return {"dialect": "postgres", "release": left["release"], "compound": {
        "operator": "UNION", "all": True, "left": left, "right": right,
    }}


def finite(tag, data):
    literals = list(map(leaf, data["literalOperands"]))
    for role, mode, value, distinct in product(data["sources"], data["targets"], literals, data["distinct"]):
        yield project(tag, cast(mode, value), role, distinct)
    for role, mode, slot, distinct in product(data["sources"], data["targets"], data["referenceSlots"], data["distinct"]):
        if role != "unit":
            yield project(tag, cast(mode, ["slot", slot]), role, distinct)


def finite_id(_, response, _item):
    expression = response["columns"][0][0]
    return source_role(response["source"]), expression[1], node_key(expression[2]), response["distinct"]


def finite_ids(_, specification, _tag):
    data = specification["finite"]
    identities = set()
    for role in data["sources"]:
        operands = [leaf(value) for value in data["literalOperands"]]
        if role != "unit":
            operands.extend(["slot", slot] for slot in data["referenceSlots"])
        for operand in operands:
            identities.update((role, target, node_key(operand), option)
                              for option in data["distinct"] for target in data["targets"])
    return identities


def seeded(tag, data, seed, count):
    rng = Random(seed)
    for _ in range(count):
        role = rng.choice(data["sources"])
        literals = list(map(leaf, data["literalOperands"]))
        atoms = literals + ([["slot", slot] for slot in data["referenceSlots"]] if role != "unit" else [])
        names = rng.choices(("value", 'quoted"field', "型", "value"), k=rng.randrange(1, 6))
        fields = []
        for _ in names:
            value = rng.choice(atoms)
            for target in rng.choices(data["targets"], k=rng.randrange(1, 7)):
                value = cast(target, value)
            fields.append(value)
        yield project(tag, fields, role, rng.choice(data["distinct"]), names)


def source_sql_cases(tag):
    boolean, signed = ("bool", "int4")
    yield "SELECT CAST(TRUE AS INTEGER), NULL::BOOL", project(
        tag, [cast(signed, leaf(True)), cast(boolean, leaf(None))], label=["int4", "bool"])
    yield "SELECT CAST(-2147483648 AS BOOL) AS negative, CAST(11 AS BOOL)::int AS nested", project(
        tag, [cast(boolean, leaf(-2147483648)), cast(signed, cast(boolean, leaf(11)))], label=["negative", "nested"])
    yield ('SELECT DISTINCT (a.id)::bool, a.delta::int AS "d""q" FROM ONLY public.ir_evidence_accounts a',
           project(tag, [cast(boolean, ["slot", 0]), cast(signed, ["slot", 1])], "only", True, ["id", 'd"q']))
    yield ("SELECT NULL::BOOL UNION ALL SELECT FALSE", union(
        project(tag, cast(boolean, leaf(None)), label="bool"), project(tag, leaf(False), label="?column?")))
    yield ("SELECT FALSE::integer AS v UNION ALL SELECT NULL", union(
        project(tag, cast(signed, leaf(False)), label="v"), project(tag, leaf(None), label="?column?")))


def negatives(tag):
    yield project(tag, cast("bool", ["slot", 0])), "Column slot must belong to the SELECT source."
    too_deep = ["null"]
    for _ in range(33):
        too_deep = cast("bool", too_deep)
    yield project(tag, too_deep), "Expression height or expanded node count exceeds"
    yield analyzed("postgres", "SELECT CAST(1 AS BIGINT)", version=tag), "The cast target must be pg_catalog.int4 or pg_catalog.bool."
    yield analyzed("postgres", "SELECT CAST(2147483648 AS BOOL)", version=tag), "The cast input must resolve to pg_catalog.int4, pg_catalog.bool or NULL."
    yield analyzed("postgres", "SELECT CAST('off' AS BOOL)", version=tag), "The cast input must resolve to pg_catalog.int4, pg_catalog.bool or NULL."


def requests(_engine, tag, specification):
    data, exploration = specification["finite"], specification["exploration"]
    for index, item in enumerate(finite(tag, data)):
        yield f"finite/{index}", item, item, None
    for seed in exploration["seeds"]:
        for index, item in enumerate(seeded(tag, data, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (sql, item) in enumerate(source_sql_cases(tag)):
        yield f"analysis/{index}", analyzed("postgres", sql, version=tag), item, None
    for index, (item, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, {"exception": EXCEPTION, "message": reason}


def converted(expression, row):
    role = expression[0]
    if role == "slot":
        return row[expression[1]]
    if role == "boolean":
        return expression[1]
    if role == "integer":
        return int(expression[1])
    if role == "null":
        return None
    assert role == "builtin-cast"
    value = converted(expression[2], row)
    if value is None:
        return None
    if expression[1] == "bool":
        return value != 0
    assert expression[1] == "int4"
    return int(value)


def output_type(expression):
    role = expression[0]
    if role == "builtin-cast":
        return expression[1]
    return {"integer": "int4", "slot": "int4", "boolean": "bool", "null": None}[role]


def rows_and_fields(item):
    if "compound" in item:
        operation = item["compound"]
        assert operation["operator"] == "UNION" and operation["all"]
        first_rows, first_fields = rows_and_fields(operation["left"])
        second_rows, second_fields = rows_and_fields(operation["right"])
        assert len(first_fields) == len(second_fields)
        fields = []
        for (label, left), (_, right) in zip(first_fields, second_fields):
            assert left is None or right is None or left == right
            fields.append((label, left or right))
        return first_rows + second_rows, fields

    table = item["source"]
    rows = [None] if table is None else ROWS + ([] if table["only"] else CHILD_ROWS)
    fields = [(name, output_type(value)) for value, name in item["columns"]]
    result = [tuple(converted(value, row) for value, _ in item["columns"]) for row in rows]
    if item["distinct"]:
        result = list(set(result))
    return [list(row) for row in result], fields


def expected(item):
    rows, fields = rows_and_fields(item)
    return observation({"status": "rows", "fields": [[name, kind or "text"] for name, kind in fields], "rows": rows})
