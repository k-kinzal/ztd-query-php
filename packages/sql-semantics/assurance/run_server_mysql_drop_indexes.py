"""Verify the construction and native atomic effects of MySQL index-removal sets."""

from copy import deepcopy
import json
import sys

import run_server_mysql_drop_index as native
from mysql_alter_index_cases import GRAMMAR_ERROR, IGNORED_WARNING, INVALID, LEXICAL
from mysql_drop_indexes_cases import (
    ROLE_CASES, STATUS, analyze, backing_case, coordinate, declarations, finite_points, generated,
    mutation_model, native_negatives, native_rows, policy_cases, prediction, sql,
)
from mysql_drop_index_cases import LIFETIMES
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main, negative_count
from sqlite_domain import RenderFault

LABEL = "mysql-drop-indexes"
OBSERVER = "mysql_drop_indexes_observer.php"
NAME = "mysql-multiple-index-removal-evidence-v1.json"
BASE = "c7eac6a3f3fefd93b7e6a642786a0ad96c59ea14021c5e4ed07222874fa07445"
CONTRACT = "1309c74f71d04c1926a6de8fcb3f7b2dbb9710a2ad6af5a7152c34d8510b83ec"
PROFILE = "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/drop-index-set"
COMPARATOR_FAULTS = {"Accept a partial native catalog effect.": "partial-native-removal"}


def specifications():
    base, record = TOOL / "mysql-multiple-index-removal-v1.json", TOOL / NAME
    require(digest(base) == BASE and digest(record) == CONTRACT, "mysql-index-removal-set-contract")
    spec, fixed = json.loads(record.read_text()), json.loads(base.read_text())
    same(fixed["finite"], spec["finite"])
    same(fixed["finite"]["timeoutSecondsPerRelease"], spec["exploration"]["timeoutSecondsPerRelease"])
    same([], spec["exploration"]["allowedExclusions"])
    return spec, native.specifications()[1]


def finite_ids(_, spec, _tag):
    points = finite_points(spec["finite"])
    same(spec["finiteLayouts"], len(points))
    return points


def planned(model, point=None, error=None, native_id=None):
    return {"model": model, "point": point, "error": error, "nativeId": native_id}


def both(group, ident, model, point=None):
    item = planned(model, point)
    yield f"{group}/{ident}", analyze(model), item, None
    direct = {"finite": "direct", "seed": "direct-seed"}.get(group, group + "-direct")
    yield f"{direct}/{ident}", model, item, None


