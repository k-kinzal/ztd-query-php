"""Compare native DROP TABLE catalog changes with an independent effect model."""

import json
import sys

from evidence_transcript import canonical
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import main
from server_cases import SCHEMAS
from server_drop_table_cases import ROWS, case, expected, finite_id, finite_ids, observation, requests
from sqlite_domain import RenderFault

LABEL = "drop-table"
OBSERVER = "server_drop_table_observer.php"
CONTRACT = "506d7160b2e02d420a8ffb53c655593162740a00834dd1cef6441be6aa0f67cb"


def specifications():
    path, projection = TOOL / "server-drop-table-v4.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(projection) == PROJECTION
            and digest(TOOL / "server-drop-table-v3.json") == "7eb385b083a3b17a19155a2644fdd58247c1271c42cac650232058e849911226"
            and digest(TOOL / "server-drop-table-v2.json") == "e0ffff4fc165236fafb583316d1ff8f1ce0a17956089ded17bd65cfc9011b022", "server-drop-table-contract-mismatch")
    return json.loads(path.read_text()), json.loads(projection.read_text())["profiles"]


def check_value(item, response):
    require(response["status"] == "constructed", "server-drop-table-positive-rejected", response)
    require(response["profile"].startswith(item["release"] + "/"), "server-drop-table-profile-mismatch", response)
    require(response["drop"] == item["drop"], "server-drop-table-shape-mismatch", response)


def observer_request(item, sql):
    return {"sql": sql, "dependent": item["dependent"]}


def check_observation(item, actual, sql):
    prediction = expected(item)
    witness = {"sql": sql, "expected": prediction, "observed": actual}
    require(actual["status"] in {"dropped", "42S02", "42P01", "2BP01"}, "server-drop-table-unregistered-native-result", witness)
    require(canonical(prediction) == canonical(actual), "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] in {"dropped", "42S02", "42P01", "2BP01"}, "server-drop-table-fault-native")


def required_forms(engine, _tag):
    common = {"width/1", "width/2", "width/3", "width/4", "if-exists/False", "if-exists/True", "dependent/False",
              "dependent/True", "target/a", "target/b", "target/control", "target/missing"}
    return common | ({"cascade/False", "cascade/True"} if engine == "postgres" else set())


def sample_forms(forms, response, engine, item):
    model = response["drop"]
    forms[f"width/{len(model['targets'])}"] += 1
    forms[f"if-exists/{model['ifExists']}"] += 1
    forms[f"dependent/{item['dependent']}"] += 1
    for _, target in model["targets"]:
        forms["target/" + target.removeprefix("ir_drop_")] += 1
    if engine == "postgres":
        forms[f"cascade/{model['cascade']}"] += 1


def controls():
    item = case("postgres", "pg-17.2", ["a"], cascade=True, dependent=True)
    prediction = expected(item)
    check_observation(item, prediction, "known transactional cascade")
    after = prediction["effect"]
    altered = {
        "wrong-view": {**prediction, "effect": {**after, "view": True}},
        "erased-restoration": {**prediction, "restored": after},
        "changed-retained-type": {**prediction, "effect": {**after, "rows": {**after["rows"], "b": ["23"]}}},
    }
    detected = {}
    for name, actual in altered.items():
        try:
            check_observation(item, actual, name)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "server-drop-table-wrong-control-signal", name)
            detected[name] = failure.signal
        else:
            raise Failure("server-drop-table-comparator-accepted", name)
    return {"input": item, "prediction": prediction, "mismatches": altered, "detections": detected}


def outsiders(engine):
    base = SCHEMAS[engine] + "."
    return ["DROP TABLE " + base + "unregistered", "DROP TABLE " + base + "ir_drop_marker", "DROP TABLE " + base + "ir_drop_a; SELECT 1"]


def prepare(engine, db):
    return {sql: db.request({"sql": sql, "dependent": False}) for sql in outsiders(engine)}


def check_preparation(engine, environment, preparation):
    same({"rows": {**ROWS, "marker": [0]}, "view": False}, environment["initial"])
    same({sql: {"status": "outside-observer-scope"} for sql in outsiders(engine)}, preparation)


def faults(engine, tag):
    path = f"sql-semantics-{engine}/src/Ir/Schema/DropTable.php"
    control = SCHEMAS[engine] + ".ir_drop_control"
    cases = [
        RenderFault("address-another-table", path, "$names[] = $target->quoted()", "$names[] = " + repr(control), case(engine, tag, ["a"]), "meaning-mismatch"),
        RenderFault("discard-second-target", path, "implode(', ', $names)", "implode(', ', array_slice($names, 0, 1))",
                    case(engine, tag, ["a", "b"]), "meaning-mismatch"),
        RenderFault("invert-missing-policy", path, "$this->ifExists ? 'IF EXISTS '", "!$this->ifExists ? 'IF EXISTS '",
                    case(engine, tag, ["a", "missing"], if_exists=True), "meaning-mismatch"),
    ]
    if engine == "postgres":
        cases.append(RenderFault("restrict-instead-of-cascade", path, "$this->cascade ? ' CASCADE'", "false ? ' CASCADE'",
                                 case(engine, tag, ["a"], cascade=True, dependent=True), "meaning-mismatch"))
    return cases


if __name__ == "__main__":
    main(sys.modules[__name__])
