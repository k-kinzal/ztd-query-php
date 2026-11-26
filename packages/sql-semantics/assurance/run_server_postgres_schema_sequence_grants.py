"""Verify namespace grant roles and ACL transitions in native PostgreSQL."""

from copy import deepcopy
from itertools import product
import json
import os
from random import Random
import sys

from native_postgres_fixture import ENV as FIXTURE_CONTAINER
from postgres_create_btree_cases import refusal
from postgres_create_schema_cases import CATALOG, EFFECTIVE, OWNER, QUOTED_OWNER, SESSION
from postgres_reindex_cases import quoted
from postgres_schema_sequence_grants_cases import (
    ACTOR, DECOY, LOGIN, NS, OTHER, OWNERS, PUBLIC, READER, ROLES, ROLE_RECORDS, STATUS, TARGETS,
    acl_order, analyzed, direct, expected, generators, grants, named, owner, sql, trial,
)
from postgres_schema_sequences_cases import sequence_shape
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main, negative_count
from run_server_postgres_create_btree import check_preparation as check_libpq
from run_server_postgres_create_schema import observation, prepare
from run_server_postgres_create_schema import specifications as namespace_profiles
from sqlite_domain import RenderFault

LABEL = "postgres-schema-sequence-grants"
CONTRACT_NAME = "postgres-schema-sequence-grants-v2.json"
CONTRACT = "f3302a9cceca6d81a18c57e687cbeb04889b9b78ca272f5abc817a049d9da3d8"
OBSERVER = "postgres_schema_sequence_grants_observer.py"
AXES = ("owners", "targets", "options", "transactional")
RIGHTS = (["USAGE"], ["UPDATE", "SELECT"], [], ["RULE"], ["SELECT", "RULE", "UPDATE"])


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-schema-sequence-grants-contract")
    spec = json.loads(path.read_text())
    same([], spec["exploration"]["allowedExclusions"])
    return spec, namespace_profiles()[1]


def check_preparation(engine, environment, preparation):
    check_libpq(engine, environment, preparation)
    same(CATALOG, environment["currentDatabase"])
    same(ROLE_RECORDS, environment["roleCoordinates"])


def grid(spec):
    return product(*(spec["finite"][axis] for axis in AXES))


def finite_ids(_, spec, _tag):
    mathematical = set(product(OWNERS, TARGETS, (False, True), (False, True)))
    require(set(grid(spec)) == mathematical and len(mathematical) == 32, "postgres-schema-sequence-grants-grid")
    return mathematical


def analysis_finite_id(_, response, item, command):
    key = tuple(item["coordinate"])
    same(sql(trial(item["release"], *key)), command)
    check_value(item, response)
    return key


def direct_finite_id(_, response, item, request):
    key = tuple(item["coordinate"])
    same(direct(trial(item["release"], *key)), request)
    check_value(item, response)
    return key


def policy(rng, number, named_owner):
    option = bool(rng.getrandbits(1))
    candidates = [named(READER), named("PUBLIC"), LOGIN, ACTOR, named(OTHER)]
    if not option:
        candidates.append(PUBLIC)
    width = min(len(candidates), 1 + number % 4)
    grantor = rng.choice((None, ACTOR, named(named_owner) if named_owner else ACTOR))
    return {"target": TARGETS[number % len(TARGETS)], "rights": RIGHTS[number % len(RIGHTS)],
            "grantees": rng.sample(candidates, width), "grantor": grantor, "option": option, "repeat": bool(rng.getrandbits(1))}


