"""Finite and seeded PostgreSQL UPDATE layouts with old- and new-row rules."""

from itertools import product
from random import Random

from postgres_int4_cases import EXCEPTION
from postgres_physical_text_admission import negative as physical_negative
from server_join_cases import slot
from server_returning_model import fields_from, ordered_returning, project_returned
from server_update_cases import case as update, expected as effect, literal, observation as update_observation

ASSIGNMENTS = ([[slot(0), literal(7)]], [[slot(0), slot(1)], [slot(1), slot(0)]], [[slot(1), ["null"]]])
PREDICATES = (None, ["boolean", False], ["null-test", False, slot(1)])


def project(tag, fields, assignments, where=None, only=False, columns=("id", "delta"), table="target"):
    return {**update("postgres", tag, columns, assignments, where, only, table), "returning": fields_from(fields)}


def finite(tag, contract):
    for only, assignments, where, fields in product((False, True), ASSIGNMENTS, PREDICATES, contract["finite"]["layouts"]):
        yield project(tag, fields, assignments, where, only)


def finite_id(_engine, response, item):
    fields = [[reference[1], label] for reference, label in response["returning"]]
    return int(response["target"]["only"]), ASSIGNMENTS.index(response["assignments"]), PREDICATES.index(response["where"]), item["finiteLayouts"].index(fields)


def finite_ids(_engine, contract, _tag):
    dimensions = ("policies", "assignments", "predicates", "layouts")
    return set(product(*(range(len(contract["finite"][dimension])) for dimension in dimensions)))


def seeded(tag, seed, count):
    rng = Random(seed)
    for _ in range(count):
        candidates = [slot(0), slot(1), literal(rng.choice((-2147483648, -3, 0, 5, 2147483647))), ["null"]]
        assignments = [[slot(dest), rng.choice(candidates)] for dest in rng.sample((0, 1), rng.randint(1, 2))]
        condition = ["comparison", rng.choice(("=", "<", "<>")), rng.choice((slot(0), slot(1))), literal(rng.randrange(-8, 8))]
        where = rng.choice((*PREDICATES, condition))
        fields = [(rng.randrange(2), rng.choice(("repeat", "repeat", "other", "結果", 'a"b'))) for _ in range(rng.randrange(1, 6))]
        yield project(tag, fields, assignments, where, bool(rng.getrandbits(1)), table=rng.choice(("target", "child", "control")))


def analyzed(tag, suffix, declaration=None):
    return {"dialect": "postgres", "release": tag, "sql": "UPDATE " + suffix,
            "declarations": [declaration or "CREATE TABLE public.ir_update_target (id INTEGER, delta INTEGER)"]}


def positives(tag):
    table = "public.ir_update_target"
    yield (analyzed(tag, table + " SET id=delta, delta=id RETURNING ir_update_target.id AS newer, delta AS previous_id"),
           project(tag, [(0, "newer"), (1, "previous_id")], ASSIGNMENTS[1]))
    yield (analyzed(tag, "ONLY " + table + " AS r SET id=11 WHERE r.delta IS NULL RETURNING r.*"),
           project(tag, [(1, "id"), (0, "delta")], [[slot(1), literal(11)]], ["null-test", False, slot(0)],
                   True, ("delta", "id")))
    yield (analyzed(tag, table + " SET id=7 WHERE FALSE RETURNING *"),
           project(tag, [(0, "id"), (1, "delta")], ASSIGNMENTS[0], PREDICATES[1]))
    yield (analyzed(tag, table + ' AS r SET id=id RETURNING r.delta AS "two""labels", r.id AS repeat, id AS repeat'),
           project(tag, [(1, 'two"labels'), (0, "repeat"), (0, "repeat")], [[slot(0), slot(0)]]))


def negatives(tag):
    yield project(tag, [(2, "missing")], ASSIGNMENTS[0]), EXCEPTION, "returned slot must belong"
    alien = {**project(tag, [(0, "alien")], ASSIGNMENTS[0]), "returning": [[slot(0, 1), "alien"]]}
    yield alien, EXCEPTION, "must read the single target occurrence"
    yield project(tag, [(0, "n")] * 1025, ASSIGNMENTS[0]), EXCEPTION, "return at most 1024 fields"
    base = "public.ir_update_target SET id=7 RETURNING "
    yield (analyzed(tag, base + "missing"), "SqlSemantics\\Platform\\PostgreSql\\Model\\Diagnostic\\SemanticException",
           "Column missing does not exist")
    invalid, boundary = physical_negative("update-returning")
    yield (analyzed(tag, base + "delta", f"CREATE TABLE public.ir_update_target (id INTEGER, delta {invalid})"),
           boundary["exception"], boundary["message"])
    yield analyzed(tag, base + "id + 1 AS computed"), EXCEPTION, "requires a resolved target column"


def requests(_engine, tag, contract):
    for index, value in enumerate(finite(tag, contract)):
        yield f"finite/{index}", value, {**value, "finiteLayouts": contract["finite"]["layouts"]}, None
    settings = contract["exploration"]
    for seed in settings["seeds"]:
        for index, value in enumerate(seeded(tag, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", value, value, None
    for index, (request, value) in enumerate(positives(tag)):
        yield f"analysis/{index}", request, value, None
    for index, (value, exception, reason) in enumerate(negatives(tag)):
        yield f"negative/{index}", value, None, {"exception": exception, "message": reason}


def expected(item):
    effects = effect(item)
    return project_returned(item, effects, (event[3:] for event in effects["after"]["events"]))


def observation(value):
    return ordered_returning(update_observation(value))
