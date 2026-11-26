"""Compare native MySQL foreign-key removal and mixed index group effects."""

from copy import deepcopy
import json
import sys

import mysql_drop_foreign_keys_cases as laws
from mysql_drop_index_cases import published_index_target
from mysql_drop_index_cases import check_removal_facts_contract
from run import TOOL, Failure, digest, require
from run_server import same
from run_server_join import main, negative_count
from run_server_mysql_drop_index import DIAGNOSTIC, specifications as native_profiles
from sqlite_domain import RenderFault

LABEL = "mysql-drop-foreign-keys"
OBSERVER = "mysql_drop_foreign_keys_observer.php"
NAME = "mysql-drop-foreign-keys-evidence-v2.json"
BASE = "fbd8846ede8f693d6d052821b159e577b0aec91bec2cf036f959d4fd529688bb"
CONTRACT = "ffc3d82b77cdb9b1f85941437743d720cbb4208946bf3602fd0240aca30996c3"
COMPARATOR_FAULTS = {
    "Hide an enforced row difference.": "ignore-row-enforcement",
    "Treat a partial failed legacy copy as atomic.": "ignore-legacy-copy-partial-effect",
}
MISSING = "Native foreign-key removal requires one resolved constraint on its owning table."
POLICY = "The algorithm and lock cannot remove native foreign keys on this table."
INDEX_POLICY = "The algorithm and lock cannot remove these native InnoDB indexes."


def specifications():
    base, current = TOOL / "mysql-drop-foreign-keys-v3.json", TOOL / NAME
    require(digest(base) == BASE and digest(current) == CONTRACT, "mysql-drop-foreign-keys-contract")
    ref, spec = json.loads(base.read_text()), json.loads(current.read_text())
    check_removal_facts_contract(spec)
    require(digest(TOOL / ref["extends"]) == ref["priorSha256"], "mysql-drop-foreign-keys-prior-contract")
    _, profiles = native_profiles()
    for field in ("finite", "exploration", "observations", "releases", "nameBoundaries"):
        same(ref[field], spec[field])
    same(ref["releases"], profiles["mysql"]["releases"])
    return spec, profiles


def finite_ids(_, spec, tag):
    coordinates = laws.finite_coords(spec)
    same(spec["finiteIdentities"], len(coordinates))
    admitted = {point for point in coordinates if laws.coordinate(tag, point)["violation"] is None}
    same(spec["admitted"][tag], len(admitted))
    return admitted


def pair(axis, name, model):
    item = {"model": model}
    direct = {"finite": "direct", "seed": "direct-seed", "authored": "direct-authored"}[axis]
    yield f"{axis}/{name}", laws.analyze(model), item, None
    yield f"{direct}/{name}", laws.direct(model), item, None


def diagnostic(model, route):
    violation = model["violation"]
    if violation == "temporary":
        return ("This MySQL release has no foreign-key removal target on a temporary table." if route == "analysis"
                else "The foreign-key action does not belong to this native table lifetime.")
    if violation == "repeated":
        return "This MySQL release cannot repeat a foreign-key removal."
    if violation == "missing":
        return MISSING
    if violation in {"copy-none", "temporary-inplace"} or model["policy"] == ["COPY", "NONE"]:
        return INDEX_POLICY if any(role == "index" for role, _ in model["clauses"]) else POLICY
    require(violation in {"dependency", "legacy-copy-dependency"}, "unregistered-native-foreign-key-refusal", model)
    return laws.BLOCKED


def refused(axis, name, model):
    routes = [("analysis", laws.analyze)]
    if model["violation"] != "missing":
        routes.append(("direct", laws.direct))
    for route, build in routes:
        identity = f"{axis}/{name}/{route}"
        yield f"negative/{identity}", build(model), {"model": model, "rejectionId": identity}, {
            "exception": DIAGNOSTIC, "message": diagnostic(model, route),
        }


LEXICAL_NAMES = {
    "empty": "", "space-suffix": "A ", "escaped-backtick": "a`b", "64-bmp": "é" * 64, "255-bmp": "a" * 255,
}


def lexical(spec, tag):
    cases = [("persistent", spec["nameBoundaries"]["persistent"])]
    if tag in laws.LEGACY:
        cases.append(("temporary-shadow", spec["nameBoundaries"]["legacyTemporary"]))
    for fixture, labels in cases:
        for label in labels:
            model = laws.model(tag, "one-constraint", "explicit symbol", fixture)
            if model["temporary"]:
                model["temporaryLabel"] = LEXICAL_NAMES[label]
            else:
                model["foreign"][0] = LEXICAL_NAMES[label]
            yield f"{fixture}-{label}", model


