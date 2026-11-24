"""Compare completed SQLite connection-flag statements with independent native observations."""
import copy
import ctypes as c
import hashlib
import json
from pathlib import Path

from run import Failure, Worker, compare, require
from run_sqlite_create_table import quoted
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import Database
from sqlite_pragma_inventory import active_inventory
from sqlite_flag_pragma_cases import analyzed, case, expected, finite_id, finite_ids, forms, negatives, requests, required
from sqlite_flag_pragma_effects import FIXTURES, expected_effects
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-flag-pragmas-v1.json"
CONTRACT = "22b43117680de193553fb0154503d593b8788c6c858ea80aee98c7c587e1bcfe"
NAMESPACE = "ba957109ec9febbe65ec7874f647c0549233c7617166eed9d613315042dba010"
FIXTURE = ""


def namespace():
    raw = Path(__file__).with_name("sqlite-pragma-namespace-v1.json").read_bytes()
    require(hashlib.sha256(raw).hexdigest() == NAMESPACE, "sqlite-pragma-namespace-digest")
    return json.loads(raw)


class FlagDatabase(Database):
    """Restricts emitted SQL to the active native flag namespace on disposable databases."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}:{NAMESPACE}".encode()).hexdigest()

    def __init__(self, library):
        super().__init__(library)
        catalog = namespace()
        active = frozenset(row[0][1] for row in self._observe("PRAGMA pragma_list", {})["rows"])
        self.flags = frozenset(name.encode() for name in active if catalog["required"].get(name, {}).get("family") == "FLAG")
        self.schemata = frozenset()
        self.events = []

    def authorize(self, _, action, first, second, schema, origin):
        if origin is None and action == 19 and first is not None and first.lower() in self.flags and schema in self.schemata:
            self.events.append([first.decode(), None if schema is None else schema.decode(),
                                None if second is None else second.decode()])
            return 0
        if self.denied is None:
            self.denied = (action, [None if value is None else value.decode(errors="replace") for value in (first, second, schema, origin)])
        return 1

    def prepare_only(self, sql):
        statement, tail = c.c_void_p(), c.c_char_p()
        encoded = sql.encode()
        self.denied = None
        self.check(self.set_authorizer(self.db, self.authorizer, None))
        try:
            self.check(self.prepare_v2(self.db, encoded, len(encoded) + 1, c.byref(statement), c.byref(tail)))
            require(bool(statement) and not tail.value.strip(), "sqlite-flag-single-statement")
        finally:
            if statement:
                self.check(self.finalize(statement))
            self.check(self.set_authorizer(self.db, None, None))
            require(self.denied is None, "sqlite-flag-prepare-authorization", self.denied)


def setup(db, item):
    flag, attachment = item["flag"], item["attachment"]
    db.execute("ATTACH ':memory:' AS " + quoted(attachment))
    db.schemata = frozenset((None, b"main", b"temp", attachment.encode()))
    fixture, effect_sql = FIXTURES.get(flag, ("", ()))
    if fixture:
        db.execute(fixture)
    db.execute(f"PRAGMA {flag} = {item['initial']}")
    if item["transaction"]:
        db.execute("BEGIN")
    return effect_sql


def rows(db, sql):
    return db._observe(sql, {}, preserve_order=True)["rows"]


def effect(db, sql):
    try:
        return db._observe(sql, {}, preserve_order=True, capture_evaluation_error=True)
    except RuntimeError as error:
        if sql in ("SELECT id FROM old", "SELECT id FROM fresh") and str(error) == "SQLite 1: no such table: " + sql.split()[-1]:
            return {"prepareError": str(error)}
        raise


def observe(template, item, sql):
    flag_read = "PRAGMA " + item["flag"]
    db = type(template)(template.library)
    try:
        setup(db, item)
        db.prepare_only(sql)
        prepared = rows(db, flag_read)
        prepared_authorization = db.events.copy()
    finally:
        db.check(db.close(db.db))

    db = type(template)(template.library)
    try:
        effect_sql = setup(db, item)
        initial = rows(db, flag_read)
        before = rows(db, "PRAGMA database_list")
        statement = db.observe(sql, {}, preserve_order=True, capture_evaluation_error=True)
        after = rows(db, flag_read)
        after_schemas = rows(db, "PRAGMA database_list")
        active = not bool(db.get_autocommit(db.db))
        require(len(db.events) == 1 and db.events == prepared_authorization, "sqlite-flag-native-invocation", db.events)
        name, schema, argument = db.events[0]
        consequences = [effect(db, statement) for statement in effect_sql]
        return {
            "initial": initial, "afterPrepare": prepared, "after": after, "authorization": [name.lower(), schema, argument is not None],
            "schemasBefore": before, "schemasAfter": after_schemas, "active": active, "statement": statement,
            "effects": consequences, "rawArguments": prepared_authorization,
        }
    finally:
        db.check(db.close(db.db))


def semantic(observation):
    return {key: value for key, value in observation.items() if key != "rawArguments"}


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and reply.get("operation") == "flag-pragma"
            and all(reply.get(field) == item[field] for field in ("flag", "schema", "change")),
            "sqlite-flag-structure", {"expected": item, "actual": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    findings = {"observation": observe(db, item, reply["sql"])}
    if original is not None:
        findings["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **findings)
    prediction = expected(item, expected_effects)
    for finding in findings.values():
        compare(prediction, semantic(finding))
    if ledger is not None:
        ledger.record(key, "compared")
    return findings["observation"]


def controls(db):
    item = case("writable_schema", "RESET", "temp", initial=1)
    target = expected(item, expected_effects)
    healthy = observe(db, item, "PRAGMA temp.writable_schema = RESET")
    compare(target, semantic(healthy))
    ordinary = observe(db, item, "PRAGMA temp.writable_schema = OFF")
    lost_name = copy.deepcopy(healthy)
    lost_name["authorization"][0] = "fullfsync"
    mismatches = {}
    for label, observation in (("lost-schema-reload", ordinary), ("wrong-flag", lost_name)):
        try:
            compare(target, semantic(observation))
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-flag-comparator-control-signal", str(error))
            mismatches[label] = {"witness": observation, "signal": error.signal}
        else:
            raise Failure("sqlite-flag-comparator-accepted", label)
    sep = db.lib.sqlite3_prepare_v2
    statement, tail = c.c_void_p(), c.c_char_p()
    sep_sql = b"PRAGMA automatic_index = 2_56"
    rc = sep(db.db, sep_sql, len(sep_sql) + 1, c.byref(statement), c.byref(tail))
    message = db.errmsg(db.db).decode()
    require(rc == 1 and not statement and 'near "2_56": syntax error' in message,
            "sqlite-flag-native-separator-calibration", (rc, message))
    return {"healthy": healthy, "rejected": mismatches, "nativeSeparator": {"code": rc, "message": message}}


def execute(args, specification, db, worker_factory=Worker):
    catalog = namespace()
    engine = active_inventory(db.library)
    approved_profiles = [name for name, profile in catalog["profiles"].items() if engine["activeNames"] == profile["activeNames"]]
    require(len(approved_profiles) == 1 and set(engine["activeNames"]) <= catalog["required"].keys(),
            "sqlite-pragma-native-namespace-changed", engine)
    active = frozenset(engine["activeNames"])
    known = frozenset(name for name, spec in catalog["required"].items() if spec["family"] == "FLAG" and name in active)
    require(known == set(specification["finite"]["flags"]) == {name.decode() for name in db.flags}, "sqlite-flag-native-namespace")
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, lambda: controls(db))
    checks = execute_schedule(args, specification, db, worker_factory, schedule, probe,
                              measured_input=lambda reply, observation: (reply, observation))
    return {**checks, "namespace": {
        "contract": NAMESPACE, "engine": engine, "profile": approved_profiles[0],
        "exercisedFlagNames": sorted(known), "otherActiveNames": sorted(active - known),
        "unavailableNativeNames": sorted(catalog["required"].keys() - active),
    }}


def fault_probe(item, reply, db):
    if item.get("probe") == "illegal-reload":
        require(reply["status"] == "rejected" and "Only writable_schema" in reply.get("message", ""),
                "sqlite-flag-wrong-reset-accepted", reply)
    else:
        probe(item, reply, db)


def check_faults(args, db, runner=check_render_faults):
    root = "sql-semantics-sqlite/src/Ir/Maintenance/"
    output, argument = root + "FlagPragma.php", root + "FlagArgument.php"
    enable, qualified, reset = case(), case("foreign_keys", "read", "temp", initial=1), case("writable_schema", "RESET", initial=1)
    invalid = {**case("fullfsync", "RESET"), "probe": "illegal-reload"}
    modulo = case("automatic_index", "0", initial=1)
    return runner(args, db, [
        RenderFault("accept-reset-for-wrong-flag", output, "$change !== FlagChange::Reset || $name === FlagName::WritableSchema",
                    "true", invalid, "sqlite-flag-wrong-reset-accepted"),
        RenderFault("always-emit-same-setting", output, "'PRAGMA ' . $qualifier . $this->name->value . $argument",
                    "'PRAGMA automatic_index = 0'", enable, "meaning-mismatch"),
        RenderFault("output-different-connection-flag", output, "$this->name->value", "FlagName::ForeignKeys->value",
                    enable, "meaning-mismatch"),
        RenderFault("lose-flag-database-qualification", output, "$qualifier . $this->name->value", "$this->name->value",
                    qualified, "meaning-mismatch"),
        RenderFault("disable-without-schema-reload", output, "$this->change->value",
                    "($this->change === FlagChange::Reset ? FlagChange::Disable : $this->change)->value", reset, "meaning-mismatch"),
        RenderFault("omit-flag-change-operand", output, " . $argument;", ";", enable, "meaning-mismatch"),
        RenderFault("lose-native-byte-coercion", argument, "(intval($digits, $base) & 255) !== 0",
                    "intval($digits, $base) !== 0", modulo, "sqlite-flag-structure",
                    analyzed(modulo, "PRAGMA automatic_index = 256")),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("connection-flag-pragmas", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, FlagDatabase)
