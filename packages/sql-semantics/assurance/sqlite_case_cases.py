"""Construct CASE requests independently and interpret their typed observations."""

from itertools import product
from random import Random
from string import ascii_lowercase, ascii_uppercase

from run_sqlite_scalars import evaluate, query
from run_sqlite_values import names
from sqlite_cast_semantics import comparison_values
from sqlite_engine import typed
from sqlite_relational_cases import children, order_value, parameter_names, truth
from sqlite_result_cases import integer

DDL = "CREATE TABLE ir_case_words (word TEXT COLLATE NOCASE, number INTEGER);"
FIXTURE = DDL + "INSERT INTO ir_case_words VALUES ('x',7),('X',7),('no',3),(NULL,NULL);"
ROWS = [("x", 7), ("X", 7), ("no", 3), (None, None)]
WORDS = ["main", "ir_case_words", ["word"]]
NUMBERS = ["main", "ir_case_words", ["number"]]
LOWER = str.maketrans(ascii_uppercase, ascii_lowercase)


def choice(base, branches, fallback=None):
    return ["case", base, branches, fallback]


def finite_cases(domain):
    for target, base, first, second, fallback in product(
            domain["targets"], domain["bases"], domain["decisions"], domain["decisions"], domain["fallbacks"]):
        expression = choice(base, [[first, integer(7)], [second, ["blob", "00"]]], fallback)
        yield query(target, [[expression]])


def finite_ids(domain):
    width = len(domain["decisions"])
    return set(product(domain["targets"], range(len(domain["bases"])), range(width), range(width), range(len(domain["fallbacks"]))))


def finite_id(response, domain):
    target = response.get("operation", "select")
    operand = response["rows"][0][0] if target == "values" else response["columns"][0][0]
    _, base, branches, fallback = operand
    return (target, domain["bases"].index(base), domain["decisions"].index(branches[0][0]),
            domain["decisions"].index(branches[1][0]), domain["fallbacks"].index(fallback))


def seeded(domain, seed, count):
    rng = Random(seed)
    atoms = domain["decisions"] + [integer(-3), ["blob", "FF"], ["blob", "31"], ["text", "0x"],
                                  ["text", "2junk"], ["parameter", "2"], ["parameter", "02"], ["parameter", "7"]]

    def choice_operand(depth):

        def child():
            return scalar(depth - 1)

        return choice(None if rng.getrandbits(1) else child(),
                      [[child(), child()] for _ in range(rng.randint(1, 5))],
                      None if rng.getrandbits(1) else child())

    def scalar(depth):
        if not depth:
            return rng.choice(atoms)
        kind = rng.randrange(5)
        if kind == 0:
            return choice_operand(depth)
        if kind == 1:
            return ["arithmetic", "-", scalar(depth - 1), scalar(depth - 1)]
        if kind == 2:
            return ["comparison", "IS", scalar(depth - 1), scalar(depth - 1)]
        return rng.choice(atoms)

    for _ in range(count):
        target = rng.choice(domain["targets"])
        width = rng.randint(1, 3)
        height = 1 if target == "select" else rng.randint(1, 3)
        operands = [[choice_operand(rng.randint(1, 3)) for _ in range(width)] for _ in range(height)]
        item = query(target, operands)
        item["bindings"] = {slot: rng.choice((None, 0, 1, 2.5, "2x", "off")) for slot in item["bindings"]}
        yield item


def analyzed():
    test = choice(None, [[integer(1), ["parameter", "02"]], [["parameter", "8"], ["parameter", "2"]]],
                  ["parameter", "9"])
    yield ("SELECT CASE WHEN 1 THEN ?02 WHEN ?8 THEN ?2 ELSE ?9 END AS p", [], query("select", [[test]], ["p"]))
    expression = choice(["column", 0], [[["text", "X"], integer(9)]], integer(0))
    item = query("select", [[expression]], ["label"])
    item["source"] = WORDS
    yield "SELECT CASE word WHEN 'X' THEN 9 ELSE 0 END AS label FROM ir_case_words", [DDL], item
    expression = choice(["text", "7"], [[["column", 0], ["blob", "77"]]])
    item = query("select", [[expression]], ["value"])
    item["source"] = NUMBERS
    yield "SELECT CASE '7' WHEN number THEN X'77' END AS value FROM ir_case_words", [DDL], item
    item = query("select", [[["column", 0]]], ["word"])
    item["source"] = WORDS
    item["where"] = choice(["column", 0], [[["text", "X"], integer(1)]])
    yield "SELECT word FROM ir_case_words WHERE CASE word WHEN 'X' THEN 1 END", [DDL], item


