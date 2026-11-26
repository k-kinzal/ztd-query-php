"""Compare sequence default changes and subsequent object creation with PostgreSQL."""

from copy import deepcopy
from itertools import product
import json
import os
from random import Random
import sys

from native_postgres_fixture import ENV as FIXTURE_CONTAINER
from postgres_create_btree_cases import refusal
from postgres_sequence_default_access_cases import (
    ACTOR, BITS, CREATOR, EFFECTIVE, LOGIN, OPERATIONS, OTHER_CREATOR, PUBLIC, QUOTED, READER, RIGHTS,
    ROLE_IDENTITIES, SESSION, SCOPE_PLANS, STATUS, analyzed, direct, expected, named, outline, sql, trial,
)
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_drop_table import specifications as drop_profiles
from run_server_join import main
from run_server_postgres_create_btree import observation, prepare
from run_server_postgres_drop_sequence import check_preparation
from sqlite_domain import RenderFault

LABEL = "postgres-sequence-default-access"
CONTRACT_NAME = "postgres-sequence-default-access-v2.json"
CONTRACT = "ae7303a8273cc207a42bede9f1d71fd9020ab5fc26c54b4b990e91733b01dc7f"
OBSERVER = "postgres_sequence_default_access_observer.py"
NATIVE_EDGES = ("missing-creator", "missing-recipient", "missing-schema", "public-creator", "temp-alias", "repeated-scope",
                "wrong-right", "columns", "public-grant-option", "public-legacy-option")
AXES = ("operations", "scopes", "rights", "transactional")


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-sequence-default-access-contract")
    spec = json.loads(path.read_bytes())
    same([], spec["exploration"]["allowedExclusions"])
    spec["additionalPostgreSqlNegatives"] = list(range(16))
    return spec, {"postgres": drop_profiles()[1]["postgres"]}


def grid(spec):
    return product(*(spec["finite"][axis] for axis in AXES))


def finite_ids(_, spec, _tag):
    registered = set(product(OPERATIONS, SCOPE_PLANS, RIGHTS, (False, True)))
    require(set(grid(spec)) == registered and len(registered) == 128, "postgres-sequence-default-access-grid")
    return registered


def analysis_finite_id(_, reply, item, command):
    key = tuple(item["coordinate"])
    same(sql(trial(item["release"], *key)), command)
    check_value(item, reply)
    return key


def direct_finite_id(_, reply, item, request):
    key = tuple(item["coordinate"])
    same(direct(trial(item["release"], *key)), request)
    check_value(item, reply)
    return key


def generated(tag, seed, count):
    rng = Random(seed)
    roles = (EFFECTIVE, SESSION, named(CREATOR), named(OTHER_CREATOR))
    recipients = (EFFECTIVE, SESSION, named(READER), named(QUOTED), PUBLIC)
    candidates = (*BITS, "RULE")
    for index in range(count):
        operation = OPERATIONS[(index + seed) % len(OPERATIONS)]
        creators = rng.choices(roles, k=index % 5)
        locations = rng.choices(("west", "east"), k=(index + 1) % 5)
        rights = rng.choices(candidates, k=(index + 1) % 6)
        grantees = rng.choices(recipients[:4] if operation == "grant-option" else recipients, k=index % 4 + 1)
        actor = ACTOR if index % 3 else OTHER_CREATOR
        if index == 0:
            operation, creators, locations, rights, grantees, actor = "grant", [], [], ["USAGE"], [PUBLIC], ACTOR
        if index == 1:
            operation, creators, locations, rights, grantees = "revoke-option", [named(CREATOR)], ["east"], ["UPDATE"], [named(QUOTED)]
        if index == 2:
            creators, actor = [EFFECTIVE, SESSION, named(CREATOR)], OTHER_CREATOR
        if index == 3:
            operation, creators, locations, rights, grantees = "grant-option", [named(CREATOR)], ["west"], ["RULE"], [named(READER)]
        yield trial(tag, operation, (creators, locations), rights, index % 3 != 1,
                    actor=actor, grantees=grantees, quoted_name=index % 4 != 0,
                    initialized=index % 2 == 1, reverse=index % 3 == 1,
                    user_word=index % 3 == 2, current_role=index % 2 == 1, cascade=index % 4 == 1)


