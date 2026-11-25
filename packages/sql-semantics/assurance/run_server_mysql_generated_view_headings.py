"""Check generated persistent MySQL view headings and the native width boundary."""

from collections import Counter
import json
import sys

from evidence_transcript import canonical
import mysql_generated_view_heading_cases as law
from run import Failure, TOOL, digest, require
from run_server import rejects, same
from run_server_join import main
from run_server_mysql_create_view import TARGET
from run_server_mysql_create_view_output_names import (
    INVALID, MEANING, OBSERVER, check_preparation, observation, prepare,
    source_request, specifications as explicit_specification,
)
from run_server_mysql_signed_membership import refusal
from sqlite_domain import RenderFault

LABEL = "mysql-generated-view-headings"
CONTRACT_NAME = "mysql-generated-view-headings-v2.json"
CONTRACT = "e1f34fa97cc20a3557b20e1747af99ebef0c4e80ae42129e3ddbcd27e9e98511"
COMPARATOR_FAULTS = {"Ignore a known ordered result heading mismatch.": "changedGeneratedName"}
WIDTH = "A MySQL view permits at most 4096 result columns."
SELECT_WIDTH = "A SELECT with more than 1024 results must be an independent projection of at most 4097 signed literals."
ADMITTED = "mysql-generated-view-width-admitted"
GRAPH = "mysql-generated-view-graph"
DUPLICATE = {
    "exception": "SqlSemantics\\Platform\\MySql\\Model\\Diagnostic\\SemanticException",
    "message": "Duplicate column name dup.",
}


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "mysql-generated-view-contract")
    contract = json.loads(path.read_text())
    base, profiles = explicit_specification()
    for field in ("extends", "versionedFrom"):
        reference = contract[field]
        same(reference["sha256"], digest(TOOL / reference["path"]))
    same(base["profiles"], contract["profiles"])
    same([], contract["finite"]["allowedExclusions"])
    same([], contract["exploration"]["allowedExclusions"])
    return contract, profiles


def finite_ids(_, contract, _tag):
    listed = list(law.grid(contract))
    independent = law.independent_ids()
    require(len(listed) == len(independent) and set(listed) == independent,
            "mysql-generated-view-finite-definition")
    return independent


def finite_id(_, response, item):
    body = response["createView"]
    matches = [cell for cell in law.independent_ids()
               if law.structure(law.finite(item["tag"], *cell), observed=True)["createView"] == body]
    require(len(matches) == 1, "mysql-generated-view-finite-identity", matches)
    return matches[0]


def analysis_finite_id(engine, response, item, sql):
    same(law.command(item), sql)
    return finite_id(engine, response, item)


def direct_finite_id(engine, response, item, request):
    same(law.structure(item), request)
    return finite_id(engine, response, item)


def rejected_finite_ids(*_):
    return {"duplicate-fixed", "width"}


def native_bad_width(tag):
    return {**law.maximum(tag, 4097), "invalid": "width"}


def invalid_empty(tag):
    item = law.finite(tag, "empty-with-duplicate", "one", "written")
    view = law.structure(item)
    view["createView"]["query"]["columns"][0][1] = "not_requested"
    return view


def observe_rejection(_, item, actual, sql):
    role = item["invalid"]
    state, code, message = ("HY000", 1117, "Too many columns") if role == "width" else (
        "42S21", 1060, "Duplicate column name 'dup'")
    stage = "execute" if item["tag"] in law.LEGACY else "prepare"
    failure = {"status": "native-refusal", "sqlState": state, "code": code, "message": message,
               "warnings": [["Error", code, message]], "catalogExists": False}
    expected = {"native-prepared": {**failure, "phase": stage}, "text": {**failure, "phase": "execute"}}
    require(canonical(expected) == canonical(actual), "mysql-generated-view-native-negative-mismatch",
            {"input": item, "sql": sql, "expected": expected, "observed": actual})
    return role


