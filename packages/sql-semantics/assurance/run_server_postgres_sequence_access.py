"""Compare PostgreSQL sequence privilege changes with an independent ACL graph."""

from copy import deepcopy
from itertools import product
import json
import os
from random import Random
import sys

from native_postgres_fixture import ENV as FIXTURE_CONTAINER
from postgres_create_btree_cases import refusal
from postgres_sequence_access_cases import (
    ACTOR, BITS, EFFECTIVE, ESCAPED, HEAD, LEAF, LOCAL, LOGIN, OPERATIONS, OTHER, PUBLIC, RIGHTS, ROLE_IDENTITIES,
    SESSION, STATUS, TARGETS, analyzed, direct, expected, named, outline, sql, trial,
)
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_drop_table import specifications as drop_profiles
from run_server_join import main
from run_server_postgres_create_btree import observation, prepare
from run_server_postgres_drop_sequence import check_preparation
from sqlite_domain import RenderFault

LABEL = "postgres-sequence-access"
CONTRACT_NAME = "postgres-sequence-access-v1.json"
CONTRACT = "9cb25bd56833b134ea2213137b45680a7ff9abf526687c351ad8d3ec26b70e8c"
OBSERVER = "postgres_sequence_access_observer.py"
NATIVE_EDGES = ("missing-sequence", "missing-schema", "missing-recipient", "missing-grantor", "foreign-catalog",
                "wrong-kind", "columns", "unknown-right", "public-grantor", "public-grant-option")
AXES = ("operations", "targets", "rights", "transactional")


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-sequence-access-contract")
    spec = json.loads(path.read_bytes())
    same([], spec["exploration"]["allowedExclusions"])
    spec["additionalPostgreSqlNegatives"] = list(range(16))
    return spec, {"postgres": drop_profiles()[1]["postgres"]}


def grid(spec):
    return product(*(spec["finite"][axis] for axis in AXES))


def finite_ids(_, spec, _tag):
    registered = set(product(OPERATIONS, TARGETS, RIGHTS, (False, True)))
    require(set(grid(spec)) == registered and len(registered) == 160, "postgres-sequence-access-grid")
    return registered


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
    target_plans = list(TARGETS.values()) + [("schemas", ("temporary", "local")), ("named", ("local", "bonus"))]
    candidates = ("USAGE", "SELECT", "UPDATE", "RULE")
    roles = [named(HEAD), named(ESCAPED), named("PUBLIC"), PUBLIC, EFFECTIVE, SESSION]
    operations = (*OPERATIONS, "restrict-option", "cascade")
    for index in range(count):
        mode = operations[(index + seed) % len(operations)]
        target, fixed = target_plans[index % len(target_plans)]
        addresses = list(fixed)
        if index % 5 == 2:
            addresses.extend(rng.choices(addresses, k=2))
            rng.shuffle(addresses)
        rights = list(RIGHTS["all"]) if index % 7 == 0 else rng.choices(candidates, k=index % 5 + 1)
        if index % 5 == 0 and index % 7 != 0:
            rights = ["RULE"]
        recipients = rng.choices(roles, k=index % 4 + 1)
        if target == "named" and mode == "grant-option" and rights != ["RULE"]:
            recipients = [named("PUBLIC") if grantee == PUBLIC else grantee for grantee in recipients]
        actor = ACTOR if index % 3 else LOGIN
        grantor = (None, EFFECTIVE, SESSION, named(actor))[index % 4]
        if index == 0:
            mode, rights, recipients, grantor = "grant", ["SELECT", "UPDATE", "USAGE"], [PUBLIC, EFFECTIVE, SESSION], EFFECTIVE
        if index == 1:
            mode, target, addresses, rights, recipients = "cascade-option", "named", ["local"], ["SELECT"], [named(HEAD)]
        if index == 2:
            mode, target, addresses, rights, recipients, grantor, actor = "restrict", "named", ["local"], ["SELECT"], [named(HEAD)], None, LOGIN
        if index == 3:
            mode, rights, recipients, grantor, actor = "grant", ["RULE"], [named("PUBLIC")], SESSION, ACTOR
        yield trial(tag, mode, (target, addresses), rights, index % 3 != 1, catalog=index % 2 == 0,
                    quoted_name=index % 3 != 0, grantees=recipients, actor=actor, grantor=grantor,
                    alternate=index % 4 == 1, current_role=index % 3 == 0, base=rng.randrange(5, 90))


