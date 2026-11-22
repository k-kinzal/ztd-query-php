"""Measure SQLite CHECK marker registration and row effects after empty membership."""

import copy
import hashlib

from run import Failure, Worker, compare, require
from run_sqlite_create_table import quoted
from run_sqlite_unique_keys import values_sql
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import Database
from sqlite_folded_check_cases import (
    VECTORS, check, expected, finite_id, finite_ids, folded, negatives, placed, registrations, requests, table,
)
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-folded-checks-v1.json"
CONTRACT = "cb60008a2bfcb42854ec8b7ffb17f7be4eeae896460beb054c1f54928e931741"
FIXTURE = ""


class FoldedCheckDatabase(Database):
    """Records native preparation and resolved slot bindings for declaration statements."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}".encode()).hexdigest()


def observe(db, item, sql, bindings):
    schema, name = item["schema"], item["table"]
    require(schema in ("main", "temp"), "sqlite-folded-check-schema", schema)
    target = f"{schema}.{quoted(name)}"
    db.execute("SAVEPOINT folded_check")
    try:
        declaration = db.observe(sql, bindings, capture_evaluation_error=True)
        columns = db.metadata("table_xinfo", schema=schema, table=name)
        require(bool(columns), "sqlite-folded-check-target-absent", declaration)
        writes = []
        for ordinal, vector in enumerate(VECTORS, start=1):
            data = (*vector, ordinal)
            result = db.observe(f"INSERT INTO {target} VALUES {values_sql([data])}", {}, capture_evaluation_error=True)
            writes.append({"error": result["evaluationError"], "changes": result["changes"]})
        selected = db.observe(f"SELECT * FROM {target} ORDER BY seq", {}, preserve_order=True)
        return {"create": declaration, "columns": columns, "names": selected["names"], "writes": writes, "rows": selected["rows"]}
    finally:
        db.execute("ROLLBACK TO folded_check; RELEASE folded_check")


def binding_values(positions):
    if not positions:
        return []
    return [
        {slot: None for slot in positions},
        {slot: 17 - int(slot) if int(slot) % 2 else "opaque marker value" for slot in positions},
        {slot: "unread" if int(slot) % 2 else -12 for slot in positions},
    ]


def measurements(db, item, sql, prediction):
    seen = [observe(db, item, sql, {})]
    # Validating the empty input first prevents an unexpected bind error from concealing a lost marker.
    compare(prediction, seen[0])
    for binding in binding_values(prediction["create"]["parameters"]):
        actual = observe(db, item, sql, binding)
        compare(prediction, actual)
        seen.append(actual)
    return seen


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and reply["operation"] == "create-table",
            "sqlite-folded-check-positive-rejected", reply)
    fields = ("operation", "schema", "table", "columns", "checks", "strict", "keys", "withoutRowid", "ifNotExists")
    require(all(reply[field] == item[field] for field in fields), "sqlite-folded-check-structure-mismatch", reply)
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    prediction = expected(item)
    observed = {"observation": measurements(db, item, reply["sql"], prediction)}
    if original is not None:
        observed["original"] = measurements(db, item, original, prediction)
    if ledger is not None:
        ledger.record(key, "observed", **observed)
        ledger.record(key, "compared")


def marks(expression, height=0):
    yield f"depth/{height}"
    role = expression[0]
    if role == "folded-membership":
        yield f"fold/{expression[1]}"
        for label in expression[2]:
            yield f"slot/{int(label)}"
            if len(label) > len(str(int(label))):
                yield "marker/padded"
    elif role == "logical":
        yield f"connective/{expression[1]}"
        yield from marks(expression[2], height + 1)
        yield from marks(expression[3], height + 1)
    elif role == "comparison":
        for operand in expression[2:]:
            if operand[0] == "column":
                yield f"reference/{operand[1]}"


def forms(reply):
    yield f"schema/{reply['schema']}"
    yield f"checks/{len(reply['checks'])}"
    for clause in reply["checks"]:
        yield f"owner/{clause['column']}"
        yield from marks(clause["condition"])
        labels = list(registrations(clause["condition"]))
        if len(labels) > len(set(map(int, labels))):
            yield "marker/repeated"


def required(domain):
    return {
        *(f"schema/{schema}" for schema in domain["schemas"]), "checks/1", "checks/5", "owner/0", "owner/1", "owner/None",
        "fold/False", "fold/True", "marker/padded", "marker/repeated", "slot/1", "slot/82", "depth/3",
        "connective/AND", "connective/OR", "reference/0", "reference/1",
    }


def controls():
    item = placed("main", True, ["07", "7"], "table", "alone")
    baseline = expected(item)
    compare(baseline, copy.deepcopy(baseline))
    changed = {**baseline, "create": {**baseline["create"], "parameters": {"7": "?7"}}}
    try:
        compare(baseline, changed)
    except Failure as error:
        require(error.signal == "meaning-mismatch", "sqlite-folded-check-control-signal", error.detail)
    else:
        raise Failure("sqlite-folded-check-comparator-undetected")
    return {"input": item, "healthy": baseline, "mismatched": changed}


def execute(args, contract, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return execute_schedule(args, contract, db, worker_factory, schedule, probe)


def check_faults(args, db, runner=check_render_faults):
    scalar = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    declaration = "sql-semantics-sqlite/src/Ir/Schema/TableDeclarationSql.php"
    both = table([check("first", folded(True, ["07"])), check("second", folded(True, ["7"]))])
    grouped = placed("main", True, ["07", "1", "007"], "table", "alone")
    return runner(args, db, [
        RenderFault("erase-inert-check-markers", scalar, "if ($expression->bindings === [])", "if (true)", grouped, "meaning-mismatch"),
        RenderFault("reverse-inert-check-registrations", scalar, "foreach ($expression->bindings as $binding)",
                    "foreach (array_reverse($expression->bindings) as $binding)", grouped, "meaning-mismatch"),
        RenderFault("invert-folded-check-result", scalar, "$expression->matches ? ' NOT IN ())' : ' IN ())'",
                    "$expression->matches ? ' IN ())' : ' NOT IN ())'", grouped, "meaning-mismatch"),
        RenderFault("reverse-check-first-marker-order", declaration, "foreach ($checks as $check)",
                    "foreach (array_reverse($checks) as $check)", both, "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("folded-checks", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, FoldedCheckDatabase)
