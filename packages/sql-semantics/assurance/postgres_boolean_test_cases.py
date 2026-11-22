"""Direct Boolean truth decisions and predicate tree schedules."""

from itertools import product
from random import Random

from postgres_int4_cases import EXCEPTION, integer, projection, source_for, source_role
from postgres_int4_distinctness_cases import distinctness, item
from postgres_int4_range_cases import NULL, int4_range
from server_cases import analyzed, node_key

SEMANTIC = "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\SemanticException"


def leaf(truth):
    return NULL if truth is None else ["boolean", truth]


def test(mode, predicate):
    return ["truth-test", mode, predicate]


def finite(tag, domain):
    for role, position, mode, value in product(domain["sources"], domain["placements"], domain["modes"], domain["truth"]):
        yield item(tag, position, test(mode, leaf(value)), role)


def finite_id(_engine, response, _item):
    where = response["where"]
    expression = response["columns"][0][0] if where is None else where
    return source_role(response["source"]), "projection" if where is None else "where", node_key(expression)


def finite_ids(_engine, scope, _tag):
    data = scope["finite"]
    width = len(data["truth"])
    cases = set()
    for index in range(len(data["modes"]) * width):
        operation, value = divmod(index, width)
        identity = node_key(["truth-test", data["modes"][operation], leaf(data["truth"][value])])
        cases.update((role, place, identity) for role in data["sources"] for place in data["placements"])
    return cases


def seeded(tag, seed, count, modes):
    rng = Random(seed)
    for _ in range(count):
        role = rng.choice(("unit", "only", "inherited"))
        ints = [integer(number) for number in (-7, -2, 0, 2, 3, 11)]
        if role != "unit":
            ints += [["slot", 0], ["slot", 1]]
        nullable = [NULL, *ints]

        def atomic():
            kind = rng.randrange(5)
            if kind == 0:
                return leaf(rng.choice((True, False, None)))
            if kind == 1:
                return ["comparison", rng.choice(("=", ">", "<>", "<=")), rng.choice(ints), rng.choice(ints)]
            if kind == 2:
                return distinctness(rng.choice(nullable), rng.choice(nullable), bool(rng.randrange(2)))
            if kind == 3:
                return int4_range(*rng.choices(nullable, k=3), negated=bool(rng.randrange(2)), symmetric=bool(rng.randrange(2)))
            return ["null-test", bool(rng.randrange(2)), rng.choice(nullable)]

        def decision(depth):
            choice = rng.randrange(4) if depth else 0
            if choice == 0:
                return atomic()
            if choice == 1:
                return test(rng.choice(modes), decision(depth - 1))
            if choice == 2:
                return ["logic", "NOT", decision(depth - 1), None]
            return ["logic", rng.choice(("AND", "OR")), decision(depth - 1), decision(depth - 1)]

        fields = []
        names = [rng.choice(("tested", 'name"test', "判定", "tested")) for _ in range(rng.randrange(1, 5))]
        for _ in names:
            operand = test(rng.choice(modes), decision(rng.randrange(3)))
            field = ["int4-case", [[operand, rng.choice(nullable)]], rng.choice([None, *nullable])] if rng.randrange(3) == 0 else operand
            fields.append(field)
        yield {**projection(tag, fields, source_for(role), names),
               "where": test(rng.choice(modes), decision(rng.randrange(3))) if rng.randrange(3) else None}


def originals(tag):
    yield "SELECT NULL IS TRUE AS result", item(tag, "projection", test("IS TRUE", NULL), "unit")
    yield ("SELECT CAST((NULL) AS pg_catalog.bool) IS NOT FALSE AS result",
           item(tag, "projection", test("IS NOT FALSE", NULL), "unit"))
    yield "SELECT (FALSE IS UNKNOWN) IS NOT TRUE AS result", item(
        tag, "projection", test("IS NOT TRUE", test("IS UNKNOWN", leaf(False))), "unit")
    lhs, rhs = ["slot", 0], ["slot", 1]
    yield ("SELECT (a.id BETWEEN a.delta AND 3) IS UNKNOWN AS result FROM ONLY public.ir_evidence_accounts a",
           item(tag, "projection", test("IS UNKNOWN", int4_range(lhs, rhs, integer(3))), "only"))
    yield (
        "SELECT a.id AS id,a.delta AS delta FROM public.ir_evidence_accounts a WHERE (a.id = a.delta) IS NOT FALSE",
        {**projection(tag, [lhs, rhs], source_for("inherited"), ["id", "delta"]),
         "where": test("IS NOT FALSE", ["comparison", "=", lhs, rhs])},
    )


def negatives(tag):
    lhs, rhs = ["slot", 0], integer(1)
    yield (item(tag, "projection", test("IS TRUE", ["comparison", "=", lhs, rhs]), "unit"),
           EXCEPTION, "A tested predicate slot must belong to the SELECT source.")
    yield (item(tag, "where", test("IS FALSE", distinctness(["slot", 0, 1], rhs)), "only"),
           EXCEPTION, "A predicate slot must belong to a visible input occurrence.")
    deep = NULL
    for _ in range(33):
        deep = test("IS UNKNOWN", deep)
    yield (item(tag, "projection", deep, "unit"), EXCEPTION, "Predicate depth or expanded node count exceeds")
    yield analyzed("postgres", "SELECT 1 IS TRUE AS result", version=tag), SEMANTIC, "must be type boolean"


def requests(_engine, tag, specification):
    for index, value in enumerate(finite(tag, specification["finite"])):
        yield f"finite/{index}", value, value, None
    settings = specification["exploration"]
    for seed in settings["seeds"]:
        for index, value in enumerate(seeded(tag, seed, settings["casesPerSeed"], specification["finite"]["modes"])):
            yield f"seed/{seed}/{index}", value, value, None
    for index, (sql, value) in enumerate(originals(tag)):
        yield f"analysis/{index}", analyzed("postgres", sql, version=tag), value, None
    for index, (value, exception, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", value, None, {"exception": exception, "message": reason}
