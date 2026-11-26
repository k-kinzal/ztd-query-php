"""Observe declared CTAS columns, signed conversions and their native diagnostics."""

from copy import deepcopy
import json
import sys

import mysql_create_table_as_declaration_cases as cases
import run_server_mysql_create_table_as as constants
from mysql_create_table_as_declaration_cases import finite_id, finite_ids, prediction
from run import TOOL, Failure, digest, require
from run_server import same
from run_server_join import main, probe
from sqlite_domain import RenderFault

LABEL = "mysql-create-table-as-declarations"
OBSERVER = constants.OBSERVER
CONTRACT_NAME = "mysql-create-table-as-declarations-v1.json"
CONTRACT = constants.DECLARATION_CONTRACT
COMPLETE_SEEDS = True
check_value = constants.check_value
observation = constants.observation


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "mysql-create-as-declaration-contract-mismatch")
    return json.loads(path.read_text()), constants.specifications()[1]


def requests(_, tag, specification):
    for index, item in enumerate(cases.finite_cases(tag, specification)):
        yield f"finite/{index}", item, item, None
    originals = list(cases.authored(tag))
    require(len(originals) == len(specification["analysis"]), "mysql-create-as-declaration-analysis-count")
    same(specification["replacesPreviousNegative"]["previousSql"], originals[0][0]["sql"])
    for index, (request, item) in enumerate(originals):
        yield f"analyze/{index}", request, item, None
    item = cases.capacity(tag)
    yield "probe/128-combined-fields", item, item, None
    for seed in specification["exploration"]["seeds"]:
        for index, item in enumerate(cases.seeded(tag, specification, seed)):
            yield f"seed/{seed}/{index}", item, item, None
    invalid = list(cases.negatives(tag))
    require(len(invalid) == len(specification["negative"]), "mysql-create-as-declaration-negative-count")
    for index, (request, message) in enumerate(invalid):
        yield f"negative/{index}", request, None, {"exception": constants.DIAGNOSTIC, "message": message}


def observer_request(item, sql):
    return {**constants.observer_request(item, sql), "explicitDefinitions": True}


def check_observation(item, actual, sql):
    expected = prediction(item)
    witness = {"sql": sql, "expected": expected, "observed": actual}
    require(actual["status"] == constants.LABEL, "mysql-create-as-declaration-observer-rejected", witness)
    try:
        same(expected, actual)
    except Failure as error:
        raise Failure(error.signal, witness) from error


def sample_forms(forms, response, _, item):
    result = response["mysqlCreateTableAs"]
    declarations = result["declaredColumns"]
    query = result["query"]
    names = {name for _, name in query["columns"]}
    forms[f"namespace/{result['target'][2]}"] += 1
    forms[f"guard/{result['ifNotExists']}"] += 1
    forms[f"prior/{item['topology']}"] += 1
    forms["width/" + ("single" if len(declarations) == 1 else "multiple")] += 1
    for name, storage, nullable, default in declarations:
        forms[f"storage/{storage}"] += 1
        forms["match/" + ("exact" if name in names else "folded" if name.lower() in names else "absent")] += 1
        forms[f"nullable/{nullable}"] += 1
        forms["default/" + ("absent" if default is None else default[0])] += 1
    for term, _ in query["columns"]:
        forms[f"selected/{term[0]}"] += 1
    materialized, diagnostics = cases.projected_values(item["release"], result["target"][2], result)
    forms[f"rows/{len(materialized['rows'])}"] += 1
    for _, code, _ in diagnostics:
        forms[f"potential-warning/{code}"] += 1


def required_forms(_, _tag):
    return {"namespace/table", "namespace/temporary-table", "guard/True", "guard/False", "width/single", "width/multiple",
            "match/exact", "match/folded", "match/absent", "nullable/True", "nullable/False", "default/absent",
            "default/integer", "default/null", "selected/integer", "selected/null", "selected/signed-cast", "rows/0", "rows/1",
            *("storage/" + role for role in cases.STORAGE),
            *("prior/" + role for role in ("absent", "persistent", "temporary", "shadow")),
            *("potential-warning/" + str(code) for code in (1048, 1264, 1364))}


