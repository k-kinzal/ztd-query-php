"""Validate the resolved AUTO_INCREMENT dependencies of native index removals."""

from copy import deepcopy
import json
import sys

import run_server_mysql_drop_index as native
from mysql_index_auto_increment_cases import (
    DIAGNOSTIC, RETAINS, ROLES, ROUTES, STATUS, analyze, command, declarations, finite_points, generated, model, native_rows, prediction,
)
from mysql_drop_index_cases import LIFETIMES
from mysql_innodb_declaration_width_cases import OLD
from mysql_drop_index_cases import check_removal_facts_contract
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main, negative_count
from sqlite_domain import RenderFault

LABEL = "mysql-index-auto-increment"
OBSERVER = "mysql_index_auto_increment_observer.php"
NAME = "mysql-index-auto-increment-evidence-v2.json"
BASE = "24552a62d52e5be756358f47ee5d3f2c24d1be923609e00ae265851fff89c201"
CONTRACT = "fb03cbf71cb3d58f42fe391399d241b9845ff9a47ca22a15bd2e2346f76023c4"
COMPARATOR_FAULTS = {
    "Ignore a changed generated identity.": "changed-generated-identity",
    "Ignore the disappearance of an alternate physical key.": "missing-retained-support",
}


def specifications():
    path = TOOL / NAME
    baseline = TOOL / "mysql-index-auto-increment-v1.json"
    require(digest(path) == CONTRACT and digest(baseline) == BASE, "mysql-index-auto-increment-contract")
    spec = json.loads(path.read_text())
    check_removal_facts_contract(spec)
    fixed = json.loads(baseline.read_text())
    same(fixed["finite"], spec["finite"])
    same(spec["finite"]["timeoutSecondsPerRelease"], spec["exploration"]["timeoutSecondsPerRelease"])
    same(spec["finite"]["allowedExclusions"], spec["exploration"]["allowedExclusions"])
    return spec, native.specifications()[1]


def finite_ids(_, spec, _tag):
    coordinates = finite_points(spec["finite"])
    same(spec["finiteLayouts"], len(coordinates))
    accepted = {point for point in coordinates if point[2] != "sole-leading"}
    same(spec["admittedLayouts"], len(accepted))
    return {(*point, route) for point in accepted for route in ROUTES[1:]}


def direct_finite_ids(_, spec, _tag):
    return {(*point, ROUTES[0]) for point in finite_points(spec["finite"]) if point[2] != "sole-leading"}


def wrapped(item, route, ident, refused=False):
    return {"model": item, "route": route, "id": ident, "failed": refused}


def requests(engine, tag, spec):
    rejected = 0
    for point in sorted(finite_points(spec["finite"])):
        item = model(tag, *point)
        failed = point[2] == "sole-leading"
        for route in ROUTES:
            key = "/".join((*point, route))
            request = item if route == "direct" else analyze(item, route)
            entry = wrapped(item, route, key, failed)
            if failed:
                rejected += 1
                yield f"negative/{key}", request, None if route == "direct" else entry, {"exception": DIAGNOSTIC, "message": RETAINS}
            else:
                yield ("direct/" if route == "direct" else "finite/") + key, request, entry, None
    for name, item in edges(tag):
        for route in ROUTES:
            request = item if route == "direct" else analyze(item, route)
            yield f"edge/{name}/{route}", request, wrapped(item, route, name), None
    for name, request, message in invalid_direct(tag):
        rejected += 1
        yield "negative/direct/" + name, request, None, {"exception": DIAGNOSTIC, "message": message}
    same(negative_count(spec, engine, tag), rejected)
    for seed in spec["exploration"]["seeds"]:
        for ordinal, item in enumerate(generated(tag, spec["finite"], seed, spec["exploration"]["casesPerSeed"])):
            route = ROUTES[1 + ordinal % 2]
            yield f"seed/{seed}/{ordinal}", analyze(item, route), wrapped(item, route, str(ordinal)), None
            yield f"direct-seed/{seed}/{ordinal}", item, wrapped(item, "direct", str(ordinal)), None


def edges(tag):
    yield "descending-alternate", model(tag, "persistent", "primary", "leading-with-distinct-alternate", "attribute", descending=True)
    yield "copied-temp-shadow-retains-support", model(tag, "temporary-shadow", "clustered-unique",
        "leading-with-distinct-alternate", "serial-type", clone=True, extra_alternates=2)
    if tag not in OLD:
        yield "invisible-alternate", model(tag, "persistent", "ordinary", "leading-with-distinct-alternate", "attribute", invisible=True)


def invalid_direct(tag):
    for label, value, reason in (
        ("repeated-alternate", ["supports", "SUPPORTS"], "distinct native identifiers"),
        ("target-is-not-an-alternate", ["CHOSEN"], "different physical key"),
        ("too-many-alternates", [f"key_{n}" for n in range(64)], "at most 63 alternate keys"),
        ("empty-field", ["supports"], "valid native name"),
    ):
        item = model(tag, "persistent", "ordinary", "leading-with-distinct-alternate", "attribute")
        item["mysqlDropIndex"]["autoIncrement"] = {"field": "" if label == "empty-field" else "a", "alternates": value}
        yield label, item, reason


