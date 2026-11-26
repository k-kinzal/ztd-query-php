"""Verify PostgreSQL partitioned-index rebuilds and the exact selected index lineage."""

import json
from itertools import product
from random import Random
import sys

from evidence_transcript import canonical
from postgres_create_btree_cases import refusal
from postgres_reindex_cases import CONCURRENT, FORMS, qualified
from postgres_reindex_partition_index_cases import STATUS, TARGETS, analyzed, expected, trial
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_drop_table import specifications as drop_contract
from run_server_join import main, probe
from run_server_postgres_create_btree import check_preparation, observation, observer_request, prepare
from run_server_postgres_reindex_table import snapshot
from sqlite_domain import RenderFault

LABEL = "postgres-reindex-partition-index"
CONTRACT_NAME = "postgres-reindex-partition-index-v1.json"
CONTRACT = "d40f9d750809a35275ed69631b74ff9e59132c96ffbcfc178a039a8d57310bb0"
OBSERVER = "postgres_reindex_partition_index_observer.py"
AXES = ("targets", "concurrently", "active", "spellings")
ROLE = "postgres-partition-index-value"


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "postgres-partition-index-contract")
    spec = json.loads(path.read_text())
    require(spec["exploration"]["allowedExclusions"] == [], "postgres-partition-index-exclusions")
    spec["additionalPostgreSqlNegatives"] = ["Unresolved index in a namespace with generated partition index names"]
    return spec, {"postgres": drop_contract()[1]["postgres"]}


def finite_ids(_, spec, _tag):
    model = {(target, requested, active, spelling) for target in TARGETS for requested in (False,True)
             for active in (False,True) for spelling in ("qualified", "local")}
    require(len(model) == 40 and model == set(product(*(spec["finite"][name] for name in AXES))),
            "postgres-partition-index-grid")
    return model


def finite_id(_, result, item):
    coordinate = tuple(item["coordinate"])
    same(trial(item["release"], *coordinate)["reindex"], result["reindex"])
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
        form = rng.choice(tuple(FORMS))
        yield trial(tag, rng.choice(tuple(TARGETS)), form in CONCURRENT, bool(rng.getrandbits(1)),
                    rng.choice(("qualified", "local")), root=rng.choice(("orders", 'Orders."名"')), key=rng.choice(("root_key", 'key."名"')),
                    base=rng.choice((4, 34, 64)), form=form)


def negatives(tag):
    root = trial(tag, "root-nested", False, False, "qualified")
    leaf = trial(tag, "leaf-physical", False, False, "qualified")
    command = analyzed(root)
    empty = analyzed(trial(tag, "root-empty", False, False, "qualified"))
    yield {**empty, "sql": 'REINDEX INDEX "fixture"."unknown"'}, refusal("requires a declared index")
    yield {**command, "sql": 'REINDEX INDEX "other"."fixture"."root_key"'}, refusal("requires the matching current database")
    yield {**command, "sql": 'DROP INDEX "fixture"."root_key"'}, refusal("protected in the ordinary index profile")
    yield {**analyzed(leaf), "sql": 'DROP INDEX "fixture"."payload"'}, refusal("protected in the ordinary index profile")
    yield {**root, "reindex": {**root["reindex"], "target": ["pg_temp", "root_key", "orders"]}}, refusal("requires a persistent application schema")
    yield {**root, "reindex": {**root["reindex"], "target": ["fixture", "orders", "orders"]}}, refusal("require distinct relation names")
    yield {**command, "sql": 'REINDEX INDEX "fixture"."unknown"'}, refusal("cannot infer absence in an open index namespace")