def outsiders():
    return ["DROP TABLE ir_create_target", "CREATE TABLE ir_create_target (x INT, PRIMARY KEY (x)) AS SELECT 1 AS c0",
            "CREATE TABLE ir_create_target (x INT) AS SELECT 1 AS c0; SELECT 1"]


def prepare(_, db):
    item = next(cases.finite_cases("mysql-8.4.7", specifications()[0]))
    return {sql: db.request(observer_request(item, sql)) for sql in outsiders()}


def check_preparation(_, _environment, preparation):
    same({sql: {"status": "outside-observer-scope"} for sql in outsiders()}, preparation)


def controls():
    item = cases.case("mysql-8.4.7", constants.TEMP, "absent", False, cases.layout("mixed"), cases.projection("null"))
    expected = prediction(item)
    check_observation(item, expected, "known declaration and projection composition")
    target = constants.target_name(item["mysqlCreateTableAs"])
    defaults = deepcopy(expected)
    defaults["after"]["visible"][target]["columns"][0][3] = "0"
    warnings = deepcopy(expected)
    warnings["diagnostics"] = []
    swapped = deepcopy(expected)
    swapped["after"]["visible"][target]["columns"].reverse()
    variants = {"changed-declared-default": defaults, "lost-diagnostics": warnings, "reversed-combined-heading": swapped}
    detections = {}
    for name, actual in variants.items():
        try:
            check_observation(item, actual, name)
        except Failure as error:
            same("meaning-mismatch", error.signal)
            detections[name] = error.signal
        else:
            raise Failure("mysql-create-as-declaration-comparator-accepted", name)
    return {"input": item, "prediction": expected, "mismatches": variants, "detections": detections}


def fault_probe(item, response, db):
    if "boundary" in item:
        return constants.check_boundary(item, response)
    return probe(item, response, db, sys.modules[__name__])


def replay_fault(rule, record):
    if "boundary" in rule.item:
        return constants.replay_fault(rule, record)
    witness = record["detail"]
    check_value(rule.item, record["healthyResponse"])
    check_value(rule.item, record["mutantResponse"])
    try:
        same(prediction(rule.item), witness["expected"])
        check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("mysql-create-as-declaration-replay-accepted-fault", rule.name)


def faults(_, tag):
    folder = "sql-semantics-mysql/src/Ir/Schema/"
    type_ = folder + "Query/CreateTableAs.php"
    bounds = folder + "Query/CreateAsLayout.php"
    column = folder + "TableColumn.php"
    materialize = cases.case(tag, constants.TEMP, "absent", False, cases.layout("mixed"), cases.projection("values"))
    null = cases.case(tag, constants.TEMP, "absent", False, cases.layout("override"), cases.projection("null"))
    invalid_name, name_error = list(cases.negatives(tag))[2]
    oversized, size_error = list(cases.negatives(tag))[3]
    boundary = lambda message: {"boundary": {"exception": constants.DIAGNOSTIC, "message": message}}
    return [
        RenderFault("reverse-explicit-definition-only-fields", type_, "foreach ($this->declaredColumns as $column)",
                    "foreach (array_reverse($this->declaredColumns) as $column)", materialize, "meaning-mismatch"),
        RenderFault("erase-explicit-storage-override", type_, "foreach ($this->declaredColumns as $column)",
                    "foreach (array_slice($this->declaredColumns, 1) as $column)", materialize, "meaning-mismatch"),
        RenderFault("erase-explicit-required-nullability", column, "$this->nullable ? ' NULL' : ' NOT NULL'",
                    "' NULL'", null, "meaning-mismatch"),
        RenderFault("replace-explicit-stored-default", column, "' DEFAULT ' . $this->default->decimal",
                    "' DEFAULT 0'", materialize, "meaning-mismatch"),
        RenderFault("allow-duplicate-explicit-heading", bounds, "!isset($names[$key])", "true",
                    boundary(name_error), constants.INVALID, invalid_name),
        RenderFault("allow-oversized-combined-heading", folder + "Query/CreateAsRecord.php", "$columns <= 1017", "true",
                    boundary(size_error), constants.INVALID, oversized),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
