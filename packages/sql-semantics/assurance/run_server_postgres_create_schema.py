"""Verify PostgreSQL schema creation, authorization identity and deployment effects."""

from itertools import product
import json
import os
from random import Random
import sys

from native_postgres_fixture import ENV as FIXTURE_CONTAINER
from postgres_create_btree_cases import refusal
from postgres_create_schema_cases import (CATALOG, EFFECTIVE, OWNER, QUOTED_OWNER, SESSION, SHAPES, STATUS,
                                          analyzed, direct, name, original, outcome, shape, trial)
from postgres_reindex_cases import quoted
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_drop_table import specifications as drop_contract
from run_server_join import main
from run_server_postgres_create_btree import check_preparation as check_libpq
from run_server_postgres_create_btree import observation, prepare
from sqlite_domain import RenderFault

LABEL = "postgres-create-schema"
CONTRACT_NAME = "postgres-create-schema-v3.json"
CONTRACT = "914c73e7d4a64d9e12c035be63498c61485a8762949f42248830ab7c0ac78228"
OBSERVER = "postgres_create_schema_observer.py"
AXES = ("ownerCoordinates", "occupied", "guarded", "transactional")
UNKNOWN_OWNER = refusal("A named schema owner requires a declared role.")
RESERVED_SCHEMA = refusal("A declared schema name cannot start with pg_.")
BAD_OWNER = refusal("A named schema owner needs a single role name.")


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-create-schema-contract")
    spec = json.loads(path.read_bytes())
    same([], spec["exploration"]["allowedExclusions"])
    return spec, {"postgres": drop_contract()[1]["postgres"]}


def check_preparation(engine, environment, preparation):
    check_libpq(engine, environment, preparation)
    same(CATALOG, environment["currentDatabase"])
    same(sorted([[OWNER, "f"], [QUOTED_OWNER, "f"], [EFFECTIVE, "t"], [SESSION, "t"]]), environment["roleCoordinates"])


def grid(spec):
    return product(*(spec["finite"][axis] for axis in AXES))


def finite_ids(_, spec, _tag):
    seen = set(grid(spec))
    expected = set(product(SHAPES, (False, True), (False, True), (False, True)))
    require(seen == expected and len(seen) == 72, "postgres-create-schema-grid")
    return expected


def analysis_finite_id(_, reply, item, sql):
    coordinate = tuple(item["coordinate"])
    same(original(trial(item["release"], *coordinate)), sql)
    check_value(item, reply)
    return coordinate


def direct_finite_id(_, reply, item, request):
    coordinate = tuple(item["coordinate"])
    same(direct(trial(item["release"], *coordinate)), request)
    check_value(item, reply)
    return coordinate


def generated(tag, seed, count):
    rng = Random(seed)
    orders = list(SHAPES)
    rng.shuffle(orders)
    for index in range(count):
        yield trial(tag, orders[index % len(orders)], bool(rng.getrandbits(1)), bool(rng.getrandbits(1)),
                    bool(rng.getrandbits(1)), quoted_name=bool(rng.getrandbits(1)), base=rng.randrange(-40, 90))


def refusals(tag):
    base = trial(tag, "named/explicit", True, True, False)
    for name_value, owner_value, guard in (("pg_reserved", OWNER, RESERVED_SCHEMA),
                                          ("one", "public", BAD_OWNER), ("one", "none", BAD_OWNER)):
        yield {**direct(base), "createSchema": {"name": name_value, "owner": {"named": owner_value}, "ifNotExists": True}}, None, guard
    missing = {**analyzed(base), "sql": f"CREATE SCHEMA IF NOT EXISTS {quoted(name(base))} AUTHORIZATION absent_binding"}
    yield missing, None, UNKNOWN_OWNER
    reserved = trial(tag, "named/derived", False, True, False, variant="pg_checkpoint")
    yield direct(reserved), None, RESERVED_SCHEMA
    members = {**analyzed(base), "sql": "CREATE SCHEMA owning_schema CREATE SEQUENCE unimplemented_member"}
    yield members, None, refusal("CREATE SCHEMA elements require a checked namespace composition.")
    if tag == "pg-16.6":
        stock = trial(tag, "named/explicit", False, False, False, variant="pg_maintain")
        yield analyzed(stock), stock, UNKNOWN_OWNER


def requests(_, tag, spec):
    for index, coordinate in enumerate(grid(spec)):
        item = trial(tag, *coordinate, coordinate=list(coordinate))
        yield f"finite/{index}", analyzed(item), item, None
        yield f"direct/{index}", direct(item), item, None
    for label, item, request in edge_cases(tag):
        yield f"edge/{label}", request, item, None
    search = spec["exploration"]
    for seed in search["seeds"]:
        for index, item in enumerate(generated(tag, seed, search["casesPerSeed"])):
            yield f"seed/{seed}/{index}", analyzed(item), item, None
            yield f"direct-seed/{seed}/{index}", direct(item), item, None
    for index, (request, item, boundary) in enumerate(refusals(tag)):
        yield f"negative/{index}", request, item, boundary


