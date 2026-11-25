"""Compare resolved native local slots against independent projected row values."""

import json
import sys

from evidence_transcript import canonical
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import CONTRACT as JOIN, OBSERVER, main, observer_request, prepare as prepare_join
from server_join_cases import fixture
from server_compound_cases import leaf
from server_local_projection_cases import base_definition, case, definition, expected, finite_id, finite_ids, observation, requests, structural_model
from sqlite_domain import RenderFault

LABEL = "local-projection"
CONTRACT = "dbbc9157fd7d3e8ec74967bfb483346aad4dfd65df353f3bf190731c0243cfb9"


def specifications():
    path, projection, join = (TOOL / filename for filename in ("server-local-projection-v4.json", "server-projection-v1.json", "server-join-v2.json"))
    require(all(digest(file) == checksum for file, checksum in ((path, CONTRACT), (projection, PROJECTION), (join, JOIN))),
            "server-local-projection-contract-mismatch")
    return json.loads(path.read_text()), json.loads(projection.read_text())["profiles"]


def check_value(item, response):
    require(response["status"] == "constructed", "server-local-projection-positive-rejected", response)
    require(response["profile"].startswith(item["release"] + "/"), "server-local-projection-profile-mismatch", response)
    if item["dialect"] == "mysql":
        suffix = "/materialized" if item["release"].startswith("mysql-5.6.") else "/derived-merge=on"
        require(response["profile"].endswith(suffix), "server-local-projection-optimizer-profile-mismatch", response)
    actual = expand_local(response)
    require(canonical(structural_model(item)) == canonical(actual["local"]), "server-local-projection-shape-mismatch", response)


def expand_local(query):
    if "with" in query:
        projection = query["with"]
        require(len(projection["definitions"]) == 1 and "read" in projection["body"],
                "server-local-projection-constructor-shape", projection)
        (policy, definition), = projection["definitions"]
        source, columns = projection["body"]["read"]
        require(policy == "" and source == 0, "server-local-projection-constructor-binding", projection)
        return {"local": {"definition": expand_local(definition), "columns": columns}}
    if "local" in query:
        projection = query["local"]
        return {"local": {**projection, "definition": expand_local(projection["definition"])}}
    return {key: query[key] for key in ("columns", "source", "where", "distinct", "orderBy", "limit")}


def check_observation(item, actual, sql):
    prediction = expected(item)
    witness = {"sql": sql, "expected": prediction, "observed": actual}
    require(actual["status"] == "rows", "server-local-projection-native-rejection", witness)
    require(canonical(prediction) == canonical(actual), "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "server-local-projection-fault-native")


def prepare(engine, db):
    if engine != "mysql" or db.environment["database"][0].startswith("5.6."):
        return prepare_join(engine, db)
    setup = db.request({"fixture": ["SET SESSION optimizer_switch = 'derived_merge=on'", *fixture(engine)]})
    optimizations = db.request({"sql": "SELECT @@SESSION.optimizer_switch AS settings"})
    return {"fixture": setup, "optimizer": optimizations}


def check_preparation(engine, environment, preparation):
    if engine != "mysql" or environment["database"][0].startswith("5.6."):
        same({"status": "fixture"}, preparation)
        return
    same({"status": "fixture"}, preparation["fixture"])
    optimizer = preparation["optimizer"]
    require(optimizer["status"] == "rows" and len(optimizer["rows"]) == 1
            and "derived_merge=on" in optimizer["rows"][0][0].split(","), "server-local-projection-optimizer-setup", optimizer)


def required_forms(_engine, _tag):
    return {"source/absent", "source/present", "order/repeated", "order/reordered", "width/single", "width/multiple",
            "distinct/True", "distinct/False", "field/literal", "field/reference", "field/null", "depth/1", "depth/2", "depth/8"}


def sample_forms(forms, response, _, _item):
    local = response["local"]
    definition_, depth = base_definition(local)
    forms[f"depth/{depth}"] += 1
    forms["source/" + ("present" if definition_["source"] is not None else "absent")] += 1
    forms[f"distinct/{definition_['distinct']}"] += 1
    positions = [slot for slot, _ in local["columns"]]
    forms["width/" + ("single" if len(positions) == 1 else "multiple")] += 1
    if len(set(positions)) < len(positions):
        forms["order/repeated"] += 1
    if positions != sorted(positions):
        forms["order/reordered"] += 1
    for expr, _ in definition_["columns"]:
        forms["field/" + {"slot": "reference", "integer": "literal", "null": "null"}[expr[0]]] += 1


def controls():
    tag = "pg-17.2"
    item = case("postgres", tag, definition("postgres", tag, None), [1, 0, 1])
    prediction = expected(item)
    check_observation(item, prediction, "known local slot order")
    altered = {
        "misordered-local-slots": {**prediction, "rows": [[-3, 9, -3]]},
        "different-output-name": {**prediction, "fields": [["c1", "int4"], *prediction["fields"][1:]]},
        "lost-field-type": {**prediction, "fields": [["r0", "int8"], *prediction["fields"][1:]]},
    }
    detected = {}
    for name, actual in altered.items():
        try:
            check_observation(item, actual, name)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "server-local-projection-wrong-control-signal", name)
            detected[name] = failure.signal
        else:
            raise Failure("server-local-projection-comparator-accepted", name)
    return {"input": item, "prediction": prediction, "mismatches": altered, "detections": detected}