def generated(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        ownership = OWNERS[index % len(OWNERS)]
        quoted_name = bool(rng.getrandbits(1))
        declared = (QUOTED_OWNER if quoted_name else OWNER) if ownership == "named" else None
        width = 1 + index % 4
        policies = [policy(rng, index + ordinal, declared) for ordinal in range(width)]
        yield trial(tag, ownership, policies[0]["target"], policies[0]["option"], bool(rng.getrandbits(1)),
                    width=width, base=rng.randrange(1, 120), quoted_name=quoted_name, derived=declared is not None and bool(rng.getrandbits(1)),
                    qualified_target=bool(rng.getrandbits(1)), written_first=bool(rng.getrandbits(1)), settings=policies)


def edges(tag):
    public_option = {"target": "schema-local", "rights": ["USAGE"], "grantees": [PUBLIC],
                     "grantor": None, "option": True, "repeat": False}
    yield "empty-public-no-op", trial(tag, "named", "schema-local", True, False, empty=True, settings=[public_option])
    yield "populated-legacy-rule-no-op", trial(tag, "effective", "schema-local", True, True,
                                              settings=[{**public_option, "rights": ["RULE"]}])
    yield "foreign-only", trial(tag, "named", "foreign-only", False, False, empty=True, quoted_name=True,
                                settings=[{**public_option, "target": "foreign-only", "option": False, "rights": ["UPDATE"]}])
    yield "derived-authorization", trial(tag, "named", "named-mixed", True, True, derived=True, quoted_name=True)
    self_option = {"target": "named-local", "rights": ["SELECT"], "grantees": [ACTOR],
                   "grantor": ACTOR, "option": True, "repeat": False}
    yield "implicit-and-explicit-owner-option", trial(tag, "named", "named-local", True, False, settings=[self_option])


def refusals(tag):
    item = trial(tag, "named", "named-local", True, True)
    boundaries = (
        ("missing", f"CREATE SCHEMA {NS} GRANT USAGE ON SEQUENCE {NS}.missing TO {READER}", "42P01",
         refusal("An absent sequence selector requires IF EXISTS.")),
        ("table", f"CREATE SCHEMA {NS} GRANT USAGE ON SEQUENCE tickets TO {READER} CREATE TABLE tickets (n integer)", "42809",
         refusal('"tickets" is not a sequence', "SemanticException")),
        ("index", f"CREATE SCHEMA {NS} GRANT USAGE ON SEQUENCE tickets TO {READER}"
         " CREATE INDEX tickets ON ledger (n) CREATE TABLE ledger (n integer)", "42809",
         refusal("The selected relation is not a sequence.")),
        ("populated", f"CREATE SCHEMA {NS} GRANT USAGE ON ALL SEQUENCES IN SCHEMA {NS} TO PUBLIC WITH GRANT OPTION"
         " CREATE SEQUENCE tickets", "0LP01", refusal("A populated schema grant requires individual grantees for grant options.")),
        ("grantor", f"CREATE SCHEMA {NS} AUTHORIZATION {quoted(OWNER)} GRANT USAGE ON SEQUENCE tickets TO {READER} GRANTED BY {OTHER}"
         " CREATE SEQUENCE tickets", "0A000", refusal("A named schema grantor must match its named schema owner.")),
    )
    for label, command, native, rejection in boundaries:
        selected = {**item, "command": command, "nativeRefusal": native, "negative": label}
        yield analyzed(selected), selected, rejection
    request = direct(item)
    request["createSchema"]["grants"][0]["release"] = "pg-16.6" if tag == "pg-17.2" else "pg-17.2"
    yield request, None, refusal("A schema grant must belong to its release.")
    request = direct(item)
    request["createSchema"]["sequences"] = []
    yield request, None, refusal("A schema grant requires a preceding sequence at each named local target.")
    request = direct(item)
    request["createSchema"]["grants"][0] = "unchecked grant"
    yield request, None, refusal("Schema grants are an ordered list of checked sequence grants.")


def requests(engine, tag, spec):
    for index, key in enumerate(grid(spec)):
        value = trial(tag, *key, coordinate=list(key))
        yield f"finite/{index}", analyzed(value), value, None
        yield f"direct/{index}", direct(value), value, None
    for label, value in edges(tag):
        yield f"edge/{label}", analyzed(value), value, None
    exploration = spec["exploration"]
    for seed in exploration["seeds"]:
        for index, value in enumerate(generated(tag, seed, exploration["casesPerSeed"])):
            yield f"seed/{seed}/{index}", analyzed(value), value, None
            yield f"direct-seed/{seed}/{index}", direct(value), value, None
    invalid = list(refusals(tag))
    same(negative_count(spec, engine, tag), len(invalid))
    for index, (request, value, rejected) in enumerate(invalid):
        yield f"negative/{index}", request, value, rejected


def check_value(item, response):
    same(["constructed", item["release"] + "/utf8/standard-conforming-strings/native"],
         [response.get("status"), response.get("profile")])
    schema = direct(item)["createSchema"]
    same({**{key: schema[key] for key in ("owner", "ifNotExists")}, "name": item["namespace"]}, response["createSchema"])
    for key in ("schemaTables", "schemaViews", "schemaIndexes"):
        same([], response[key])
    same([sequence_shape(value, True) for value in generators(item)], response["schemaSequences"])
    same(grants(item), response["schemaGrants"])


def measure(phase, item, identifiers, witness):
    if phase is None:
        return None
    roles = {identity: name for name, identity in identifiers.items()}
    names = [entry["oid"] for entry in phase["sequences"]]
    require(not phase["others"] and len(names) >= len(item["names"]) and len(names) == len(set(names))
            and all(type(node) is int and node > 0 for node in names), "meaning-mismatch", witness)
    namespace = phase["namespaceOid"]
    require((namespace is None and phase["owner"] is None) or (type(namespace) is int and namespace > 0
            and namespace not in names and phase["owner"] is not None), "meaning-mismatch", witness)
    values = []
    for entry in phase["sequences"]:
        raw_acl = entry["aclOids"]
        acl = None if raw_acl is None else sorted(
            ([roles[a], None if b == 0 else roles[b], key, option] for a, b, key, option in raw_acl), key=acl_order)
        shared = sorted([roles[value], kind] for value, kind in entry["sharedOids"])
        require(acl == entry["acl"] and shared == entry["shared"], "meaning-mismatch", witness)
        values.append({key: entry[key] for key in ("coordinate", "value", "acl", "shared", "effective")})
    return {"owner": phase["owner"], "sequences": values}


def check_observation(item, actual, command):
    model = expected(item)
    witness = {"expected": model, "observed": actual, "sql": command}
    require(actual.get("status") == STATUS, "postgres-schema-sequence-grants-observer-scope", witness)
    identifiers = actual["principalOids"]
    require(set(identifiers) == set(ROLES) and len(set(identifiers.values())) == len(ROLES)
            and all(type(oid) is int and oid > 0 for oid in identifiers.values())
            and [actual["actor"], actual["session"]] == [EFFECTIVE, SESSION], "meaning-mismatch", witness)
    parts = ("before", "during", "settled")
    measured = {key: measure(actual[key], item, identifiers, witness) if key in parts else actual[key] for key in model}
    require(measured == model, "meaning-mismatch", witness)
    prior = {tuple(entry["coordinate"]): entry["oid"] for entry in actual["before"]["sequences"]}
    for key in ("during", "settled"):
        if actual[key] is not None:
            external = {tuple(entry["coordinate"]): entry["oid"] for entry in actual[key]["sequences"] if entry["coordinate"][0] == DECOY}
            require(external == prior, "meaning-mismatch", witness)
    if not item["active"] and actual["during"] is not None:
        require(actual["settled"] == actual["during"], "meaning-mismatch", witness)
    error = actual["error"]
    require(error is None if model["sqlState"] == "00000" else error is not None and error[:2] == ["ERROR", model["sqlState"]],
            "meaning-mismatch", witness)


def observer_request(item, command):
    return {"trial": item, "sql": command}


def observe_rejection(_, item, actual, command):
    same(item["command"], command)
    check_observation(item, actual, command)
    return item["negative"]


def rejected_finite_ids(_, _tag):
    return {"missing", "table", "index", "populated", "grantor"}


def sample_forms(forms, response, _engine, item):
    for key in ("owner", "active", "quoted", "qualified", "first"):
        forms[f"{key}/{item[key]}"] += 1
    current = response["schemaGrants"]
    forms[f"width/{len(current)}"] += 1
    for grant in current:
        target = grant["target"]
        key = "named" if "named" in target else "schemas"
        addresses = target[key]
        distinct = {tuple(entry) if isinstance(entry, list) else entry for entry in addresses}
        forms[f"target/{key}"] += 1
        forms[f"repeated/{len(distinct) != len(addresses)}"] += 1
        forms[f"option/{grant['grantOption']}"] += 1
        forms[f"rights/{RIGHTS.index(grant['rights'])}"] += 1
        roles = grant["grantees"]
        for label, role in (("PUBLIC", PUBLIC), ("quoted-PUBLIC", named("PUBLIC")), ("effective", ACTOR), ("session", LOGIN), ("named", named(READER))):
            if role in roles:
                forms[f"role/{label}"] += 1
        grantor = grant["grantor"]
        forms[f"grantor/{'omitted' if grantor is None else 'effective' if grantor == ACTOR else 'named'}"] += 1


def required_forms(*_):
    return {*(f"owner/{mode}" for mode in OWNERS), *(f"width/{size}" for size in range(1, 5)),
            *(f"rights/{index}" for index in range(len(RIGHTS))), "target/named", "target/schemas",
            *(f"{key}/{flag}" for key in ("active", "quoted", "qualified", "first", "option", "repeated") for flag in (True, False)),
            *(f"role/{key}" for key in ("PUBLIC", "quoted-PUBLIC", "effective", "session", "named")),
            "grantor/omitted", "grantor/effective", "grantor/named"}


def controls():
    item = trial("pg-17.2", "named", "named-mixed", True, True)
    good = deepcopy({**expected(item), "error": None, "actor": EFFECTIVE, "session": SESSION})
    identities = {name: 101 + number for number, name in enumerate(ROLES)}
    good["principalOids"] = identities
    for key in ("before", "during", "settled"):
        state = good[key]
        state.update(others=[], namespaceOid=800 if key == "during" else None)
        for entry in state["sequences"]:
            entry["oid"] = 200 + item["names"].index(entry["coordinate"][1]) + (20 if entry["coordinate"][0] == DECOY else 0)
            entry["sharedOids"] = sorted([identities[name], kind] for name, kind in entry["shared"])
            acl = entry["acl"]
            entry["aclOids"] = None if acl is None else sorted(
                [identities[source], 0 if target is None else identities[target], kind, option] for source, target, kind, option in acl)
    check_observation(item, good, sql(item))
    failures = {}
    for key in ("aclOids", "effective"):
        bad = deepcopy(good)
        entry = next(value for value in bad["during"]["sequences"] if value["acl"] is not None)
        if key == "aclOids":
            entry[key][0][1] = identities[OTHER]
        else:
            entry[key][0][1][0] = not entry[key][0][1][0]
        try:
            check_observation(item, bad, sql(item))
        except Failure as failure:
            same("meaning-mismatch", failure.signal)
            failures[key] = failure.detail
            continue
        raise Failure("schema-grant-comparison-ignored-observation", key)
    return failures


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    same(STATUS, witness["observed"]["status"])


def faults(_, tag):
    schema = "sql-semantics-postgres/src/Ir/Schema/Namespace/CreateSchema.php"
    item = trial(tag, "named", "named-local", True, True)
    emitter = "$grant->toString()"
    return [
        RenderFault("schema-grants-omit-phase", schema, "foreach ($this->grants as $grant)",
                    "foreach ([] as $grant)", item, "meaning-mismatch", direct(item)),
        RenderFault("schema-grants-redirect-local-selector", schema, emitter,
                    """str_replace('"ir_grant_namespace".', '"ir_grant_decoy".', $grant->toString())""",
                    item, "meaning-mismatch", direct(item)),
        RenderFault("schema-grants-redirect-session-recipient", schema, emitter,
                    "str_replace('SESSION_USER', 'CURRENT_USER', $grant->toString())", item, "meaning-mismatch", direct(item)),
        RenderFault("schema-grants-omit-grant-option", schema, emitter,
                    "str_replace(' WITH GRANT OPTION', '', $grant->toString())", item, "meaning-mismatch", direct(item)),
    ]


if __name__ == "__main__":
    require(FIXTURE_CONTAINER in os.environ, "postgres-schema-sequence-grants-disposable-service-required")
    main(sys.modules[__name__])
