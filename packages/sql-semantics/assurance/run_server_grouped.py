"""Compare native grouped statements with independent typed partitions."""

import json
import sys

from evidence_transcript import canonical
from run import TOOL, Failure, digest, require
from run_server import same
from run_server_join import OBSERVER, main, observer_request, specifications as join_specification
from server_cases import SCHEMAS, fixtures as projection_fixture, observation as bag
from server_delete_cases import literal
from server_grouped_cases import ADDITIONAL_ROWS, aggregate, case, expected, finite_id, finite_ids, ordered, requests
from server_join_cases import NAMES_BY_TABLE, compare, fixture, slot, table
from server_wildcard_cases import LABEL_DDL, LABEL_RELATION
from sqlite_domain import RenderFault

LABEL = "grouped"
CONTRACT = "d927ba45c6a8c77e669b6da5551e099a815d38dc63dd8338b97553d4cd0cb199"


def specifications():
    path = TOOL / "server-grouped-v3.json"
    require(digest(path) == CONTRACT, "server-grouped-contract-mismatch")
    _, profiles = join_specification()
    return json.loads(path.read_text()), profiles


def check_value(item, response):
    require(response["status"] == "constructed", "server-grouped-positive-rejected", response)
    require(response["profile"].startswith(item["release"] + "/") and response["profile"].endswith("/native/grouped"),
            "server-grouped-profile-mismatch", response)
    require(canonical(item["grouped"]) == canonical(response["grouped"]), "server-grouped-shape-mismatch", response)


def observation(actual):
    """Keep the ordered transport until the release-specific comparator interprets it."""
    return actual


def check_observation(item, actual, sql):
    prediction = expected(item)
    require(actual["status"] == "rows", "server-grouped-native-rejection", {"sql": sql, "observed": actual})
    observed = actual if ordered(item) else bag(actual)
    require(canonical(prediction) == canonical(observed), "meaning-mismatch", {"sql": sql, "expected": prediction, "observed": actual})


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "server-grouped-fault-native")


ROUTINE_PROBE = "SELECT count(delta) AS shadow, (SELECT pg_catalog.count(v) FROM (VALUES (41)) AS a(v)) AS catalog FROM (VALUES (41)) AS r(delta)"


def prepare(engine, db):
    values = ",".join(f"({key},{value})" for key, value in ADDITIONAL_ROWS)
    statements = [*fixture(engine), *projection_fixture(engine), f"INSERT INTO {SCHEMAS[engine]}.{NAMES_BY_TABLE['accounts']} VALUES {values}"]
    if engine == "mysql":
        statements += [f"DROP TABLE IF EXISTS ir_fixture.{LABEL_RELATION}", LABEL_DDL,
                       f"INSERT INTO ir_fixture.{LABEL_RELATION} VALUES (1,2),(3,NULL),(1,2)"]
        return {"fixture": db.request({"fixture": statements})}
    statements += [
        "DROP FUNCTION IF EXISTS public.count(integer)",
        "CREATE FUNCTION public.count(integer) RETURNS integer LANGUAGE SQL IMMUTABLE RETURN 97",
    ]
    return {"fixture": db.request({"fixture": statements}), "routineResolution": db.request({"sql": ROUTINE_PROBE})}


def check_preparation(engine, _environment, preparation):
    expected = {"fixture": {"status": "fixture"}}
    if engine == "postgres":
        expected["routineResolution"] = {"status": "rows", "fields": [["shadow", "int4"], ["catalog", "int8"]], "rows": [[97, 1]]}
    same(expected, preparation)


def required_forms(engine, _tag):
    forms = {"key/global", "key/simple", "key/multiple", "key/repeated", "key/omitted", "field/key",
             "field/count", "field/sum", "field/min", "field/max", "argument/row", "argument/slot",
             "distinct/True", "distinct/False", "filter/present", "filter/absent", "source/empty"}
    return forms | ({"relation/inherited", "relation/own"} if engine == "postgres" else set())


