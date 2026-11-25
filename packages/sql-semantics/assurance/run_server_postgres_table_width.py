"""Check exact native heap field counts, row representation and physical boundaries."""

from copy import deepcopy
from itertools import product
import json
import os
from random import Random
import sys

from native_postgres_fixture import ENV as FIXTURE_CONTAINER
from postgres_create_btree_cases import refusal
from postgres_table_width_cases import (
    LAYOUTS, NS, STATUS, TYPES, WIDTHS, analyzed, direct, expected, recipe, sql,
)
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main, negative_count
from run_server_postgres_create_schema import specifications as namespace_profiles
from sqlite_domain import RenderFault

LABEL = "postgres-table-width"
NAME = "postgres-table-width-evidence-v1.json"
CONTRACT = "95dee92f0f9a3743c816c7f79c44491706ead597c460f96d03fca7348fe8e408"
BASE = "20f3d5e79fa35a767394e85538396ccf8c824648d526a4f8a4ca0dec5085a975"
OBSERVER = "postgres_table_width_observer.py"
LIMIT_MESSAGE = "A PostgreSQL heap table can declare at most 1600 columns."
ORDER_MESSAGE = "CREATE TABLE columns must form an ordered list of checked columns."


def specifications():
    path = TOOL / NAME
    require(digest(path) == CONTRACT and digest(TOOL / "postgres-table-width-v1.json") == BASE,
            "postgres-table-width-contract")
    report = json.loads(path.read_text())
    same([], report["exploration"]["allowedExclusions"])
    return report, namespace_profiles()[1]


def grid(spec):
    return product(spec["finite"]["widths"], spec["finite"]["layouts"], spec["finite"]["guard"])


def finite_ids(_, spec, _tag):
    expected_points = set(product(WIDTHS, LAYOUTS, (False, True)))
    require(len(expected_points) == 42 and expected_points == set(grid(spec)), "postgres-table-width-finite-grid")
    return expected_points


def analysis_finite_id(_, response, item, command):
    point = tuple(item["coordinate"])
    same(sql(recipe(item["release"], *point)), command)
    check_value(item, response)
    return point


def direct_finite_id(_, response, item, request):
    point = tuple(item["coordinate"])
    same(direct(recipe(item["release"], *point)), request)
    check_value(item, response)
    return point


def edges(tag, spec):
    original = spec["predecessorAdmission"]["previousDirectRefusal"]
    empty = recipe(tag, 0, "integer", False)
    empty["member"]["target"] = original["create"]["target"]
    empty["prior"] = original["occupied"]
    same({"dialect": "postgres", "release": tag, "create": original["create"]}, direct(empty))
    yield "former-empty-refusal", empty
    nested = recipe(tag, 0, "integer", False)
    nested["member"]["target"] = [NS, "empty_member"]
    nested["compose"] = True
    yield "empty-schema-member", nested
    item = recipe(tag, 0, "bigint", True)
    item["prior"] = True
    yield "guarded-empty-table", item
    item = recipe(tag, 0, "integer", False)
    item["probe"] = "returning"
    yield "zero-column-returning-rejected", item
    for storage in ("integer", "bigint"):
        item = recipe(tag, 1600, storage, False)
        item["probe"] = "pack"
        yield f"1600-non-null-{storage}s", item


def generated(tag, seed, count):
    rng = Random(seed)
    for index in range(count):
        layout = LAYOUTS[(seed + index) % len(LAYOUTS)]
        width = 0 if index == 0 else rng.randrange(1, 1601)
        item = recipe(tag, width, layout, bool(index % 2))
        columns = item["member"]["columns"]
        if width > 0:
            columns[0][0] = f'Test "wide".欄 {seed}'
            for ordinal in range(0, width, 37):
                field = columns[ordinal]
                default = {
                    "INTEGER": ["integer", str(rng.randrange(-200, 200))], "SMALLINT": ["integer", "19"],
                    "BIGINT": ["wide-integer", "5000000000"], "pg_catalog.text": ["text", "exact's"],
                    "pg_catalog.bool": ["boolean", False],
                }[field[1]]
                field[2:] = [bool((ordinal + index) % 2), default]
        yield item


def refusals(tag, spec):
    item = recipe(tag, 1601, "integer", False)
    declined = {**item, "nativeRefusal": "54011", "negative": "1601-rejected"}
    yield analyzed(item), declined, refusal(LIMIT_MESSAGE)
    yield direct(item), None, refusal(LIMIT_MESSAGE)
    normal = direct(recipe(tag, 0, "integer", False))
    sparse = deepcopy(normal)
    sparse["create"]["columns"] = spec["predecessorAdmission"]["replacementDirectRefusal"]["create"]["columns"]
    yield sparse, None, refusal(ORDER_MESSAGE)
    wrong = deepcopy(normal)
    wrong["create"]["columns"] = ["foreign-object"]
    yield wrong, None, refusal(ORDER_MESSAGE)
    missing = deepcopy(normal)
    missing["create"]["primaryKey"] = {"keys": ["absent"], "included": [], "name": None, "timing": "NOT DEFERRABLE"}
    yield missing, None, refusal("A primary key reference must select a declared column.")


