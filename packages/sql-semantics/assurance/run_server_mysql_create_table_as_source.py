"""Observe MySQL table creation from resolved signed source fields."""

from collections import Counter
from copy import deepcopy
import json
import sys

import mysql_create_table_as_source_cases as cases
import run_server_mysql_create_table_as as constants
from mysql_create_table_as_source_cases import check_observation, finite_id, finite_ids, snapshots
from run import TOOL, Failure, digest, require
from run_server import same
from run_server_join import main, probe
from sqlite_domain import RenderFault

LABEL = "mysql-create-table-as-source"
OBSERVER = constants.OBSERVER
CONTRACT_NAME = "mysql-create-table-as-source-v1.json"
CONTRACT = constants.SOURCE_CONTRACT
VARCHAR_ADMISSION = "a58750e80af50328a9d7f492a2e96bf9d6b49d722fd6a578026b6d4629d0d46f"
COMPLETE_SEEDS = True
BINDING = "mysql-create-as-input-binding-mismatch"


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "mysql-create-as-input-contract-mismatch")
    amendment = TOOL / "mysql-create-table-as-varchar-v1.json"
    require(digest(amendment) == VARCHAR_ADMISSION, "mysql-create-as-text-admission-mismatch")
    require("priorAdmission" in json.loads(amendment.read_text()), "mysql-create-as-text-admission-record")
    return json.loads(path.read_text()), constants.specifications()[1]


def requests(_, tag, specification):
    for index, item in enumerate(cases.finite_cases(tag, specification)):
        yield f"finite/{index}", item, item, None
    authored = list(cases.authored(tag))
    require(len(authored) + 1 == len(specification["analysis"]), "mysql-create-as-input-analysis-count")
    for index, (request, item) in enumerate(authored):
        yield f"analyze/{index}", request, item, None
    retained = cases.preserved_original(tag)
    yield "probe/retained-source", retained, retained, None
    for seed in specification["exploration"]["seeds"]:
        for index, item in enumerate(cases.seeded(tag, specification, seed)):
            yield f"seed/{seed}/{index}", item, item, None
    invalid = list(cases.negatives(tag))
    require(len(invalid) == len(specification["negative"]), "mysql-create-as-input-negative-count")
    for index, (request, exception, message) in enumerate(invalid):
        yield f"negative/{index}", request, None, {"exception": exception, "message": message}


def observer_request(item, sql):
    return {**constants.observer_request(item, sql), "sourceFixture": item["sourceFixture"]}


def observation(raw):
    return raw


def check_value(item, response):
    require(response["status"] == "constructed", "mysql-create-as-input-positive-rejected", response)
    target = response["mysqlCreateTableAs"]
    original = item["mysqlCreateTableAs"]
    require(target["query"]["source"] == original["query"]["source"], BINDING,
            {"expected": original["query"]["source"], "observed": target["query"]["source"], "sql": response["sql"]})
    constants.check_value(item, response)


def sample_forms(forms, response, _, item):
    model = response["mysqlCreateTableAs"]
    query, target = model["query"], model["target"]
    where, keys, bounds = query["where"], query["orderBy"], query["limit"]
    slots = Counter(term[1] for term, _ in query["columns"] if term[0] == "slot")
    scope = next(role for role, schema in constants.SCHEMAS.items() if query["source"]["schema"] == schema)
    forms[f"source/{scope}"] += 1
    forms[f"prior/{item['sourceFixture']['topology']}"] += 1
    forms[f"namespace/{target[2]}"] += 1
    forms[f"collision/{item['topology']}"] += 1
    forms[f"distinct/{query['distinct']}"] += 1
    forms[f"width/{len(query['columns'])}"] += 1
    forms[f"filter/{'absent' if where is None else where[0]}"] += 1
    forms["order/" + ("all" if len(keys) == len(query["columns"]) else "partial" if keys else "none")] += 1
    forms["bounds/" + ("none" if bounds is None else "offset" if bounds[1] else "count")] += 1
    if any(count > 1 for count in slots.values()):
        forms["repeated/physical-slot"] += 1


def required_forms(_engine, _tag):
    return {"source/primary", "source/decoy", "prior/persistent", "prior/temporary-only", "prior/shadow",
            "namespace/table", "namespace/temporary-table", "distinct/True", "distinct/False",
            "width/2", "width/3", "filter/absent", "filter/comparison", "filter/null-test",
            "order/all", "order/none", "order/partial", "bounds/none", "bounds/offset", "bounds/count",
            "repeated/physical-slot", *("collision/" + role for role in ("absent", "persistent", "temporary", "shadow"))}


