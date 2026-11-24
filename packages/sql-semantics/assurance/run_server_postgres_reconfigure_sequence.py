"""Compare checked PostgreSQL sequence reconfiguration against both native releases."""

from copy import deepcopy
from itertools import product
import json
import os
from random import Random
import re
import sys

from native_postgres_fixture import ENV as FIXTURE_CONTAINER
from postgres_create_btree_cases import refusal
from postgres_reconfigure_sequence_cases import (
    ASSIGNMENTS, LOCAL, MISSING, OTHER, OWNER_TABLE, PRESENT, ROLES, STATUS, TOKEN, active_role, analyzed, direct,
    expected, source, sql, trial,
)
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_drop_table import specifications as drop_profiles
from run_server_join import main
from run_server_postgres_create_btree import observation, prepare
from run_server_postgres_drop_sequence import check_preparation
from sqlite_domain import RenderFault

LABEL = "postgres-reconfigure-sequence"
CONTRACT_NAME = "postgres-reconfigure-sequence-v5.json"
CONTRACT = "450292a076f75dbc9e455cb7894325b4f28b3adc5eace78dbf86b4b1b48584be"
OBSERVER = "postgres_reconfigure_sequence_observer.py"
AXES = ("roles", "assignments", "guard", "transactional")
ABSENT = refusal("An absent sequence selector requires IF EXISTS.")
LIMITS = refusal("Sequence limits must increase within the selected integer storage.")
POSITIONS = refusal("The sequence start and restart must be within its limits.")
NATIVE_EDGES = ("duplicate", "zero", "cache", "inverted", "storage", "start", "unknown-owner-column", "other-owner-schema",
                "identity-option", "foreign-catalog", "wrong-kind")


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-reconfigure-sequence-contract")
    spec = json.loads(path.read_bytes())
    same([], spec["exploration"]["allowedExclusions"])
    spec["additionalPostgreSqlNegatives"] = [*sorted(unguarded(spec))] * 2 + list(range(12))
    return spec, {"postgres": drop_profiles()[1]["postgres"]}


def grid(spec):
    return product(*(spec["finite"][key] for key in AXES))


def unguarded(spec):
    return {key for key in grid(spec) if key[0] in MISSING and not key[2]}


def finite_ids(_, spec, _tag):
    independent = set(product(ROLES, ASSIGNMENTS, (False, True), (False, True)))
    require(set(grid(spec)) == independent and len(independent) == 168 and len(unguarded(spec)) == 42,
            "postgres-reconfigure-sequence-grid")
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


def generated(tag, seed, count, original_count):
    rng = Random(seed)
    roles = list(ROLES)
    rng.shuffle(roles)
    require(count == original_count + 1, "postgres-reconfigure-sequence-increment-witness-budget")
    for i in range(original_count):
        role = roles[i % len(roles)]
        family = ASSIGNMENTS[(i + seed) % len(ASSIGNMENTS)]
        common = {"base": rng.randrange(4, 55), "quoted_name": bool(rng.getrandbits(1)),
                  "catalog": bool(rng.getrandbits(1)), "warm": bool(rng.getrandbits(1)),
                  "reversed_options": bool(rng.getrandbits(1))}
        item = trial(tag, role, family, role in MISSING or bool(rng.getrandbits(1)), bool(rng.getrandbits(1)), **common)
        if family == "numeric-configuration":
            high = rng.randrange(30, 130)
            choices = {
                "storage": rng.choice(("SMALLINT", "INTEGER", "BIGINT")),
                "increment": str(rng.choice((-4, -3, -1, 1, 2, 5))),
                "minimum": str(-high),
                "maximum": str(high),
                "start": str(rng.randrange(-high, high)),
                "restart": rng.choice((TOKEN, str(rng.randrange(-high, high)))),
                "cache": str(rng.randrange(1, 6)),
                "cycle": bool(rng.getrandbits(1)),
            }
            item["changes"] = {key: value for key, value in choices.items() if rng.getrandbits(1)}
            if not item["changes"]:
                item["changes"]["restart"] = TOKEN
        if role in PRESENT and bool(set(item["changes"]) - {"owner"}) and (item["warm"] or "restart" in item["changes"]):
            item["probeValues"] = 3
        yield item

    role = rng.choice(PRESENT)
    step = rng.choice((-7, -3, -1, 1, 2, 5, 8))
    changes = {"increment": str(step), "cache": str(rng.randrange(1, 5))}
    if rng.getrandbits(1):
        changes["restart"] = str(rng.randrange(4, 18))
    item = trial(
        tag, role, "numeric-configuration", bool(rng.getrandbits(1)), bool(rng.getrandbits(1)),
        base=rng.randrange(4, 55), quoted_name=bool(rng.getrandbits(1)), catalog=bool(rng.getrandbits(1)),
        warm=bool(rng.getrandbits(1)), varied=changes, reversed_options=bool(rng.getrandbits(1)),
    )
    yield {**item, "probeValues": 4}


