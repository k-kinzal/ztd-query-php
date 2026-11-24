"""Check trigger mutations on native SQLite with an independent row transition model."""

from copy import deepcopy
import hashlib

from run import Failure, Worker, compare, require
from sqlite_add_column_cases import statement
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import Database
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule
from sqlite_trigger_mutation_cases import (
    IMAGE_ERROR, LOCAL_ERROR, SCHEMAS, STRUCTURE, authored, declared, finite_ids, forms, literal,
    make_case, measured_id, negatives, original_rows, qualified, requests, required, simulate,
    source_sql, typed_rows, watched_rows,
)

CONTRACT_NAME = "sqlite-trigger-mutations-v2.json"
CONTRACT = "38eebbe8868778d844c8d18bd74a5faa45c36442f34c8e7ef82217d8b5061b56"
FIXTURE = ""
INVALID = "SqlSemantics\\Platform\\Sqlite\\Model\\Diagnostic\\InvalidConstruction"


class NativeDatabase(Database):
    """Permits definition and activation in the named disposable fixture."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library):
        super().__init__(library)
        self.phase = "install"
        self.installed = []

    def configure(self, item):
        self.name = item["name"].encode()
        self.relations = frozenset(value.encode() for value in (item["watched"][1], item["ledger"], "witness"))
        self.schemata = frozenset(schema.encode() for schema in SCHEMAS)
        self.activations = frozenset((None, self.name, *(
            f"ledger_{action}_{slot}".encode() for action in ("update", "delete") for slot in range(len(SCHEMAS))
        )))

    def authorize(self, _user, action, first, second, schema, origin):
        if self.phase == "install" and origin is None and schema in self.schemata:
            if action in (5, 7) and first == self.name and second in self.relations:
                self.installed.append([action, first.decode(), second.decode(), schema.decode()])
                return 0
            if action in (18, 20, 23) and first in (b"sqlite_master", b"sqlite_temp_master"):
                return 0
        if self.phase == "activate" and origin in self.activations:
            if action == 21 and first is None and second is None and schema is None:
                return 0
            if action == 31 and second == b"count":
                return 0
            if action in (9, 18, 20, 23) and first in self.relations and schema in self.schemata:
                return 0
        if self.denied is None:
            self.denied = (action, [None if field is None else field.decode(errors="replace")
                                    for field in (first, second, schema, origin)])
        return 1


def storable(rows):
    return ", ".join("(" + ", ".join(literal(value) for value in row) + ")" for row in rows)


def setup(db, item):
    db.execute("ATTACH DATABASE ':memory:' AS archive")
    db.execute("; ".join(declared(item)))
    watched = item["watched"][1]
    prior, _ = watched_rows(item)
    for index, schema in enumerate(SCHEMAS):
        db.execute(f"INSERT INTO {qualified(schema, watched)} VALUES " + storable(prior))
        db.execute(f'INSERT INTO {qualified(schema, item["ledger"])} VALUES ' + storable(original_rows(item, schema)))
        db.execute(f"CREATE TABLE {qualified(schema, 'witness')}(action,affected,seen,payload)")
        scope = "main" if schema == "temp" else schema
        live = qualified(scope, watched)
        for action in ("update", "delete"):
            catalog = "" if schema == "temp" else schema + "."
            temporary = "TEMP " if schema == "temp" else ""
            db.execute(f"CREATE {temporary}TRIGGER {catalog}ledger_{action}_{index} AFTER {action.upper()} "
                       f'ON {qualified(schema, item["ledger"])} BEGIN INSERT INTO witness VALUES '
                       f"('{action}', OLD.id, (SELECT count(*) FROM {live}),"
                       f" (SELECT payload FROM {live} WHERE id={item['eventId']})); END")


def rows(db, sql):
    result = db._observe(sql, {}, preserve_order=True)
    require(result["readOnly"] and result["changes"] == 0, "sqlite-trigger-mutation-observer-not-readonly", sql)
    return {"names": result["names"], "rows": result["rows"]}


def snapshot(db, item):
    return {schema: {
        "watched": rows(db, f'SELECT * FROM {qualified(schema, item["watched"][1])} ORDER BY id'),
        "ledger": rows(db, f'SELECT * FROM {qualified(schema, item["ledger"])} ORDER BY id'),
        "witness": rows(db, f"SELECT * FROM {qualified(schema, 'witness')} ORDER BY rowid"),
    } for schema in SCHEMAS}


def drive(db, item):
    event = item["event"]
    target = qualified(*item["watched"][:2])
    _, after = watched_rows(item)
    if event == "INSERT":
        values = after[-1]
        sql = f"INSERT INTO {target} VALUES (?, ?, ?)"
    elif event == "UPDATE":
        changed = after[0]
        values = changed[1], changed[2], changed[0]
        sql = f"UPDATE {target} SET binding=?, payload=? WHERE id=?"
    else:
        values = (item["eventId"],)
        sql = f"DELETE FROM {target} WHERE id=?"
    bindings = {str(index): value for index, value in enumerate(values, 1)}
    return db.observe(sql, bindings, preserve_order=True, capture_evaluation_error=True)


def state_rows(item, ledger, witness, changed_watch):
    prior, after = watched_rows(item)
    return {schema: {
        "watched": {"names": list(item["watched"][2]), "rows": typed_rows(after if changed_watch and schema == item["watched"][0] else prior)},
        "ledger": {"names": list(item["program"][0]["table"][2]), "rows": typed_rows(ledger[schema])},
        "witness": {"names": ["action", "affected", "seen", "payload"], "rows": typed_rows(witness[schema])},
    } for schema in SCHEMAS}


def selected_payload(item, before, after):
    event = item["event"]
    selected = before if event == "DELETE" else after
    rows = selected[item["watched"][0]]["watched"]["rows"]
    value = next(record[2] for record in rows if record[0] == ["integer", str(item["eventId"])])
    return int(value[1]) if value[0] == "integer" else value[1]


def observe(template, item, sql):
    db = NativeDatabase(template.library)
    try:
        setup(db, item)
        db.configure(item)
        before = snapshot(db, item)
        installed = db.observe(sql, {}, preserve_order=True, capture_evaluation_error=True)
        trigger_catalog = {schema: rows(db, f"SELECT name,tbl_name FROM {qualified(schema, 'sqlite_schema')} "
                                               f"WHERE type='trigger' AND name={literal(item['name'])}")["rows"]
                           for schema in SCHEMAS}
        if item["rolledBack"]:
            db.execute("BEGIN")
        db.phase = "activate"
        activation = drive(db, item)
        after = snapshot(db, item)
        active = not bool(db.get_autocommit(db.db))
        if active:
            db.execute("ROLLBACK")
        if activation["evaluationError"] is None:
            payload = selected_payload(item, before, after)
        else:
            payload = None
        return {"before": before, "definition": installed, "catalog": trigger_catalog, "authorization": db.installed,
                "activation": activation, "driver": {"payload": payload, "rollback": active},
                "after": after, "settled": snapshot(db, item), "transactionAfter": not bool(db.get_autocommit(db.db))}
    finally:
        db.check(db.close(db.db))


def expected(item):
    ledger, receipts, changes = simulate(item)
    original = {schema: original_rows(item, schema) for schema in SCHEMAS}
    empty = {schema: [] for schema in SCHEMAS}
    before = state_rows(item, original, empty, False)
    after = state_rows(item, ledger, receipts, True)
    scope, name, relation = item["schema"], item["name"], item["watched"][1]
    operation = statement(changes=changes)
    operation["parameterCount"] = 1 if item["event"] == "DELETE" else 3
    return {"before": before, "definition": statement(),
            "catalog": {schema: typed_rows([(name, relation)]) if schema == scope else [] for schema in SCHEMAS},
            "authorization": [[5 if scope == "temp" else 7, name, relation, scope]], "activation": operation,
            "driver": {"payload": item["payload"], "rollback": item["rolledBack"]}, "after": after,
            "settled": before if item["rolledBack"] else after, "transactionAfter": False}


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and all(reply.get(field) == item[field] for field in STRUCTURE),
            "sqlite-trigger-mutation-structure", {"expected": item, "reply": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    observed = {"observation": observe(db, item, reply["sql"])}
    if original is not None:
        observed["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **observed)
    prediction = expected(item)
    for receipt in observed.values():
        compare(prediction, receipt)
    if ledger is not None:
        ledger.record(key, "compared")
    return observed["observation"]


def controls(db):
    item = make_case("archive", "UPDATE", "AFTER", "update-delete", "target-is-null", "current", True)
    observation = observe(db, item, source_sql(item))
    predicted = expected(item)
    compare(predicted, observation)
    misplaced, removed, phase = deepcopy(observation), deepcopy(observation), deepcopy(observation)
    misplaced["authorization"][0][-1] = "temp"
    removed["after"]["archive"]["ledger"]["rows"].pop()
    phase["after"]["archive"]["witness"]["rows"][0][-1] = ["text", "prior"]
    result = {}
    for name, negative in (("wrong-bound-destination", misplaced), ("omitted-ledger-row", removed), ("wrong-timing", phase)):
        try:
            compare(predicted, negative)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "sqlite-trigger-mutation-control-signal", failure.signal)
            result[name] = {"signal": failure.signal, "witness": negative}
        else:
            raise Failure("sqlite-trigger-mutation-comparator-accepted", name)
    return {"healthy": observation, "rejected": result}


def execute(args, specification, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, measured_id, finite_ids, forms, required, lambda: controls(db))
    return execute_schedule(args, specification, db, worker_factory, schedule, probe,
                            measured_input=lambda reply, outcome: (reply, outcome), paired_finite=lambda version: version)


def fault_probe(item, reply, db):
    if item.get("probe") != "trigger-program-construction":
        return probe(item, reply, db)
    if reply["status"] == "constructed":
        require(all(reply.get(field) == item[field] for field in STRUCTURE) and "sql" not in reply,
                "sqlite-trigger-mutation-malformed-construction-control", reply)
        raise Failure("sqlite-trigger-mutation-invalid-accepted", reply)
    require(reply["status"] == "rejected" and reply["exception"] == INVALID and item["refusal"] in reply["message"],
            "sqlite-trigger-mutation-wrong-refusal", reply)


def negative(item, step, reason):
    return {**item, "program": [step], "probe": "trigger-program-construction", "refusal": reason}


def check_faults(args, db, runner=check_render_faults):
    root = "sql-semantics-sqlite/src/Ir/Schema/Trigger/"
    common = root + "TriggerSql.php"
    render = root + "Mutation/TriggerMutationSql.php"
    ranges = root + "Mutation/TriggerMutationScope.php"
    checks = root + "Mutation/TriggerActionFields.php"
    selected = make_case("main", "UPDATE", "AFTER", payload="current")
    order = make_case("archive", "UPDATE", "AFTER", "update-delete", "target-is-null", "current")
    deleting = make_case("main", "INSERT", "AFTER", "delete", payload="current")
    collated, _, ignored, _ = tuple(authored())
    invalid_filter = negative(selected, {**selected["program"][0], "where": ["local-null", 4, True]}, LOCAL_ERROR)
    unavailable = make_case("main", "INSERT", "AFTER")
    invalid_image = negative(unavailable, {**unavailable["program"][0], "assignments": [[2, ["field", "OLD", 2]]]}, IMAGE_ERROR)
    new = make_case(ledger="new", condition="none", payload="live")
    captured = negative(new, {**new["program"][0], "assignments": [[2, ["field", "NEW", 0]]]}, "shadows this row-image field")
    signal = "meaning-mismatch"
    invalid = "sqlite-trigger-mutation-invalid-accepted"
    return runner(args, db, [
        RenderFault("reverse-ordered-trigger-mutations", common, "foreach ($trigger->program as $step)",
                    "foreach (array_reverse($trigger->program) as $step)", order, signal),
        RenderFault("swap-trigger-mutation-images", common, "$field->image->value . '.'",
                    "($field->image === RowImage::Old ? RowImage::New : RowImage::Old)->value . '.'", selected, signal),
        RenderFault("read-neighboring-local-trigger-slot", render, "$target->columns[$field->column]->quoted()",
                    "$target->columns[0]->quoted()", selected, signal),
        RenderFault("omit-trigger-delete-filter", render, "'DELETE FROM ' . $target->table->quoted() . $filter",
                    "'DELETE FROM ' . $target->table->quoted()", deleting, signal),
        RenderFault("reverse-trigger-comparison-collation", render, "$filter->targetFirst ?", "!$filter->targetFirst ?", collated, signal),
        RenderFault("drop-one-trigger-assignment", render, "foreach ($step->assignments as $assignment)",
                    "foreach (array_slice($step->assignments, 0, -1) as $assignment)", selected, signal),
        RenderFault("ignore-trigger-update-conflict-policy", render,
                    "$step->conflict === UpdateConflict::Inherit ? '' : ' OR ' . strtoupper($step->conflict->value)",
                    "''", ignored, signal),
        RenderFault("install-stored-trigger-in-decoy-schema", common, "$trigger->schema->quoted() . '.'",
                    "'temp.'", order, signal),
        RenderFault("admit-out-of-range-trigger-local-reference", ranges, "isset($destination->table->columns[$value->column])",
                    "true", invalid_filter, invalid),
        RenderFault("permit-unavailable-trigger-mutation-image", checks, "(new TriggerChecks())->field($image, $event, $watched);",
                    "", invalid_image, invalid),
        RenderFault("skip-checked-trigger-mutation-dispatch", common, "$mutation->action($step, $watched)",
                    "'SELECT 1'", selected, signal),
        RenderFault("allow-same-name-trigger-image-capture", checks, "$field->key() !== $name",
                    "true", captured, invalid),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("trigger-mutations", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, NativeDatabase)
