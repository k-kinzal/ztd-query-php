"""Verify SQLite's signed, persistent and connection-wide cache operations."""

import copy
import hashlib
import os
from pathlib import Path
from tempfile import TemporaryDirectory

from run import Failure, Worker, compare, require
from run_sqlite_create_table import quoted
from run_sqlite_page_pragmas import PagerDatabase
from sqlite_cache_cases import (AMOUNT_ERROR, CEILING, DEFAULT, LIVE, ON, SCOPE_ERROR, SPILL, analysis, case,
                                expected, finite_id, finite_ids, forms, negatives, pressure_expected, requests, required, source)
from sqlite_cache_fixture import populate, snapshot, write_pressure
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-cache-pragmas-v1.json"
CONTRACT = "b4cf15cf49ddeb3502284586b80d88abc9c38c61a0d5e5ad9cf228bfd8bd77a6"
FIXTURE = ""
NAMES = frozenset(map(str.encode, (LIVE, DEFAULT, SPILL)))


class NativeDatabase(PagerDatabase):
    """Restrict candidate SQL to a single registered cache operation."""

    policy_digest = hashlib.sha256(f"{PagerDatabase.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def authorize(self, _, action, first, second, schema, origin):
        if origin is None and action == 19 and first is not None and first.lower() in NAMES and schema in self.schemata:
            self.calls.append([first.decode().lower(), None if schema is None else schema.decode(),
                               None if second is None else second.decode()])
            return 0
        if self.denied is None:
            self.denied = (action, [None if field is None else field.decode(errors="replace")
                                    for field in (first, second, schema, origin)])
        return 1


def isolated(template, item, sql, action, *, pressure=None):
    with TemporaryDirectory(prefix="semantics-ir-sqlite-cache-") as directory:
        previous = Path.cwd()
        os.chdir(directory)
        db = NativeDatabase(template.library, b"primary.sqlite")
        try:
            populate(db, item, pressure=pressure is not None)
            db.schemata = frozenset((None, b"main", b"temp", item["attachment"].encode()))
            if pressure is not None:
                return action(db, item, sql, pressure)
            return action(db, item, sql)
        finally:
            db.check(db.close(db.db))
            os.chdir(previous)


def program(db, item, sql):
    if item["schema"] == "temp":
        db._observe("PRAGMA temp.page_size", {}, preserve_order=True)
    prepared = db.observe("EXPLAIN " + sql, {}, preserve_order=True)["rows"]
    require(len(db.calls) == 1, "sqlite-cache-unexpected-program-coordinate", db.calls)
    decoded = []
    for row in prepared:
        name, p1, p2, p3 = (field[1] for field in row[1:5])
        if name == "SetCookie":
            decoded.append([name, int(p1), int(p2), int(p3)])
        elif name == "Transaction":
            decoded.append([name, int(p1), int(p2)])
        elif name == "ReadCookie":
            decoded.append([name, int(p1), int(p3)])
        elif name == "ResultRow":
            decoded.append([name, int(p2)])
        elif name == "Int64":
            decoded.append([name, int(row[5][1])])
    return {"program": decoded, "programRaw": prepared, "programInvocation": db.calls[0]}


def actual(db, item, sql):
    files = {}
    before = snapshot(db, files)
    if item["transaction"]:
        db.execute("BEGIN")
    phase = item["phase"]
    statement = (db.observe_preparation(sql) if phase == "prepare"
                 else db.observe(sql, {}, preserve_order=True, capture_evaluation_error=True))
    require(len(db.calls) == 1, "sqlite-cache-unexpected-operation", db.calls)
    after = snapshot(db, files)
    rollback = None
    if item["transaction"]:
        require(not db.get_autocommit(db.db), "sqlite-cache-lost-transaction")
        db.execute("ROLLBACK")
        require(db.get_autocommit(db.db), "sqlite-cache-unclosed-transaction")
        rollback = snapshot(db, files)
    db.execute("PRAGMA cache_spill=ON")
    revealed = snapshot(db, files)
    connection = NativeDatabase(db.library, b"primary.sqlite")
    try:
        connection.execute("ATTACH 'attached.sqlite' AS " + quoted(item["attachment"]))
        reopened = snapshot(connection, files)
    finally:
        connection.check(connection.close(connection.db))
    return {"before": before, "after": after, "rollback": rollback, "revealed": revealed, "reopened": reopened,
            "statement": statement, "phase": phase, "invocation": db.calls[0], "input": sql, "files": files}


def pressure_trial(db, item, sql, scope):
    if item["pressure"] == "inside":
        db.execute("BEGIN")
    outcome = db.observe(sql, {}, preserve_order=True, capture_evaluation_error=True)
    require(outcome["evaluationError"] is None and len(db.calls) == 1,
            "sqlite-cache-pressure-unexpected-operation", outcome)
    if item["pressure"] == "before":
        db.execute("BEGIN")
    return write_pressure(db, scope)


def observe(template, item, sql):
    receipt = isolated(template, item, sql, actual)
    measured = isolated(template, item, sql, program)
    require(measured["programInvocation"] == receipt["invocation"], "sqlite-cache-program-operated-on-another-request", measured)
    pressure = None
    if item["pressure"] is not None:
        pressure = {scope: isolated(template, item, sql, pressure_trial, pressure=scope)
                    for scope in ("main", item["attachment"])}
    return {**receipt, **measured, "pressure": pressure}


def semantic(observation):
    return {key: value for key, value in observation.items()
            if key not in ("input", "invocation", "programInvocation", "programRaw", "files", "pressure")}


def rendered_argument(item):
    value = item["value"]
    if value is None:
        return None
    if item["kind"] != SPILL:
        return str(value)
    if value[0] == "switch":
        return value[1]
    _, amount, mode = value
    return ("+" if mode == ON and amount > 0 and amount % 256 == 0 else "") + str(amount)


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and reply.get("operation") == "cache-pragma"
            and all(reply.get(field) == item[field] for field in ("kind", "schema", "value")),
            "sqlite-cache-structure", {"expected": item["value"], "actual": reply})
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
        if item["pressure"] is not None:
            compare(pressure_expected(item), observation["pressure"])
    compare([item["kind"], item["schema"], rendered_argument(item)], findings["observation"]["invocation"])
    if original is not None:
        compare([item["kind"], item["qualifier"], item["text"]], findings["original"]["invocation"])
    if ledger is not None:
        ledger.record(key, "compared")
    return findings.get("original", findings["observation"])


