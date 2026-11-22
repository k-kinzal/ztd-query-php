"""Compare declared SQLite NOT NULL policies against native write and transaction effects."""

import time
from collections import Counter

from run import Failure, Ledger, Worker, compare, require, write_json
from run_sqlite_create_table import case, quoted
from run_sqlite_defaults import semantic
from sqlite_domain import RenderFault, check_render_faults, complete_observations, run_domain
from sqlite_not_null_conflict_cases import (
    FIXTURE, TABLE, expected, finite_id, finite_ids, insert_rows, negatives, payload, plan, requests,
)

CONTRACT_NAME = "sqlite-not-null-conflict-v2.json"
CONTRACT = "7dea02740bfcba38f358358c38aa42fd54e577923a9d78beca6a425b1c59b820"


def tuple_sql(row):
    return "(" + ", ".join("NULL" if value is None else str(value) for value in row) + ")"


def observe(db, item, sql):
    schema, table, columns, profile = item["schema"], item["table"], item["columns"], item["probe"]
    target = f"{schema}.{quoted(table)}"
    marker = "main.ir_nn_policy_marker"
    db.execute(f"DELETE FROM {marker}")
    created = False
    try:
        creation = db.observe(sql, {}, capture_evaluation_error=True)
        require(not creation["readOnly"] and creation["changes"] == 0 and creation["evaluationError"] is None,
                "sqlite-not-null-create-failed", creation)
        created = True
        catalog = db.metadata("table_xinfo", schema=schema, table=table)
        require(bool(catalog), "sqlite-not-null-wrong-target", target)

        slot = quoted(columns[profile["slot"]][0])
        policy = "" if profile["override"] == "inherit" else " OR " + profile["override"]
        if profile["write"] == "insert":
            values = ", ".join(map(tuple_sql, insert_rows(item)))
            write = f"INSERT{policy} INTO {target} VALUES {values}"
        else:
            rows = ", ".join(tuple_sql(payload(len(columns), token)) for token in (41, 81))
            db.execute(f"INSERT INTO {target} VALUES {rows}")
            write = f"UPDATE{policy} {target} SET {slot} = NULL WHERE rowid = 1"
        if profile["transaction"]:
            db.execute(f"BEGIN; INSERT INTO {marker} VALUES (61)")
        active = not db.get_autocommit(db.db)

        statement = db.observe(write, {}, capture_evaluation_error=True)
        changes = db.changes(db.db)
        fields = ", ".join(quoted(column[0]) for column in columns)
        rows = db.observe(f"SELECT rowid, {fields} FROM {target} ORDER BY rowid", {}, preserve_order=True)["rows"]
        return {
            "profile": {"write": write.split()[0].lower(), "override": write.split()[2] if policy else "inherit",
                        "transaction": active},
            "nativeWrite": write, "columns": catalog, "rows": rows,
            "statement": {"error": statement["evaluationError"], "changes": changes},
            "marker": db.observe(f"SELECT value FROM {marker}", {})["rows"],
            "active": not db.get_autocommit(db.db),
        }
    finally:
        if not db.get_autocommit(db.db):
            db.execute("ROLLBACK")
        if created:
            db.execute(f"DROP TABLE IF EXISTS {target}")


def observed_effects(result):
    catalog = semantic({"columns": result["columns"]})["columns"]
    return {"catalog": catalog, **{key: result[key] for key in ("rows", "statement", "marker", "active")}}


def probe(item, response, db, ledger=None, key=None, original=None):
    require(response["status"] == "constructed", "sqlite-not-null-positive-rejected", response)
    roles = ("operation", "schema", "table", "columns", "ifNotExists")
    require(all(response[role] == item[role] for role in roles), "sqlite-not-null-shape-mismatch",
            {"expected": item, "actual": response})
    if ledger is not None:
        ledger.record(key, "constructed", response=response)
    measured = {"observation": observe(db, item, response["sql"])}
    if original is not None:
        measured["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **measured)
    prediction = expected(item)
    for result in measured.values():
        require(all(result["profile"][role] == item["probe"][role] for role in result["profile"]),
                "sqlite-not-null-observer-profile", result)
        compare(prediction, observed_effects(result))
    if ledger is not None:
        ledger.record(key, "compared")
    return measured["observation"]


def controls():
    item = plan(case([["value", "INTEGER", "FAIL", ["integer", "-7"]]], table=TABLE), explicit=True)
    actual = expected(item)
    alternatives = {"missing-prefix": {**actual, "rows": []}, "lost-transaction": {**actual, "active": False},
                    "lost-marker": {**actual, "marker": []}, "lost-error": {**actual, "statement": {"changes": 1, "error": None}}}
    compare(actual, actual)
    for label, changed in alternatives.items():
        try:
            compare(actual, changed)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "sqlite-not-null-control-signal", label)
        else:
            raise Failure("sqlite-not-null-comparator-accepted-difference", label)
    return {"input": item, "expected": actual, "rejected": alternatives}


