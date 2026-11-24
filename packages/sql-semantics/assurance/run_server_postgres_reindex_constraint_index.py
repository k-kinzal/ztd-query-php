"""Check PostgreSQL rebuilds that preserve and reconnect declared constraints."""

import json
from itertools import product
from random import Random
import sys

from postgres_create_btree_cases import refusal
from postgres_reindex_cases import CONSTRAINT_DDL, analyzed, case, expected
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_drop_table import specifications as drop_contract
from run_server_join import main, probe
from run_server_postgres_create_btree import check_preparation, observation, observer_request, prepare
from run_server_postgres_reindex_index import OBSERVER, check_value, check_observation as check_rebuild
from sqlite_domain import RenderFault

LABEL = "postgres-reindex-constraint-index"
CONTRACT_NAME = "postgres-reindex-constraint-index-v1.json"
CONTRACT = "38b45ec46b0da6a420a1fb61a5ebce316a8fd4313cc14e432e7cc479d16471ae"
COMPLETE_SEEDS = True
AXES = ("kind", "concurrently", "active", "spelling")
ROWS = [[1, 20, True], [2, 19, False]]


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-reindex-constraint-contract")
    spec = json.loads(path.read_text())
    require(spec["exploration"]["allowedExclusions"] == [], "postgres-reindex-constraint-exclusions")
    return spec, {"postgres": drop_contract()[1]["postgres"]}


def finite_ids(_, spec, _tag):
    coordinates = {(mode, concurrent, active, spelling) for mode in CONSTRAINT_DDL
                   for concurrent in (False, True) for active in (False, True)
                   for spelling in ("qualified", "local")}
    require(len(coordinates) == 32 and coordinates == set(product(*(spec["finite"][name] for name in AXES))),
            "postgres-reindex-constraint-finite-size")
    return coordinates


def build(tag, kind, concurrent, active, spelling, *, coordinate=None, scope="fixture", **names):
    item = case(tag, scope, "keyword" if concurrent else "ordinary", active,
                "absolute" if spelling == "qualified" else "local",
                "unique-null-equal" if kind == "unique-nulls" else "unique", False,
                order="ASC NULLS LAST", covering=(), coordinate=coordinate, **names)
    return {**item, "rows": ROWS, "constraintPolicy": kind,
            "reindex": {**item["reindex"], "constraintOwned": True}}


def finite_id(_, result, item):
    coordinate = tuple(item["coordinate"])
    require(result["reindex"] == build(item["release"], *coordinate)["reindex"],
            "postgres-reindex-constraint-finite-identity", coordinate)
    return coordinate


def direct_finite_id(engine, result, item, _request):
    return finite_id(engine, result, item)


def analysis_finite_id(engine, result, item, _authored):
    return finite_id(engine, result, item)


def generated(tag, seed, total):
    rng = Random(seed)
    for ordinal in range(total):
        yield build(
            tag, rng.choice(tuple(CONSTRAINT_DDL)), bool(rng.getrandbits(1)), bool(rng.getrandbits(1)),
            rng.choice(("qualified", "local")), scope=rng.choice(("fixture", "pg_temp")),
            index=rng.choice(("cover", 'Key "名"')), table=rng.choice(("orders", "Orders 名")),
        )


def negatives(tag):
    primary = analyzed(build(tag, "primary", False, False, "qualified"))
    unique = analyzed(build(tag, "unique", True, True, "local"))
    for root in (primary, unique):
        yield {**root, "sql": 'DROP INDEX "fixture"."cover"'}, refusal("protected in the ordinary index profile")
    yield {**primary, "sql": 'REINDEX INDEX "fixture"."unbound"'}, refusal("requires a declared index")
    yield {**primary, "declarations": [*primary["declarations"], 'CREATE INDEX cover ON fixture.orders (ref)']}, refusal("conflicting index declarations")
    partitioned = [part + " PARTITION BY RANGE (ref)" if 'CONSTRAINT "cover" PRIMARY KEY' in part else part
                   for part in primary["declarations"]]
    yield {**primary, "declarations": partitioned}, refusal("requires a nonpartitioned index")


