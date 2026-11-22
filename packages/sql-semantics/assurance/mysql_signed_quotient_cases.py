"""Schedule and independent projection observations for MySQL signed quotients."""

from itertools import product
from random import Random

from server_cases import analyzed, case, expected as projection_expected, node_key, source

EXCEPTION = "SqlSemantics\\Platform\\MySql\\Model\\Diagnostic\\InvalidConstruction"


def integer(value):
    return ["integer", str(value)]


def operation(operator, left, right):
    return ["operation", operator, left, right]


def projection(tag, expression, table=None, label="result"):
    values = [expression] if isinstance(label, str) else expression
    names = [label] if isinstance(label, str) else label
    return {**case("mysql", table, values, names), "release": tag, "where": None}


def source_role(table):
    return "unit" if table is None else "table"


def expected(item):
    observed = projection_expected(item)
    return {**observed, "warningCount": 0} if observed["status"] == "rows" else observed


def finite_cases(tag, domain):
    for role in domain["sources"]:
        table = None if role == "unit" else source("mysql")
        atoms = [integer(value) for value in domain["integers"]]
        if table is not None:
            atoms.extend(["slot", slot] for slot in domain["slots"])
        for operator, left, right in product(domain["operators"], atoms, atoms):
            yield projection(tag, operation(operator, left, right), table)


def finite_id(_, response, _item):
    _, operator, left, right = response["columns"][0][0]
    return source_role(response["source"]), operator, node_key(left), node_key(right)


def finite_ids(_, specification, _tag):
    domain = specification["finite"]
    literal_keys = [f'["integer","{value}"]' for value in domain["integers"]]
    slot_keys = [f'["slot",{slot}]' for slot in domain["slots"]]
    keys = {}
    for role, atoms in (("unit", literal_keys), ("table", literal_keys + slot_keys)):
        keys[role] = {(operator, atoms[index // len(atoms)], atoms[index % len(atoms)])
                      for operator in domain["operators"] for index in range(len(atoms) ** 2)}
    return {(role, *identity) for role in domain["sources"] for identity in keys[role]}


def seeded(tag, domain, seed, count):
    rng = Random(seed)
    operators = ("+", "-", "*", *domain["operators"])
    for _ in range(count):
        table = rng.choice([None, source("mysql")])
        atoms = [integer(value) for value in (-11, -3, -1, 0, 1, 3, 5)]
        if table is not None:
            atoms.extend([["slot", 0], ["slot", 1]])

        def scalar(depth):
            if not depth or rng.randrange(4) == 0:
                return rng.choice(atoms)
            return operation(rng.choice(operators), scalar(depth - 1), scalar(depth - 1))

        fields = [scalar(rng.randrange(1, 4)) for _ in range(rng.randrange(1, 5))]
        names = [rng.choice(("same", "same", 'view`', "余")) for _ in fields]
        yield projection(tag, fields, table, names)


def minimum():
    half = operation("+", operation("*", integer(-2147483648), integer(2147483647)), integer(-2147483648))
    return operation("+", half, half)


def positive_sql(tag):
    yield ("SELECT -11 DIV 3 AS q, -11 MOD 3 AS r",
           projection(tag, [operation("DIV", integer(-11), integer(3)), operation("%", integer(-11), integer(3))], label=["q", "r"]))
    yield ("SELECT Id DIV delta AS q, ir_evidence_accounts.Id % delta AS r FROM ir_fixture.ir_evidence_accounts",
           projection(tag, [operation("DIV", ["slot", 0], ["slot", 1]), operation("%", ["slot", 0], ["slot", 1])],
                      source("mysql"), ["q", "r"]))
    yield ("SELECT 2 + 11 DIV 3 * 2 AS n",
           projection(tag, operation("+", integer(2), operation("*", operation("DIV", integer(11), integer(3)), integer(2))),
                      label="n"))
    yield ("SELECT 7 DIV 0 AS q, 7 MOD 0 AS r",
           projection(tag, [operation("DIV", integer(7), integer(0)), operation("%", integer(7), integer(0))], label=["q", "r"]))
    yield ("SELECT -11 mod 3",
           projection(tag, operation("%", integer(-11), integer(3)), label="-11 mod 3"))


def negatives(tag):
    for left, right in ((["slot", 0], integer(1)), (integer(1), ["slot", 0])):
        yield projection(tag, operation("DIV", left, right)), "belong to the SELECT source"
    yield analyzed("mysql", "SELECT 3.5 DIV 2 AS n", version=tag), "numeric literal must have the signed integer role"
    yield analyzed("mysql", "SELECT 2 MOD NULL AS n", version=tag), "Arithmetic operands must have the signed integer role"
    item = {"dialect": "mysql", "release": tag, "sql": "SELECT value DIV 2 FROM ir_fixture.ir_unsigned_operand",
            "declarations": ["CREATE TABLE ir_fixture.ir_unsigned_operand (value INT UNSIGNED)"]}
    yield item, "bound column must have the signed 32-bit integer type"


def requests(_engine, tag, specification):
    domain, settings = specification["finite"], specification["exploration"]
    for index, item in enumerate(finite_cases(tag, domain)):
        yield f"finite/{index}", item, item, None
    for seed in settings["seeds"]:
        for index, item in enumerate(seeded(tag, domain, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for operator, right in product(domain["operators"], [-1, 0, 1, 2]):
        item = projection(tag, operation(operator, minimum(), integer(right)))
        yield f"boundary/{operator}/{right}", item, item, None
    product_at_minimum = operation("*", operation("+", integer(2147483647), integer(1)),
                                   operation("*", integer(-2147483648), integer(2)))
    item = projection(tag, operation("DIV", product_at_minimum, integer(0)))
    yield "boundary/preceding-multiplication", item, item, None
    for index, (sql, item) in enumerate(positive_sql(tag)):
        yield f"analysis/{index}", analyzed("mysql", sql, version=tag), item, None
    for index, (item, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, {"exception": EXCEPTION, "message": reason}