def edges(tag):
    yield "retained-new-upper-type-limit", {**trial(tag, "local", "numeric-configuration", False, True,
            varied={"storage": "INTEGER", "increment": "-1", "maximum": TOKEN, "start": "8"}), "probeValues": 4}
    yield "retained-new-lower-type-limit", {**trial(tag, "local", "numeric-configuration", True, True, descending=True,
            varied={"storage": "INTEGER", "increment": "1", "minimum": TOKEN, "start": "-4"}), "probeValues": 4}
    for cycle in (False, True):
        edge = trial(tag, "other", "numeric-configuration", False, True, warm=True,
                     varied={"minimum": "2", "maximum": "105", "restart": "103", "increment": "2", "cache": "2", "cycle": cycle})
        yield "wrap" if cycle else "no-wrap", {**edge, "probeValues": 5}
    absent = trial(tag, "missing-namespace", "restart-at-start", True, False)
    yield "unevaluated-duplicate-and-owner", {**absent,
            "command": "ALTER SEQUENCE IF EXISTS ir_counter_absent.none_here CACHE 0 CACHE 1 OWNED BY nowhere.receipt.id"}
    yield "state-dependent-restart-failure", trial(tag, "local", "explicit-restart", False, True,
                                                   varied={"restart": "9999999"})
    yield "declared-current-catalog", trial(tag, "local", "start-and-restart", True, True, quoted_name=True, catalog=True)


def refusals(tag):
    present = trial(tag, "local", "explicit-restart", True, True, warm=True)
    local, other = quoted_name(present, "local"), quoted_name(present, "other")
    prefix = f"ALTER SEQUENCE {local}.ticket "
    absent = trial(tag, "missing-bare", "explicit-restart", True, True)
    invalid = (
        ("RESTART WITH 1 RESTART", "42601", refusal("A sequence option may be specified only once.")),
        ("INCREMENT 0", "22023", refusal("A sequence increment cannot be zero.")),
        ("CACHE 0", "22023", refusal("A sequence cache must be positive.")),
        ("MINVALUE 9 MAXVALUE 9", "22023", LIMITS),
        ("AS smallint MAXVALUE 40000", "22023", LIMITS),
        ("MINVALUE 1 START 0", "22023", POSITIONS),
        (f"OWNED BY {local}.{OWNER_TABLE}.absent", "42703", refusal("Sequence ownership requires one declared column.")),
        (f"OWNED BY {other}.{OWNER_TABLE}.anchor", "55000", refusal("The sequence and its owning column must share a namespace.")),
        ("SEQUENCE NAME duplicate", "42601", refusal("invalid sequence option SEQUENCE NAME", "SemanticException")),
    )
    for name, (suffix, code, rejection) in zip(NATIVE_EDGES[:-2], invalid):
        item = {**present, "command": prefix + suffix, "negative": name, "nativeRefusal": code}
        yield analyzed(item), item, rejection
    foreign = {**present, "command": f"ALTER SEQUENCE outside.{local}.ticket RESTART", "negative": "foreign-catalog", "nativeRefusal": "0A000"}
    yield analyzed(foreign), foreign, refusal("An explicit PostgreSQL catalog requires the matching current database.")
    wrong_kind = {**present, "command": f"ALTER SEQUENCE {local}.consumer RESTART", "negative": "wrong-kind", "nativeRefusal": "42809"}
    message = '"consumer" is not a sequence' if tag == "pg-16.6" else 'cannot open relation "consumer"'
    yield analyzed(wrong_kind), wrong_kind, refusal(message, "SemanticException")
    yield {**analyzed(absent), "complete": False}, None, refusal("A sequence selector cannot infer absence in an open relation namespace.")
    for changes, rejection in (({}, refusal("A sequence change requires at least one assignment.")),
                               (None, refusal("A present sequence requires checked changes."))):
        request = direct(present)
        request["reconfigureSequence"]["changes"] = changes
        yield request, None, rejection
    request = direct(absent)
    request["reconfigureSequence"]["changes"] = {"restart": TOKEN}
    yield request, None, refusal("An absent sequence cannot carry changes.")
    request = direct(present)
    request["reconfigureSequence"]["source"] = ["pg_catalog", "pg_class", "existing"]
    yield request, None, refusal("A stock non-sequence relation cannot be a sequence target.")