def requests(_, tag, spec):
    for ordinal, coordinate in enumerate(product(*(spec["finite"][name] for name in AXES))):
        item = build(tag, *coordinate, coordinate=list(coordinate))
        yield f"finite/{ordinal}", analyzed(item), item, None
        yield f"direct/{ordinal}", item, item, None
    for seed in spec["exploration"]["seeds"]:
        for ordinal, item in enumerate(generated(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{ordinal}", item, item, None
    for ordinal, (request, reason) in enumerate(negatives(tag)):
        yield f"negative/{ordinal}", request, None, reason


def identities(item, snapshot):
    if snapshot is None:
        return None
    scope = item["reindex"]["target"][0]
    selected = snapshot[scope]
    kind = item["constraintPolicy"]
    key, foreign, rows = (selected["constraints"][name] for name in ("key", "foreign", "rows"))
    physical = selected["index"]["oid"]
    want = "p" if kind == "primary" else "u"
    deferrable = kind == "deferrable"
    require(key[1:3] == [want, physical] and key[4:] == ["0", deferrable, False, True],
            "postgres-reindex-key-link", {"physical": physical, "key": key})
    if deferrable:
        same([None, None], [foreign, rows])
        return [key[0], key[3]]
    require(foreign[1:3] == ["f", physical] and foreign[4:] == [key[3], False, False, True] and rows == [19, 20],
            "postgres-reindex-foreign-link", {"physical": physical, "key": key, "foreign": foreign, "rows": rows})
    return [key[0], key[3], foreign[0], foreign[3]]


def check_observation(item, actual, sql):
    check_rebuild(item, actual, sql)
    first = identities(item, actual["before"])
    try:
        same(first, identities(item, actual["settled"]))
        if actual["during"] is not None:
            same(first, identities(item, actual["during"]))
    except Failure as error:
        raise Failure("meaning-mismatch", {"expected": expected(item), "observed": actual, "sql": sql,
                                          "constraintWitness": error.detail}) from error


def sample_forms(forms, response, _, item):
    forms[f"scope/{response['reindex']['target'][0]}"] += 1
    forms[f"kind/{item['constraintPolicy']}"] += 1
    forms[f"mode/{response['reindex']['mode']}"] += 1
    forms[f"transaction/{item['active']}"] += 1
    forms[f"runtime/{expected(item)['sqlState']}"] += 1


def required_forms(*_):
    return {"scope/fixture", "scope/pg_temp", "mode/standard", "mode/concurrently", "transaction/True", "transaction/False",
            "runtime/00000", "runtime/25001", *(f"kind/{kind}" for kind in CONSTRAINT_DDL)}


def controls():
    item = build("pg-17.2", "primary", True, False, "qualified")
    reference = expected(item)
    good = {field: value for field, value in reference.items() if field not in ("changedDuring", "changedSettled")}
    for phase, oid in (("before", "10"), ("during", "12"), ("settled", "12")):
        other = reference[phase]["decoy"]
        selected = reference[phase]["fixture"]
        good[phase] = {
            "fixture": {"rows": selected["rows"], "index": {**selected["index"], "oid": oid, "file": oid},
                        "constraints": {"key": ["11", "p", oid, "7", "0", False, False, True],
                                        "foreign": ["9", "f", oid, "8", "7", False, False, True],
                                        "rows": [19, 20]}},
            "decoy": {"rows": other["rows"], "index": {**other["index"], "oid": "15", "file": "16"}},
        }
    check_observation(item, good, "the foreign key follows the current physical index")
    stale = json.loads(json.dumps(good))
    stale["settled"]["fixture"]["constraints"]["foreign"][2] = "10"
    try:
        check_observation(item, stale, "stale foreign key")
    except Failure as error:
        same("meaning-mismatch", error.signal)
        same("10", error.detail["constraintWitness"]["foreign"][2])
        return {"stale-foreign-index-link": {"input": item, "witness": error.detail}}
    raise Failure("postgres-reindex-constraint-comparator-accepted-stale-link")


def fault_probe(item, response, db):
    probe(item, response, db, sys.modules[__name__])


def replay_fault(rule, record):
    witness = record["detail"]
    check_value(rule.item, record["healthyResponse"])
    try:
        check_value(rule.item, record["mutantResponse"])
        check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("postgres-reindex-constraint-fault-not-replayed", rule.name)


def faults(_, tag):
    root = "sql-semantics-postgres/src/Ir/Schema/Index/ReindexIndex.php"
    lookup = "sql-semantics-postgres/src/Ir/Schema/Index/IndexTargetLowering.php"
    ordinary = build(tag, "primary", False, False, "qualified")
    concurrent = build(tag, "unique-nulls", True, False, "qualified")
    return [
        RenderFault("postgres-constraint-reindex-lost-typed-ownership", lookup,
                    "IndexDeclarationKind::KeyConstraint => new ConstraintIndexReference($schema, $index, $table)",
                    "IndexDeclarationKind::KeyConstraint => new IndexReference($schema, $index, $table)",
                    ordinary, "postgres-reindex-value", analyzed(ordinary)),
        RenderFault("postgres-constraint-reindex-wrong-target", root, "$this->target->quoted()",
                    """'"decoy".' . $this->target->index->quoted()""", ordinary, "meaning-mismatch"),
        RenderFault("postgres-constraint-reindex-lost-concurrency", root, "$this->mode === IndexRebuildMode::Concurrent",
                    "false", concurrent, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
