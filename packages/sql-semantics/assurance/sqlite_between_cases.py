"""Independent scalar ranges, fixture comparison metadata and finite identities."""

from itertools import product
from random import Random

from run_sqlite_scalars import evaluate, query
from run_sqlite_values import names
from sqlite_builtin_collations import collation_key, explicit_collation
from sqlite_cast_semantics import comparison_values
from sqlite_engine import typed
from sqlite_relational_cases import children, parameter_names
from sqlite_result_cases import integer

TABLE = "ir_range_inputs"
DDL = f"CREATE TABLE main.{TABLE} (n INTEGER, t TEXT COLLATE NOCASE)"
DATA = ((2, "10"), (2, "10"), (0, "apple"), (8, "aPPLe"), (12, "Z"), (None, "b"), (3, None))
FIXTURE = DDL + f"; INSERT INTO main.{TABLE} VALUES (2,'10'),(2,'10'),(0,'apple'),(8,'aPPLe'),(12,'Z'),(NULL,'b'),(3,NULL);"


def col(index):
    return ["column", index]


def between(operand, lower, upper, negated=False):
    return ["between", negated, operand, lower, upper]


def statement(root, rows, labels=None, columns=None):
    item = query("values" if root == "values" else "select", rows, labels)
    if root == "select-table":
        item["source"] = ["main", TABLE, ["n", "t"] if columns is None else columns]
    return item


def role(item):
    if item.get("operation") == "values":
        return "values"
    return "select-unit" if item["source"] is None else "select-table"


def leaves(domain, root):
    return domain["atoms"] + ([col(slot) for slot in domain["slots"]] if root == "select-table" else [])


def finite_cases(domain):
    for root, negated in product(domain["roots"], domain["negated"]):
        for operand, lower, upper in product(leaves(domain, root), repeat=3):
            yield statement(root, [[between(operand, lower, upper, negated)]])


def finite_id(reply, domain):
    root = role(reply)
    _, negated, *operands = reply["rows"][0][0] if root == "values" else reply["columns"][0][0]
    atoms = leaves(domain, root)
    indices = [atoms.index(term) for term in operands]
    return root, negated, (indices[0] * len(atoms) + indices[1]) * len(atoms) + indices[2]


def finite_ids(domain):
    return {(root, negated, offset) for root in domain["roots"] for negated in domain["negated"]
            for offset in range((len(domain["atoms"]) + (len(domain["slots"]) if root == "select-table" else 0)) ** 3)}


def seeded(domain, seed, count):
    rng = Random(seed)
    for _ in range(count):
        root = rng.choice(domain["roots"])
        atoms = leaves(domain, root) + [["parameter", "02"], ["parameter", "2"], ["parameter", "7"],
                                        ["text", "a"], ["text", "Z"], ["blob", "32"]]

        def scalar(depth):
            if depth == 0 or rng.randrange(4) == 0:
                return rng.choice(atoms)
            if rng.randrange(3) == 0:
                return ["unary", rng.choice(("+", "NOT", "~")), scalar(depth - 1)]
            if rng.randrange(3) == 0:
                return ["arithmetic", rng.choice(("+", "-", "%")), scalar(depth - 1), scalar(depth - 1)]
            return between(scalar(depth - 1), scalar(depth - 1), scalar(depth - 1), bool(rng.getrandbits(1)))

        height = rng.randrange(1, 4) if root == "values" else 1
        width = rng.randrange(1, 5)
        rows = [[between(scalar(rng.randrange(4)), scalar(rng.randrange(4)), scalar(rng.randrange(4)), bool(rng.getrandbits(1)))
                 for _ in range(width)] for _ in range(height)]
        labels = [rng.choice(("same", "same", 'range"test', "範囲")) for _ in range(width)]
        item = statement(root, rows, labels)
        item["bindings"] = {slot: rng.choice((None, -1, 0, 3, "2", "a")) for slot in item["bindings"]}
        yield item


def deep(height):
    operand = integer(2)
    for _ in range(height):
        operand = between(operand, integer(0), integer(3))
    return operand


def affinity_witness():
    expressions = [col(0), between(["text", "9"], col(0), col(1)), between(["text", "APPLE"], col(1), ["text", "APPLE"])]
    return statement("select-table", [expressions], ["n", "affinity", "collation"])


def boundaries():
    yield statement("select-unit", [[deep(32)]])
    yield statement("values", [[between(["parameter", "02"], ["parameter", "2"], ["parameter", "7"])]])
    yield affinity_witness()


