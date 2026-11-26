"""Compare PostgreSQL sequence relocation against the live catalog."""

from copy import deepcopy
from itertools import product
import json
import os
from random import Random
import sys

from native_postgres_fixture import ENV as FIXTURE_CONTAINER
from postgres_create_btree_cases import refusal
from postgres_move_sequence_cases import (
    DESTINATIONS, LOCAL, MISSING, OTHER, PRESENT, ROLES, STATUS, analyzed, destination, direct, expected, source, sql, trial,
)
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_drop_table import specifications as drop_profiles
from run_server_join import main
from run_server_postgres_create_btree import observation, prepare
from run_server_postgres_drop_sequence import check_preparation
from sqlite_domain import RenderFault

LABEL = "postgres-move-sequence"
CONTRACT_NAME = "postgres-move-sequence-v1.json"
CONTRACT = "24c7fe38fbb38791b13d6881dafb951000e415708bb53977ca17c1cb832d0328"
OBSERVER = "postgres_move_sequence_observer.py"
AXES = ("roles", "destinations", "guard", "transactional", "owned")

ABSENT = refusal("An absent sequence selector requires IF EXISTS.")
UNDECLARED = refusal("A sequence move requires a declared destination namespace.")
TEMP_SOURCE = refusal("A sequence move requires a persistent source namespace.")
INVALID_DESTINATION = refusal("A sequence move requires a persistent destination outside TOAST.")


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-move-sequence-contract")
    spec = json.loads(path.read_bytes())
    same([], spec["exploration"]["allowedExclusions"])
    # Both construction paths cover each refused coordinate, followed by twelve separate boundary cases.
    spec["additionalPostgreSqlNegatives"] = [*sorted(unguarded(spec))] * 2 + list(range(10))
    return spec, {"postgres": drop_profiles()[1]["postgres"]}


def grid(spec):
    return product(*(spec["finite"][axis] for axis in AXES))


def unguarded(spec):
    return {key for key in grid(spec) if key[0] in MISSING and not key[2]}


def finite_ids(_, spec, _tag):
    independent = set(product(ROLES, DESTINATIONS, (False, True), (False, True), (False, True)))
    require(set(grid(spec)) == independent and len(independent) == 120 and len(unguarded(spec)) == 36,
            "postgres-move-sequence-grid")
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
        role = roles[index % len(roles)]
        target = rng.choice(DESTINATIONS)
        yield trial(tag, role, target, role in MISSING or bool(rng.getrandbits(1)),
                    bool(rng.getrandbits(1)), bool(rng.getrandbits(1)), quoted_name=bool(rng.getrandbits(1)),
                    local_catalog=bool(rng.getrandbits(1)), dependent=bool(rng.getrandbits(1)),
                    occupied=target != "same" and bool(rng.getrandbits(1)), base=rng.randrange(4, 80))


def edges(tag):
    yield "explicit-local-catalog", trial(tag, "local", "second", True, True, False, quoted_name=True, local_catalog=True)
    yield "absent-move-to-unallocated-namespace", trial(tag, "missing-namespace", "unallocated", True, True, True)
    yield "allocated-temporary-absence", trial(tag, "missing-temporary", "pg_temp", True, True, False)
    yield "persistent-stock-catalog", trial(tag, "local", "pg_catalog", False, True, False)
    yield "persistent-information-schema", trial(tag, "other", "information_schema", True, False, False)
    yield "occupied-destination", trial(tag, "local", "second", False, True, False, occupied=True)