def edges(tag):
    yield "scope-revoke-preserves-global", trial(tag, "revoke", ((named(CREATOR),), ("west",)), "usage", True)
    yield "remove-global-entry", trial(tag, "revoke", ((named(CREATOR),), ()), "all", False)
    yield "remove-local-option", trial(tag, "revoke-option", ((named(CREATOR),), ("east",)), ["UPDATE"], True,
                                     grantees=[named(QUOTED)], quoted_name=True, cascade=True)
    yield "effective-creator", trial(tag, "grant", "implicit-global", ["SELECT"], True, grantees=[named(READER)])
    yield "members-own-defaults", trial(tag, "grant", ((named(CREATOR),), ("west",)), "usage", False,
                                      actor=OTHER_CREATOR, grantees=[named(QUOTED)], initialized=True)
    yield "later-unauthorized-creator", trial(tag, "grant", ((EFFECTIVE, SESSION), ("west",)), "usage", True,
                                             actor=OTHER_CREATOR, grantees=[named(READER)])
    yield "global-owner-self-revoke", trial(tag, "revoke", ((named(CREATOR),), ()), "all", True,
                                           grantees=[named(CREATOR)], initialized=False)


def refusals(tag):
    item = trial(tag, "grant", "implicit-global", "usage", True)
    prefix = "ALTER DEFAULT PRIVILEGES "
    grant = " GRANT USAGE ON SEQUENCES TO " + READER
    semantic = lambda message: refusal(message, "SemanticException")
    commands = [
        (prefix + "FOR ROLE absent_creator" + grant, "42704", refusal("A named schema owner requires a declared role.")),
        (prefix + "GRANT USAGE ON SEQUENCES TO absent_recipient", "42704", refusal("A named schema owner requires a declared role.")),
        (prefix + "IN SCHEMA absent_default_namespace" + grant, "3F000", refusal("Default sequence privileges require a declared schema.")),
        (prefix + "FOR ROLE PUBLIC" + grant, "42704", semantic('role "public" does not exist')),
        (prefix + "IN SCHEMA pg_temp" + grant, "3F000", refusal("Default sequence privileges require named persistent schemas.")),
        (prefix + "FOR ROLE CURRENT_USER FOR USER SESSION_USER" + grant, "42601", semantic("conflicting or redundant options")),
        (prefix + f"GRANT DELETE ON SEQUENCES TO {READER}", "0LP01", semantic("invalid privilege type DELETE for sequence")),
        (prefix + f"GRANT SELECT(a) ON SEQUENCES TO {READER}", "0LP01", semantic("default privileges cannot be set for columns")),
        (prefix + "GRANT USAGE ON SEQUENCES TO PUBLIC WITH GRANT OPTION", "0LP01", semantic("grant options can only be granted to roles")),
        (prefix + "GRANT RULE ON SEQUENCES TO PUBLIC WITH GRANT OPTION", "0LP01", semantic("grant options can only be granted to roles")),
    ]
    for name, (command, code, failure) in zip(NATIVE_EDGES, commands):
        negative = {**item, "command": command, "nativeRefusal": code, "negative": name}
        request = analyzed(negative)
        if name == "temp-alias":
            request["declarations"].append("CREATE TEMP SEQUENCE default_acl_temp_probe")
        yield request, negative, failure
    value = direct(item)
    attempts = [
        ({"creators": ["invalid"]}, "Default sequence privileges require an ordered list of creator roles."),
        ({"schemas": [42]}, "Default sequence privileges require an ordered list of schema identities."),
        ({"schemas": ["pg_temp_29"]}, "Default sequence privileges require named persistent schemas."),
        ({"rights": ["bogus"]}, "Default sequence privileges require an ordered list of rights."),
        ({"grantees": []}, "Default sequence privileges require an ordered nonempty list of recipients."),
        ({"grantees": ["bogus"]}, "Default sequence privileges require an ordered nonempty list of recipients."),
        ({"creators": [named("pg_role_no_longer_found")]}, "The stock sequence owner must exist in this release."),
        ({"rights": ["RULE"], "grantees": [PUBLIC], "grantOption": True}, "Default sequence grant options require individual recipients."),
    ]
    for patch, message in attempts:
        yield {**value, "sequenceDefaultAccess": {**value["sequenceDefaultAccess"], **patch}}, None, refusal(message)


