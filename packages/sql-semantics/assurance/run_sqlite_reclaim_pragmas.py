"""Compare resolved native reclamation operations with live and on-disk effects."""

import copy
import hashlib
import os
from pathlib import Path
from tempfile import TemporaryDirectory

from run import Failure, Worker, compare, require
from run_sqlite_page_pragmas import PagerDatabase
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_reclaim_cases import (AUTOMATIC, INCREMENTAL, LIMIT_ERROR, MAXIMUM, SCOPE_ERROR, analysis, case,
                                  expected, finite_id, finite_ids, forms, negatives, requests, required, source)
from sqlite_reclaim_fixture import populate, snapshot
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-reclaim-pragmas-v1.json"
CONTRACT = "30f8b880b79308e646b56dee585b0c09f79bb512457fcad530d120e35f6b7c52"
FIXTURE = ""


class NativeDatabase(PagerDatabase):
    """Permit only the two intended pragma commands on the fixture's scopes."""

    policy_digest = hashlib.sha256(f"{PagerDatabase.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def authorize(self, _, action, first, second, schema, origin):
        if (origin is None and action == 19 and first is not None and
                first.lower() in (b"auto_vacuum", b"incremental_vacuum") and schema in self.schemata):
            self.calls.append([first.decode().lower(), None if schema is None else schema.decode(),
                               None if second is None else second.decode()])
            return 0
        if self.denied is None:
            self.denied = (action, [None if field is None else field.decode(errors="replace")
                                    for field in (first, second, schema, origin)])
        return 1


def isolated(template, item, sql, action):
    with TemporaryDirectory(prefix="semantics-ir-sqlite-reclaim-") as temporary:
        previous = Path.cwd()
        os.chdir(temporary)
        db = NativeDatabase(template.library, b"primary.sqlite")
        try:
            populate(db, item)
            db.schemata = frozenset((None, b"main", b"temp", item["attachment"].encode()))
            return action(db, item, sql)
        finally:
            db.check(db.close(db.db))
            os.chdir(previous)


def program(db, item, sql):
    if item["schema"] == "temp":
        db._observe("PRAGMA temp.page_size", {}, preserve_order=True)
    program_rows = db.observe("EXPLAIN " + sql, {}, preserve_order=True)["rows"]
    require(len(db.calls) == 1, "sqlite-reclaim-unexpected-program-coordinate", db.calls)
    decoded = []
    for row in program_rows:
        name, p1, p2, p3 = (field[1] for field in row[1:5])
        if name == "SetCookie":
            decoded.append([name, int(p1), int(p2), int(p3)])
        elif name in ("Integer", "Transaction"):
            decoded.append([name, int(p1), int(p2)])
        elif name == "IncrVacuum":
            decoded.append([name, int(p1)])
        elif name == "ReadCookie":
            decoded.append([name, int(p1), int(p3)])
        elif name == "ResultRow":
            decoded.append([name, int(p2)])
        elif name == "Int64":
            decoded.append([name, int(row[5][1]), int(p2)])
    return {"program": decoded, "programRaw": program_rows, "programInvocation": db.calls[0]}


def actual(db, item, sql):
    files = {}
    before = snapshot(db, files)
    if item["transaction"]:
        db.execute("BEGIN")
    phase = item["phase"]
    statement = (db.observe_preparation(sql) if phase == "prepare"
                 else db.observe(sql, {}, preserve_order=True, capture_evaluation_error=True))
    require(len(db.calls) == 1, "sqlite-reclaim-unexpected-operation", db.calls)
    after = snapshot(db, files)
    rollback = None
    if item["transaction"]:
        require(not db.get_autocommit(db.db), "sqlite-reclaim-lost-transaction")
        db.execute("ROLLBACK")
        require(db.get_autocommit(db.db), "sqlite-reclaim-unclosed-transaction")
        rollback = snapshot(db, files)
    if item["initial"] != "empty-none":
        db.execute("VACUUM main")
    return {"before": before, "after": after, "rollback": rollback, "compacted": snapshot(db, files),
            "statement": statement, "phase": phase, "invocation": db.calls[0], "input": sql, "files": files}


def observe(template, item, sql):
    receipt = isolated(template, item, sql, actual)
    measured = isolated(template, item, sql, program)
    require(measured["programInvocation"] == receipt["invocation"], "sqlite-reclaim-program-operated-on-another-request", measured)
    return {**receipt, **measured}


def semantic(observation):
    return {key: value for key, value in observation.items()
            if key not in ("input", "invocation", "programInvocation", "programRaw", "files")}


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and reply.get("operation") == "reclaim-pragma"
            and all(reply.get(field) == item[field] for field in ("kind", "schema", "value")),
            "sqlite-reclaim-structure", {"expected": item["value"], "actual": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    findings = {"observation": observe(db, item, reply["sql"])}
    if original is not None:
        findings["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **findings)
    model = expected(item)
    for observation in findings.values():
        compare(model, semantic(observation))
    value = item["value"]
    argument = None if value is None or item["kind"] == INCREMENTAL and value == MAXIMUM else str(value)
    compare([item["kind"], item["schema"], argument], findings["observation"]["invocation"])
    if original is not None:
        compare([item["kind"], item["qualifier"], item["text"]], findings["original"]["invocation"])
    if ledger is not None:
        ledger.record(key, "compared")
    return findings.get("original", findings["observation"])


def controls(db):
    item = case(INCREMENTAL, "attached", "two", transaction=True)
    healthy = observe(db, item, source(item))
    model = expected(item)
    compare(model, semantic(healthy))
    count, rollback, cookie = (copy.deepcopy(healthy) for _ in range(3))
    count["statement"]["rows"].pop()
    rollback["rollback"]["databases"]["archive"]["free"] -= 1
    cookie["after"]["databases"]["archive"]["physical"]["incremental"] = 0
    rejected = {}
    for name, altered in (("lost-empty-row", count), ("lost-rollback", rollback), ("ignored-physical-mode", cookie)):
        try:
            compare(model, semantic(altered))
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "sqlite-reclaim-comparator-signal", failure.signal)
            rejected[name] = {"witness": altered, "signal": failure.signal}
        else:
            raise Failure("sqlite-reclaim-comparator-accepted", name)
    return {"healthy": healthy, "rejected": rejected}


def pair(measured):
    reply, native = measured
    return reply, semantic(native)


def execute(args, contract, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, lambda: controls(db))
    return execute_schedule(args, contract, db, worker_factory, schedule, probe,
                            measured_input=lambda reply, observation: (reply, observation), paired_finite=pair)


def fault_probe(item, reply, db):
    if "refusal" in item:
        require(reply["status"] == "rejected" and item["refusal"] in reply.get("message", ""),
                "sqlite-reclaim-invalid-input-accepted", reply)
    else:
        probe(item, reply, db)


def check_faults(args, db, runner=check_render_faults):
    home = "sql-semantics-sqlite/src/Ir/Maintenance/Reclaim/"
    automatic = home + "AutoVacuum.php"
    incremental = home + "IncrementalVacuum.php"
    lowering = home + "ReclaimPragmaLowering.php"
    bad_limit = {**case(INCREMENTAL), "value": 0, "refusal": LIMIT_ERROR}
    unknown = {**case(AUTOMATIC, role="two"), "refusal": SCOPE_ERROR}
    wrong_mode = case(AUTOMATIC, "attached", "one")
    erase_mode = case(AUTOMATIC, "attached", "zero")
    cap = case(INCREMENTAL, "attached", "two")
    negative = case(INCREMENTAL, "attached", "negative")
    resolve = "(new PragmaScope())->resolve($operation->context, $statement->name->schema) ?? new Identifier('main')"
    return runner(args, db, [
        RenderFault("admit-zero-reclamation-limit", home + "PageReclaimLimit.php", "$pages >= 1", "$pages >= 0",
                    bad_limit, "sqlite-reclaim-invalid-input-accepted"),
        RenderFault("write-a-different-automatic-mode", automatic, "$this->mode->value . ')'", "'2)'",
                    wrong_mode, "meaning-mismatch"),
        RenderFault("erase-the-automatic-operand", automatic, " . '.auto_vacuum' . $argument", " . '.auto_vacuum'",
                    erase_mode, "meaning-mismatch"),
        RenderFault("reclaim-from-main-instead-of-attachment", incremental, "$this->schema->quoted()", "'main'",
                    cap, "meaning-mismatch"),
        RenderFault("discard-a-finite-reclaim-limit", incremental, "$this->limit->pages === PageReclaimLimit::MAXIMUM", "true",
                    cap, "meaning-mismatch"),
        RenderFault("treat-a-negative-reclaim-count-as-positive", lowering, "$limit > 0 ? $limit",
                    "$limit !== 0 ? abs($limit)", negative, "sqlite-reclaim-structure", analysis(negative, source(negative))),
        RenderFault("default-unresolved-reclaim-scope-to-main", lowering, resolve + ";\n        $value = $statement->value;\n        if",
                    "new Identifier('main');\n        $value = $statement->value;\n        if", unknown,
                    "sqlite-reclaim-invalid-input-accepted", analysis(unknown, "PRAGMA unavailable.auto_vacuum(2)")),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("reclaim-pragmas", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, NativeDatabase)
