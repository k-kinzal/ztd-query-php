"""Check PostgreSQL owner-wide rebuilds on both stock native releases."""

import json
from itertools import product
from random import Random
import sys

from evidence_transcript import canonical
from postgres_create_btree_cases import refusal
from postgres_reindex_cases import CONCURRENT, qualified
from postgres_reindex_table_cases import LAYOUTS, NATIVE_FORMS, OWNERS, analyzed, expected, trial
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_drop_table import specifications as drop_contract
from run_server_join import main, probe
from run_server_postgres_create_btree import check_preparation, observation, observer_request, prepare
from sqlite_domain import RenderFault

LABEL = "postgres-reindex-table"
CONTRACT_NAME = "postgres-reindex-table-v2.json"
CONTRACT = "b0c19d7d6e033e2e9ee01097c2ad99ef174b159496ceb3a175199a2f093d30ca"
OBSERVER = "postgres_reindex_table_observer.py"
AXES = ("owner", "concurrently", "active", "layout", "spelling")
COMPARATOR_FAULTS = {
    "Accept a TOAST-only owner as a no-op when its TOAST index should have been rebuilt": "omit-toast-rebuild",
}


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-reindex-table-contract")
    spec = json.loads(path.read_text())
    require(spec["exploration"]["allowedExclusions"] == [], "postgres-reindex-table-exclusions")
    return spec, {"postgres": drop_contract()[1]["postgres"]}


def finite_ids(_, spec, _tag):
    coordinates = {(owner, concurrent, active, layout, spelling)
                   for owner in OWNERS for concurrent in (False, True) for active in (False, True)
                   for layout in LAYOUTS for spelling in ("qualified", "local")}
    require(len(coordinates) == 120 and coordinates == set(product(*(spec["finite"][name] for name in AXES))),
            "postgres-reindex-table-finite-size")
    return coordinates


def finite_id(_, response, item):
    coordinate = tuple(item["coordinate"])
    rebuilt = trial(item["release"], *coordinate, coordinate=item["coordinate"])
    require(response["reindexTable"] == rebuilt["reindexTable"],
            "postgres-reindex-table-finite-identity", coordinate)
    return coordinate


def direct_finite_id(engine, response, item, request):
    coordinate = finite_id(engine, response, item)
    rebuilt = trial(item["release"], *coordinate, coordinate=list(coordinate))
    require(canonical(rebuilt) == canonical(request), "postgres-reindex-table-direct-input", coordinate)
    return coordinate


def analysis_finite_id(engine, response, item, sql):
    coordinate = finite_id(engine, response, item)
    rebuilt = trial(item["release"], *coordinate, coordinate=list(coordinate))
    require(analyzed(rebuilt)["sql"] == sql, "postgres-reindex-table-analysis-input", coordinate)
    return coordinate


def generated(tag, seed, total):
    rng = Random(seed)
    for _ in range(total):
        form = rng.choice(tuple(NATIVE_FORMS))
        yield trial(
            tag, rng.choice(tuple(OWNERS)), form in CONCURRENT, bool(rng.getrandbits(1)),
            rng.choice(tuple(LAYOUTS)), rng.choice(("qualified", "local")),
            form=form, name=rng.choice(("orders", 'Orders "名"')), base=rng.choice((0, 20, -23, 2147483647)),
        )


def negatives(tag):
    direct = trial(tag, "persistent-table", False, False, "one-index", "qualified")
    standard = analyzed(direct)
    view = {"dialect": "postgres", "release": tag, "declarations": ['CREATE VIEW public.y AS SELECT 1 AS id'],
            "sql": "REINDEX TABLE public.y"}
    partitioned = {"dialect": "postgres", "release": tag,
                   "declarations": ["CREATE TABLE public.y (id integer) PARTITION BY RANGE (id)"],
                   "sql": "REINDEX TABLE public.y"}
    yield {**standard, "sql": 'REINDEX TABLE "fixture"."missing"'}, refusal("missing", "SemanticException")
    yield {**standard, "sql": 'REINDEX TABLE "other"."fixture"."orders"'}, refusal("needs a local schema and owner")
    yield view, refusal("materialized view", "SemanticException")
    yield partitioned, refusal("requires a nonpartitioned owner")
    for schema, kind, message in (
        ("pg_temp_14", "base-table", "requires an application schema or pg_temp"),
        ("pg_temp", "materialized-view", "Only base tables are temporary index owners"),
        ("pg_catalog", "base-table", "requires an application schema or pg_temp"),
    ):
        yield {**direct, "reindexTable": {**direct["reindexTable"], "target": [schema, "orders", kind]}}, refusal(message)
    yield {**standard, "sql": 'REINDEX (TABLESPACE pg_default) TABLE "fixture"."orders"'}, refusal("accepts only concurrency options")


