"""Compare named SQLite row conditions and their native placement effects."""

import copy
import hashlib

from run import Failure, Worker, compare, require
from run_sqlite_create_table import quoted
from run_sqlite_unique_keys import UniqueDatabase, values_sql
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import ObserverAuthorizationError
from sqlite_named_check_cases import (
    CONDITIONS, FIXTURE, MARKER, MODES, check, expected, finite_id, finite_ids, negatives, requests, table, values,
)
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-named-checks-v4.json"
CONTRACT = "8f50053817516e759452a0da21ed563479f48a61b9c22a2a725b4cb29fa93290"


class NamedCheckDatabase(UniqueDatabase):
    """Limits candidate SQL to the approved SQLite authorizer with one scoped unique index."""

    policy_digest = hashlib.sha256(f"{UniqueDatabase.policy_digest}:{CONTRACT}".encode()).hexdigest()


def state(db, target):
    return db.observe(f"SELECT * FROM {target} ORDER BY seq", {}, preserve_order=True)


def write(db, target, rows, mode):
    override = "" if mode is None else f" OR {mode}"
    statement = f"INSERT{override} INTO {target} VALUES {values_sql(rows)}"
    result = db.observe(statement, {}, capture_evaluation_error=True)
    return {"error": result["evaluationError"], "changes": db.changes(db.db), "rows": state(db, target)["rows"]}


def create(db, item, sql):
    observed = db.create(sql, item["schema"], item["table"], len(item["keys"]))
    require(not observed["readOnly"] and observed["changes"] == 0 and observed["evaluationError"] is None
            and observed["parameterCount"] == 0, "sqlite-check-create-failed", observed)


def check_lifecycle(db, item, sql, target):
    create(db, item, sql)
    try:
        db.execute(f"ALTER TABLE {target} RENAME COLUMN c0 TO renamed")
        renamed = {"fields": state(db, target)["names"], "error": write(db, target, [(-1, 2, 1)], "ABORT")["error"]}
    finally:
        db.execute(f"DROP TABLE {target}")

    create(db, item, sql)
    try:
        problem = diagnostic = None
        try:
            db.execute(f"ALTER TABLE {target} DROP COLUMN c0")
        except RuntimeError as error:
            diagnostic = str(error)
            prefix = f"SQLite 1: error in table {item['table']} after drop column: no such column: "
            known = (prefix + "c0", prefix + '"c0" - should this be a string literal in single-quotes?')
            require(diagnostic in known, "sqlite-check-unexpected-alter-error", diagnostic)
            problem = {"code": 1, "table": item["table"], "column": "c0"}
        fields = state(db, target)["names"]
        if problem is None:
            require(write(db, target, [(-5, 1)], "ABORT")["error"] is None,
                    "sqlite-check-drop-column-write", fields)
        dropped = {"error": problem, "fields": fields, "rows": state(db, target)["rows"]}
    finally:
        db.execute(f"DROP TABLE {target}")
    return {"rename": renamed, "drop": dropped, "dropDiagnostic": diagnostic}


