"""Compare SQLite integrity audit IR with independently predicted native reports and access."""

import copy
import hashlib

from run import Failure, Worker, compare, require
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import Database
from sqlite_integrity_cases import (
    UNKNOWN, analysis, case, expected, finite_id, finite_ids, forms, negatives, requests, required, source,
)
from sqlite_integrity_fixture import CHECKED, at, guards, populate, register
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-integrity-audit-v2.json"
CONTRACT = "ddd4deca982a5e3c1d16342d175cf20b9e692598e180f185f0b84e77490c4381"
FIXTURE = ""
MODES = frozenset((b"integrity_check", b"quick_check"))


class IntegrityDatabase(Database):
    """Allows only one native integrity command in the selected namespace."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library):
        super().__init__(library)
        self.schemata = frozenset()
        self.calls = []

    def authorize(self, _, action, first, second, schema, origin):
        if origin is None and action == 19 and first is not None and first.lower() in MODES and schema in self.schemata:
            self.calls.append([first.decode().lower(), None if schema is None else schema.decode(),
                               None if second is None else second.decode()])
            return 0
        if self.denied is None:
            self.denied = (action, [None if field is None else field.decode(errors="replace")
                                    for field in (first, second, schema, origin)])
        return 1


def rows(db, sql):
    return db._observe(sql, {}, preserve_order=True)["rows"]


def population(db, item):
    relations = [at(schema, table) for schema, table, _ in guards(item)]
    selected = {name: rows(db, f"SELECT id, value FROM {name} ORDER BY id") for name in relations}
    checked = at("main", CHECKED)
    selected[checked] = rows(db, f"SELECT id, code FROM {checked} ORDER BY id")
    return selected


def flag(db):
    value = rows(db, "PRAGMA ignore_check_constraints")
    require(value in ([[["integer", "0"]]], [[["integer", "1"]]]), "sqlite-integrity-constraint-flag", value)
    return value[0][0][1] == "1"


def health(db, populated):
    ordered = [code for _, code in populated[at("main", CHECKED)]]
    equal = rows(db, "SELECT 'A' COLLATE integrity_order = 'a'")
    require(ordered == [["text", "A"], ["text", "a"]], "sqlite-integrity-collation-witness", ordered)
    require(equal in ([[["integer", "0"]]], [[["integer", "1"]]]), "sqlite-integrity-collation-relation", equal)
    violations = any(value[0] == "integer" and int(value[1]) < 0 for relation in populated.values() for _, value in relation)
    stale = equal[0][0][1] == "1"
    require(not (violations and stale), "sqlite-integrity-fixture-overlap")
    return ["stale-unique-index" if stale else "check-violations" if violations else "clean", flag(db)]


def observe(template, item, sql):
    db = IntegrityDatabase(template.library)
    try:
        collation = register(db, False)
        populate(db, item)
        collation = register(db, item["data"] == "stale-unique-index")
        db.schemata = frozenset((None, b"main", b"temp", item["attachment"].encode()))
        before, initial = rows(db, "PRAGMA database_list"), population(db, item)
        fixture = health(db, initial)
        statement = db.observe(sql, {}, preserve_order=True, capture_evaluation_error=True)
        require(len(db.calls) == 1, "sqlite-integrity-native-operation", db.calls)
        invocation = db.calls[0]
        after, unchanged = rows(db, "PRAGMA database_list"), population(db, item)
        after_flag = flag(db)
        db.calls.clear()
        program = db.observe("EXPLAIN " + sql, {}, preserve_order=True)["rows"]
        require(db.calls == [invocation], "sqlite-integrity-program-coordinate", db.calls)
        slots = [int(row[2][1]) for row in program if row[1] == ["text", "Transaction"]]
        physical = [int(row[6][1]) for row in program if row[1] == ["text", "IntegrityCk"]]
        limits = [int(row[2][1]) + 1 for row in program if row[1] == ["text", "Integer"] and row[3] == ["integer", "1"]]
        require(limits, "sqlite-integrity-native-budget-absent", program)
        return {"statement": statement, "program": program, "slots": slots, "physical": physical, "budget": limits[0],
                "attachedBefore": before, "attachedAfter": after, "populationBefore": initial, "populationAfter": unchanged,
                "fixture": fixture, "ignoreAfter": after_flag, "invocation": invocation, "input": sql}
    finally:
        db.check(db.close(db.db))


def semantic(observation):
    return {key: value for key, value in observation.items() if key not in ("invocation", "input", "program")}


def exact_invocation(item):
    target = item["target"]
    if target[0] == "limit":
        argument = None if target[1] == 100 else str(target[1])
    elif target[0] == "relation":
        argument = target[2]
    else:
        argument = "sqlite_temp_master" if target[1] == "temp" else "sqlite_master"
    return [item["pragma"], item["schema"], argument]


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and reply.get("operation") == "integrity-audit"
            and all(reply.get(field) == item[field] for field in ("pragma", "schema", "target")),
            "sqlite-integrity-structure", {"expected": item["target"], "actual": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    findings = {"observation": observe(db, item, reply["sql"])}
    if original is not None:
        findings["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **findings)
    compare(exact_invocation(item), findings["observation"]["invocation"])
    if original is not None:
        compare([item["pragma"], item["schema"], item["argument"]], findings["original"]["invocation"])
    for observation in findings.values():
        compare(expected(item), semantic(observation))
    if ledger is not None:
        ledger.record(key, "compared")
    return findings.get("original", findings["observation"])


def controls(db):
    args = case(role="implicit-guard", temp="shadowed", counts=(3, 2, 4))
    healthy = observe(db, args, source(args))
    model = expected(args)
    focused, collapsed, budget = (copy.deepcopy(healthy) for _ in range(3))
    focused["slots"] = [1]
    collapsed["statement"]["rows"].pop()
    budget["budget"] = 1
    compare(model, semantic(healthy))
    failures = {}
    for name, altered in (("lost-implicit-verification-slots", focused), ("collapsed-native-reports", collapsed),
                          ("changed-prepared-budget", budget)):
        try:
            compare(model, semantic(altered))
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "sqlite-integrity-comparator-signal", failure.signal)
            failures[name] = {"witness": altered, "signal": failure.signal}
        else:
            raise Failure("sqlite-integrity-comparator-accepted", name)
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
                "sqlite-integrity-invalid-accepted", reply)
    else:
        probe(item, reply, db)


def check_faults(args, db, runner=check_render_faults):
    prefix = "sql-semantics-sqlite/src/Ir/Maintenance/Integrity/"
    output, lower, limit = (prefix + filename for filename in ("IntegrityAudit.php", "IntegrityAuditLowering.php", "IntegrityLimit.php"))
    named = case(role="implicit-guard", temp="shadowed")
    bounded = case(role="signed-one")
    missing = {**named, "refusal": UNKNOWN}
    zero = {**bounded, "target": ["limit", 0], "refusal": "must be a positive signed 32-bit integer"}
    return runner(args, db, [
        RenderFault("permit-zero-integrity-budget", limit, "$value >= 1 && $value <= self::MAX_VALUE",
                    "$value >= 0 && $value <= self::MAX_VALUE", zero, "sqlite-integrity-invalid-accepted"),
        RenderFault("always-run-full-integrity-check", output, "$this->kind->value",
                    "IntegrityKind::Full->value", case("quick_check", "stale-relation", data="stale-unique-index"), "meaning-mismatch"),
        RenderFault("suppress-integrity-table-selector", output, "$target instanceof RelationTarget => '(' . $target->name->quoted() . ')',",
                    "$target instanceof RelationTarget => '',", named, "meaning-mismatch"),
        RenderFault("restrict-implicit-integrity-audit-to-target-database", output,
                    "$this->schema === null ? '' : $this->schema->quoted() . '.'",
                    "$this->schema === null && $target instanceof RelationTarget ? $target->schema->quoted() . '.' : ($this->schema === null ? '' : $this->schema->quoted() . '.')",
                    named, "meaning-mismatch"),
        RenderFault("use-signed-spelling-to-determine-integrity-budget", lower, "(new IntegrityNumber())->limit($value->token)",
                    "(new IntegrityNumber())->limit($value->nativeText())",
                    bounded, "sqlite-integrity-structure", analysis(bounded, source(bounded))),
        RenderFault("publish-unknown-integrity-selector-as-default", lower,
                    "Check::input(!$target instanceof MissingMetadataTable, 'Integrity audit requires a resolved table, view or schema catalog.');",
                    "if ($target instanceof MissingMetadataTable) { return new IntegrityAudit($kind, schema: $scope); }",
                    missing, "sqlite-integrity-invalid-accepted", analysis(missing, "PRAGMA quick_check(missing_table)")),
        RenderFault("drop-finite-integrity-budget-from-output", output,
                    "$target instanceof IntegrityLimit => $target->value === IntegrityLimit::DEFAULT_VALUE ? '' : '(' . $target->value . ')',",
                    "$target instanceof IntegrityLimit => '',", bounded, "meaning-mismatch"),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("integrity-audit", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, IntegrityDatabase)