def controls(db):
    item = case(SPILL, "attached", "quoted-plus", pressure="inside")
    healthy = observe(db, item, source(item))
    model = expected(item)
    pressure = pressure_expected(item)
    compare(model, semantic(healthy))
    compare(pressure, healthy["pressure"])

    hidden, local, actual_pressure = (copy.deepcopy(healthy) for _ in range(3))
    hidden["revealed"]["databases"]["archive"]["spill"] = 33
    local["after"]["databases"]["main"]["spill"] = 0
    actual_pressure["pressure"]["archive"]["spills"] = 5
    rejected = {}
    for name, altered in (("lost-underlying-pager-cap", hidden), ("lost-decoy-connection-flag", local),
                          ("ignored-physical-spills", actual_pressure)):
        try:
            compare(model, semantic(altered))
            compare(pressure, altered["pressure"])
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "sqlite-cache-comparator-signal", failure.signal)
            rejected[name] = {"witness": altered, "signal": failure.signal}
        else:
            raise Failure("sqlite-cache-comparator-accepted", name)
    return {"healthy": healthy, "rejected": rejected}


def pair(measured):
    reply, receipt = measured
    return reply, semantic(receipt)


def execute(args, contract, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, lambda: controls(db))
    return execute_schedule(args, contract, db, worker_factory, schedule, probe,
                            measured_input=lambda reply, observation: (reply, observation), paired_finite=pair)


def fault_probe(item, reply, db):
    if "refusal" in item:
        require(reply["status"] == "rejected" and item["refusal"] in reply.get("message", ""),
                "sqlite-cache-invalid-input-accepted", reply)
    else:
        probe(item, reply, db)


def check_faults(args, db, runner=check_render_faults):
    home = "sql-semantics-sqlite/src/Ir/Maintenance/Cache/"
    capacity = case(LIVE, "attached", "negative", phase="prepare")
    bad = {**capacity, "value": CEILING + 1, "refusal": AMOUNT_ERROR}
    default = case(DEFAULT, "attached", "negative")
    disabled = case(SPILL, "attached", "low-byte-zero")
    enabled = case(SPILL, "attached", "quoted-plus")
    unknown = {**capacity, "refusal": SCOPE_ERROR}
    resolve = "(new PragmaScope())->resolve($operation->context, $statement->name->schema) ?? new Identifier('main')"
    return runner(args, db, [
        RenderFault("permit-out-of-range-cache-amount", home + "CacheAmount.php", "$value <= 2147483647",
                    "$value <= 2147483648", bad, "sqlite-cache-invalid-input-accepted"),
        RenderFault("erase-live-cache-request", home + "CacheSize.php", " . '.cache_size' . $argument",
                    " . '.cache_size'", capacity, "meaning-mismatch"),
        RenderFault("write-default-only-to-live-capacity", home + "DefaultCacheSize.php", "'.default_cache_size'",
                    "'.cache_size'", default, "meaning-mismatch"),
        RenderFault("change-main-instead-of-selected-default", home + "DefaultCacheSize.php", "$this->schema->quoted()",
                    "'main'", default, "meaning-mismatch"),
        RenderFault("lose-threshold-while-disabled", home + "CacheSpill.php", "$change->sqlArgument()",
                    "($change->mode === SpillMode::Disabled ? 'OFF' : $change->sqlArgument())", disabled, "meaning-mismatch"),
        RenderFault("emit-enabled-low-byte-zero-as-disabled", home + "SpillThreshold.php", "\"'+\" . $value . \"'\"",
                    "(string) $value", enabled, "meaning-mismatch"),
        RenderFault("decode-leading-plus-as-unconditional-native-digit", home + "SpillArgument.php", r"\A[0-9]",
                    r"\A\+?[0-9]", enabled, "sqlite-cache-structure", analysis(enabled, source(enabled))),
        RenderFault("default-an-unresolved-cache-scope", home + "CachePragmaLowering.php",
                    "): CacheSize\n    {\n        $schema = " + resolve,
                    "): CacheSize\n    {\n        $schema = new Identifier('main')", unknown,
                    "sqlite-cache-invalid-input-accepted", analysis(unknown, "PRAGMA unavailable.cache_size(2)")),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("cache-pragmas", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, NativeDatabase)
