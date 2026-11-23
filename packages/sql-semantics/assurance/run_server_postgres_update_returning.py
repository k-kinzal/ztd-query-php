"""Compare PostgreSQL UPDATE effects and ordered new-row output fields."""

import json
import sys

from postgres_update_returning_cases import ASSIGNMENTS, expected, finite_id, finite_ids, observation, project, requests
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_join import main, observer_request
from run_server_update import check_value as checked_update, outsider_requests, sample_forms as sample_update_forms
from server_delete_cases import ROWS, ordered
from server_returning_model import project_returned
from sqlite_domain import RenderFault

LABEL = "postgres-update-returning"
CONTRACT = "19861e0e485e9c024118dce01b45d259e431641ed253dae4e5f856b908985831"
OBSERVER = "server_update_observer.php"


def specifications():
    path, projection = TOOL / "postgres-update-returning-v4.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(projection) == PROJECTION, "postgres-update-returning-contract-mismatch")
    rules = json.loads(path.read_text())
    require(len(finite_ids("postgres", rules, "pg-17.2")) == rules["finite"]["identities"], "postgres-update-returning-domain-size")
    return rules, {"postgres": json.loads(projection.read_text())["profiles"]["postgres"]}


def inadmissible(engine):
    return (*outsider_requests(engine),
            "UPDATE public.ir_update_target SET id=1 RETURNING id; UPDATE public.ir_update_control SET id=8",
            "UPDATE public.ir_update_target SET id=1 RETURNING (SELECT id FROM public.ir_update_control)")


def prepare(engine, db):
    inactive = "UPDATE public.ir_update_target SET id=1 WHERE FALSE RETURNING delta, id"
    return {"outside": [db.request({"sql": sql}) for sql in inadmissible(engine)], "inactive": db.request({"sql": inactive})}


def check_preparation(engine, environment, prepared):
    only = ordered(ROWS)
    same(only, ordered(environment["initial"]))
    same([{"status": "outside-observer-scope"}] * len(inadmissible(engine)), prepared["outside"])
    same({"status": "updated", "count": 0, "columns": 2, "fields": [["delta", "int4"], ["id", "int4"]],
          "rows": [], "after": only, "restored": only}, observation(prepared["inactive"]))


def check_value(item, response):
    checked_update(item, response)
    same(item["returning"], response["returning"])


def check_observation(item, actual, sql):
    prediction = expected(item)
    require(prediction == actual, "meaning-mismatch", {"sql": sql, "expected": prediction, "observed": actual})


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "updated", "postgres-update-returning-fault-native")


def required_forms(_engine, _tag):
    return {"assignments/single", "assignments/multiple", "reads/previously-written", "value/null", "value/slot",
            "inherit/True", "inherit/False", "where/absent", "where/present", "layout/repeated-slot", "layout/repeated-label",
            "layout/reordered", "width/1", "width/5", "target/target", "target/control", "target/child"}


def sample_forms(forms, response, engine, item):
    sample_update_forms(forms, response, engine, item)
    slots = [ref[1] for ref, _ in response["returning"]]
    labels = [label for _, label in response["returning"]]
    forms[f"width/{len(slots)}"] += 1
    if len(set(slots)) != len(slots):
        forms["layout/repeated-slot"] += 1
    if len(set(labels)) != len(labels):
        forms["layout/repeated-label"] += 1
    if slots != sorted(slots):
        forms["layout/reordered"] += 1


def controls():
    item = project("pg-17.2", [(0, "new_id"), (1, "new_delta")], ASSIGNMENTS[0])
    healthy = expected(item)
    incorrect = {
        "return-old-values": project_returned(item, healthy, (event[1:3] for event in healthy["after"]["events"])),
        "widen-int4": {**healthy, "fields": [["new_id", "int8"], ["new_delta", "int4"]]},
        "drop-duplicates": {**healthy, "rows": list(map(list, dict.fromkeys(map(tuple, healthy["rows"]))))},
        "change-output-label": {**healthy, "fields": [["old_id", "int4"], ["new_delta", "int4"]]},
    }
    check_observation(item, healthy, "healthy")
    result = {}
    for name, bad in incorrect.items():
        try:
            check_observation(item, bad, name)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "postgres-update-returning-control-signal", name)
            result[name] = {"input": item, **error.detail}
        else:
            raise Failure("postgres-update-returning-accepted-control", name)
    return result


def faults(_engine, tag):
    path = "sql-semantics-postgres/src/Ir/Mutation/ReturningSql.php"
    item = project(tag, [(0, "left"), (1, "right")], ASSIGNMENTS[1])
    return [
        RenderFault("omit-update-returning", path, "return $fields === [] ? '' : ' RETURNING ' . implode(', ', $fields);",
                    "return '';", item, "meaning-mismatch"),
        RenderFault("swap-update-returned-slot", path, "$target->columns[$field->reference->slot]",
                    "$target->columns[1 - $field->reference->slot]", item, "meaning-mismatch"),
        RenderFault("reverse-update-output-layout", path, "foreach ($returning as $field)",
                    "foreach (array_reverse($returning) as $field)", item, "meaning-mismatch"),
        RenderFault("replace-update-return-label", path, "$field->name->quoted()", "'\"altered\"'", item, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