def authored(tag):
    for plan in ("one-constraint", "remaining-foreign-key", "retained-incoming"):
        yield f"{plan}-copy-none", laws.model(tag, plan, laws.NAMES[0], laws.FIXTURES[0], algorithm="COPY", lock="NONE")
        yield f"{plan}-copy-shared", laws.model(tag, plan, laws.NAMES[1], laws.FIXTURES[0], algorithm="COPY", lock="SHARED")
    yield "absent-persistent-symbol", laws.model(tag, "missing", laws.NAMES[0], laws.FIXTURES[0])
    if tag in laws.LEGACY:
        yield "ignored-absent-session-label", laws.model(tag, "missing", laws.NAMES[0], laws.FIXTURES[1])
        yield "temporary-inplace", laws.model(tag, "one-constraint", laws.NAMES[1], laws.FIXTURES[1], algorithm="INPLACE")


def malformed(tag):
    model = laws.model(tag, "constraint-then-index", laws.NAMES[0], laws.FIXTURES[0])
    yield "open-native-catalog", {**laws.analyze(model), "complete": False}, "Persistent index removal needs a complete native foreign-key catalog."
    normal = laws.direct(model)
    for name, actions, reason in (
        ("empty-actions", [], "Foreign-key removal requires an ordered nonempty action list."),
        ("nonlist-actions", {"1": normal["mysqlDropForeignKeys"]["actions"][0]}, "Foreign-key removal requires an ordered nonempty action list."),
        ("foreign-action-type", [{"raw": "DROP FOREIGN KEY fk"}], "Foreign-key removal requires a checked native action."),
        ("missing-constraint-role", normal["mysqlDropForeignKeys"]["actions"][1:], "The native action group needs at least one foreign-key removal."),
    ):
        yield name, {**normal, "mysqlDropForeignKeys": {**normal["mysqlDropForeignKeys"], "actions": actions}}, reason
    wrong_scope = deepcopy(normal)
    wrong_scope["mysqlDropForeignKeys"]["actions"][1]["index"]["owner"] = [laws.AUX, laws.CHILD, "table"]
    yield "foreign-index-owner", wrong_scope, "A mixed foreign-key and index removal requires one owning table and lifetime."
    wrong_scope = deepcopy(normal)
    wrong_scope["mysqlDropForeignKeys"]["actions"][0]["foreign"] = [None, "ignored-on-temporary"]
    yield "foreign-key-role-on-persistent-owner", wrong_scope, "The foreign-key action does not belong to this native table lifetime."
    for name, operand, reason in (
        ("raw-temporary-label", ["ignored", "ignored-on-temporary"], "Only a resolved persistent foreign-key action has a physical name."),
        ("unresolved-persistent-label", [None, "constraint"], "Only a resolved persistent foreign-key action has a physical name."),
        ("persistent-label-length", ["é" * 65, "constraint"], "A native foreign-key name has at most 64 characters."),
    ):
        wrong_name = deepcopy(normal)
        wrong_name["mysqlDropForeignKeys"]["actions"][0]["foreign"] = operand
        yield name, wrong_name, reason
    pure = laws.direct(laws.model(tag, "one-constraint", laws.NAMES[0], laws.FIXTURES[0]))
    yield "irrelevant-support-facts", {**pure, "mysqlIndexForeignKeys": normal["mysqlIndexForeignKeys"]}, "A foreign-key-only removal does not consume index requirements."


def plan(spec, tag):
    for coord in sorted(laws.finite_coords(spec)):
        model = laws.coordinate(tag, coord)
        name = "/".join(coord).replace(" ", "-")
        yield from pair("finite", name, model) if model["violation"] is None else refused("finite", name, model)
    for seed in spec["exploration"]["seeds"]:
        for index, model in enumerate(laws.explored(tag, seed, spec["exploration"]["casesPerSeed"])):
            name = f"{seed}/{index}"
            if model["violation"] is not None:
                yield from refused("seed", name, model)
                algorithm, lock = model["policy"]
                model = laws.model(tag, "one-constraint", model["style"], model["fixture"], model["offset"],
                                   False, algorithm, lock, model["ignore"])
                require(model["violation"] is None, "invalid-native-positive-control", model)
            yield from pair("seed", name, model)
    for name, model in authored(tag):
        yield from pair("authored", name, model) if model["violation"] is None else refused("authored", name, model)
    for name, model in lexical(spec, tag):
        yield from pair("authored", name, model)
    for name, request, reason in malformed(tag):
        yield f"negative/boundary/{name}", request, None, {"exception": DIAGNOSTIC, "message": reason}


