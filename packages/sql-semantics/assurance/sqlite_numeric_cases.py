"""Finite and generated observations for numeric scalar composition."""

from itertools import product
from random import Random

from run_sqlite_scalars import query
from sqlite_case_cases import DDL, NUMBERS
from sqlite_relational_cases import children
from sqlite_result_cases import integer


def operation(operator, left, right):
    return ["arithmetic", operator, left, right]


def finite_cases(domain):
    for root, operator, left, right in product(domain["roots"], domain["operators"], domain["atoms"], domain["atoms"]):
        yield query(root, [[operation(operator, left, right)]])


def finite_ids(domain):
    indices = range(len(domain["atoms"]))
    return set(product(domain["roots"], domain["operators"], indices, indices))


def finite_id(reply, domain):
    root = reply.get("operation", "select")
    _, operator, left, right = reply["rows"][0][0] if root == "values" else reply["columns"][0][0]
    return root, operator, domain["atoms"].index(left), domain["atoms"].index(right)


def seeded(domain, seed, count):
    rng = Random(seed)
    for _ in range(count):
        root = rng.choice(domain["roots"])
        source = root == "select" and rng.randrange(4) == 0
        atoms = domain["atoms"] + [integer(n) for n in (-65, -64, -63, -1, 1, 2, 63, 64, 65)]
        atoms += [["parameter", "2"], ["parameter", "8"], ["text", "off"], ["blob", "322E35"]]
        if source:
            atoms += [["column", 0]]

        def scalar(depth):
            if depth > 0 and rng.randrange(3):
                operator = rng.choice(domain["operators"])
                return operation(operator, scalar(depth - 1), scalar(depth - 1))
            return rng.choice(atoms)

        rows = [[operation(rng.choice(domain["operators"]), scalar(rng.randrange(4)), scalar(rng.randrange(4)))
                 for _ in range(rng.randrange(1, 5))]]
        item = query(root, rows)
        if source:
            item["source"] = NUMBERS
        item["bindings"] = {slot: rng.choice((None, -64, -1, 0, 1, 64, 2.5, "64", "2.5", "3tail", "none"))
                            for slot in item["bindings"]}
        yield item


def boundaries():
    min_int = integer(-(1 << 63))
    real = ["real", "35", "5", None, False]
    large_real = ["real", "1", None, "300", False]
    yield query("values", [[operation(operator, integer(-3), integer(distance)) for operator in ("<<", ">>")
                            for distance in (-65, -64, -63, 0, 63, 64, 65, -(1 << 63))]])
    yield query("select", [[operation("/", min_int, integer(-1)), operation("%", min_int, integer(-1)),
                            operation("%", real, integer(4)), operation("/", integer(0), ["real", "1", "0", None, True])]])
    yield query("select", [[operation("/", large_real, ["real", "1", None, "-300", False]), operation("%", large_real, integer(11))]])
    yield query("select", [[operation("%", ["blob", "31382E3061"], integer(5)),
                            operation("/", ["text", "2tail"], ["text", "off"]),
                            operation("|", ["blob", "2D3700FF"], ["text", " 2 "])]])


def analyzed():
    expr = operation("+", integer(2), operation("/", integer(11), operation("%", integer(8), integer(5))))
    yield "SELECT 2 + 11 / (8 % 5) AS result", [], query("select", [[expr]], ["result"])
    expr = operation("&", operation("<<", ["column", 0], ["parameter", "02"]), integer(15))
    item = query("select", [[expr]], ["bits"])
    item["source"] = NUMBERS
    yield "SELECT number << ?02 & 15 AS bits FROM ir_case_words", [DDL], item
    fields = [operation(">>", integer(-26), integer(-2)), operation("|", integer(1), integer(8))]
    yield "VALUES ((-26 >> (-2)), (1 | 8))", [], query("values", [fields])
    fields = [operation("%", ["parameter", "02"], integer(5)), operation("/", ["parameter", "2"], integer(3))]
    yield "SELECT ?02 % 5 AS rem, ?2 / 3 AS quo", [], query("select", [fields], ["rem", "quo"])


def negatives():
    for left, right in ((["column", 0], integer(1)), (integer(1), ["column", 0])):
        yield query("values", [[operation("/", left, right)]]), "rejected", "must belong to the statement source"
    item = query("select", [[operation(">>", ["column", 0, 1], integer(1))]])
    item["source"] = NUMBERS
    yield item, "rejected", "at a visible occurrence"
    term = integer(1)
    for _ in range(33):
        term = operation("&", term, integer(2))
    yield query("select", [[term]]), "rejected", "Expression height or expanded node count"


def forms(reply):
    root = reply.get("operation", "select")
    yield "root/" + root
    terms = (expr for row in reply["rows"] for expr in row) if root == "values" else (expr for expr, _ in reply["columns"])
    for expression in terms:
        for operand in children(expression):
            if operand[0] != "arithmetic":
                yield "operand/" + operand[0]
                continue
            yield "operator/" + operand[1]
            if any(child[0] == "arithmetic" for child in operand[2:]):
                yield "nested"
