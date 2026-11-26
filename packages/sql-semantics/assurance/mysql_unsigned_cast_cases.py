"""Generate and interpret unsigned MySQL conversions and typed UNION arms."""

import itertools
import random

from mysql_signed_cast_cases import EXCEPTION, cast as signed, leaf as narrow, project, role_of
from server_cases import ROWS, analyzed, evaluate, node_key, observation
from server_compound_cases import case as compound, legacy

MINIMUM = -(1 << 63)
MAXIMUM = (1 << 63) - 1
MASK = (1 << 64) - 1
BIGINT, DECIMAL = 8, 246
VALUES = {
    "minimum": MINIMUM, "minus-one": -1, "zero": 0, "positive": 7,
    "maximum": MAXIMUM, "null": None, "truth": True,
}
S, U = "signed-cast", "unsigned-cast"
PATTERNS = {
    "unsigned": (U,), "unsigned-unsigned": (U, U),
    "unsigned-signed-unsigned": (U, S, U), "signed-unsigned": (S, U),
}


def leaf(value):
    if type(value) is int and (value < -(1 << 31) or value > (1 << 31) - 1):
        return ["range-integer", str(value), 0, False]
    return narrow(value)


def unsigned(operand):
    return [U, operand]


def conversion(pattern, operand):
    for target in reversed(pattern):
        operand = [target, operand]
    return operand


ARMS = {
    "unsigned-bound": unsigned(leaf(-1)), "unsigned-zero": unsigned(leaf(0)), "unsigned-null": unsigned(leaf(None)),
    "signed-minimum": signed(leaf(MINIMUM)), "signed-positive": signed(leaf(17)), "signed-zero": signed(leaf(0)),
    "untyped-null": leaf(None),
}


def one(tag, operand, role="unit", distinct=False, name="result"):
    return {**project(tag, [operand], [name], role, distinct), "orderBy": [], "limit": None}


def combine(tag, a, b, all_rows=True):
    return compound("mysql", tag, a, "UNION", all_rows, b)


def arms(tag, left, right, all_rows):
    return combine(tag, one(tag, ARMS[left]), one(tag, ARMS[right], name="right_result"), all_rows)


def finite_ids(_, specification, _tag):
    domains = specification["finite"]
    p, c = domains["projections"], domains["compounds"]
    expected = {("projection", role, distinct, operand, pattern)
                for role, distinct, operand, pattern in itertools.product(p["sources"], p["distinct"], p["inputs"], p["patterns"])}
    arm_set = set(c["arms"])
    signed_set = arm_set - set(c["unsignedArms"])
    pairs = set(itertools.product(arm_set, repeat=2)) - set(itertools.product(signed_set, repeat=2))
    expected.update(("compound", a, b, all_rows) for a, b in pairs for all_rows in c["all"])
    return expected


def finite_id(_, reply, _item):
    if "compound" in reply:
        pair = reply["compound"]
        names = [next(name for name, operand in ARMS.items() if operand == pair[side]["columns"][0][0])
                 for side in ("left", "right")]
        return ("compound", *names, pair["all"])
    expr = reply["columns"][0][0]
    prefix = []
    while expr[0] in (S, U):
        prefix.append(expr[0])
        expr = expr[1]
    cast = next(name for name, pattern in PATTERNS.items() if pattern == tuple(prefix))
    operand = next(name for name, value in VALUES.items() if node_key(leaf(value)) == node_key(expr))
    return "projection", role_of(reply["source"]), reply["distinct"], operand, cast


def finite(tag, domain):
    p, c = domain["projections"], domain["compounds"]
    for role, distinct, operand, pattern in itertools.product(p["sources"], p["distinct"], p["inputs"], p["patterns"]):
        yield one(tag, conversion(PATTERNS[pattern], leaf(VALUES[operand])), role, distinct)
    for left, right, all_rows in itertools.product(c["arms"], c["arms"], c["all"]):
        if left in c["unsignedArms"] or right in c["unsignedArms"]:
            yield arms(tag, left, right, all_rows)


def seeded(tag, seed, count):
    rng = random.Random(seed)
    for index in range(count):
        role = rng.choice(("unit", "table"))
        columns = []
        width = 1 + index % 6
        inputs = [leaf(value) for value in VALUES.values()]
        small = [leaf(value) for value in range(-7, 8)]
        if role == "table":
            refs = [["slot", 0], ["slot", 1]]
            small.extend(refs)
            inputs.extend(refs)
        names = rng.choices(("value", "label`op", "符号", "value"), k=width)
        for _ in names:
            value = rng.choice(inputs)
            if rng.randrange(4) == 0:
                value = ["operation", rng.choice(("+", "-", "*")), rng.choice(small), rng.choice(small)]
            value = unsigned(value)
            for _ in range(rng.randrange(6)):
                value = [rng.choice((S, U)), value]
            columns.append(value)
        yield project(tag, columns, names, role, rng.choice((False, True)))