def sample_forms(forms, response, _engine, _item):
    model = response["grouped"]
    keys = [key[1] for key in model["keys"]]
    forms["key/" + ("global" if not keys else "simple" if len(keys) == 1 else "multiple")] += 1
    if len(set(keys)) != len(keys):
        forms["key/repeated"] += 1
    if keys and not any(expr[0] == "slot" for expr, _ in model["columns"]):
        forms["key/omitted"] += 1
    if model["source"]["table"].endswith("_empty"):
        forms["source/empty"] += 1
    if "only" in model["source"]:
        forms["relation/" + ("own" if model["source"]["only"] else "inherited")] += 1
    forms["filter/" + ("absent" if model["where"] is None else "present")] += 1
    for expr, _ in model["columns"]:
        if expr[0] == "slot":
            forms["field/key"] += 1
        else:
            forms["field/" + expr[1]] += 1
            forms["argument/" + ("row" if expr[2] is None else "slot")] += 1
            forms[f"distinct/{expr[3]}"] += 1


def controls():
    engine, tag = "mysql", "mysql-5.7.44"
    item = case(engine, tag, table(engine, "accounts"), [slot(0)], [[slot(0), "key"], [aggregate("sum", slot(1)), "value"]])
    prediction = expected(item)
    check_observation(item, prediction, "known grouped result")
    modified = [list(row) for row in prediction["rows"]]
    modified[0][1] = int(modified[0][1])
    altered = {
        "changed-implicit-order": {**prediction, "rows": list(reversed(prediction["rows"]))},
        "lost-decimal-type": {**prediction, "fields": [prediction["fields"][0], ["value", "LONGLONG"]]},
        "decoded-exact-value-as-integer": {**prediction, "rows": modified},
        "wrong-result-label": {**prediction, "fields": [["other", "LONG"], prediction["fields"][1]]},
    }
    detected = {}
    for name, actual in altered.items():
        try:
            check_observation(item, actual, name)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "server-grouped-wrong-control-signal", name)
            detected[name] = failure.signal
        else:
            raise Failure("server-grouped-comparator-accepted", name)
    return {"input": item, "prediction": prediction, "mismatches": altered, "detections": detected}


def faults(engine, tag):
    target = f"sql-semantics-{engine}/src/Ir/Aggregation/GroupedSql.php"
    source = table(engine, "accounts")
    count = [[aggregate("count"), "n"], [aggregate("sum", slot(1)), "s"]]
    column_count = case(engine, tag, source, [], [[aggregate("count", slot(1)), "n"]])
    if engine == "mysql":
        key = "$this->operand($key, $query->source, $renderer)"
        wrong_key = "$this->operand(new ColumnReference(1), $query->source, $renderer)"
    else:
        key = "$renderer->expression($key, $query->source)"
        wrong_key = "$renderer->expression(new ColumnReference(1), $query->source)"
    result = [
        RenderFault("erase-aggregate-distinct", target, "($value->distinct ? 'DISTINCT ' : '')", "''",
                    case(engine, tag, source, [], [[aggregate("count", slot(1), True), "n"]]), "meaning-mismatch"),
        RenderFault("count-nulls-as-fields", target, "$value->argument === null ? '*'", "$value->operator === AggregateOperator::Count ? '*'",
                    column_count, "meaning-mismatch"),
        RenderFault("group-by-wrong-field", target, key, wrong_key,
                    case(engine, tag, source, [slot(0)], count), "meaning-mismatch"),
        RenderFault("erase-group-input-filter", target, "if ($query->where !== null)", "if (false)",
                    case(engine, tag, source, [], count, compare(">", slot(1), literal(1))), "meaning-mismatch"),
    ]
    if engine == "mysql":
        role = {"schema": "ir_fixture", "table": LABEL_RELATION, "columns": [" lead"]}
        intrinsic = case(engine, tag, role, [slot(0)], [[slot(0), " lead"], [aggregate("count"), "n"]])
        result.append(RenderFault("alias-physical-group-label", target,
                                  """($name === ltrim($name, " \\t\\r\\n\\v\\f") ? ' AS ' . $column->name->quoted() : '')""",
                                  "(' AS ' . $column->name->quoted())", intrinsic, "meaning-mismatch"))
    if engine == "postgres":
        result.append(RenderFault("unbind-aggregate-routine", target, "'pg_catalog.' . $value->operator->value", "$value->operator->value",
                                  column_count, "meaning-mismatch"))
    return result


if __name__ == "__main__":
    main(sys.modules[__name__])
