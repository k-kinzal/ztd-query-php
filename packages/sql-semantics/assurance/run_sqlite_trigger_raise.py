"""Compare checked SQLite trigger interruption with independent state transitions."""

from copy import deepcopy
import hashlib

from run import Failure, Worker, compare, require
from run_sqlite_trigger_insert_batches import NativeDatabase as BatchDatabase, read
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import Database
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule
from sqlite_trigger_raise_cases import (
    IMAGE_ERROR, POLICY_ERROR, SCHEMAS, STRUCTURE, declarations, expected, finite_ids, forms, literal,
    make_case, measured_id, negatives, qualified, requests, required, source, with_raise,
)

CONTRACT_NAME = "sqlite-trigger-raise-v1.json"
CONTRACT = "24d974e9718d5d92eed53e53d76393ec2dac2e99f550924d131fa8adf40c47bb"
FIXTURE = ""
INVALID = "SqlSemantics\\Platform\\Sqlite\\Model\\Diagnostic\\InvalidConstruction"


class NativeDatabase(BatchDatabase):
    """Checks the native declaration and writes against the declared audit fixture."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def configure(self, item):
        super().configure({**item, "ledger": item["audit"]})
        self.event = item["event"]
        self.watched_scope = item["watched"][0].encode()

    def authorize(self, user, action, first, second, schema, origin):
        if not self.install and origin is None and first == self.watched and schema == self.watched_scope:
            if self.event == "UPDATE" and action == 23 and second in (b"guard", b"message"):
                return 0
            if self.event == "DELETE" and action == 9 and second is None:
                return 0
        return super().authorize(user, action, first, second, schema, origin)



def setup(db, item):
    db.execute("ATTACH DATABASE ':memory:' AS archive")
    db.execute(";".join(declarations(item)))
    for index, scope in enumerate(SCHEMAS):
        audit = qualified(scope, item["audit"])
        db.execute(f"INSERT INTO {audit} VALUES ('fixture', {index})")
        if item["prior"]:
            row = ", ".join(literal(value) for value in item["prior"][0])
            db.execute(f'INSERT INTO {qualified(scope, item["watched"][1])} VALUES ({row})')


def snapshot(db, item):
    return {schema: {
        "watched": read(db, f'SELECT * FROM {qualified(schema, item["watched"][1])} ORDER BY rowid'),
        "audit": read(db, f'SELECT * FROM {qualified(schema, item["audit"])} ORDER BY rowid'),
    } for schema in SCHEMAS}


def drive(db, item):
    target = qualified(*item["watched"][:2])
    event = item["event"]
    policy = " OR IGNORE" if item["outer"] == "IGNORE" else ""
    if event == "INSERT":
        sql = f"INSERT{policy} INTO {target} VALUES (?, ?, ?)"
        values = item["final"][0]
    elif event == "UPDATE":
        sql = f"UPDATE{policy} {target} SET guard=?, message=? WHERE id=?"
        row = item["final"][0]
        values = (row[1], row[2], row[0])
    else:
        sql = f"DELETE FROM {target} WHERE id=?"
        values = (item["prior"][0][0],)
    bindings = {str(key): value for key, value in enumerate(values, 1)}
    return db.observe(sql, bindings, preserve_order=True, capture_evaluation_error=True)


def observe(template, item, sql):
    db = NativeDatabase(template.library)
    try:
        setup(db, item)
        db.configure(item)
        definition = db.observe(sql, {}, preserve_order=True, capture_evaluation_error=True)
        catalog = {schema: read(db, f"SELECT name,tbl_name FROM {qualified(schema, 'sqlite_schema')} "
                                    f"WHERE type='trigger' AND name={literal(item['name'])}")["rows"] for schema in SCHEMAS}
        if item["transaction"]:
            db.execute("BEGIN")
            db.execute(f'INSERT INTO {qualified(item["schema"], item["audit"])} VALUES (\'transaction\',99)')
        before = snapshot(db, item)
        active_before = not bool(db.get_autocommit(db.db))
        bindings = {str(key): value for key, value in enumerate(item["bound"], 1)}
        bound = db._observe("SELECT ?, ?, ?", bindings, preserve_order=True)
        db.install = False
        activation = drive(db, item)
        after = snapshot(db, item)
        active_after = not bool(db.get_autocommit(db.db))
        if active_after:
            db.execute("ROLLBACK")
        return {"definition": definition, "before": before, "catalog": catalog, "authorization": db.authorization,
                "activation": activation, "after": after, "bound": bound["rows"][0], "transactionBefore": active_before,
                "transactionAfter": active_after, "settled": snapshot(db, item)}
    finally:
        db.check(db.close(db.db))


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and all(reply.get(field) == item[field] for field in STRUCTURE),
            "sqlite-trigger-raise-structure", {"expected": item, "reply": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    observed = {"observation": observe(db, item, reply["sql"])}
    if original is not None:
        observed["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **observed)
    for receipt in observed.values():
        compare(expected(item), receipt)
    if ledger is not None:
        ledger.record(key, "compared")
    return observed["observation"], item["outer"]


def controls(db):
    item = make_case(transaction=True)
    baseline = observe(db, item, source(item))
    prediction = expected(item)
    compare(prediction, baseline)
    wrong_text, dropped_change, ended = deepcopy(baseline), deepcopy(baseline), deepcopy(baseline)
    wrong_text["activation"]["evaluationError"]["message"] = ""
    dropped_change["after"]["main"]["audit"]["rows"].pop()
    ended["transactionAfter"] = False
    detections = {}
    for name, control in (("wrong-message", wrong_text), ("missing-prefix-effect", dropped_change), ("untimely-rollback", ended)):
        try:
            compare(prediction, control)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "sqlite-trigger-raise-wrong-control-signal", failure.signal)
            detections[name] = {"signal": failure.signal, "witness": control}
        else:
            raise Failure("sqlite-trigger-raise-comparator-accepted", name)
    return {"healthy": baseline, "rejected": detections}


def execute(args, specification, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, measured_id, finite_ids, forms, required, lambda: controls(db))
    return execute_schedule(args, specification, db, worker_factory, schedule, probe,
                            measured_input=lambda reply, outcome: (reply, *outcome),
                            paired_finite=lambda version: version)


def fault_probe(item, reply, db):
    if item.get("probe") != "trigger-program-construction":
        return probe(item, reply, db)
    if reply["status"] == "constructed":
        require(all(reply.get(field) == item[field] for field in STRUCTURE) and "sql" not in reply,
                "sqlite-trigger-raise-wrong-construction", reply)
        raise Failure("sqlite-trigger-raise-invalid-accepted", reply)
    require(reply["status"] == "rejected" and reply["exception"] == INVALID and item["refusal"] in reply["message"],
            "sqlite-trigger-raise-wrong-refusal", reply)


def check_faults(args, db, runner=check_render_faults):
    root = "sql-semantics-sqlite/src/Ir/Schema/Trigger/"
    render = root + "TriggerSql.php"
    skipped = make_case(predicate="not-selected")
    selected = make_case()
    abort = make_case(action=("ABORT", "literal"))
    rollback = make_case(action=("ROLLBACK", "literal"), transaction=True)
    ignore = make_case(action=("IGNORE", "absent"))
    illicit = with_raise(selected, policy="IGNORE")
    illicit.update(probe="trigger-program-construction", refusal=POLICY_ERROR)
    unavailable = with_raise(selected, message=["field", "OLD", 2])
    unavailable.update(probe="trigger-program-construction", refusal=IMAGE_ERROR)
    filter_line = r"""$filter = $condition === null ? '' : ' WHERE ' . $this->field($condition->field, $watched)
            . ($condition->isNull ? ' IS NULL' : ' IS NOT NULL');"""
    control = "'SELECT RAISE(' . $step->policy->value . $message . ')' . $filter"
    meaning = "meaning-mismatch"
    invalid = "sqlite-trigger-raise-invalid-accepted"
    return runner(args, db, [
        RenderFault("ignore-native-raise-condition", render, "$filter = $condition === null ?", "$filter = true ?", skipped, meaning),
        RenderFault("invert-trigger-raise-null-condition", render, filter_line,
                    filter_line.replace("$condition->isNull", "!$condition->isNull"), selected, meaning),
        RenderFault("replace-trigger-abort-with-fail", render, "$step->policy->value . $message",
                    "'FAIL' . $message", abort, meaning),
        RenderFault("replace-trigger-rollback-with-abort", render, "$step->policy->value . $message",
                    "'ABORT' . $message", rollback, meaning),
        RenderFault("convert-trigger-ignore-to-error", render, control,
                    "\"SELECT RAISE(FAIL, 'changed')\" . $filter", ignore, meaning),
        RenderFault("emit-fixed-trigger-error-text", render, "$this->value($step->message, $watched)",
                    "\"'fixed'\"", selected, meaning),
        RenderFault("read-wrong-trigger-error-field", render, "$this->value($step->message, $watched)",
                    "$this->value(new TriggerField($step->message->image, 1), $watched)", selected, meaning),
        RenderFault("execute-later-action-before-raise", render, "foreach ($trigger->program as $step)",
                    "foreach (array_reverse($trigger->program) as $step)", selected, meaning),
        RenderFault("allow-message-for-trigger-ignore", root + "Program/TriggerRaise.php",
                    "($policy === TriggerRaisePolicy::Ignore) === ($message === null)", "true", illicit, invalid),
        RenderFault("skip-trigger-raise-message-image-boundary", root + "TriggerChecks.php",
                    "$this->field($step->message, $event, $watched);", "", unavailable, invalid),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("trigger-raise", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, NativeDatabase)
