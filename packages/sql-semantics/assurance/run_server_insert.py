"""Compare native literal INSERT against an independent typed effect model."""

import json
import sys

from evidence_transcript import canonical
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import main, observer_request
from server_cases import SCHEMAS
from server_insert_cases import INITIAL, case, expected, finite_id, finite_ids, observation, ordered, requests
from sqlite_domain import RenderFault

LABEL = "insert"
OBSERVER = "server_insert_observer.php"
CONTRACT = "0456225d41ef56fa2e8271102caae8ebb225bf9c76372eaaaa18126b2d8b0f79"


def specifications():
    path, projection = TOOL / "server-insert-v1.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(projection) == PROJECTION, "server-insert-contract-mismatch")
    return json.loads(path.read_text()), json.loads(projection.read_text())["profiles"]


def check_value(item, response):
    require(response["status"] == "constructed", "server-insert-positive-rejected", response)
    require(response["profile"].startswith(item["release"] + "/"), "server-insert-profile-mismatch", response)
    require(response["target"] == item["target"] and response["rows"] == item["rows"], "server-insert-shape-mismatch", response)


def check_observation(item, actual, sql):
    prediction = expected(item)
    witness = {"sql": sql, "expected": prediction, "observed": actual}
    require(actual["status"] == "inserted", "server-insert-native-rejection", witness)
    require(canonical(prediction) == canonical(actual), "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "inserted", "server-insert-fault-native")


def required_forms(_engine, _tag):
    return {"width/one", "width/wide", "rows/one", "rows/multiple", "rows/repeated",
            "cell/null", "cell/min", "cell/max", "defaults/omitted", "defaults/all-specified"}


def sample_forms(forms, response, _engine, _item):
    columns, rows = response["target"]["columns"], response["rows"]
    forms["width/" + ("one" if len(columns) == 1 else "wide")] += 1
    forms["rows/" + ("one" if len(rows) == 1 else "multiple")] += 1
    if len(rows) > len({canonical(row) for row in rows}):
        forms["rows/repeated"] += 1
    forms["defaults/" + ("all-specified" if len(columns) == 3 else "omitted")] += 1
    extremes = {None: "null", "-2147483648": "min", "2147483647": "max"}
    for row in rows:
        for cell in row:
            if cell in extremes:
                forms["cell/" + extremes[cell]] += 1


def controls():
    item = case("mysql", "mysql-8.4.7", ["id", "delta"], [["1", "2"], ["1", "2"]])
    healthy = expected(item)
    check_observation(item, healthy, "known effect")
    problems = {
        "count": {**healthy, "count": 1},
        "duplicates": {**healthy, "after": {**healthy["after"], "target": healthy["after"]["target"][1:]}},
        "trigger": {**healthy, "after": {**healthy["after"], "events": []}},
        "rollback": {**healthy, "restored": healthy["after"]},
    }
    signals = {}
    for label, actual in problems.items():
        try:
            check_observation(item, actual, label)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "server-insert-wrong-control-signal", label)
            signals[label] = failure.signal
        else:
            raise Failure("server-insert-comparator-accepted", label)
    return {"input": item, "healthy": healthy, "mismatches": problems, "detections": signals}


def outsider_requests(engine):
    root = SCHEMAS[engine] + "."
    return [
        "INSERT INTO " + root + "ir_unregistered (id) VALUES (1)",
        "INSERT INTO " + root + "ir_insert_target (id) VALUES (1); INSERT INTO " + root + "ir_insert_control (id) VALUES (2)",
        "UPDATE " + root + "ir_insert_target SET id = 0",
    ]


def prepare(engine, db):
    return {sql: db.request({"sql": sql}) for sql in outsider_requests(engine)}


def check_preparation(engine, environment, preparation):
    same(ordered(INITIAL), ordered(environment["initial"]))
    same({sql: {"status": "outside-observer-scope"} for sql in outsider_requests(engine)}, preparation)


def faults(engine, tag):
    path = f"sql-semantics-{engine}/src/Ir/Mutation/Insert.php"
    destination = path if engine == "mysql" else path.removesuffix(".php") + "/Destination.php"
    target_name = "$target->table->quoted()" if engine == "mysql" else "$this->relation->table->quoted()"
    sample = case(engine, tag, ["id", "delta"], [["2", "3"], [None, "1"]])
    delimiter = chr(96) if engine == "mysql" else '"'
    return [
        RenderFault("swap-insert-target-columns", destination, "implode(', ', $columns)", "implode(', ', array_reverse($columns))",
                    sample, "meaning-mismatch"),
        RenderFault("omit-insert-final-row", path, "implode(', ', $rows)", "implode(', ', array_slice($rows, 0, -1))",
                    sample, "meaning-mismatch"),
        RenderFault("redirect-insert-target", destination, target_name, f"'{delimiter}ir_insert_control{delimiter}'",
                    sample, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
