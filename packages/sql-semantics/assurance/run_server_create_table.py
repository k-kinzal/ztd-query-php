"""Compare native table definitions with an independent catalog and default model."""

import json
import sys

from evidence_transcript import canonical
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import main
from server_cases import SCHEMAS
from server_create_table_cases import TYPES, case, expected, finite_id, finite_ids, observation, requests
from server_delete_cases import literal
from sqlite_domain import RenderFault

LABEL = "create-table"
OBSERVER = "server_create_table_observer.php"
CONTRACT = "b1c5f91edac3d4be6e304600ba2b480ee45497fd51e84ee90597f13a8ec6911c"


def specifications():
    path, projection = TOOL / "server-create-table-v2.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(projection) == PROJECTION, "server-create-table-contract-mismatch")
    return json.loads(path.read_text()), json.loads(projection.read_text())["profiles"]


def check_value(item, response):
    require(response["status"] == "constructed", "server-create-table-positive-rejected", response)
    require(response["profile"].startswith(item["release"] + "/"), "server-create-table-profile-mismatch", response)
    require(response["create"] == item["create"], "server-create-table-shape-mismatch", response)


def observer_request(item, sql):
    return {"sql": sql, "occupied": item["occupied"]}


def check_observation(item, actual, sql):
    prediction = expected(item)
    witness = {"sql": sql, "expected": prediction, "observed": actual}
    require(actual["status"] in {"executed", "42S01", "42P07"}, "server-create-table-unregistered-native-result", witness)
    require(canonical(prediction) == canonical(actual), "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] in {"executed", "42S01", "42P07"}, "server-create-table-fault-native")


def required_forms(engine, tag):
    return {"type/" + kind for kind in TYPES} | {"width/single", "width/multiple", "prior/True", "prior/False", "optional/True",
           "optional/False", "nullability/True", "nullability/False", "default/absent", "default/null", "default/integer"}


def sample_forms(forms, response, engine, item):
    model = response["create"]
    forms["width/" + ("single" if len(model["columns"]) == 1 else "multiple")] += 1
    forms[f"prior/{item['occupied']}"] += 1
    forms[f"optional/{model['ifNotExists']}"] += 1
    for _, storage, nullable, default in model["columns"]:
        forms["type/" + storage] += 1
        forms[f"nullability/{nullable}"] += 1
        forms["default/" + ("absent" if default is None else default[0])] += 1


def controls():
    item = case("postgres", "pg-17.2", [["c0", "SMALLINT", False, literal(7)], ["c1", "INTEGER", True, None]])
    prediction = expected(item)
    check_observation(item, prediction, "known catalog and defaults")
    catalog = prediction["catalog"]
    altered = {
        "changed-column-positions": {**prediction, "catalog": {**catalog, "columns": list(reversed(catalog["columns"]))}},
        "changed-default-type": {**prediction, "defaultInsertion": {"status": "inserted", "values": ["7", None]}},
        "lost-rollback": {**prediction, "afterRollback": catalog},
    }
    detected = {}
    for name, actual in altered.items():
        try:
            check_observation(item, actual, name)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "server-create-table-wrong-control-signal", name)
            detected[name] = failure.signal
        else:
            raise Failure("server-create-table-comparator-accepted", name)
    return {"input": item, "prediction": prediction, "mismatches": altered, "detections": detected}


def outsiders(engine):
    base = SCHEMAS[engine] + "."
    return [f"CREATE TABLE {base}unregistered (c0 INTEGER)",
            f"CREATE TABLE {base}ir_create_target (c0 INTEGER); SELECT 1", f"DROP TABLE {base}ir_create_target"]


def prepare(engine, db):
    return {sql: db.request({"sql": sql, "occupied": False}) for sql in outsiders(engine)}


def check_preparation(engine, environment, preparation):
    same("InnoDB" if engine == "mysql" else "heap", environment["storage"])
    same([0], environment["marker"])
    same({sql: {"status": "outside-observer-scope"} for sql in outsiders(engine)}, preparation)


def faults(engine, tag):
    root = f"sql-semantics-{engine}/src/Ir/Schema/"
    fields = [["c0", "SMALLINT", False, literal(-11)], ["c1", "INTEGER", True, None]]
    return [
        RenderFault("reverse-column-order", root + "CreateTable.php", "implode(', ', $columns)", "implode(', ', array_reverse($columns))",
                    case(engine, tag, fields), "meaning-mismatch"),
        RenderFault("widen-signed-column", root + "TableColumn.php", "$this->storage->value", "'BIGINT'",
                    case(engine, tag, fields[:1]), "meaning-mismatch"),
        RenderFault("replace-declared-default", root + "TableColumn.php", "' DEFAULT ' . $this->default->decimal", "' DEFAULT 0'",
                    case(engine, tag, fields[:1]), "meaning-mismatch"),
        RenderFault("invert-create-if-not-exists", root + "CreateTable.php", "$this->ifNotExists ? 'IF NOT EXISTS '", "!$this->ifNotExists ? 'IF NOT EXISTS '",
                    case(engine, tag, fields[:1], optional=True, occupied=True), "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
