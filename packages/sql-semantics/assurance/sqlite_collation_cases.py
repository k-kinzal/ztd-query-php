"""Ordered explicit collations and witnesses for SQLite's comparison rules."""

from itertools import product
from random import Random

from sqlite_between_cases import DDL, FIXTURE, between, col, expected as scalar_observation, statement
from sqlite_builtin_collations import collation_key
from sqlite_membership_cases import membership
from sqlite_relational_cases import children
from sqlite_result_cases import integer


def text(value):
    return ["text", value]


def collate(sequence, operand):
    return ["collate", sequence, operand]


def compare(operator, left, right):
    return ["comparison", operator, left, right]


def finite_cases(domain):
    for root, sequence, operator, position in product(domain["roots"], domain["collations"], domain["operators"], domain["positions"]):
        for left, right in product(domain["atoms"], repeat=2):
            operands = [left, right]
            index = domain["positions"].index(position)
            operands[index] = collate(sequence, operands[index])
            yield statement("select-unit" if root == "select" else root, [[compare(operator, *operands)]])


def finite_id(reply, domain):
    root = reply.get("operation", "select")
    _, operator, left, right = reply["rows"][0][0] if root == "values" else reply["columns"][0][0]
    if left[0] == "collate":
        position, sequence, left = "left", left[1], left[2]
    else:
        position, sequence, right = "right", right[1], right[2]
    atoms = domain["atoms"]
    return root, sequence, operator, position, atoms.index(left) * len(atoms) + atoms.index(right)


def finite_ids(domain):
    return {(root, sequence, operator, position, index)
            for root in domain["roots"] for sequence in domain["collations"] for operator in domain["operators"]
            for position in domain["positions"] for index in range(len(domain["atoms"]) ** 2)}


def seeded(domain, seed, count):
    rng = Random(seed)
    atoms = [*domain["atoms"], text("Ä"), text("ä"), text("a\t"), ["blob", "61"], ["parameter", "02"], ["parameter", "2"]]

    def expression(depth):
        if not depth or rng.randrange(4) == 0:
            return rng.choice(atoms)
        operand = expression(depth - 1)
        if rng.randrange(3) == 0:
            other = expression(depth - 1)
            return ["case", None, [[integer(rng.randrange(2)), operand]], other]
        return collate(rng.choice(domain["collations"]), operand)

    for _ in range(count):
        root = rng.choice(domain["roots"])
        width = rng.randrange(1, 4)
        height = 1 if root == "select" else rng.randrange(1, 4)
        rows = [[compare(rng.choice(domain["operators"]), collate(rng.choice(domain["collations"]), expression(rng.randrange(4))),
                         expression(rng.randrange(4))) for _ in range(width)] for _ in range(height)]
        names = [rng.choice(("same", "same", 'comp"are', "照合")) for _ in range(width)]
        item = statement("select-unit" if root == "select" else root, rows, names)
        item["bindings"] = {slot: rng.choice((None, 0, 2, "A", "a ", "ä", "a\0b")) for slot in item["bindings"]}
        yield item


def deep(height):
    value = text("a")
    for _ in range(height):
        value = collate("BINARY", value)
    return value


def ordered():
    item = statement("select-table", [[collate("NOCASE", col(0)), col(1)]], ["t", "n"], ["t", "n"])
    item["orderBy"] = [[1, False, True], [2, False, True]]
    return item


def nul_witness():
    first, second, third = (["parameter", str(index)] for index in range(1, 4))
    item = statement("select-unit", [[compare("=", collate("NOCASE", first), second), compare("=", collate("NOCASE", first), third),
                                     compare("=", collate("BINARY", first), second)]], ["same_len", "longer", "binary"])
    item["bindings"] = {"1": "a\0b", "2": "A\0z", "3": "A\0zz"}
    return item


def boundaries():
    yield statement("select-unit", [[deep(32)]])
    yield statement("values", [[collate("NOCASE", text("a ")), collate("RTRIM", ["blob", "6162"])]])
    yield ordered()
    yield nul_witness()