def edge_cases(tag):
    quoted_owner = trial(tag, "named/derived", False, True, False, quoted_name=True)
    yield "quoted-user", quoted_owner, analyzed(quoted_owner, complete=False)
    stock = trial(tag, "named/explicit", False, True, False, variant="pg_checkpoint")
    yield "stock-checkpoint", stock, analyzed(stock)
    if tag == "pg-17.2":
        maintain = trial(tag, "named/explicit", False, False, False, variant="pg_maintain")
        yield "stock-maintain", maintain, analyzed(maintain)
    session = trial(tag, "session/derived", False, True, False)
    yield "closed-empty-session", session, {**analyzed(session), "declarations": []}


def check_value(item, reply):
    same([], reply.get("schemaTables"))
    same(["constructed", item["release"] + "/utf8/standard-conforming-strings/native", shape(item)],
         [reply.get("status"), reply.get("profile"), reply.get("createSchema")])


def observer_request(item, sql):
    return {"trial": item, "sql": sql}


def check_observation(item, actual, sql):
    expected = outcome(item)
    witness = {"expected": expected, "observed": actual, "sql": sql}
    require(actual.get("status") == STATUS, "postgres-create-schema-observer-scope", witness)
    require({key: actual[key] for key in expected} == expected, "meaning-mismatch", witness)
    message = {
        "00000": None, "42P06": f'schema "{name(item)}" already exists',
        "42704": f'role "{item["named"]}" does not exist',
    }[expected["sqlState"]]
    error = None if message is None else ["ERROR", expected["sqlState"], message, None, None]
    require(actual["error"] == error, "meaning-mismatch", witness)


def observe_rejection(_spec, item, actual, sql):
    same(original(item), sql)
    check_observation(item, actual, sql)
    return "stock-maintain-absence"


def rejected_finite_ids(_spec, tag):
    return {"stock-maintain-absence"} if tag == "pg-16.6" else set()


def sample_forms(forms, _reply, _engine, item):
    forms["owner/" + item["mode"] + ("/derived" if item["name"] is None else "/explicit")] += 1
    for key in ("occupied", "guard", "active"):
        forms[f"{key}/{item[key]}"] += 1
    forms["name/" + ("quoted" if name(item).startswith("IR ") else "regular")] += 1


def required_forms(*_):
    return {*(f"owner/{role}" for role in SHAPES), "name/quoted", "name/regular",
            *(f"{key}/{value}" for key in ("occupied", "guard", "active") for value in (False, True))}


def controls():
    item = trial("pg-17.2", "session/explicit", False, True, False)
    healthy = {**outcome(item), "error": None}
    check_observation(item, healthy, original(item))
    def wrong_owner(state):
        return {**state, "namespaces": [[key, EFFECTIVE if key == name(item) else role, acl] for key, role, acl in state["namespaces"]]}
    mutant = {**healthy, "during": wrong_owner(healthy["during"]), "settled": wrong_owner(healthy["settled"])}
    try:
        check_observation(item, mutant, original(item))
    except Failure as failure:
        same("meaning-mismatch", failure.signal)
        return {"effective-owner-substitution": failure.detail}
    raise Failure("postgres-create-schema-comparator-ignored-ownership")


def check_fault_witness(item, witness):
    same(outcome(item), witness["expected"])
    same(STATUS, witness["observed"]["status"])


def faults(_, tag):
    path = "sql-semantics-postgres/src/Ir/Schema/Namespace/CreateSchema.php"
    session = trial(tag, "session/explicit", False, True, False, quoted_name=True)
    duplicate = trial(tag, "named/explicit", True, True, True)
    distinct_name = trial(tag, "named/explicit", False, False, False)
    return [
        RenderFault("create-schema-effective-owner-for-session", path, ": $this->owner->value;",
                    ": SchemaSessionRole::Effective->value;", session, "meaning-mismatch", direct(session)),
        RenderFault("create-schema-omit-guard", path, "($this->ifNotExists ? 'IF NOT EXISTS ' : '')",
                    "''", duplicate, "meaning-mismatch", direct(duplicate)),
        RenderFault("create-schema-owner-as-separate-name", path, "$this->name->quoted() . ' '",
                    "$this->owner->quoted() . ' '", distinct_name, "meaning-mismatch", direct(distinct_name)),
    ]


if __name__ == "__main__":
    require(FIXTURE_CONTAINER in os.environ, "postgres-create-schema-disposable-service-required")
    main(sys.modules[__name__])