def edges(tag):
    yield "two-ways-to-delegate", trial(tag, "cascade-option", "local", ["SELECT"], True, alternate=True)
    yield "restrict-chain", trial(tag, "restrict-option", "local", ["SELECT"], True)
    yield "cascade-entire-privilege", trial(tag, "cascade", "local", ["SELECT"], False)
    yield "unmaterialized-zero-rights", trial(tag, "grant-option", "local", "rule", True, grantees=[PUBLIC])
    yield "empty-schema-public-option", trial(tag, "grant-option", "all-in-empty", "all", True, grantees=[PUBLIC])
    yield "populated-schema-public-option", trial(tag, "grant-option", "all-in-two", "all", True, grantees=[PUBLIC])
    yield "native-nondelegated-source", trial(tag, "grant", "local", "usage", True, actor=HEAD, chain=True,
                                             grantees=[named(ESCAPED)], grantor=named(HEAD), quoted_name=True)
    yield "effective-not-authenticated", trial(tag, "grant", "local", "usage", True,
                                              actor=ACTOR, grantees=[EFFECTIVE], grantor=EFFECTIVE, current_role=True)
    yield "public-and-named-role", trial(tag, "grant", "temporary", "usage", True, grantees=[PUBLIC, named("PUBLIC")])


def refusals(tag):
    item = trial(tag, "grant", "local", "usage", True)
    local = LOCAL + ".ticket"
    role = HEAD
    semantic = lambda message: refusal(message, "SemanticException")
    commands = [
        (f"GRANT USAGE ON SEQUENCE {LOCAL}.missing TO {role}", "42P01", refusal("An absent sequence selector requires IF EXISTS.")),
        (f"GRANT USAGE ON ALL SEQUENCES IN SCHEMA undeclared_acl_scope TO {role}", "3F000", refusal("Sequence privileges require a declared schema.")),
        (f"GRANT USAGE ON SEQUENCE {local} TO role_not_declared", "42704", refusal("A named schema owner requires a declared role.")),
        (f"GRANT USAGE ON SEQUENCE {local} TO {role} GRANTED BY role_not_declared", "42704", refusal("A named schema owner requires a declared role.")),
        (f"GRANT USAGE ON SEQUENCE outside.{local} TO {role}", "0A000", refusal("An explicit PostgreSQL catalog requires the matching current database.")),
        (f"GRANT USAGE ON SEQUENCE {LOCAL}.consumer TO {role}", "42809", semantic('"consumer" is not a sequence')),
        (f"GRANT SELECT(a) ON SEQUENCE {local} TO {role}", "0LP01", semantic("column privileges are only valid for relations")),
        (f"GRANT gibberish ON SEQUENCE {local} TO {role}", "42601", semantic('unrecognized privilege type "gibberish"')),
        (f"GRANT USAGE ON SEQUENCE {local} TO {role} GRANTED BY PUBLIC", "42704", semantic('role "public" does not exist')),
        (f"GRANT USAGE ON SEQUENCE {local} TO PUBLIC WITH GRANT OPTION", "0LP01", semantic("grant options can only be granted to roles")),
    ]
    for name, (command, code, problem) in zip(NATIVE_EDGES, commands):
        negative = {**item, "command": command, "nativeRefusal": code, "negative": name}
        yield analyzed(negative), negative, problem
    value = direct(item)
    requests = [
        ({"target": {"named": []}}, "Sequence privileges require named sequence targets."),
        ({"target": {"named": [[LOCAL, "absent", "missing-sequence"]]}}, "Sequence privileges require an existing named sequence."),
        ({"target": {"schemas": ["pg_temp_29"]}}, "Sequence privileges cannot use a numbered temporary schema."),
        ({"rights": ["bogus"]}, "Sequence privileges require an ordered list of rights."),
        ({"grantees": []}, "Sequence privileges require an ordered nonempty list of recipients."),
        ({"grantees": ["bogus"]}, "Sequence privileges require an ordered nonempty list of recipients."),
        ({"grantees": [named("none")]}, "A sequence owner must identify a single role."),
        ({"grantees": [PUBLIC], "grantOption": True}, "Grant options on named sequences require individual grantees."),
    ]
    for patch, message in requests:
        yield {**value, "sequenceAccess": {**value["sequenceAccess"], **patch}}, None, refusal(message)