def requests(engine, tag, spec):
    items = list(plan(spec, tag))
    same(negative_count(spec, engine, tag), sum(verdict is not None for _, _, _, verdict in items))
    yield from items


def rejected_finite_ids(spec, tag):
    return {item["rejectionId"] for _, _, item, verdict in plan(spec, tag) if verdict is not None and item is not None}


def rejection_sql(item, _):
    return laws.sql(item["model"])


def observe_rejection(_, item, actual, sql):
    require(item["model"]["violation"] is not None, "mysql-drop-foreign-keys-refusal-predicted-admitted")
    check_observation(item, actual, sql)
    return item["rejectionId"]


def check_value(item, actual):
    model = item["model"]
    require(actual["status"] == "constructed", "mysql-drop-foreign-keys-not-constructed", actual)
    same(model["release"] + laws.PROFILE, actual["profile"])
    root = laws.direct(model)["mysqlDropForeignKeys"]
    expected = [{**action, "index": published_index_target(action["index"])} if "index" in action else action
                for action in root["actions"]]
    same({**root, "actions": expected}, actual["mysqlDropForeignKeys"])


def coordinate_seen(actual, model):
    wire = actual["mysqlDropForeignKeys"]
    names = [value["foreign"][0] for value in wire["actions"] if "foreign" in value]
    style = model["style"] if names[0] is None else (
        laws.NAMES[1] if all(label.startswith(laws.CHILD + "_ibfk_") for label in names) else laws.NAMES[0])
    fixture = laws.FIXTURES[1] if wire["owner"][2] == "temporary-table" else laws.FIXTURES[0]
    indexes = [value["index"]["name"] for value in wire["actions"] if "index" in value]
    if indexes:
        if indexes == model["indexes"][1:]:
            plan_name = "remaining-foreign-key"
        elif model["incoming"]:
            # The independent DB observation confirms the incoming edge even when a
            # temporary table owns the operation and the edge is on the persistent twin.
            plan_name = "retained-incoming"
        else:
            plan_name = "index-then-constraint" if "index" in wire["actions"][0] else "constraint-then-index"
    elif len(names) == 1:
        plan_name = "one-constraint"
    else:
        plan_name = model["plan"] if names[0] is None else ("repeated" if len(set(names)) == 1 else "two-constraints")
    return plan_name, style, fixture


def analysis_finite_id(_, actual, item, query):
    same(laws.sql(item["model"], True), query)
    return coordinate_seen(actual, item["model"])


def direct_finite_id(_, actual, item, request):
    same(laws.direct(item["model"]), request)
    return coordinate_seen(actual, item["model"])


def required_forms(_, tag):
    forms = {"lifetime/table", "name/generated name", "name/explicit symbol", "ordinal/foreign-first",
             "ordinal/index-first", "action/constraint", "action/index", "native/probeX-admitted", "native/probeY-blocked",
             "policy/DEFAULT", "policy/INPLACE", "policy/COPY", "native/constraint-only-index-retained"}
    if tag in laws.LEGACY:
        forms.update(("lifetime/temporary-table", "action/ignored-on-temporary", "native/qualified-incoming-retained"))
    if tag == "mysql-5.6.51":
        forms.add("ignore/true")
    return forms


def sample_forms(forms, actual, _, item):
    model = item["model"]
    wire = actual["mysqlDropForeignKeys"]
    labels = [action["foreign"][0] for action in wire["actions"] if "foreign" in action]
    style = model["style"] if labels[0] is None else (
        laws.NAMES[1] if all(label.lower().startswith(laws.CHILD + "_ibfk_") for label in labels) else laws.NAMES[0])
    forms.update((f"lifetime/{wire['owner'][2]}", f"name/{style}", f"policy/{wire['policy'][0]}",
                  "ordinal/index-first" if "index" in wire["actions"][0] else "ordinal/foreign-first"))
    if wire["ignore"]:
        forms["ignore/true"] += 1
    forms.update(f"action/{action['foreign'][1]}" if "foreign" in action else "action/index" for action in wire["actions"])


def sample_native_forms(forms, actual, _, item):
    for column in ("X", "Y"):
        outcome = "admitted" if actual["probe" + column][0] == "executed" else "blocked"
        forms[f"native/probe{column}-{outcome}"] += 1
    if actual["after"]["base"] == actual["before"]["base"] and any(
        coordinate not in actual["after"]["edges"] for coordinate in actual["before"]["edges"]
    ):
        forms["native/constraint-only-index-retained"] += 1
    if any(coordinate[0] == laws.AUX for coordinate in actual["after"]["edges"]):
        forms["native/qualified-incoming-retained"] += 1