def refusals(tag):
    present = trial(tag, "local", "first", True, True, False)
    absent = trial(tag, "missing-bare", "first", True, True, False)
    cases = (
        ("missing-destination", {**present, "destinationChoice": "unallocated"}, "3F000", UNDECLARED),
        ("temporary-source", trial(tag, "temporary", "first", True, True, False), "0A000", TEMP_SOURCE),
        ("temporary-destination", {**present, "destinationChoice": "pg_temp"}, "0A000", INVALID_DESTINATION),
        ("toast-destination", {**present, "destinationChoice": "pg_toast"}, "0A000", INVALID_DESTINATION),
        ("wrong-kind", {**present, "command": f'ALTER SEQUENCE IF EXISTS {LOCAL}.consumer SET SCHEMA public'},
         "42809", refusal('"consumer" is not a sequence', "SemanticException")),
        ("foreign-catalog", {**present, "command": f'ALTER SEQUENCE outside.{LOCAL}.ticket SET SCHEMA public'},
         "0A000", refusal("An explicit PostgreSQL catalog requires the matching current database.")),
    )
    for key, value, state, rejection in cases:
        item = {**value, "command": sql(value), "negative": key, "nativeRefusal": state}
        yield analyzed(item), item, rejection
    yield {**analyzed(absent), "complete": False}, None, refusal("A sequence selector cannot infer absence in an open relation namespace.")
    absent_direct = direct(absent)
    yield {**absent_direct, "moveSequence": {**absent_direct["moveSequence"], "destination": "public"}}, None, refusal(
        "An absent sequence move has no destination namespace.")
    present_direct = direct(present)
    yield {**present_direct, "moveSequence": {**present_direct["moveSequence"], "destination": None}}, None, refusal(
        "A present sequence move requires a destination namespace.")
    yield {**present_direct, "moveSequence": {**present_direct["moveSequence"], "destination": "pg_toast"}}, None, INVALID_DESTINATION
    yield {**present_direct, "moveSequence": {**present_direct["moveSequence"], "source": ["pg_catalog", "pg_class", "existing"]}}, None, refusal(
        "A stock non-sequence relation cannot be a sequence target.")
    yield direct(trial(tag, "temporary", "first", True, True, False)), None, TEMP_SOURCE


def requests(_, tag, spec):
    rejected = unguarded(spec)
    for ordinal, key in enumerate(grid(spec)):
        item = trial(tag, *key, coordinate=list(key))
        if key in rejected:
            yield f"negative-finite/{ordinal}", analyzed(item), item, ABSENT
            yield f"negative-direct/{ordinal}", direct(item), None, ABSENT
        else:
            yield f"finite/{ordinal}", analyzed(item), item, None
            yield f"direct/{ordinal}", direct(item), item, None
    for label, item in edges(tag):
        yield f"edge/{label}", analyzed(item), item, None
    options = spec["exploration"]
    for seed in options["seeds"]:
        for index, item in enumerate(generated(tag, seed, options["casesPerSeed"])):
            yield f"seed/{seed}/{index}", analyzed(item), item, None
            yield f"direct-seed/{seed}/{index}", direct(item), item, None
    for ordinal, (request, item, rejected) in enumerate(refusals(tag)):
        yield f"negative/{ordinal}", request, item, rejected


def check_value(item, observed):
    same(["constructed", item["release"] + "/utf8/standard-conforming-strings/native", direct(item)["moveSequence"]],
         [observed.get("status"), observed.get("profile"), observed.get("moveSequence")])


def observer_request(item, command):
    return {"trial": item, "sql": command}


def project(record):
    return {
        **{key: value for key, value in record.items() if key not in ("consumerOid", "defaultOid", "sequences", "occupied")},
        "sequences": {
            role: {**{key: value for key, value in detail.items() if key not in ("file", "value")},
                   "value": {key: value for key, value in detail["value"].items() if key not in ("oid", "ownership")}}
            for role, detail in record["sequences"].items()
        },
        "occupied": {key: value for key, value in record["occupied"].items() if key != "oid"} if record["occupied"] is not None else None,
    }


def identities(record):
    occupancy = record["occupied"]
    return [record["consumerOid"], occupancy["oid"] if occupancy is not None else None, record["defaultOid"],
            [[role, detail["value"]["oid"], detail["file"]] for role, detail in sorted(record["sequences"].items())]]