def observe(db, item, sql):
    schema, name = item["schema"], item["table"]
    require(schema in ("main", "temp"), "sqlite-check-unknown-schema", schema)
    target = f"{schema}.{quoted(name)}"
    rows = list(values(item))
    create(db, item, sql)
    try:
        columns = db.metadata("table_xinfo", schema=schema, table=name)
        fields = state(db, target)["names"]
        separate = []
        for row in rows:
            separate.append(write(db, target, [row], None))
            db.execute(f"DELETE FROM {target}")
        policies = {}
        for mode in MODES:
            db.execute(f"BEGIN; INSERT INTO {MARKER} VALUES(73)")
            try:
                effects = write(db, target, rows, mode)
                policies[mode] = {**effects, "marker": db.observe(f"SELECT value FROM {MARKER}", {})["rows"],
                                  "transaction": not db.get_autocommit(db.db)}
            finally:
                if not db.get_autocommit(db.db):
                    db.execute("ROLLBACK")
    finally:
        db.execute(f"DROP TABLE {target}")

    result = {"columns": columns, "fields": fields, "separate": separate, "policies": policies}
    if item["lifecycle"]:
        result["lifecycle"] = check_lifecycle(db, item, sql, target)
    return result


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and reply["operation"] == "create-table",
            "sqlite-check-positive-rejected", reply)
    properties = tuple(name for name in item if name != "lifecycle")
    require(all(reply[name] == item[name] for name in properties), "sqlite-check-structure-mismatch",
            {"input": item, "output": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    results = {"observation": observe(db, item, reply["sql"])}
    if original is not None:
        results["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **results)
    prediction = expected(item)
    for measurement in results.values():
        if "lifecycle" in measurement:
            lifecycle = {name: value for name, value in measurement["lifecycle"].items() if name != "dropDiagnostic"}
            measurement = {**measurement, "lifecycle": lifecycle}
        compare(prediction, measurement)
    if ledger is not None:
        ledger.record(key, "compared")


def expressions(operand, depth=0):
    role = operand[0]
    yield f"expression/{role}"
    yield f"depth/{depth}"
    if role == "column":
        yield f"slot/{operand[1]}"
    if role == "integer" and abs(int(operand[1])) > 9223372036854775800:
        yield "literal/int64-bound"
    if role in ("arithmetic", "logical", "comparison"):
        yield f"operator/{operand[1]}"
        yield from expressions(operand[2], depth + 1)
        yield from expressions(operand[3], depth + 1)


def forms(reply):
    yield f"schema/{reply['schema']}"
    yield f"strict/{reply['strict']}"
    yield f"width/{len(reply['columns'])}"
    yield f"checks/{len(reply['checks'])}"
    for clause in reply["checks"]:
        yield "placement/table" if clause["column"] is None else f"placement/{clause['column']}"
        yield "label/empty" if clause["label"] == "" else "label/named"
        if '"' in clause["label"]:
            yield "label/quote"
        yield from expressions(clause["condition"])


def required(domain):
    return {
        *(f"schema/{schema}" for schema in domain["schemas"]), "strict/True", "strict/False", "width/3", "width/6",
        "checks/1", "checks/5", "placement/table", "placement/0", "placement/4", "label/empty", "label/named",
        "label/quote", "depth/4", "slot/0", "slot/4", "literal/int64-bound",
        "operator/AND", "operator/OR", "operator/<>", "operator/>", "operator/<", "operator/<=", "operator/>=", "operator/=",
    }


def controls(db):
    sample = table([check("pass", CONDITIONS["first-positive"], 0)], lifecycle=True)
    good = expected(sample)
    compare(good, copy.deepcopy(good))
    for part in ("policies", "lifecycle"):
        corrupt = {**good, part: {}}
        try:
            compare(good, corrupt)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-check-comparator-signal")
        else:
            raise Failure("sqlite-check-undetected-comparator-fault", part)
    denied = f"ALTER TABLE {MARKER} RENAME COLUMN value TO unrelated"
    try:
        db.observe(denied, {})
    except ObserverAuthorizationError as error:
        require(error.action == 26, "sqlite-check-observer-control", error.arguments)
    else:
        raise Failure("sqlite-check-observer-allowed-candidate-alter")
    return {"expected": good, "mutatedParts": ["policies", "lifecycle"], "observer": {"sql": denied, "action": 26}}


def execute(args, specification, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, lambda: controls(db))
    return execute_schedule(args, specification, db, worker_factory, schedule, probe)


def check_faults(args, db, runner=check_render_faults):
    renderer = "sql-semantics-sqlite/src/Ir/Schema/TableDeclarationSql.php"
    scalar = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    first = check("first", CONDITIONS["first-positive"], 0)
    second = check("second", CONDITIONS["second-nonzero"], 0)
    effect = table([first])
    order = table([first, second])
    return runner(args, db, [
        RenderFault("drop-named-check", renderer, "foreach ($checks as $check)", "foreach ([] as $check)",
                    effect, "meaning-mismatch"),
        RenderFault("substitute-named-check-slot", scalar, "? $name : $this->alias",
                    "? $source->columns[1]->quoted() : $this->alias", effect, "meaning-mismatch"),
        RenderFault("replace-named-check-label", renderer, "$check->label->quoted()", "'different'",
                    effect, "meaning-mismatch"),
        RenderFault("reverse-named-checks", renderer, "foreach ($checks as $check)", "foreach (array_reverse($checks) as $check)",
                    order, "meaning-mismatch"),
        RenderFault("relocate-named-check", renderer, "if ($check->column === null)", "if (true)",
                    table([first], lifecycle=True), "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("named-checks", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, NamedCheckDatabase)
