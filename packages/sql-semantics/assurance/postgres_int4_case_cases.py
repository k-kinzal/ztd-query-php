"""Ordered direct CASE values and independently indexed finite shapes."""

from itertools import product
from random import Random

from postgres_int4_cases import EXCEPTION, integer, operation, projection, source_for
from server_cases import analyzed, node_key

NULL = ["null"]


def decision(value):
    return NULL if value is None else ["boolean", value]


def result(value):
    return NULL if value is None else integer(value)


def case(arms, otherwise=None):
    return ["int4-case", [list(arm) for arm in arms], otherwise]


def fallback(form):
    return {"omitted": None, "null": NULL, "integer": integer(11)}[form]


def finite(tag, contract):
    arms = list(product(map(decision, contract["conditions"]), map(result, contract["results"])))
    for length in contract["armCounts"]:
        for sequence in product(arms, repeat=length):
            for form in contract["fallbacks"]:
                yield projection(tag, case(sequence, fallback(form)))


def finite_id(_engine, response, _item):
    return node_key(response["columns"][0][0])


def finite_ids(_engine, specification, _tag):
    domain = specification["finite"]
    choices = len(domain["conditions"]) * len(domain["results"])
    keys = set()
    for length in domain["armCounts"]:
        for rank in range(choices ** length):
            indices = [(rank // choices ** position) % choices for position in reversed(range(length))]
            arms = []
            for index in indices:
                predicate, then = divmod(index, len(domain["results"]))
                arms.append([decision(domain["conditions"][predicate]), result(domain["results"][then])])
            for form in domain["fallbacks"]:
                keys.add(node_key(["int4-case", arms, fallback(form)]))
    return keys


def seeded(tag, seed, count):
    rng = Random(seed)
    for _ in range(count):
        role = rng.choice(("unit", "only", "inherited"))
        literals = [integer(value) for value in (-9, -2, 0, 3, 5, 19)]
        leaves = literals + ([] if role == "unit" else [["slot", 0], ["slot", 1]])

        def scalar(depth, nullable=True):
            atoms = [*leaves, NULL] if nullable else leaves
            if depth == 0:
                return rng.choice(atoms)
            form = rng.randrange(4)
            if form < 2:
                return rng.choice(atoms)
            if form == 2:
                return operation(rng.choice(("+", "-", "*")), integer(rng.choice((-3, 0, 6))), scalar(depth - 1, False))
            return branch(depth - 1)

        def predicate():
            form = rng.randrange(4)
            if form == 0:
                return decision(rng.choice((True, False, None)))
            comparison = ["comparison", rng.choice(("=", "<>", ">", "<=")), rng.choice(leaves), rng.choice(leaves)]
            return comparison if form < 3 else ["logic", "OR", comparison, decision(rng.choice((False, None)))]

        def branch(depth):
            arms = [(predicate(), scalar(depth)) for _ in range(rng.randrange(1, 5))]
            other = scalar(depth) if rng.randrange(3) else None
            return case(arms, other)

        labels = [rng.choice(("result", 'part"2', "結果", "result")) for _ in range(rng.randrange(1, 4))]
        yield projection(tag, [branch(rng.randrange(1, 4)) for _ in labels], source_for(role), labels)


def positive_sql(tag):
    id_slot, delta = ["slot", 0], ["slot", 1]
    yes, no = decision(True), decision(False)
    yield ("SELECT CASE WHEN TRUE THEN 3 WHEN TRUE THEN 8 ELSE 1 END AS choice",
           projection(tag, case([(yes, integer(3)), (yes, integer(8))], integer(1)), label="choice"))
    yield ("SELECT CASE WHEN NULL THEN 3 WHEN FALSE THEN 8 END AS answer",
           projection(tag, case([(NULL, integer(3)), (no, integer(8))]), label="answer"))
    yield ("SELECT CASE WHEN true THEN CAST(NULL AS pg_catalog.int4) ELSE NULL END AS empty",
           projection(tag, case([(yes, NULL)], NULL), label="empty"))
    guarded = case([(["comparison", "<>", delta, integer(0)], operation("/", integer(10), delta))], integer(99))
    yield ("SELECT id AS id, CASE WHEN delta <> 0 THEN 10 / delta ELSE 99 END AS guarded FROM public.ir_evidence_accounts",
           projection(tag, [id_slot, guarded], source_for("inherited"), ["id", "guarded"]))
    inner = case([(["comparison", "<", id_slot, integer(0)], delta)], NULL)
    nested = operation("+", integer(2), case([(["null-test", False, id_slot], integer(8))], inner))
    yield ("SELECT 2 + CASE WHEN id IS NULL THEN 8 ELSE CASE WHEN id < 0 THEN delta ELSE NULL END END AS nested FROM ONLY public.ir_evidence_accounts",
           projection(tag, nested, source_for("only"), "nested"))


def negatives(tag):
    yes = decision(True)
    integer_value = integer(1)
    missing = ["slot", 0]
    for expression, message in (
        (case([]), "needs a nonempty list"),
        (case([(["comparison", "=", missing, integer_value], integer_value)]), "A CASE condition slot must belong"),
        (case([(decision(False), missing)], integer_value), "Column slot must belong"),
        (case([(yes, integer_value)], missing), "Column slot must belong"),
    ):
        yield projection(tag, expression), EXCEPTION, message
    yield analyzed("postgres", "SELECT CASE WHEN true THEN NULL ELSE NULL END AS n", version=tag), EXCEPTION, "must resolve to pg_catalog.int4"
    yield analyzed("postgres", "SELECT CASE 1 WHEN 1 THEN 2 ELSE 3 END AS n", version=tag), EXCEPTION, "requires the searched form"
    yield (analyzed("postgres", "SELECT CASE WHEN true THEN 1 ELSE false END AS n", version=tag),
           "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\SemanticException", "CASE types integer and boolean cannot be matched")


def requests(_engine, tag, specification):
    for index, item in enumerate(finite(tag, specification["finite"])):
        yield f"finite/{index}", item, item, None
    settings = specification["exploration"]
    for seed in settings["seeds"]:
        for index, item in enumerate(seeded(tag, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (sql, item) in enumerate(positive_sql(tag)):
        yield f"analysis/{index}", analyzed("postgres", sql, version=tag), item, None
    for index, (item, exception, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, {"exception": exception, "message": reason}
