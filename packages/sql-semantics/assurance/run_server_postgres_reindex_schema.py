"""Compare schema-wide rebuilds and their transaction effects on stock PostgreSQL."""

import json
from itertools import product
from random import Random
import sys

from evidence_transcript import canonical
from postgres_create_btree_cases import refusal
from postgres_reindex_cases import CONCURRENT, qualified
from postgres_reindex_schema_cases import DECOY, LAYOUTS, NATIVE_FORMS, SCOPES, analyzed, expected, trial
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_drop_table import specifications as drop_contract
from run_server_join import main, probe
from run_server_postgres_create_btree import check_preparation, observation, observer_request, prepare
from run_server_postgres_reindex_table import snapshot
from sqlite_domain import RenderFault

LABEL = "postgres-reindex-schema"
CONTRACT_NAME = "postgres-reindex-schema-v2.json"
CONTRACT = "68f45a1025c1116c904b0a4adabd7675b9f8d576a72112438492d6f6a16c5577"
OBSERVER = "postgres_reindex_schema_observer.py"
AXES = ("layouts", "concurrently", "active", "spellings")


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-reindex-schema-contract")
    spec = json.loads(path.read_text())
    require(spec["exploration"]["allowedExclusions"] == [], "postgres-reindex-schema-exclusions")
    return spec, {"postgres": drop_contract()[1]["postgres"]}


def finite_ids(_, spec, _tag):
    model = {(layout, concurrent, active, form) for layout in LAYOUTS for concurrent in (False,True)
             for active in (False,True) for form in SCOPES}
    require(len(model) == 32 and model == set(product(*(spec["finite"][name] for name in AXES))),
            "postgres-reindex-schema-grid")
    return model


def finite_id(_, result, item):
    coordinate = tuple(item["coordinate"])
    same(trial(item["release"], *coordinate)["reindexSchema"], result["reindexSchema"])
    return coordinate


def direct_finite_id(engine, result, item, request):
    coordinate = finite_id(engine, result, item)
    same(canonical(trial(item["release"], *coordinate, coordinate=list(coordinate))), canonical(request))
    return coordinate


def analysis_finite_id(engine, result, item, sql):
    coordinate = finite_id(engine, result, item)
    same(analyzed(trial(item["release"], *coordinate))["sql"], sql)
    return coordinate


def generated(tag, seed, total):
    rng = Random(seed)
    for _ in range(total):
        form = rng.choice(tuple(NATIVE_FORMS))
        schema = rng.choice(("fixture", "reports.scope", 'Reporting "名"'))
        spelling = rng.choice(SCOPES) if schema == "fixture" else "quoted"
        yield trial(tag, rng.choice(LAYOUTS), form in CONCURRENT, bool(rng.getrandbits(1)), spelling,
                    schema=schema, base=rng.choice((12, 40, 75)), form=form)


def negatives(tag):
    item = trial(tag, "ordinary", False, False, "unquoted")
    request = analyzed(item)
    missing = {**request, "sql": "REINDEX SCHEMA unresolved"}
    yield missing, refusal("requires a declared schema")
    yield {**missing, "complete": False}, refusal("requires a declared schema")
    for name in ("pg_temp", "pg_temp_42", "pg_catalog"):
        yield {**item, "reindexSchema": {"schema": name, "mode": "standard"}}, refusal("requires a persistent application namespace")
    yield {**request, "sql": "REINDEX (TABLESPACE pg_default) SCHEMA fixture"}, refusal("cannot move indexes to a tablespace")


