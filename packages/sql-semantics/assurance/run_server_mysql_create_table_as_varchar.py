"""Compare bounded MySQL table creation from native VARCHAR fields."""

from copy import deepcopy
import json
import sys

import mysql_create_table_as_varchar_cases as cases
import run_server_mysql_create_table_as as constants
import run_server_mysql_create_table_as_source as source
from mysql_create_table_as_varchar_cases import finite_id, finite_ids
from mysql_create_table_as_source_cases import check_observation, snapshots
from run import TOOL, Failure, digest, require
from run_server import same
from run_server_join import main, probe
from sqlite_domain import RenderFault

LABEL = "mysql-create-table-as-varchar"
OBSERVER = constants.OBSERVER
CONTRACT_NAME = "mysql-create-table-as-varchar-v1.json"
CONTRACT = source.VARCHAR_ADMISSION
COMPLETE_SEEDS = True

check_value = source.check_value
observer_request = source.observer_request
observation = source.observation
prepare = source.prepare
check_preparation = source.check_preparation


def specifications():
    path = TOOL / CONTRACT_NAME
    require(digest(path) == CONTRACT, "mysql-create-as-text-contract-mismatch")
    return json.loads(path.read_text()), constants.specifications()[1]


def requests(_, tag, specification):
    for index, item in enumerate(cases.finite_cases(tag, specification)):
        yield f"finite/{index}", item, item, None
    originals = list(cases.authored(tag))
    require(len(originals) == len(specification["analysis"]), "mysql-create-as-text-analysis-count")
    for index, (request, item) in enumerate(originals):
        yield f"analyze/{index}", request, item, None
    for label, item in (("65535-bytes", cases.wide(tag)), ("retained-column", cases.retained(tag))):
        yield f"probe/{label}", item, item, None
    for seed in specification["exploration"]["seeds"]:
        for index, item in enumerate(cases.seeded(tag, specification, seed)):
            yield f"seed/{seed}/{index}", item, item, None
    invalid = list(cases.negatives(tag))
    require(len(invalid) == len(specification["negative"]), "mysql-create-as-text-negative-count")
    for index, (request, exception, message) in enumerate(invalid):
        yield f"negative/{index}", request, None, {"exception": exception, "message": message}


def sample_forms(forms, response, engine, item):
    source.sample_forms(forms, response, engine, item)
    query = response["mysqlCreateTableAs"]["query"]
    columns = query["columns"]
    counts = {}
    for term, _ in columns:
        forms[f"field/{term[0]}"] += 1
        if term[0] == "text-slot":
            width = query["source"]["columnTypes"][term[1]][1]
            forms[f"width-characters/{width}"] += 1
            counts[term[1]] = counts.get(term[1], 0) + 1
    if any(count > 1 for count in counts.values()):
        forms["repeated/text-slot"] += 1
    where = query["where"]
    if where is not None:
        forms["null-test/" + ("present" if where[1] else "null")] += 1
    for position, _, _ in query["orderBy"]:
        forms[f"order-role/{columns[position - 1][0][0]}"] += 1


def required_forms(_, _tag):
    return {"field/slot", "field/text-slot", "width-characters/0", "width-characters/8", "width-characters/63",
            "width-characters/64", "null-test/present", "null-test/null", "repeated/text-slot", "order-role/slot",
            "order-role/text-slot", "source/primary", "source/decoy", "prior/persistent", "prior/shadow",
            "prior/temporary-only", "namespace/table", "namespace/temporary-table", "distinct/True", "distinct/False",
            "bounds/none", "bounds/count", "bounds/offset", "order/none", "order/partial", "order/all",
            *("collision/" + role for role in ("absent", "persistent", "temporary", "shadow"))}


