"""Finite operand orders and structural exploration of the PostgreSQL int4 operators."""

from itertools import product
from random import Random

from server_cases import analyzed, case, node_key, source

EXCEPTION = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\InvalidConstruction"


def integer(value):
    return ["integer", str(value)]


def operation(operator, left, right):
    return ["operation", operator, left, right]


def projection(tag, expression, table=None, label="result"):
    values = [expression] if isinstance(label, str) else expression
    names = [label] if isinstance(label, str) else label
    return {**case("postgres", table, values, names), "release": tag, "where": None}


def source_for(role):
    return None if role == "unit" else source("postgres", only=role == "only")


def source_role(table):
    return "unit" if table is None else "only" if table["only"] else "inherited"


def leaves(domain, role):
    refs = [] if role == "unit" else [["slot", slot] for slot in domain["slots"]]
    return [*[integer(value) for value in domain["integers"]], *refs]


def finite_cases(tag, domain):
    for role in domain["sources"]:
        atoms = leaves(domain, role)
        for operator, left, right in product(domain["operators"], atoms, atoms):
            yield projection(tag, operation(operator, left, right), source_for(role))


def finite_id(_, response, _item):
    role = source_role(response["source"])
    _, operator, left, right = response["columns"][0][0]
    return role, operator, node_key(left), node_key(right)


def finite_ids(_, specification, _tag):
    domain = specification["finite"]
    return {(role, operator, node_key(left), node_key(right)) for role in domain["sources"] for operator in domain["operators"]
            for left in leaves(domain, role) for right in leaves(domain, role)}


def seeded(tag, domain, seed, count):
    rng = Random(seed)
    operators = ("+", "-", "*", *domain["operators"])
    safe = [integer(value) for value in (-7, -3, -2, -1, 2, 3, 5)]
    for _ in range(count):
        role = rng.choice(domain["sources"])
        atoms = [*safe, integer(0), *([["slot", 0], ["slot", 1]] if role != "unit" else [])]

        def scalar(depth):
            if not depth or rng.randrange(4) == 0:
                return rng.choice(atoms)
            operator = rng.choice(operators)
            left = scalar(depth - 1)
            right = rng.choice(safe) if operator in ("/", "%") else scalar(depth - 1)
            return operation(operator, left, right)

        fields = [scalar(rng.randrange(1, 4)) for _ in range(rng.randrange(1, 5))]
        names = [rng.choice(("same", "same", 'view"', "余")) for _ in fields]
        yield projection(tag, fields, source_for(role), names)


def positive_sql(tag):
    yield ("SELECT (-7) OPERATOR(pg_catalog./) 3 AS q, (-7) % 3 AS r",
           projection(tag, [operation("/", integer(-7), integer(3)), operation("%", integer(-7), integer(3))], label=["q", "r"]))
    yield ("SELECT id # delta AS cross_bits, delta & id AS common FROM ONLY public.ir_evidence_accounts",
           projection(tag, [operation("#", ["slot", 0], ["slot", 1]), operation("&", ["slot", 1], ["slot", 0])],
                      source_for("only"), ["cross_bits", "common"]))
    yield ("SELECT 2 % 0 AS zero", projection(tag, operation("%", integer(2), integer(0)), label="zero"))
    yield ("SELECT (-2147483648) OPERATOR(pg_catalog./) (-1) AS too_wide",
           projection(tag, operation("/", integer(-(2 ** 31)), integer(-1)), label="too_wide"))
    yield ("SELECT (-2147483648) % (-1) AS zero", projection(tag, operation("%", integer(-(2 ** 31)), integer(-1)), label="zero"))


def negatives(tag):
    for left, right in ((["slot", 0], integer(1)), (integer(1), ["slot", 0])):
        yield projection(tag, operation("/", left, right)), "belong to the SELECT source"
    yield analyzed("postgres", "SELECT 7 OPERATOR(public./) 2 AS n", version=tag), "pg_catalog operator"
    yield analyzed("postgres", "SELECT 2147483648 % 2 AS n", version=tag), "Arithmetic operands must have the int4 role."


def requests(_engine, tag, specification):
    domain, settings = specification["finite"], specification["exploration"]
    for index, item in enumerate(finite_cases(tag, domain)):
        yield f"finite/{index}", item, item, None
    for seed in settings["seeds"]:
        for index, item in enumerate(seeded(tag, domain, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (sql, item) in enumerate(positive_sql(tag)):
        yield f"analysis/{index}", analyzed("postgres", sql, version=tag), item, None
    for index, (item, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, {"exception": EXCEPTION, "message": reason}
