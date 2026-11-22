"""Independent Boolean tests and witnesses that distinguish truth-word binding."""

from itertools import product
from random import Random

from run_sqlite_scalars import evaluate, query
from run_sqlite_values import names
from sqlite_engine import typed
from sqlite_group_filter_cases import grouped
from sqlite_grouped_cases import FIXTURE as GROUPS, SCHEMA as GROUP_DDL, count, source as group_source
from sqlite_relational_cases import children, parameter_names
from sqlite_result_cases import integer

SHADOW = ("value", "true", "false")
TABLE = "ir_truth_shadow"
DDL = f"CREATE TABLE main.{TABLE} (value BLOB, true BLOB, false BLOB)"
DATA = ((7, 3, 8), (3, 3, 7), (None, None, None), (0, 9, None))
FIXTURE = GROUPS + ";" + DDL + f";INSERT INTO main.{TABLE} VALUES (7,3,8),(3,3,7),(NULL,NULL,NULL),(0,9,NULL);"


def col(slot):
    return ["column", slot]


def test(operand, desired=True, negated=False):
    return ["truth", desired, negated, operand]


def finite_cases(domain):
    for root, desired, negated, operand in product(domain["roots"], domain["truthValues"], domain["negated"], domain["operands"]):
        yield query(root, [[test(operand, desired, negated)]])


def finite_id(reply, domain):
    root = reply.get("operation", "select")
    tag, desired, negated, operand = reply["rows"][0][0] if root == "values" else reply["columns"][0][0]
    assert tag == "truth"
    return root, desired, negated, domain["operands"].index(operand)


def finite_ids(domain):
    return set(product(domain["roots"], domain["truthValues"], domain["negated"], range(len(domain["operands"]))))


def seeded(domain, seed, repetitions):
    rng = Random(seed)
    for _ in range(repetitions):
        root = rng.choice(domain["roots"])
        source = ["main", TABLE, ["value"]] if root == "select" and rng.randrange(3) == 0 else None
        atoms = domain["operands"] + [["parameter", "2"], ["parameter", "7"], ["text", "no"], ["blob", "2D32"]]
        if source is not None:
            atoms += [col(0)]

        def scalar(height):
            if not height:
                return rng.choice(atoms)
            if rng.randrange(3) == 0:
                return ["unary", rng.choice(("+", "NOT", "~")), scalar(height - 1)]
            if rng.randrange(3) == 0:
                return ["arithmetic", rng.choice(("-", "+", "%")), scalar(height - 1), scalar(height - 1)]
            return test(scalar(height - 1), bool(rng.getrandbits(1)), bool(rng.getrandbits(1)))

        width = rng.randrange(1, 5)
        height = 1 if root == "select" else rng.randrange(1, 4)
        item = query(root, [[test(scalar(rng.randrange(1, 5)), bool(rng.getrandbits(1)), bool(rng.getrandbits(1)))
                             for _ in range(width)] for _ in range(height)])
        if source is not None:
            item["source"] = source
        item["bindings"] = {slot: rng.choice((None, 0, 8, -4, 0.5, "2tail", "off")) for slot in item["bindings"]}
        yield item


def deep(level):
    term = integer(0)
    for _ in range(level):
        term = test(term)
    return term


def shadow_witness():
    item = query("select", [[col(0), test(col(0))]], ["value", "boolean_result"])
    item["source"] = ["main", TABLE, ["value"]]
    return item


def boundaries():
    yield query("select", [[deep(32)]])
    yield shadow_witness()
    yield query("values", [[test(["parameter", "02"], False, True), test(["parameter", "2"], True, True)]])


