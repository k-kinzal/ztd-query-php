"""Compare ordered PostgreSQL sequence authorization with native observations."""

from copy import deepcopy
from itertools import product
import json
import os
from random import Random
import sys

from native_postgres_fixture import ENV as FIXTURE_CONTAINER
from postgres_create_btree_cases import refusal
from postgres_reindex_cases import qualified
from postgres_sequence_authorization_cases import (
    LOCAL, LOGGING, MISSING, OTHER, PLANS, ROLES, STATUS, analyzed, direct, expected, selected_role, source, sql, trial,
)
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_drop_table import specifications as drop_profiles
from run_server_join import main
from run_server_postgres_create_btree import observation, prepare
from run_server_postgres_drop_sequence import check_preparation
from run_server_postgres_sequence_logging import project as project_logging
from sqlite_domain import RenderFault

LABEL = "postgres-sequence-authorization"
CONTRACT_NAME = "postgres-sequence-authorization-v1.json"
CONTRACT = "f2b80990255125fcea9ffd4bc28e0b371fff6b9fa2a611ee684068c4e3bfdbba"
OBSERVER = "postgres_sequence_authorization_observer.py"
ABSENT = refusal("An absent sequence selector requires IF EXISTS.")
TEMPORARY = refusal("Changing sequence logging requires a persistent source.")
NATIVE_EDGES = ("unknown-owner", "public-owner", "wrong-kind", "foreign-catalog")


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-sequence-authorization-contract")
    spec = json.loads(path.read_bytes())
    same([], spec["exploration"]["allowedExclusions"])
    spec["additionalPostgreSqlNegatives"] = [*sorted(disallowed(spec))] * 2 + list(range(10))
    return spec, {"postgres": drop_profiles()[1]["postgres"]}


def grid(spec):
    finite = spec["finite"]
    return product(finite["roles"], map(tuple, finite["plans"]), finite["guard"], finite["transactional"])


def disallowed(spec):
    return {key for key in grid(spec) if (key[0] in MISSING and not key[2])
            or (key[0] == "temporary" and any(action in LOGGING for action in key[1]))}


def finite_ids(_, spec, _tag):
    registered = set(product(ROLES, PLANS, (False, True), (False, True)))
    require(set(grid(spec)) == registered and len(registered) == 168 and len(disallowed(spec)) == 50,
            "postgres-sequence-authorization-grid")
    return registered - disallowed(spec)


def identity(item):
    role, plan, guard, active = item["coordinate"]
    return role, tuple(plan), guard, active


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
    owners = ("first", "second", "effective", "session")
    for index in range(count):
        length = index % 8 + 1
        role = rng.choice(("local", "other")) if length == 8 else roles[index % len(roles)]
        plan = [rng.choice(owners) for _ in range(length)]
        if role in ("local", "other") and length > 1:
            prefix, tail = rng.choices(tuple(LOGGING), k=2)
            positions = sorted(rng.sample(range(1, length), rng.randrange(1, length)))
            for where in positions[:-1]:
                plan[where] = prefix
            plan[positions[-1]] = tail
        yield trial(tag, role, plan, role in MISSING or index % 2 == 1, index % 4 < 2,
                    quoted_name=index % 3 == 0, catalog=bool(rng.getrandbits(1)), owned=index % 4 == 1,
                    acl=index % 3 != 0, effective=index % 4 != 0, current_role_token=index % 2 == 0,
                    base=rng.randrange(3, 90))


def edges(tag):
    yield "acl-retarget", trial(tag, "local", ["first", "second"], False, True, acl=True, quoted_name=True)
    yield "session-is-not-effective", trial(tag, "other", ["effective", "session"], True, True, acl=True, current_role_token=True)
    yield "owned-role-no-op", trial(tag, "other", ["session"], True, False, owned=True, acl=True)
    yield "owned-role-refused", trial(tag, "local", ["first"], False, True, owned=True, acl=True)
    yield "logging-and-owner", trial(tag, "local", ["logged", "effective", "unlogged"], False, False, acl=True)
    missing = trial(tag, "missing-scoped", ["effective"], True, True)
    target = qualified(*source(missing)[:2])
    yield "guarded-undeclared-role", {**missing, "command": f"ALTER SEQUENCE IF EXISTS {target} OWNER TO role_not_declared"}


