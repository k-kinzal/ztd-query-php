"""Compare resolved trigger removal, native verification and subsequent trigger firing."""

from copy import deepcopy
import hashlib
from itertools import product

from run import Failure, Worker, compare, require
from run_sqlite_create_table import quoted
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_drop_trigger_cases import (
    AXES, FIELDS, analyze, declarations, expected, finite_domain, finite_ids, forms, identifier, make_case,
    measured_id, negatives, qualified, requests, required, source,
)
from sqlite_engine import Database, ObserverAuthorizationError
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-drop-triggers-v4.json"
CONTRACT = "7784b1c111552cf523ef02e1d2f9bd6e173fff5506e97d04aa9380e19672a591"
FIXTURE = ""
DROP_ACTIONS = frozenset((14, 16))
CATALOGS = frozenset(("sqlite_master", "sqlite_temp_master"))
INVALID = "SqlSemantics\\Platform\\Sqlite\\Model\\Diagnostic\\InvalidConstruction"
MISSING_GUARD = "An absent DROP TRIGGER target requires IF EXISTS."
CROSS_SCHEMA = "A stored trigger must watch a relation in its own schema."


class NativeDatabase(Database):
    """Allows a single checked drop and its native catalog reads."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library):
        super().__init__(library)
        self.target = None
        self.events = []

    def authorize(self, _user, action, first, second, schema, origin):
        if self.target is not None and origin is None:
            scope, trigger, watched = self.target
            if schema == scope:
                temporary = scope == b"temp"
                catalog = b"sqlite_temp_master" if temporary else b"sqlite_master"
                if action == (14 if temporary else 16) and first == trigger and second == watched:
                    self.events.append([action, first.decode(), second.decode(), schema.decode()])
                    return 0
                if self.events and first == catalog:
                    if (action == 9 and second is None) or (action == 20 and second in (b"name", b"type")):
                        return 0
        if self.denied is None:
            self.denied = (action, [value.decode(errors="replace") if value is not None else None
                                    for value in (first, second, schema, origin)])
        return 1

    def drop(self, sql, item):
        self.target = (item["schema"].encode(), item["name"].encode(), item["watched"][1].encode()) if (
            item["targetRole"] == "installed"
        ) else None
        self.events = []
        try:
            plan = self.observe("EXPLAIN " + sql, {}, preserve_order=True)
            slots = [int(row[2][1]) for row in plan["rows"] if row[1] == ["text", "Transaction"]]
            self.events = []
            result = self.observe(sql, {}, preserve_order=True, capture_evaluation_error=True)
            return result, slots, self.events.copy()
        except ObserverAuthorizationError as error:
            if error.action in DROP_ACTIONS or (error.action == 9 and error.arguments[0] in CATALOGS):
                raise Failure("sqlite-drop-trigger-target-mismatch", [error.action, *error.arguments]) from error
            raise
        finally:
            self.target = None


def setup(db, item):
    for schema in item["schemas"][2:]:
        db.execute(f"ATTACH ':memory:' AS {quoted(schema)}")
    db.execute("; ".join(declarations(item)))


def rows(db, sql):
    result = db._observe(sql, {}, preserve_order=True)
    require(result["readOnly"] and result["changes"] == 0, "sqlite-drop-trigger-observer-read", sql)
    return result["rows"]


def snapshot(db, item):
    return {schema: rows(db, f"SELECT name,tbl_name,rootpage,sql FROM {quoted(schema)}.sqlite_schema "
                             "WHERE type='trigger' ORDER BY name") for schema in item["schemas"]}


def observe(template, item, sql):
    db = NativeDatabase(template.library)
    try:
        setup(db, item)
        before = snapshot(db, item)
        if item["rollback"]:
            db.execute("BEGIN")
        try:
            statement, verification, authorization = db.drop(sql, item)
        except RuntimeError as failure:
            missing = item["name"] if item["schema"] is None else item["schema"] + "." + item["name"]
            require(item["targetRole"] == "absent" and str(failure) == f"SQLite 1: no such trigger: {missing}",
                    "sqlite-drop-trigger-unexpected-error", str(failure))
            statement, verification, authorization = {"preparationError": str(failure)}, [], []
        after = snapshot(db, item)
        active = not bool(db.get_autocommit(db.db))
        if active:
            db.execute("ROLLBACK")
        settled = snapshot(db, item)
        for slot, schema in enumerate(item["schemas"]):
            watched = qualified(schema, item["watchedName"])
            db.execute(f"INSERT INTO {watched} VALUES ({item['value'] + slot},'witness')")
        audit = {schema: rows(db, f"SELECT input,marker FROM {qualified(schema, 'audit')} ORDER BY rowid")
                 for schema in item["schemas"]}
        return {"before": before, "statement": statement, "verification": verification, "authorization": authorization,
                "after": after, "settled": settled, "transactionDuringDrop": active,
                "transactionAfter": not bool(db.get_autocommit(db.db)), "audit": audit}
    finally:
        db.check(db.close(db.db))


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and all(reply.get(field) == item[field] for field in FIELDS),
            "sqlite-drop-trigger-structure", {"expected": item, "reply": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    observed = {"observation": observe(db, item, reply["sql"])}
    if original is not None:
        observed["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **observed)
    for observation in observed.values():
        compare(expected(item), observation)
    if ledger is not None:
        ledger.record(key, "compared")
    return observed["observation"], original


def controls(db):
    item = make_case("temp-shadow", None, "tracker", False, False)
    original = observe(db, item, source(item))
    prediction = expected(item)
    compare(prediction, original)
    changed_rows, changed_plan = deepcopy(original), deepcopy(original)
    changed_rows["audit"]["temp"] = [[["integer", "18"], ["integer", "1"]]]
    changed_plan["verification"] = [0]
    rejected = {}
    for name, witness in (("audit-row", changed_rows), ("verification", changed_plan)):
        try:
            compare(prediction, witness)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "sqlite-drop-trigger-comparator-signal", failure.signal)
            rejected[name] = {"signal": failure.signal, "witness": witness}
        else:
            raise Failure("sqlite-drop-trigger-comparator-accepted", name)
    return {"healthy": original, "rejected": rejected}


def native_refusals(db, domain):
    checked = []
    observed = set()
    required_ids = finite_ids(domain)[1]
    for coordinate, item in zip(product(*(domain[axis] for axis in AXES)), finite_domain(domain)):
        if item["ifExists"] or item["targetRole"] == "installed":
            continue
        identity = identifier(coordinate, domain)
        require(identity not in observed, "sqlite-drop-trigger-repeated-refusal", identity)
        native = observe(db, item, source(item))
        requested_schema = item["requestedSchema"]
        selector = ((requested_schema + ".") if requested_schema is not None else "") + item["requestedName"]
        compare({"preparationError": "SQLite 1: no such trigger: " + selector}, native["statement"])
        require(native["before"] == native["after"] == native["settled"] and native["authorization"] == [],
                "sqlite-drop-trigger-refusal-mutates-catalog", native)
        observed.add(identity)
        checked.append((analyze(item), "rejected", "DROP TRIGGER requires a resolved trigger or a known guarded absence",
                        {"finiteIdentity": identity, "nativeRefusal": native}))
    require(observed == required_ids, "sqlite-drop-trigger-incomplete-native-refusals", sorted(required_ids - observed))
    return checked, observed


def execute(args, specification, db, worker_factory=Worker):
    refused, identities = native_refusals(db, specification["finite"])
    schedule = ScalarSchedule(requests, negatives, measured_id, lambda domain: finite_ids(domain)[0],
                              forms, required, lambda: controls(db))
    result = execute_schedule(args, specification, db, worker_factory, schedule, probe,
                              measured_input=lambda reply, observed: (reply, *observed),
                              paired_finite=lambda measured: measured[:2], finite_refusals=refused)
    result["negativeFiniteIds"] = sorted(identities)
    return result


def fault_probe(item, reply, db):
    if item.get("probe") != "drop-trigger-construction":
        return probe(item, reply, db)
    if reply["status"] == "constructed":
        require(all(reply.get(field) == item[field] for field in FIELDS) and "sql" not in reply,
                "sqlite-drop-trigger-unexpected-value", reply)
        raise Failure("sqlite-drop-trigger-invalid-construction", reply)
    require(reply["status"] == "rejected" and reply["exception"] == INVALID and item["refusal"] in reply["message"],
            "sqlite-drop-trigger-fault-wrong-rejection", reply)


def check_faults(args, db, runner=check_render_faults):
    root = "sql-semantics-sqlite/src/Ir/Schema/Trigger/"
    found = make_case("temp-shadow", "main", "tracker", False, False)
    absent = make_case("empty", None, "tracker", True, True)
    qualified_absence = make_case("empty", "archive", "tracker", True, True)
    no_guard = {**absent, "ifExists": False, "probe": "drop-trigger-construction", "refusal": MISSING_GUARD}
    wrong_watch = {**found, "watched": ["temp", "watched"], "probe": "drop-trigger-construction", "refusal": CROSS_SCHEMA}
    builtin_unknown = {**absent, "schema": "MAIN", "verification": "unknown-schema", "probe": "drop-trigger-construction",
                       "refusal": "The main and temp trigger schemas are always known."}
    return runner(args, db, [
        RenderFault("drop-trigger-from-shadow-schema", root + "Removal/TriggerReference.php", "$this->schema->quoted()", "'temp'",
                    found, "sqlite-drop-trigger-target-mismatch"),
        RenderFault("drop-adjacent-trigger", root + "Removal/TriggerReference.php", "$this->name->quoted()", "'sentinel'",
                    found, "sqlite-drop-trigger-target-mismatch"),
        RenderFault("omit-drop-trigger-guard", root + "DropTrigger.php", "($this->ifExists ? 'IF EXISTS ' : '')", "''",
                    absent, "meaning-mismatch"),
        RenderFault("restrict-all-trigger-absence", root + "Removal/MissingTrigger.php", "$this->schema === null ? ''",
                    "$this->schema === null ? 'main.'", absent, "meaning-mismatch"),
        RenderFault("forget-qualified-trigger-absence", root + "Removal/MissingTrigger.php",
                    "($this->schema === null ? '' : $this->schema->quoted() . '.') . ", "", qualified_absence, "meaning-mismatch"),
        RenderFault("allow-unguarded-trigger-absence", root + "DropTrigger.php", "$ifExists || $target instanceof TriggerReference",
                    "true", no_guard, "sqlite-drop-trigger-invalid-construction"),
        RenderFault("allow-cross-schema-stored-trigger", root + "Removal/TriggerReference.php",
                    "$schema->key() === 'temp' || $schema->key() === $watchedSchema->key()",
                    "true", wrong_watch, "sqlite-drop-trigger-invalid-construction"),
        RenderFault("allow-unknown-built-in-trigger-schema", root + "Removal/MissingTrigger.php",
                    "$verification !== TriggerVerification::UnknownSchema || ($schema !== null && $schema->key() !== 'main' && $schema->key() !== 'temp')",
                    "true", builtin_unknown, "sqlite-drop-trigger-invalid-construction"),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("drop-triggers", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, NativeDatabase)