def prepare(_engine, db):
    item = cases.construction("mysql-8.4.7", "primary", "persistent", "table", cases.layout("normal"))
    return {sql: db.request(observer_request(item, sql)) for sql in outsiders()}


def outsiders():
    return ["DROP TABLE ir_create_target", "CREATE TABLE ir_create_target AS SELECT n FROM arbitrary",
            "CREATE TABLE ir_create_target AS SELECT n FROM ir_ctas_source; SELECT 1"]


def check_preparation(_, _environment, preparation):
    same({sql: {"status": "outside-observer-scope"} for sql in outsiders()}, preparation)


def controls():
    item = cases.construction("mysql-8.4.7", "primary", "shadow", constants.TEMP, cases.layout("normal"),
                              order=[[1, False, True]], limit=[2, 2])
    expected = snapshots(item)
    alternate = snapshots(item, [[3, None], [3, 96]], "valid alternate boundary tie")
    require(alternate != expected, "mysql-create-as-input-tie-controls-equal")
    check_observation(item, expected, "known source inheritance")
    check_observation(item, alternate, "valid alternate boundary tie")
    target = constants.target_name(item["mysqlCreateTableAs"])
    wrong_slot = deepcopy(expected)
    wrong_slot["after"]["visible"][target]["columns"][0][3] = "0"
    wrong_peer = deepcopy(expected)
    wrong_peer["after"]["visible"][target]["rows"][0][0] = 999
    lost_repeat = deepcopy(expected)
    lost_repeat["after"]["visible"][target]["rows"].pop()
    variants = {"wrong-inherited-default": wrong_slot, "wrong-order-peer": wrong_peer, "lost-duplicate": lost_repeat}
    detected = {}
    for role, candidate in variants.items():
        try:
            check_observation(item, candidate, role)
        except Failure as error:
            same("meaning-mismatch", error.signal)
            detected[role] = error.signal
        else:
            raise Failure("mysql-create-as-input-comparator-accepted", role)
    return {"input": item, "prediction": expected, "validAlternateTie": alternate,
            "mismatches": variants, "detections": detected}


def fault_probe(item, response, db):
    return probe(item, response, db, sys.modules[__name__])


def replay_fault(rule, record):
    witness = record["detail"]
    check_value(rule.item, record["healthyResponse"])
    try:
        check_value(rule.item, record["mutantResponse"])
        check_observation(rule.item, witness["observed"], witness["sql"])
    except Failure as error:
        same(rule.signal, error.signal)
        same(witness, error.detail)
        return
    raise Failure("mysql-create-as-input-replay-accepted-fault", rule.name)


def faults(_, tag):
    ir = "sql-semantics-mysql/src/Ir/"
    select = ir + "SelectSql.php"
    source = cases.construction(tag, "primary", "persistent", "table", cases.layout("normal"), where=cases.comparison())
    reduced = cases.construction(tag, "primary", "persistent", "table", cases.layout("normal"),
                                 order=[[2, True, False]], limit=[2, 1])
    quantifier = cases.construction(tag, "primary", "persistent", constants.TEMP, cases.layout("normal"), distinct=True)
    analysis, shadow = list(cases.authored(tag))[2]
    return [
        RenderFault("use-other-query-source-schema", select, "$source->schema->quoted() . '.' . $source->table->quoted()",
                    "'`ir_ctas_decoy`.' . $source->table->quoted()", source, "meaning-mismatch"),
        RenderFault("bind-persistent-source-instead-of-temporary",
                    "sql-semantics-mysql/src/Model/Construction/Derivation.php",
                    "->find($this->context, $name, $namespace)", "->find($this->context, $name, TableNamespace::PersistentPreferred)",
                    shadow, BINDING, analysis),
        RenderFault("use-other-query-source-slot", select, "$name = $input->columns[$expression->slot]->quoted();",
                    "$name = $input->columns[1 - $expression->slot]->quoted();", source, "meaning-mismatch"),
        RenderFault("discard-query-source-predicate", select, "if ($select->where !== null)", "if (false)",
                    source, "meaning-mismatch"),
        RenderFault("materialize-duplicate-query-source-rows", select, "$select->distinct ? 'SELECT DISTINCT ' : 'SELECT '",
                    "'SELECT '", quantifier, "meaning-mismatch"),
        RenderFault("discard-query-source-row-bound", ir + "Ordering/ResultSql.php",
                    "        if ($select->limit !== null) {\n            $sql .= ' LIMIT ' . $select->limit->count;\n        }\n"
                    "        if ($select->limit !== null && $select->limit->offset > 0) {\n"
                    "            $sql .= ' OFFSET ' . $select->limit->offset;\n        }", "",
                    reduced, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