def refusals(tag):
    item = trial(tag, "local", ["first"], True, False)
    commands = (
        ("unknown-owner", f"ALTER SEQUENCE {LOCAL}.ticket OWNER TO role_not_declared", "42704",
         refusal("A named schema owner requires a declared role.")),
        ("public-owner", f"ALTER SEQUENCE {LOCAL}.ticket OWNER TO PUBLIC", "42704", refusal("A schema requires a single owner.")),
        ("wrong-kind", f"ALTER SEQUENCE IF EXISTS {LOCAL}.consumer OWNER TO CURRENT_USER", "42809",
         refusal('"consumer" is not a sequence', "SemanticException")),
        ("foreign-catalog", f"ALTER SEQUENCE outside.{LOCAL}.ticket OWNER TO CURRENT_USER", "0A000",
         refusal("An explicit PostgreSQL catalog requires the matching current database.")),
    )
    for name, command, state, problem in commands:
        negative = {**item, "command": command, "negative": name, "nativeRefusal": state}
        yield analyzed(negative), negative, problem
    absent = trial(tag, "missing-scoped", ["first"], True, True)
    yield {**analyzed(absent), "complete": False}, None, refusal("A sequence selector cannot infer absence in an open relation namespace.")
    cases = [
        (item, [{"owner": {"name": "none"}}], "A sequence owner must identify a single role."),
        (item, [{"owner": {"name": "public"}}], "A sequence owner must identify a single role."),
        (item, [{"owner": {"name": "pg_unknown_owner"}}], "The stock sequence owner must exist in this release."),
        (item, [{"logging": "unlogged"}], "A present sequence authorization requires an owning role assignment."),
        (item, [{"logging": "unlogged"}, {"logging": "logged"}, {"owner": {"session": "CURRENT_USER"}}, {"logging": "unlogged"}],
         "A sequence logging prefix must request one consistent mode."),
        (item, ["untyped"], "Sequence authorization needs an ordered action list."),
        (absent, [{"owner": {"session": "CURRENT_USER"}}], "An absent sequence cannot carry authorization actions."),
    ]
    for value, actions, message in cases:
        request = direct(value)
        request["sequenceAuthorization"]["actions"] = actions
        yield request, None, refusal(message)


def requests(_, tag, spec):
    refused = disallowed(spec)
    for ordinal, key in enumerate(grid(spec)):
        item = trial(tag, *key, coordinate=list(key))
        if key in refused:
            analyzed_failure = TEMPORARY
            direct_failure = TEMPORARY
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
    same(seeds["casesPerSeed"], seeds["directCasesPerSeed"])
    for seed in seeds["seeds"]:
        for index, item in enumerate(generated(tag, seed, seeds["casesPerSeed"])):
            yield f"seed/{seed}/{index}", analyzed(item), item, None
            yield f"direct-seed/{seed}/{index}", direct(item), item, None
    for index, (request, item, problem) in enumerate(refusals(tag)):
        yield f"negative/{index}", request, item, problem


def check_value(item, actual):
    same(["constructed", item["release"] + "/utf8/standard-conforming-strings/native", direct(item)["sequenceAuthorization"]],
         [actual.get("status"), actual.get("profile"), actual.get("sequenceAuthorization")])


def observer_request(item, command):
    return {"trial": item, "sql": command}


def project(record):
    value = project_logging(record)
    for role, sequence in value["sequences"].items():
        for field in ("acl", "shared"):
            sequence[field] = record["sequences"][role][field]
    return value


def check_observation(item, actual, command):
    model = expected(item)
    witness = {"expected": model, "observed": actual, "sql": command}
    require(actual.get("status") == STATUS, "sequence-authorization-observer-scope", witness)
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
            changes_file = model["rewritten"] and role == selected_role(item) and (phase == "during" or phase == "settled" and not item["active"])
            require(sequence["value"]["oid"] == before["value"]["oid"]
                    and sequence["value"]["ownership"] == before["value"]["ownership"]
                    and type(sequence["file"]) is int and sequence["file"] > 0
                    and (sequence["file"] != before["file"]) == changes_file, "meaning-mismatch", witness)
    error = actual["error"]
    require(error is None if model["sqlState"] == "00000"
            else error is not None and error[:2] == ["ERROR", model["sqlState"]], "meaning-mismatch", witness)
    if model["sqlState"] == "0A000" and "nativeRefusal" not in item:
        source_mode = model["before"]["sequences"][selected_role(item)]["value"]["persistence"]
        modes = [LOGGING[mode] for mode in item["plan"] if mode in LOGGING]
        message = (
            "cannot change persistence setting twice" if any(mode != source_mode for mode in modes[:-1])
            else f'cannot change owner of sequence "{item["name"]}"'
        )
        require(error[2] == message, "meaning-mismatch", witness)


