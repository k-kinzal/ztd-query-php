"""Compare native projection parameters with an independent bound-value vector."""

import json
import sys

from evidence_transcript import canonical
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import main
from server_parameter_cases import case, expected, finite_id, finite_ids, observation, reconcile_catalog_rejections, ref, requests
from sqlite_domain import RenderFault

LABEL = "parameter"
OBSERVER = "server_parameter_observer.php"
CONTRACT = "d3c7edac68d8e12993dccdddfaa42195c68defde2f4f778b6491b91856db6ab6"


def specifications():
    path, projection = TOOL / "server-parameter-v6.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(projection) == PROJECTION, "server-parameter-contract-mismatch")
    return reconcile_catalog_rejections(json.loads(path.read_text())), json.loads(projection.read_text())["profiles"]


def check_value(item, response):
    require(response["status"] == "constructed", "server-parameter-positive-rejected", response)
    require(response["profile"].startswith(item["release"] + "/"), "server-parameter-profile-mismatch", response)
    require(response["columns"] == item["columns"] and response["source"] is None and response["where"] is None,
            "server-parameter-shape-mismatch", response)


def observer_request(item, sql):
    return {"sql": sql, "bindings": item["bindings"]}


def check_observation(item, actual, sql):
    prediction = expected(item)
    witness = {"sql": sql, "expected": prediction, "observed": actual}
    require(actual["status"] == "rows", "server-parameter-native-rejection", witness)
    require(canonical(prediction) == canonical(actual), "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "server-parameter-fault-native")


def required_forms(engine, _tag):
    common = {"slots/single", "slots/multiple", "column/literal", "column/null", "binding/null",
              "binding/minimum", "binding/maximum", "layout/ascending"}
    return common | ({"layout/reordered", "layout/repeated"} if engine == "postgres" else set())


def sample_forms(forms, response, _, item):
    positions = [expr[1] for expr, _ in response["columns"] if expr[0] == "parameter"]
    forms["slots/" + ("single" if len(set(positions)) == 1 else "multiple")] += 1
    if len(positions) > len(set(positions)):
        forms["layout/repeated"] += 1
    forms["layout/" + ("ascending" if positions == sorted(positions) else "reordered")] += 1
    for expression, _ in response["columns"]:
        if expression[0] == "integer":
            forms["column/literal"] += 1
        elif expression[0] == "null":
            forms["column/null"] += 1
    for value in item["bindings"]:
        boundary = {None: "null", -2147483648: "minimum", 2147483647: "maximum"}.get(value)
        if boundary is not None:
            forms["binding/" + boundary] += 1


def controls():
    item = case("postgres", "pg-17.2", [ref(2), ref(1), ref(2)], [4, -3])
    prediction = expected(item)
    check_observation(item, prediction, "known numbered projection")
    altered = {
        "lost-repeated-position": {**prediction, "rows": [[-3, 4, 4]]},
        "swapped-values": {**prediction, "rows": [[4, -3, 4]]},
        "widened-metadata": {**prediction, "fields": [["a0", "int8"], *prediction["fields"][1:]]},
    }
    detected = {}
    for name, actual in altered.items():
        try:
            check_observation(item, actual, name)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "server-parameter-wrong-control-signal", name)
            detected[name] = failure.signal
        else:
            raise Failure("server-parameter-comparator-accepted", name)
    return {"input": item, "prediction": prediction, "mismatches": altered, "detections": detected}


def outsiders(engine):
    cast = "CAST(? AS SIGNED)" if engine == "mysql" else "CAST($1 AS pg_catalog.int4)"
    return [{"sql": f"SELECT {cast} AS a0; SELECT 1", "bindings": [0]},
            {"sql": f"SELECT {cast} AS a0", "bindings": ["0"]},
            {"sql": f"SELECT {cast} AS a0", "bindings": []}]


def prepare(engine, db):
    return [db.request(item) for item in outsiders(engine)]


def check_preparation(engine, environment, preparation):
    same("PDO native integer or NULL" if engine == "mysql" else "PREPARE with int4 positions", environment["binding"])
    same([{"status": "outside-observer-scope"}] * len(outsiders(engine)), preparation)


def faults(engine, tag):
    root = f"sql-semantics-{engine}/src/Ir/"
    comparison = case(engine, tag, [ref(1), ref(2)], [-11, 23])
    target, alternate = ("AS SIGNED)';", "AS UNSIGNED)';") if engine == "mysql" else ("$this->input->value", "'int8'")
    return [
        RenderFault("discard-projected-parameter", root + "SelectSql.php", "$expression instanceof Parameter => $expression->toString(),",
                    "$expression instanceof Parameter => $expression->toString() . ' * 0',", comparison, "meaning-mismatch"),
        RenderFault("reverse-parameter-outputs", root + "SelectSql.php", "implode(', ', $items)", "implode(', ', array_reverse($items))",
                    comparison, "meaning-mismatch"),
        RenderFault("change-parameter-cast", root + "Binding/Parameter.php", target, alternate, comparison, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
