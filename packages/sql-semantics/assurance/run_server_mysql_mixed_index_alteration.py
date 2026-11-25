"""Compare mixed MySQL index removal and rename as one catalog operation."""

from copy import deepcopy
from itertools import product
import json
import sys

import run_server_mysql_index_rename as renames
from mysql_alter_index_cases import INVALID
from mysql_drop_index_cases import ABSENT, LIFETIMES
from mysql_index_auto_increment_cases import LAST_SUPPORT, native_key
from mysql_index_policy_cases import refusal
from mysql_mixed_index_cases import (
    COMPOSITIONS, STATUS, analysis, coordinate, declarations, explored, finite_points, generating, kind, make,
    native_rows, physical, prediction, sql,
)
from run_server_mysql_create_btree_index import check_validation_contract
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main, negative_count
from sqlite_domain import RenderFault

LABEL = "mysql-mixed-index-alteration"
OBSERVER = "mysql_mixed_index_observer.php"
NAME = "mysql-mixed-index-alteration-evidence-v3.json"
BASE = "fcb33e154ba1f9da239a182f08dd70de8660f5e8cb4c382a0e8c92a66c30934c"
CONTRACT = "b9c0633048a26a9a38a9ab8994c1346e52073c7510b23a06e646007b156630fd"
PROFILE = "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/innodb/alter-indexes"
FIXTURE_RESET = {"mysql.innodb_index_stats": 0, "mysql.innodb_table_stats": 0}
POLICY = "The algorithm and lock cannot remove these native InnoDB indexes."
COMPARATOR_FAULTS = {"Ignore a surviving key that should have been removed.": "retained-removed-key"}


def specifications():
    base, rec = TOOL / "mysql-mixed-index-alteration-v1.json", TOOL / NAME
    require(digest(base) == BASE and digest(rec) == CONTRACT, "mysql-mixed-index-contract")
    spec, fixed = json.loads(rec.read_text()), json.loads(base.read_text())
    check_validation_contract(spec)
    same(fixed["finite"], spec["finite"])
    same(fixed["exploration"], spec["exploration"])
    same(fixed["supportedReleases"], spec["releases"])
    _, profiles = renames.specifications()
    same(profiles["mysql"]["releases"], spec["releases"])
    return spec, profiles


def finite_ids(_, spec, _tag):
    points = finite_points(spec["finite"])
    same(spec["finiteLayouts"], len(points))
    return points


def planned(model, point=None, *, error=None, native_id=None):
    return {"model": model, "point": list(point) if point else None, "error": error, "nativeId": native_id}


def both(group, ident, model, point=None):
    item = planned(model, point)
    yield f"{group}/{ident}", analysis(model), item, None
    direct = {"finite": "direct", "seed": "direct-seed"}.get(group, group + "-direct")
    yield f"{direct}/{ident}", model, item, None


def case(tag, life="persistent", clustered=False):
    keys = [native_key("renamed_b", "ordinary", ["b"]), native_key("unused_a", "ordinary", ["a","c"]),
            native_key("PRIMARY" if clustered else "remove_c", "primary" if clustered else "ordinary", ["a" if clustered else "c"])]
    return make(tag, life, keys, [("drop", keys[-1]["name"]), ("rename", keys[0]["name"], "new_b")])


def options(tag):
    for life, cluster, algorithm, lock in product(LIFETIMES, (False, True), ("DEFAULT", "INPLACE", "COPY"),
                                                 ("DEFAULT", "NONE", "SHARED", "EXCLUSIVE")):
        model = case(tag, life, cluster)
        model["mysqlMixedIndexes"]["policy"] = [algorithm, lock]
        role = "drop-primary" if cluster else "drop-ordinary"
        yield f"{life}/{cluster}/{algorithm}/{lock}", model, refusal(tag, role, life, algorithm, lock)


