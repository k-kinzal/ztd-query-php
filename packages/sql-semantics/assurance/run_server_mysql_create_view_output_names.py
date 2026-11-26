"""Check resolved view headings against persistent and client-visible effects."""

from collections import Counter
import json
import sys

from evidence_transcript import canonical
import mysql_create_view_output_name_cases as law
from mysql_create_view_projection_alias_cases import refusal_text
from mysql_signed_projection_alias_cases import STYLES, quote
from run import Failure, TOOL, digest, require
from run_server import rejects, same
from run_server_join import main
from run_server_mysql_create_view import TARGET
from run_server_mysql_create_view_projection_alias import (
    INVALID, MEANING, OBSERVER, check_preparation, observation, prepare, source_request,
    specifications as previous_specification,
)
from run_server_mysql_signed_membership import refusal
from sqlite_domain import RenderFault

LABEL = "mysql-create-view-output-names"
CONTRACT_NAME = "mysql-create-view-output-names-v2.json"
CONTRACT = "b46218c187468ced7737f36cf10240e2d9dbb0e5629400eb5c1458e09198830e"
COMPARATOR_FAULTS = {"Ignore native catalog metadata or a warning phase.": "separateCatalogAndWarningPhase"}
DUPLICATE = "The view heading cannot repeat a column name."
INCOMPLETE = "A checked MySQL view heading must persist valid UTF-8."
WIDTH = "A checked MySQL view field needs at most 256 bytes."
LEGACY_NAME = "A legacy view heading needs a native effective name or its requested alias."
ADMITTED = "mysql-view-output-name-invalid-admitted"
WHITESPACE = "\t\n\v\f\r "


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "mysql-view-output-name-contract")
    contract = json.loads(path.read_text())
    previous, profiles = previous_specification()
    for field in ("versionedFrom", "extends"):
        reference = contract[field]
        same(reference["sha256"], digest(TOOL / reference["path"]))
    same([profile.split("/", 1)[0] for profile in previous["profiles"]], contract["profiles"])
    same([], contract["finite"]["allowedExclusions"])
    same([], contract["exploration"]["allowedExclusions"])
    return contract, profiles


def finite_ids(_, contract, tag):
    listed = list(law.grid(contract, tag))
    independent = law.independent_ids(tag)
    release = "legacy" if tag in law.LEGACY else "modern"
    require(len(listed) == len(independent) == contract["finite"]["coordinatesPerRoute"][release]
            and set(listed) == independent, "mysql-view-output-finite-definition")
    return independent


def finite_id(_, response, item):
    actual = response["createView"]
    matches = [identity for identity in law.independent_ids(item["tag"])
               if law.structure(law.finite(item["tag"], *identity), observed=True)["createView"] == actual]
    require(len(matches) == 1, "mysql-view-output-finite-identity", matches)
    return matches[0]


def analysis_finite_id(engine, response, item, sql):
    same(law.command(item), sql)
    return finite_id(engine, response, item)


def direct_finite_id(engine, response, item, request):
    same(law.structure(item), request)
    return finite_id(engine, response, item)


def bad_name(tag, coordinate, name, *, body=False, duplicate=False):
    other = "é" if duplicate else "other"
    declared = ("visible", other) if body else (name, other)
    aliases = (name if body else "p", "q")
    header = ", ".join(quote(value, STYLES[0]) for value in declared)
    sql = (f"CREATE VIEW {TARGET} ({header}) AS SELECT "
           f"11 AS {quote(aliases[0], STYLES[0])}, -2147483649 AS q")
    return {"tag": tag, "negative": coordinate, "name": name, "sql": sql}


def negative_items(tag):
    for char in WHITESPACE:
        yield bad_name(tag, f"body-trailing-{ord(char):02x}", "edge" + char, body=True), INVALID
    yield bad_name(tag, "duplicate", "É", duplicate=True), DUPLICATE
    if tag in law.LEGACY:
        yield bad_name(tag, "partial-native-character", "漢" * 86), INCOMPLETE
    else:
        yield bad_name(tag, "modern-overlong", "漢" * 65), INVALID
        yield bad_name(tag, "modern-empty", ""), INVALID


def rejected_finite_ids(_, tag):
    return {f"body-trailing-{ord(char):02x}" for char in WHITESPACE} | {"duplicate"} | (
        {"partial-native-character"} if tag in law.LEGACY else {"modern-overlong", "modern-empty"})


