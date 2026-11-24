"""Compare resolved PostgreSQL sequence removal against native catalog effects."""

from copy import deepcopy
from itertools import product
import json
import os
from random import Random
import sys

from native_postgres_fixture import ENV as FIXTURE_CONTAINER
from postgres_create_btree_cases import refusal
from postgres_drop_sequence_cases import DATABASE, EXISTING, LOCAL, MISSING, OTHER, PLANS, STATUS, analyzed, direct, expected, sql, trial
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_drop_table import specifications as drop_profiles
from run_server_join import main
from run_server_postgres_create_btree import observation, prepare
from run_server_postgres_create_btree import check_preparation as check_libpq
from sqlite_domain import RenderFault

LABEL = "postgres-drop-sequence"
CONTRACT_NAME = "postgres-drop-sequence-v3.json"
CONTRACT = "dcf87dc6000d6cd99acb91edb79d618d3f8b62ef15e4bb2987383ce20231c924"
STOCK_NAME = "postgres-drop-sequence-stock-v1.json"
STOCK = "dcbf5a63db6b9156d42a69547f9595f885432531ec8fafaa89832652bb64bdb4"
OBSERVER = "postgres_drop_sequence_observer.py"
AXES = ("targetPlans", "guarded", "cascade", "transactional", "dependentDefault")
ABSENT = refusal("An absent sequence selector requires IF EXISTS.")
OPEN = refusal("A sequence selector cannot infer absence in an open relation namespace.")
WRONG = refusal("The selected relation is not a sequence.")


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-drop-sequence-contract")
    spec = json.loads(path.read_bytes())
    same([], spec["exploration"]["allowedExclusions"])
    spec["additionalPostgreSqlNegatives"] = [*sorted(unguarded_grid(spec))] * 2
    return spec, {"postgres": drop_profiles()[1]["postgres"]}


def check_preparation(engine, environment, preparation):
    check_libpq(engine, environment, preparation)
    filename = TOOL / STOCK_NAME
    require(digest(filename) == STOCK, "sequence-stock-contract")
    code = environment["database"][0]
    native = f"{int(code) // 10000}.{int(code) % 10000}"
    stock = json.loads(filename.read_bytes())["releases"][native]
    same([DATABASE, stock["sha256"], stock["count"]],
         [environment["currentDatabase"], environment["stockDigest"], environment["stockCount"]])


def grid(spec):
    for plan, *factors in product(*(spec["finite"][axis] for axis in AXES)):
        yield (tuple(plan), *factors)


def unguarded_grid(spec):
    return {entry for entry in grid(spec) if not entry[1] and any(role in MISSING for role in entry[0])}


def finite_ids(_engine, spec, tag):
    independent = set(product(PLANS, (False, True), (False, True), (False, True), (False, True)))
    require(set(grid(spec)) == independent and len(independent) == 128, "postgres-drop-sequence-grid")
    negative = unguarded_grid(spec)
    require(len(negative) == 32, "postgres-drop-sequence-negative-grid")
    return independent - negative


def coordinates(item):
    roles, *factors = item["coordinate"]
    return (tuple(roles), *factors)


def analysis_finite_id(_, reply, item, command):
    key = coordinates(item)
    same(sql(trial(item["release"], *key)), command)
    check_value(item, reply)
    return key


def direct_finite_id(_, reply, item, request):
    key = coordinates(item)
    same(direct(trial(item["release"], *key)), request)
    check_value(item, reply)
    return key


def generated(tag, seed, count):
    rng = Random(seed)
    roles = [*EXISTING, *MISSING]
    rng.shuffle(roles)
    for index in range(count):
        plan = [roles[index % len(roles)], *rng.choices(roles, k=index % 6)]
        yield trial(tag, plan, any(role in MISSING for role in plan) or bool(rng.getrandbits(1)),
                    bool(rng.getrandbits(1)), bool(rng.getrandbits(1)), bool(rng.getrandbits(1)),
                    quoted_name=bool(rng.getrandbits(1)), catalog=bool(rng.getrandbits(1)), base=rng.randrange(3, 71),
                    temp_written=bool(rng.getrandbits(1)))


def edges(tag):
    yield "checked-current-catalog", trial(tag, ["local", "other"], True, True, True, True, catalog=True)
    yield "bare-temporary-shadow", trial(tag, ["temporary", "other"], True, False, False, True, quoted_name=True)
    yield "allocated-temporary-absence", trial(tag, ["temporary-missing", "temporary"], True, True, True, False)
    yield "quoted-coordinates", trial(tag, ["local", "local", "missing-relation"], True, True, True, True, quoted_name=True)


