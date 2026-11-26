"""Compare the resolved declaration and generator against native PostgreSQL."""

from copy import deepcopy
from itertools import product
import json
import os
from random import Random
import sys

from native_postgres_fixture import ENV as FIXTURE_CONTAINER
from postgres_create_btree_cases import refusal
from postgres_create_sequence_cases import DATABASE, MODES, POLICIES, STATUS, TYPES, analyzed, direct, expected, resolved, sql, trial
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_drop_table import specifications as drop_profiles
from run_server_join import main
from run_server_postgres_create_btree import observation, prepare
from run_server_postgres_create_btree import check_preparation as check_libpq
from sqlite_domain import RenderFault

LABEL = "postgres-create-sequence"
CONTRACT_NAME = "postgres-create-sequence-v3.json"
CONTRACT = "2456da29e44e0cb0758f2628a2ae7a783bd90752a599b54e062a43d9c96bd230"
OBSERVER = "postgres_create_sequence_observer.py"
AXES = ("types", "directions", "policies", "persistence")
ZERO = "A sequence increment cannot be zero."
CACHE = "A sequence cache must be positive."
LIMITS = "Sequence limits must increase within the selected integer storage."
START = "The sequence start and restart must be within its limits."
PLACEMENT = "A sequence namespace must agree with its temporary storage."


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-create-sequence-contract")
    spec = json.loads(path.read_bytes())
    same([], spec["exploration"]["allowedExclusions"])
    return spec, {"postgres": drop_profiles()[1]["postgres"]}


def check_preparation(engine, environment, preparation):
    check_libpq(engine, environment, preparation)
    same([DATABASE, "postgres"], [environment["currentDatabase"], environment["owner"]])


def grid(spec):
    return product(*(spec["finite"][axis] for axis in AXES))


def finite_ids(_engine, spec, _tag):
    independent = set(product(TYPES, (1, -1), POLICIES, ("permanent", "unlogged", "temporary")))
    require(set(grid(spec)) == independent and len(independent) == 54, "postgres-create-sequence-grid")
    return independent


def analysis_finite_id(_, reported, item, command):
    coordinate = tuple(item["coordinate"])
    same(sql(trial(item["release"], *coordinate)), command)
    check_value(item, reported)
    return coordinate


def direct_finite_id(_, reported, item, request):
    coordinate = tuple(item["coordinate"])
    same(direct(trial(item["release"], *coordinate)), request)
    check_value(item, reported)
    return coordinate