def analyzed():
    yield ("SELECT 2 BETWEEN 2 AND 2 AS inclusive, NULL NOT BETWEEN 1 AND 3 AS unknown",
           [], statement("select-unit", [[between(integer(2), integer(2), integer(2)),
                                          between(["null"], integer(1), integer(3), True)]], ["inclusive", "unknown"]))
    yield ("VALUES (1 BETWEEN NULL AND -1, 1 NOT BETWEEN 2 AND NULL, 0 BETWEEN 0 AND NULL)",
           [], statement("values", [[between(integer(1), ["null"], integer(-1)), between(integer(1), integer(2), ["null"], True),
                                     between(integer(0), integer(0), ["null"])]]))
    yield ("SELECT n, '9' BETWEEN n AND t AS affinity, 'APPLE' BETWEEN t AND 'APPLE' AS collation FROM ir_range_inputs",
           [DDL], affinity_witness())
    yield ("SELECT (?02 BETWEEN ?2 AND (?7 + 2)) BETWEEN 0 AND 1 AS nested", [],
           statement("select-unit", [[between(between(["parameter", "02"], ["parameter", "2"],
                                                      ["arithmetic", "+", ["parameter", "7"], integer(2)]),
                                               integer(0), integer(1))]], ["nested"]))
    yield ("SELECT t, ('APPLE' BETWEEN +t AND t) AS folded FROM main.ir_range_inputs", [DDL],
           statement("select-table", [[col(0), between(["text", "APPLE"], ["unary", "+", col(0)], col(0))]], ["t", "folded"], ["t"]))


def negatives():
    for index in range(3):
        operands = [integer(1)] * 3
        operands[index] = col(0)
        yield statement("select-unit", [[between(*operands)]]), "rejected", "must belong to the statement source"
    yield ({"sql": "SELECT (1,2) BETWEEN (0,1) AND (3,4)", "declarations": []},
           "rejected", "no complete SQLite scalar representation")
    yield statement("select-unit", [[deep(33)]]), "rejected", "Expression height or expanded node count"


def comparison(source):
    slots = [] if source is None else source[2]

    def describe(term):
        if term[0] == "collate":
            return describe(term[2])
        if term[0] == "column":
            name = slots[term[1]]
            return ["cast", "INTEGER" if name == "n" else "TEXT"], "BINARY" if name == "n" else "NOCASE"
        if term[:2] == ["unary", "+"]:
            return term, describe(term[2])[1]
        if term[0] == "cast":
            return term, describe(term[2])[1]
        return term, None

    def apply(left, right, a, b):
        l_explicit = explicit_collation(left)
        if right[0] == "membership-item":
            r_explicit = explicit_collation(right[1]) if right[2] else None
        else:
            r_explicit = explicit_collation(right)
        left, l_collation = describe(left)
        right, r_collation = describe(right)
        a, b = comparison_values(left, right, a, b)
        if type(a) is str and type(b) is str:
            collation = l_explicit or r_explicit or l_collation or r_collation or "BINARY"
            return collation_key(a, collation), collation_key(b, collation)
        return a, b

    return apply


def expected(item):
    bindings = item["bindings"]
    source = item.get("source")
    if item.get("operation") == "values":
        fields = item["rows"]
        labels = [f"column{i + 1}" for i in range(len(fields[0]))]
        parameters = names(fields)
    else:
        fields = [[term for term, _ in item["columns"]]]
        labels = [label for _, label in item["columns"]]
        parameters = parameter_names(item)
    rows = [()] if source is None else [tuple(row[("n", "t").index(name)] for name in source[2]) for row in DATA]
    compare = comparison(source)
    observed = [[typed(evaluate(term, bindings, row, comparison_inputs=compare)) for term in expressions]
                for row in rows for expressions in fields]
    return {"names": labels, "parameters": parameters, "parameterCount": max(map(int, parameters), default=0),
            "rows": sorted(observed), "readOnly": True, "changes": 0}


def forms(reply):
    root = role(reply)
    yield "root/" + root
    expressions = (term for row in reply["rows"] for term in row) if root == "values" else (term for term, _ in reply["columns"])
    for expression in expressions:
        for term in children(expression):
            if term[0] != "between":
                continue
            yield f"negated/{term[1]}"
            for position, operand in zip(("value", "lower", "upper"), term[2:]):
                yield f"operand/{position}/{operand[0]}"