def analyzed():
    yield ("SELECT 'a' COLLATE NOCASE COLLATE BINARY = 'A' AS bin, 'a' COLLATE BINARY COLLATE NOCASE = 'A' AS folded", [],
           statement("select-unit", [[compare("=", collate("BINARY", collate("NOCASE", text("a"))), text("A")),
                                      compare("=", collate("NOCASE", collate("BINARY", text("a"))), text("A"))]], ["bin", "folded"]))
    yield ("SELECT 'a' COLLATE BINARY = 'A' COLLATE NOCASE AS l, 'a' COLLATE NOCASE = 'A' COLLATE BINARY AS r", [],
           statement("select-unit", [[compare("=", collate("BINARY", text("a")), collate("NOCASE", text("A"))),
                                      compare("=", collate("NOCASE", text("a")), collate("BINARY", text("A")))]], ["l", "r"]))
    yield ("SELECT (CASE WHEN 0 THEN 'z' COLLATE NOCASE ELSE 'a' END) = 'A' AS dormant,"
           " CAST('a' COLLATE NOCASE AS TEXT) = 'A' AS cast_arm", [],
           statement("select-unit", [[compare("=", ["case", None, [[integer(0), collate("NOCASE", text("z"))]], text("a")], text("A")),
                                      compare("=", ["cast", "TEXT", collate("NOCASE", text("a"))], text("A"))]], ["dormant", "cast_arm"]))
    casefold_a = collate("NOCASE", text("A"))
    yield ("SELECT 'a' IN ('A' COLLATE NOCASE) AS single, 'a' IN ('A' COLLATE NOCASE,'b') AS many,"
           " 'a' COLLATE BINARY IN ('A' COLLATE NOCASE) AS lhs", [],
           statement("select-unit", [[membership(text("a"), [casefold_a]), membership(text("a"), [casefold_a, text("b")]),
                                      membership(collate("BINARY", text("a")), [casefold_a])]], ["single", "many", "lhs"]))
    yield ("SELECT 'apple' BETWEEN ('APPLE' COLLATE NOCASE) AND ('APPLE' COLLATE BINARY) AS split,"
           " 'apple' COLLATE NOCASE BETWEEN ('APPLE' COLLATE BINARY) AND 'APPLE' AS lhs", [],
           statement("select-unit", [[between(text("apple"), collate("NOCASE", text("APPLE")), collate("BINARY", text("APPLE"))),
                                      between(collate("NOCASE", text("apple")), collate("BINARY", text("APPLE")), text("APPLE"))]], ["split", "lhs"]))
    yield ("SELECT t COLLATE nOcAsE AS t, n AS n FROM ir_range_inputs ORDER BY 1, 2", [DDL], ordered())
    yield ("SELECT ?1 COLLATE NOCASE = ?2 AS same_len, ?1 COLLATE NOCASE = ?3 AS longer, ?1 COLLATE BINARY = ?2 AS binary",
           [], nul_witness())
    yield ("SELECT n COLLATE RTRIM = '2' AS preserved_affinity, t COLLATE BINARY = 'APPLE' AS binary FROM ir_range_inputs", [DDL],
           statement("select-table", [[compare("=", collate("RTRIM", col(0)), text("2")),
                                       compare("=", collate("BINARY", col(1)), text("APPLE"))]], ["preserved_affinity", "binary"]))


def negatives():
    yield {"sql": "SELECT 'x' COLLATE application_defined", "declarations": []}, "rejected", "requires a known SQLite builtin"
    yield {"sql": "SELECT (1,2) COLLATE NOCASE", "declarations": []}, "analysis-rejected", "row value"
    yield statement("select-unit", [[collate("NOCASE", col(0))]]), "rejected", "must belong to the statement source"
    yield statement("select-unit", [[deep(33)]]), "rejected", "Expression height or expanded node count"


def expected(item):
    observation = scalar_observation(item)
    if not item.get("orderBy"):
        return observation

    def keys(row):
        first, second = row
        text_key = (0, b"") if first[0] == "null" else (1, collation_key(first[1], "NOCASE"))
        number_key = (0, 0) if second[0] == "null" else (1, int(second[1]))
        return text_key, number_key

    return {**observation, "rows": sorted(observation["rows"], key=keys)}


def forms(reply):
    root = reply.get("operation", "select")
    yield "root/" + root
    expressions = [term for row in reply["rows"] for term in row] if root == "values" else [term for term, _ in reply["columns"]]
    for expression in expressions:
        for operand in children(expression):
            if operand[0] == "collate":
                yield "collation/" + operand[1]
                yield "operand/" + operand[2][0]
            if operand[0] == "comparison":
                yield "operator/" + operand[1]
