"""Verify renaming and ownership changes against native PostgreSQL."""

from copy import deepcopy
from itertools import product
import json
import os
from random import Random
import sys

from native_postgres_fixture import ENV as FIXTURE_CONTAINER
from postgres_alter_schema_cases import DECOY, OWNERS, RENAMES, ROWS, STATUS, analyzed, direct, expected, ownership, rename, sql
from postgres_create_btree_cases import refusal
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main
from run_server_postgres_create_schema import check_preparation, observation, observer_request, prepare
from run_server_postgres_create_schema import specifications as namespace_specifications
from sqlite_domain import RenderFault

LABEL = "postgres-alter-schema"
CONTRACT_NAME = "postgres-alter-schema-v1.json"
CONTRACT = "ad8e64bdd9b855ab46749acc0575b686cc66592ee9a3eb49084d534e322198dc"
OBSERVER = "postgres_alter_schema_observer.py"
AXES = {"rename": ("qualifiedSpelling", "occupied", "active", "sourceData"), "owner": ("role", "acl", "active")}


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-alter-schema-contract")
    spec = json.loads(path.read_text())
    same([], spec["exploration"]["allowedExclusions"])
    return spec, namespace_specifications()[1]


def grid(spec):
    for mode, axes in AXES.items():
        for coordinate in product(*(spec["finite"][mode][axis] for axis in axes)):
            yield (mode, *coordinate)


def finite_ids(_, spec, _tag):
    seen = set(grid(spec))
    fixed = set(product(("rename",), RENAMES, (False, True), (False, True), ROWS))
    fixed |= set(product(("owner",), OWNERS, (False, True), (False, True)))
    require(seen == fixed and len(seen) == 36, "postgres-alter-schema-grid")
    return fixed


def case(tag, key):
    mode, *factors = key
    factory = rename if mode == "rename" else ownership
    return factory(tag, *factors, coordinate=list(key))


def analysis_finite_id(_, observed, item, command):
    key = tuple(item["coordinate"])
    same(sql(case(item["release"], key)), command)
    check_value(item, observed)
    return key


def direct_finite_id(_, observed, item, request):
    key = tuple(item["coordinate"])
    same(direct(case(item["release"], key)), request)
    check_value(item, observed)
    return key


def generated(tag, seed, count):
    rng = Random(seed)
    modes = ["rename", *OWNERS]
    rng.shuffle(modes)
    for index in range(count):
        action = modes[index % len(modes)]
        active, quoted = bool(rng.getrandbits(1)), bool(rng.getrandbits(1))
        data, base = rng.choice(ROWS), rng.randrange(-60, 83)
        if action == "rename":
            yield rename(tag, "quoted" if quoted else "plain", bool(rng.getrandbits(1)), active, data,
                         base=base, relation_bound=bool(rng.getrandbits(1)))
        else:
            yield ownership(tag, action, bool(rng.getrandbits(1)), active, base=base, quoted_name=quoted, data=data)


def refusals(tag):
    base = rename(tag, "plain", False, False, "distinct")
    absent = {**analyzed(base), "declarations": []}
    yield absent, None, refusal("ALTER SCHEMA requires a declared source namespace.")
    temp = direct(base)
    yield {**temp, "alterSchema": {**temp["alterSchema"], "schema": "pg_temp"}}, None, refusal("requires a persistent namespace.")
    same_name = {**base, "new": base["old"]}
    yield analyzed(same_name), same_name, refusal("requires a distinct unreserved destination.")
    reserved = direct({**base, "new": "pg_occupied"})
    yield reserved, None, refusal("requires a distinct unreserved destination.")
    named = ownership(tag, "named", True, False)
    yield {**analyzed(named), "declarations": ["CREATE SCHEMA ir_ns_alter_subject"]}, None, refusal("requires a declared role.")
    yield {**analyzed(named), "sql": "ALTER SCHEMA ir_ns_alter_subject OWNER TO PUBLIC"}, None, refusal("requires a single owner.")


