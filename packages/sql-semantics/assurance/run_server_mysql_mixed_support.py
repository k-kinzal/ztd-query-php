"""Check simultaneous MySQL foreign-key and explicit B-tree additions on the native server."""

from copy import deepcopy
import json
import sys

import mysql_mixed_support_cases as laws
from run import Failure, TOOL, digest, require
from run_server import same
from run_server_join import main, negative_count
from run_server_mysql_drop_index import DIAGNOSTIC, specifications as profiles
from sqlite_domain import RenderFault

LABEL = "mysql-mixed-support"
NAME = "mysql-mixed-support-evidence-v2.json"
CONTRACT = "071dac466128b4396c2d3decf7a08bec47b7ffe3cacaf3839d7739e27917ceca"
OBSERVER = "mysql_mixed_support_observer.php"
NAME_COLLISION = "An introduced foreign-key index needs an available physical name."
COMPARATOR_FAULTS = {
    "Ignore the direction of the ordered supporting columns.": "ignore-support-direction",
    "Ignore the exact allocated physical name.": "ignore-physical-name",
    "Discard the duplicate-definition notice preceding a physical name conflict.": "discard-native-preceding-notice",
}


def specifications():
    path = TOOL / NAME
    require(digest(path) == CONTRACT, "mysql-mixed-support-contract")
    specification = json.loads(path.read_text())
    for name, expected in specification["references"].items():
        require(digest(TOOL / name) == expected, "mysql-mixed-support-prior-contract", name)
    _, profile = profiles()
    same(profile["mysql"]["releases"], specification["releases"])
    same([], specification["exploration"]["allowedExclusions"])
    return specification, profile


def accepted(specification, tag, valid):
    points = {coordinate for coordinate in laws.finite(specification["finite"])
              if (laws.key_result(laws.model(tag, *coordinate))["collision"] is None) == valid}
    verify = specification["admitted"][tag]
    same(verify if valid else specification["finite"]["coordinates"] - verify, len(points))
    return points


def finite_ids(_, specification, tag):
    return accepted(specification, tag, True)


def rejected_finite_ids(specification, tag):
    return {"/".join(("finite", *point, route))
            for point in accepted(specification, tag, False) for route in ("analysis", "direct")}


def both(category, identity, value, outcome, boundary=None):
    item = {"model": value, "result": outcome}
    key = ({"finite": "direct", "seed": "direct-seed", "edge": "edge-direct"})[category]
    for route, label, request in (("analysis", category, laws.analyze(value)),
                                  ("direct", key, laws.direct(value, outcome))):
        if boundary is None:
            yield f"{label}/{identity}", request, item, None
        else:
            name = f"{category}/{identity}/{route}"
            yield f"negative/{name}", request, {**item, "identity": name}, {"exception": DIAGNOSTIC, "message": boundary}


def negatives(tag):
    value = laws.model(tag, "vacant", "unnamed-leading", "selected", "key-first")
    request = laws.direct(value, laws.key_result(value))
    path = ["mysqlAlterForeignKeys", "actions", 0, "index"]
    for label, field, replacement, message in (
        ("wrong-heading", "headingOwner", [laws.OWNER[0], "other", "table"], "exact declared owning table"),
        ("missing-field", "heading", [["spare", "int"]], "exact whole signed fields"),
        ("wrong-index-role", "role", "clustered-unique", "full explicit ordinary or unique"),
        ("implicit-index-as-explicit", "implicitForeignKey", True, "full explicit ordinary or unique"),
        ("invented-automatic-name", "name", "invented", "differs from native allocation"),
    ):
        current = deepcopy(request)
        current[path[0]][path[1]][path[2]][path[3]][field] = replacement
        yield label, current, message
    without_original = deepcopy(request)
    without_original["mysqlForeignKeyCatalog"]["keys"] = [
        key for key in request["mysqlForeignKeyCatalog"]["keys"] if key["owner"] != laws.OWNER]
    yield "no-clustered-owner", without_original, "requires an original clustered identity"
    mismatched = deepcopy(request)
    mismatched[path[0]][path[1]][path[2]][path[3]]["owner"] = [laws.OWNER[0], "other", "table"]
    yield "wrong-index-owner", mismatched, "must belong to the altered table"
    no_parent = deepcopy(request)
    no_parent["mysqlForeignKeyCatalog"]["keys"] = [
        key for key in request["mysqlForeignKeyCatalog"]["keys"] if key["owner"] == laws.OWNER]
    yield "missing-original-parent-key", no_parent, "requires original physical support on the resolved parent"