def requests(_, tag, contract):
    for index, cell in enumerate(law.grid(contract)):
        item = law.finite(tag, *cell)
        yield f"finite/{index}", source_request(tag, law.command(item)), item, None
        yield f"direct/{index}", law.structure(item), item, None
    for width in (4095, 4096):
        item = law.maximum(tag, width)
        yield f"width/{width}/analyze", source_request(tag, law.command(item)), item, None
        yield f"width/{width}/direct", law.structure(item), item, None
    duplicate = {"tag": tag, "operands": [["17", "dup"], ["59", "dup"]], "invalid": "duplicate-fixed"}
    yield "negative/duplicate-fixed", source_request(tag, law.command(duplicate)), duplicate, DUPLICATE
    width = native_bad_width(tag)
    yield "negative/native-width", source_request(tag, law.command(width)), width, refusal(WIDTH)
    if tag in law.LEGACY:
        yield "negative/direct-width", law.structure(width), None, refusal(WIDTH)
    else:
        yield "negative/implicit-empty", invalid_empty(tag), None, refusal(INVALID)
    standalone = {**law.maximum(tag, 4097), "standalone": True}
    direct = law.standalone_structure(standalone)
    yield "standalone/analyze", source_request(tag, law.command(standalone)), standalone, None
    yield "standalone/direct", direct, standalone, None
    too_wide = law.standalone_structure(law.maximum(tag, 4098))
    yield "negative/standalone-width", too_wide, None, refusal(SELECT_WIDTH)
    yield "negative/distinct-wide", {**direct, "distinct": True}, None, refusal(SELECT_WIDTH)
    mixed = {**direct, "columns": [[["boolean", True], "test"], *direct["columns"][1:]]}
    yield "negative/nonliteral-wide", mixed, None, refusal(SELECT_WIDTH)
    combined = {"dialect": "mysql", "release": tag,
                "compound": {"left": direct, "right": direct, "operator": "UNION", "all": True}}
    yield "negative/compound-wide", combined, None, refusal("A compound can have at most 1024 result columns.")
    settings = contract["exploration"]
    for seed in settings["seeds"]:
        for index, item in enumerate(law.seeded(tag, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", source_request(tag, law.command(item)), item, None
        for index, item in enumerate(law.seeded(tag, seed + 1, settings["directCasesPerSeed"])):
            yield f"direct-seed/{seed}/{index}", law.structure(item), item, None


def check_value(item, response):
    if item.get("standalone"):
        prefix = item["tag"] + "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/"
        shape = law.structure(item, observed=True)["createView"]["query"]
        same("constructed", response["status"])
        same(prefix + ("materialized" if item["tag"] == "mysql-5.6.51" else "derived-merge=on"), response["profile"])
        same(shape, {key: response[key] for key in shape})
        return
    expected = law.structure(item, observed=True)["createView"]
    profile = item["tag"] + "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/view-definition"
    require(response["status"] == "constructed" and response["profile"] == profile and response["createView"] == expected,
            GRAPH, {"expected": expected, "actual": response})


def observer_request(item, sql):
    if item.get("standalone"):
        return {"sql": sql, "standaloneProjection": True}
    return {"sql": sql, "catalogColumns": True}


def check_observation(item, actual, sql):
    if item.get("standalone"):
        expected = law.standalone_expected(item)
        require(canonical(expected) == canonical(actual), MEANING, {"sql": sql, "expected": expected, "observed": actual})
        return
    expected = law.expected(item)
    detail = {"sql": sql, "expected": expected, "observed": actual}
    for mode in expected:
        record = actual[mode]
        require(record["status"] == "created", "mysql-generated-view-native-refusal", detail)
        require(isinstance(record.get("definitionSql"), str) and record["definitionSql"].startswith("CREATE ALGORITHM="),
                "mysql-generated-view-definition-unobserved", detail)
        effect = {key: value for key, value in record.items() if key != "definitionSql"}
        require(canonical(effect) == canonical(expected[mode]), MEANING, detail)


def sample_forms(forms: Counter, answer, _engine, item):
    shape = answer["createView"]
    names = shape["columns"]
    for label, (_, original) in zip(names, shape["query"]["columns"], strict=True):
        effective = original[-1] if isinstance(original, list) else original
        forms[f"renamed/{label != effective}"] += 1
        forms[f"empty/{not label}"] += 1
        forms[f"intrinsic/{isinstance(original, list) and original[0] == 'intrinsic-integer-name'}"] += 1
        forms[f"generated-prefix/{label.startswith(law.heading_prefix(item['tag']))}"] += 1
        forms[f"long-heading/{len(label) > 64}"] += 1
    forms[f"arity/{len(names)}"] += 1


def required_forms(_, tag):
    common = {"renamed/True", "renamed/False", "empty/True", "empty/False", "intrinsic/True", "intrinsic/False",
              "generated-prefix/True", "generated-prefix/False", "arity/3", "arity/4", "arity/5", "arity/6"}
    return common | ({"long-heading/True"} if tag in law.LEGACY else {"long-heading/False"})


def controls():
    item = law.finite("mysql-9.1.0", "same", "one", "written")
    correct = {mode: {**record, "definitionSql": "CREATE ALGORITHM=UNDEFINED VIEW fixture"}
               for mode, record in law.expected(item).items()}
    check_observation(item, correct, law.command(item))
    text = correct["text"]
    fields = [list(entry) for entry in text["fields"]]
    fields[1][0] = "incorrect".encode().hex()
    changed = {**correct, "text": {**text, "fields": fields}}
    try:
        check_observation(item, changed, law.command(item))
    except Failure as error:
        same(MEANING, error.signal)
        return {"changedGeneratedName": error.detail}
    raise Failure("mysql-generated-view-comparator-accepted-changed-name")


def faults(_, tag):
    src = "sql-semantics-mysql/src/"
    generated = law.finite(tag, "same", "one", "written")
    earlier = law.finite(tag, "fixed-later", "one", "written")
    width = native_bad_width(tag)
    if tag in law.LEGACY:
        removed = RenderFault(
            "discard-legacy-generated-heading", src + "Ir/Schema/View/CreateView.php", "$name->quoted()",
            "(new \\SqlSemantics\\Platform\\MySql\\Ir\\Identifier(substr($name->value, 0, 64)))->quoted()",
            law.finite(tag, "same", "padded-64", "written"), MEANING,
            law.structure(law.finite(tag, "same", "padded-64", "written")),
        )
    else:
        empty = law.finite(tag, "empty-with-duplicate", "one", "written")
        removed = RenderFault(
            "discard-inferred-generated-heading", src + "Ir/Schema/View/CreateView.php", "viewNames: $names",
            "viewNames: []", empty, "mysql-generated-view-native-refusal", law.structure(empty),
        )
    return [
        RenderFault("ignore-native-generated-heading", src + "Ir/Schema/View/ViewHeadingLowering.php",
                    "$assign->allocate($labels, $generated)", "$labels",
                    {**generated, "wrongRejection": "The view heading cannot repeat a column name."}, GRAPH,
                    source_request(tag, law.command(generated))),
        RenderFault("rename-fixed-heading", src + "Rules/TableDefinition/ViewColumns.php",
                    "$generated[$current] ? $current : ($generated[$earlier] ? $earlier : null)",
                    "$generated[$earlier] || $generated[$current] ? $current : null",
                    earlier, GRAPH, source_request(tag, law.command(earlier))),
        RenderFault("ignore-persistent-view-width", src + "Ir/Schema/View/CreateView.php", "count($columns) <= 4096",
                    "count($columns) <= 4097", width, ADMITTED, law.structure(width)),
        removed,
    ]


def validate_fault_response(item, answer):
    message = item.get("wrongRejection")
    sql = law.command(item)
    if answer["status"] == "constructed":
        try:
            check_value(item, answer)
        except Failure as error:
            same(GRAPH, error.signal)
            raise Failure(GRAPH, {**error.detail, "sql": sql}) from error
        return
    require(message is not None and answer == {"status": "rejected", **refusal(message)},
            "mysql-generated-view-unrelated-fault-rejection", answer)
    raise Failure(GRAPH, {
        "expected": law.structure(item, observed=True)["createView"], "actual": answer, "sql": sql,
    })


def check_width(item, response, original):
    same("width", observe_rejection(None, item, original, law.command(item)))
    if rejects(response, refusal(WIDTH)):
        return
    check_value(item, response)
    raise Failure(ADMITTED, {"sql": law.command(item), "original": original, "response": response})


def fault_probe(item, response, observer):
    if item.get("invalid") == "width":
        check_width(item, response, observer.request(observer_request(item, law.command(item))))
        return
    validate_fault_response(item, response)
    sql = response["sql"]
    check_observation(item, observer.request(observer_request(item, sql)), sql)


def replay_fault(rule, record):
    detail = record["detail"]
    item = rule.item
    if "invalid" in item:
        check_width(item, record["healthyResponse"], detail["original"])
    else:
        check_value(item, record["healthyResponse"])
    answer = record["mutantResponse"]
    try:
        if "invalid" in item:
            check_width(item, answer, detail["original"])
        else:
            validate_fault_response(item, answer)
            same(answer["sql"], detail["sql"])
            check_observation(item, detail["observed"], detail["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(detail, error.detail)
        return
    raise Failure("mysql-generated-view-fault-not-reproduced")


if __name__ == "__main__":
    main(sys.modules[__name__])