def faults(engine, tag):
    target = f"sql-semantics-{engine}/src/Ir/Rows/LocalProjectionSql.php"
    synthetic = chr(96) if engine == "mysql" else '"'
    wrong_query = repr("SELECT -19 AS " + synthetic + "__ztd_c0" + synthetic)
    if engine == "mysql":
        definition_render = "$source instanceof Select => $renderer->render($source, !$retain),"
        wrong_definition = "$source instanceof Select => " + wrong_query + ","
    else:
        definition_render = "$renderer->render($source, true)"
        wrong_definition = wrong_query
    result = [
        RenderFault("misaddress-local-slot", target, "$renderer->localColumn($column->slot)", "$renderer->localColumn(0)",
                    case(engine, tag, definition(engine, tag, None), [1, 0]), "meaning-mismatch"),
        RenderFault("remove-local-row-multiplicity", target, "return 'SELECT '", "return 'SELECT DISTINCT '",
                    case(engine, tag, definition(engine, tag, "accounts"), [0]), "meaning-mismatch"),
        RenderFault("replace-local-definition", target, definition_render, wrong_definition,
                    case(engine, tag, definition(engine, tag, None), [0]), "meaning-mismatch"),
        RenderFault("bypass-nested-slot-layout", target, "$this->render($source, true)", "$renderer->render($source->definition, true)",
                    case(engine, tag, case(engine, tag, definition(engine, tag, None), [1, 0]), [0]), "meaning-mismatch"),
    ]
    if engine == "mysql":
        padded = leaf(engine, tag, [[["integer", "1", 9, False], "c0"]])
        result.append(RenderFault("erase-materialized-integer-width", "sql-semantics-mysql/src/Ir/SelectSql.php",
                                  "$expression instanceof IntegerLiteral, $expression instanceof RangeIntegerLiteral, "
                                  "$expression instanceof ProjectedIntegerLiteral, $expression instanceof TextLiteral => $expression->toString(),",
                                  "$expression instanceof IntegerLiteral => $expression->decimal, "
                                  "$expression instanceof RangeIntegerLiteral, $expression instanceof ProjectedIntegerLiteral, "
                                  "$expression instanceof TextLiteral => $expression->toString(),",
                                  case(engine, tag, padded, [0]), "meaning-mismatch"))
    return result


if __name__ == "__main__":
    main(sys.modules[__name__])