def requests(_, tag, spec):
    for ordinal, key in enumerate(grid(spec)):
        item = case(tag, key)
        yield f"finite/{ordinal}", analyzed(item), item, None
        yield f"direct/{ordinal}", direct(item), item, None
    value = rename(tag, "quoted", False, True, "distinct", relation_bound=True)
    yield "edge/relation-bound-namespace", analyzed(value), value, None
    settings = spec["exploration"]
    for seed in settings["seeds"]:
        for index, item in enumerate(generated(tag, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", analyzed(item), item, None
            yield f"direct-seed/{seed}/{index}", direct(item), item, None
    for ordinal, (request, native, boundary) in enumerate(refusals(tag)):
        yield f"negative/{ordinal}", request, native, boundary


def check_value(item, observed):
    same(["constructed", item["release"] + "/utf8/standard-conforming-strings/native", direct(item)["alterSchema"]],
         [observed.get("status"), observed.get("profile"), observed.get("alterSchema")])


def check_identities(item, observed, target):
    before, during, later = (observed[phase] for phase in ("before", "during", "settled"))
    observed_name = item["old"] if item["mode"] == "owner" else item["new"]
    identities = before["identities"]
    altered = {key if key != item["old"] else observed_name: pair for key, pair in identities.items()}
    unique = set()
    for schema, tables in identities.values():
        require(type(schema) is int and len(tables) == 1 and type(tables[0]) is int and schema not in unique
                and tables[0] not in unique and schema != tables[0], "meaning-mismatch", target)
        unique.update((schema, tables[0]))
    require(type(before["viewIdentity"]) is int and before["viewIdentity"] not in unique, "meaning-mismatch", target)
    for phase, index in ((during, altered), (later, identities if item["active"] or during is None else altered)):
        if phase is not None:
            require(phase["identities"] == index and phase["viewIdentity"] == before["viewIdentity"], "meaning-mismatch", target)


def structural(observed, model):
    states = {"before", "during", "settled"}
    return {key: ({field: observed[key][field] for field in model[key]} if key in states and model[key] is not None
                  and observed[key] is not None else observed[key]) for key in model}


def check_observation(item, actual, command):
    model = expected(item)
    witness = {"expected": model, "observed": actual, "sql": command}
    require(actual.get("status") == STATUS, "postgres-alter-schema-observer-scope", witness)
    require(structural(actual, model) == model, "meaning-mismatch", witness)
    check_identities(item, actual, witness)
    error = ["ERROR", "42P06", f'schema "{item["new"]}" already exists', None, None] if model["sqlState"] == "42P06" else None
    require(actual["error"] == error, "meaning-mismatch", witness)


def observe_rejection(_spec, item, actual, sql_text):
    same(sql(item), sql_text)
    check_observation(item, actual, sql_text)
    return "identical-source-destination"


def rejected_finite_ids(_spec, _tag):
    return {"identical-source-destination"}


def sample_forms(forms, _response, _engine, item):
    forms["mode/" + item["mode"]] += 1
    forms["data/" + item["data"]] += 1
    for field in ("occupied", "active", "acl", "quoted"):
        forms[f"{field}/{item[field]}"] += 1
    if item["mode"] == "owner":
        forms["owner/" + item["ownerMode"]] += 1


def required_forms(*_):
    return {"mode/rename", "mode/owner", *(f"data/{kind}" for kind in ROWS), *(f"owner/{kind}" for kind in OWNERS),
            *(f"{axis}/{value}" for axis in ("quoted", "active", "acl", "occupied") for value in (False, True))}


def controls():
    item = rename("pg-17.2", "plain", False, False, "nullable-bag")
    healthy = {**expected(item), "error": None}
    for state, name in (("before", item["old"]), ("during", item["new"]), ("settled", item["new"])):
        healthy[state] = {**healthy[state], "identities": {name: [11, [12]], DECOY: [31, [32]]}, "viewIdentity": 55}
    check_observation(item, healthy, sql(item))
    broken = deepcopy(healthy)
    broken["during"]["identities"][item["new"]][1] = [90]
    try:
        check_observation(item, broken, sql(item))
    except Failure as failure:
        same("meaning-mismatch", failure.signal)
        return {"native-identity-different": failure.detail}
    raise Failure("postgres-alter-schema-comparator-ignored-physical-identity")


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    same(STATUS, witness["observed"]["status"])


def faults(_, tag):
    rename_path = "sql-semantics-postgres/src/Ir/Schema/Namespace/RenameSchema.php"
    owner_path = "sql-semantics-postgres/src/Ir/Schema/Namespace/ChangeSchemaOwner.php"
    other = rename(tag, "plain", False, False, "distinct")
    session = ownership(tag, "session", True, True)
    named = ownership(tag, "named", True, False, quoted_name=True)
    return [
        RenderFault("rename-schema-select-decoy", rename_path, "$this->schema->quoted()",
                    "'" + '"' + DECOY + '"' + "'", other, "meaning-mismatch", direct(other)),
        RenderFault("alter-schema-replace-session-owner", owner_path, ": $this->owner->value;",
                    ": 'CURRENT_USER';", session, "meaning-mismatch", direct(session)),
        RenderFault("alter-schema-replace-named-owner", owner_path, "$this->owner->quoted() :",
                    "'CURRENT_USER' :", named, "meaning-mismatch", direct(named)),
    ]


if __name__ == "__main__":
    require(FIXTURE_CONTAINER in os.environ, "postgres-alter-schema-disposable-service-required")
    main(sys.modules[__name__])
