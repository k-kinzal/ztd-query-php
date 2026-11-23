"""Independent projection of PostgreSQL's deleted rows and finite identities."""

from itertools import product
from random import Random

from postgres_int4_cases import EXCEPTION
from postgres_physical_text_admission import negative as physical_negative
from server_delete_cases import case as deletion, expected as effect, literal, observation as deletion_observation
from server_returning_model import fields_from, ordered_returning, project_returned

CONDITIONS = (None, ["boolean", False], ["comparison", "<", ["slot", 0], literal(0)], ["null-test", False, ["slot", 1]])
POLICIES = (False, True)


def project(tag, fields, where=None, only=False, columns=("id", "delta"), table="target"):
    return {**deletion("postgres", tag, where, only, columns, table),
            "returning": fields_from(fields)}


def finite(tag, specification):
    for only, where, fields in product(POLICIES, CONDITIONS, specification["finite"]["layouts"]):
        yield project(tag, fields, where, only)


def finite_id(_engine, response, item):
    layouts = item["finiteLayouts"]
    fields = [[operand[1], label] for operand, label in response["returning"]]
    return POLICIES.index(response["target"]["only"]), CONDITIONS.index(response["where"]), layouts.index(fields)


def finite_ids(_engine, specification, _tag):
    domain = specification["finite"]
    policies, conditions, layouts = (len(domain[key]) for key in ("policies", "predicates", "layouts"))
    return {(policy, condition, layout) for policy in range(policies) for condition in range(conditions) for layout in range(layouts)}


def seeded(tag, seed, count):
    rng = Random(seed)
    for _ in range(count):
        where = rng.choice((*CONDITIONS, ["comparison", ">", ["slot", 1], literal(rng.randrange(-6, 8))]))
        fields = [(rng.randrange(2), rng.choice(("same", "same", "ordre", "列", "a\"b"))) for _ in range(rng.randint(1, 5))]
        yield project(tag, fields, where, rng.choice(POLICIES), table=rng.choice(("target", "child")))


def analyzed(tag, sql, declarations=None):
    return {"dialect": "postgres", "release": tag, "sql": sql,
            "declarations": declarations or ["CREATE TABLE public.ir_delete_target (id INTEGER, delta INTEGER)"]}


def positives(tag):
    start = "DELETE FROM public.ir_delete_target"
    yield analyzed(tag, start + " RETURNING id"), project(tag, [(0, "id")], columns=("id",))
    yield (analyzed(tag, 'DELETE FROM ONLY public.ir_delete_target AS r WHERE r.delta IS NULL RETURNING r.*'),
           project(tag, [(1, "id"), (0, "delta")], ["null-test", False, ["slot", 0]], True, ("delta", "id")))
    yield (analyzed(tag, start + ' AS r RETURNING r.delta AS "remove""d", id AS duplicate, r.id AS duplicate'),
           project(tag, [(0, 'remove"d'), (1, "duplicate"), (1, "duplicate")], columns=("delta", "id")))
    yield analyzed(tag, start + " WHERE FALSE RETURNING *"), project(tag, [(0, "id"), (1, "delta")], CONDITIONS[1])
    yield (analyzed(tag, start + " WHERE id < 0 RETURNING delta AS change, id"),
           project(tag, [(1, "change"), (0, "id")], CONDITIONS[2]))


def negatives(tag):
    yield project(tag, [(2, "missing")]), EXCEPTION, "returned slot must belong"
    absent = {**project(tag, [(0, "other")]), "returning": [[["slot", 0, 1], "other"]]}
    yield absent, EXCEPTION, "must read the single target occurrence"
    yield project(tag, [(0, "n")] * 1025), EXCEPTION, "return at most 1024 fields"
    start = "DELETE FROM public.ir_delete_target RETURNING "
    yield (analyzed(tag, start + "missing"), "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\SemanticException",
           "Column missing does not exist")
    invalid, boundary = physical_negative("delete-returning")
    yield (analyzed(tag, start + "id", [f"CREATE TABLE public.ir_delete_target (id {invalid}, delta INTEGER)"]),
           boundary["exception"], boundary["message"])
    yield analyzed(tag, start + "id + 1 AS computed"), EXCEPTION, "requires a resolved target column"


def requests(_engine, tag, specification):
    layouts = specification["finite"]["layouts"]
    for index, item in enumerate(finite(tag, specification)):
        yield f"finite/{index}", item, {**item, "finiteLayouts": layouts}, None
    for seed in specification["exploration"]["seeds"]:
        for index, item in enumerate(seeded(tag, seed, specification["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item, None
    for index, (request, item) in enumerate(positives(tag)):
        yield f"analysis/{index}", request, item, None
    for index, (item, exception, message) in enumerate(negatives(tag)):
        yield f"negative/{index}", item, None, {"exception": exception, "message": message}


def observation(value):
    return ordered_returning(deletion_observation(value))


def expected(item):
    result = effect(item)
    return project_returned(item, result, (event[1:] for event in result["after"]["events"]))
