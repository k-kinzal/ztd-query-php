"""Compare targetless DO NOTHING with independent SQLite statement and transaction effects."""

from itertools import product

from run import Failure, Worker, compare, require
from run_sqlite_mutation_conflicts import PROFILES, compare_profiles, observe
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_insert_query_cases import shape
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule
from sqlite_upsert_nothing_cases import (
    FIXTURE, INSERT_CASES, expected, finite_id, finite_ids, negatives, projection, requests, values,
)

CONTRACT_NAME = "sqlite-upsert-nothing-v1.json"
CONTRACT = "2247a27e0acf8b29c247fecbd880d6dd7254e92adf9f3c952fe9c00d4d873c1a"


def observed(db, sql, recursive, explicit):
    try:
        return observe(db, sql, recursive, explicit)
    except RuntimeError as error:
        for token in ("DO", "ON"):
            if str(error) == f'SQLite 1: near "{token}": syntax error':
                raise Failure("native-upsert-clause-unparseable",
                              {"sql": sql, "stage": "prepare", "code": 1, "unexpectedToken": token}) from error
        raise


def probe(request, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed", "sqlite-upsert-positive-rejected", reply)
    roles = ("operation", "handler", "target", "conflict", "writes", "returning")
    require(all(reply[role] == request[role] for role in roles), "sqlite-upsert-structure-mismatch",
            {"request": request, "reply": reply})
    require(reply["query"] == shape(request) if "query" in request else reply["rows"] == request["rows"],
            "sqlite-upsert-input-shape-mismatch", {"request": request, "reply": reply})
    return compare_profiles(request, reply, db, ledger, key, original, predictor=expected, observer=observed)


def forms(reply):
    yield "handler/" + reply["handler"]
    yield "policy/" + reply["conflict"]
    yield f"returning/{len(reply['returning'])}"
    if "query" not in reply:
        yield f"values/{len(reply['rows'])}"
        return
    query = reply["query"]
    yield f"select/where/{query['where'] is not None}"
    yield f"select/limit/{query['limit'] is not None}"
    if query["orderBy"]:
        yield f"select/descending/{query['orderBy'][0][1]}"
        yield f"select/order-slot/{query['orderBy'][0][0]}"


def required(domain):
    return {
        "select/where/True", "select/where/False", "select/limit/True", "select/limit/False",
        "select/descending/True", "select/descending/False",
        *(f"select/order-slot/{position}" for position in range(1, 4)),
        *("handler/" + name for name in domain["handlers"]),
        *("policy/" + name for name in domain["policies"]),
        *(f"values/{size}" for size in range(1, 6)),
        *(f"returning/{size}" for size in range(4)),
    }


def controls():
    request = values(INSERT_CASES["rowid"], "replace", "do-nothing", [("id", "written")])
    correct = expected(request, True, True)
    alternate = {
        "replace-unique-blocker": expected({**request, "handler": "none"}, True, True),
        "drop-insert-activity": {**correct, "events": []},
        "incorrect-returning-type": {**correct, "statement": {**correct["statement"], "rows": [[["text", "3"]], [["text", "4"]]]}},
    }
    records = {}
    compare(correct, correct)
    for name, wrong in alternate.items():
        try:
            compare(correct, wrong)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-upsert-control-signal", name)
            records[name] = {"expected": correct, "observed": wrong, "signal": error.signal}
        else:
            raise Failure("sqlite-upsert-accepted-control", name)
    return records


def execute(args, specification, db, worker_factory=Worker):
    domain = specification["finite"]
    require(tuple(product(domain["recursiveTriggers"], domain["transaction"])) == PROFILES,
            "sqlite-upsert-fixture-profile-set")
    return execute_schedule(args, specification, db, worker_factory,
                            ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls), probe)


def check_faults(args, db, runner=check_render_faults):
    insert = "sql-semantics-sqlite/src/Ir/Mutation/Insert.php"
    select = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    return runner(args, db, [
        RenderFault("omit-uniqueness-handler", insert, "InsertConflictHandler::DoNothing => ' ON CONFLICT DO NOTHING'",
                    "InsertConflictHandler::DoNothing => ''", values(INSERT_CASES["rowid"], "replace", "do-nothing"), "meaning-mismatch"),
        RenderFault("apply-handler-to-all-constraint-errors", insert, "match ($this->conflict)",
                    "match ($this->handler === InsertConflictHandler::DoNothing ? InsertConflict::Ignore : $this->conflict)",
                    values(INSERT_CASES["not-null"], "abort", "do-nothing"), "meaning-mismatch"),
        RenderFault("drop-source-disambiguation", select, "elseif ($requireWhere)", "elseif (false)",
                    projection("unique", "replace", "do-nothing"), "native-upsert-clause-unparseable"),
        RenderFault("place-handler-after-output", insert, "$source . $action . $returning", "$source . $returning . $action",
                    values(INSERT_CASES["unique"], "replace", "do-nothing", [("id", "written")]), "native-upsert-clause-unparseable"),
    ], probe)


if __name__ == "__main__":
    run_domain("upsert-nothing", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults)