def requests(_, tag, spec):
    for ordinal, coordinate in enumerate(product(*(spec["finite"][name] for name in AXES))):
        item = trial(tag, *coordinate, coordinate=list(coordinate))
        yield f"finite/{ordinal}", analyzed(item), item, None
        yield f"direct/{ordinal}", item, item, None
    for ordinal, form in enumerate(key for key in NATIVE_FORMS if key not in ("ordinary", "keyword")):
        item = trial(tag, "persistent-table", form in CONCURRENT, True, "toast-and-index", "local", form=form)
        yield f"edge/{ordinal}", analyzed(item), item, None
    for seed in spec["exploration"]["seeds"]:
        for ordinal, item in enumerate(generated(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{ordinal}", analyzed(item), item, None
    for ordinal, (request, reason) in enumerate(negatives(tag)):
        yield f"negative/{ordinal}", request, None, reason


def check_value(item, response):
    require(response["status"] == "constructed" and response["reindexTable"] == item["reindexTable"]
            and response["profile"] == item["release"] + "/utf8/standard-conforming-strings/native",
            "postgres-reindex-table-value", response)


def snapshot(before, later):
    if later is None:
        return None
    states, changes = {}, {}
    same(sorted(before), sorted(later))
    for schema, current in later.items():
        first = before[schema]
        same([first[key] for key in ("oid", "file", "toastOid")], [current[key] for key in ("oid", "file", "toastOid")])
        content = {key: value for key, value in current.items() if key not in ("oid", "file", "toastOid")}
        content["hasToast"] = current["toastOid"] != "0"
        changes[schema] = {}
        for group in ("indexes", "toastIndexes"):
            changes[schema][group] = []
            if group == "toastIndexes" and current[group]:
                same(["pg_toast_" + current["oid"] + "_index"], [entry["name"] for entry in current[group]])
            same([entry["name"] for entry in first[group]], [entry["name"] for entry in current[group]])
            fields = ("file", "oid", "name") if group == "toastIndexes" else ("file", "oid")
            content[group] = [{key: value for key, value in index.items() if key not in fields} for index in current[group]]
            for original, rebuilt in zip(first[group], current[group]):
                changes[schema][group].append([original[key] != rebuilt[key] for key in ("file", "oid")])
        states[schema] = content
    return states, changes


def check_observation(item, actual, sql):
    require(actual.get("status") == "table-rebuild", "postgres-reindex-table-unrelated-result", {"sql": sql, "actual": actual})
    wanted = expected(item)
    try:
        before, _ = snapshot(actual["before"], actual["before"])
        during = snapshot(actual["before"], actual["during"])
        settled, changed_settled = snapshot(actual["before"], actual["settled"])
        same(wanted, {**actual, "before": before, "during": None if during is None else during[0], "settled": settled,
                      "changedDuring": None if during is None else during[1], "changedSettled": changed_settled})
    except Failure as error:
        raise Failure("meaning-mismatch", {"expected": wanted, "observed": actual, "sql": sql,
                                          "difference": error.detail}) from error


def required_forms(*_):
    return {*(f"owner/{name}" for name in OWNERS), *(f"layout/{name}" for name in LAYOUTS),
            *(f"form/{name}" for name in NATIVE_FORMS),
            "spelling/qualified", "spelling/local", "active/True", "active/False",
            "mode/standard", "mode/concurrently", "runtime/00000", "runtime/25001"}


def sample_forms(forms, response, _, item):
    for field in ("owner", "layout", "spelling", "form", "active"):
        forms[f"{field}/{item[field]}"] += 1
    forms[f"mode/{response['reindexTable']['mode']}"] += 1
    forms[f"runtime/{expected(item)['sqlState']}"] += 1


def controls():
    item = trial("pg-17.2", "persistent-table", False, False, "toast-only", "qualified")
    reference = expected(item)

    def observed(phase):
        return {
            schema: {
                **{key: value for key, value in shape.items() if key != "hasToast"},
                "oid": "12" if schema == "fixture" else "14", "file": "12" if schema == "fixture" else "14",
                "toastOid": "24" if shape["hasToast"] else "0",
                "indexes": [{**index, "file": "16", "oid": "15"} for index in shape["indexes"]],
                "toastIndexes": [{**index, "name": "pg_toast_12_index", "file": "30" if phase == "before" else "32",
                                  "oid": "25"} for index in shape["toastIndexes"]],
            } for schema, shape in reference[phase].items()
        }

    good = {**{key: value for key, value in reference.items() if not key.startswith("changed")},
            **{phase: observed(phase) for phase in ("before", "during", "settled")}}
    check_observation(item, good, 'REINDEX TABLE "fixture"."orders"')
    stale = json.loads(json.dumps(good))
    stale["settled"]["fixture"]["toastIndexes"][0]["file"] = good["before"]["fixture"]["toastIndexes"][0]["file"]
    try:
        check_observation(item, stale, 'REINDEX TABLE "fixture"."orders"')
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"omit-toast-rebuild": {"input": item, "witness": error.detail}}
    raise Failure("postgres-reindex-table-comparator-accepted-omitted-toast")


def fault_probe(item, response, db):
    probe(item, response, db, sys.modules[__name__])


def replay_fault(rule, record):
    check_value(rule.item, record["healthyResponse"])
    check_value(rule.item, record["mutantResponse"])
    witness = record["detail"]
    try:
        check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("postgres-reindex-table-fault-not-replayed", rule.name)


def faults(_, tag):
    root = "sql-semantics-postgres/src/Ir/Schema/Index/ReindexTable.php"
    two = trial(tag, "persistent-table", False, False, "two-indexes", "qualified")
    active = trial(tag, "temporary-table", True, True, "toast-only", "qualified")
    return [
        RenderFault("postgres-table-reindex-wrong-owner", root, "$this->target->quoted()",
                    """'"decoy".' . $this->target->name->quoted()""", two, "meaning-mismatch"),
        RenderFault("postgres-table-reindex-lost-concurrency", root, "$this->mode === IndexRebuildMode::Concurrent",
                    "false", active, "meaning-mismatch"),
        RenderFault("postgres-table-reindex-first-index-only", root,
                    "return 'REINDEX TABLE ' . ($this->mode === IndexRebuildMode::Concurrent ? 'CONCURRENTLY ' : '') . $this->target->quoted();",
                    "return 'REINDEX INDEX " + qualified("fixture", "by_key") + "';", two, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
