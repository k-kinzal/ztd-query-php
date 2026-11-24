"""Compare three-part PostgreSQL index statements with resolved local operations."""

from itertools import product
import json
from random import Random
import sys

from postgres_create_btree_cases import refusal
from postgres_index_catalog_cases import (
    COLUMNS, DATABASE, FOREIGN_SPELLINGS, NEGATIVE_ROLES, ROLES, SCHEMAS, SPELLINGS, STATUS,
    analyzed, case, expected, sql,
)
from run import Failure, TOOL, digest, require
from run_server import rejects, same
from run_server_drop_table import specifications as drop_contract
from run_server_join import main, probe
from run_server_postgres_create_btree import observation, prepare, check_preparation as check_libpq
from sqlite_domain import RenderFault

LABEL = "postgres-index-catalog"
CONTRACT_NAME = "postgres-index-catalog-binding-v1.json"
CONTRACT = "c2f6d6e7a8f4e72cc90fc15daef3d6d3204538ec836ddece4c9a9453467ca128"
OBSERVER = "postgres_index_catalog_observer.py"
COMPLETE_SEEDS = True
AXES = ("roles", "spellings", "targets")
REFUSAL = refusal("requires the matching current database")
ESCAPED = "postgres-index-catalog-foreign-published"


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-index-catalog-contract")
    spec = json.loads(path.read_text())
    require(spec["exploration"]["allowedExclusions"] == [], "postgres-index-catalog-exclusions")
    spec["negative"] = list(product(spec["negative"], NEGATIVE_ROLES))
    return spec, {"postgres": drop_contract()[1]["postgres"]}


def check_preparation(engine, environment, preparation):
    check_libpq(engine, environment, preparation)
    same(DATABASE, environment["currentDatabase"])


def finite_ids(_, spec, _tag):
    predicted = set(product(ROLES, SPELLINGS, SCHEMAS))
    require(len(predicted) == 48 and set(product(*(spec["finite"][name] for name in AXES))) == predicted,
            "postgres-index-catalog-finite-axes")
    return predicted


def analysis_finite_id(_, result, item, authored):
    check_value(item, result)
    coordinate = tuple(item["coordinate"])
    same(sql(case(item["release"], *coordinate)), authored)
    return coordinate


def rejected_finite_ids(_, _tag):
    return set(product(NEGATIVE_ROLES, FOREIGN_SPELLINGS))


def observe_rejection(_, item, actual, authored):
    same(sql(item), authored)
    check_observation(item, actual, authored, invalid=True)
    return item["role"], item["spelling"]


def generated(tag, seed, total):
    rng = Random(seed)
    for _ in range(total):
        yield case(tag, rng.choice(ROLES), rng.choice(SPELLINGS), rng.choice(tuple(SCHEMAS)),
                   table=rng.choice(("items", 'Order."名"')), index=rng.choice(("chosen", 'key."値"')),
                   slot=rng.choice((0, 1)), base=rng.randrange(-1000, 1000))


