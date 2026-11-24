"""Compare ordered PostgreSQL sequence logging effects."""

from copy import deepcopy
from itertools import product
import json
import os
from random import Random
import sys

from native_postgres_fixture import ENV as FIXTURE_CONTAINER
from postgres_create_btree_cases import refusal
from postgres_sequence_logging_cases import (
    LOCAL, MISSING, MODES, OTHER, PLANS, PRESENT, ROLES, STATUS, analyzed, direct, expected, source, sql, trial,
)
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_drop_table import specifications as drop_profiles
from run_server_join import main
from run_server_postgres_create_btree import observation, prepare
from run_server_postgres_drop_sequence import check_preparation
from run_server_postgres_reconfigure_sequence import project as project_generator
from sqlite_domain import RenderFault

LABEL = "postgres-sequence-logging"
CONTRACT_NAME = "postgres-sequence-logging-v1.json"
CONTRACT = "888fc4009a3aa9647bcc19688afedddbfdfb16a73ad9af2e83b5d5e6b6117661"
OBSERVER = "postgres_sequence_logging_observer.py"
ABSENT = refusal("An absent sequence selector requires IF EXISTS.")
PREFIX = refusal("A sequence logging prefix must request one consistent mode.")
TEMPORARY = refusal("Changing sequence logging requires a persistent source.")
NATIVE_EDGES = ("temporary-source", "wrong-kind", "foreign-catalog")


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-sequence-logging-contract")
    spec = json.loads(path.read_bytes())
    same([], spec["exploration"]["allowedExclusions"])
    spec["additionalPostgreSqlNegatives"] = [*sorted(disallowed(spec))] * 2 + list(range(7))
    return spec, {"postgres": drop_profiles()[1]["postgres"]}


def grid(spec):
    finite = spec["finite"]
    plans = [plan for length in finite["length"] for plan in product(finite["actions"], repeat=length)]
    return product(finite["roles"], plans, finite["guard"], finite["transactional"])


def disallowed(spec):
    return {key for key in grid(spec) if (key[0] in MISSING and not key[2])
            or (key[0] in PRESENT and len(set(key[1][:-1])) > 1)}


def finite_ids(_, spec, _tag):
    registered = set(product(ROLES, PLANS, (False, True), (False, True)))
    require(set(grid(spec)) == registered and len(registered) == 280 and len(disallowed(spec)) == 116,
            "postgres-sequence-logging-grid")
    return registered - disallowed(spec)


def identity(item):
    role, modes, guard, active = item["coordinate"]
    return role, tuple(modes), guard, active


def analysis_finite_id(_, observed, item, command):
    key = identity(item)
    same(sql(trial(item["release"], *key)), command)
    check_value(item, observed)
    return key


def direct_finite_id(_, observed, item, request):
    key = identity(item)
    same(direct(trial(item["release"], *key)), request)
    check_value(item, observed)
    return key


def generated(tag, seed, count):
    rng = Random(seed)
    roles = list(ROLES)
    rng.shuffle(roles)
    modes = tuple(MODES)
    for index in range(count):
        length = index % 8 + 1
        role = rng.choice(PRESENT) if length == 8 else roles[index % len(roles)]
        assignments = [rng.choice(modes)] * (length - 1) + [rng.choice(modes)]
        yield trial(tag, role, assignments, role in MISSING or bool(rng.getrandbits(1)),
                    bool(rng.getrandbits(1)), quoted_name=bool(rng.getrandbits(1)),
                    catalog=bool(rng.getrandbits(1)), owned=bool(rng.getrandbits(1)), base=rng.randrange(3, 90))


def edges(tag):
    yield "qualified-release", trial(tag, "local", ["unlogged"], True, True, quoted_name=True, catalog=True, owned=True)
    yield "retained-owned-unlogged-decoy", trial(tag, "other", ["unlogged", "logged"], False, True, owned=True)
    yield "already-logged", trial(tag, "local", ["logged"], False, False, owned=True)
    yield "known-temporary-absence", trial(tag, "missing-temporary", ["unlogged", "logged"], True, True)
    yield "native-prefix-conflict", trial(tag, "other", ["logged", "unlogged"], False, True)


