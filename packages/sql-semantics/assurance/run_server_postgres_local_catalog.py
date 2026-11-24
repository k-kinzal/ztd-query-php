"""Check native effects and the bound local reference after catalog qualification."""

import json
from itertools import product
from random import Random
import sys

from postgres_create_btree_cases import refusal
from postgres_local_catalog_cases import DATABASE, POSITIONS, ROLES, analyzed, case, expected, source_sql
from run import Failure, TOOL, digest, require
from run_server import rejects, same
from run_server_drop_table import specifications as drop_contract
from run_server_join import main
from run_server_postgres_create_btree import observation, observer_request, prepare
from run_server_postgres_create_btree import check_preparation as check_libpq
from sqlite_domain import RenderFault

LABEL = "postgres-local-catalog"
CONTRACT_NAME = "postgres-local-catalog-binding-v1.json"
CONTRACT = "855a3af17e33095b5a1603198d563d69a9b6ba373b879b716629f9a5b1ffc3ce"
OBSERVER = "postgres_local_catalog_observer.py"
COMPLETE_SEEDS = True
REFUSAL = refusal("requires the matching current database")
ESCAPED = "postgres-catalog-qualifier-escaped"
AXES = ("roles", "qualification", "positions")
MISMATCHED = ("source", "column", "wildcard", "insert", "update", "delete", "refresh", "nested", "declaration")


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-local-catalog-contract")
    spec = json.loads(path.read_text())
    require(spec["exploration"]["allowedExclusions"] == [], "postgres-local-catalog-exclusions")
    return spec, {"postgres": drop_contract()[1]["postgres"]}


def check_preparation(engine, environment, preparation):
    check_libpq(engine, environment, preparation)
    same(DATABASE, environment["currentDatabase"])


def finite_ids(_, spec, _tag):
    model = {(r, form, location) for r in ROLES for form in ("schema-only", "verified-current") for location in POSITIONS}
    require(len(model) == 24 and model == set(product(*(spec["finite"][name] for name in AXES))),
            "postgres-local-catalog-grid")
    return model


def analysis_finite_id(_, result, item, sql):
    check_value(item, result)
    coordinate = tuple(item["coordinate"])
    same(sql, source_sql(case(item["release"], *coordinate)))
    return coordinate


def generated(tag, seed, total):
    rng = Random(seed)
    for _ in range(total):
        form = rng.choice(("schema-only", "verified-current", "local"))
        position = ("first-schema" if form == "local" else rng.choice(tuple(POSITIONS)))
        yield case(tag, rng.choice((*ROLES, "wildcard")), form, position,
                   name=rng.choice(("items", 'Order."名"')), base=rng.choice((-100, 0, 31, 2147483577)),
                   copies=rng.choice((1, 2)))


def rejected_finite_ids(_, _tag):
    return {(role, "mismatched") for role in MISMATCHED}


def requests(_, tag, spec):
    for ordinal, axes in enumerate(product(*(spec["finite"][name] for name in AXES))):
        item = case(tag, *axes, coordinate=list(axes))
        yield f"finite/{ordinal}", analyzed(item), item, None
    for index, form in enumerate(("schema-only", "verified-current")):
        item = case(tag, "wildcard", form, "explicit-second-schema")
        yield f"edge/{index}", analyzed(item), item, None
    for seed in spec["exploration"]["seeds"]:
        for ordinal, item in enumerate(generated(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{ordinal}", analyzed(item), item, None
    for role in ("source", "column", "wildcard"):
        item = case(tag, role, "unknown-current", "first-schema")
        yield f"negative/unknown/{role}", analyzed(item), None, REFUSAL
    for role in MISMATCHED:
        item = case(tag, role, "mismatched", "first-schema")
        yield f"negative/mismatched/{role}", analyzed(item), item, REFUSAL


def check_value(item, result):
    schema, role = POSITIONS[item["position"]], item["role"]
    require(result["status"] == "constructed"
            and result["profile"] == item["release"] + "/utf8/standard-conforming-strings/native",
            "postgres-local-catalog-value", result)
    if role == "refresh":
        same([schema, item["name"] + " rollup"], result["materialization"]["target"])
        same("refresh", result["materialization"]["operation"])
    else:
        source = result["source" if role in ("source", "column", "wildcard") else "target"]
        same((schema, item["name"], ["id"]), (source["schema"], source["table"], source["columns"]))
        if role in ("source", "column", "wildcard"):
            same([[["slot", 0], "id" if role == "wildcard" else "answer"]], result["columns"])


def check_observation(item, actual, sql, original=False):
    want = expected(item, original)
    require(actual == want, "meaning-mismatch", {"sql": sql, "expected": want, "observed": actual})


def observe_rejection(_, item, actual, sql):
    same(source_sql(item), sql)
    check_observation(item, actual, sql, original=True)
    return item["role"], item["form"]


def sample_forms(forms, _response, _engine, item):
    for key in ("role", "form", "position", "copies"):
        forms[f"{key}/{item[key]}"] += 1
    forms["quoted/dotted" if "." in item["name"] else "quoted/simple"] += 1


def required_forms(*_):
    return {*(f"role/{role}" for role in (*ROLES, "wildcard")),
            "form/schema-only", "form/verified-current", "form/local",
            *(f"position/{position}" for position in POSITIONS),
            "copies/1", "copies/2", "quoted/dotted", "quoted/simple"}


def controls():
    item = case("pg-17.2", "column", "verified-current", "first-schema")
    healthy = expected(item)
    check_observation(item, healthy, source_sql(item))
    wrong = {**healthy, "columns": [["wrong", 23]]}
    try:
        check_observation(item, wrong, source_sql(item))
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"mislabeled-projection": {"input": item, "witness": error.detail}}
    raise Failure("postgres-local-catalog-comparator-accepted")


def fault_probe(item, response, db):
    if rejects(response, REFUSAL):
        return
    check_value(item, response)
    original_sql = source_sql(item)
    original = observation(db.request(observer_request(item, original_sql)))
    changed = observation(db.request(observer_request(item, response["sql"])))
    check_observation(item, original, original_sql, original=True)
    check_observation(item, changed, response["sql"])
    raise Failure(ESCAPED, {"sql": response["sql"], "originalSql": original_sql, "original": original, "rendered": changed})


def replay_fault(rule, record):
    require(rejects(record["healthyResponse"], REFUSAL), "postgres-local-catalog-fault-control")
    check_value(rule.item, record["mutantResponse"])
    witness = record["detail"]
    same(record["mutantResponse"]["sql"], witness["sql"])
    same(source_sql(rule.item), witness["originalSql"])
    check_observation(rule.item, witness["original"], witness["originalSql"], original=True)
    check_observation(rule.item, witness["rendered"], witness["sql"])
    same(ESCAPED, rule.signal)


def faults(_, tag):
    file = "sql-semantics-postgres/src/LocalCatalogBinding.php"
    anchors = {
        "source": "$object instanceof QualifiedName => $object->catalog",
        "column": "$object instanceof ColumnReference && count($object->parts) === 4 => $object->parts[0]",
        "wildcard": "$object instanceof ColumnStar && count($object->qualifiers) === 3 => $object->qualifiers[0]",
    }
    return [
        RenderFault(f"discard-postgres-catalog-{role}", file, anchor, anchor.split(" => ")[0] + " => null",
                    item, ESCAPED, analyzed(item))
        for role, anchor in anchors.items() for item in (case(tag, role, "mismatched", "first-schema"),)
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
