"""Compare native UPDATE assignment order and effects with an independent evaluator."""

import json
import sys

from evidence_transcript import canonical
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import main, observer_request
from server_cases import SCHEMAS
from server_delete_cases import ROWS, literal, ordered
from server_join_cases import slot
from server_update_cases import case, expected, finite_id, finite_ids, observation, requests
from sqlite_domain import RenderFault

LABEL = "update"
OBSERVER = "server_update_observer.php"
CONTRACT = "38f94fa32e0e19ddbacfba57f71f45e2e0a4ab27b12a6d84b007599a7c142542"


def specifications():
    path, projection = TOOL / "server-update-v2.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(projection) == PROJECTION, "server-update-contract-mismatch")
    return json.loads(path.read_text()), json.loads(projection.read_text())["profiles"]


def check_value(item, response):
    require(response["status"] == "constructed", "server-update-positive-rejected", response)
    require(response["profile"].startswith(item["release"] + "/"), "server-update-profile-mismatch", response)
    require(all(response[key] == item[key] for key in ("target", "assignments", "where")), "server-update-shape-mismatch", response)


def check_observation(item, actual, sql):
    model = expected(item)
    witness = {"sql": sql, "expected": model, "observed": actual}
    require(actual["status"] == "updated", "server-update-native-rejection", witness)
    require(canonical(model) == canonical(actual), "meaning-mismatch", witness)


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "updated", "server-update-fault-native")


def required_forms(engine, _tag):
    width = {"assignments/single", "assignments/multiple", "value/integer", "value/slot", "value/null", "where/absent",
             "where/present", "target/target", "target/control", "target/child", "reads/previously-written"}
    return width | ({"inherit/True", "inherit/False"} if engine == "postgres" else {"writes/repeated"})


def sample_forms(forms, response, engine, _):
    sets = response["assignments"]
    forms["assignments/" + ("single" if len(sets) == 1 else "multiple")] += 1
    written = set()
    for destination, value in sets:
        forms["value/" + value[0]] += 1
        if value[0] == "slot" and value[1] in written:
            forms["reads/previously-written"] += 1
        if destination[1] in written:
            forms["writes/repeated"] += 1
        written.add(destination[1])
    forms["where/" + ("absent" if response["where"] is None else "present")] += 1
    forms["target/" + response["target"]["table"].removeprefix("ir_update_")] += 1
    if engine == "postgres":
        forms[f"inherit/{not response['target']['only']}"] += 1


def controls():
    item = case("postgres", "pg-17.2", ["id", "delta"], [[slot(0), literal(1)], [slot(1), slot(0)]])
    prediction = expected(item)
    check_observation(item, prediction, "known old-row rule")
    altered = {
        "changed-count": {**prediction, "count": 0},
        "removed-trigger": {**prediction, "after": {**prediction["after"], "events": []}},
        "lost-rollback": {**prediction, "restored": prediction["after"]},
    }
    detected = {}
    for name, value in altered.items():
        try:
            check_observation(item, value, name)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "server-update-wrong-control-signal", name)
            detected[name] = failure.signal
        else:
            raise Failure("server-update-comparator-accepted", name)
    return {"input": item, "prediction": prediction, "mismatches": altered, "detections": detected}


def outsider_requests(engine):
    base = SCHEMAS[engine] + "."
    return [
        f"UPDATE {base}ir_unregistered SET id=1",
        f"UPDATE {base}ir_update_target SET id=1; UPDATE {base}ir_update_control SET id=2",
        f"DELETE FROM {base}ir_update_target",
        f"UPDATE {base}ir_update_target SET id=1 WHERE id IN (SELECT id FROM {base}ir_update_control)",
    ]


def prepare(engine, db):
    return {sql: db.request({"sql": sql}) for sql in outsider_requests(engine)}


def check_preparation(engine, environment, preparation):
    same(ordered(ROWS), ordered(environment["initial"]))
    same({sql: {"status": "outside-observer-scope"} for sql in outsider_requests(engine)}, preparation)


def faults(engine, tag):
    root = f"sql-semantics-{engine}/src/Ir/"
    path = root + "Mutation/Update.php"
    names = ["id", "delta"]
    faults = [
        RenderFault("replace-update-destination", path, "$this->target->columns[$assignment->target->slot]",
                    "$this->target->columns[1]", case(engine, tag, names, [[slot(0), literal(3)]]), "meaning-mismatch"),
        RenderFault("omit-update-where", path, "if ($this->where !== null)", "if (false)",
                    case(engine, tag, names, [[slot(0), literal(3)]], ["boolean", False]), "meaning-mismatch"),
        RenderFault("invert-update-null-test", root + "SelectSql.php",
                    "? ' IS NOT NULL)' : ' IS NULL)'", "? ' IS NULL)' : ' IS NOT NULL)'",
                    case(engine, tag, names, [[slot(0), literal(3)]], ["null-test", False, slot(1)]), "meaning-mismatch"),
    ]
    if engine == "postgres":
        faults.append(RenderFault("omit-update-only", root + "SelectSql.php", "($source->only ? 'ONLY ' : '')",
                                  "''", case(engine, tag, names, [[slot(0), literal(7)]], only=True), "meaning-mismatch"))
    else:
        faults.append(RenderFault("reverse-update-assignments", path, "implode(', ', $parts)",
                                  "implode(', ', array_reverse($parts))", case(
                                      engine, tag, names, [[slot(0), literal(7)], [slot(1), slot(0)]]), "meaning-mismatch"))
    return faults


if __name__ == "__main__":
    main(sys.modules[__name__])