def refusals(tag):
    item = trial(tag, "local", ["logged"], True, False)
    session = trial(tag, "temporary", ["unlogged"], True, True)
    temporary = {**session, "command": sql(session), "negative": "temporary-source", "nativeRefusal": "42P16"}
    yield analyzed(temporary), temporary, TEMPORARY
    wrong = {**item, "command": f"ALTER SEQUENCE IF EXISTS {LOCAL}.consumer SET LOGGED",
             "negative": "wrong-kind", "nativeRefusal": "42809"}
    yield analyzed(wrong), wrong, refusal('"consumer" is not a sequence', "SemanticException")
    foreign = {**item, "command": f"ALTER SEQUENCE outside.{LOCAL}.ticket SET UNLOGGED",
               "negative": "foreign-catalog", "nativeRefusal": "0A000"}
    yield analyzed(foreign), foreign, refusal("An explicit PostgreSQL catalog requires the matching current database.")
    absent = trial(tag, "missing-scoped", ["logged"], True, True)
    yield {**analyzed(absent), "complete": False}, None, refusal("A sequence selector cannot infer absence in an open relation namespace.")
    yield direct(session), None, TEMPORARY
    request = direct(item)
    request["sequenceLogging"]["modes"] = []
    yield request, None, refusal("A present sequence requires at least one logging assignment.")
    request = direct(item)
    request["sequenceLogging"]["modes"] = ["untyped"]
    yield request, None, refusal("Sequence logging needs an ordered list of modes.")
    request = direct(absent)
    request["sequenceLogging"]["modes"] = ["unlogged"]
    yield request, None, refusal("An absent sequence cannot carry logging assignments.")
    request = direct(item)
    request["sequenceLogging"]["source"] = ["pg_catalog", "pg_class", "existing"]
    yield request, None, refusal("A stock non-sequence relation cannot be a sequence target.")


def requests(_, tag, spec):
    refused = disallowed(spec)
    for ordinal, key in enumerate(grid(spec)):
        item = trial(tag, *key, coordinate=list(key))
        if key in refused:
            analyzed_failure = PREFIX
            direct_failure = PREFIX
            if key[0] in MISSING:
                analyzed_failure = refusal(f"Relation {source(item)[1]} does not exist.", "SemanticException")
                direct_failure = ABSENT
            yield f"negative-finite/{ordinal}", analyzed(item), item, analyzed_failure
            yield f"negative-direct/{ordinal}", direct(item), None, direct_failure
        else:
            yield f"finite/{ordinal}", analyzed(item), item, None
            yield f"direct/{ordinal}", direct(item), item, None
    for name, item in edges(tag):
        yield f"edge/{name}", analyzed(item), item, None
    seeds = spec["exploration"]
    for seed in seeds["seeds"]:
        for index, item in enumerate(generated(tag, seed, seeds["casesPerSeed"])):
            yield f"seed/{seed}/{index}", analyzed(item), item, None
            yield f"direct-seed/{seed}/{index}", direct(item), item, None
    for index, (request, item, problem) in enumerate(refusals(tag)):
        yield f"negative/{index}", request, item, problem


def check_value(item, actual):
    same(["constructed", item["release"] + "/utf8/standard-conforming-strings/native", direct(item)["sequenceLogging"]],
         [actual.get("status"), actual.get("profile"), actual.get("sequenceLogging")])


def observer_request(item, command):
    return {"trial": item, "sql": command}


def project(record):
    value = project_generator(record)
    del value["defaultOid"]
    return value


def check_observation(item, actual, command):
    model = expected(item)
    witness = {"expected": model, "observed": actual, "sql": command}
    require(actual.get("status") == STATUS, "sequence-logging-observer-scope", witness)
    measured = {key: project(actual[key]) if key in ("before", "during", "settled") and actual[key] is not None
                else actual[key] for key in model}
    require(measured == model, "meaning-mismatch", witness)
    old = actual["before"]
    local_oid = old["sequences"]["local"]["value"]["oid"]
    ids = [old["consumerOid"], *(sequence["value"]["oid"] for sequence in old["sequences"].values())]
    require(len(ids) == len(set(ids)) and all(type(value) is int and value > 0 for value in ids),
            "meaning-mismatch", witness)
    for phase in ("before", "during", "settled"):
        observed = actual[phase]
        if observed is None:
            continue
        require(observed["consumerOid"] == old["consumerOid"] and observed["defaultOid"] == local_oid
                and observed["temporaryNamespace"] == old["temporaryNamespace"], "meaning-mismatch", witness)
        for role, sequence in observed["sequences"].items():
            before = old["sequences"][role]
            changes_file = model["rewritten"] and role == item["role"] and (phase == "during" or phase == "settled" and not item["active"])
            require(sequence["value"]["oid"] == before["value"]["oid"]
                    and sequence["value"]["ownership"] == before["value"]["ownership"]
                    and type(sequence["file"]) is int and sequence["file"] > 0
                    and (sequence["file"] != before["file"]) == changes_file, "meaning-mismatch", witness)
    error = actual["error"]
    require(error is None if model["sqlState"] == "00000"
            else error is not None and error[:2] == ["ERROR", model["sqlState"]], "meaning-mismatch", witness)
    if model["sqlState"] == "0A000" and "nativeRefusal" not in item:
        require(error[2] == "cannot change persistence setting twice", "meaning-mismatch", witness)