def observe_rejection(_, item, actual, command):
    same(sql(item), command)
    check_observation(item, actual, command)
    return ("edge", item["negative"]) if "negative" in item else ("finite", identity(item))


def rejected_finite_ids(spec, _tag):
    return {("finite", key) for key in disallowed(spec)} | {("edge", label) for label in NATIVE_EDGES}


def sample_forms(forms, reply, _engine, item):
    for key in ("role", "guard", "active", "catalog", "quoted", "oldOwner", "effectiveRole", "acl"):
        forms[f"{key}/{item[key]}"] += 1
    actions = reply["sequenceAuthorization"]["actions"]
    for action in actions:
        if "owner" in action:
            forms["principal/" + action["owner"].get("session", "named")] += 1
        else:
            forms["logging/" + action["logging"]] += 1
    forms[f"length/{len(actions)}"] += 1


def sample_native_forms(forms, actual, _engine, item):
    forms["state/" + actual["sqlState"]] += 1
    forms[f"rewritten/{actual['rewritten']}"] += 1
    if actual["during"] is not None:
        role = selected_role(item)
        differs = actual["during"]["sequences"][role]["acl"] != actual["before"]["sequences"][role]["acl"]
        forms[f"aclRetargeted/{differs}"] += 1


def required_forms(*_):
    return {*(f"role/{role}" for role in ROLES), *(f"logging/{mode}" for mode in LOGGING),
            *(f"{axis}/{value}" for axis in ("guard", "active", "catalog", "quoted", "oldOwner", "effectiveRole", "acl")
              for value in (True, False)),
            "principal/CURRENT_USER", "principal/SESSION_USER", "principal/named",
            "state/00000", "state/0A000", "rewritten/True", "rewritten/False", "aclRetargeted/True", "aclRetargeted/False",
            "length/0", "length/1", "length/8"}


def controls():
    item = trial("pg-17.2", "local", ["first", "second"], False, True, acl=True)
    healthy = deepcopy({**expected(item), "error": None})
    for phase in ("before", "during", "settled"):
        state = healthy[phase]
        state.update(consumerOid=13, defaultOid=30, temporaryNamespace=["pg_temp_1", 15])
        for index, detail in enumerate(state["sequences"].values(), 30):
            detail["value"]["oid"] = index
            detail["value"]["ownership"] = []
            detail["file"] = index + 100
    check_observation(item, healthy, sql(item))
    broken = deepcopy(healthy)
    broken["during"]["sequences"]["local"]["acl"] = healthy["before"]["sequences"]["local"]["acl"]
    try:
        check_observation(item, broken, sql(item))
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"ignored-acl-rewrite": error.detail}
    raise Failure("postgres-sequence-authorization-ignored-acl-rewrite")


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    same(STATUS, witness["observed"]["status"])


def faults(_, tag):
    directory = "sql-semantics-postgres/src/Ir/Schema/Sequence/Authorization/"
    path = directory + "AlterSequenceAuthorization.php"
    local = trial(tag, "local", ["first"], False, True)
    effective = trial(tag, "local", ["effective"], True, True)
    order = trial(tag, "local", ["first", "second"], False, False, acl=True)
    mixed = trial(tag, "local", ["logged", "first", "unlogged"], False, False, acl=True)
    quoting = "str_replace('\"" + LOCAL + "\".', '\"" + OTHER + "\".', $this->source->quoted())"
    return [
        RenderFault("sequence-authorization-target-decoy", path, "$this->source->quoted()", quoting,
                    local, "meaning-mismatch", direct(local)),
        RenderFault("sequence-authorization-session-instead-of-effective", directory + "SequencePrincipal.php",
                    "$this->owner->quoted() : $this->owner->value", "$this->owner->quoted() : 'SESSION_USER'",
                    effective, "meaning-mismatch", direct(effective)),
        RenderFault("sequence-authorization-collapse-ordered-owners", path, "implode(', ', $clauses)", "$clauses[count($clauses) - 1]",
                    order, "meaning-mismatch", direct(order)),
        RenderFault("sequence-authorization-drop-logging", path, "implode(', ', $clauses)",
                    "implode(', ', array_filter($clauses, static fn ($clause) => !str_starts_with($clause, 'SET ')))",
                    mixed, "meaning-mismatch", direct(mixed)),
    ]


if __name__ == "__main__":
    require(FIXTURE_CONTAINER in os.environ, "postgres-sequence-authorization-disposable-service-required")
    main(sys.modules[__name__])