def forms(reply, measurement, specification):
    yield f"schema/{reply['schema']}"
    yield f"width/{len(reply['columns'])}"
    yield f"guard/{reply['ifNotExists']}"
    for role, value in measurement["profile"].items():
        yield f"{role}/{value}"
    for column in reply["columns"]:
        yield f"type/{column[1]}"
        yield f"constraint/{column[2]}"
        yield "default/" + ("absent" if len(column) == 3 else column[3][0])
    yield f"slot/{specification['slot']}"
    if measurement["profile"]["write"] == "insert":
        yield f"prefix/{specification['prefix']}"


def required(domain):
    return {
        *(f"schema/{schema}" for schema in domain["schemas"]),
        *(f"width/{width}" for width in range(1, 5)),
        *(f"constraint/{policy}" for policy in domain["constraints"]),
        *(f"override/{override}" for override in domain["overrides"]),
        *(f"prefix/{width}" for width in range(5)),
        *(f"slot/{slot}" for slot in range(4)),
        "guard/False", "guard/True", "type/", "type/INTEGER", "write/insert", "write/update",
        "transaction/False", "transaction/True", "default/absent", "default/null", "default/integer",
    }


def execute(args, specification, db, worker_factory=Worker):
    domain, settings = specification["finite"], specification["exploration"]
    seen, exploration = Counter(), Counter()
    comparator = controls()
    key = current = None
    with (args.out / "events.jsonl").open("w") as stream, worker_factory(args, time.monotonic() + settings["timeoutSeconds"]) as worker:
        ledger = Ledger(stream)
        try:
            for key, current, item in requests(specification):
                ledger.record(key, "attempted", input=current, expected=item)
                reply = worker.request(current)
                measured = probe(item, reply, db, ledger, key, original=current.get("sql"))
                if key.startswith("finite/"):
                    seen[finite_id(reply, measured, domain)] += 1
                if key.startswith("seed/"):
                    exploration.update(forms(reply, measured, item["probe"]))
            for index, (current, (status, exception, reason)) in enumerate(negatives()):
                key = f"negative/{index}"
                ledger.record(key, "attempted", input=current)
                reply = worker.request(current)
                require(reply["status"] == status and reply["exception"] == exception and reason in reply["message"],
                        "sqlite-not-null-negative-boundary", reply)
                ledger.record(key, "rejected", response=reply)
            require(seen.keys() == finite_ids(domain) and set(seen.values()) == {1}, "sqlite-not-null-finite-incomplete")
            missing = required(domain) - exploration.keys()
            require(not missing, "sqlite-not-null-unobserved-forms", sorted(missing))
            counts = complete_observations(ledger, settings)
        except Exception as error:
            signal = error.signal if isinstance(error, Failure) else "unexpected-error"
            detail = error.detail if isinstance(error, Failure) else None
            if key in ledger.events and ledger.events[key][-1] not in ("compared", "rejected"):
                ledger.record(key, "timeout" if signal == "timeout" else "failed", signal=signal, detail=detail)
            write_json(args.out / "counterexample.json",
                       {"id": key, "original": current, "signal": signal, "failure": str(error), "detail": detail, "shrunk": None})
            raise
        return {"counts": counts, "finiteIds": sorted(seen), "forms": dict(exploration), "php": worker.environment,
                "controls": comparator}


def check_faults(args, db, runner=check_render_faults):
    column = "sql-semantics-sqlite/src/Ir/Schema/TableDeclarationSql.php"

    def example(algorithm, **probe):
        return plan(case([["value", "INTEGER", algorithm, ["integer", "-7"]]], table=TABLE), **probe)

    return runner(args, db, [
        RenderFault("force-implicit-abort", column, "$type . $constraint . $default . $collation . $generation;",
                    "$type . ($column->notNull === null ? '' : ' NOT NULL') . $default . $collation . $generation;", example("IGNORE"), "meaning-mismatch"),
        RenderFault("discard-prefix-on-fail", column, "NotNullConflict::Fail => ' NOT NULL ON CONFLICT FAIL'",
                    "NotNullConflict::Fail => ' NOT NULL'", example("FAIL"), "meaning-mismatch"),
        RenderFault("keep-transaction-on-rollback", column, "NotNullConflict::Rollback => ' NOT NULL ON CONFLICT ROLLBACK'",
                    "NotNullConflict::Rollback => ' NOT NULL'", example("ROLLBACK", explicit=True), "meaning-mismatch"),
        RenderFault("skip-replacement-value", column, "NotNullConflict::Replace => ' NOT NULL ON CONFLICT REPLACE'",
                    "NotNullConflict::Replace => ' NOT NULL ON CONFLICT IGNORE'", example("REPLACE", write="update"), "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("not-null-conflict", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults)