def original_cases(tag):
    yield "SELECT CAST(1 AS UNSIGNED)", one(tag, unsigned(leaf(1)), name="CAST(1 AS UNSIGNED)")
    yield "SELECT CAST( Id AS UNSIGNED ), convert( delta, unsigned integer ) FROM ir_fixture.ir_evidence_accounts", project(
        tag, [unsigned(["slot", 0]), unsigned(["slot", 1])], ["CAST( Id AS UNSIGNED )", "convert( delta, unsigned integer )"], "table")
    yield "SELECT CONVERT(NULL, UNSIGNED INT), CAST(TRUE AS UNSIGNED)", project(
        tag, [unsigned(leaf(None)), unsigned(leaf(True))], ["CONVERT(NULL, UNSIGNED INT)", "CAST(TRUE AS UNSIGNED)"])
    yield "SELECT CAST(CAST(-1 AS UNSIGNED) AS SIGNED) AS result", one(tag, signed(unsigned(leaf(-1))))
    yield "SELECT CAST((9223372036854775807 + 1) AS UNSIGNED) AS result", one(
        tag, unsigned(["operation", "+", leaf(MAXIMUM), leaf(1)]))
    yield "SELECT CAST(-1 AS UNSIGNED) AS result UNION ALL SELECT CAST(-2 AS SIGNED) AS rhs", combine(
        tag, one(tag, unsigned(leaf(-1))), one(tag, signed(leaf(-2)), name="rhs"))
    yield "SELECT CAST(-2 AS SIGNED) AS result UNION ALL SELECT CAST(-1 AS UNSIGNED) AS rhs", combine(
        tag, one(tag, signed(leaf(-2))), one(tag, unsigned(leaf(-1)), name="rhs"))
    yield ("SELECT CAST(-1 AS UNSIGNED) AS result UNION ALL SELECT CAST(-2 AS SIGNED) AS rhs "
           "UNION ALL SELECT CAST(-1 AS UNSIGNED) AS third"), combine(
        tag, combine(tag, one(tag, unsigned(leaf(-1))), one(tag, signed(leaf(-2)), name="rhs")),
        one(tag, unsigned(leaf(-1)), name="third"))


def negatives(tag):
    yield one(tag, unsigned(["slot", 2]), "table"), "Column slot must belong to the SELECT source."
    yield one(tag, conversion((U,) * 33, leaf(1))), "Expression height or expanded node count exceeds"
    for sql, reason in (
        ("SELECT CAST(CAST(? AS UNSIGNED) AS UNSIGNED)",
         "A marker under another integral conversion is outside the projected parameter role."),
        ("SELECT CAST('input' AS UNSIGNED)", "The operand has no complete MySQL projection representation."),
        ("SELECT CAST(1 BETWEEN 0 AND 2 AS UNSIGNED)", "An integer cast requires a checked integral scalar input."),
    ):
        yield analyzed("mysql", sql, version=tag), reason


def requests(_engine, tag, specification):
    for index, item in enumerate(finite(tag, specification["finite"])):
        yield f"finite/{index}", item, item, None
    settings = specification["exploration"]
    for seed in settings["seeds"]:
        for index, item in enumerate(seeded(tag, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (sql, item) in enumerate(original_cases(tag)):
        yield f"analysis/{index}", analyzed("mysql", sql, version=tag), item, None
    for index, (item, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, {"exception": EXCEPTION, "message": reason}


def scalar(expr, row, release):
    role = expr[0]
    if role not in (S, U):
        return evaluate(expr, row, "mysql", release)
    value = scalar(expr[1], row, release)
    if value is None:
        return None
    if role == U:
        return value & MASK
    return value if value < (1 << 63) else value - (1 << 64)


def names_and_kinds(item):
    if "compound" not in item:
        return [(name, {"null": "unknown", U: "uint64"}.get(expr[0], "int64")) for expr, name in item["columns"]]
    data = item["compound"]
    left, right = names_and_kinds(data["left"]), names_and_kinds(data["right"])
    result = []
    for (name, a), (_, b) in zip(left, right):
        if "decimal-integer" in (a, b):
            kind = "decimal-integer"
        elif "uint64" in (a, b):
            if legacy(item["release"]):
                kind = "uint64" if a == "uint64" else "int64"
            else:
                kind = "decimal-integer" if "int64" in (a, b) else "uint64"
        else:
            kind = "int64" if "int64" in (a, b) else "unknown"
        result.append((name, kind))
    return result


def rows(item):
    if "compound" not in item:
        source = [None] if item["source"] is None else ROWS
        result = [tuple(scalar(expression, row, item["release"]) for expression, _ in item["columns"]) for row in source]
        return list(set(result)) if item["distinct"] else result
    node = item["compound"]
    bounded = legacy(item["release"])
    roles = [kind for _, kind in names_and_kinds(item)]
    result = []
    for inputs in (rows(node["left"]), rows(node["right"])):
        for row in inputs:
            result.append(tuple(
                min(max(value, 0), MASK) if bounded and value is not None and kind == "uint64"
                else min(max(value, MINIMUM), MAXIMUM) if bounded and value is not None and kind == "int64" else value
                for value, kind in zip(row, roles)
            ))
    return result if node["all"] else list(set(result))


def observe_value(value, kind):
    if value is None:
        return None
    if kind == "decimal-integer" or (kind == "uint64" and value > MAXIMUM):
        return str(value)
    return value


def expected(item):
    roles = names_and_kinds(item)
    try:
        result = [[observe_value(value, role) for value, (_, role) in zip(row, roles)] for row in rows(item)]
    except OverflowError:
        return {"status": "db-error", "state": "22003"}
    type_codes = {"unknown": (6, False), "uint64": (BIGINT, True),
                  "int64": (BIGINT, False), "decimal-integer": (DECIMAL, False)}
    return observation({"status": "rows", "fields": [[name, *type_codes[role]] for name, role in roles], "rows": result})