def generated(tag, seed, count):
    rng = Random(seed)
    policies, kinds, storage = list(POLICIES), list(TYPES), list(MODES)
    rng.shuffle(policies)
    rng.shuffle(kinds)
    rng.shuffle(storage)
    for index in range(count):
        occupancy = {3: "sequence", 7: "table"}.get(index)
        yield trial(tag, kinds[(index // 3) % len(kinds)], rng.choice((1, -1)), policies[index % len(policies)],
                    storage[index % len(storage)], span=rng.randrange(7, 73), step=rng.randrange(2, 21),
                    cache=rng.randrange(1, 6), active=bool(rng.getrandbits(1)), cycle=bool(rng.getrandbits(1)),
                    quoted_name=bool(rng.getrandbits(1)), relative=bool(rng.getrandbits(1)),
                    guard=occupancy is not None or bool(rng.getrandbits(1)), occupied=occupancy, explicit_none=bool(rng.getrandbits(1)))


def edges(tag):
    outside = trial(tag, "smallint", 1, "default", "permanent", active=True, cycle=True)
    outside.update(increment=9223372036854775807)
    yield "bigint-increment-narrow-storage", outside
    signed = trial(tag, "bigint", -1, "default", "unlogged", active=True, cycle=True)
    signed.update(increment=-9223372036854775808, initial=-9223372036854775808)
    yield "signed-64-bit-minimum", signed
    implicit = trial(tag, "integer", 1, "default", "temporary", explicit_none=True)
    implicit.update(implicitTemp=True, explicitDefaults=True, bareRestart=True)
    yield "implicit-temporary-with-none-owner", implicit
    yield "global-temporary-warning", trial(tag, "integer", 1, "explicit", "global", active=True, relative=True)
    for kind in ("table", "sequence"):
        yield f"guarded-existing-{kind}", trial(tag, "smallint", 1, "restart", "permanent", guard=True, occupied=kind)
    local = trial(tag, "integer", -1, "restart", "unlogged", quoted_name=True, active=True)
    local.update(catalogWritten=True)
    yield "explicit-current-catalog", local
    yield "startup-and-exhaustion", trial(tag, "smallint", 1, "restart", "permanent", active=True, cycle=False)


def refusals(tag):
    base = trial(tag, "smallint", 1, "default", "permanent", active=True)
    temporary = trial(tag, "smallint", 1, "default", "temporary", active=True)
    name = '"ir_sequence_fixture"."ticket"'
    commands = [
        (base, f"CREATE SEQUENCE {name} INCREMENT 0", "22023", refusal(ZERO)),
        (base, f"CREATE SEQUENCE {name} MINVALUE 1 MAXVALUE 1", "22023", refusal(LIMITS)),
        (base, f"CREATE SEQUENCE {name} AS smallint MAXVALUE 32768", "22023", refusal(LIMITS)),
        (base, f"CREATE SEQUENCE {name} MAXVALUE 10 RESTART 11", "22023", refusal(START)),
        (base, f"CREATE SEQUENCE {name} NO MINVALUE MINVALUE 1", "42601", refusal("A sequence option may be specified only once.")),
        (base, f"CREATE SEQUENCE {name} INCREMENT 1.5", "22P02", refusal("A sequence option requires a signed decimal integer.")),
        (base, f"CREATE SEQUENCE absent_database.{name}", "0A000", refusal("An explicit PostgreSQL catalog requires the matching current database.")),
        (base, f"CREATE TEMP SEQUENCE {name}", "42P16", refusal("cannot create temporary relation in non-temporary schema", "SemanticException")),
        (base, f"CREATE SEQUENCE {name} OWNED BY ir_sequence_fixture.owner.missing", None, refusal("Sequence ownership requires one declared column.")),
        (temporary, "CREATE UNLOGGED SEQUENCE pg_temp.ticket", "42P16", refusal("only temporary relations may be created in temporary schemas", "SemanticException")),
    ]
    for index, (item, command, state, boundary) in enumerate(commands):
        chosen = {**item, "command": command, "nativeRefusal": state, "negative": index}
        yield analyzed(chosen), chosen if state is not None else None, boundary
    for fields, boundary in (
        ({"increment": "0"}, ZERO), ({"cache": "0"}, CACHE), ({"minimum": "9", "maximum": "9"}, LIMITS),
        ({"start": "32768"}, START), ({"initial": "0"}, START),
        ({"persistence": "TEMPORARY"}, PLACEMENT),
        ({"target": ["pg_temp_99", "ticket"]}, "A sequence name cannot select a numbered temporary schema."),
    ):
        request = direct(base)
        request["createSequence"].update(fields)
        yield request, None, refusal(boundary)


def requests(_, tag, spec):
    for ordinal, key in enumerate(grid(spec)):
        item = trial(tag, *key, coordinate=list(key))
        yield f"finite/{ordinal}", analyzed(item), item, None
        yield f"direct/{ordinal}", direct(item), item, None
    for label, item in edges(tag):
        yield f"edge/{label}", analyzed(item), item, None
    for seed in spec["exploration"]["seeds"]:
        for index, item in enumerate(generated(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{index}", analyzed(item), item, None
            yield f"direct-seed/{seed}/{index}", direct(item), item, None
    for index, (request, native, boundary) in enumerate(refusals(tag)):
        yield f"negative/{index}", request, native, boundary


def check_value(item, reported):
    same(["constructed", item["release"] + "/utf8/standard-conforming-strings/native", direct(resolved(item))["createSequence"]],
         [reported.get("status"), reported.get("profile"), reported.get("createSequence")])


def observer_request(item, command):
    return {"trial": item, "sql": command}


def stripped(state):
    if state is None:
        return None
    return {field: {key: val for key, val in entry.items() if key != "oid"} if entry is not None else None
            for field, entry in state.items()}


def check_identities(actual, witness):
    decoy = actual["before"]["decoy"]["oid"]
    selected = actual["before"]["selected"]
    current = actual["during"]
    rollback = actual["rollback"]
    require(type(decoy) is int and decoy > 0 and all(state is None or state["decoy"]["oid"] == decoy
            for state in (current, rollback)), "meaning-mismatch", witness)
    if selected is not None:
        require(type(selected["oid"]) is int and selected["oid"] > 0 and selected["oid"] != decoy
                and all(state is None or state["selected"]["oid"] == selected["oid"]
                        for state in (current, rollback)), "meaning-mismatch", witness)
    elif current is not None:
        created = current["selected"]["oid"]
        require(type(created) is int and created > 0 and created != decoy, "meaning-mismatch", witness)


def check_observation(item, actual, command):
    model = expected(item)
    witness = {"expected": model, "observed": actual, "sql": command}
    require(actual.get("status") == STATUS, "sequence-observer-scope", witness)
    measured = {key: stripped(actual[key]) if key in ("before", "during", "rollback") else actual[key] for key in model}
    require(measured == model, "meaning-mismatch", witness)
    error = actual["error"]
    require(error is None if model["sqlState"] == "00000"
            else error is not None and error[:2] == ["ERROR", model["sqlState"]], "meaning-mismatch", witness)
    check_identities(actual, witness)


def observe_rejection(_, item, actual, command):
    same(item["command"], command)
    check_observation(item, actual, command)
    return item["negative"]


def rejected_finite_ids(_, _tag):
    return set(range(10)) - {8}


def sample_forms(forms, reply, _, item):
    operand = reply["createSequence"]
    forms["type/" + operand["storage"].lower()] += 1
    forms["persistence/" + operand["persistence"]] += 1
    forms["policy/" + item["policy"]] += 1
    forms["direction/" + ("descending" if int(operand["increment"]) < 0 else "ascending")] += 1
    for field in ("cycle", "ifNotExists"):
        forms[f"{field}/{operand[field]}"] += 1
    for field in ("active", "relative", "explicitNone"):
        forms[f"{field}/{item[field]}"] += 1
    forms["previous/" + (item["occupied"] or "vacant")] += 1
    forms["coordinates/" + ("quoted" if '"' in operand["target"][1] else "plain")] += 1
    forms["restart/" + ("separate" if operand["start"] != operand["initial"] else "catalog")] += 1
    forms["cache/" + ("single" if operand["cache"] == "1" else "multiple")] += 1


def required_forms(*_):
    return {*(f"type/{kind}" for kind in TYPES), *(f"policy/{policy}" for policy in POLICIES),
            *(f"persistence/{value[0]}" for value in MODES.values()),
            *(f"{field}/{value}" for field in ("cycle", "ifNotExists", "active", "relative", "explicitNone") for value in (True, False)),
            "previous/sequence", "previous/table", "previous/vacant", "coordinates/quoted", "coordinates/plain",
            "restart/separate", "restart/catalog", "direction/ascending", "direction/descending", "cache/single", "cache/multiple"}


def controls():
    item = trial("pg-17.2", "smallint", 1, "restart", "temporary", active=True)
    good = deepcopy({**expected(item), "error": None})
    for phase in ("before", "during", "rollback"):
        good[phase]["decoy"]["oid"] = 15
    good["during"]["selected"]["oid"] = 25
    check_observation(item, good, sql(item))
    changed = deepcopy(good)
    changed["calls"][0]["rows"][0][0] += 1
    try:
        check_observation(item, changed, sql(item))
    except Failure as failure:
        same("meaning-mismatch", failure.signal)
        return {"changed-first-generated-value": failure.detail}
    raise Failure("sequence-comparator-ignored-restart")


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    same([STATUS, "00000"], [witness["observed"]["status"], witness["observed"]["sqlState"]])


def faults(_, tag):
    path = "sql-semantics-postgres/src/Ir/Schema/Sequence/CreateSequence.php"
    item = trial(tag, "integer", 1, "restart", "permanent", active=True)
    return [
        RenderFault("sequence-restart-erased", path, ". $restart . ' CACHE '", ". ' CACHE '", item, "meaning-mismatch", direct(item)),
        RenderFault("sequence-direction-reversed", path, "' INCREMENT BY ' . $numbers->increment->decimal",
                    "' INCREMENT BY -' . $numbers->increment->decimal", item, "meaning-mismatch", direct(item)),
        RenderFault("sequence-cycling-erased", path, "($numbers->cycle ? ' CYCLE' : ' NO CYCLE')",
                    "' NO CYCLE'", item, "meaning-mismatch", direct(item)),
    ]


if __name__ == "__main__":
    require(FIXTURE_CONTAINER in os.environ, "postgres-create-sequence-disposable-service-required")
    main(sys.modules[__name__])