def observe_rejection(_, item, actual, command):
    same(sql(item), command)
    check_observation(item, actual, command)
    return ("edge", item["negative"]) if "negative" in item else ("finite", identity(item))


def rejected_finite_ids(spec, _tag):
    return {("finite", key) for key in disallowed(spec)} | {("edge", label) for label in NATIVE_EDGES}


def sample_forms(forms, reply, _engine, item):
    for key in ("role", "guard", "active", "catalog", "quoted", "oldOwner"):
        forms[f"{key}/{item[key]}"] += 1
    for mode in reply["sequenceLogging"]["modes"]:
        forms["mode/" + mode] += 1
    forms["length/" + str(len(reply["sequenceLogging"]["modes"]))] += 1


def sample_native_forms(forms, actual, _engine, _item):
    forms["state/" + actual["sqlState"]] += 1
    forms[f"rewritten/{actual['rewritten']}"] += 1


def required_forms(*_):
    return {*(f"role/{role}" for role in ROLES), *(f"mode/{mode}" for mode in MODES),
            *(f"{axis}/{value}" for axis in ("guard", "active", "catalog", "quoted", "oldOwner") for value in (True, False)),
            "state/00000", "state/0A000", "rewritten/True", "rewritten/False", "length/0", "length/1", "length/8"}


def controls():
    item = trial("pg-17.2", "local", ["logged", "unlogged"], False, True)
    healthy = deepcopy({**expected(item), "error": None})
    for phase in ("before", "during", "settled"):
        state = healthy[phase]
        state.update(consumerOid=13, defaultOid=30, temporaryNamespace=["pg_temp_1", 15])
        for index, (role, detail) in enumerate(state["sequences"].items(), 30):
            detail["value"]["oid"] = index
            detail["value"]["ownership"] = []
            detail["file"] = index + (200 if role == "local" and phase == "during" else 100)
    check_observation(item, healthy, sql(item))
    broken = deepcopy(healthy)
    broken["during"]["sequences"]["local"]["value"]["persistence"] = "p"
    try:
        check_observation(item, broken, sql(item))
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"ignored-logging-effect": error.detail}
    raise Failure("postgres-sequence-logging-ignored-storage-transition")


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    same(STATUS, witness["observed"]["status"])


def faults(_, tag):
    path = "sql-semantics-postgres/src/Ir/Schema/Sequence/Alter/SetSequenceLogging.php"
    local = trial(tag, "local", ["logged", "unlogged"], False, True)
    other = trial(tag, "other", ["logged", "unlogged"], False, True)
    absent = trial(tag, "missing-scoped", ["unlogged"], True, True)
    return [
        RenderFault("sequence-logging-target-decoy", path, "$this->source->quoted()",
                    "str_replace('\"" + LOCAL + "\".', '\"" + OTHER + "\".', $this->source->quoted())",
                    local, "meaning-mismatch", direct(local)),
        RenderFault("sequence-logging-drop-prefix", path, "implode(', ', $commands)", "$commands[count($commands) - 1]",
                    other, "meaning-mismatch", direct(other)),
        RenderFault("sequence-logging-reverse-order", path, "implode(', ', $commands)", "implode(', ', array_reverse($commands))",
                    local, "meaning-mismatch", direct(local)),
        RenderFault("sequence-logging-drop-guard", path, "($this->ifExists ? 'IF EXISTS ' : '')", "''",
                    absent, "meaning-mismatch", direct(absent)),
    ]


if __name__ == "__main__":
    require(FIXTURE_CONTAINER in os.environ, "postgres-sequence-logging-disposable-service-required")
    main(sys.modules[__name__])
