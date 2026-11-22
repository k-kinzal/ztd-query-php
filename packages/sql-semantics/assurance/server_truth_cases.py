"""Enumerate truth-table inputs and explore nested native predicate structures."""

from itertools import product
from random import Random

from server_compound_cases import leaf
from server_delete_cases import literal
from server_join_cases import declarations, join, slot, table


VALUES = (["boolean", True], ["boolean", False], ["null"])
MASKS = ("admitted", "unknown")
BINARY = ("AND", "OR")
UNARY = ("NOT", "IS NULL", "IS NOT NULL")


def logic(symbol, left, right=None):
    return ["logic", symbol, left, right]


def null_test(operand, negated=False):
    return ["null-test", negated, operand]


def binary(engine):
    return (*BINARY, "XOR") if engine == "mysql" else BINARY


def unary(symbol, operand):
    return logic(symbol, operand) if symbol == "NOT" else null_test(operand, symbol == "IS NOT NULL")


def scalar(engine, tag, condition, output="admitted"):
    return leaf(engine, tag, [[literal(7), output]], where=condition)


def finite_cases(engine, tag):
    fixed = [
        *(unary(symbol, value) for symbol, value in product(UNARY, VALUES)),
        *(logic(symbol, left, right) for symbol, left, right in product(binary(engine), VALUES, VALUES)),
    ]
    for predicate, output in product(fixed, MASKS):
        yield scalar(engine, tag, predicate if output == MASKS[0] else null_test(predicate), output)


def finite_id(_engine, response, _item):
    [(_, output)] = response["columns"]
    condition = response["where"]
    if output == MASKS[1]:
        kind, negated, condition = condition
        if kind != "null-test" or negated:
            raise ValueError("The observed unknown mask must apply IS NULL")
    if condition[0] == "null-test":
        _, negated, operand = condition
        return output, "IS NOT NULL" if negated else "IS NULL", VALUES.index(operand), -1
    kind, symbol, left, right = condition
    if kind != "logic" or (right is None) != (symbol == "NOT"):
        raise ValueError("An observed finite identity has an invalid operator arity")
    return output, symbol, VALUES.index(left), VALUES.index(right) if right is not None else -1


def finite_ids(engine, specification, _tag):
    finite = specification["finite"]
    masks = ("admitted", "unknown")
    values = range(len(finite["values"]))
    return {
        *((mask, op, value, -1) for mask, op, value in product(masks, finite["unary"], values)),
        *product(masks, finite["binary"][engine], values, values),
    }


def seeded(engine, tag, seed, count):
    rng = Random(seed)

    def tree(depth, width):
        atoms = list(VALUES)
        if width:
            reference = slot(rng.randrange(2), rng.randrange(width))
            atoms += [null_test(reference, bool(rng.getrandbits(1))), ["comparison", rng.choice(("=", "<", ">")), reference, literal(rng.randrange(-2, 3))]]
        if depth == 0 or rng.randrange(4) == 0:
            return rng.choice(atoms)
        lhs = tree(depth - 1, width)
        operator = rng.choice((*binary(engine), *UNARY))
        return logic(operator, lhs, tree(depth - 1, width)) if operator in binary(engine) else unary(operator, lhs)

    for _ in range(count):
        width = rng.randrange(3)
        output = [[literal(8), "absent"]] if width == 0 else [[slot(rng.randrange(2), rng.randrange(width)), "present"]]
        source = None if width == 0 else table(engine, "accounts")
        if width == 2:
            on = tree(rng.randrange(1, 6), width)
            source = join(rng.choice(("INNER", "LEFT", "RIGHT")), source, table(engine, rng.choice(("independent", "empty"))), on)
        yield leaf(engine, tag, output, source, tree(rng.randrange(2, 8), width))


def analyzed(engine, tag):
    left, right = table(engine, "accounts"), table(engine, "independent")
    name = left["columns"][0]
    qualified = lambda source: source["schema"] + "." + source["table"]
    edge = logic("AND", ["comparison", "<", slot(0), slot(1, 1)], null_test(slot(0, 1), True))
    left_input = {**left, "columns": [name]}
    right_input = {**right, "columns": ["delta", name]}
    right_label = [[slot(0, 1), "v"]]
    forms = [
        (f"SELECT 7 AS admitted FROM {qualified(left)} WHERE NOT ({name}<2 AND delta IS NULL)",
         leaf(engine, tag, [[literal(7), "admitted"]], left,
              logic("NOT", logic("AND", ["comparison", "<", slot(0), literal(2)], null_test(slot(1)))))),
        ("SELECT 7 AS admitted WHERE (FALSE OR NULL) IS NULL" if engine != "mysql" else
         "SELECT 7 AS admitted FROM DUAL WHERE (FALSE OR NULL) IS NULL",
         scalar(engine, tag, null_test(logic("OR", VALUES[1], VALUES[2])))),
        (f"SELECT b.delta AS v FROM {qualified(left)} AS a LEFT JOIN {qualified(right)} AS b ON a.{name} < b.{name} AND b.delta IS NOT NULL",
         leaf(engine, tag, right_label, join("LEFT", left_input, right_input, edge))),
    ]
    for sql, model in forms:
        yield {"dialect": engine, "release": tag, "declarations": declarations(engine), "sql": sql}, model


def negatives(engine, tag):
    yield scalar(engine, tag, logic("AND", VALUES[0])), "Only logical NOT has one operand"
    yield scalar(engine, tag, logic("NOT", VALUES[0], VALUES[1])), "Only logical NOT has one operand"
    yield scalar(engine, tag, logic("NOT", null_test(slot(0)))), "predicate slot must belong"
    source = table(engine, "accounts")
    yield leaf(engine, tag, [[literal(7), "admitted"]], source, logic("OR", VALUES[0], null_test(slot(2)))), "predicate slot must belong"
    expression = VALUES[0]
    for _ in range(33):
        expression = logic("NOT", expression)
    yield scalar(engine, tag, expression), "Predicate depth or expanded node count"
    expression = VALUES[0]
    for _ in range(13):
        expression = logic("AND", expression, expression)
    yield scalar(engine, tag, expression), "Predicate depth or expanded node count"


def boundaries(engine, tag):
    expression = VALUES[0]
    for _ in range(32):
        expression = logic("NOT", expression)
    yield scalar(engine, tag, expression)
    expression = VALUES[0]
    for _ in range(12):
        expression = logic("AND", expression, expression)
    yield scalar(engine, tag, logic("NOT", expression))


def requests(engine, tag, specification):
    for i, item in enumerate(finite_cases(engine, tag)):
        yield f"finite/{i}", item, item, None
    for seed in specification["exploration"]["seeds"]:
        for i, item in enumerate(seeded(engine, tag, seed, specification["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{i}", item, item, None
    for i, (request, model) in enumerate(analyzed(engine, tag)):
        yield f"analyzed/{i}", request, model, None
    for i, item in enumerate(boundaries(engine, tag)):
        yield f"boundary/{i}", item, item, None
    for i, (request, signal) in enumerate(negatives(engine, tag)):
        yield f"negative/{i}", request, request, signal
