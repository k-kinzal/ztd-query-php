"""Compare native SQLite header pragma effects, page-one values and transaction state."""

import copy
import hashlib
from pathlib import Path
from tempfile import TemporaryDirectory

from run import Failure, Worker, compare, require
from run_sqlite_create_table import quoted
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import Database
from sqlite_flag_pragma_cases import result
from sqlite_header_cases import analysis, case, expected, finite_id, finite_ids, forms, negatives, requests, required, source
from sqlite_header_fixture import COOKIE, fixture, initial_names, populate, rows
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-header-pragmas-v1.json"
CONTRACT = "09e3bdc05914c05d24212d3e9b88c96c4a9812c34d5257599996a6cbfa0a0d58"
FIXTURE = ""
FIELDS = frozenset(name.encode() for name in COOKIE)


class HeaderDatabase(Database):
    """Allows only the independent contract's header operations and known database identities."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library, filename=b":memory:"):
        super().__init__(library, filename)
        self.schemata = frozenset()
        self.calls = []

    def authorize(self, _, action, first, second, schema, origin):
        if origin is None and action == 19 and first is not None and first.lower() in FIELDS and schema in self.schemata:
            self.calls.append([first.decode().lower(), None if schema is None else schema.decode(),
                               None if second is None else second.decode()])
            return 0
        if self.denied is None:
            self.denied = (action, [None if field is None else field.decode(errors="replace")
                                    for field in (first, second, schema, origin)])
        return 1

    def observe_header(self, sql):
        self.calls.clear()
        statement = self.observe(sql, {}, preserve_order=True, capture_evaluation_error=True)
        require(len(self.calls) == 1, "sqlite-header-native-operation", self.calls)
        return statement, self.calls[0]


def set_access(db, item):
    db.schemata = frozenset((None, b"main", b"temp", item["attachment"].encode()))


def inspect_program(db, sql, invocation):
    db.calls.clear()
    program = db.observe("EXPLAIN " + sql, {}, preserve_order=True)["rows"]
    require(db.calls == [invocation], "sqlite-header-native-program-coordinate", db.calls)
    slots, opcodes = [], []
    for _, instruction, first, second, third, _, fifth, _ in program:
        name = instruction[1]
        if name == "Transaction":
            slots.append([int(first[1]), int(second[1])])
        if name in ("ReadCookie", "SetCookie") or (name == "Noop" and fifth[1] == "1"):
            opcodes.append([name, int(first[1]), int(second[1]), int(third[1]), int(fifth[1])])
    require(len(opcodes) == 1, "sqlite-header-exact-cookie-opcode", [invocation, program])
    return slots, opcodes[0], program


def observe(template, item, sql):
    db = HeaderDatabase(template.library)
    try:
        populate(db, item)
        set_access(db, item)
        names = initial_names(item)
        before = fixture(db, names)
        statement, invocation = db.observe_header(sql)
        after = fixture(db, names)
        slots, opcode, program = inspect_program(db, sql, invocation)
        if item["transaction"]:
            db.execute("ROLLBACK")
        return {"statement": statement, "slots": slots, "opcode": opcode, "program": program,
                "before": before, "after": after, "settled": fixture(db, names), "invocation": invocation, "input": sql}
    finally:
        db.check(db.close(db.db))


def read_history(template, item, sql):
    with TemporaryDirectory(prefix="semantics-ir-sqlite-cookie-") as directory:
        filename = str(Path(directory) / "header.db")
        scope = item["schema"]
        attached = scope != "main"
        first = HeaderDatabase(template.library, b":memory:" if attached else filename.encode())
        second = Database(template.library, filename.encode())
        try:
            if attached:
                # The filename comes from the verifier's temporary directory.
                first.execute("ATTACH '" + filename.replace("'", "''") + "' AS " + quoted(scope))
            qualified = quoted(scope) + "."
            first.execute("CREATE TABLE " + qualified + "entries(n)")
            set_access(first, item)
            reads = [first.observe_header(sql)[0]]
            other = [rows(second, "PRAGMA data_version")]
            second.execute("INSERT INTO entries VALUES (17)")
            reads.append(first.observe_header(sql)[0])
            other.append(rows(second, "PRAGMA data_version"))
            first.execute("INSERT INTO " + qualified + "entries VALUES (23)")
            reads.append(first.observe_header(sql)[0])
            other.append(rows(second, "PRAGMA data_version"))
            first.execute("PRAGMA " + qualified + "user_version=19")
            reads.append(first.observe_header(sql)[0])
            other.append(rows(second, "PRAGMA data_version"))
            return {"read": reads, "other": other, "rows": rows(second, "SELECT n FROM entries ORDER BY n"),
                    "cookie": rows(second, "PRAGMA user_version")}
        finally:
            second.check(second.close(second.db))
            first.check(first.close(first.db))


def expected_history():
    return {"read": [{**result(("data_version",), ((version,),)), "evaluationError": None} for version in (1, 2, 2, 2)],
            "other": [result(rows=((version,),))["rows"] for version in (1, 1, 2, 3)],
            "rows": result(rows=((17,), (23,)))["rows"], "cookie": [[["integer", "19"]]]}


def semantic(observation):
    return {key: value for key, value in observation.items() if key not in ("input", "invocation", "program", "history")}


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and reply.get("operation") == "header-pragma"
            and all(reply.get(field) == item[field] for field in ("field", "schema", "value")),
            "sqlite-header-structure", {"expected": [item[field] for field in ("field", "schema", "value")], "actual": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    statements = {"observation": reply["sql"]}
    if original is not None:
        statements["original"] = original
    outcomes = {role: observe(db, item, sql) for role, sql in statements.items()}
    if item.get("fileHistory"):
        for role, sql in statements.items():
            outcomes[role]["history"] = read_history(db, item, sql)
    if ledger is not None:
        ledger.record(key, "observed", **outcomes)
    compare([item["field"], item["schema"], None if item["value"] is None else str(item["value"])],
            outcomes["observation"]["invocation"])
    if original is not None:
        compare([item["field"], item["qualifier"], item["argument"]], outcomes["original"]["invocation"])
    for native in outcomes.values():
        compare(expected(item), semantic(native))
        if item.get("fileHistory"):
            compare(expected_history(), native["history"])
    if ledger is not None:
        ledger.record(key, "compared")
    return outcomes.get("original", outcomes["observation"])


def controls(db):
    item = case("application_id", "attached", "minimum", transaction=True)
    healthy = observe(db, item, source(item))
    model = expected(item)
    compare(model, semantic(healthy))
    changed_cookie, lost_rollback, unsigned = (copy.deepcopy(healthy) for _ in range(3))
    changed_cookie["slots"] = [[0, 1]]
    lost_rollback["settled"]["headers"][item["attachment"]]["application_id"] = item["value"]
    unsigned["after"]["headers"][item["attachment"]]["application_id"] = 2147483648
    failures = {}
    for name, altered in (("affected-main-instead-of-attached", changed_cookie), ("ignored-transaction-rollback", lost_rollback),
                          ("lost-signed-storage-meaning", unsigned)):
        try:
            compare(model, semantic(altered))
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "sqlite-header-comparator-signal", failure.signal)
            failures[name] = {"witness": altered, "signal": failure.signal}
        else:
            raise Failure("sqlite-header-comparator-accepted", name)
    return {"healthy": healthy, "rejected": failures}


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
                "sqlite-header-invalid-value-accepted", reply)
    else:
        probe(item, reply, db)


def check_faults(args, db, runner=check_render_faults):
    root = "sql-semantics-sqlite/src/Ir/Maintenance/"
    base = root + "Header/"
    output, lowering, integer = (base + name for name in ("HeaderPragma.php", "HeaderPragmaLowering.php", "HeaderInteger.php"))
    parser = root + "Argument/NativePragmaInteger.php"
    selected = case("application_id", "attached", "minimum")
    invalid = case("user_version", "main", "invalid-text")
    hex_value = case("application_id", "main", "signed-hexadecimal")
    overflow = {**selected, "value": 2147483648, "refusal": "requires a signed 32-bit integer"}
    read_only = {**selected, "field": "data_version", "refusal": "cannot carry a cookie write"}
    return runner(args, db, [
        RenderFault("admit-header-overflow", integer, "$value >= -2147483648 && $value <= 2147483647",
                    "$value >= -2147483648", overflow, "sqlite-header-invalid-value-accepted"),
        RenderFault("let-read-only-header-have-a-write", output, "$value === null || $field instanceof HeaderCookie",
                    "true", read_only, "sqlite-header-invalid-value-accepted"),
        RenderFault("write-main-instead-of-resolved-header", output, "$this->schema->quoted()",
                    "'main'", selected, "meaning-mismatch"),
        RenderFault("write-user-version-for-another-cookie", output, "$this->field->value",
                    "HeaderCookie::UserVersion->value", selected, "meaning-mismatch"),
        RenderFault("treat-an-unparseable-cookie-operand-as-read", lowering,
                    "$integer = (new NativePragmaInteger())->parse($text) ?? 0;",
                    "$integer = (new NativePragmaInteger())->parse($text); if ($integer === null) { return new HeaderPragma($field, $schema); }",
                    invalid, "sqlite-header-structure", analysis(invalid, source(invalid))),
        RenderFault("erase-cookie-write-operand", output, "$this->value === null ? '' : '(' . $this->value->value . ')'",
                    "''", selected, "meaning-mismatch"),
        RenderFault("interpret-negative-hexadecimal-as-a-header-number", parser, r"/\A0[xX]([0-9a-fA-F]+)/",
                    r"/\A-?0[xX]([0-9a-fA-F]+)/", hex_value, "sqlite-header-structure", analysis(hex_value, source(hex_value))),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("header-pragmas", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, HeaderDatabase)