def check_value(item, response):
    native.check_value(item, response)


def observed_point(response, item):
    shape, model_ = response["mysqlDropIndex"], item["model"]
    same("table" if model_["life"] == "persistent" else "temporary-table", shape["owner"][2])
    use = model_["use"]
    return model_["life"], shape["role"], use, model_["declaration"], item["route"]


def analysis_finite_id(_, response, item, sql):
    same(command(item["model"], route=item["route"]), sql)
    return observed_point(response, item)


def direct_finite_id(_, response, item, request):
    same(item["model"], request)
    return observed_point(response, item)


def rejected_finite_ids(spec, _tag):
    return {"/".join((*point, route)) for point in finite_points(spec["finite"]) if point[2] == "sole-leading" for route in ROUTES[1:]}


def observe_rejection(_, item, actual, sql):
    check_observation(item, actual, sql)
    return item["id"]


def observer_request(item, sql):
    shape = item["model"]
    return {"sql": sql, "declarations": declarations(shape),
            "rows": {"temporary": native_rows(shape, True), "persistent": native_rows(shape, False)}}


def observation(raw):
    return raw


def native_alternates(initial, target):
    generator = next((column[0] for column in initial["columns"] if column[4] == "auto_increment"), None)
    leading = [entry[0] for entry in initial["indexes"] if entry[2] == 1 and entry[3] == generator]
    return len(leading) - 1 if target in leading else None


def check_observation(item, actual, sql):
    predicted = prediction(item["model"], item["failed"])
    witness = {"expected": predicted, "observed": actual, "sql": sql}
    require(actual.get("status") == STATUS, "mysql-index-auto-increment-observer-scope", witness)
    require({key: actual[key] for key in predicted} == predicted, "meaning-mismatch", witness)
    count = native_alternates(actual["initial"], item["model"]["mysqlDropIndex"]["name"])
    measured = "unrelated" if count is None else "sole-leading" if count == 0 else "leading-with-distinct-alternate"
    same(item["model"]["use"], measured)
    if item["failed"]:
        require(actual["affected"] is None and any(note[1] == 1075 for note in actual["diagnostics"]), "meaning-mismatch", witness)
    else:
        require(type(actual["affected"]) is int and actual["affected"] >= 0 and actual["diagnostics"] == [], "meaning-mismatch", witness)


def required_forms(_, _tag):
    return {*(f"role/{role}" for role in ROLES), *(f"lifetime/{life}" for life in LIFETIMES),
            "dependence/unrelated", "dependence/one", "dependence/many", "clone/True", "clone/False"}


def sample_forms(forms, response, _, item):
    model_, actual = item["model"], response["mysqlDropIndex"]
    forms.update((f"role/{actual['role']}", f"lifetime/{model_['life']}",
                  f"clone/{model_['clone']}", f"declaration/{model_['declaration']}"))


def sample_native_forms(forms, actual, _, item):
    count = native_alternates(actual["initial"], item["model"]["mysqlDropIndex"]["name"])
    role = "unrelated" if count is None else "one" if count == 1 else "many"
    forms[f"dependence/{role}"] += 1


def witness(tag):
    target = model(tag, "temporary-shadow", "ordinary", "leading-with-distinct-alternate", "attribute")
    return wrapped(target, "direct", "retains-native-alternate")


def prepare(_, db):
    item = witness("mysql-5.6.51")
    outsider = [db.request(observer_request(item, sql)) for sql in (
        "DROP TABLE ir_index_sequence", "DROP INDEX a ON other_table", command(item["model"]) + "; SELECT 1")]
    return {"outside": outsider}


def check_preparation(_, _environment, receipt):
    same({"outside": [{"status": "outside-observer-scope"}] * 3}, receipt)


def controls():
    item = witness("mysql-9.1.0")
    expected = {**prediction(item["model"], False), "affected": 0, "diagnostics": []}
    sql = command(item["model"])
    check_observation(item, expected, sql)
    wrong_id = {**expected, "generated": "17"}
    missing_key = deepcopy(expected)
    missing_key["after"]["indexes"] = [row for row in expected["after"]["indexes"] if row[0] != "supports"]
    failures = {}
    for label, observed in (("changed-generated-identity", wrong_id), ("missing-retained-support", missing_key)):
        try:
            check_observation(item, observed, sql)
        except Failure as error:
            same("meaning-mismatch", error.signal)
            failures[label] = error.detail
        else:
            raise Failure("mysql-auto-increment-missed-control", label)
    return failures


def check_fault_witness(item, verdict):
    same(prediction(item["model"], False), verdict["expected"])
    same(["executed"], verdict["observed"]["effect"])


def faults(_, tag):
    item = witness(tag)
    path = "sql-semantics-mysql/src/Ir/Schema/Index/DropIndex.php"
    wrong_name = "'" + chr(96) + "supports" + chr(96) + "'"
    return [RenderFault("remove-retained-auto-increment-key", path, "$this->target->name->quoted()", wrong_name,
                        item, "meaning-mismatch", item["model"])]


if __name__ == "__main__":
    main(sys.modules[__name__])