def boundaries():
    branches = [[integer(0), integer(1)] for _ in range(31)] + [[integer(1), integer(2)]]
    yield query("select", [[choice(None, branches)]])
    deep = integer(3)
    for _ in range(32):
        deep = choice(None, [[integer(1), deep]])
    yield query("select", [[deep]])
    item = query("select", [[choice(None, [[integer(1), ["text", "left"]], [integer(1), ["text", "right"]]])]])
    item["source"] = WORDS
    item["where"] = choice(None, [[["column", 0], integer(1)]])
    yield item


def negatives():
    yield query("select", [[choice(None, [])]]), "rejected", "at least one checked branch"
    yield query("select", [[choice(None, [[integer(1), integer(2)]] * 33)]]), "rejected", "at most 32 branches"
    yield query("values", [[choice(None, [[integer(0), ["column", 0]]])]]), "rejected", "must belong to the statement source"
    deep = integer(1)
    for _ in range(33):
        deep = choice(None, [[integer(1), deep]])
    yield query("select", [[deep]]), "rejected", "Expression height or expanded node count"
    yield ({"sql": "SELECT CASE WHEN 1 THEN (1, 2) END AS n", "declarations": []},
           "analysis-rejected", "row value misused")


def equality(source, base, when, value, decision):
    if decision is None:
        return False
    if any(operand[0] == "column" for operand in (base, when)):
        if source == WORDS and type(value) is str and type(decision) is str:
            return value.translate(LOWER) == decision.translate(LOWER)
        if source == NUMBERS:
            if type(value) is str:
                value = int(value)
            if type(decision) is str:
                decision = int(decision)
    value, decision = comparison_values(base, when, value, decision)
    return order_value(value) == order_value(decision)


def expected(request, comparison_inputs=comparison_values):
    bindings = request["bindings"]
    if request.get("operation") == "values":
        sources = [()]
        operands = request["rows"]
        labels = [f"column{index + 1}" for index in range(len(operands[0]))]
        first = names(operands)
        predicate = None
        source = None
    else:
        source = request["source"]
        sources = [()] if source is None else [(row[0] if source == WORDS else row[1],) for row in ROWS]
        operands = [[expr for expr, _ in request["columns"]]]
        labels = [label for _, label in request["columns"]]
        first = parameter_names(request)
        predicate = request["where"]

    def compare(base, when, value, decision):
        return equality(source, base, when, value, decision)

    rows = [[typed(evaluate(expr, bindings, row, compare, comparison_inputs)) for expr in fields]
            for row in sources if predicate is None or truth(evaluate(predicate, bindings, row, compare, comparison_inputs)) is True
            for fields in operands]
    return {"names": labels, "parameters": first, "parameterCount": max(map(int, first), default=0),
            "rows": sorted(rows), "readOnly": True, "changes": 0}


def forms(response):
    target = response.get("operation", "select")
    yield "target/" + target
    roots = (expr for row in response["rows"] for expr in row) if target == "values" else (
        expr for expr, _ in response["columns"])
    for root in roots:
        for operand in children(root):
            if operand[0] == "case":
                yield "arity/" + ("one" if len(operand[2]) == 1 else "multiple")
                yield "base/" + ("absent" if operand[1] is None else "present")
                yield "fallback/" + ("absent" if operand[3] is None else "present")
                siblings = (child for child in children(operand) if child[0] == "case")
                next(siblings)
                if next(siblings, None) is not None:
                    yield "nested"
            else:
                yield "operand/" + operand[0]
