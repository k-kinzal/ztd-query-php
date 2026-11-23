"""Generate PostgreSQL null-safe integer comparisons and independently index their pairs."""

from itertools import product
from random import Random

from postgres_int4_cases import EXCEPTION, integer, projection, source_for, source_role
from postgres_int4_range_cases import NULL, operands
from server_cases import analyzed, node_key


def distinctness(left, right, same=False):
    return ["int4-distinctness", same, left, right]


def item(tag, where_or_projection, expression, role):
    source = source_for(role)
    if where_or_projection == "projection":
        return projection(tag, expression, source)
    return {**projection(tag, integer(11) if source is None else ["slot", 0], source), "where": expression}


def finite(tag, domain):
    for role, placement, same in product(domain["sources"], domain["placements"], domain["negated"]):
        for left, right in product(operands(domain, role), repeat=2):
            yield item(tag, placement, distinctness(left, right, same), role)


def finite_id(_engine, response, _item):
    target = "where" if response["where"] is not None else "projection"
    expression = response["where"] if target == "where" else response["columns"][0][0]
    return source_role(response["source"]), target, node_key(expression)


def finite_ids(_engine, specification, _tag):
    domain = specification["finite"]
    result = set()
    for scope in domain["sources"]:
        atoms = operands(domain, scope)
        n = len(atoms)
        for index in range(n * n):
            left, right = divmod(index, n)
            for clause in domain["placements"]:
                for same in domain["negated"]:
                    result.add((scope, clause, node_key(["int4-distinctness", same, atoms[left], atoms[right]])))
    return result


def seeded(tag, seed, count):
    rng = Random(seed)
    for _ in range(count):
        region = rng.choice(("unit", "only", "inherited"))
        variants = [NULL, *(integer(value) for value in (-2147483648, -9, -1, 0, 1, 2, 2147483647))]
        if region != "unit":
            variants += [["slot", 0], ["slot", 1]]

        def tested():
            return distinctness(rng.choice(variants), rng.choice(variants), bool(rng.randrange(2)))

        def condition(depth):
            form = rng.randrange(5) if depth else 0
            if form < 2:
                return tested()
            if form == 2:
                return ["null-test", bool(rng.randrange(2)), tested()]
            if form == 3:
                return ["logic", "NOT", condition(depth - 1), None]
            return ["logic", rng.choice(("AND", "OR")), condition(depth - 1), condition(depth - 1)]

        def field():
            if rng.randrange(3):
                return tested()
            return ["int4-case", [[condition(rng.randrange(3)), rng.choice(variants)]], rng.choice([None, *variants])]

        names = [rng.choice(("same", "same", 'many"roles', "符号")) for _ in range(rng.randrange(1, 5))]
        yield {
            **projection(tag, [field() for _ in names], source_for(region), names),
            "where": condition(rng.randrange(3)) if rng.randrange(3) else None,
        }


def originals(tag):
    yield ("SELECT NULL IS DISTINCT FROM NULL AS result",
           item(tag, "projection", distinctness(NULL, NULL), "unit"))
    yield ("SELECT CAST(NULL AS pg_catalog.int4) IS NOT DISTINCT FROM NULL AS result",
           item(tag, "projection", distinctness(NULL, NULL, True), "unit"))
    yield ("SELECT (-2147483648) IS NOT DISTINCT FROM -2147483648 AS result",
           item(tag, "projection", distinctness(integer(-2147483648), integer(-2147483648), True), "unit"))
    left, right = ["slot", 0], ["slot", 1]
    yield (
        "SELECT a.id IS DISTINCT FROM a.delta AS result FROM public.ir_evidence_accounts a",
        item(tag, "projection", distinctness(left, right), "inherited"),
    )
    yield (
        "SELECT a.id AS id,a.delta AS delta FROM ONLY public.ir_evidence_accounts a WHERE a.id IS NOT DISTINCT FROM NULL",
        {**projection(tag, [left, right], source_for("only"), ["id", "delta"]),
         "where": distinctness(left, NULL, True)},
    )


def negatives(tag):
    one, missing = integer(1), ["slot", 0]
    for left, right in ((missing, one), (one, missing)):
        yield item(tag, "projection", distinctness(left, right), "unit"), "A tested predicate slot must belong to the SELECT source."
    yield (item(tag, "where", distinctness(["slot", 0, 1], one), "only"),
           "A predicate slot must belong to a visible input occurrence.")
    yield analyzed("postgres", "SELECT (1 + 2) IS DISTINCT FROM 4 AS result", version=tag), "A signed comparison operand requires a pure signed integer or NULL."
    yield analyzed("postgres", "SELECT 1.5 IS DISTINCT FROM 2 AS result", version=tag), "A signed comparison operand must resolve to pg_catalog.int4, pg_catalog.int8 or NULL."


def requests(_engine, tag, specification):
    for index, value in enumerate(finite(tag, specification["finite"])):
        yield f"finite/{index}", value, value, None
    exploration = specification["exploration"]
    for seed in exploration["seeds"]:
        for index, value in enumerate(seeded(tag, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{index}", value, value, None
    for index, (sql, value) in enumerate(originals(tag)):
        yield f"analysis/{index}", analyzed("postgres", sql, version=tag), value, None
    shadowed = {**item(tag, "projection", distinctness(integer(2), integer(2), True), "unit"), "shadow": True}
    yield "witness/shadow-equality", shadowed, shadowed, None
    for index, (value, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", value, None, {"exception": EXCEPTION, "message": reason}