def requests(_, tag, spec):
    for ordinal, axes in enumerate(product(*(spec["finite"][name] for name in AXES))):
        item = trial(tag, *axes, coordinate=list(axes))
        yield f"finite/{ordinal}", analyzed(item), item, None
        yield f"direct/{ordinal}", item, item, None
    for ordinal, form in enumerate(key for key in FORMS if key not in ("ordinary", "keyword")):
        item = trial(tag, "leaf-physical", form in CONCURRENT, True, "local", form=form)
        yield f"edge/{ordinal}", analyzed(item), item, None
    for seed in spec["exploration"]["seeds"]:
        for ordinal, item in enumerate(generated(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield f"seed/{seed}/{ordinal}", analyzed(item), item, None
    for ordinal, (request, reason) in enumerate(negatives(tag)):
        yield f"negative/{ordinal}", request, None, reason


def check_value(item, result):
    want = item["reindex"]
    require(result["status"] == "constructed" and result.get("reindex") == want
            and result["profile"] == item["release"] + "/utf8/standard-conforming-strings/native",
            ROLE, {"sql": result.get("sql"), "expected": want, "observed": result})


def check_observation(item, actual, sql):
    require(actual.get("status") == STATUS, "postgres-partition-index-unrelated-result", {"sql": sql, "actual": actual})
    want = expected(item)
    try:
        before, _ = snapshot(actual["before"], actual["before"])
        during = snapshot(actual["before"], actual["during"])
        settled, changed_settled = snapshot(actual["before"], actual["settled"])
        same(want, {**actual, "before": before, "during": None if during is None else during[0],
                    "settled": settled, "changedDuring": None if during is None else during[1], "changedSettled": changed_settled})
    except Failure as error:
        raise Failure("meaning-mismatch", {"sql": sql, "expected": want, "observed": actual,
                                          "difference": error.detail}) from error


def sample_forms(forms, result, _, item):
    for key in ("selector", "active", "spelling", "form"):
        forms[f"{key}/{item[key]}"] += 1
    forms[f"mode/{result['reindex']['mode']}"] += 1
    forms[f"kind/{result['reindex']['partitionKind']}"] += 1


def required_forms(*_):
    return {*(f"selector/{key}" for key in TARGETS), *(f"form/{key}" for key in FORMS),
            "kind/branch", "kind/leaf", "mode/standard", "mode/concurrently",
            "active/True", "active/False", "spelling/local", "spelling/qualified"}


def controls():
    item = trial("pg-17.2", "root-empty", False, True, "qualified")
    prediction = expected(item)
    raw = {}
    for ordinal, (key, shape) in enumerate(prediction["before"].items(), start=20):
        identifier = str(ordinal)
        raw[key] = {**{k: v for k, v in shape.items() if k not in ("hasToast", "indexes")},
                    "oid": identifier, "file": identifier, "toastOid": "0",
                    "indexes": [{**entry, "oid": identifier + "01", "file": identifier + "02"} for entry in shape["indexes"]]}
    healthy = {key: value for key, value in prediction.items() if not key.startswith("changed")}
    healthy.update(before=raw, settled=raw)
    check_observation(item, healthy, "standard partition rebuild in a transaction")
    forged = {**healthy, "sqlState": "00000"}
    try:
        check_observation(item, forged, "erased top-level requirement")
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"branch-requires-top-level": {"input": item, "witness": error.detail}}
    raise Failure("postgres-partition-index-comparator-accepted")


def fault_probe(item, response, db):
    probe(item, response, db, sys.modules[__name__])


def replay_fault(rule, record):
    check_value(rule.item, record["healthyResponse"])
    witness = record["detail"]
    try:
        check_value(rule.item, record["mutantResponse"])
        check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("postgres-partition-index-fault-not-replayed", rule.name)


def faults(_, tag):
    directory = "sql-semantics-postgres/src/Ir/Schema/Index/"
    root = directory + "ReindexIndex.php"
    ordinary = trial(tag, "root-nested", False, False, "qualified")
    concurrent = trial(tag, "branch-remote", True, False, "qualified")
    render = "return ($this->tablespace === null ? 'REINDEX ' . ($this->verbose ? '(VERBOSE) ' : '') : $this->tablespace->prefix($this->verbose)) . 'INDEX ' . ($this->mode === IndexRebuildMode::Concurrent ? 'CONCURRENTLY ' : '') . $this->target->quoted();"
    return [
        RenderFault("postgres-partition-index-publish-wrong-role", directory + "IndexTargetLowering.php",
                    "IndexDeclarationKind::PartitionBranch => new PartitionIndexReference($schema, $index, $table, PartitionRebuildKind::Branch)",
                    "IndexDeclarationKind::PartitionBranch => new PartitionIndexReference($schema, $index, $table, PartitionRebuildKind::Leaf)",
                    ordinary, ROLE, analyzed(ordinary)),
        RenderFault("postgres-partition-index-rebuild-just-one-child", root, render,
                    "return 'REINDEX INDEX " + qualified("fixture", "low_id_idx") + "';", ordinary, "meaning-mismatch"),
        RenderFault("postgres-partition-index-rebuild-unrelated-and-toast", root, render,
                    "return 'REINDEX TABLE " + qualified("fixture", "orders") + "';", ordinary, "meaning-mismatch"),
        RenderFault("postgres-partition-index-discard-mode", root, "$this->mode === IndexRebuildMode::Concurrent",
                    "false", concurrent, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