def refusals(tag):
    value = trial(tag, ["local"], True, True, True, False)
    request = analyzed(value)
    item = {**value, "command": f"DROP SEQUENCE IF EXISTS {LOCAL}.consumer", "nativeRefusal": "42809", "negative": "wrong-kind"}
    yield analyzed(item), item, refusal('"consumer" is not a sequence', "SemanticException")
    item = {**value, "command": f"DROP SEQUENCE IF EXISTS wrong_catalog.{LOCAL}.ticket", "nativeRefusal": "0A000",
            "negative": "foreign-catalog"}
    yield analyzed(item), item, refusal("An explicit PostgreSQL catalog requires the matching current database.")
    yield {**request, "sql": f"DROP SEQUENCE IF EXISTS {LOCAL}.never_defined", "complete": False}, None, OPEN
    yield {**request, "declarations": [*request["declarations"], f"CREATE SEQUENCE {LOCAL}.ticket AS smallint"]}, None, refusal(
        "A sequence selector cannot resolve conflicting declarations.")
    collision = [f"CREATE INDEX crowded ON {LOCAL}.consumer (n)", f"CREATE SEQUENCE {OTHER}.crowded"]
    item = {**value, "command": "DROP SEQUENCE IF EXISTS crowded", "nativeRefusal": "42809",
            "negative": "declared-index", "extraFixture": collision}
    yield {**analyzed(item), "declarations": [*request["declarations"], *collision]}, item, WRONG
    collision = [f"CREATE SEQUENCE {LOCAL}.pg_class"]
    item = {**value, "command": "DROP SEQUENCE IF EXISTS pg_class", "nativeRefusal": "42809",
            "negative": "stock-relation", "extraFixture": collision}
    yield {**analyzed(item), "declarations": [*request["declarations"], *collision]}, item, WRONG
    bad = direct(value)
    bad["dropSequence"]["targets"][0][0] = "pg_temp_47"
    yield bad, None, refusal("A sequence selector cannot use a physical TOAST or temporary schema.")
    no_temp = [command for command in request["declarations"] if '"pg_temp".' not in command]
    yield {**request, "sql": "DROP SEQUENCE IF EXISTS pg_temp.session_absent", "declarations": no_temp}, None, refusal(
        "An absent temporary sequence requires a declared session namespace.")
    empty = direct(value)
    empty["dropSequence"]["targets"] = []
    yield empty, None, refusal("Sequence removal requires a nonempty list of checked selectors.")
    contradicting = direct(value)
    contradicting["dropSequence"]["targets"].append([LOCAL, "ticket", "missing-sequence"])
    yield contradicting, None, refusal("Sequence removal cannot disagree about a named target.")
    conflict = direct(value)
    conflict["dropSequence"]["targets"].append([LOCAL, "vacant", "missing-namespace"])
    yield conflict, None, refusal("Sequence removal cannot disagree about a named namespace.")
    unqualified = direct(value)
    unqualified["dropSequence"]["targets"][0][0] = None
    yield unqualified, None, refusal("A sequence selector requires an absolute namespace.")
    for target in (["pg_catalog", "pg_class", "existing"], [None, "pg_class", "missing-sequence"]):
        wrong_role = direct(value)
        wrong_role["dropSequence"]["targets"] = [target]
        yield wrong_role, None, refusal("A stock non-sequence relation cannot be a sequence target.")


