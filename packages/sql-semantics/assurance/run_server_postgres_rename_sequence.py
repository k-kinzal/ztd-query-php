"""Compare resolved PostgreSQL sequence renaming against the live catalog."""

from copy import deepcopy
from itertools import product
import json
import os
from random import Random
import sys

from native_postgres_fixture import ENV as FIXTURE_CONTAINER
from postgres_create_btree_cases import refusal
from postgres_rename_sequence_cases import LOCAL, MISSING, OTHER, PRESENT, ROLES, STATUS, analyzed, direct, expected, source, sql, trial
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_drop_table import specifications as drop_profiles
from run_server_join import main
from run_server_postgres_create_btree import observation, prepare
from run_server_postgres_drop_sequence import check_preparation
from sqlite_domain import RenderFault

LABEL = "postgres-rename-sequence"
CONTRACT_NAME = "postgres-rename-sequence-v1.json"
CONTRACT = "cbc29f97151b369e1572a4aecf2585b5fc970cf3ff41569920f5c9d3f22ef2b4"
OBSERVER = "postgres_rename_sequence_observer.py"
AXES = ("roles", "guard", "transactional", "occupiedDestination", "dependentDefault")
ABSENT = refusal("An absent sequence selector requires IF EXISTS.")
SAME = refusal("A present sequence rename requires a distinct destination.")


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-rename-sequence-contract")
    spec = json.loads(path.read_bytes())
    same([], spec["exploration"]["allowedExclusions"])
    spec["additionalPostgreSqlNegatives"] = [*sorted(unguarded(spec))] * 2
    return spec, {"postgres": drop_profiles()[1]["postgres"]}


def grid(spec):
    return product(*(spec["finite"][axis] for axis in AXES))


def unguarded(spec):
    return {key for key in grid(spec) if key[0] in MISSING and not key[1]}


def finite_ids(_, spec, _tag):
    independent = set(product(ROLES, (False, True), (False, True), (False, True), (False, True)))
    require(set(grid(spec)) == independent and len(independent) == 96 and len(unguarded(spec)) == 24,
            "postgres-rename-sequence-grid")
    return independent - unguarded(spec)


def analysis_finite_id(_, observed, item, command):
    key = tuple(item["coordinate"])
    same(sql(trial(item["release"], *key)), command)
    check_value(item, observed)
    return key


def direct_finite_id(_, observed, item, request):
    key = tuple(item["coordinate"])
    same(direct(trial(item["release"], *key)), request)
    check_value(item, observed)
    return key


def generated(tag, seed, count):
    rng = Random(seed)
    roles = list(ROLES)
    rng.shuffle(roles)
    for index in range(count):
        choice = roles[index % len(roles)]
        yield trial(tag, choice, choice in MISSING or bool(rng.getrandbits(1)), bool(rng.getrandbits(1)),
                    bool(rng.getrandbits(1)), bool(rng.getrandbits(1)), quoted_name=bool(rng.getrandbits(1)),
                    local_catalog=bool(rng.getrandbits(1)), temp_written=bool(rng.getrandbits(1)), base=rng.randrange(4, 80))


def edges(tag):
    yield "qualified-current-catalog", trial(tag, "local", True, True, False, True, quoted_name=True, local_catalog=True)
    yield "allocated-session-absence", trial(tag, "missing-temporary", True, True, False, False)
    missing = trial(tag, "missing-scoped", True, False, False, True)
    yield "absent-with-same-destination", {**missing, "destination": source(missing)[1]}


def refusals(tag):
    item = trial(tag, "local", True, True, False, True)
    conflict = {**item, "destination": item["name"], "negative": "same-coordinate"}
    yield analyzed(conflict), conflict, SAME
    wrong_kind = {**item, "command": f"ALTER SEQUENCE IF EXISTS {LOCAL}.consumer RENAME TO rejected",
                  "nativeRefusal": "42809", "negative": "wrong-kind"}
    yield analyzed(wrong_kind), wrong_kind, refusal('"consumer" is not a sequence', "SemanticException")
    foreign = {**item, "command": f"ALTER SEQUENCE IF EXISTS outside.{LOCAL}.ticket RENAME TO rejected",
               "nativeRefusal": "0A000", "negative": "external-catalog"}
    yield analyzed(foreign), foreign, refusal("An explicit PostgreSQL catalog requires the matching current database.")
    missing = trial(tag, "missing-scoped", True, False, False, False)
    yield {**analyzed(missing), "complete": False}, None, refusal("A sequence selector cannot infer absence in an open relation namespace.")
    stock = direct(item)
    stock["renameSequence"]["source"] = ["pg_catalog", "pg_class", "existing"]
    yield stock, None, refusal("A stock non-sequence relation cannot be a sequence target.")
    unguarded_absence = {**missing, "guard": False}
    yield direct(unguarded_absence), None, ABSENT


