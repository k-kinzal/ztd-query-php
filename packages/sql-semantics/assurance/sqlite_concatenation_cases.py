"""Ordered SQLite text bytes and scope witnesses for scalar concatenation."""

from itertools import product
from random import Random

from sqlite_between_cases import DDL, FIXTURE, col, expected, statement
from sqlite_collation_cases import collate, compare, text
from sqlite_relational_cases import children
from sqlite_result_cases import integer


def concat(left, right):
    return ["concat", left, right]


def finite_cases(domain):
    for root, left, right in product(domain["roots"], domain["atoms"], domain["atoms"]):
        yield statement("select-unit" if root == "select" else root, [[concat(left, right)]])


def finite_id(reply, domain):
    root = reply.get("operation", "select")
    _, left, right = reply["rows"][0][0] if root == "values" else reply["columns"][0][0]
    atoms = domain["atoms"]
    return root, atoms.index(left) * len(atoms) + atoms.index(right)


def finite_ids(domain):
    return {(root, index) for root in domain["roots"] for index in range(len(domain["atoms"]) ** 2)}


def seeded(domain, seed, count):
    rng = Random(seed)
    atoms = [*domain["atoms"], ["text", ""], ["text", "🦉"], ["blob", "00FF"], ["parameter", "2"], ["parameter", "7"]]
    for _ in range(count):
        root = rng.choice(("select-unit", "select-unit", "values", "select-table"))
        leaves = atoms + ([col(0), col(1)] if root == "select-table" else [])

        def scalar(height):
            if not height or rng.randrange(4) == 0:
                return rng.choice(leaves)
            return concat(scalar(height - 1), scalar(height - 1))

        height = rng.randrange(1, 4) if root == "values" else 1
        width = rng.randrange(1, 5)
        rows = [[concat(scalar(rng.randrange(1, 5)), scalar(rng.randrange(1, 5))) for _ in range(width)] for _ in range(height)]
        labels = [rng.choice(("same", "same", "build", "文字列")) for _ in range(width)]
        item = statement(root, rows, labels)
        item["bindings"] = {slot: rng.choice((None, -5, 0, 0.125, " é ", "a\0b")) for slot in item["bindings"]}
        yield item


def deep(depth):
    term = text("x")
    for _ in range(depth):
        term = concat(term, text("y"))
    return term


def boundaries():
    yield statement("select-unit", [[deep(32)]])
    yield statement("select-unit", [[concat(["blob", "C3"], ["blob", "A9"]), concat(["blob", "00FF"], text("x"))]])
    yield statement("values", [[concat(["parameter", "02"], ["parameter", "2"]), concat(["blob", "6100"], text("b"))]])


def analyzed():
    yield ("SELECT X'C3' || X'A9' AS combined, X'00FF' || 'x' AS bytes", [],
           statement("select-unit", [[concat(["blob", "C3"], ["blob", "A9"]), concat(["blob", "00FF"], text("x"))]], ["combined", "bytes"]))
    yield ("SELECT 2 * 3 || 4 AS precedence, (2 * 3) || 4 AS grouped", [],
           statement("select-unit", [[["arithmetic", "*", integer(2), concat(integer(3), integer(4))],
                                      concat(["arithmetic", "*", integer(2), integer(3)], integer(4))]], ["precedence", "grouped"]))
    yield ("SELECT (-0.0) || ':' || 1e100 AS formatted, ?02 || ?2 AS shared", [],
           statement("select-unit", [[concat(concat(["real", "0", "0", None, True], text(":")), ["real", "1", None, "100", False]),
                                      concat(["parameter", "02"], ["parameter", "2"])]], ["formatted", "shared"]))
    yield ("SELECT ('a' COLLATE NOCASE || 'b') = 'AB' AS applies", [],
           statement("select-unit", [[compare("=", concat(collate("NOCASE", text("a")), text("b")), text("AB"))]], ["applies"]))
    yield ("SELECT n || ':' || t AS summary, t || n AS reversed FROM main.ir_range_inputs", [DDL],
           statement("select-table", [[concat(concat(col(0), text(":")), col(1)), concat(col(1), col(0))]], ["summary", "reversed"]))
    yield ("VALUES (X'6100' || 'b', 'x' || NULL), ('' || 2, 2 || '')", [],
           statement("values", [[concat(["blob", "6100"], text("b")), concat(text("x"), ["null"])],
                                 [concat(text(""), integer(2)), concat(integer(2), text(""))]]))


def negatives():
    yield statement("select-unit", [[concat(col(0), text("x"))]]), "rejected", "must belong to the statement source"
    yield statement("select-unit", [[concat(text("x"), col(0))]]), "rejected", "must belong to the statement source"
    yield {"sql": "SELECT (1,2) || 'a'", "declarations": []}, "analysis-rejected", "row value"
    yield statement("select-unit", [[deep(33)]]), "rejected", "Expression height or expanded node count"


def forms(reply):
    root = reply.get("operation", "select")
    yield "root/" + root
    expressions = [term for row in reply["rows"] for term in row] if root == "values" else [term for term, _ in reply["columns"]]
    for expression in expressions:
        for operand in children(expression):
            if operand[0] == "concat":
                yield "left/" + operand[1][0]
                yield "right/" + operand[2][0]