def requests(engine, tag, spec):
    for point in sorted(finite_ids(engine, spec, tag)):
        yield from both("finite", "/".join(map(str, point)), coordinate(tag, point), point)
    for seed in spec["exploration"]["seeds"]:
        for ordinal, model in enumerate(generated(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield from both("seed", f"{seed}/{ordinal}", model)
    rejected = 0
    for name, model, error, message in policy_cases(tag):
        if error is None:
            yield from both("edge", name, model)
            continue
        item = planned(model, error=error, native_id=name)
        exception = LEXICAL if error == GRAMMAR_ERROR else INVALID
        yield "negative/analysis/" + name, analyze(model), item, {"exception": exception, "message": message}
        direct = "Only MySQL 5.6.51 admits ALTER IGNORE TABLE." if error == GRAMMAR_ERROR else message
        yield "negative/direct/" + name, model, None, {"exception": INVALID, "message": direct}
        rejected += 2
    for name, model, error, message in native_negatives(tag):
        yield "negative/analysis/" + name, analyze(model), planned(model, error=error, native_id=name), {"exception": INVALID, "message": message}
        rejected += 1
    for name, model, message in invalid_direct(tag):
        yield "negative/direct/" + name, model, None, {"exception": INVALID, "message": message}
        rejected += 1
    same(negative_count(spec, engine, tag), rejected)


def invalid_direct(tag):
    original = mutation_model(tag)
    example = original["mysqlDropIndexes"]["targets"]
    for name, count, message in (("none", 0, "ordered list of at least two"), ("single", 1, "ordered list of at least two"),
                                 ("65", 65, "at most 64 physical keys")):
        model = deepcopy(original)
        model["mysqlDropIndexes"]["targets"] = [{**example[0], "name": f"physical_{i}"} for i in range(count)]
        yield name, model, message
    for name, change, message in (
        ("mixed-lifetimes", {"owner": [*example[0]["owner"][:2], "table"]}, "one owning table and lifetime"),
        ("mixed-tables", {"owner": ["ir_fixture", "other", example[0]["owner"][2]]}, "one owning table and lifetime"),
        ("two-clustered", {"role": "clustered-unique"}, "at most one clustered index"),
        ("same-name-by-native-case", {"name": example[0]["name"].swapcase()}, "cannot repeat a physical key"),
    ):
        model = deepcopy(original)
        targets = model["mysqlDropIndexes"]["targets"]
        if name == "two-clustered":
            targets[0]["role"] = "clustered-unique"
        targets[1].update(change)
        yield name, model, message
    for name, change, message in (
        ("different-backing-set", {"field": "a", "alternates": ["PRIMARY", "unrelated"]}, "agree on one field"),
        ("different-generating-field", {"field": "b", "alternates": ["PRIMARY", "kept_support"]}, "agree on one field"),
        ("missing-backing-role", None, "identify the same AUTO_INCREMENT dependence"),
    ):
        model = backing_case(tag, "persistent", True)
        model["mysqlDropIndexes"]["targets"][1]["autoIncrement"] = change
        yield name, model, message


def check_value(item, response):
    model = item["model"]
    require(response["status"] == "constructed", "mysql-index-removal-set-constructed", response)
    same(model["release"] + PROFILE, response["profile"])
    same(model["mysqlDropIndexes"], response["mysqlDropIndexes"])


def coordinate_seen(response, item):
    model, received = item["model"], response["mysqlDropIndexes"]
    targets = received["targets"]
    same(["temporary-table" if model["life"] != "persistent" else "table"] * len(targets), [target["owner"][2] for target in targets])
    roles = {target["role"] for target in targets}
    role = "explicit-primary-and-secondary" if "primary" in roles else "clustered-unique-and-secondary" if "clustered-unique" in roles else "secondary-only"
    source_order = {key["name"]: offset for offset, key in enumerate(model["keys"])}
    first, last = source_order[targets[0]["name"]], source_order[targets[-1]["name"]]
    return model["life"], len(targets), role, "forward" if first < last else "reverse"


def analysis_finite_id(_, response, item, written):
    same(sql(item["model"], True), written)
    return coordinate_seen(response, item)


def direct_finite_id(_, response, item, request):
    same(item["model"], request)
    return coordinate_seen(response, item)


def rejected_finite_ids(_, tag):
    return {name for name, _, error, _ in (*policy_cases(tag), *native_negatives(tag)) if error is not None}


def observer_request(item, sql):
    model = item["model"]
    return {"sql": sql, "declarations": declarations(model), "generated": model["declaration"] != "plain",
            "rows": {"temporary": native_rows(model, True), "persistent": native_rows(model, False)}}


def observation(raw):
    return raw


def check_observation(item, actual, sql):
    model, error = item["model"], item["error"]
    expected = prediction(model, error)
    witness = {"expected": expected, "observed": actual, "sql": sql}
    require(actual.get("status") == STATUS, "mysql-index-removal-set-observer-scope", witness)
    require({key: actual[key] for key in expected} == expected, "meaning-mismatch", witness)
    if error:
        require(actual["affected"] is None and any(note[1] == error[2] for note in actual["diagnostics"]), "meaning-mismatch", witness)
    else:
        warnings = [IGNORED_WARNING] if model["mysqlDropIndexes"]["ignore"] else []
        require(type(actual["affected"]) is int and actual["affected"] >= 0 and actual["diagnostics"] == warnings, "meaning-mismatch", witness)


def observe_rejection(_, item, actual, sql):
    check_observation(item, actual, sql)
    return item["nativeId"]


def required_forms(_, _tag):
    return {*(f"namespace/{scope}" for scope in LIFETIMES), *(f"roles/{role}" for role in ROLE_CASES),
            "has-generating-field/True", "has-generating-field/False", "width/wide", "width/narrow"}


def sample_forms(forms, response, _, item):
    scope, size, roles, order = coordinate_seen(response, item)
    forms.update((f"namespace/{scope}", f"roles/{roles}", f"ordering/{order}",
                  f"has-generating-field/{item['model']['declaration'] != 'plain'}",
                  "width/wide" if size >= 8 else "width/narrow"))


def prepare(_, db):
    model = mutation_model("mysql-5.6.51")
    item = planned(model)
    outsiders = ("DROP TABLE ir_index_set", "ALTER TABLE unrelated DROP KEY a, DROP KEY b", sql(model) + "; SELECT 1")
    return {"outside": [db.request(observer_request(item, command)) for command in outsiders]}


def check_preparation(_, _environment, receipt):
    same({"outside": [{"status": "outside-observer-scope"}] * 3}, receipt)


def controls():
    model = mutation_model("mysql-9.1.0")
    item = planned(model)
    correct = {**prediction(model, None), "affected": 0, "diagnostics": []}
    check_observation(item, correct, sql(model))
    partial = deepcopy(correct)
    kept = model["mysqlDropIndexes"]["targets"][0]["name"]
    partial["after"]["indexes"].extend(row for row in correct["initial"]["indexes"] if row[0] == kept)
    try:
        check_observation(item, partial, sql(model))
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"partial-native-removal": error.detail}
    raise Failure("mysql-index-removal-set-missed-control")


def check_fault_witness(item, verdict):
    same(prediction(item["model"], None), verdict["expected"])
    same(["executed"], verdict["observed"]["effect"])


def faults(_, tag):
    model = mutation_model(tag)
    path = "sql-semantics-mysql/src/Ir/Schema/Index/DropIndexes.php"
    return [RenderFault("omit-physical-index-from-atomic-drop", path, "implode(', ', $actions)",
                        "implode(', ', array_slice($actions, 1))", planned(model), "meaning-mismatch", model)]


if __name__ == "__main__":
    main(sys.modules[__name__])