def invalid_native(tag):
    for name, change, error, msg in (
        ("rename-also-removed", ["rename", "REMOVE_C", "new_b"], renames.NATIVE["missing"], "cannot also be renamed or unmoved"),
        ("rename-absent-source", ["rename", "absent", "new_b"], renames.NATIVE["missing"], "requires one resolved ordinary index"),
        ("rename-occupied-destination", ["rename", "renamed_b", "UNUSED_A"], renames.NATIVE["duplicate"], "resulting native index names must be distinct"),
        ("drop-absent", ["drop", "absent"], ABSENT, "requires one resolved ordinary index"),
        ("drop-duplicate", ["drop", "REMOVE_C"], ABSENT, "cannot repeat a physical key"),
    ):
        model = case(tag)
        wrote = model["written"]
        if name == "drop-duplicate":
            wrote.insert(0, change)
        else:
            wrote[0 if name == "drop-absent" else 1] = change
        yield name, model, error, msg
    for life in LIFETIMES:
        yield "generating-exhausted/" + life, generating(tag, life, False), LAST_SUPPORT, "must retain a backing key"


def invalid_direct(tag):
    model = case(tag)
    actions = model["mysqlMixedIndexes"]["actions"]
    for name, update, expected in (
        ("empty-actions", {"actions": []}, "ordered list of two or more actions"),
        ("only-drop-actions", {"actions": [{**actions[0]}, {"drop": {**actions[0]["drop"], "name": "unused_a"}}]}, "at least one removal and one rename"),
        ("destination-collision", {"unmoved": ["new_b"]}, "resulting native index names must be distinct"),
        ("dropped-is-unmoved", {"unmoved": ["remove_c"]}, "cannot also be renamed or unmoved"),
        ("original-native-capacity", {"unmoved": [f"unused_{index}" for index in range(63)]}, "at most 64 physical keys"),
        ("different-owner", {"owner": ["ir_fixture", "same_scope_other_table", "table"]}, "one owning table and lifetime"),
        ("different-lifetime", {"owner": ["ir_fixture", "ir_index_set", "temporary-table"]}, "one owning table and lifetime"),
    ):
        candidate = deepcopy(model)
        candidate["mysqlMixedIndexes"].update(update)
        yield name, candidate, expected
    candidate = generating(tag, "persistent", True)
    candidate["mysqlMixedIndexes"]["actions"][0]["drop"]["autoIncrement"]["alternates"] = ["invented"]
    yield "unresolved-generating-support", candidate, "Every AUTO_INCREMENT backing name must belong to the original native namespace."


def requests(engine, tag, spec):
    for point in sorted(finite_ids(engine, spec, tag)):
        yield from both("finite", "/".join(map(str, point)), coordinate(tag, point), point)
    for seed in spec["exploration"]["seeds"]:
        for index, model in enumerate(explored(tag, seed, spec["exploration"]["casesPerSeed"])):
            yield from both("seed", f"{seed}/{index}", model)
    for life, direction in product(LIFETIMES, ("remove-first", "rename-first")):
        yield from both("edge", f"retains-generating/{life}/{direction}", generating(tag, life, True, direction=direction))
    if tag == renames.LEGACY:
        yield from both("edge", "legacy-qualifiers", {**case(tag), "qualify": True})
    rejected = 0
    for key, model, error in options(tag):
        if error is None:
            yield from both("policy", key, model)
            continue
        boundary = {"exception": INVALID, "message": POLICY}
        name = "policy/" + key
        yield "negative/analysis/" + name, analysis(model), planned(model, error=error, native_id=name), boundary
        yield "negative/direct/" + name, model, None, boundary
        rejected += 2
    for key, model, error, msg in invalid_native(tag):
        item = planned(model, error=error, native_id=key)
        yield "negative/analysis/" + key, analysis(model), item, {"exception": INVALID, "message": msg}
        rejected += 1
    for key, model, msg in invalid_direct(tag):
        yield "negative/direct/" + key, model, None, {"exception": INVALID, "message": msg}
        rejected += 1
    same(negative_count(spec, engine, tag), rejected)


def check_value(item, result):
    model = item["model"]
    require(result["status"] == "constructed", "mysql-mixed-index-constructed", result)
    same(model["release"] + PROFILE, result["profile"])
    change = model["mysqlMixedIndexes"]
    same({field: change[field] for field in ("owner", "actions", "policy")}, result["mysqlMixedIndexes"])


def seen(result, item):
    observed = result["mysqlMixedIndexes"]
    life = item["model"]["life"]
    same(observed["owner"][2], "temporary-table" if life != "persistent" else "table")
    actions = observed["actions"]
    return life, len(actions), kind(actions), "remove-first" if "drop" in actions[0] else "rename-first"


def analysis_finite_id(_, result, item, source):
    same(sql(item["model"], True), source)
    return seen(result, item)