def requests(_, tag, spec):
    for ordinal, axes in enumerate(product(*(spec["finite"][key] for key in AXES))):
        item = trial(tag, *axes, coordinate=list(axes))
        yield f"finite/{ordinal}", analyzed(item), item, None
        yield f"direct/{ordinal}", item, item, None
    for ordinal, form in enumerate(key for key in NATIVE_FORMS if key not in ("ordinary", "keyword")):
        item = trial(tag, "ordinary", form in CONCURRENT, False, "quoted", form=form)
        yield f"edge/{ordinal}", analyzed(item), item, None
    for seed in spec["exploration"]["seeds"]:
        for ordinal, item in enumerate(generated(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{ordinal}", analyzed(item), item, None
    for ordinal, (request, reason) in enumerate(negatives(tag)):
        yield f"negative/{ordinal}", request, None, reason


def check_value(item, result):
    require(result["status"] == "constructed" and result["reindexSchema"] == item["reindexSchema"]
            and result["profile"] == item["release"] + "/utf8/standard-conforming-strings/native",
            "postgres-reindex-schema-value", result)


def check_observation(item, actual, sql):
    require(actual.get("status") == "schema-rebuild", "postgres-reindex-schema-unrelated-result", {"sql": sql, "actual": actual})
    want = expected(item)
    try:
        before, _ = snapshot(actual["before"], actual["before"])
        settled, changed = snapshot(actual["before"], actual["settled"])
        same(want, {**actual, "before": before, "settled": settled, "changed": changed})
    except Failure as error:
        raise Failure("meaning-mismatch", {"sql": sql, "expected": want, "observed": actual, "difference": error.detail}) from error


def sample_forms(forms, result, _, item):
    for key in ("layout", "active", "spelling", "form"):
        forms[f"{key}/{item[key]}"] += 1
    forms[f"mode/{result['reindexSchema']['mode']}"] += 1
    forms["scope/dotted" if "." in result["reindexSchema"]["schema"] else "scope/ordinary"] += 1


def required_forms(*_):
    return {*(f"layout/{key}" for key in LAYOUTS), *(f"form/{key}" for key in NATIVE_FORMS),
            "active/True", "active/False", "spelling/quoted", "spelling/unquoted", "scope/dotted", "scope/ordinary",
            "mode/standard", "mode/concurrently"}


def controls():
    item = trial("pg-17.2", "split-partitions", True, False, "quoted")
    prediction = expected(item)

    def raw(phase):
        result = {}
        for number, (target, shape) in enumerate(prediction[phase].items(), start=20):
            stable = {key: value for key, value in shape.items() if key != "hasToast"}
            updates = prediction["changed"][target]
            groups = {}
            for name in ("indexes", "toastIndexes"):
                groups[name] = []
                for ordinal, entry in enumerate(shape[name]):
                    oid = str(100 * number + len(name) + ordinal)
                    change = phase == "settled" and updates[name][ordinal][0]
                    change_oid = phase == "settled" and updates[name][ordinal][1]
                    groups[name].append({**entry, "oid": oid + ("9" if change_oid else ""), "file": oid + ("8" if change else ""),
                                         **({"name": f"pg_toast_{number}_index"} if name == "toastIndexes" else {})})
            result[target] = {**stable, "oid": str(number), "file": str(number),
                              "toastOid": str(10 * number) if shape["hasToast"] else "0", **groups}
        return result

    healthy = {key: value for key, value in prediction.items() if key != "changed"}
    healthy.update(before=raw("before"), settled=raw("settled"))
    check_observation(item, healthy, "healthy native shape")
    corrupted = json.loads(json.dumps(healthy))
    target = qualified(DECOY, "right_leaf")
    corrupted["settled"][target]["indexes"][0]["file"] = "10001"
    try:
        check_observation(item, corrupted, "forged cross-schema leaf rebuild")
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"unselected-partition-remains": {"input": item, "witness": error.detail}}
    raise Failure("postgres-reindex-schema-comparator-accepted")


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
    raise Failure("postgres-reindex-schema-fault-not-replayed", rule.name)


def faults(_, tag):
    root = "sql-semantics-postgres/src/Ir/Schema/Index/Scope/ReindexSchema.php"
    ordinary = trial(tag, "ordinary", False, False, "quoted")
    concurrent = trial(tag, "split-partitions", True, False, "quoted")
    return [
        RenderFault("postgres-schema-reindex-decoy", root, "$this->schema->quoted()", """'"decoy"'""", ordinary, "meaning-mismatch"),
        RenderFault("postgres-schema-reindex-single-owner", root,
                    "return 'REINDEX ' . ($this->verbose ? '(VERBOSE) ' : '') . 'SCHEMA ' . ($this->mode === IndexRebuildMode::Concurrent ? 'CONCURRENTLY ' : '') . $this->schema->quoted();",
                    "return 'REINDEX TABLE ' . $this->schema->quoted() . '.\"orders\"';", ordinary, "meaning-mismatch"),
        RenderFault("postgres-schema-reindex-loses-concurrency", root, "$this->mode === IndexRebuildMode::Concurrent",
                    "false", concurrent, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