def observer_request(item, sql):
    model = item["model"]
    return {"sql": sql, "declarations": laws.declarations(model), "temporary": model["temporary"], "incoming": model["incoming"]}


def observation(actual):
    return actual


def expected_diagnostics(model):
    diagnostics = [["Warning", 1681]] if model["ignore"] else []
    if model["violation"] is not None:
        diagnostics.append(["Error", laws.REASONS[model["violation"]][2]])
    return diagnostics


def check_observation(item, actual, sql):
    model = item["model"]
    expected = laws.expected(model)
    witness = {"expected": expected, "observed": actual, "sql": sql}
    require(actual.get("status") == laws.STATUS, "mysql-drop-foreign-keys-unobserved", witness)
    require({key: actual.get(key) for key in expected} == expected, "meaning-mismatch", witness)
    diagnostics = actual["diagnostics"]
    require([note[:2] for note in diagnostics] == expected_diagnostics(model), "meaning-mismatch", witness)
    if model["violation"] == "legacy-copy-dependency":
        require("errno: 150" in diagnostics[-1][2], "meaning-mismatch", witness)


def prepare(_, db):
    item = {"model": laws.model("mysql-9.1.0", "one-constraint", laws.NAMES[0], laws.FIXTURES[0])}
    sql = laws.sql(item["model"])
    return [db.request(observer_request(item, command)) for command in
            (sql + "; SELECT 1", sql.replace(laws.CHILD, "other_table"), f"DROP TABLE {laws.table(laws.CHILD)}")]


def check_preparation(_, _environment, result):
    same([{"status": "outside-observer-scope"}] * 3, result)


def controls():
    result = {}
    positive = {"model": laws.model("mysql-9.1.0", "one-constraint", laws.NAMES[0], laws.FIXTURES[0])}
    partial = {"model": laws.model("mysql-5.7.44", "retained-incoming", laws.NAMES[0], laws.FIXTURES[0], algorithm="COPY")}
    for key, item in (("ignore-row-enforcement", positive), ("ignore-legacy-copy-partial-effect", partial)):
        command = laws.sql(item["model"])
        actual = {**laws.expected(item["model"]), "diagnostics": [note + ["errno: 150"] for note in expected_diagnostics(item["model"])]}
        check_observation(item, actual, command)
        mutant = deepcopy(actual)
        if key == "ignore-row-enforcement":
            mutant["probeY"] = ["executed", 1]
        else:
            mutant["after"]["edges"] = mutant["before"]["edges"]
        try:
            check_observation(item, mutant, command)
        except Failure as error:
            same("meaning-mismatch", error.signal)
            result[key] = error.detail
        else:
            raise Failure("mysql-drop-foreign-keys-control-missed", key)
    return result


def faults(_, tag):
    pure = laws.model(tag, "one-constraint", laws.NAMES[0], laws.FIXTURES[0])
    mixed = laws.model(tag, "constraint-then-index", laws.NAMES[0], laws.FIXTURES[0])
    directory = "sql-semantics-mysql/src/Ir/Schema/ForeignKey/"
    return [
        RenderFault("bind-wrong-physical-constraint", directory + "ForeignKeyRemoval.php",
                    "($this->name?->quoted() ?? '``')", "'`Fk_B0`'", {"model": pure}, "meaning-mismatch", laws.direct(pure)),
        RenderFault("omit-requested-index-removal", directory + "DropForeignKeys.php",
                    "$actions[] = $action instanceof ForeignKeyRemoval ?",
                    "if ($action instanceof IndexIdentity) { continue; }\n            $actions[] = $action instanceof ForeignKeyRemoval ?",
                    {"model": mixed}, "meaning-mismatch", laws.direct(mixed)),
    ]


def check_fault_witness(item, witness):
    predicted, actual = witness["expected"], witness["observed"]
    same(laws.expected(item["model"]), predicted)
    same(["executed"], actual["effect"])
    if item["model"]["plan"] == "one-constraint":
        require(predicted["probeX"] != actual["probeX"] and predicted["probeY"] != actual["probeY"], "missing-foreign-target-effect", witness)
    else:
        require(predicted["after"]["base"] != actual["after"]["base"], "missing-physical-key-effect", witness)
        same(predicted["after"]["edges"], actual["after"]["edges"])


if __name__ == "__main__":
    main(sys.modules[__name__])