def direct_finite_id(_, result, item, request):
    same(item["model"], request)
    return seen(result, item)


def rejected_finite_ids(_, tag):
    return {*(name for name, *_ in invalid_native(tag)), *("policy/" + key for key, _, err in options(tag) if err)}


def observer_request(item, sql):
    model = item["model"]
    return {"sql": sql, "declarations": declarations(model), "generated": model["declaration"] != "plain",
            "rows": {"temporary": native_rows(model, True), "persistent": native_rows(model, False)}}


def observation(raw):
    return raw


def warnings(model):
    removed, moved = physical(model)
    if any(key["role"] in {"primary", "clustered-unique"} for key in removed):
        return []
    names = {key["name"] for key in removed}
    remaining = [key for key in model["keys"] if key["name"] not in names]
    return renames.statistics_warnings({
        "keys": remaining, "life": model["life"],
        "mysqlIndexRenames": {"moves": moved, "policy": model["mysqlMixedIndexes"]["policy"]},
    })


def check_observation(item, actual, sql):
    model, error = item["model"], item["error"]
    expected = prediction(model, error)
    detail = {"expected": expected, "observed": actual, "sql": sql}
    require(actual.get("status") == STATUS, "mysql-mixed-index-observer-scope", detail)
    same(FIXTURE_RESET, actual.get("fixtureStats"))
    require({field: actual[field] for field in expected} == expected, "meaning-mismatch", detail)
    if error:
        require(any(note[1] == error[2] for note in actual["diagnostics"]), "meaning-mismatch", detail)
    else:
        require(type(actual["affected"]) is int and actual["diagnostics"] == warnings(model), "meaning-mismatch", detail)


def observe_rejection(_, item, actual, sql):
    check_observation(item, actual, sql)
    return item["nativeId"]


def required_forms(_, _tag):
    return {*(f"life/{life}" for life in LIFETIMES), *(f"composition/{role}" for role in COMPOSITIONS),
            "remove/single", "remove/multiple", "generation/True", "generation/False"}


def sample_forms(forms, observed, _, item):
    life, _, role, ordering = seen(observed, item)
    removed = sum("drop" in action for action in observed["mysqlMixedIndexes"]["actions"])
    forms.update((f"life/{life}", f"composition/{role}", f"order/{ordering}", "remove/multiple" if removed > 1 else "remove/single",
                  f"generation/{item['model']['declaration'] != 'plain'}"))


def outsiders():
    return ["ALTER TABLE other RENAME INDEX a TO b, DROP KEY c", "DROP TABLE ir_index_set",
            sql(case("mysql-8.0.44")) + "; SELECT 1"]


def prepare(_, db):
    item = planned(case("mysql-8.0.44"))
    return {"outside": [db.request(observer_request(item, command)) for command in outsiders()]}


def check_preparation(_, _environment, receipt):
    same({"outside": [{"status": "outside-observer-scope"}] * 3}, receipt)


def controls():
    model = case("mysql-8.0.44")
    item = planned(model)
    result = {**prediction(model, None), "diagnostics": warnings(model), "fixtureStats": FIXTURE_RESET}
    check_observation(item, result, sql(model))
    changed = deepcopy(result)
    retained = [key for key in result["initial"]["indexes"] if key[0] == "remove_c"]
    changed["after"]["indexes"] = sorted([*result["after"]["indexes"], *retained])
    try:
        check_observation(item, changed, sql(model))
    except Failure as error:
        same("meaning-mismatch", error.signal)
        return {"retained-removed-key": error.detail}
    raise Failure("mysql-mixed-index-comparator-control")


def check_fault_witness(item, verdict):
    same(prediction(item["model"], None), verdict["expected"])
    same(["executed"], verdict["observed"]["effect"])


def faults(_, tag):
    model = coordinate(tag, ("temporary-shadow", 3, "fresh-destination", "remove-first"))
    path = "sql-semantics-mysql/src/Ir/Schema/Index/Change/AlterIndexes.php"
    return [
        RenderFault("omit-mixed-index-removal", path, "implode(', ', $clauses)", "implode(', ', array_slice($clauses, 1))",
                    planned(model), "meaning-mismatch", model),
        RenderFault("omit-mixed-index-rename", path, "implode(', ', $clauses)", "implode(', ', array_slice($clauses, 0, -1))",
                    planned(model), "meaning-mismatch", model),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
