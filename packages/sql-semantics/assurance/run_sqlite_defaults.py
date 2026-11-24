"""Replay SQLite column defaults against independent typed INSERT observations."""

import time
from collections import Counter

from run import Failure, Ledger, Worker, compare, require, write_json
from run_sqlite_create_table import DECLARATIONS, EXISTING, FIXTURE, case, quoted
from sqlite_default_cases import TABLE, analyzed, columns_of, expected, finite_cases, finite_id, finite_ids, negatives, seeded
from sqlite_domain import RenderFault, check_render_faults, complete_observations, run_domain

CONTRACT_NAME = "sqlite-defaults-v4.json"
CONTRACT = "07ada5b77b795821b43c0aa682803c3340112645eb1f5a61f965883534e64a49"
EXCEPTION = "SqlSemantics\\Platform\\Sqlite\\Model\\Diagnostic\\"
SCHEMAS = ("main", "temp")


def catalogs(db):
    return {name: db.observe(f"SELECT name FROM {name}.sqlite_schema WHERE type = 'table'", {})["rows"] for name in SCHEMAS}


def observe(db, item, sql):
    schema, table = item["schema"], item["table"]
    target = f"{schema}.{quoted(table)}"
    db.execute("SAVEPOINT default_probe")
    try:
        statement = db.observe(sql, {}, capture_evaluation_error=True)
        require(statement["evaluationError"] is None and not statement["readOnly"] and statement["changes"] == 0,
                "sqlite-default-create-failed", statement)
        column_state = db.metadata("table_xinfo", schema=schema, table=table)
        require(bool(column_state), "sqlite-default-schema-missing", target)
        writes = rows = None
        if table != EXISTING:
            width = len(item["columns"])
            markers = ", ".join(f"?{slot}" for slot in range(1, width + 1))
            bindings = {str(slot): "7" for slot in range(1, width + 1)}
            omitted = f"INSERT INTO {target} DEFAULT VALUES"
            supplied = f"INSERT INTO {target} VALUES ({markers})"
            writes = {}
            for role, statement, values in (
                ("omitted", omitted, {}),
                ("explicit", supplied, bindings),
                ("nulls", supplied, dict.fromkeys(bindings)),
            ):
                event = db.observe(statement, values, capture_evaluation_error=True)
                writes[role] = {"changes": event["changes"], "error": event["evaluationError"]}
            rows = db.observe(f"SELECT rowid, * FROM {target} ORDER BY rowid", {}, preserve_order=True)["rows"]
        result = {"columns": column_state, "catalogs": catalogs(db), "writes": writes, "rows": rows,
                  "existing": {name: db.observe(f"SELECT saved FROM {name}.{EXISTING}", {})["rows"] for name in SCHEMAS}}
    finally:
        db.execute("ROLLBACK TO default_probe; RELEASE default_probe")
    return {**result, "restored": catalogs(db)}


def semantic(observation):
    column_state = [[*column[:4], column[4] != ["null", None], *column[5:]] for column in observation["columns"]]
    return {**observation, "columns": column_state}