def requests(_, tag, specification):
    for ordinal, axes in enumerate(product(*(specification["finite"][name] for name in AXES))):
        item = case(tag, *axes, coordinate=list(axes))
        yield f"finite/{ordinal}", analyzed(item), item, None
    settings = specification["exploration"]
    for seed in settings["seeds"]:
        for ordinal, item in enumerate(generated(tag, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{ordinal}", analyzed(item), item, None
    for role, spelling in product(NEGATIVE_ROLES, (*FOREIGN_SPELLINGS, "unknown-current")):
        item = case(tag, role, spelling, "later")
        yield f"negative/{role}/{spelling}", analyzed(item), None if spelling == "unknown-current" else item, REFUSAL


def result_shape(item):
    schema = SCHEMAS[item["target"]]
    name, table, role = item["index"], item["table"], item["role"]
    if role == "create":
        return "createBtree", {
            "table": [schema, table, list(COLUMNS), ["int4", "int4"], False],
            "name": name, "predicate": None, "keys": [[item["slot"], "ASC NULLS LAST"]], "included": [],
            "presence": "vacant", "ifNotExists": False, "unique": False, "nullsNotDistinct": False, "concurrently": False,
        }
    if role == "drop":
        return "dropIndex", {"targets": [["bound", schema, name, table]], "ifExists": False, "cascade": False, "mode": "ordinary"}
    if role == "rebuild-owner":
        return "reindexTable", {"target": [schema, table, "base-table"], "mode": "standard"}
    if role == "rebuild-partition-owner":
        return "reindexPartitionTable", {"target": [schema, table], "kind": "branch", "mode": "standard"}
    specialization = {
        "rebuild-index": {}, "rebuild-key": {"constraintOwned": True},
        "rebuild-exclusion": {"exclusionOwned": True}, "rebuild-partition-index": {"partitionKind": "branch"},
    }[role]
    return "reindex", {"target": [schema, name, table], "mode": "standard", **specialization}


def check_value(item, response):
    key, shape = result_shape(item)
    require(response.get("status") == "constructed" and response.get("profile") == item["release"] + "/utf8/standard-conforming-strings/native"
            and response.get(key) == shape and (not key.startswith("reindex") or response.get("verbose") is False),
            "postgres-index-catalog-value", {"expected": [key, shape], "observed": response})


def observer_request(item, command):
    return {"trial": item, "sql": command}


def check_observation(item, actual, command, invalid=False):
    model = expected(item, invalid)
    require(actual == model, "meaning-mismatch", {"sql": command, "expected": model, "observed": actual})


def sample_forms(forms, _response, _engine, item):
    for attribute in ("role", "spelling", "target", "slot"):
        forms[f"{attribute}/{item[attribute]}"] += 1
    forms["name/quoted" if "." in item["table"] else "name/simple"] += 1


def required_forms(*_):
    return {*(f"role/{role}" for role in ROLES), *(f"spelling/{form}" for form in SPELLINGS),
            *(f"target/{schema}" for schema in SCHEMAS), "slot/0", "slot/1", "name/simple", "name/quoted"}


def controls():
    item = case("pg-17.2", "rebuild-index", "quoted-current", "later")
    model = expected(item)
    check_observation(item, model, sql(item))
    wrong = {**model, "changedFiles": [[SCHEMAS["front"], item["index"]]]}
    try:
        check_observation(item, wrong, sql(item))
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"decoy-file-change": error.detail}
    raise Failure("postgres-index-catalog-comparator-accepted")


def fault_probe(item, response, db):
    if item["spelling"] not in FOREIGN_SPELLINGS:
        return probe(item, response, db, sys.modules[__name__])
    if rejects(response, REFUSAL):
        return
    check_value(item, response)
    original = sql(item)
    command = response["sql"]
    observed_origin = db.request(observer_request(item, original))
    observed_render = db.request(observer_request(item, command))
    check_observation(item, observed_origin, original, invalid=True)
    check_observation(item, observed_render, command)
    raise Failure(ESCAPED, {"sql": command, "original": observed_origin, "observed": observed_render,
                            "expected": expected(item, invalid=True)})


def replay_fault(rule, record):
    item, witness = rule.item, record["detail"]
    check_value(item, record["mutantResponse"])
    same(record["mutantResponse"]["sql"], witness["sql"])
    if rule.signal == ESCAPED:
        require(rejects(record["healthyResponse"], REFUSAL), "postgres-index-catalog-fault-control")
        same(expected(item, invalid=True), witness["expected"])
        check_observation(item, witness["original"], sql(item), invalid=True)
        check_observation(item, witness["observed"], witness["sql"])
        return
    check_value(item, record["healthyResponse"])
    same(expected(item), witness["expected"])
    try:
        check_observation(item, witness["observed"], witness["sql"])
    except Failure as error:
        same("meaning-mismatch", error.signal)
        same(expected(item)["changedFiles"], [[SCHEMAS["later"], item["index"]]])
        same(witness["observed"]["changedFiles"], [[SCHEMAS["front"], item["index"]]])
    else:
        raise Failure("postgres-index-catalog-fault-not-reproduced")


def faults(_, tag):
    package = "sql-semantics-postgres/src/"
    index = package + "Ir/Schema/Index/"
    create = case(tag, "create", "foreign", "later")
    drop = case(tag, "drop", "case-distinct", "later")
    rebuild = case(tag, "rebuild-index", "quoted-current", "later")
    return [
        RenderFault("erase-index-owner-catalog", package + "LocalCatalogBinding.php",
                    "$object instanceof QualifiedName => $object->catalog", "$object instanceof QualifiedName => null",
                    create, ESCAPED, analyzed(create)),
        RenderFault("accept-foreign-drop-index", index + "IndexTargetLowering.php",
                    "$name->catalog === null || $name->catalog->value === $context->currentDatabase?->value", "true",
                    drop, ESCAPED, analyzed(drop)),
        RenderFault("render-decoy-index-schema", index + "IndexReference.php",
                    "return $this->schema->quoted() . '.' . $this->index->quoted();",
                    "return '\"qual_front\"' . '.' . $this->index->quoted();",
                    rebuild, "meaning-mismatch", analyzed(rebuild)),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