def requests(_, tag, spec):
    for ordinal, key in enumerate(grid(spec)):
        item = trial(tag, *key, coordinate=list(key))
        yield f"finite/{ordinal}", analyzed(item), item, None
        yield f"direct/{ordinal}", direct(item), item, None
    for name, item in edges(tag):
        yield f"edge/{name}", analyzed(item), item, None
    explores = spec["exploration"]
    same(explores["casesPerSeed"], explores["directCasesPerSeed"])
    for seed in explores["seeds"]:
        for index, item in enumerate(generated(tag, seed, explores["casesPerSeed"])):
            yield f"seed/{seed}/{index}", analyzed(item), item, None
            yield f"direct-seed/{seed}/{index}", direct(item), item, None
    for index, (request, item, problem) in enumerate(refusals(tag)):
        yield f"negative/{index}", request, item, problem


def check_value(item, actual):
    same(["constructed", item["release"] + "/utf8/standard-conforming-strings/native", outline(item)],
         [actual.get("status"), actual.get("profile"), actual.get("sequenceAccess")])


def observer_request(item, command):
    return {"trial": item, "sql": command}


def project(record):
    return {**{key: record[key] for key in ("default", "rows", "columns")},
            "sequences": {role: {"value": {key: value for key, value in detail["value"].items() if key != "oid"},
                                 "acl": detail["acl"], "shared": detail["shared"]} for role, detail in record["sequences"].items()}}


def check_observation(item, actual, command):
    model = expected(item)
    witness = {"expected": model, "observed": actual, "sql": command}
    require(actual.get("status") == STATUS, "sequence-access-observer-scope", witness)
    measured = {key: project(actual[key]) if key in ("before", "during", "settled") and actual[key] is not None
                else actual[key] for key in model}
    require(measured == model, "meaning-mismatch", witness)
    roles = actual["principalOids"]
    require(set(roles) == set(ROLE_IDENTITIES) and len(set(roles.values())) == len(roles)
            and all(type(oid) is int and oid > 0 for oid in roles.values()), "meaning-mismatch", witness)
    before = actual["before"]
    local = before["sequences"]["local"]["value"]["oid"]
    ids = [before["consumerOid"], *(sequence["value"]["oid"] for sequence in before["sequences"].values())]
    require(len(ids) == len(set(ids)) and all(type(value) is int and value > 0 for value in ids), "meaning-mismatch", witness)
    for phase in ("before", "during", "settled"):
        record = actual[phase]
        if record is None:
            continue
        require(record["consumerOid"] == before["consumerOid"] and record["defaultOid"] == local, "meaning-mismatch", witness)
        for role, details in record["sequences"].items():
            old = before["sequences"][role]
            observed = details["acl"]
            expected_oids = None if observed is None else sorted([roles[source], roles.get(recipient, 0), right, opt]
                                                                 for source, recipient, right, opt in observed)
            shared = sorted([roles[name], kind] for name, kind in details["shared"])
            require(details["value"]["oid"] == old["value"]["oid"] and type(details["file"]) is int and details["file"] > 0
                    and details["file"] == old["file"] and details["aclOids"] == expected_oids and details["sharedOids"] == shared,
                    "meaning-mismatch", witness)
    error = actual["error"]
    require(error is None if model["sqlState"] == "00000"
            else error is not None and error[:2] == ["ERROR", model["sqlState"]], "meaning-mismatch", witness)
    if model["sqlState"] == "2BP01":
        require(error[2] == "dependent privileges exist", "meaning-mismatch", witness)
    if model["sqlState"] == "0A000" and not item.get("nativeRefusal"):
        require(error[2] == "grantor must be current user", "meaning-mismatch", witness)


def observe_rejection(_, item, actual, command):
    same(sql(item), command)
    check_observation(item, actual, command)
    return ("edge", item["negative"])


def rejected_finite_ids(_spec, _tag):
    return {("edge", label) for label in NATIVE_EDGES}


def sample_forms(forms, reply, _engine, item):
    value = reply["sequenceAccess"]
    for key in ("targetKind", "operation", "quoted", "active", "catalog", "chain", "alternate"):
        forms[f"{key}/{item[key]}"] += 1
    for right in value["rights"] or ["ALL"]:
        forms["right/" + right] += 1
    for role in value["grantees"]:
        forms["recipient/" + role.get("session", "nominal" if "name" in role else "public")] += 1
    grantor = value["grantor"]
    forms["grantor/" + ("implicit" if grantor is None else grantor.get("session", "nominal"))] += 1