def requests(engine, tag, spec):
    for ordinal, point in enumerate(grid(spec)):
        item = recipe(tag, *point, coordinate=list(point))
        yield f"finite/{ordinal}", analyzed(item), item, None
        yield f"direct/{ordinal}", direct(item), item, None
    cases = list(edges(tag, spec))
    same(spec["finite"]["requiredEdges"], [*(name for name, _ in cases), "1601-rejected"])
    for name, item in cases:
        yield f"edge/{name}/analysis", analyzed(item), item, None
        yield f"edge/{name}/direct", direct(item), item, None
    settings = spec["exploration"]
    for seed in settings["seeds"]:
        for ordinal, item in enumerate(generated(tag, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{ordinal}", analyzed(item), item, None
            yield f"direct-seed/{seed}/{ordinal}", direct(item), item, None
    negatives = list(refusals(tag, spec))
    same(negative_count(spec, engine, tag), len(negatives))
    for ordinal, (request, item, error) in enumerate(negatives):
        yield f"negative/{ordinal}", request, item, error


def check_value(item, response):
    require(response.get("status") == "constructed", "postgres-table-width-admission", response)
    profile = item["release"] + "/utf8/standard-conforming-strings/native"
    if item["compose"]:
        same(profile, response["profile"])
        same([item["member"]], response["schemaTables"])
        same({"name": NS, "owner": {"session": "CURRENT_USER"}, "ifNotExists": False}, response["createSchema"])
        for phase in ("schemaSequences", "schemaViews", "schemaIndexes", "schemaGrants"):
            same([], response[phase])
    else:
        same(profile + "/heap", response["profile"])
        same(direct(item)["create"], response["create"])


def prepare(_, db):
    return db.request({"fixture": True})


def check_preparation(_, _env, response):
    same({"status": "fixture", "blockSize": 8192, "heap": "heap"}, response)


def observer_request(item, command):
    return {"item": item, "sql": command}


def observation(response):
    return response


def check_observation(item, actual, command):
    model = expected(item)
    witness = {"expected": model, "observed": actual, "sql": command}
    require(actual.get("status") == STATUS, "postgres-table-width-observer-scope", witness)
    require({key: actual[key] for key in model} == model, "meaning-mismatch", witness)
    oid, prior = actual["relationOid"], actual["priorOid"]
    error = actual["error"]
    if model["sqlState"] == "00000":
        require(type(oid) is int and oid > 0 and error is None and (oid == prior if item["prior"] else prior is None),
                "meaning-mismatch", witness)
    else:
        require(oid is None and prior is None and error is not None and error[:2] == ["ERROR", model["sqlState"]],
                "meaning-mismatch", witness)


def observe_rejection(_, item, actual, command):
    same(sql(item), command)
    check_observation(item, actual, command)
    return item["negative"]


def rejected_finite_ids(_, _tag):
    return {"1601-rejected"}


def sample_forms(forms, response, _engine, _item):
    model = response["create"]
    fields = model["columns"]
    forms[f"nonempty/{bool(fields)}"] += 1
    forms[f"wide/{len(fields) > 128}"] += 1
    forms[f"guard/{model['ifNotExists']}"] += 1
    for column in fields:
        forms[f"kind/{column[1]}"] += 1
        forms[f"default/{column[3] is not None}"] += 1


def required_forms(*_):
    return {*(f"{option}/{enabled}" for option in ("nonempty", "wide", "guard", "default") for enabled in (False, True)),
            *(f"kind/{kind}" for kind in TYPES)}


def controls():
    item = recipe("pg-17.2", 0, "bigint", False)
    good = {**expected(item), "error": None, "relationOid": 13521, "priorOid": None}
    check_observation(item, good, sql(item))
    output = deepcopy(good)
    output["writes"]["rows"] = []
    try:
        check_observation(item, output, sql(item))
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"dropped-zero-width-tuples": error.detail}
    raise Failure("postgres-table-width-ignored-empty-tuples")


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    same(STATUS, witness["observed"]["status"])
    require(witness["observed"]["sqlState"] == "00000", "postgres-table-width-fault-must-execute", witness)


def faults(_, tag):
    path = "sql-semantics-postgres/src/Ir/Schema/CreateTable.php"
    empty = recipe(tag, 0, "integer", False)
    wide = recipe(tag, 129, "integer", False)
    cases = (
        ("fabricate", empty, "implode(', ', $columns)", """($columns === [] ? '"fabricated" INTEGER NULL' : implode(', ', $columns))"""),
        ("last", wide, "implode(', ', $columns)", "implode(', ', array_slice($columns, 0, -1))"),
        ("first-type", wide, "$columns[] = $column->toString();",
         "$columns[] = $columns === [] ? str_replace(' INTEGER', ' BIGINT', $column->toString()) : $column->toString();"),
    )
    return [RenderFault(f"native-width-{mode}", path, before, patch, {**item, "faultMode": mode}, "meaning-mismatch", direct(item))
            for mode, item, before, patch in cases]


if __name__ == "__main__":
    require(FIXTURE_CONTAINER in os.environ, "postgres-table-width-disposable-service-required")
    main(sys.modules[__name__])