def quoted_name(item, role):
    return '"' + item["namespaces"][role].replace('"', '""') + '"'


def requests(_, tag, spec):
    rejected = unguarded(spec)
    for ordinal, key in enumerate(grid(spec)):
        item = trial(tag, *key, coordinate=list(key))
        if key in rejected:
            missing = refusal(f"Relation {source(item)[1]} does not exist.", "SemanticException")
            yield f"negative-finite/{ordinal}", analyzed(item), item, missing
            yield f"negative-direct/{ordinal}", direct(item), None, ABSENT
        else:
            yield f"finite/{ordinal}", analyzed(item), item, None
            yield f"direct/{ordinal}", direct(item), item, None
    for label, item in edges(tag):
        yield f"edge/{label}", analyzed(item), item, None
    options = spec["exploration"]
    for seed in options["seeds"]:
        for index, item in enumerate(generated(tag, seed, options["casesPerSeed"], options["originalCasesPerSeed"])):
            yield f"seed/{seed}/{index}", analyzed(item), item, None
            yield f"direct-seed/{seed}/{index}", direct(item), item, None
    for ordinal, (request, item, rejected) in enumerate(refusals(tag)):
        yield f"negative/{ordinal}", request, item, rejected


def check_value(item, reply):
    actual = reply.get("reconfigureSequence")
    if actual is not None and actual["changes"] is not None:
        actual = {**actual, "changes": {key: value for key, value in actual["changes"].items() if value is not None}}
    same(["constructed", item["release"] + "/utf8/standard-conforming-strings/native", direct(item)["reconfigureSequence"]],
         [reply.get("status"), reply.get("profile"), actual])


def observer_request(item, command):
    return {"trial": item, "sql": command}


def project(record):
    name, _ = record["temporaryNamespace"]

    def coordinate(edge):
        if edge is None or edge[0] != name:
            return edge
        return ["pg_temp", *edge[1:]]

    return {**{key: value for key, value in record.items() if key not in ("consumerOid", "sequences", "temporaryNamespace")},
            "sequences": {role: {"value": {key: value for key, value in detail["value"].items() if key not in ("oid", "ownership")},
                                 "ownership": coordinate(detail["ownership"])} for role, detail in record["sequences"].items()}}


def check_observation(item, actual, command):
    model = expected(item)
    witness = {"expected": model, "observed": actual, "sql": command}
    require(actual.get("status") == STATUS, "sequence-reconfigure-observer-scope", witness)
    measured = {key: project(actual[key]) if key in ("before", "during", "settled") and actual[key] is not None
                else actual[key] for key in model}
    require(measured == model, "meaning-mismatch", witness)
    before = actual["before"]
    temporary = before["temporaryNamespace"]
    require(re.fullmatch(r"pg_temp_[0-9]+", temporary[0]) and type(temporary[1]) is int and temporary[1] > 0,
            "sequence-reconfigure-session-namespace", witness)
    ids = [before["consumerOid"], *(value["value"]["oid"] for value in before["sequences"].values())]
    require(len(ids) == len(set(ids)) and all(type(value) is int and value > 0 for value in ids), "meaning-mismatch", witness)
    for phase in ("before", "during", "settled"):
        record = actual[phase]
        if record is None:
            continue
        require(record["consumerOid"] == before["consumerOid"] and record["temporaryNamespace"] == temporary,
                "meaning-mismatch", witness)
        for role, value in record["sequences"].items():
            prior = before["sequences"][role]
            moving_file = model["rewritten"] and role == active_role(item) and (phase == "during" or phase == "settled" and not item["active"])
            require(value["value"]["oid"] == prior["value"]["oid"] and type(value["file"]) is int and value["file"] > 0
                    and (value["file"] != prior["file"]) == moving_file, "meaning-mismatch", witness)
            links = value["value"]["ownership"]
            edge = value["ownership"]
            require((links == []) if edge is None else len(links) == 1 and int(links[0][0]) > 0 and
                    [int(links[0][1]), links[0][2]] == edge[-2:], "meaning-mismatch", witness)
    error = actual["error"]
    require(error is None if model["sqlState"] == "00000"
            else error is not None and error[:2] == ["ERROR", model["sqlState"]], "meaning-mismatch", witness)


