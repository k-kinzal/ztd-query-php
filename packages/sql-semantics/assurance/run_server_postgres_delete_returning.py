"""Compare returned DELETE columns and physical PostgreSQL effects."""

import json
import sys

from postgres_delete_returning_cases import expected, finite_id, finite_ids, observation, positives, project, requests
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_delete import check_value as checked_delete, observer_probes
from run_server_join import main
from server_delete_cases import ROWS, negatives, ordered
from sqlite_domain import RenderFault

LABEL = "postgres-delete-returning"
CONTRACT = "55f8de4b507bf8105763afa05c30d28957c874f16d825ee8e8652cd473bf26a6"
OBSERVER = "server_delete_observer.php"


def specifications():
    path, projection = TOOL / "postgres-delete-returning-v3.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(projection) == PROJECTION, "postgres-delete-returning-contract-mismatch")
    record = json.loads(path.read_text())
    require(len(finite_ids("postgres", record, "pg-17.2")) == record["finite"]["identities"], "postgres-delete-returning-domain-size")
    previous = record["predecessorCheck"]
    same(previous["previousWitness"], next(positives("pg-17.2"))[0]["sql"])
    replacement = [rejection for request, rejection in negatives("postgres", "pg-17.2")
                   if request.get("sql") == previous["replacementWitness"]]
    same(["Complete PostgreSQL DELETE requires"], replacement)
    return record, {"postgres": json.loads(projection.read_text())["profiles"]["postgres"]}


def observer_request(_, sql):
    return {"sql": sql}


def unsafe_statements(engine):
    return (*observer_probes(engine),
            "DELETE FROM public.ir_delete_target RETURNING id; DELETE FROM public.ir_delete_control",
            "DELETE FROM public.ir_delete_target RETURNING (SELECT id FROM public.ir_delete_control)")


def prepare(engine, db):
    inactive = "DELETE FROM public.ir_delete_target WHERE FALSE RETURNING id, delta"
    return {
        "outOfScope": [db.request({"sql": sql}) for sql in unsafe_statements(engine)],
        "inactive": db.request({"sql": inactive}),
    }


def check_preparation(engine, environment, prepared):
    initial = ordered(ROWS)
    same(initial, ordered(environment["initial"]))
    same([{"status": "outside-observer-scope"}] * len(unsafe_statements(engine)), prepared["outOfScope"])
    same({"status": "deleted", "count": 0, "columns": 2, "fields": [["id", "int4"], ["delta", "int4"]],
          "rows": [], "after": initial, "restored": initial}, observation(prepared["inactive"]))


def check_value(item, response):
    checked_delete(item, response)
    same(item["returning"], response["returning"])


def check_observation(item, actual, sql):
    prediction = expected(item)
    require(prediction == actual, "meaning-mismatch", {"sql": sql, "expected": prediction, "observed": actual})


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "deleted", "postgres-delete-returning-fault-native")


def required_forms(_engine, _tag):
    return {"policy/False", "policy/True", "target/target", "target/child", "layout/1", "layout/5", "layout/repeated-slot",
            "layout/repeated-label", "layout/reordered", "where/omitted", "where/boolean", "where/comparison", "where/null-test"}


def sample_forms(forms, measured, _engine, _item):
    target = measured["target"]
    forms[f"policy/{target['only']}"] += 1
    forms["target/" + target["table"].removeprefix("ir_delete_")] += 1
    forms[f"layout/{len(measured['returning'])}"] += 1
    slots = [ref[1] for ref, _ in measured["returning"]]
    names = [name for _, name in measured["returning"]]
    if len(slots) != len(set(slots)):
        forms["layout/repeated-slot"] += 1
    if len(names) != len(set(names)):
        forms["layout/repeated-label"] += 1
    if slots != sorted(slots):
        forms["layout/reordered"] += 1
    condition = measured["where"]
    forms["where/" + ("omitted" if condition is None else condition[0])] += 1


def controls():
    item = project("pg-17.2", [(0, "left"), (1, "right")])
    correct = expected(item)
    bad = {
        "hide-label": {**correct, "fields": [["gone", "int4"], ["right", "int4"]]},
        "widen-type": {**correct, "fields": [["left", "int8"], ["right", "int4"]]},
        "erase-duplicate": {**correct, "rows": list(map(list, dict.fromkeys(map(tuple, correct["rows"]))))},
        "erase-deletion-event": {**correct, "after": {**correct["after"], "events": correct["after"]["events"][1:]}},
    }
    check_observation(item, correct, "healthy")
    records = {}
    for name, altered in bad.items():
        try:
            check_observation(item, altered, name)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "postgres-delete-returning-control-signal", name)
            records[name] = {"input": item, **error.detail}
        else:
            raise Failure("postgres-delete-returning-accepted-control", name)
    return records


def faults(_engine, tag):
    path = "sql-semantics-postgres/src/Ir/Mutation/ReturningSql.php"
    item = project(tag, [(0, "left"), (1, "right")])
    return [
        RenderFault("omit-delete-returning", path, "return $fields === [] ? '' : ' RETURNING ' . implode(', ', $fields);",
                    "return '';", item, "meaning-mismatch"),
        RenderFault("swap-delete-returned-slot", path, "$target->columns[$field->reference->slot]",
                    "$target->columns[1 - $field->reference->slot]", item, "meaning-mismatch"),
        RenderFault("reverse-delete-output-layout", path, "foreach ($returning as $field)",
                    "foreach (array_reverse($returning) as $field)", item, "meaning-mismatch"),
        RenderFault("replace-delete-return-label", path, "$field->name->quoted()", "'\"altered\"'", item, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