def check_observation(item, actual, command):
    model = expected(item)
    witness = {"expected": model, "observed": actual, "sql": command}
    require(actual.get("status") == STATUS, "sequence-move-observer-scope", witness)
    measured = {key: project(actual[key]) if key in ("before", "during", "settled") and actual[key] is not None
                else actual[key] for key in model}
    require(measured == model, "meaning-mismatch", witness)
    before = identities(actual["before"])
    unique_ids = [value for value in [*before[:2], *(entry[1] for entry in before[3])] if value is not None]
    require(len(unique_ids) == len(set(unique_ids)) and all(type(value) is int and value > 0 for value in unique_ids)
            and all(type(entry[2]) is int and entry[2] > 0 for entry in before[3])
            and before[2] == (actual["before"]["sequences"]["local"]["value"]["oid"] if item["dependent"] else None),
            "meaning-mismatch", witness)
    for phase in ("before", "during", "settled"):
        observed = actual[phase]
        if observed is not None:
            require(identities(observed) == before, "meaning-mismatch", witness)
            for detail in observed["sequences"].values():
                links = detail["value"]["ownership"]
                expected_edge = detail["ownership"]
                require((links == []) if expected_edge is None else len(links) == 1 and
                        int(links[0][0]) > 0 and [int(links[0][1]), links[0][2]] == expected_edge[-2:],
                        "meaning-mismatch", witness)
    error = actual["error"]
    require(error is None if model["sqlState"] == "00000"
            else error is not None and error[:2] == ["ERROR", model["sqlState"]], "meaning-mismatch", witness)


def observe_rejection(_, item, actual, command):
    same(sql(item), command)
    check_observation(item, actual, command)
    return ("edge", item["negative"]) if "negative" in item else ("finite", tuple(item["coordinate"]))


def rejected_finite_ids(spec, _tag):
    return {("finite", key) for key in unguarded(spec)} | {("edge", name) for name in
            ("missing-destination", "temporary-source", "temporary-destination", "toast-destination", "wrong-kind", "foreign-catalog")}


def sample_forms(forms, reply, _engine, item):
    for field in ("role", "destinationChoice", "guard", "active", "owned", "occupied", "dependent", "quoted", "catalog"):
        forms[f"{field}/{item[field]}"] += 1
    forms["shape/" + reply["moveSequence"]["source"][2]] += 1


def required_forms(*_):
    return {*(f"role/{role}" for role in ROLES), *(f"destinationChoice/{dest}" for dest in DESTINATIONS),
            *(f"{axis}/{value}" for axis in ("guard", "active", "owned", "occupied", "dependent", "quoted", "catalog")
              for value in (False, True)), "shape/existing", "shape/missing-sequence", "shape/missing-namespace"}


def controls():
    item = trial("pg-17.2", "local", "first", False, True, False)
    healthy = deepcopy({**expected(item), "error": None})
    for phase in ("before", "during", "settled"):
        state = healthy[phase]
        # The absent and transaction-restored phases can point to the same oracle dictionary.
        if "defaultOid" in state:
            continue
        state["consumerOid"] = 19
        state["defaultOid"] = 30
        for index, value in enumerate(state["sequences"].values(), 30):
            value["value"]["oid"] = index
            value["value"]["ownership"] = []
            value["file"] = index + 100
    check_observation(item, healthy, sql(item))
    altered = deepcopy(healthy)
    altered["during"]["sequences"]["local"]["scope"] = item["namespaces"]["local"]
    try:
        check_observation(item, altered, sql(item))
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"ignored-namespace-move": error.detail}
    raise Failure("postgres-sequence-move-ignored-namespace")


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    same(STATUS, witness["observed"]["status"])


def faults(_, tag):
    path = "sql-semantics-postgres/src/Ir/Schema/Sequence/Alter/MoveSequence.php"
    present = trial(tag, "local", "first", False, True, False)
    missing = trial(tag, "missing-scoped", "second", True, True, False)
    return [
        RenderFault("move-sequence-replace-source", path, "$this->source->quoted()",
                    "str_replace('\"" + LOCAL + "\".', '\"" + OTHER + "\".', $this->source->quoted())",
                    present, "meaning-mismatch", direct(present)),
        RenderFault("move-sequence-replace-destination", path, """($this->destination?->quoted() ?? '"pg_catalog"')""",
                    """'"pg_catalog"'""", present, "meaning-mismatch", direct(present)),
        RenderFault("move-sequence-drop-guard", path, "($this->ifExists ? 'IF EXISTS ' : '')", "''",
                    missing, "meaning-mismatch", direct(missing)),
    ]


if __name__ == "__main__":
    require(FIXTURE_CONTAINER in os.environ, "postgres-move-sequence-disposable-service-required")
    main(sys.modules[__name__])