def positive_edges(tag):
    yield "drop-implicit-and-reapply", laws.model(tag, "implicit", "distinct", "long", "foreign-first", drop=True)
    yield "drop-explicit-and-keep-key", laws.model(tag, "explicit", "unnamed-leading", "selected", "key-first", drop=True)
    yield "wider-original-physical-direction", laws.model(tag, "occupied", "unnamed-descending", "long", "key-first", cross=True)


def requests(engine, tag, specification):
    refused = 0
    for point in laws.finite(specification["finite"]):
        value = laws.model(tag, *point)
        outcome = laws.key_result(value)
        message = NAME_COLLISION if outcome["collision"] is not None else None
        refused += 2 if message is not None else 0
        yield from both("finite", "/".join(point), value, outcome, message)
    for label, value in positive_edges(tag):
        outcome = laws.key_result(value)
        require(outcome["collision"] is None, "mysql-mixed-support-edge-inconsistent", label)
        yield from both("edge", label, value, outcome)
    for name, current, reason in negatives(tag):
        refused += 1
        yield f"negative/direct/{name}", current, None, {"exception": DIAGNOSTIC, "message": reason}
    same(negative_count(specification, engine, tag), refused)
    for seed in specification["exploration"]["seeds"]:
        for ordinal, value in enumerate(laws.explorations(tag, seed, specification["exploration"]["casesPerSeed"])):
            outcome = laws.key_result(value)
            require(outcome["collision"] is None, "mysql-mixed-support-seed-inconsistent", [tag, seed, ordinal])
            yield from both("seed", f"{seed}/{ordinal}", value, outcome)


def check_value(item, response):
    value, outcome = item["model"], item["result"]
    require(response.get("status") == "constructed", "mysql-mixed-support-not-constructed", response)
    same(value["tag"] + laws.PROFILE, response["profile"])
    same(laws.published(value, outcome), response["mysqlAlterForeignKeys"])


def analysis_finite_id(_, response, item, sql):
    same(laws.statement(item["model"]), sql)
    check_value(item, response)
    return tuple(item["model"]["coordinate"])


def direct_finite_id(_, response, item, request):
    same(laws.direct(item["model"], item["result"]), request)
    check_value(item, response)
    return tuple(item["model"]["coordinate"])


def required_forms(_, tag):
    return {"index-count/1", "index-count/2", "field-count/1", "field-count/2", "field-count/3",
            "automatic/True", "automatic/False", "unique/True", "unique/False", "order/index", "order/add",
            "replacement/True", "replacement/False", "parent/same", "parent/other", "composed-removal/True",
            "generated/True", "generated/False", "direction/False",
            *({"direction/True"} if tag not in laws.OLD else set())}


def sample_forms(forms, response, _, item):
    value = response["mysqlAlterForeignKeys"]
    actions = value["actions"]
    forms["order/" + next(iter(actions[0]))] += 1
    forms[f"replacement/{bool(value['removedIndexes'])}"] += 1
    forms[f"composed-removal/{any('drop' in action for action in actions)}"] += 1
    indexes = [action["index"] for action in actions if "index" in action]
    forms[f"index-count/{len(indexes)}"] += 1
    for index in indexes:
        forms[f"field-count/{len(index['fields'])}"] += 1
        forms[f"automatic/{index['automatic']}"] += 1
        forms[f"unique/{index['role'] == 'unique'}"] += 1
        for _, direction in index["fields"]:
            forms[f"direction/{direction}"] += 1
    addition = next(action["add"] for action in actions if "add" in action)
    forms[f"generated/{addition['generated']}"] += 1
    forms["parent/same" if addition["parent"][0] == value["owner"][0] else "parent/other"] += 1


def observer_request(item, sql):
    value = item["model"]
    return {"sql": sql, "parent": value["parent"][0], "declarations": laws.ddl(value)}


def observation(payload):
    return payload


def diagnostics(value, outcome):
    collision = outcome["collision"]
    severity = "Note" if value["tag"] == "mysql-5.6.51" else "Warning"
    namespace = [(role, fields) for name, role, fields, implicit in value["originals"]
                 if not implicit and name not in outcome["removed"]]
    warnings = []
    for position, current in enumerate(outcome["applied"]):
        if position == outcome["collisionAt"]:
            warnings.append(["Error", 1061, f"Duplicate key name '{collision}'"])
            break
        if current is None or current[3]:
            continue
        name, kind, fields, _ = current
        if (kind, fields) in namespace:
            warnings.append([severity, 1831, f"Duplicate index '{name}' defined on the table '{'.'.join(laws.OWNER[:2])}'. "
                             "This is deprecated and will be disallowed in a future release."])
        namespace.append((kind, fields))
    return warnings


