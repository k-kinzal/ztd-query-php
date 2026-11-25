"""Compare implicit InnoDB index names, ownership and runtime key effects."""

from copy import deepcopy
import json
import sys

import mysql_implicit_foreign_index_cases as laws
from mysql_drop_index_cases import published_single_removal
from mysql_drop_index_cases import check_removal_facts_contract
from run import TOOL, Failure, digest, require
from run_server import same
from run_server_join import main, negative_count
from run_server_mysql_drop_index import DIAGNOSTIC, specifications as native_profiles
from sqlite_domain import RenderFault

LABEL = "mysql-implicit-foreign-indexes"
OBSERVER = "mysql_implicit_foreign_index_observer.php"
NAME = "mysql-implicit-foreign-index-evidence-v2.json"
BASE = "00e718cae8ba4f042261fb13508dc501eab226f937d4faa74dbda4fe20fafade"
CONTRACT = "61163af1b695b1ea0c81861c7459ac3ecd3f3661a0dcd6d71831f059b7dd8bae"
COMPARATOR_FAULTS = {
    "Lose a physically present generated key in the copied catalog.": "ignore-native-generated-key",
    "Forget to observe the enforced native constraint.": "ignore-native-foreign-key",
}


def specifications():
    base, current = TOOL / "mysql-implicit-foreign-index-names-v1.json", TOOL / NAME
    require(digest(base) == BASE and digest(current) == CONTRACT, "mysql-implicit-foreign-index-contract")
    ref, plan = json.loads(base.read_text()), json.loads(current.read_text())
    check_removal_facts_contract(plan)
    _, profiles = native_profiles()
    same(ref["finite"]["names"], plan["finite"]["names"])
    same(ref["finite"]["coverage"], plan["finite"]["coverage"])
    same(ref["exploration"], plan["exploration"])
    same(ref["releases"], plan["releases"])
    same(ref["releases"], profiles["mysql"]["releases"])
    return plan, profiles


def finite_ids(_, spec, tag):
    grid = laws.finite_coords(spec)
    same(spec["finiteIdentities"], len(grid))
    expected = {point for point in grid if laws.coordinate(tag, point)["accepted"]}
    same(spec["admitted"][tag], len(expected))
    return expected


def pair(axis, name, model):
    item = {"model": model}
    direct_axis = {"finite": "direct", "seed": "direct-seed", "authored": "direct-authored"}[axis]
    yield f"{axis}/{name}", laws.analyze(model), item, None
    yield f"{direct_axis}/{name}", laws.direct(model), item, None


def refused(axis, name, model):
    for route, build in (("analysis", laws.analyze), ("direct", laws.direct)):
        identity = f"{axis}/{name}/{route}"
        yield f"negative/{identity}", build(model), {"model": model, "rejectionId": identity}, {
            "exception": DIAGNOSTIC, "message": laws.BLOCKED,
        }


def plan(spec, tag):
    for coord in sorted(laws.finite_coords(spec)):
        model = laws.coordinate(tag, coord)
        name = "/".join(coord).replace(" ", "-")
        yield from pair("finite", name, model) if model["accepted"] else refused("finite", name, model)
    for seed in spec["exploration"]["seeds"]:
        for index, model in enumerate(laws.explored(tag, seed, spec["exploration"]["casesPerSeed"])):
            name = f"{seed}/{index}"
            if not model["accepted"]:
                yield from refused("seed", name, model)
                model = laws.model(tag, model["style"], model["coverage"], "copied" if index % 2 else "unrelated",
                                   model["variant"], model["first"] == "Y")
            yield from pair("seed", name, model)

    positive = laws.model(tag, "both", "absent", "unrelated")
    illegal = laws.model(tag, "both", "absent", "required")
    if tag not in laws.LEGACY:
        yield from pair("authored", "implicit-index-keeps-foreign-key-while-hidden",
                        laws.model(tag, "both", "absent", "hide"))
    yield from refused("authored", "selected-generated-constraint", illegal)
    for name, request, reason in (
        ("unresolved-physical-index", {**laws.analyze(positive), "sql": laws.sql(positive).replace(laws.DECOY, "not_allocated")},
         "DROP INDEX requires one resolved ordinary index on its owning table."),
        ("open-native-catalog", {**laws.analyze(positive), "complete": False},
         "Persistent index removal needs a complete native foreign-key catalog."),
    ):
        yield f"negative/{name}", request, None, {"exception": DIAGNOSTIC, "message": reason}


def requests(engine, tag, spec):
    items = list(plan(spec, tag))
    same(negative_count(spec, engine, tag), sum(boundary is not None for _, _, _, boundary in items))
    yield from items


def rejected_finite_ids(spec, tag):
    return {item["rejectionId"] for _, _, item, boundary in plan(spec, tag) if item is not None and boundary is not None}


def rejection_sql(item, _):
    return laws.sql(item["model"])