def requests(_, tag, spec):
    negative = unguarded_grid(spec)
    for ordinal, key in enumerate(grid(spec)):
        plan, *factors = key
        item = trial(tag, plan, *factors, coordinate=[list(plan), *factors])
        if key in negative:
            yield f"negative-finite/{ordinal}", analyzed(item), item, ABSENT
            yield f"negative-direct/{ordinal}", direct(item), None, ABSENT
        else:
            yield f"finite/{ordinal}", analyzed(item), item, None
            yield f"direct/{ordinal}", direct(item), item, None
    for label, item in edges(tag):
        yield f"edge/{label}", analyzed(item), item, None
    settings = spec["exploration"]
    for seed in settings["seeds"]:
        for index, item in enumerate(generated(tag, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", analyzed(item), item, None
            yield f"direct-seed/{seed}/{index}", direct(item), item, None
    for index, (request, item, refused) in enumerate(refusals(tag)):
        yield f"negative/{index}", request, item, refused


def check_value(item, reply):
    same(["constructed", item["release"] + "/utf8/standard-conforming-strings/native", direct(item)["dropSequence"]],
         [reply.get("status"), reply.get("profile"), reply.get("dropSequence")])


def observer_request(item, command):
    return {"trial": item, "sql": command}


def projection(record):
    if record is None:
        return None
    return {**{key: value for key, value in record.items() if key != "consumerOid"},
            "sequences": {role: {key: value for key, value in sequence.items() if key != "oid"}
                          for role, sequence in record["sequences"].items()}}


def check_observation(item, actual, command):
    model = expected(item)
    witness = {"expected": model, "observed": actual, "sql": command}
    require(actual.get("status") == STATUS, "sequence-removal-observer-scope", witness)
    measured = {key: projection(actual[key]) if key in ("before", "during", "settled") else actual[key] for key in model}
    require(measured == model, "meaning-mismatch", witness)
    before = actual["before"]
    identities = {role: sequence["oid"] for role, sequence in before["sequences"].items()}
    distinct = {*identities.values(), before["consumerOid"]}
    require(len(distinct) == 4 and all(type(value) is int and value > 0 for value in distinct), "meaning-mismatch", witness)
    for phase in (actual["during"], actual["settled"]):
        if phase is not None:
            require(phase["consumerOid"] == before["consumerOid"]
                    and all(sequence["oid"] == identities[role] for role, sequence in phase["sequences"].items()),
                    "meaning-mismatch", witness)
    error = actual["error"]
    require(error is None if model["sqlState"] == "00000"
            else error is not None and error[:2] == ["ERROR", model["sqlState"]], "meaning-mismatch", witness)


def observe_rejection(_, item, actual, command):
    same(sql(item), command)
    check_observation(item, actual, command)
    return ("edge", item["negative"]) if "negative" in item else ("finite", coordinates(item))


def rejected_finite_ids(spec, _tag):
    return {("finite", key) for key in unguarded_grid(spec)} | {("edge", label) for label in ("wrong-kind", "foreign-catalog", "declared-index", "stock-relation")}


def sample_forms(forms, reply, _engine, item):
    resolved = reply["dropSequence"]
    for role in item["selectors"]:
        forms["role/" + role] += 1
    for key in ("guard", "cascade", "active", "dependent", "quoted", "catalog", "tempWritten"):
        forms[f"{key}/{item[key]}"] += 1
    forms[f"width/{len(resolved['targets'])}"] += 1
    forms["repeat/" + str(len(set(map(tuple, resolved["targets"]))) != len(resolved["targets"]))] += 1


def required_forms(*_):
    return {*(f"role/{role}" for role in (*EXISTING, *MISSING)),
            *(f"{key}/{value}" for key in ("guard", "cascade", "active", "dependent", "quoted", "catalog", "tempWritten", "repeat")
              for value in (False, True)), "width/1", "width/6"}


def controls():
    item = trial("pg-17.2", ["local", "missing-relation", "unqualified-missing"], True, True, True, True)
    good = deepcopy({**expected(item), "error": None})
    for phase in ("before", "during", "settled"):
        good[phase]["consumerOid"] = 11
        for role, sequence in good[phase]["sequences"].items():
            sequence["oid"] = 20 + EXISTING.index(role)
    check_observation(item, good, sql(item))
    different = deepcopy(good)
    different["settled"]["rows"] = list(different["settled"]["rows"])
    different["settled"]["rows"].pop()
    try:
        check_observation(item, different, sql(item))
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"settled-dropped-row": error.detail}
    raise Failure("postgres-drop-sequence-comparator-ignored-rollback")


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    same(STATUS, witness["observed"]["status"])


def faults(_, tag):
    path = "sql-semantics-postgres/src/Ir/Schema/Sequence/DropSequence.php"
    ordered = trial(tag, PLANS[-1], True, True, False, False)
    decoy = trial(tag, ["local"], True, True, True, False)
    cascade = trial(tag, ["local", "other"], True, True, True, True)
    return [
        RenderFault("drop-sequence-reverse-selectors", path, "foreach ($this->targets as $target)",
                    "foreach (array_reverse($this->targets) as $target)", ordered, "meaning-mismatch", direct(ordered)),
        RenderFault("drop-sequence-choose-decoy", path, "$target->quoted()",
                    "str_replace('\"" + LOCAL + "\".', '\"" + OTHER + "\".', $target->quoted())", decoy, "meaning-mismatch", direct(decoy)),
        RenderFault("drop-sequence-replace-cascade", path, "($this->cascade ? ' CASCADE' : ' RESTRICT')",
                    "' RESTRICT'", cascade, "meaning-mismatch", direct(cascade)),
    ]


if __name__ == "__main__":
    require(FIXTURE_CONTAINER in os.environ, "postgres-drop-sequence-disposable-service-required")
    main(sys.modules[__name__])
