"""Compare native trigger tuple grouping, ordered writes and caller transaction effects."""

from copy import deepcopy
import hashlib

from run import Failure, Worker, compare, require
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import Database
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule
from sqlite_trigger_batch_cases import (
    HEADING_ERROR, IMAGE_ERROR, SCHEMAS, STRUCTURE, declarations, expected, finite_ids, forms, literal,
    make_case, measured_id, negatives, qualified, requests, required, source,
)

CONTRACT_NAME = "sqlite-trigger-insert-batches-v1.json"
CONTRACT = "37bb1ec003e68968ca96f1d387e36f5092d94725c36d05277f129639c6b4dde4"
FIXTURE = ""
INVALID = "SqlSemantics\\Platform\\Sqlite\\Model\\Diagnostic\\InvalidConstruction"


class NativeDatabase(Database):
    """Accepts only the scoped candidate declaration and fixture effects."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library):
        super().__init__(library)
        self.install = True
        self.authorization = []

    def configure(self, item):
        self.name = item["name"].encode()
        self.watched = item["watched"][1].encode()
        self.ledger = item["ledger"].encode()
        self.schemata = frozenset(schema.encode() for schema in SCHEMAS)

    def authorize(self, _user, action, first, second, schema, origin):
        if action == 21 and first is None and second is None and schema is None and (
            (self.install and origin is None) or (not self.install and origin in (None, self.name))
        ):
            return 0
        if self.install and origin is None and schema in self.schemata:
            if action in (5, 7) and first == self.name and second == self.watched:
                self.authorization.append([action, first.decode(), second.decode(), schema.decode()])
                return 0
            if action in (18, 20, 23) and first in (b"sqlite_master", b"sqlite_temp_master"):
                return 0
        if not self.install and origin in (None, self.name, b"tally") and schema in self.schemata:
            if action in (18, 20) and first in (self.watched, self.ledger, b"effects"):
                return 0
            if action == 9 and first == self.ledger:
                return 0
        if self.denied is None:
            self.denied = (action, [None if value is None else value.decode(errors="replace")
                                    for value in (first, second, schema, origin)])
        return 1


def setup(db, item):
    db.execute("ATTACH DATABASE ':memory:' AS archive")
    db.execute(";".join(declarations(item)))
    for index, schema in enumerate(SCHEMAS):
        ledger = qualified(schema, item["ledger"])
        db.execute(f"CREATE TABLE {qualified(schema, 'effects')}(seq INTEGER PRIMARY KEY, k, stamp);"
                   f"INSERT INTO {ledger} VALUES (0,{index}),(2,'decoy');")
        catalog = "" if schema == "temp" else schema + "."
        temporary = "TEMP " if schema == "temp" else ""
        db.execute(f"CREATE {temporary}TRIGGER {catalog}tally AFTER INSERT ON {ledger} "
                   "BEGIN INSERT INTO effects (k,stamp) VALUES(NEW.k,NEW.payload); END")


def read(db, sql):
    result = db._observe(sql, {}, preserve_order=True)
    require(result["readOnly"] and result["changes"] == 0, "sqlite-trigger-batch-observer", sql)
    return {"names": result["names"], "rows": result["rows"]}


def snapshot(db, item):
    relations = {
        "watched": item["watched"][1], "ledger": item["ledger"], "effects": "effects",
    }
    return {schema: {name: read(db, f"SELECT * FROM {qualified(schema, actual)} ORDER BY rowid")
                     for name, actual in relations.items()} for schema in SCHEMAS}


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
            ledger = qualified(item["schema"], item["ledger"])
            db.execute(f"INSERT INTO {ledger} VALUES (99,'transaction-marker')")
        before = snapshot(db, item)
        active_before = not bool(db.get_autocommit(db.db))
        db.install = False
        operation = "INSERT" if item["outer"] == "inherit" else "INSERT OR IGNORE"
        watched = qualified(*item["watched"][:2])
        activation = db.observe(f"{operation} INTO {watched} VALUES (101,{literal(item['activationValue'])})", {},
                                preserve_order=True, capture_evaluation_error=True)
        after = snapshot(db, item)
        active_after = not bool(db.get_autocommit(db.db))
        if active_after:
            db.execute("ROLLBACK")
        return {"definition": definition, "before": before, "catalog": catalog, "authorization": db.authorization,
                "activation": activation, "after": after, "transactionBefore": active_before, "transactionAfter": active_after,
                "settled": snapshot(db, item)}
    finally:
        db.check(db.close(db.db))


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and all(reply.get(field) == item[field] for field in STRUCTURE),
            "sqlite-trigger-batch-structure", {"expected": item, "reply": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    observed = {"observation": observe(db, item, reply["sql"])}
    if original is not None:
        observed["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **observed)
    for result in observed.values():
        compare(expected(item), result)
    if ledger is not None:
        ledger.record(key, "compared")
    return observed["observation"], item["outer"]


def controls(db):
    batch = make_case("main", "batch", "FAIL", "middle", True)
    separate = make_case("main", "separate", "FAIL", "middle", True)
    first, second = (observe(db, item, source(item)) for item in (batch, separate))
    prediction = expected(batch)
    compare(prediction, first)
    compare(expected(separate), second)
    require(first["after"] == second["after"] and first["activation"]["changes"] != second["activation"]["changes"],
            "sqlite-trigger-batch-missing-grouping-witness", [first, second])
    reversed_effects = deepcopy(first)
    reversed_effects["after"]["main"]["effects"]["rows"].reverse()
    defeated = {}
    for name, candidate in (("collapsed-grouping", second), ("changed-sequence", reversed_effects)):
        try:
            compare(prediction, candidate)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "sqlite-trigger-batch-wrong-control", failure.signal)
            defeated[name] = {"signal": failure.signal, "witness": candidate}
        else:
            raise Failure("sqlite-trigger-batch-comparator-accepted", name)
    return {"healthy": first, "rejected": defeated}


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
                "sqlite-trigger-batch-wrong-construction", reply)
        raise Failure("sqlite-trigger-batch-invalid-construction", reply)
    require(reply["status"] == "rejected" and reply["exception"] == INVALID and item["refusal"] in reply["message"],
            "sqlite-trigger-batch-wrong-refusal", reply)


def check_faults(args, db, runner=check_render_faults):
    root = "sql-semantics-sqlite/src/Ir/Schema/Trigger/"
    render = root + "TriggerSql.php"
    batch = make_case("main", "batch", "FAIL", "middle")
    separate = make_case("main", "separate", "FAIL", "middle")
    success = make_case("main", "batch", "IGNORE", "none")
    ignored = make_case("main", "batch", "IGNORE", "middle")
    watched = make_case("archive", "batch", "IGNORE", "none")
    changed = deepcopy(batch)
    changed["program"][0]["rows"][1].reverse()
    changed.update(probe="trigger-program-construction", refusal=HEADING_ERROR)
    missing = deepcopy(batch)
    missing["program"][0]["rows"][-1][1][1] = ["field", "OLD", 1]
    missing.update(probe="trigger-program-construction", refusal=IMAGE_ERROR)
    meaning = "meaning-mismatch"
    invalid = "sqlite-trigger-batch-invalid-construction"
    return runner(args, db, [
        RenderFault("split-native-trigger-batch", render, "implode(', ', $rows)",
                    """implode('; INSERT' . $policy . ' INTO ' . $target->table->quoted() . ' (' . implode(', ', $columns) . ') VALUES ', $rows)""",
                    batch, meaning),
        RenderFault("merge-independent-trigger-insertions", render, "implode(' ', $steps)",
                    r"""preg_replace('~; INSERT OR FAIL INTO "ledger" \("k", "payload"\) VALUES ~', ', ', implode(' ', $steps))""",
                    separate, meaning),
        RenderFault("reverse-trigger-rows", render, "foreach ($step->rows as $index => $tuple)",
                    "foreach (array_reverse($step->rows) as $index => $tuple)", success, meaning),
        RenderFault("omit-last-trigger-tuple", render, "foreach ($step->rows as $index => $tuple)",
                    "foreach (array_slice($step->rows, 0, -1) as $index => $tuple)", success, meaning),
        RenderFault("reuse-first-trigger-tuple-operand", render, "$values[] = $this->value($field->value, $watched);",
                    "$values[] = $this->value($index > 0 && $field->column === 1 ? $step->rows[0]->fields[1]->value : $field->value, $watched);",
                    success, meaning),
        RenderFault("admit-changing-trigger-batch-heading", root + "TriggerInsert.php", "$tuple->columns() === $heading",
                    "true", changed, invalid),
        RenderFault("skip-last-trigger-image-check", root + "TriggerChecks.php", "foreach ($step->rows as $tuple)",
                    "foreach (array_slice($step->rows, 0, 1) as $tuple)", missing, invalid),
        RenderFault("omit-native-trigger-batch-policy", render,
                    "$step->conflict === InsertConflict::Inherit ? '' : ' OR ' . strtoupper($step->conflict->value)",
                    "''", ignored, meaning),
        RenderFault("trigger-batch-runs-in-decoy", render, "$trigger->schema->quoted() . '.'",
                    "'temp.'", watched, meaning),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("trigger-insert-batches", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, NativeDatabase)
