"""Direct ordered triples and observed finite identities for pg_catalog int4 range predicates."""

from itertools import product
from random import Random

from postgres_int4_cases import EXCEPTION, integer, projection, source_for, source_role
from server_cases import analyzed, node_key

NULL = ["null"]


def int4_range(subject, lower, upper, negated=False, symmetric=False):
    return ["int4-range", negated, symmetric, subject, lower, upper]


def item(tag, kind, shape, role):
    table = source_for(role)
    if kind == "projection":
        return projection(tag, shape, table)
    return {**projection(tag, integer(17) if role == "unit" else ["slot", 0], table), "where": shape}


def operands(domain, source):
    constants = [NULL if value is None else integer(value) for value in domain["literalValues"]]
    return constants if source == "unit" else [*constants, *(["slot", slot] for slot in domain["slots"])]


def finite(tag, domain):
    for role, placement in product(domain["sources"], domain["placements"]):
        for negated, symmetric in product(domain["negated"], domain["symmetric"]):
            for triple in product(operands(domain, role), repeat=3):
                yield item(tag, placement, int4_range(*triple, negated=negated, symmetric=symmetric), role)


def finite_id(_engine, response, _item):
    placement = "where" if response["where"] is not None else "projection"
    expression = response["where"] if placement == "where" else response["columns"][0][0]
    return source_role(response["source"]), placement, node_key(expression)


def finite_ids(_engine, specification, _tag):
    domain = specification["finite"]
    result = set()
    for role in domain["sources"]:
        values = operands(domain, role)
        size = len(values)
        modes = [(negative, symmetric) for negative in domain["negated"] for symmetric in domain["symmetric"]]
        for index in range(len(domain["placements"]) * len(modes) * size ** 3):
            shape, subject = divmod(index, size)
            shape, lower = divmod(shape, size)
            shape, upper = divmod(shape, size)
            placement, mode = divmod(shape, len(modes))
            negative, symmetric = modes[mode]
            result.add((role, domain["placements"][placement], node_key([
                "int4-range", negative, symmetric, values[subject], values[lower], values[upper],
            ])))
    return result


def seeded(tag, seed, count):
    rng = Random(seed)
    for _ in range(count):
        role = rng.choice(("unit", "only", "inherited"))
        atoms = [NULL, *(integer(value) for value in (-2147483648, -3, -2, 0, 2, 3, 2147483647))]
        if role != "unit":
            atoms += [["slot", 0], ["slot", 1]]

        def range_predicate():
            return int4_range(*rng.choices(atoms, k=3), negated=bool(rng.randrange(2)), symmetric=bool(rng.randrange(2)))

        def predicate(depth):
            choice = rng.randrange(5) if depth else 0
            if choice <= 1:
                return range_predicate()
            if choice == 2:
                return ["null-test", bool(rng.randrange(2)), range_predicate()]
            if choice == 3:
                return ["logic", "NOT", predicate(depth - 1), None]
            return ["logic", rng.choice(("AND", "OR")), predicate(depth - 1), predicate(depth - 1)]

        def expression():
            if rng.randrange(3):
                return range_predicate()
            return ["int4-case", [[predicate(2), rng.choice(atoms)]], rng.choice([None, *atoms])]

        names = [rng.choice(("range", 'quoted"range', "範囲", "range")) for _ in range(rng.randrange(1, 5))]
        fields = [expression() for _ in names]
        yield {
            **projection(tag, fields, source_for(role), names),
            "where": predicate(rng.randrange(4)) if rng.randrange(3) else None,
        }


def positive_sql(tag):
    one, two, three = integer(1), integer(2), integer(3)
    n = 2147483647
    yield (
        "SELECT 2 BETWEEN ASYMMETRIC 2 AND 3 AS result",
        item(tag, "projection", int4_range(two, two, three), "unit"),
    )
    yield (
        "SELECT 2 NOT BETWEEN SYMMETRIC 3 AND 1 AS result",
        item(tag, "projection", int4_range(two, three, one, True, True), "unit"),
    )
    yield (
        "SELECT 1 BETWEEN NULL AND CAST(NULL AS pg_catalog.int4) AS result",
        item(tag, "projection", int4_range(one, NULL, NULL), "unit"),
    )
    yield (
        "SELECT CAST(NULL AS pg_catalog.int4) BETWEEN NULL AND 1 AS result",
        item(tag, "projection", int4_range(NULL, NULL, one), "unit"),
    )
    yield (
        "SELECT NULL BETWEEN 1 AND 2 AS result",
        item(tag, "projection", int4_range(NULL, one, two), "unit"),
    )
    yield (
        "SELECT 2147483647 BETWEEN -2147483648 AND 2147483647 AS result",
        item(tag, "projection", int4_range(integer(n), integer(-n - 1), integer(n)), "unit"),
    )
    field, bound = ["slot", 0], ["slot", 1]
    table = source_for("only")
    yield (
        "SELECT a.id BETWEEN a.delta AND 3 AS result FROM ONLY public.ir_evidence_accounts a",
        projection(tag, int4_range(field, bound, three), table),
    )
    yield (
        "SELECT a.id AS id, a.delta AS delta FROM public.ir_evidence_accounts a WHERE a.id NOT BETWEEN 1 AND a.delta",
        {**projection(tag, [field, bound], source_for("inherited"), ["id", "delta"]),
         "where": int4_range(field, one, bound, negated=True)},
    )


def negatives(tag):
    one, missing = integer(1), ["slot", 0]
    for triple in ((missing, one, one), (one, missing, one), (one, one, missing)):
        yield item(tag, "projection", int4_range(*triple), "unit"), "A tested predicate slot must belong to the SELECT source."
    yield item(tag, "where", int4_range(one, one, ["slot", 0, 1]), "only"), "A predicate slot must belong to a visible input occurrence."
    for sql, message in (
        ("SELECT (1 + 2) BETWEEN 1 AND 3 AS n", "A signed comparison operand requires a pure signed integer or NULL."),
        ("SELECT 1.5 BETWEEN 1 AND 3 AS n", "A signed comparison operand must resolve to pg_catalog.int4, pg_catalog.int8 or NULL."),
        ("SELECT NULL BETWEEN NULL AND 3 AS n", "Each range comparison requires a known signed operand."),
    ):
        yield analyzed("postgres", sql, version=tag), message


def requests(_engine, tag, specification):
    for index, request in enumerate(finite(tag, specification["finite"])):
        yield f"finite/{index}", request, request, None
    exploration = specification["exploration"]
    for seed in exploration["seeds"]:
        for index, request in enumerate(seeded(tag, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{index}", request, request, None
    for index, (sql, request) in enumerate(positive_sql(tag)):
        yield f"analysis/{index}", analyzed("postgres", sql, version=tag), request, None
    probe = {**item(tag, "projection", int4_range(integer(2), integer(1), integer(3)), "unit"), "shadow": True}
    yield "witness/shadow-operators", probe, probe, None
    for index, (request, message) in enumerate(negatives(tag)):
        yield f"negative/{index}", request, None, {"exception": EXCEPTION, "message": message}
