"""Compare SQLite conflict policy values, statement effects and transaction state."""

from itertools import product

from run import Failure, Worker, compare, require
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_mutation_conflict_cases import (
    FIXTURE, INSERT_CASES, RESET, TABLE, expected, finite_id, finite_ids, insert, negatives, requests, update,
)
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-mutation-conflicts-v2.json"
CONTRACT = "083319fa81636593499d85ac4420ef701acbf9cdbc275c1fa7f64a2d92d84ada"
PROFILES = tuple(product((False, True), repeat=2))


def observe(db, sql, recursive, explicit):
    require(bool(db.get_autocommit(db.db)), "conflict-observer-left-transaction-open")
    db.execute(RESET)
    db.execute("PRAGMA recursive_triggers=" + ("ON" if recursive else "OFF"))
    if explicit:
        db.execute("BEGIN; INSERT INTO ir_marker VALUES (61)")
    try:
        native_recursive = db._observe("PRAGMA recursive_triggers", {})["rows"]
        # The observation returns a row containing one typed value.
        require(native_recursive in ([[["integer", "0"]]], [[["integer", "1"]]]),
                "conflict-observer-trigger-setting", native_recursive)
        fixture = {"recursiveTriggers": native_recursive[0][0][1] == "1",
                   "explicitTransaction": not db.get_autocommit(db.db)}
        statement = db.observe(sql, {}, capture_evaluation_error=True)
        statement["changes"] = db.changes(db.db)
        return {
            "fixture": fixture,
            "statement": statement,
            "rows": db.observe(f"SELECT id,code,payload FROM main.{TABLE}", {})["rows"],
            "events": db.observe("SELECT kind,id,code,payload FROM main.ir_conflict_log ORDER BY rowid", {}, preserve_order=True)["rows"],
            "marker": db.observe("SELECT value FROM main.ir_marker", {})["rows"],
            "active": not db.get_autocommit(db.db),
        }
    finally:
        if not db.get_autocommit(db.db):
            db.execute("ROLLBACK")


def probe(request, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed", "sqlite-conflict-positive-rejected", reply)
    roles = ["operation", "target", "conflict", "returning"]
    roles += ["writes", "rows"] if request["operation"] == "insert" else ["assignments", "where"]
    require(all(request[role] == reply[role] for role in roles), "sqlite-conflict-structure-mismatch",
            {"request": request, "reply": reply})
    if request["operation"] == "insert":
        require(reply["handler"] == "none", "sqlite-conflict-unexpected-handler", reply)
    return compare_profiles(request, reply, db, ledger, key, original)


def compare_profiles(request, reply, db, ledger=None, key=None, original=None, predictor=expected, observer=observe):
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    measurements = {"observation": [observer(db, reply["sql"], mode, explicit) for mode, explicit in PROFILES]}
    if original is not None:
        measurements["original"] = [observer(db, original, mode, explicit) for mode, explicit in PROFILES]
    if ledger is not None:
        ledger.record(key, "observed", **measurements)
    prediction = [predictor(request, mode, explicit) for mode, explicit in PROFILES]
    for states in measurements.values():
        compare(prediction, states)
    if ledger is not None:
        ledger.record(key, "compared")
    return measurements["observation"]


def forms(reply):
    kind = reply["operation"]
    target = reply["target"][2]
    yield "kind/" + kind
    yield "policy/" + reply["conflict"]
    yield "layout/" + ",".join(target)
    yield f"returning/{len(reply['returning'])}"
    if kind == "insert":
        yield f"rows/{len(reply['rows'])}"
    else:
        yield f"assignments/{len(reply['assignments'])}"
        slot = reply["assignments"][0][0]
        yield "first-write/" + target[slot]


def required(domain):
    return {
        "kind/insert", "kind/update", "layout/id,code,payload", "layout/payload,code,id",
        "assignments/1", "assignments/2", "first-write/id", "first-write/code", "first-write/payload",
        *(f"policy/{policy}" for policy in domain["policies"]),
        *(f"rows/{length}" for length in range(1, 6)),
        *(f"returning/{length}" for length in range(4)),
    }


def controls():
    request = insert(INSERT_CASES["rowid"], "fail", [("id", "written")])
    correct = expected(request, True, True)
    alternate = {
        "failed-prefix-erased": {**correct, "rows": expected({**request, "conflict": "abort"}, True, True)["rows"]},
        "transaction-state-erased": {**correct, "active": False},
        "wrong-returning-name": {**correct, "statement": {**correct["statement"], "names": ["wrong"]}},
        "wrong-trigger-events": {**correct, "events": []},
    }
    compare(correct, correct)
    records = {}
    for name, wrong in alternate.items():
        try:
            compare(correct, wrong)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-conflict-control-signal", name)
            records[name] = {"expected": correct, "observed": wrong, "signal": error.signal}
        else:
            raise Failure("sqlite-conflict-accepted-control", name)
    return records


def execute(args, specification, db, worker_factory=Worker):
    domain = specification["finite"]
    require(tuple(product(domain["recursiveTriggers"], domain["transaction"])) == PROFILES,
            "sqlite-conflict-fixture-profile-set")
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return execute_schedule(args, specification, db, worker_factory, schedule, probe)


def check_faults(args, db, runner=check_render_faults):
    insert_file = "sql-semantics-sqlite/src/Ir/Mutation/Insert.php"
    update_file = "sql-semantics-sqlite/src/Ir/Mutation/Update.php"
    return runner(args, db, [
        RenderFault("discard-failed-insert-prefix", insert_file, "InsertConflict::Fail => ' OR FAIL'",
                    "InsertConflict::Fail => ' OR ABORT'", insert(INSERT_CASES["rowid"], "fail"), "meaning-mismatch"),
        RenderFault("retain-transaction-after-rollback", insert_file, "InsertConflict::Rollback => ' OR ROLLBACK'",
                    "InsertConflict::Rollback => ' OR ABORT'", insert(INSERT_CASES["not-null"], "rollback"), "meaning-mismatch"),
        RenderFault("ignore-conflicting-insert", insert_file, "InsertConflict::Replace => ' OR REPLACE'",
                    "InsertConflict::Replace => ' OR IGNORE'", insert(INSERT_CASES["unique"], "replace"), "meaning-mismatch"),
        RenderFault("ignore-conflicting-update", update_file, "UpdateConflict::Replace => ' OR REPLACE'",
                    "UpdateConflict::Replace => ' OR IGNORE'", update(2, [("code", 10)], "replace"), "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("mutation-conflicts", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults)