def expected_observation(item):
    value, result = item["model"], item["result"]
    return {**laws.prediction(value, result), "diagnostics": diagnostics(value, result)}


def check_fault_witness(item, witness):
    same(expected_observation(item), witness["expected"])
    same("mysql-mixed-support", witness["observed"].get("status"))


def check_observation(item, actual, sql):
    expected = expected_observation(item)
    witness = {"expected": expected, "observed": actual, "sql": sql}
    require(actual.get("status") == "mysql-mixed-support", "mysql-mixed-support-not-observed", witness)
    require(actual == expected, "meaning-mismatch", witness)


def rejection_sql(item, _):
    return laws.statement(item["model"])


def observe_rejection(_, item, actual, sql):
    check_observation(item, actual, sql)
    return item["identity"]


def control_input(tag, point):
    value = laws.model(tag, *point)
    return {"model": value, "result": laws.key_result(value)}


def prepare(_, db):
    item = control_input("mysql-9.1.0", ("vacant", "distinct", "long", "key-first"))
    sql = laws.statement(item["model"])
    invalid = (sql + "; SELECT 1", sql.replace(laws.OWNER[1], "arbitrary"), sql.replace(laws.PARENT, "arbitrary"))
    return [db.request(observer_request(item, statement)) for statement in invalid]


def check_preparation(_, _environment, observed):
    same([{"status": "outside-observer-scope"}] * 3, observed)


def controls():
    item = control_input("mysql-9.1.0", ("vacant", "unnamed-descending", "selected", "key-first"))
    value, outcome = item["model"], item["result"]
    sql = laws.statement(value)
    valid = {**laws.prediction(value, outcome), "diagnostics": diagnostics(value, outcome)}
    check_observation(item, valid, sql)
    changed = {}
    for role, position, replacement in (("ignore-support-direction", 4, "A"), ("ignore-physical-name", 0, "wrong")):
        defect = deepcopy(valid)
        row = next(key for key in defect["after"]["keys"] if key[0] == "pid")
        row[position] = replacement
        try:
            check_observation(item, defect, sql)
        except Failure as error:
            same("meaning-mismatch", error.signal)
            changed[role] = error.detail
        else:
            raise Failure("mysql-mixed-support-ignores-fault", role)
    collision = control_input("mysql-5.6.51", ("explicit", "second-explicit", "generated", "key-first"))
    model, result = collision["model"], collision["result"]
    command = laws.statement(model)
    observed = {**laws.prediction(model, result), "diagnostics": diagnostics(model, result)}
    require(len(observed["diagnostics"]) == 2, "mysql-mixed-support-colliding-control-diagnostics")
    check_observation(collision, observed, command)
    omitted = {**observed, "diagnostics": observed["diagnostics"][1:]}
    try:
        check_observation(collision, omitted, command)
    except Failure as error:
        same("meaning-mismatch", error.signal)
        changed["discard-native-preceding-notice"] = error.detail
    else:
        raise Failure("mysql-mixed-support-ignores-fault", "discard-native-preceding-notice")
    return changed


def faults(_, tag):
    exchange = "sql-semantics-mysql/src/Ir/Schema/ForeignKey/Exchange/"
    ordering = control_input(tag, ("vacant", "same-automatic-fk-key", "generated", "key-first"))
    fields = control_input(tag, ("vacant", "unnamed-later", "selected", "key-first"))
    return [
        RenderFault("reverse-name-allocating-key-actions", exchange + "AlterForeignKeys.php",
                    "foreach ($this->actions as $action) {", "foreach (array_reverse($this->actions) as $action) {",
                    ordering, "meaning-mismatch", laws.direct(ordering["model"], ordering["result"])),
        RenderFault("reverse-leading-index-operands", exchange + "IndexAddition.php",
                    "foreach ($this->key->fields as $field) {", "foreach (array_reverse($this->key->fields) as $field) {",
                    fields, "meaning-mismatch", laws.direct(fields["model"], fields["result"])),
        RenderFault("omit-explicit-mixed-key", exchange + "AlterForeignKeys.php",
                    "$actions[] = $action->toString();", "if (!$action instanceof IndexAddition) { $actions[] = $action->toString(); }",
                    fields, "meaning-mismatch", laws.direct(fields["model"], fields["result"])),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