def observe_rejection(_, item, actual, sql):
    coordinate = item["negative"]
    legacy = item["tag"] in law.LEGACY
    native_stage, text_stage = "prepare", "execute"
    state, code, exists = "42000", 1166, False
    message = refusal_text(item["tag"], item["name"])
    if coordinate == "duplicate":
        state, code = "42S21", 1060
        message = "Duplicate column name 'é'" if legacy else "Duplicate column name 'É'"
        if legacy:
            native_stage = "execute"
    elif coordinate == "partial-native-character":
        native_stage = text_stage = "read"
        code, exists = 1064, True
        message = ("You have an error in your SQL syntax; check the manual that corresponds to your MySQL server "
                   f"version for the right syntax to use near '`{'漢' * 26}' at line 1")
    failure = {"status": "native-refusal", "sqlState": state, "code": code, "message": message,
               "warnings": [["Error", code, message]], "catalogExists": exists}
    expected = {"native-prepared": {**failure, "phase": native_stage}, "text": {**failure, "phase": text_stage}}
    require(canonical(expected) == canonical(actual), "mysql-view-output-native-negative-mismatch",
            {"input": item, "sql": sql, "expected": expected, "observed": actual})
    return coordinate


def requests(_, tag, contract):
    for index, identity in enumerate(law.grid(contract, tag)):
        item = law.finite(tag, *identity)
        yield f"finite/{index}", source_request(tag, law.command(item)), item, None
        yield f"direct/{index}", law.structure(item), item, None
    for item, message in negative_items(tag):
        yield f"negative/{item['negative']}", source_request(tag, item["sql"]), item, refusal(message)
    template = law.structure(law.finite(tag, "ordinary", "explicit", "written"))
    if tag in law.LEGACY:
        draft = {**template, "createView": {**template["createView"], "columns": [" raw", "other"]}}
        yield "negative/legacy-effective-name", draft, None, refusal(LEGACY_NAME)
    draft = {**template, "createView": {**template["createView"], "columns": ["漢" * 86, "other"]}}
    yield "negative/direct-width", draft, None, refusal(WIDTH)
    settings = contract["exploration"]
    for seed in settings["seeds"]:
        for index, item in enumerate(law.seeded(tag, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", source_request(tag, law.command(item)), item, None
        for index, item in enumerate(law.seeded(tag, seed + 1, settings["directCasesPerSeed"])):
            yield f"direct-seed/{seed}/{index}", law.structure(item), item, None


def check_value(item, response):
    expected = law.structure(item, observed=True)["createView"]
    profile = item["tag"] + "/utf8mb4/lower-case-table-names=0/sql-mode=empty/native/view-definition"
    require(response["status"] == "constructed" and response["profile"] == profile and response["createView"] == expected,
            "mysql-view-output-graph", {"expected": expected, "actual": response})


def observer_request(_, sql):
    return {"sql": sql, "catalogColumns": True}


def check_observation(item, actual, sql):
    expected = law.expected(item)
    detail = {"sql": sql, "expected": expected, "observed": actual}
    for mode in expected:
        record = actual[mode]
        require(record["status"] == "created", "mysql-view-output-native-refusal", detail)
        require(isinstance(record.get("definitionSql"), str) and record["definitionSql"].startswith("CREATE ALGORITHM="),
                "mysql-view-output-definition-unobserved", detail)
        effect = {key: value for key, value in record.items() if key != "definitionSql"}
        require(canonical(effect) == canonical(expected[mode]), MEANING, detail)


def sample_forms(forms: Counter, response, *_):
    graph = response["createView"]
    forms[f"width/{len(graph['columns'])}"] += 1
    forms[f"inferred-sql/{') AS ' not in response['sql']}"] += 1
    for name, pair in zip(graph["columns"], graph["query"]["columns"], strict=True):
        operand, body_name = pair
        forms[f"declared/{type(name).__name__}"] += 1
        forms[f"body/{type(body_name).__name__}"] += 1
        forms[f"operand/{operand[0]}"] += 1
        effect = name[-1] if isinstance(name, list) else name
        forms[f"empty/{effect == ''}"] += 1
        forms[f"utf8/{not effect.isascii()}"] += 1
        forms[f"catalog-display-shorter/{len(effect) > 64}"] += 1


def required_forms(_, tag):
    common = {"width/2", "width/3", "width/4", "declared/str", "body/str", "body/list", "operand/integer",
              "operand/projected-integer", "empty/True", "empty/False", "utf8/True", "utf8/False", "inferred-sql/False"}
    if tag in law.LEGACY:
        return common | {"declared/list", "catalog-display-shorter/True"}
    return common | {"inferred-sql/True"}


def controls():
    item = law.finite("mysql-5.6.51", "leading-control", "explicit", "written")
    healthy = {mode: {**record, "definitionSql": "CREATE ALGORITHM=UNDEFINED VIEW fixture"}
               for mode, record in law.expected(item).items()}
    sql = law.command(item)
    check_observation(item, healthy, sql)
    native = healthy["native-prepared"]
    catalog = [["414c5445524544", *native["catalogColumns"][0][1:]], *native["catalogColumns"][1:]]
    alternatives = [
        {**native, "catalogColumns": catalog},
        {**native, "prepareWarnings": native["executeWarnings"], "executeWarnings": native["prepareWarnings"]},
    ]
    mismatches = []
    for record in alternatives:
        broken = {**healthy, "native-prepared": record}
        try:
            check_observation(item, broken, sql)
        except Failure as error:
            same(MEANING, error.signal)
            mismatches.append(error.detail)
        else:
            raise Failure("mysql-view-output-comparator-ignored-observation")
    return {"separateCatalogAndWarningPhase": mismatches}


def faults(_, tag):
    prefix = "sql-semantics-mysql/src/Ir/Schema/View/"
    item = law.finite(tag, "leading-control", "explicit", "written")
    if tag in law.LEGACY:
        before = "$name->aliasSql()"
        replacement = '"`" . str_replace("`", "``", $name->value) . "`"'
    else:
        before = "$name->quoted()"
        replacement = '"`" . str_replace("`", "``", ltrim($name->value)) . "`"'
    duplicate = bad_name(tag, "duplicate", "É", duplicate=True)
    duplicate["expectedRefusal"] = DUPLICATE
    adjacency = bad_name(tag, "body-trailing-09", "edge\t", body=True)
    adjacency["expectedRefusal"] = INVALID
    return [
        RenderFault("discard-observable-view-name", prefix + "CreateView.php", before, replacement,
                    item, MEANING, law.structure(item)),
        RenderFault("fold-view-heading-only-in-ascii", prefix + "CreateView.php", "$names->key($column->name->value)",
                    "$column->name->key()", duplicate, ADMITTED, source_request(tag, duplicate["sql"])),
        RenderFault("admit-view-body-trailing-control", prefix + "ViewProjectionAliases.php", r"(?<![\x09-\x0D\x20])", "(?<! )",
                    adjacency, ADMITTED, source_request(tag, adjacency["sql"])),
    ]


def check_admission(item, response, original):
    same(item["negative"], observe_rejection(None, item, original, item["sql"]))
    if rejects(response, refusal(item["expectedRefusal"])):
        return
    require(response["status"] == "constructed", "mysql-view-output-unrelated-rejection", response)
    require(response["createView"]["target"] == ["ir_fixture", "ir_cv_main"] and response["sql"].startswith("CREATE "),
            "mysql-view-output-unrelated-publication", response)
    raise Failure(ADMITTED, {"sql": item["sql"], "original": original, "response": response})


def fault_probe(item, response, db):
    if "negative" in item:
        check_admission(item, response, db.request(observer_request(item, item["sql"])))
        return
    check_value(item, response)
    check_observation(item, db.request(observer_request(item, response["sql"])), response["sql"])


def replay_fault(rule, record):
    detail = record["detail"]
    item = rule.item
    if "negative" in item:
        check_admission(item, record["healthyResponse"], detail["original"])
    else:
        check_value(item, record["healthyResponse"])
    mutant = record["mutantResponse"]
    try:
        if "negative" in item:
            check_admission(item, mutant, detail["original"])
        else:
            check_value(item, mutant)
            same(mutant["sql"], detail["sql"])
            check_observation(item, detail["observed"], detail["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(detail, error.detail)
        return
    raise Failure("mysql-view-output-fault-not-reproduced")


if __name__ == "__main__":
    main(sys.modules[__name__])