def requests(_, tag, spec):
    refused = unguarded(spec)
    for ordinal, key in enumerate(grid(spec)):
        item = trial(tag, *key, coordinate=list(key))
        if key in refused:
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
    for ordinal, (request, item, rejected) in enumerate(refusals(tag)):
        yield f"negative/{ordinal}", request, item, rejected


def check_value(item, observed):
    same(["constructed", item["release"] + "/utf8/standard-conforming-strings/native", direct(item)["renameSequence"]],
         [observed.get("status"), observed.get("profile"), observed.get("renameSequence")])


def observer_request(item, command):
    return {"trial": item, "sql": command}


def value_fields(value):
    return {field: val for field, val in value.items() if field != "oid"} if value is not None else None


def project(record):
    if record is None:
        return None
    return {**{field: value for field, value in record.items() if field != "consumerOid"},
            "sequences": {role: {"name": seq["name"], "value": value_fields(seq["value"])}
                          for role, seq in record["sequences"].items()}, "occupied": value_fields(record["occupied"])}


def identities(record):
    occupancy = record["occupied"]
    return [record["consumerOid"], occupancy["oid"] if occupancy is not None else None,
            [[role, seq["value"]["oid"], seq["file"]] for role, seq in sorted(record["sequences"].items())]]


def check_observation(item, actual, command):
    model = expected(item)
    witness = {"expected": model, "observed": actual, "sql": command}
    require(actual.get("status") == STATUS, "sequence-rename-observer-scope", witness)
    measured = {key: project(actual[key]) if key in ("before", "during", "settled") else actual[key] for key in model}
    require(measured == model, "meaning-mismatch", witness)
    before = identities(actual["before"])
    numbered = [number for number in [*before[:2], *(seq[1] for seq in before[2])] if number is not None]
    require(len(numbered) == len(set(numbered)) and all(type(value) is int and value > 0 for value in numbered)
            and all(type(seq[2]) is int and seq[2] > 0 for seq in before[2]), "meaning-mismatch", witness)
    for phase in ("during", "settled"):
        if actual[phase] is not None:
            require(identities(actual[phase]) == before, "meaning-mismatch", witness)
    error = actual["error"]
    require(error is None if model["sqlState"] == "00000"
            else error is not None and error[:2] == ["ERROR", model["sqlState"]], "meaning-mismatch", witness)


def observe_rejection(_, item, actual, command):
    same(sql(item), command)
    check_observation(item, actual, command)
    return ("edge", item["negative"]) if "negative" in item else ("finite", tuple(item["coordinate"]))


def rejected_finite_ids(spec, _tag):
    return {("finite", key) for key in unguarded(spec)} | {
        ("edge", name) for name in ("same-coordinate", "wrong-kind", "external-catalog")}


def sample_forms(forms, reply, _engine, item):
    for field in ("role", "guard", "active", "occupied", "dependent", "quoted", "catalog"):
        forms[f"{field}/{item[field]}"] += 1
    forms["shape/" + reply["renameSequence"]["source"][2]] += 1


def required_forms(*_):
    return {*(f"role/{role}" for role in ROLES),
            *(f"{axis}/{value}" for axis in ("guard", "active", "occupied", "dependent", "quoted", "catalog")
              for value in (False, True)), "shape/existing", "shape/missing-sequence", "shape/missing-namespace"}


def controls():
    item = trial("pg-17.2", "local", False, True, False, True)
    healthy = deepcopy({**expected(item), "error": None})
    for phase in ("before", "during", "settled"):
        state = healthy[phase]
        state["consumerOid"] = 19
        for index, value in enumerate(state["sequences"].values(), 30):
            value["value"]["oid"] = index
            value["file"] = index + 100
    check_observation(item, healthy, sql(item))
    broken = deepcopy(healthy)
    broken["during"]["sequences"]["local"]["file"] += 1
    try:
        check_observation(item, broken, sql(item))
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"changed-relation-file": error.detail}
    raise Failure("postgres-sequence-rename-ignored-storage-identity")


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    same(STATUS, witness["observed"]["status"])


def faults(_, tag):
    path = "sql-semantics-postgres/src/Ir/Schema/Sequence/Alter/RenameSequence.php"
    present = trial(tag, "local", False, True, False, True)
    missing = trial(tag, "missing-scoped", True, True, False, False)
    return [
        RenderFault("rename-sequence-replace-source", path, "$this->source->quoted()",
                    "str_replace('\"" + LOCAL + "\".', '\"" + OTHER + "\".', $this->source->quoted())",
                    present, "meaning-mismatch", direct(present)),
        RenderFault("rename-sequence-replace-destination", path, "$this->destination->quoted()", "$this->source->name->quoted()",
                    present, "meaning-mismatch", direct(present)),
        RenderFault("rename-sequence-drop-guard", path, "($this->ifExists ? 'IF EXISTS ' : '')", "''",
                    missing, "meaning-mismatch", direct(missing)),
    ]


if __name__ == "__main__":
    require(FIXTURE_CONTAINER in os.environ, "postgres-rename-sequence-disposable-service-required")
    main(sys.modules[__name__])