def observe_rejection(_, item, actual, sql):
    require(not item["model"]["accepted"], "mysql-implicit-foreign-index-refusal-predicted-admitted")
    check_observation(item, actual, sql)
    return item["rejectionId"]


def check_value(item, actual):
    model = item["model"]
    field = laws.WIRES[model["operation"]]
    require(actual["status"] == "constructed", "mysql-implicit-foreign-index-construction", actual)
    same(model["release"] + laws.PROFILES[model["operation"]], actual["profile"])
    value = laws.direct(model)[field]
    same(published_single_removal(value) if field == "mysqlDropIndex" else value, actual[field])


def measured_action(actual):
    ready = actual.get("mysqlDropIndex")
    if ready is None:
        return "hide"
    if ready["owner"][1] == laws.COPY:
        return "copied"
    if ready["name"] == laws.DECOY:
        return "unrelated"
    return "required"


def analysis_finite_id(_, actual, item, query):
    model = item["model"]
    same(laws.sql(model, True), query)
    return model["style"], model["coverage"], measured_action(actual)


def direct_finite_id(_, actual, item, request):
    model = item["model"]
    same(laws.direct(model), request)
    return model["style"], model["coverage"], measured_action(actual)


def required_forms(_, tag):
    required = {"action/copied", "action/unrelated", "first/X", "first/Y", "physical/allocated", "physical/declared",
                "constraint/symbol", "constraint/native", "foreignKey/enforced"}
    if tag not in laws.LEGACY:
        required.add("action/hide")
    return required


def sample_forms(forms, actual, _, item):
    forms[f"action/{measured_action(actual)}"] += 1


def sample_native_forms(forms, actual, _, item):
    model = item["model"]
    rows = actual["before"]["source"]
    forms["physical/declared" if any(term[0].startswith("alternate_") and term[3] == model["first"] for term in rows) else "physical/allocated"] += 1
    name, _, field, *_ = actual["before"]["foreignKeys"][0]
    forms[f"first/{field}"] += 1
    forms["constraint/native" if name.startswith(laws.CHILD) else "constraint/symbol"] += 1
    forms["foreignKey/enforced"] += int(actual["foreignKeyEnforcement"] == ["db-error", "23000", 1452])


def observer_request(item, sql):
    return {"sql": sql, "declarations": laws.declarations(item["model"])}


def observation(actual):
    return actual


def check_observation(item, actual, sql):
    expected = laws.expected(item["model"])
    witness = {"expected": expected, "observed": actual, "sql": sql}
    require(actual.get("status") == laws.STATUS, "mysql-implicit-foreign-index-unobserved", witness)
    require({key: actual.get(key) for key in expected} == expected, "meaning-mismatch", witness)
    require(actual["diagnostics"] == [] if expected["effect"] == ["executed"] else
            any(note[:2] == ["Error", 1553] for note in actual["diagnostics"]),
            "meaning-mismatch", witness)


def outside():
    return ["DROP INDEX unrelated ON other_table", f"TRUNCATE TABLE {laws.table(laws.CHILD)}",
            f"DROP INDEX `X` ON {laws.table(laws.CHILD)}; SELECT 1"]


def prepare(_, db):
    item = {"model": laws.model("mysql-9.1.0", "both", "absent", "copied")}
    return [db.request(observer_request(item, sql)) for sql in outside()]


def check_preparation(_, _environment, result):
    same([{"status": "outside-observer-scope"}] * len(outside()), result)


def controls():
    item = {"model": laws.model("mysql-9.1.0", "both", "absent", "copied")}
    command = laws.sql(item["model"])
    actual = {**laws.expected(item["model"]), "diagnostics": []}
    check_observation(item, actual, command)
    a, b = deepcopy(actual), deepcopy(actual)
    a["before"]["copy"].pop()
    b["foreignKeyEnforcement"] = ["executed", 1]
    result = {}
    for label, mutant in (("ignore-native-generated-key", a), ("ignore-native-foreign-key", b)):
        try:
            check_observation(item, mutant, command)
        except Failure as error:
            same("meaning-mismatch", error.signal)
            result[label] = error.detail
        else:
            raise Failure("mysql-implicit-foreign-index-control-missed", label)
    return result


def check_fault_witness(item, witness):
    same(laws.expected(item["model"]), witness["expected"])
    same(["executed"], witness["observed"]["effect"])


def faults(_, tag):
    selected = laws.model(tag, "both", "absent", "copied")
    return [RenderFault("remove-copied-decoy-instead-of-generated-key", "sql-semantics-mysql/src/Ir/Schema/Index/DropIndex.php",
                        "$name = $this->target->name->quoted();", "$name = '`protect_y`';",
                        {"model": selected}, "meaning-mismatch", laws.direct(selected))]


if __name__ == "__main__":
    main(sys.modules[__name__])