def controls():
    item = cases.case("mysql-8.4.7", "primary", "shadow", constants.TEMP, cases.columns("text"))
    expected = snapshots(item)
    check_observation(item, expected, "known copies of VARCHAR bytes")
    target = constants.target_name(item["mysqlCreateTableAs"])
    distinct = cases.case("mysql-8.4.7", "primary", "shadow", constants.TEMP, cases.columns("text"), distinct=True)
    first_distinct = snapshots(distinct)
    alternate_rows = deepcopy(first_distinct["after"]["visible"][target]["rows"])
    equivalent = next(row for row in alternate_rows if isinstance(row[0], str) and row[0].rstrip(" ") == "a")
    alternate_value = "a " if equivalent[0] == "a" else "a"
    equivalent[:2] = [alternate_value, alternate_value]
    alternate_distinct = snapshots(distinct, alternate_rows, "alternate whole-row padding representative")
    require(first_distinct != alternate_distinct, "mysql-create-as-text-distinct-controls-equal")
    check_observation(distinct, first_distinct, "first whole-row padding representative")
    check_observation(distinct, alternate_distinct, "alternate whole-row padding representative")
    pad = deepcopy(expected)
    rows = pad["after"]["visible"][target]["rows"]
    padding = next(row for row in rows if row[0] == "a ")
    padding[0] = "a"
    width = deepcopy(expected)
    width["after"]["visible"][target]["columns"][0][1] = "varchar(1)"
    untied = deepcopy(expected)
    untied["after"]["visible"][target]["rows"].pop()
    alternatives = {"erased-padding": pad, "incorrect-text-width": width, "lost-repeat": untied}
    detections = {}
    for identifier, changed in alternatives.items():
        try:
            check_observation(item, changed, identifier)
        except Failure as error:
            same("meaning-mismatch", error.signal)
            detections[identifier] = error.signal
        else:
            raise Failure("mysql-create-as-text-comparator-accepted", identifier)
    return {"input": item, "prediction": expected, "mismatches": alternatives, "detections": detections,
            "distinctInput": distinct, "distinctRepresentatives": [first_distinct, alternate_distinct]}


def fault_probe(item, response, db):
    if "boundary" in item:
        return constants.check_boundary(item, response)
    return probe(item, response, db, sys.modules[__name__])


def replay_fault(rule, record):
    if "boundary" in rule.item:
        return constants.replay_fault(rule, record)
    return source.replay_fault(rule, record)


def faults(_, tag):
    ir = "sql-semantics-mysql/src/Ir/"
    limit = cases.case(tag, "primary", "persistent", "table", cases.columns("text"),
                       order=[[1, True, False]], limit=[2, 1])
    selected = cases.case(tag, "primary", "persistent", constants.TEMP, cases.columns("text"),
                          where=["null-test", True, cases.text(2)], distinct=True)
    invalid, exception, message = next(cases.negatives(tag))
    return [
        RenderFault("select-other-varchar-slot", ir + "SelectSql.php", "$name = $input->columns[$expression->slot]->quoted();",
                    "$name = $input->columns[$expression->slot === 2 ? 4 : $expression->slot]->quoted();",
                    selected, "meaning-mismatch"),
        RenderFault("select-other-varchar-source", ir + "SelectSql.php", "$source->schema->quoted() . '.' . $source->table->quoted()",
                    "'`ir_ctas_decoy`.' . $source->table->quoted()", selected, "meaning-mismatch"),
        RenderFault("omit-varchar-null-probe", ir + "SelectSql.php", "if ($select->where !== null)", "if (false)", selected, "meaning-mismatch"),
        RenderFault("leave-repeated-varchar-equivalence-classes", ir + "SelectSql.php", "$select->distinct ? 'SELECT DISTINCT ' : 'SELECT '",
                    "'SELECT '", selected, "meaning-mismatch"),
        RenderFault("admit-large-logical-record", ir + "Schema/Query/CreateAsRecord.php", "$logical + $bitmap <= 65535", "true",
                    {"boundary": {"exception": exception, "message": message}}, constants.INVALID, invalid),
        RenderFault("erase-varchar-row-count", ir + "Ordering/ResultSql.php", "$sql .= ' LIMIT ' . $select->limit->count;",
                    "$sql .= ' LIMIT 2147483647';", limit, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
