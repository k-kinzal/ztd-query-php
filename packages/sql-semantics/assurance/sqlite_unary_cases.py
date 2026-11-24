"""Generate prefix and NULL operators and distinguish their affinity behavior."""

from itertools import product
from random import Random

from run_sqlite_scalars import query
from sqlite_case_cases import DDL, LOWER, NUMBERS, WORDS, expected as scalar_results
from sqlite_cast_semantics import REAL_PREFIX, SPACES, comparison_values, numeric_value
from sqlite_relational_cases import children
from sqlite_result_cases import integer

PREFIX = {"plus": "+", "negate": "-", "not": "NOT", "complement": "~"}
TESTS = {"is-null": "IS", "is-not-null": "IS NOT"}


def expression(operator, operand):
    if operator in PREFIX:
        return ["unary", PREFIX[operator], operand]
    return ["comparison", TESTS[operator], operand, ["null"]]


def finite_cases(domain):
    for root, operator, atom in product(domain["roots"], domain["operators"], domain["atoms"]):
        yield query(root, [[expression(operator, atom)]])


def finite_id(reply, domain):
    root = reply.get("operation", "select")
    term = reply["rows"][0][0] if root == "values" else reply["columns"][0][0]
    role = next(name for name in domain["operators"] if expression(name, term[-1] if term[0] != "comparison" else term[2]) == term)
    atom = term[2] if term[0] == "comparison" else term[-1]
    return root, role, domain["atoms"].index(atom)


def finite_ids(domain):
    return set(product(domain["roots"], domain["operators"], range(len(domain["atoms"]))))


def seeded(domain, seed, count):
    rng = Random(seed)
    for _ in range(count):
        root = rng.choice(domain["roots"])
        source = rng.choice((None, NUMBERS, WORDS)) if root == "select" else None
        atoms = domain["atoms"] + [["parameter", "2"], ["parameter", "8"], ["text", "off"], ["blob", "322E35"]]
        if source is not None:
            atoms += [["column", 0]]

        def scalar(depth):
            return expression(rng.choice(domain["operators"]), scalar(depth - 1)) if depth else rng.choice(atoms)

        width, height = rng.randint(1, 5), 1 if root == "select" else rng.randint(1, 3)
        item = query(root, [[scalar(rng.randint(1, 6)) for _ in range(width)] for _ in range(height)])
        if source is not None:
            item["source"] = source
        item["bindings"] = {slot: rng.choice((None, -3, -0.0, 1, 2.5, "9", "3tail", "none")) for slot in item["bindings"]}
        yield item


def deep(level):
    term = integer(1)
    for _ in range(level):
        term = expression("not", term)
    return term


def boundaries():
    yield query("select", [[deep(32)]])
    yield query("values", [[["arithmetic", "-", integer(0), ["hex", "8000000000000000", False]]]])
    yield query("select", [[expression("plus", ["real", "0", "0", None, True])]])
    yield query("select", [[expression("complement", integer(-(1 << 63))), expression("negate", integer(-(1 << 63)))]])
    yield query("select", [[expression("negate", ["real", "0", "0", None, False])]])


def affinity_witness():
    expr = ["comparison", "=", expression("plus", ["column", 0]), ["text", "7"]]
    item = query("select", [[expr]], ["without_affinity"])
    item["source"] = NUMBERS
    return item


def analyzed():
    yield ("SELECT number = '7' AS native_affinity, +number = '7' AS without_affinity FROM ir_case_words", [DDL],
           {**affinity_witness(), "columns": [[["comparison", "=", ["column", 0], ["text", "7"]], "native_affinity"],
                                             *affinity_witness()["columns"]]})
    item = query("select", [[["comparison", "=", expression("plus", ["column", 0]), ["text", "X"]]]], ["collation"])
    item["source"] = WORDS
    yield "SELECT +word = 'X' AS collation FROM ir_case_words", [DDL], item
    yield ("SELECT -(+9223372036854775808) AS minimum, -0.0 AS signed_zero, -(-0.0) AS computed",
           [], query("select", [[integer(-(1 << 63)), ["real", "0", "0", None, True],
                                 expression("negate", ["real", "0", "0", None, True])]], ["minimum", "signed_zero", "computed"]))
    yield ("VALUES (NOT ?02, ~?2, ?02 ISNULL, ?2 NOTNULL, ?02 NOT NULL)",
           [], query("values", [[expression(kind, ["parameter", label])
                                 for kind, label in (("not", "02"), ("complement", "2"), ("is-null", "02"),
                                                     ("is-not-null", "2"), ("is-not-null", "02"))]]))


def negatives():
    yield query("values", [[expression("plus", ["column", 0])]]), "rejected", "must belong to the statement source"
    operand = expression("not", ["arithmetic", "+", integer(1), ["column", 0]])
    yield query("values", [[operand]]), "rejected", "must belong to the statement source"
    yield ({"sql": "SELECT -(+0x8000000000000000) AS n", "declarations": []},
           "rejected", "minimum hexadecimal INTEGER cannot be negated")
    yield query("select", [[deep(33)]]), "rejected", "Expression height or expanded node count"
    yield query("values", [[expression("negate", expression("plus", ["hex", "8000000000000000", False]))]]), "rejected", "minimum hexadecimal INTEGER cannot be negated"


def expected(item):
    source = item.get("source")

    def numeric_text(value):
        if type(value) is str and REAL_PREFIX.fullmatch(value.strip(SPACES)):
            return numeric_value(value.strip(SPACES))
        return value

    def column_with_collation(term):
        while term[:2] == ["unary", "+"]:
            term = term[2]
        return term[0] == "column"

    def comparison(left, right, a, b):
        a, b = comparison_values(left, right, a, b)
        if source == NUMBERS:
            if left[0] == "column":
                b = numeric_text(b)
            if right[0] == "column":
                a = numeric_text(a)
        if source == WORDS and type(a) is str and type(b) is str and (column_with_collation(left) or column_with_collation(right)):
            return a.translate(LOWER), b.translate(LOWER)
        return a, b

    return scalar_results(item, comparison)


def forms(reply):
    root = reply.get("operation", "select")
    yield "root/" + root
    operands = (expr for row in reply["rows"] for expr in row) if root == "values" else (expr for expr, _ in reply["columns"])
    for term in operands:
        for value in children(term):
            if value[0] in ("unary", "arithmetic", "comparison"):
                yield "operator/" + value[1]
                if any(child[0] in ("unary", "arithmetic", "comparison") for child in value[2:]):
                    yield "nested"
            else:
                yield "operand/" + value[0]
