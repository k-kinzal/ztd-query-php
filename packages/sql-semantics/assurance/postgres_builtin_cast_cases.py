"""Direct casts and an independent model of the PostgreSQL int4, int8 and bool conversions."""

from itertools import product
from random import Random

from postgres_int4_cases import EXCEPTION, integer, projection, source_for, source_role
from server_cases import CHILD_ROWS, ROWS, analyzed, node_key, observation, source_key


def leaf(value):
    if value is None:
        return ["null"]
    if type(value) is bool:
        return ["boolean", value]
    return integer(value)


def cast(target, operand):
    return ["builtin-cast", target, operand]


def wide(value):
    return ["wide-integer", str(value)]


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


def ordered_pair(tag, decimal, other, wide_first):
    left = project(tag, wide(decimal))
    right = project(tag, other)
    return union(left, right) if wide_first else union(right, left)


def pair_operand(kind):
    return {"int4": leaf(1), "unknown": leaf(None), "int8": wide(1)}[kind]


def wide_finite(tag, data):
    distincts = data["distinct"]
    for role, target, number, distinct in product(data["sources"], data["targetsForWide"], data["wideOperands"], distincts):
        yield project(tag, cast(target, wide(number)), role, distinct)
    for role, value, distinct in product(data["sources"], data["typedNarrowOperands"], distincts):
        yield project(tag, cast("int8", leaf(value)), role, distinct)
    for role, slot, distinct in product(["only", "inherited"], data["referenceSlots"], distincts):
        yield project(tag, cast("int8", ["slot", slot]), role, distinct)
    for number, distinct in product(data["wideOperands"], distincts):
        yield project(tag, wide(number), "unit", distinct)
    for number, kind, direction in product(data["pairWideOperands"], data["pairedTypes"], data["pairArmOrders"]):
        yield ordered_pair(tag, number, pair_operand(kind), direction == "wide-first")


def finite_id(_, response, _item):
    if "compound" in response:
        compound = response["compound"]
        left, right = (compound[side]["columns"][0][0] for side in ("left", "right"))
        return "pair", node_key(left), node_key(right), compound["all"]
    return source_key(response["source"]), node_key(response["columns"][0][0]), "", response["distinct"]


def finite_ids(_, specification, _tag):
    previous, current = specification["finite"], specification["wideFinite"]
    identities = set()
    for role, key in (("unit", "none"), ("only", "own"), ("inherited", "all")):
        for distinct in (False, True):
            atoms = [leaf(value) for value in previous["literalOperands"]]
            references = [] if role == "unit" else [["slot", slot] for slot in previous["referenceSlots"]]
            identities.update((key, node_key(cast(mode, value)), "", distinct)
                              for mode in ("int4", "bool") for value in atoms + references)
            identities.update((key, node_key(cast(mode, wide(decimal))), "", distinct)
                              for mode in ("int4", "int8") for decimal in current["wideOperands"])
            promoted = [leaf(value) for value in current["typedNarrowOperands"]] + references
            identities.update((key, node_key(cast("int8", value)), "", distinct) for value in promoted)
            if role == "unit":
                identities.update((key, node_key(wide(decimal)), "", distinct) for decimal in current["wideOperands"])
    for number in current["pairWideOperands"]:
        left = node_key(wide(number))
        for other in (["integer", "1"], ["null"], ["wide-integer", "1"]):
            right = node_key(other)
            identities.update([("pair", left, right, True), ("pair", right, left, True)])
    assert len(identities) == 300
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


def wide_seeded(tag, data, seed, count):
    rng = Random(seed)
    for _ in range(count):
        role = rng.choice(data["sources"])
        atoms = [wide(value) for value in data["wideOperands"]] + [leaf(None), leaf(True), leaf(False), leaf(rng.randrange(-100, 101))]
        if role != "unit":
            atoms.extend(["slot", slot] for slot in data["referenceSlots"])
        fields = []
        names = rng.choices(("wide", "value", 'quoted"int8', "桁"), k=rng.randrange(1, 6))
        for _ in names:
            expression = rng.choice(atoms)
            for _ in range(rng.randrange(7)):
                kind = output_type(expression)
                targets = ("int4", "int8", "bool")
                if kind in ("int8", "bool"):
                    targets = ("int4", kind)
                expression = cast(rng.choice(targets), expression)
            fields.append(expression)
        left = project(tag, fields, role, rng.choice(data["distinct"]), names)
        if rng.random() < 0.5:
            right = [wide(1) if output_type(expr) == "int8" else leaf(None) for expr in fields]
            yield union(left, project(tag, right, label=names))
        else:
            yield left


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

    yield "SELECT CAST(1 AS BIGINT)", project(tag, cast("int8", leaf(1)), label="int8")
    yield ("SELECT (2147483648) AS high, -(9223372036854775808) AS low, 0x8000_0000 AS hex, -0x8000_0000 AS small",
           project(tag, [wide(2147483648), wide(-(2 ** 63)), wide(2147483648), leaf(-(2 ** 31))], label=["high", "low", "hex", "small"]))
    yield ("SELECT 9223372036854775807 AS exact, NULL::BIGINT AS n",
           project(tag, [wide((2 ** 63) - 1), cast("int8", leaf(None))], label=["exact", "n"]))
    yield "SELECT CAST(-9223372036854775808 AS INTEGER) AS overflow", project(tag, cast("int4", wide(-(2 ** 63))), label="overflow")
    yield ("SELECT 1 AS result UNION ALL SELECT 2147483648",
           union(project(tag, leaf(1)), project(tag, wide(2147483648), label="?column?")))
    yield ("SELECT 2147483648 AS result UNION ALL SELECT 1",
           union(project(tag, wide(2147483648)), project(tag, leaf(1), label="?column?")))


