"""Independent schedules for ordered candidates, NULL and SQLite binding metadata."""

from itertools import product
from random import Random

from sqlite_between_cases import DDL, FIXTURE, col, expected, leaves, role, statement
from sqlite_relational_cases import children
from sqlite_result_cases import integer


def membership(operand, items, negated=False):
    return ["membership", negated, operand, items]


def finite_cases(domain):
    for root, negated, length in product(domain["roots"], domain["negated"], domain["lengths"]):
        atoms = leaves(domain, root)
        for operand, *items in product(atoms, repeat=length + 1):
            yield statement(root, [[membership(operand, items, negated)]])


def finite_id(reply, domain):
    root = role(reply)
    _, negated, operand, items = reply["rows"][0][0] if root == "values" else reply["columns"][0][0]
    atoms = leaves(domain, root)
    ordinal = 0
    for term in (operand, *items):
        ordinal = ordinal * len(atoms) + atoms.index(term)
    return root, negated, len(items), ordinal


def finite_ids(domain):
    count = len(domain["atoms"])
    return {(root, negated, length, offset) for root in domain["roots"] for negated in domain["negated"] for length in domain["lengths"]
            for offset in range((count + (len(domain["slots"]) if root == "select-table" else 0)) ** (length + 1))}


def seeded(domain, seed, count):
    rng = Random(seed)
    for _ in range(count):
        root = rng.choice(domain["roots"])
        atoms = leaves(domain, root) + [["parameter", "02"], ["parameter", "2"], ["parameter", "7"],
                                        ["text", "APPLE"], ["blob", "61"]]

        def scalar(depth):
            if not depth or rng.randrange(3) == 0:
                return rng.choice(atoms)
            if rng.randrange(4) == 0:
                return ["arithmetic", rng.choice(("+", "-", "%")), scalar(depth - 1), scalar(depth - 1)]
            return test(depth - 1)

        def test(depth):
            operand = scalar(depth)
            items = [scalar(depth) for _ in range(rng.randrange(5))]
            return membership(operand, items, bool(rng.getrandbits(1)))

        width = rng.randrange(1, 5)
        height = rng.randrange(1, 4) if root == "values" else 1
        rows = [[test(rng.randrange(4)) for _ in range(width)] for _ in range(height)]
        labels = [rng.choice(("same", "same", 'member"test', "集合")) for _ in range(width)]
        item = statement(root, rows, labels)
        item["bindings"] = {slot: rng.choice((None, -1, 0, 7, "2", "aPPLe")) for slot in item["bindings"]}
        yield item


def deep(height):
    value = integer(2)
    for _ in range(height):
        value = membership(value, [integer(2)])
    return value


def column_witness():
    n, t = col(0), col(1)
    number_text, ascii_upper = ["text", "2"], ["text", "APPLE"]
    return statement("select-table", [[n, t, membership(number_text, [n]), membership(n, [number_text]),
                                        membership(ascii_upper, [t]), membership(t, [ascii_upper])]],
                     ["n", "t", "numeric_right", "numeric_left", "collation_right", "collation_left"])


def boundaries():
    yield statement("select-unit", [[deep(32)]])
    yield statement("values", [[membership(["parameter", "02"], []), membership(["parameter", "7"], [], True)]])
    yield column_witness()


def analyzed():
    yield ("SELECT ?02 IN () AS folded, ?7 NOT IN () AS inverse, ?2 AS shared", [],
           statement("select-unit", [[["folded-membership", False, ["02"]], ["folded-membership", True, ["7"]], ["parameter", "2"]]],
                     ["folded", "inverse", "shared"]))
    yield ("SELECT NULL IN () AS empty, NULL NOT IN () AS inverse", [],
           statement("select-unit", [[["folded-membership", False, []], ["folded-membership", True, []]]], ["empty", "inverse"]))
    yield ("SELECT n,t,'2' IN (n) AS numeric_right,n IN ('2') AS numeric_left,"
           "'APPLE' IN (t) AS collation_right,t IN ('APPLE') AS collation_left FROM ir_range_inputs",
           [DDL], column_witness())
    yield ("VALUES (7 IN (3,NULL,7,7), 7 NOT IN (3,NULL), NULL IN (10))", [],
           statement("values", [[membership(integer(7), [integer(3), ["null"], integer(7), integer(7)]),
                                 membership(integer(7), [integer(3), ["null"]], True), membership(["null"], [integer(10)])]]))
    yield ("SELECT CAST('2' AS INTEGER) IN ('2') AS typed, '2' IN (CAST(2 AS INTEGER)) AS no_affinity", [],
           statement("select-unit", [[membership(["cast", "INTEGER", ["text", "2"]], [["text", "2"]]),
                                     membership(["text", "2"], [["cast", "INTEGER", integer(2)]])]], ["typed", "no_affinity"]))


def raw_negative(expression):
    return {"source": None, "columns": [[expression, "test"]], "bindings": {}}


def negatives():
    message = "Expected an ordered list of scalar membership items"
    yield raw_negative(membership(integer(1), {"invalid": integer(1)})), "rejected", message
    yield raw_negative(membership(integer(1), [["nonmember"]])), "rejected", message
    yield statement("select-unit", [[membership(col(0), [integer(2)])]]), "rejected", "must belong to the statement source"
    yield statement("select-unit", [[membership(integer(2), [col(0)])]]), "rejected", "must belong to the statement source"
    yield ({"sql": "SELECT (1,2) IN ((1,2))", "declarations": []},
           "rejected", "no complete SQLite scalar representation")
    yield statement("select-unit", [[deep(33)]]), "rejected", "Expression height or expanded node count"


def forms(reply):
    target = role(reply)
    yield "root/" + target
    expressions = (term for row in reply["rows"] for term in row) if target == "values" else (term for term, _ in reply["columns"])
    for expression in expressions:
        for term in children(expression):
            if term[0] != "membership":
                continue
            _, negated, operand, members = term
            yield f"negated/{negated}"
            yield f"length/{len(members)}"
            yield f"tested/{operand[0]}"
            for member in members:
                yield f"member/{member[0]}"