def analyzed():
    yield ("SELECT TRUE AS t, false AS f, 7 IS TRUE AS truth, 7 IS +TRUE AS eq, 7 IS ((TRUE)) AS grouped",
           [], query("select", [[integer(1), integer(0), test(integer(7)), ["comparison", "IS", integer(7), ["unary", "+", integer(1)]],
                                 test(integer(7))]], ["t", "f", "truth", "eq", "grouped"]))
    yield ("SELECT NULL IS FALSE AS f, NULL IS NOT FALSE AS nf, '1z' IS NOT DISTINCT FROM TRUE AS positive, 0 IS DISTINCT FROM FALSE AS differs",
           [], query("select", [[test(["null"], False), test(["null"], False, True), test(["text", "1z"]),
                                 test(integer(0), False, True)]], ["f", "nf", "positive", "differs"]))
    item = query("select", [[col(0), ["comparison", "IS", col(1), col(0)], ["comparison", "IS NOT", col(1), col(2)]]],
                 ["constant_or_column", "same_true", "distinct_false"])
    item["source"] = ["main", TABLE, ["true", "value", "false"]]
    yield ("SELECT TRUE AS constant_or_column, value IS TRUE AS same_true, value IS NOT FALSE AS distinct_false FROM ir_truth_shadow",
           [DDL], item)
    operand = ["parameter", "02"]
    yield ("VALUES (?02 IS TRUE, ?2 IS NOT TRUE, NOT FALSE, NULL IS NOT TRUE)", [],
           query("values", [[test(operand), test(["parameter", "2"], True, True), ["unary", "NOT", integer(0)], test(["null"], True, True)]]))
    bag = group_source("bag", ())
    fields = [[count(), "n"]]
    for sql_test, comparator in (
        ("count(*) IS TRUE", [count(), "<>", integer(0), False]),
        ("count(*) IS NOT FALSE", [count(), "<>", integer(0), False]),
        ("TRUE IS count(*)", [count(), "IS", integer(1), True]),
    ):
        yield (f"SELECT count(*) AS n FROM main.ir_group_bag HAVING {sql_test}", list(GROUP_DDL),
               grouped(bag, [], fields, None, comparator))
    yield ("SELECT count(*) AS n FROM main.ir_group_empty HAVING count(*) IS FALSE", list(GROUP_DDL),
           grouped(group_source("empty", ()), [], fields, None, [count(), "=", integer(0), False]))


def negatives():
    yield query("select", [[test(col(0))]]), "rejected", "must belong to the statement source"
    yield query("values", [[test(["unary", "+", col(0)])]]), "rejected", "must belong to the statement source"
    yield ({"sql": "SELECT a.value IS TRUE FROM ir_truth_shadow AS a JOIN ir_truth_shadow AS b", "declarations": [DDL]},
           "analysis-rejected", "Column true is ambiguous.")
    yield query("select", [[deep(33)]]), "rejected", "Expression height or expanded node count"


def expected(item):
    bindings = item["bindings"]
    if item.get("operation") == "values":
        operands = item["rows"]
        labels = [f"column{index+1}" for index in range(len(operands[0]))]
        parameters = names(operands)
        rows = [()]
    else:
        operands = [[term for term, _ in item["columns"]]]
        labels = [label for _, label in item["columns"]]
        parameters = parameter_names(item)
        source = item["source"]
        rows = [()] if source is None else [tuple(row[SHADOW.index(name)] for name in source[2]) for row in DATA]
    observed = [[typed(evaluate(expression, bindings, row)) for expression in fields] for row in rows for fields in operands]
    return {"names": labels, "parameters": parameters, "parameterCount": max(map(int, parameters), default=0),
            "rows": sorted(observed), "readOnly": True, "changes": 0}


def forms(reply):
    root = reply.get("operation", "select")
    yield "root/" + root
    operands = (expr for row in reply["rows"] for expr in row) if root == "values" else (expr for expr, _ in reply["columns"])
    for term in operands:
        for operand in children(term):
            if operand[0] == "truth":
                yield f"truth/{operand[1]}/{operand[2]}"
                yield "input/" + operand[3][0]
                if operand[3][0] == "truth":
                    yield "nested"