def requests(_, tag, spec):
    for ordinal, key in enumerate(grid(spec)):
        item = trial(tag, *key, coordinate=list(key))
        yield f"finite/{ordinal}", analyzed(item), item, None
        yield f"direct/{ordinal}", direct(item), item, None
    for name, item in edges(tag):
        yield f"edge/{name}", analyzed(item), item, None
    settings = spec["exploration"]
    same(settings["casesPerSeed"], settings["directCasesPerSeed"])
    for seed in settings["seeds"]:
        for index, item in enumerate(generated(tag, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", analyzed(item), item, None
            yield f"direct-seed/{seed}/{index}", direct(item), item, None
    for index, (request, item, rejection) in enumerate(refusals(tag)):
        yield f"negative/{index}", request, item, rejection


def check_value(item, reply):
    same(["constructed", item["release"] + "/utf8/standard-conforming-strings/native", outline(item)],
         [reply.get("status"), reply.get("profile"), reply.get("sequenceDefaultAccess")])


def observer_request(item, command):
    return {"trial": item, "sql": command}


def without_physical_ids(values):
    return None if values is None else [{key: value for key, value in row.items() if key not in ("oid", "creatorOid", "schemaOid")} for row in values]


def coordinates(item, actual, witness):
    roles, schemas = actual["principalOids"], actual["namespaceOids"]
    for actual_ids, expected_keys in ((roles, ROLE_IDENTITIES), (schemas, item["namespaces"].values())):
        ids = list(actual_ids.values())
        require(set(actual_ids) == set(expected_keys) and len(ids) == len(set(ids))
                and all(type(oid) is int and oid > 0 for oid in ids), "meaning-mismatch", witness)
    old = {(entry["creator"], entry["schema"]): entry["oid"] for entry in actual["before"]}
    for phase in ("before", "during", "settled"):
        rows = actual[phase]
        if rows is None:
            continue
        ids = [row["oid"] for row in rows]
        require(len(ids) == len(set(ids)) and all(type(oid) is int and oid > 0 for oid in ids), "meaning-mismatch", witness)
        for row in rows:
            creator, schema = row["creator"], row["schema"]
            require(row["creatorOid"] == roles[creator] and row["schemaOid"] == (0 if schema is None else schemas[schema]),
                    "meaning-mismatch", witness)
            if (creator, schema) in old:
                require(row["oid"] == old[creator, schema], "meaning-mismatch", witness)
    for key in ("futureDuring", "futureSettled"):
        rows = actual[key]
        if rows is not None:
            ids = [row["oid"] for row in rows]
            require(len(ids) == len(set(ids)) and all(type(oid) is int and oid > 0 for oid in ids), "meaning-mismatch", witness)


def check_observation(item, actual, command):
    model = expected(item)
    witness = {"expected": model, "observed": actual, "sql": command}
    require(actual.get("status") == STATUS, "sequence-default-access-observer-scope", witness)
    objects = ("before", "during", "settled", "futureDuring", "futureSettled")
    measured = {key: without_physical_ids(actual[key]) if key in objects else actual[key] for key in model}
    require(measured == model, "meaning-mismatch", witness)
    coordinates(item, actual, witness)
    legacy = actual["legacyBefore"]
    require(type(legacy["oid"]) is int and legacy["oid"] > 0 and legacy["creator"] == CREATOR and legacy["kind"] == "S"
            and legacy["schema"] == item["namespaces"]["west"] and legacy["acl"] is None and legacy["ownerRights"] == [True, True, True]
            and legacy == actual["legacySettled"]
            and actual["legacyDuring"] == (legacy if model["sqlState"] == "00000" else None), "meaning-mismatch", witness)
    error = actual["error"]
    require(error is None if model["sqlState"] == "00000"
            else error is not None and error[:2] == ["ERROR", model["sqlState"]], "meaning-mismatch", witness)
    if model["sqlState"] == "42501":
        require(error[2] == "permission denied to change default privileges", "meaning-mismatch", witness)


def observe_rejection(_, item, actual, command):
    same(sql(item), command)
    check_observation(item, actual, command)
    return ("edge", item["negative"])


def rejected_finite_ids(_spec, _tag):
    return {("edge", name) for name in NATIVE_EDGES}


def sample_forms(forms, reply, _engine, item):
    policy = reply["sequenceDefaultAccess"]
    for key in ("active", "quoted", "initialized", "reverseClauses", "operation"):
        forms[f"{key}/{item[key]}"] += 1
    forms[f"scope/{'local' if policy['schemas'] else 'global'}"] += 1
    for role in policy["creators"] or [{"session": "implicit"}]:
        forms["creator/" + role.get("session", "named")] += 1
    for role in policy["grantees"]:
        forms["recipient/" + role.get("session", "named" if "name" in role else "public")] += 1
    for right in policy["rights"] or ["ALL"]:
        forms["right/" + right] += 1


def sample_native_forms(forms, actual, _engine, _item):
    forms["state/" + actual["sqlState"]] += 1


def required_forms(*_):
    return {*(f"{key}/{val}" for key in ("active", "quoted", "initialized", "reverseClauses") for val in (True, False)),
            *(f"operation/{op}" for op in OPERATIONS), *(f"right/{right}" for right in (*BITS, "RULE", "ALL")),
            *(f"creator/{role}" for role in ("CURRENT_USER", "SESSION_USER", "implicit", "named")),
            *(f"recipient/{role}" for role in ("CURRENT_USER", "SESSION_USER", "named", "public")), "scope/local", "scope/global",
            "state/00000", "state/42501"}


def controls():
    item = trial("pg-17.2", "grant", "effective-local", "usage", True)
    healthy = deepcopy({**expected(item), "error": None})
    roles = healthy["principalOids"] = dict(zip(ROLE_IDENTITIES, range(200, 200 + len(ROLE_IDENTITIES))))
    names = healthy["namespaceOids"] = dict(zip(item["namespaces"].values(), (106, 107)))
    legacy = {"oid": 61, "creator": CREATOR, "kind": "S", "schema": item["namespaces"]["west"], "acl": None, "ownerRights": [True, True, True]}
    healthy.update(legacyBefore=legacy, legacyDuring=legacy, legacySettled=legacy)
    for key in ("before", "during", "settled"):
        for oid, row in enumerate(healthy[key], 61 + len(ROLE_IDENTITIES)):
            row.update(oid=oid, creatorOid=roles[row["creator"]], schemaOid=0 if row["schema"] is None else names[row["schema"]])
    for key in ("futureDuring", "futureSettled"):
        for oid, row in enumerate(healthy[key], 83):
            row["oid"] = oid
    check_observation(item, healthy, sql(item))
    broken = deepcopy(healthy)
    broken["futureDuring"] = deepcopy(healthy["futureSettled"])
    try:
        check_observation(item, broken, sql(item))
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"erased-future-policy": error.detail}
    raise Failure("sequence-default-comparison-ignored-future-policy")


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    same(STATUS, witness["observed"]["status"])


def faults(_, tag):
    base = "sql-semantics-postgres/src/Ir/Schema/Sequence/Defaults/"
    policy, grant_path, revoke_path = (base + name + ".php" for name in ("SequenceDefaultPolicy", "GrantSequenceDefaults", "RevokeSequenceDefaults"))
    scoped = trial(tag, "grant", "effective-local", "usage", True)
    option = trial(tag, "grant-option", "effective-local", "usage", True)
    revoke = trial(tag, "revoke-option", ((named(CREATOR),), ("east",)), ["UPDATE"], True, grantees=[named(QUOTED)])
    authorization = trial(tag, "grant", "effective-local", "usage", False)
    return [
        RenderFault("sequence-default-use-authenticated-creator", policy, "$owners[] = $role->reference();", "$owners[] = 'SESSION_USER';",
                    scoped, "meaning-mismatch", direct(scoped)),
        RenderFault("sequence-default-erase-schema-scope", policy, "($schemas === [] ? '' : ' IN SCHEMA ' . implode(', ', $schemas))", "''",
                    scoped, "meaning-mismatch", direct(scoped)),
        RenderFault("sequence-default-drop-grant-option", grant_path, "($this->grantOption ? ' WITH GRANT OPTION' : '')", "''",
                    option, "meaning-mismatch", direct(option)),
        RenderFault("sequence-default-revoke-entire-privilege", revoke_path, "($this->grantOptionOnly ? 'GRANT OPTION FOR ' : '')", "''",
                    revoke, "meaning-mismatch", direct(revoke)),
        RenderFault("sequence-default-publish-to-universal-role", policy, "$roles[] = $role->toString();", "$roles[] = 'PUBLIC';",
                    authorization, "meaning-mismatch", direct(authorization)),
    ]


if __name__ == "__main__":
    require(FIXTURE_CONTAINER in os.environ, "postgres-sequence-default-access-disposable-service-required")
    main(sys.modules[__name__])