def sample_native_forms(forms, actual, _engine, _item):
    forms["state/" + actual["sqlState"]] += 1


def required_forms(*_):
    return {"targetKind/named", "targetKind/schemas", *(f"operation/{op}" for op in (*OPERATIONS, "restrict-option", "cascade")),
            *(f"right/{right}" for right in (*BITS, "ALL", "RULE")),
            *(f"{flag}/{value}" for flag in ("quoted", "active", "catalog", "chain", "alternate") for value in (False, True)),
            *(f"recipient/{role}" for role in ("public", "nominal", "CURRENT_USER", "SESSION_USER")),
            *(f"grantor/{role}" for role in ("implicit", "nominal", "CURRENT_USER", "SESSION_USER")),
            "state/00000", "state/0A000", "state/2BP01"}


def controls():
    item = trial("pg-17.2", "grant", "local", "usage", False, grantees=[PUBLIC, named("PUBLIC")])
    healthy = deepcopy({**expected(item), "error": None})
    roles = dict(zip(ROLE_IDENTITIES, range(100, 100 + len(ROLE_IDENTITIES))))
    healthy["principalOids"] = roles
    for phase in ("before", "during", "settled"):
        state = healthy[phase]
        state.update(consumerOid=13, defaultOid=30)
        for oid, sequence in enumerate(state["sequences"].values(), 30):
            sequence["value"]["oid"] = oid
            sequence["file"] = oid + 100
            observed = sequence["acl"]
            sequence["aclOids"] = None if observed is None else sorted([roles[source], roles.get(recipient, 0), right, opt]
                                                                        for source, recipient, right, opt in observed)
            sequence["sharedOids"] = sorted([roles[name], kind] for name, kind in sequence["shared"])
    check_observation(item, healthy, sql(item))
    broken = deepcopy(healthy)
    broken["during"]["sequences"]["local"]["aclOids"] = [
        row for row in broken["during"]["sequences"]["local"]["aclOids"] if row[1] != 0
    ]
    try:
        check_observation(item, broken, sql(item))
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"erased-universal-identity": error.detail}
    raise Failure("postgres-sequence-access-erased-universal-identity")


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    same(STATUS, witness["observed"]["status"])


def faults(_, tag):
    prefix = "sql-semantics-postgres/src/Ir/Schema/Sequence/"
    grants = prefix + "Access/GrantSequenceAccess.php"
    revokes = prefix + "Access/RevokeSequenceAccess.php"
    local = trial(tag, "grant-option", "local", "usage", True)
    effective = trial(tag, "grant", "local", "usage", True, actor=ACTOR, grantees=[EFFECTIVE])
    cascading = trial(tag, "cascade-option", "local", ["SELECT"], True)
    return [
        RenderFault("sequence-access-decoy", prefix + "Access/NamedSequences.php", "$names[] = $sequence->quoted();",
                    f"""$names[] = str_replace('"{LOCAL}".', '"{OTHER}".', $sequence->quoted());""", local, "meaning-mismatch", direct(local)),
        RenderFault("sequence-access-wrong-session", prefix + "Authorization/SequencePrincipal.php",
                    "$this->owner->quoted() : $this->owner->value", "$this->owner->quoted() : 'SESSION_USER'",
                    effective, "meaning-mismatch", direct(effective)),
        RenderFault("sequence-access-drop-grant-option", grants, "($this->grantOption ? ' WITH GRANT OPTION' : '')", "''",
                    local, "meaning-mismatch", direct(local)),
        RenderFault("sequence-access-drop-option-only", revokes, "($this->grantOptionOnly ? 'GRANT OPTION FOR ' : '')", "''",
                    cascading, "meaning-mismatch", direct(cascading)),
        RenderFault("sequence-access-restrict-instead-of-cascade", revokes, "$this->dependency->value", "'RESTRICT'",
                    cascading, "meaning-mismatch", direct(cascading)),
    ]


if __name__ == "__main__":
    require(FIXTURE_CONTAINER in os.environ, "postgres-sequence-access-disposable-service-required")
    main(sys.modules[__name__])
