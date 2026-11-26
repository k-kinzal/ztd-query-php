"""Compare the native INSERT query with independent positional assignment and rollback."""

import json
import sys

from evidence_transcript import canonical
from run import TOOL, Failure, digest, require
from run_server import same
from run_server_insert import specifications as insert_specification
from run_server_join import main, observer_request
from server_cases import SCHEMAS
from server_compound_cases import leaf
from server_delete_cases import literal
from server_insert_cases import observation, ordered
from server_insert_query_cases import INITIAL, case, expected, finite_id, finite_ids, requests, structural_model, table
from server_join_cases import compare, slot
from sqlite_domain import RenderFault

LABEL = "insert-query"
OBSERVER = "server_insert_query_observer.php"
CONTRACT = "5ed3c013ca28a7dcb663fa36fe217b13eaffbc4b88cd8e4d4dd65831a48e4f00"


def specifications():
    path = TOOL / "server-insert-query-v3.json"
    require(digest(path) == CONTRACT, "server-insert-query-contract-mismatch")
    _, profiles = insert_specification()
    return json.loads(path.read_text()), profiles


def check_value(item, reply):
    require(reply["status"] == "constructed", "server-insert-query-positive-rejected", reply)
    require(reply["profile"].startswith(item["release"] + "/"), "server-insert-query-profile-mismatch", reply)
    model = structural_model(item)
    require(canonical(model) == canonical({field: reply[field] for field in model}), "server-insert-query-shape-mismatch", reply)


def check_observation(item, actual, sql):
    prediction = expected(item)
    witness = {"sql": sql, "expected": prediction, "observed": actual}
    if item["dialect"] == "postgres" and actual.get("state") == "42804":
        raise Failure("server-insert-query-null-type", witness)
    require(actual["status"] == "inserted", "server-insert-query-native-rejection", witness)
    require(canonical(prediction) == canonical(actual), "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    actual = witness["observed"]
    if actual["status"] == "db-error":
        require(item["dialect"] == "postgres" and item["insertQuery"]["distinct"] and actual["state"] == "42804",
                "server-insert-query-fault-native")
        same(ordered(INITIAL), ordered(actual["restored"]))
    else:
        require(actual["status"] == "inserted", "server-insert-query-fault-native")


def outsider_requests(engine):
    schema = SCHEMAS[engine]
    prefix = f"INSERT INTO {schema}.ir_insert_target (id) "
    return [
        f"INSERT INTO {schema}.ir_insert_scan (id) SELECT 1 AS p0",
        prefix + "SELECT RAND() AS p0",
        prefix + f"SELECT id AS p0 FROM {schema}.ir_insert_events",
        prefix + f"SELECT 1 AS p0; DELETE FROM {schema}.ir_insert_target",
    ]


def prepare(engine, db):
    record = {"denials": {sql: db.request({"sql": sql}) for sql in outsider_requests(engine)}}
    if engine == "postgres":
        query = "INSERT INTO public.ir_insert_target (id) SELECT DISTINCT NULL AS p0"
        record["untypedNull"] = db.request({"sql": query})
    return record


def check_preparation(engine, environment, preparation):
    same(ordered(INITIAL), ordered(environment["initial"]))
    same({sql: {"status": "outside-observer-scope"} for sql in outsider_requests(engine)}, preparation["denials"])
    if engine == "postgres":
        rejected = preparation["untypedNull"]
        require(rejected["status"] == "db-error" and rejected["state"] == "42804",
                "postgres-insert-query-untyped-null-control", rejected)
        same(ordered(INITIAL), ordered(rejected["restored"]))


def required_forms(_engine, _tag):
    return {"source/unit", "source/ir_insert_scan", "source/ir_insert_zero", "width/1", "width/2", "width/3",
            "expression/integer", "expression/slot", "expression/null", "expression/operation",
            "distinct/True", "distinct/False", "filter/True", "filter/False"}


def sample_forms(forms, response, _engine, _item):
    query = response["insertQuery"]
    forms[f"width/{len(response['target']['columns'])}"] += 1
    forms["source/" + ("unit" if query["source"] is None else query["source"]["table"])] += 1
    forms[f"distinct/{query['distinct']}"] += 1
    forms[f"filter/{query['where'] is not None}"] += 1
    for expr, _ in query["columns"]:
        forms["expression/" + expr[0]] += 1


def controls():
    engine, tag = "postgres", "pg-17.2"
    query = leaf(engine, tag, [[slot(0), "p0"], [slot(1), "p1"]], table(engine, "ir_insert_scan"))
    item = case(engine, tag, ["delta", "id"], query)
    expected_result = expected(item)
    check_observation(item, expected_result, "measured duplicate input")
    altered = {
        "affected-rows": {**expected_result, "count": 1},
        "omitted-trigger": {**expected_result, "after": {**expected_result["after"], "events": []}},
        "modified-read-source": {**expected_result, "after": {**expected_result["after"], "scan": []}},
        "lost-rollback": {**expected_result, "restored": expected_result["after"]},
    }
    detected = {}
    for name, actual in altered.items():
        try:
            check_observation(item, actual, name)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "server-insert-query-control-wrong-signal", name)
            detected[name] = failure.signal
        else:
            raise Failure("server-insert-query-comparator-accepted", name)
    return {"input": item, "prediction": expected_result, "mismatches": altered, "detections": detected}


def faults(engine, tag):
    parent = f"sql-semantics-{engine}/src/Ir/Mutation/InsertQuery.php"
    destination = parent if engine == "mysql" else "sql-semantics-postgres/src/Ir/Mutation/Insert/Destination.php"
    renderer = f"sql-semantics-{engine}/src/Ir/SelectSql.php"
    scan = table(engine, "ir_insert_scan")
    query = leaf(engine, tag, [[slot(0), "p0"], [slot(1), "p1"]], scan)
    first = leaf(engine, tag, [[slot(1), "p0"]], scan)
    target = "$input->columns[$expression->slot]->quoted()"
    result = [
        RenderFault("reverse-query-insert-target", destination, "implode(', ', $columns)", "implode(', ', array_reverse($columns))",
                    case(engine, tag, ["id", "delta"], query), "meaning-mismatch"),
        RenderFault("switch-query-insert-slot", renderer, target, "$input->columns[0]->quoted()",
                    case(engine, tag, ["id"], first), "meaning-mismatch"),
        RenderFault("omit-query-insert-distinct", renderer, "$select->distinct ? 'SELECT DISTINCT ' : 'SELECT '", "'SELECT '",
                    case(engine, tag, ["id", "delta"], {**query, "distinct": True}), "meaning-mismatch"),
        RenderFault("omit-query-insert-filter", renderer, "if ($select->where !== null)", "if (false)",
                    case(engine, tag, ["id"], {**first, "where": compare(">", slot(1), literal(5))}), "meaning-mismatch"),
    ]
    if engine == "postgres":
        typed = leaf(engine, tag, [[["builtin-cast", "int4", ["null"]], "p0"]])
        typed["distinct"] = True
        result.append(RenderFault("erase-assigned-int4-null", renderer,
                                  "$expression instanceof BuiltinCast => 'CAST(' . $this->expression($expression->operand, $source, $offset, $qualify) . ' AS pg_catalog.' . $expression->target->value . ')'",
                                  "$expression instanceof BuiltinCast => $this->expression($expression->operand, $source, $offset, $qualify)",
                                  case(engine, tag, ["id"], typed), "server-insert-query-null-type"))
    return result


if __name__ == "__main__":
    main(sys.modules[__name__])