def observe_rejection(_, item, actual, command):
    same(sql(item), command)
    check_observation(item, actual, command)
    return ("edge", item["negative"]) if "negative" in item else ("finite", tuple(item["coordinate"]))


def rejected_finite_ids(spec, _tag):
    return {("finite", key) for key in unguarded(spec)} | {("edge", name) for name in NATIVE_EDGES}


def sample_forms(forms, reply, _engine, item):
    for key in ("role", "family", "guard", "active", "warm", "quoted", "reversed"):
        forms[f"{key}/{item[key]}"] += 1
    for key in (reply["reconfigureSequence"]["changes"] or {}):
        if reply["reconfigureSequence"]["changes"][key] is not None:
            forms["slot/" + key] += 1
    forms["shape/" + reply["reconfigureSequence"]["source"][2]] += 1


def required_forms(*_):
    return {*(f"role/{role}" for role in ROLES), *(f"family/{family}" for family in ASSIGNMENTS),
            *(f"{axis}/{value}" for axis in ("guard", "active", "warm", "quoted", "reversed") for value in (False, True)),
            *(f"slot/{slot}" for slot in ("storage", "increment", "minimum", "maximum", "start", "restart", "cache", "cycle", "owner")),
            "shape/existing", "shape/missing-sequence", "shape/missing-namespace"}


def fixture_receipt(item):
    healthy = deepcopy({**expected(item), "error": None})
    updated = active_role(item)
    for phase in ("before", "during", "settled"):
        state = healthy[phase]
        state["consumerOid"] = 19
        state["temporaryNamespace"] = ["pg_temp_3", 29]
        for index, (role, detail) in enumerate(state["sequences"].items(), 30):
            detail["value"]["oid"] = index
            detail["value"]["ownership"] = []
            edge = detail["ownership"]
            if edge is not None:
                if edge[0] == "pg_temp":
                    edge[0] = state["temporaryNamespace"][0]
                detail["value"]["ownership"].append([str(3 * index), str(edge[-2]), edge[-1]])
            detail["file"] = index + (200 if healthy["rewritten"] and role == updated and phase == "during" else 100)
    return healthy


def rejected_observation(item, change):
    healthy = fixture_receipt(item)
    check_observation(item, healthy, sql(item))
    broken = deepcopy(healthy)
    change(broken)
    try:
        check_observation(item, broken, sql(item))
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return error.detail
    raise Failure("postgres-reconfigure-sequence-ignored-change")


def controls():
    restart = trial("pg-17.2", "local", "start-and-restart", False, True, warm=True)
    owner = trial("pg-17.2", "temporary", "owner-link", False, True)

    def alter_start(record):
        record["during"]["sequences"]["local"]["value"]["tuple"][0] += 1

    def alter_namespace(record):
        record["during"]["sequences"]["temporary"]["ownership"][0] = "pg_temp_99"

    return {"wrong-initial-value": rejected_observation(restart, alter_start),
            "wrong-session-namespace": rejected_observation(owner, alter_namespace)}


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    same(STATUS, witness["observed"]["status"])


def faults(_, tag):
    root = "sql-semantics-postgres/src/Ir/Schema/Sequence/Alter/ReconfigureSequence.php"
    slots = "sql-semantics-postgres/src/Ir/Schema/Sequence/Alter/SequenceChanges.php"
    present = trial(tag, "local", "start-and-restart", False, True, warm=True)
    start = trial(tag, "local", "numeric-configuration", False, True, varied={"start": "5"})
    cycle = trial(tag, "local", "numeric-configuration", False, True)
    absent = trial(tag, "missing-scoped", "restart-at-start", True, False)
    return [
        RenderFault("change-sequence-decoy", root, "$this->source->quoted()",
                    "str_replace('\"" + LOCAL + "\".', '\"" + OTHER + "\".', $this->source->quoted())",
                    present, "meaning-mismatch", direct(present)),
        RenderFault("change-sequence-start-restarts", slots, "' START WITH '", "' RESTART WITH '",
                    start, "meaning-mismatch", direct(start)),
        RenderFault("change-sequence-drop-cycle", slots, "true => ' CYCLE'", "true => ''",
                    cycle, "meaning-mismatch", direct(cycle)),
        RenderFault("change-sequence-drop-guard", root, "($this->ifExists ? 'IF EXISTS ' : '')", "''",
                    absent, "meaning-mismatch", direct(absent)),
    ]


if __name__ == "__main__":
    require(FIXTURE_CONTAINER in os.environ, "postgres-reconfigure-sequence-disposable-service-required")
    main(sys.modules[__name__])