def negatives(tag):
    yield project(tag, cast("bool", ["slot", 0])), "Column slot must belong to the SELECT source."
    too_deep = ["null"]
    for _ in range(33):
        too_deep = cast("bool", too_deep)
    yield project(tag, too_deep), "Expression height or expanded node count exceeds"
    yield analyzed("postgres", "SELECT CAST(1 AS SMALLINT)", version=tag), "The cast target must be pg_catalog.int4, pg_catalog.int8, pg_catalog.bool or pg_catalog.text."
    yield analyzed("postgres", "SELECT CAST(2147483648 AS BOOL)", version=tag), "no direct cast between bigint and boolean"
    yield analyzed("postgres", "SELECT CAST('o' AS BOOL)", version=tag), "The Boolean input contains an invalid token."

    for value in ("9223372036854775808", "-9223372036854775809"):
        yield project(tag, wide(value)), "outside the signed 64-bit range"
    for value in ("-0", "01"):
        yield project(tag, wide(value)), "Expected a normalized signed decimal bigint"
    for value in (cast("int8", leaf(True)), cast("bool", wide(1)), cast("bool", cast("int8", leaf(None))),
                  cast("int8", cast("bool", leaf(None)))):
        yield project(tag, value), "no direct cast between bigint and boolean"
    yield analyzed("postgres", "SELECT CAST(TRUE AS BIGINT)", version=tag), "no direct cast between bigint and boolean"
    yield analyzed("postgres", "SELECT CAST(NULL AS BIGINT)::pg_catalog.bool", version=tag), "no direct cast between bigint and boolean"
    yield analyzed("postgres", "SELECT 9223372036854775808", version=tag), "no complete PostgreSQL projection representation"
    yield union(project(tag, leaf(True)), project(tag, cast("int8", leaf(None)))), "PostgreSQL compound output types are incompatible"


def requests(_engine, tag, specification):
    data, exploration = specification["finite"], specification["exploration"]
    for index, item in enumerate([*finite(tag, data), *wide_finite(tag, specification["wideFinite"])]):
        yield f"finite/{index}", item, item, None
    for seed in exploration["seeds"]:
        for index, item in enumerate(seeded(tag, data, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    wide_exploration = specification["wideExploration"]
    for seed in wide_exploration["seeds"]:
        for index, item in enumerate(wide_seeded(tag, specification["wideFinite"], seed, wide_exploration["casesPerSeed"])):
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
    if role in ("integer", "wide-integer"):
        return int(expression[1])
    if role == "null":
        return None
    assert role == "builtin-cast"
    value = converted(expression[2], row)
    if value is None:
        return None
    if expression[1] == "bool":
        return value != 0
    number = int(value)
    if expression[1] == "int4" and not (-(2 ** 31) <= number < 2 ** 31):
        raise OverflowError
    return number


def output_type(expression):
    role = expression[0]
    if role == "builtin-cast":
        return expression[1]
    return {"integer": "int4", "wide-integer": "int8", "slot": "int4", "boolean": "bool", "null": None}[role]


def rows_and_fields(item):
    if "compound" in item:
        operation = item["compound"]
        assert operation["operator"] == "UNION" and operation["all"]
        first_rows, first_fields = rows_and_fields(operation["left"])
        second_rows, second_fields = rows_and_fields(operation["right"])
        assert len(first_fields) == len(second_fields)
        fields = []
        for (label, left), (_, right) in zip(first_fields, second_fields):
            if {left, right} == {"int4", "int8"}:
                fields.append((label, "int8"))
            else:
                assert left is None or right is None or left == right
                fields.append((label, left or right or "text"))
        return first_rows + second_rows, fields

    table = item["source"]
    rows = [None] if table is None else ROWS + ([] if table["only"] else CHILD_ROWS)
    fields = [(name, output_type(value)) for value, name in item["columns"]]
    result = [tuple(converted(value, row) for value, _ in item["columns"]) for row in rows]
    if item["distinct"]:
        result = list(set(result))
    return [list(row) for row in result], fields


def expected(item):
    try:
        rows, fields = rows_and_fields(item)
    except OverflowError:
        return {"status": "db-error", "state": "22003"}
    return observation({"status": "rows", "fields": [[name, kind or "text"] for name, kind in fields], "rows": rows})
