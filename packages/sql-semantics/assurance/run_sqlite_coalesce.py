"""Observe native first non-NULL results, dormant registrations, and lazy errors."""

from copy import deepcopy
import hashlib

from run import Failure, Worker, compare, require
from run_sqlite_unique_keys import values_sql
from sqlite_coalesce_cases import (
    DATA, DECLARATION, ESCAPE_ERROR, NAME, descendants, expected, finite_case, finite_id, finite_ids, negatives, requests,
)
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import Database, typed
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-coalesce-v1.json"
CONTRACT = "e5c41c106de09b1e96eb2634c14e692316704d8c60e24e3bd778d1449e30ab21"
FIXTURE = DECLARATION + f"; INSERT INTO {NAME} VALUES " + values_sql(DATA)


class CoalesceDatabase(Database):
    """Admits only the builtin calls needed for the independently fixed fixtures."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def authorize(self, context, action, first, second, schema, origin):
        if action == 31 and first is None and second in {b"coalesce", b"ifnull", b"like"} and schema is None:
            return 0
        return super().authorize(context, action, first, second, schema, origin)


def observe_schema(db, sql):
    table = "main.ir_coalesce_schema"
    db.execute("SAVEPOINT coalesce_schema")
    try:
        created = db.observe(sql, {}, capture_evaluation_error=True)
        effects = []
        for value in ("NULL", "0", "-2", "4", "0"):
            statement = db.observe(f"INSERT INTO {table}(a) VALUES ({value})", {}, capture_evaluation_error=True)
            effects.append({"changes": statement["changes"], "error": statement["evaluationError"]})
        read = db.observe(f"SELECT * FROM {table} ORDER BY rowid", {}, preserve_order=True)
        return {"created": created, "effects": effects, "names": read["names"], "rows": read["rows"],
                "columns": db.metadata("table_xinfo", schema="main", table="ir_coalesce_schema")}
    finally:
        db.execute("ROLLBACK TO coalesce_schema; RELEASE coalesce_schema")


def expected_schema():
    failure = {"code": 19, "message": "CHECK constraint failed: admitted"}
    admitted = {"changes": 1, "error": None}
    return {
        "created": {"names": [], "parameters": {}, "parameterCount": 0, "rows": [], "readOnly": False,
                    "changes": 0, "evaluationError": None},
        "effects": [admitted, admitted, {"changes": 0, "error": failure}, admitted, admitted],
        "names": ["a", "b"],
        "rows": [list(map(typed, row)) for row in ((None, -3), (0, 0), (4, 4), (0, 0))],
        "columns": [list(map(typed, (0, "a", "INTEGER", 0, None, 0, 0))),
                    list(map(typed, (1, "b", "INTEGER", 0, None, 0, 3)))],
    }


def probe(item, reply, db, ledger=None, case=None, original=None):
    require(reply["status"] == "constructed", "coalesce-positive-rejected", reply)
    schema = item.get("operation") == "create-table"
    fields = tuple(item) if schema else ("columns", "source", "where", "joins", "distinct", "orderBy", "limit")
    require(all(reply.get(role) == item[role] for role in fields), "coalesce-structure-mismatch", {"expected": item, "actual": reply})
    if ledger is not None:
        ledger.record(case, "constructed", response=reply)
    def inspect(sql):
        return observe_schema(db, sql) if schema else db.observe(sql, item["bindings"], capture_evaluation_error=True)
    actual = {"observation": inspect(reply["sql"])}
    if original is not None:
        actual["original"] = inspect(original)
    if ledger is not None:
        ledger.record(case, "observed", **actual)
    prediction = expected_schema() if schema else expected(item)
    for row in actual.values():
        compare(prediction, row)
    if ledger is not None:
        ledger.record(case, "compared")


def forms(reply):
    yield "scoped" if reply["source"] is not None else "source-free"
    if reply["where"] is not None:
        yield "filter"
    for value, _ in reply["columns"]:
        for node in descendants(value):
            if node[0] == "coalesce":
                yield f"arity/{len(node[1])}"
                for index, argument in enumerate(node[1]):
                    yield f"argument/{argument[0]}"
                    if argument[0] == "coalesce":
                        yield "nested"
                    if argument[0] == "parameter":
                        yield f"marker/{'first' if index == 0 else 'later'}"


def required(_domain):
    return {"scoped", "source-free", "filter", "nested", "arity/2", "arity/17", "argument/null",
            "argument/text", "argument/integer", "argument/real", "argument/blob", "argument/column",
            "marker/first", "marker/later"}


def controls():
    item = finite_case(2, "last-integer", "result")
    good = expected(item)
    bad = {**deepcopy(good), "evaluationError": ESCAPE_ERROR}
    compare(good, deepcopy(good))
    try:
        compare(good, bad)
    except Failure as error:
        require(error.signal == "meaning-mismatch", "coalesce-control-signal", error.detail)
    else:
        raise Failure("coalesce-comparator-ignored-evaluation-errors")
    return {"input": item, "healthy": good, "mismatched": bad}


def execute(args, contract, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return execute_schedule(args, contract, db, worker_factory, schedule, probe)


def check_faults(args, db, runner=check_render_faults):
    renderer = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    fallback = finite_case(3, "last-integer", "result")
    dormant = {**finite_case(2, "dormant-marker", "result"), "bindings": {}}
    competing = finite_case(3, "first-text", "result")
    competing["columns"][0][0][1][-1] = ["integer", "9"]
    arg = "$arguments[] = $this->expression($argument->expression, $source, $joins);"
    output = "return ($expression->ifNull ? 'IFNULL(' : 'COALESCE(') . implode(', ', $arguments) . ')';"
    return runner(args, db, [
        RenderFault("reverse-coalesce-arguments", renderer, "foreach ($expression->arguments as $argument)",
                    "foreach (array_reverse($expression->arguments) as $argument)", competing, "meaning-mismatch"),
        RenderFault("drop-last-coalesce-argument", renderer, output,
                    output.replace("$arguments)", "array_slice($arguments, 0, -1))"), fallback, "meaning-mismatch"),
        RenderFault("erase-dormant-coalesce-registration", renderer, arg,
                    "$arguments[] = $argument->expression instanceof Parameter ? 'NULL' : $this->expression($argument->expression, $source, $joins);",
                    dormant, "meaning-mismatch"),
        RenderFault("take-first-coalesce-argument-unconditionally", renderer, output, "return $arguments[0];", fallback, "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("coalesce", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, CoalesceDatabase)