def probe(item, response, db, ledger=None, key=None, original=None):
    require(response["status"] == "constructed", "sqlite-default-positive-rejected", response)
    require(all(item[role] == response[role] for role in ("schema", "table", "columns", "ifNotExists", "operation")),
            "sqlite-default-shape-mismatch", {"expected": item, "actual": response})
    if ledger is not None:
        ledger.record(key, "constructed", response=response)
    results = {"observation": observe(db, item, response["sql"])}
    if original is not None:
        results["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **results)
    for result in results.values():
        compare(expected(item), semantic(result))
    if ledger is not None:
        ledger.record(key, "compared")


def controls():
    item = case([["value", "BLOB", False, ["null"]]], table=TABLE)
    correct = expected(item)
    compare(correct, correct)
    column = list(correct["columns"][0])
    column[4] = False
    bad = {"erased-explicit-null": {**correct, "columns": [column]},
           "dropped-omitted-row": {**correct, "rows": correct["rows"][1:]},
           "unrestored-table": {**correct, "restored": correct["catalogs"]}}
    for name, record in bad.items():
        try:
            compare(correct, record)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-default-control-signal", name)
        else:
            raise Failure("sqlite-default-comparator-accepted", name)
    return {"input": item, "expected": correct, "rejected": bad}


def requests(specification):
    domain, schedule = specification["finite"], specification["exploration"]
    for index, item in enumerate(finite_cases(domain)):
        yield f"finite/{index}", item, item
    for seed in schedule["seeds"]:
        for index, item in enumerate(seeded(domain, seed, schedule["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item
    for index, (sql, item) in enumerate(analyzed()):
        yield f"analysis/{index}", {"sql": sql, "declarations": DECLARATIONS}, item


def execute(args, specification, db, worker_factory=Worker):
    finite, settings = specification["finite"], specification["exploration"]
    seen, forms = Counter(), Counter()
    comparator = controls()
    key = current = None
    with (args.out / "events.jsonl").open("w") as stream, worker_factory(args, time.monotonic() + settings["timeoutSeconds"]) as worker:
        ledger = Ledger(stream)
        try:
            for key, current, item in requests(specification):
                ledger.record(key, "attempted", input=current, expected=item)
                response = worker.request(current)
                probe(item, response, db, ledger, key, original=current.get("sql"))
                if key.startswith("finite/"):
                    seen[finite_id(response, finite)] += 1
                if key.startswith("seed/"):
                    forms["schema/" + response["schema"]] += 1
                    forms["width/" + str(len(response["columns"]))] += 1
                    for _, kind, not_null, default in columns_of(response):
                        forms["type/" + kind] += 1
                        forms["nullability/" + str(not_null)] += 1
                        forms["default/" + (default[0] if default is not None else "absent")] += 1
            for index, (sql, status, reason) in enumerate(negatives()):
                key = f"negative/{index}"
                current = {"sql": sql, "declarations": []}
                ledger.record(key, "attempted", input=current)
                response = worker.request(current)
                exception = EXCEPTION + ("InvalidConstruction" if status == "rejected" else "SemanticException")
                require(response["status"] == status and response["exception"] == exception and reason in response["message"],
                        "sqlite-default-negative-boundary", response)
                ledger.record(key, "rejected", response=response)
            require(seen.keys() == finite_ids(finite) and set(seen.values()) == {1}, "sqlite-default-finite-incomplete")
            mandatory = {*("schema/" + scope for scope in SCHEMAS), *("type/" + kind for kind in finite["types"]),
                         "width/1", "width/5", "nullability/True", "nullability/False",
                         *("default/" + label for label in ("absent", "null", "integer", "real", "text", "blob", "hex"))}
            require(mandatory <= forms.keys(), "sqlite-default-unobserved-forms", sorted(mandatory - forms.keys()))
            counts = complete_observations(ledger, settings)
        except Exception as error:
            signal = error.signal if isinstance(error, Failure) else "unexpected-error"
            detail = error.detail if isinstance(error, Failure) else None
            if key in ledger.events and ledger.events[key][-1] not in ("compared", "rejected"):
                ledger.record(key, "timeout" if signal == "timeout" else "failed", signal=signal, detail=detail)
            write_json(args.out / "counterexample.json",
                       {"id": key, "original": current, "signal": signal, "failure": str(error), "detail": detail, "shrunk": None})
            raise
        return {"counts": counts, "finiteIds": sorted(seen), "forms": dict(forms), "php": worker.environment,
                "controls": comparator}


def check_faults(args, db, runner=check_render_faults):
    column = "sql-semantics-sqlite/src/Ir/Schema/TableDeclarationSql.php"
    null = case([["value", "BLOB", False, ["null"]]], table=TABLE)
    integer = case([["value", "INTEGER", False, ["integer", "7"]]], table=TABLE)
    blob = case([["value", "BLOB", False, ["blob", "00FF"]]], table=TABLE)
    render = "' DEFAULT ' . $expressions->expression($operand, null)"
    return runner(args, db, [
        RenderFault("omit-default-clause", column, " . $default . $collation . $generation;", " . $collation . $generation;", null, "meaning-mismatch"),
        RenderFault("replace-default-with-null", column, render, "' DEFAULT NULL'", integer, "meaning-mismatch"),
        RenderFault("stringify-blob-default", column, render, "\" DEFAULT '\" . $column->default->hex . \"'\"", blob, "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("defaults", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults)
