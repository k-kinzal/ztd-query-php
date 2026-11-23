"""Compare PostgreSQL VALUES and SELECT writes with native RETURNING effects."""

import json
import sys

from postgres_insert_returning_cases import expected, finite_id, finite_ids, insertion_case, observation, requests, select, structure
from run import TOOL, Failure, digest, require
from run_server import CONTRACT as PROJECTION, same
from run_server_insert_query import check_preparation as checked_fixture, prepare as prepared_fixture
from run_server_join import main
from server_join_cases import slot
from sqlite_domain import RenderFault

LABEL = "postgres-insert-returning"
CONTRACT = "54420253749f6b350ac14815bab57448fe05d78ff8e1c635978ee05e9489ae2d"
OBSERVER = "server_insert_query_observer.php"


def specifications():
    path, projection = TOOL / "postgres-insert-returning-v2.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(projection) == PROJECTION, "postgres-insert-returning-contract-mismatch")
    record = json.loads(path.read_text())
    require(len(finite_ids("postgres", record, "pg-17.2")) == record["finite"]["identities"], "postgres-insert-returning-domain-size")
    return record, {"postgres": json.loads(projection.read_text())["profiles"]["postgres"]}


def observer_request(item, sql):
    return {"sql": sql, "form": "values" if "rows" in item else "select"}


def prepare(engine, db):
    shots = {
        "stacked": ("values", "INSERT INTO public.ir_insert_target (id) VALUES (7) RETURNING id; DELETE FROM public.ir_insert_control"),
        "subquery": ("select", "INSERT INTO public.ir_insert_target (id) SELECT 7 AS p0 RETURNING (SELECT id FROM public.ir_insert_control)"),
    }
    return {"fixture": prepared_fixture(engine, db),
            "outside": {name: db.request({"sql": sql, "form": form}) for name, (form, sql) in shots.items()}}


def check_preparation(engine, environment, prepared):
    checked_fixture(engine, environment, prepared["fixture"])
    same({name: {"status": "outside-observer-scope"} for name in ("stacked", "subquery")}, prepared["outside"])


def check_value(item, response):
    require(response["status"] == "constructed" and response["profile"].startswith(item["release"] + "/"),
            "postgres-insert-returning-positive-rejected", response)
    same(structure(item), structure(response))


def check_observation(item, actual, sql):
    prediction = expected(item)
    require(actual == prediction, "meaning-mismatch", {"sql": sql, "expected": prediction, "observed": actual})


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "inserted", "postgres-insert-returning-fault-native")


def required_forms(_engine, _tag):
    return {"form/VALUES", "form/SELECT", "query/unit", "query/ir_insert_scan", "query/ir_insert_zero",
            "writes/reordered", "writes/3", "writes/1", "layout/repeat", "layout/unwritten", "layout/5",
            "read-distinct-policy/True", "read-distinct-policy/False"}


def sample_forms(forms, response, _engine, _item):
    written = [value[1] for value in response["written"]]
    returns = [value[1] for value, _ in response["returning"]]
    forms[f"writes/{len(written)}"] += 1
    forms[f"layout/{len(returns)}"] += 1
    if written != sorted(written):
        forms["writes/reordered"] += 1
    if len(set(returns)) != len(returns):
        forms["layout/repeat"] += 1
    if set(returns) - set(written):
        forms["layout/unwritten"] += 1
    if "rows" in response:
        forms["form/VALUES"] += 1
    else:
        query = response["insertQuery"]
        forms["form/SELECT"] += 1
        forms["query/" + ("unit" if query["source"] is None else query["source"]["table"])] += 1
        forms[f"read-distinct-policy/{query['distinct']}"] += 1


def controls():
    item = insertion_case("pg-17.2", ["id"], [("id", "out"), ("stamp", "out")], rows=[["7"], ["7"]])
    correct = expected(item)
    wrong = {
        "drop-default": {**correct, "rows": [[7, None], [7, None]]},
        "remove-duplicate": {**correct, "rows": [[7, 9]]},
        "replace-position": {**correct, "fields": [["out", "int4"], ["renamed", "int4"]]},
        "remove-effect": {**correct, "after": correct["restored"]},
    }
    check_observation(item, correct, "healthy")
    found = {}
    for name, altered in wrong.items():
        try:
            check_observation(item, altered, name)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "postgres-insert-returning-control-signal", name)
            found[name] = {"input": item, **error.detail}
        else:
            raise Failure("postgres-insert-returning-accepted-control", name)
    return found


def faults(_engine, tag):
    directory = "sql-semantics-postgres/src/Ir/Mutation/"
    command = insertion_case(tag, ["id"], [("stamp", "saved")], rows=[["7"]])
    ordered = insertion_case(tag, ["delta", "id"], [("id", "a"), ("delta", "b")], rows=[["-4", "7"]])
    query = insertion_case(tag, ["id"], [("stamp", "saved")], query=select(tag, [slot(0)], "ir_insert_scan"))
    return [
        RenderFault("write-another-insert-slot", directory + "Insert/Destination.php", "$this->relation->columns[$column->slot]",
                    "$this->relation->columns[1]", command, "meaning-mismatch"),
        RenderFault("reverse-insert-destination-order", directory + "Insert/Destination.php", "implode(', ', $columns)",
                    "implode(', ', array_reverse($columns))", ordered, "meaning-mismatch"),
        RenderFault("drop-query-insert-returning", directory + "InsertQuery.php", " . (new ReturningSql())->clause($this->target->relation, $this->returning)",
                    "", query, "meaning-mismatch"),
        RenderFault("return-another-insert-slot", directory + "ReturningSql.php", "$target->columns[$field->reference->slot]",
                    "$target->columns[0]", query, "meaning-mismatch"),
    ]


if __name__ == "__main__":
    main(sys.modules[__name__])
