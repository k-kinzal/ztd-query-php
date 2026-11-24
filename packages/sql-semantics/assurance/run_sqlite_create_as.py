"""Compare a checked SQLite creation layout and populated rows against the native engine."""

from run import Failure, Worker, compare, require
from run_sqlite_create_table import EXISTING, quoted
from run_sqlite_defaults import catalogs
from run_sqlite_values import case as value_case
from sqlite_create_as_cases import (
    FIXTURE, expected, finite_id, finite_ids, layout, negatives, query_form, record, requests, rowid_name,
)
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule
from cases import case as select_case

CONTRACT_NAME = "sqlite-create-as-v5.json"
CONTRACT = "06b5c313bc7d216a77787303c104b7bcc9fb8cab1f494eeaaa922b330ed1a372"


def observe(db, case, sql):
    schema, table = case["schema"], case["table"]
    db.execute("SAVEPOINT create_as_probe")
    try:
        failure = None
        try:
            statement = db.observe(sql, case["query"]["bindings"], capture_evaluation_error=True)
        except RuntimeError as error:
            acceptable = {f"SQLite 1: table {table} already exists", f"SQLite 1: table {quoted(table)} already exists"}
            if type(error) is not RuntimeError or str(error) not in acceptable:
                raise
            failure, statement = "table-already-exists", None
        fields = db.metadata("table_xinfo", schema=schema, table=table)
        alias = rowid_name([[field[1][1], field[2][1]] for field in fields]) if fields else None
        rows = []
        if fields:
            target = f"{schema}.{quoted(table)}"
            if alias is not None:
                read = f"SELECT {alias}, * FROM {target} ORDER BY {alias}"
            else:
                read = f"SELECT * FROM {target}"
            rows = db.observe(read, {}, preserve_order=True)["rows"]
        return {"prepareError": failure, "statement": statement, "columns": fields, "catalogs": catalogs(db),
                "rowidAlias": alias, "rows": rows}
    finally:
        db.execute("ROLLBACK TO create_as_probe; RELEASE create_as_probe")


def check_query(wanted, actual):
    if "rows" in wanted:
        require(actual["operation"] == "values" and actual["rows"] == wanted["rows"],
                "sqlite-create-as-values-shape", actual)
        return
    expected_select = {"source": None, "joins": [], "where": None, "distinct": False, "orderBy": [], "limit": None, **wanted}
    roles = ("source", "joins", "where", "distinct", "orderBy", "limit", "columns")
    require(all(expected_select[role] == actual[role] for role in roles), "sqlite-create-as-select-shape",
            {"expected": expected_select, "actual": actual})


def probe(case, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed", "sqlite-create-as-positive-rejected", reply)
    roles = ("schema", "table", "ifNotExists", "operation")
    require(all(reply[role] == case[role] for role in roles), "sqlite-create-as-target-shape", reply)
    check_query(case["query"], reply["query"])
    compare(layout(case["query"]), reply["columns"])
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    results = {"observation": observe(db, case, reply["sql"])}
    if original is not None:
        results["original"] = observe(db, case, original)
    if ledger is not None:
        ledger.record(key, "observed", **results)
    baseline = expected(case)
    for observation in results.values():
        compare(baseline, observation)
    if ledger is not None:
        ledger.record(key, "compared")
    return results["observation"]


def controls():
    input_case = record(value_case([[["cast", "NUMERIC", ["text", "2.5"]]]] * 2))
    baseline = expected(input_case)
    declared = baseline["columns"][0]
    bad = {
        "lost-declared-affinity": {**baseline, "columns": [[*declared[:2], ["text", ""], *declared[3:]]]},
        "lost-repeat": {**baseline, "rows": baseline["rows"][:1]},
        "wrong-created-name": {**baseline, "columns": [[declared[0], ["text", "wrong"], *declared[2:]]]},
    }
    compare(baseline, baseline)
    for name, mutant in bad.items():
        try:
            compare(baseline, mutant)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-create-as-comparator-control-signal", name)
        else:
            raise Failure("sqlite-create-as-comparator-accepted", name)
    return {"input": input_case, "expected": baseline, "rejected": bad}


def forms(observed):
    reply, observation = observed
    query = reply["query"]
    yield f"schema/{reply['schema']}"
    yield f"guard/{reply['ifNotExists']}"
    yield f"width/{len(reply['columns'])}"
    yield f"existing/{reply['table'] == EXISTING}"
    yield "parameters/" + str(observation["statement"]["parameterCount"] > 0)
    for name, datum in reply["columns"]:
        yield "declared/" + datum
        if ":" in name:
            yield "name/collision-or-suffix"
    if "rows" in query:
        yield f"values-length/{len(query['rows'])}"
    else:
        yield "select/populated" if observation["rows"] else "select/empty"


def required(domain):
    return {
        *(f"schema/{schema}" for schema in domain["schemas"]), *(f"width/{width}" for width in range(1, 6)),
        *(f"values-length/{length}" for length in range(1, 6)),
        "guard/True", "guard/False", "existing/True", "existing/False", "parameters/True", "parameters/False",
        "name/collision-or-suffix", "select/populated", "select/empty", "declared/", "declared/INT", "declared/TEXT",
    }


def execute(args, specification, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return execute_schedule(args, specification, db, worker_factory, schedule, probe,
                            measured_input=lambda reply, observation: (reply, observation))


def check_faults(args, db, runner=check_render_faults):
    table = "sql-semantics-sqlite/src/Ir/Schema/Query/CreateTableAs.php"
    renderer = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    query = value_case([[["integer", "2"]], [["integer", "2"]]])
    expression = select_case(None, [["cast", "TEXT", ["integer", "13"]]], labels=["cast_value"])
    return runner(args, db, [
        RenderFault("wrong-population-schema", table, "$this->schema->quoted()", "'temp'", record(query), "meaning-mismatch"),
        RenderFault("erase-population-guard", table, "($this->ifNotExists ? 'IF NOT EXISTS ' : '')", "''",
                    record(query, guard=True, table=EXISTING), "meaning-mismatch"),
        RenderFault("replace-population-query", table, "$this->query->toString()", "'SELECT 9 AS fixed'",
                    record(query), "meaning-mismatch"),
        RenderFault("erase-text-declaration-cast", renderer, "' AS ' . ($expression->typeName?->quoted() ?? $expression->target->value) . '))'", "' AS BLOB))'",
                    record(expression), "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("create-as", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults)
