"""Compare resolved trigger definitions with native catalog and ordered row changes."""

from copy import deepcopy
import hashlib
import os
from pathlib import Path
from tempfile import TemporaryDirectory

from run import Failure, Worker, compare, require
from run_sqlite_create_table import quoted
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import Database
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule
from sqlite_trigger_cases import (IMAGE_ERROR, STRUCTURE, analysis, case, expected, field, finite_id, finite_ids,
                                  forms, names, negatives, requests, required, source, with_field)
from sqlite_trigger_fixture import SCHEMA_TABLES, drive, fixture, reopen, snapshot

CONTRACT_NAME = "sqlite-audit-triggers-v2.json"
CONTRACT = "fa4707626bf562f55ad952a1c4b3d9b40df06b109e24ce2fe3eb05dc6c7a2bd1"
FIXTURE = ""


class TriggerDatabase(Database):
    """Allow only declaration and execution against the named disposable fixture."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library, filename=b":memory:"):
        super().__init__(library, filename)
        self.policy = "install"
        self.installations = []
        self.observed = []
        self.trigger = None
        self.schemata = frozenset()
        self.relations = frozenset()
        self.origins = frozenset()

    def configure(self, item):
        watched, audit = item["watched"][1], item["audit"]
        self.trigger = item["name"].encode()
        self.schemata = frozenset(schema.encode() for schema in names(item))
        self.relations = frozenset(value.encode() for value in (watched, audit, "internal_data", "audit_decoy"))
        self.origins = frozenset((None, self.trigger, watched.encode()))

    def authorize(self, _ctx, action, first, second, schema, origin):
        if self.policy == "install" and origin is None and schema in self.schemata:
            if action in (5, 7) and first == self.trigger and second in self.relations:
                self.installations.append([action, first.decode(), second.decode(), schema.decode()])
                return 0
            if action in (18, 20, 23) and first in SCHEMA_TABLES:
                if action == 18 and self.installations:
                    self.observed.append(schema.decode())
                return 0
        if self.policy == "fire" and origin in self.origins:
            if action == 21 and first is None and second is None and schema is None:
                return 0
            if action in (9, 18, 20, 23) and first in self.relations and schema in self.schemata:
                return 0
        if self.denied is None:
            self.denied = (action, [None if field is None else field.decode(errors="replace")
                                    for field in (first, second, schema, origin)])
        return 1


def observe(template, item, sql):
    with TemporaryDirectory(prefix="semantics-ir-sqlite-trigger-") as directory:
        previous = Path.cwd()
        os.chdir(directory)
        db = TriggerDatabase(template.library, b"primary.sqlite")
        try:
            fixture(db, item)
            db.configure(item)
            baseline = snapshot(db, item)
            try:
                definition = db.observe(sql, {}, preserve_order=True, capture_evaluation_error=True)
            except RuntimeError as error:
                require(item["collision"] and str(error) == f"SQLite 1: trigger {quoted(item['name'])} already exists",
                        "sqlite-trigger-unexpected-install-error", str(error))
                return {"definition": {"preparationError": str(error)}, "input": sql}
            catalog = snapshot(db, item)
            owners = [schema for schema, state in catalog.items() if state["declarations"]]
            require(len(owners) == 1, "sqlite-trigger-installation-owner", owners)
            if db.installations:
                require(len(db.installations) == 1 and db.observed, "sqlite-trigger-native-installation-shape",
                        [db.installations, db.observed])
                watched = db.observed[0]
            else:
                require(item["collision"], "sqlite-trigger-missing-installation")
                watched = item["watched"][0]
            events = drive(db, item, definition_present=not item["collision"])
            return {
                "input": sql, "before": baseline, "owner": owners[0], "watchedScope": watched,
                "authorization": db.installations, "definition": definition, "events": events,
                "after": snapshot(db, item), "reopened": reopen(db, item, TriggerDatabase) if item["reopen"] else None,
                "nativeAuthorizationScopes": db.observed,
            }
        finally:
            db.check(db.close(db.db))
            os.chdir(previous)


def semantic(observation):
    return {key: value for key, value in observation.items() if key not in ("input", "nativeAuthorizationScopes")}


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and reply.get("operation") == "create-trigger"
            and all(reply.get(role) == item[role] for role in STRUCTURE), "sqlite-trigger-structure", reply)
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    findings = {"observed": observe(db, item, reply["sql"])}
    if original is not None:
        findings["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **findings)
    model = expected(item)
    for native in findings.values():
        compare(model, semantic(native))
    if ledger is not None:
        ledger.record(key, "compared")
    return findings.get("original", findings["observed"])


def controls(db):
    item = case("archive-table-after", "update", "payload", "is-not-null", 3)
    healthy = observe(db, item, source(item))
    model = expected(item)
    compare(model, semantic(healthy))
    scope, effects, data = (deepcopy(healthy) for _ in range(3))
    scope["watchedScope"] = "main"
    writing = next(row for row in effects["events"] if len(row["hook"]) > 1)
    writing["hook"][0], writing["hook"][1] = writing["hook"][1], writing["hook"][0]
    data["after"]["archive"]["audit"].pop()
    rejected = {}
    for label, altered in (("misbound-watched-database", scope), ("lost-effect-order", effects), ("ignored-audit-row", data)):
        try:
            compare(model, semantic(altered))
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "sqlite-trigger-comparator-signal", failure.signal)
            rejected[label] = {"witness": altered, "signal": failure.signal}
        else:
            raise Failure("sqlite-trigger-comparator-accepted", label)
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
                "sqlite-trigger-invalid-accepted", reply)
    else:
        probe(item, reply, db)


def check_faults(args, db, runner=check_render_faults):
    base = "sql-semantics-sqlite/src/Ir/Schema/Trigger/"
    checks, render = base + "TriggerChecks.php", base + "TriggerSql.php"
    update = case("archive-table-after", "update", "payload")
    wrong_image = {**with_field(case(), 0, field("OLD", 1)), "refusal": IMAGE_ERROR}
    wrong_scope = case("named-temp-on-main")
    no_matching_selector = case(event="update", selector="absent")
    filtered = case(condition="is-null")
    collision = case(guard=True, collision=True)
    return runner(args, db, [
        RenderFault("permit-an-unavailable-event-image", checks, "$available && isset($watched->columns[$field->column])",
                    "isset($watched->columns[$field->column])", wrong_image, "sqlite-trigger-invalid-accepted"),
        RenderFault("swap-old-and-new", render, "$field->image->value . '.'",
                    "($field->image === RowImage::Old ? RowImage::New : RowImage::Old)->value . '.'", update, "meaning-mismatch"),
        RenderFault("install-trigger-in-main-instead-of-temp", render, "$trigger->schema->quoted() . '.'",
                    "'main.'", wrong_scope, "meaning-mismatch"),
        RenderFault("write-to-the-decoy-table", render, "$target->table->quoted()", "'audit_decoy'", update, "meaning-mismatch"),
        RenderFault("erase-the-update-of-filter", render, "if ($names !== []) {", "if (false) {", no_matching_selector, "meaning-mismatch"),
        RenderFault("reverse-the-order-of-trigger-actions", render, "foreach ($trigger->program as $step)",
                    "foreach (array_reverse($trigger->program) as $step)", update, "meaning-mismatch"),
        RenderFault("erase-the-native-when-test", render, "$when = $condition === null ? ''",
                    "$when = true ? ''", filtered, "meaning-mismatch"),
        RenderFault("drop-a-checked-destination-field", render, "foreach ($step->fields as $field)",
                    "foreach (array_slice($step->fields, 0, -1) as $field)", update, "meaning-mismatch"),
        RenderFault("erase-the-trigger-existence-guard", render, "($trigger->ifNotExists ? 'IF NOT EXISTS ' : '')",
                    "''", collision, "meaning-mismatch"),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("audit-triggers", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, TriggerDatabase)
