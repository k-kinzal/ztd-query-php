"""Check native pager requests at preparation, execution and a later file rewrite."""

import copy
import ctypes
import hashlib
import os
from pathlib import Path
from tempfile import TemporaryDirectory

from run import Failure, Worker, compare, require
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import Database, ObserverAuthorizationError
from sqlite_flag_pragma_cases import result
from sqlite_page_cases import analysis, case, expected, finite_id, finite_ids, forms, negatives, requests, required, source
from sqlite_page_fixture import KINDS, populate, snapshot, try_growth
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-page-pragmas-v2.json"
CONTRACT = "c2cbc5802be0f65bca2af3b25c3ee979aba12702785b4d81920c6bf9b8114164"
FIXTURE = ""
PRAGMAS = frozenset(name.encode() for name in KINDS)


class PagerDatabase(Database):
    """Restricts the candidate to a single pager request on a known fixture schema."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library, filename=b":memory:"):
        super().__init__(library, filename)
        self.schemata = frozenset()
        self.calls = []

    def authorize(self, _, action, first, second, schema, origin):
        if origin is None and action == 19 and first is not None and first.lower() in PRAGMAS and schema in self.schemata:
            self.calls.append([first.decode().lower(), None if schema is None else schema.decode(),
                               None if second is None else second.decode()])
            return 0
        if self.denied is None:
            self.denied = (action, [None if field is None else field.decode(errors="replace")
                                    for field in (first, second, schema, origin)])
        return 1

    def observe_preparation(self, sql):
        self.denied = None
        prepared, tail = ctypes.c_void_p(), ctypes.c_char_p()
        data = sql.encode()
        self.check(self.set_authorizer(self.db, self.authorizer, None))
        try:
            self.check(self.prepare_v2(self.db, data, len(data) + 1, ctypes.byref(prepared), ctypes.byref(tail)))
            require(prepared and not tail.value.strip(), "sqlite-page-multiple-statements")
            names = [self.column_name(prepared, index).decode() for index in range(self.column_count(prepared))]
            count = self.bind_parameter_count(prepared)
            parameters = {}
            for index in range(1, count + 1):
                label = self.bind_parameter_name(prepared, index)
                if label is not None:
                    parameters[str(index)] = label.decode()
            return {"names": names, "parameterCount": count, "parameters": parameters,
                    "readOnly": bool(self.stmt_readonly(prepared))}
        finally:
            if prepared:
                self.finalize(prepared)
            self.check(self.set_authorizer(self.db, None, None))
            if self.denied is not None:
                raise ObserverAuthorizationError(*self.denied)


def isolated(template, item, sql, action):
    with TemporaryDirectory(prefix="semantics-ir-sqlite-pager-") as temporary:
        previous = Path.cwd()
        os.chdir(temporary)
        db = PagerDatabase(template.library, b"primary.sqlite")
        try:
            populate(db, item)
            db.schemata = frozenset((None, b"main", b"temp", item["attachment"].encode()))
            return action(db, item, sql)
        finally:
            db.check(db.close(db.db))
            os.chdir(previous)


def program(db, item, sql):
    # EXPLAIN does not open the temp pager. Give the separate program fixture its native pager.
    if item["initial"] != "temp-warm":
        db._observe("PRAGMA temp.page_size", {}, preserve_order=True)
    prepared = db.observe("EXPLAIN " + sql, {}, preserve_order=True)["rows"]
    require(len(db.calls) == 1, "sqlite-page-unexpected-program-coordinate", db.calls)
    slots, interpreted = [], []
    for row in prepared:
        name, p1, _, p3 = (field[1] for field in row[1:5])
        if name == "Transaction":
            slots.append(int(p1))
        elif name == "Pagecount":
            interpreted.append([name, int(p1)])
        elif name == "Int64":
            interpreted.append([name, int(row[5][1])])
        elif name == "MaxPgcnt":
            interpreted.append([name, int(p1), int(p3) & 0xffffffff])
    return {"slots": slots, "opcode": interpreted, "program": prepared, "programInvocation": db.calls[0]}


def actual(db, item, sql):
    file_history = {}
    before = snapshot(db, item, file_history)
    if item.get("growth"):
        db.execute("BEGIN")
    phase = item["phase"]
    statement = (db.observe_preparation(sql) if phase == "prepare"
                 else db.observe(sql, {}, preserve_order=True, capture_evaluation_error=True))
    require(len(db.calls) == 1, "sqlite-page-unexpected-operation", db.calls)
    after = snapshot(db, item, file_history)
    growth = try_growth(db, item, file_history) if item.get("growth") else None
    if item["initial"] != "empty":
        db.execute("VACUUM main")
    return {"before": before, "after": after, "compacted": snapshot(db, item, file_history), "statement": statement,
            "phase": phase, "invocation": db.calls[0], "input": sql, "files": file_history, "growth": growth}


def observe(template, item, sql):
    receipt = isolated(template, item, sql, actual)
    measured = isolated(template, item, sql, program)
    require(measured["programInvocation"] == receipt["invocation"], "sqlite-page-program-operated-on-another-request", measured)
    return {**receipt, **measured}


def semantic(observation):
    return {key: value for key, value in observation.items()
            if key not in ("invocation", "input", "program", "programInvocation", "files", "growth")}


def expected_growth(state):
    return {"write": {**result(), "readOnly": False, "evaluationError": {"code": 13, "message": "database or disk is full"}},
            "rows": result(rows=((1, bytes([1])), (5, None)))["rows"], "autocommit": True, "state": state}


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and reply.get("operation") == "page-pragma"
            and all(reply.get(field) == item[field] for field in ("kind", "schema", "value")),
            "sqlite-page-structure", {"expected": item["value"], "actual": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    findings = {"observation": observe(db, item, reply["sql"])}
    if original is not None:
        findings["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **findings)
    value = item["value"]
    compare([item["kind"], item["schema"], None if value[0] == "read" else str(value[1])], findings["observation"]["invocation"])
    if original is not None:
        compare([item["kind"], item["qualifier"], item["text"]], findings["original"]["invocation"])
    model = expected(item)
    for observation in findings.values():
        compare(model, semantic(observation))
        if item.get("growth"):
            compare(expected_growth(model["after"]), observation["growth"])
    if ledger is not None:
        ledger.record(key, "compared")
    return findings.get("original", findings["observation"])


def controls(db):
    item = case("page_size", "attached", "valid-size", phase="prepare")
    healthy = observe(db, item, source(item))
    model = expected(item)
    compare(model, semantic(healthy))
    lost_pending, lost_selected, lost_phase = (copy.deepcopy(healthy) for _ in range(3))
    lost_pending["compacted"]["databases"]["main"]["page_size"] = 1024
    lost_selected["after"]["databases"]["archive"]["page_count"] = 0
    lost_phase["statement"]["names"].append("page_size")
    rejected = {}
    for name, altered in (("lost-connection-pending-size", lost_pending), ("ignored-selected-page-state", lost_selected),
                          ("read-a-size-write", lost_phase)):
        try:
            compare(model, semantic(altered))
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "sqlite-page-comparator-signal", failure.signal)
            rejected[name] = {"witness": altered, "signal": failure.signal}
        else:
            raise Failure("sqlite-page-comparator-accepted", name)
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
        require(reply["status"] == "rejected" and item["refusal"] in reply.get("message", ""), "sqlite-page-invalid-setting-accepted", reply)
    else:
        probe(item, reply, db)


def check_faults(args, db, runner=check_render_faults):
    home = "sql-semantics-sqlite/src/Ir/Maintenance/Page/"
    output, lower, decoder = (home + filename for filename in ("PagePragma.php", "PagePragmaLowering.php", "PageLimitArgument.php"))
    correct = case("page_size", "attached", "valid-size", phase="prepare")
    mismatch = {**case("page_count"), "value": ["size", 512], "refusal": "must match its native operation"}
    large = case("max_page_count", "main", "above-native")
    trailing = case("max_page_count", "main", "prefix-tail")
    invalid = case("page_size", "main", spelling=("DEFAULT", "DEFAULT", 0, None))
    zero = case("page_size", "main", "zero", phase="prepare")
    return runner(args, db, [
        RenderFault("allow-page-count-to-take-size-request", output, "PagePragmaKind::Count => $value === null",
                    "PagePragmaKind::Count => true", mismatch, "sqlite-page-invalid-setting-accepted"),
        RenderFault("erase-a-requested-page-size", output, "$value instanceof RequestedPageSize => '(' . $value->bytes . ')'",
                    "$value instanceof RequestedPageSize => ''", correct, "meaning-mismatch"),
        RenderFault("query-main-pager-instead-of-attachment", output, "$this->schema->quoted()",
                    "'main'", correct, "meaning-mismatch"),
        RenderFault("skip-native-maximum-page-count-clamp", decoder, "return new PageLimit($limit);",
                    "return new PageLimit($decimal);", large, "sqlite-page-structure", analysis(large, source(large))),
        RenderFault("accept-invalid-maximum-count-decimal-suffix", decoder,
                    r"[\x09-\x0d ]*\+?([0-9]+)[\x09-\x0d ]*\z", r"[\x09-\x0d ]*\+?([0-9]+)",
                    trailing, "sqlite-page-structure", analysis(trailing, source(trailing))),
        RenderFault("treat-invalid-size-request-as-read", lower, "new RequestedPageSize((new NativePragmaInteger())->parse($text) ?? 0)",
                    "(new NativePragmaInteger())->parse($text) === null ? null : new RequestedPageSize((new NativePragmaInteger())->parse($text))",
                    invalid, "sqlite-page-structure", analysis(invalid, source(invalid))),
        RenderFault("drop-zero-reset-of-earlier-page-size", output, "$value instanceof RequestedPageSize => '(' . $value->bytes . ')'",
                    "$value instanceof RequestedPageSize => $value->bytes === 0 ? '' : '(' . $value->bytes . ')'",
                    zero, "meaning-mismatch"),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("page-pragmas", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, PagerDatabase)
