"""Compare implicit appended CHECK labels and native schema rewrites."""

import copy
import hashlib

from run import Failure, Worker, compare, require
from run_sqlite_add_column import FIXTURE
from run_sqlite_appended_checks import CheckedAdditionDatabase, observe
from sqlite_add_column_cases import binding_projection, check_binding_contract
from sqlite_appended_checks_cases import FIELDS
from sqlite_appended_implicit_check_cases import (
    case, expected, finite_id, finite_ids, forms, inherited, inherited_request, invalid_binding, invalid_order, negatives,
    requests, required, rewrites,
)
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-appended-implicit-checks-v2.json"
CONTRACT = "050bf40f741cb7799f3a8351828bf844adb58632218923384e7eb26224ee1169"
BOUNDARY = "SqlSemantics\\Platform\\Sqlite\\Model\\Diagnostic\\InvalidConstruction"


class ImplicitAdditionDatabase(CheckedAdditionDatabase):
    policy_digest = hashlib.sha256(f"{CheckedAdditionDatabase.policy_digest}:{CONTRACT}".encode()).hexdigest()


def measure(db, item, sql):
    addition = observe(db, item, sql)
    altered = addition["statement"]["evaluationError"] is None
    native_rewrites = {}
    for kind, rewrite in rewrites(item):
        native_rewrites[kind] = observe(db, item, sql, rewrite)["writes"] if altered else None
    return {"addition": addition, "rewrites": native_rewrites}


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and all(reply.get(field) == item[field] for field in FIELDS)
            and (reply.get("slot"), reply.get("bindings")) == binding_projection(item),
            "sqlite-appended-implicit-structure", {"expected": item, "actual": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    results = {"observation": measure(db, item, reply["sql"])}
    if original is not None:
        results["original"] = measure(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **results)
    target = expected(item)
    for result in results.values():
        compare(target, result)
    if ledger is not None:
        ledger.record(key, "compared")
    return results["observation"]


def control(db):
    item = case(population="empty")
    sql = "ALTER TABLE main.ir_add_checks ADD checked INTEGER DEFAULT 2 CHECK (operand =000)"
    healthy = measure(db, item, sql)
    prediction = expected(item)
    compare(prediction, healthy)
    changed = copy.deepcopy(healthy)
    changed["rewrites"]["bare"]["statements"]["insert-first"]["evaluationError"]["message"] = "CHECK constraint failed: operand =000"
    try:
        compare(prediction, changed)
    except Failure as error:
        require(error.signal == "meaning-mismatch", "sqlite-appended-implicit-comparator-signal", error.detail)
        return {"original": healthy, "frozenLabel": {"witness": changed, "signal": error.signal}}
    raise Failure("sqlite-appended-implicit-comparator-accepted-frozen-label")


def execute(args, specification, db, worker_factory=Worker):
    check_binding_contract(specification)
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, lambda: control(db))
    return execute_schedule(args, specification, db, worker_factory, schedule, probe,
                            measured_input=lambda reply, observation: (reply, observation))


def fault_probe(item, reply, db):
    boundary = item.get("boundary")
    if boundary is None:
        probe(item, reply, db)
        return
    if reply["status"] == "constructed":
        require(all(reply.get(field) == item[field] for field in FIELDS)
                    and (reply.get("slot"), reply.get("bindings")) == binding_projection(item) and "sql" not in reply,
                "sqlite-appended-implicit-unexpected-mutant-value", reply)
        raise Failure("sqlite-appended-implicit-construction-escaped", reply)
    require(reply["status"] == "rejected" and reply["exception"] == BOUNDARY and boundary in reply["message"],
            "sqlite-appended-implicit-unexpected-rejection", reply)


def check_faults(args, db, runner=check_render_faults):
    base = "sql-semantics-sqlite/src/Ir/Schema/"
    checked = base + "Predicate/CheckConstraint.php"
    lexical = base + "Predicate/ImplicitCheckLabel.php"
    renderer = base + "TableDeclarationSql.php"
    lowerer = base + "Predicate/CheckLowering.php"
    item = case(population="empty")
    carry = inherited(schema="temp", generated=True)
    implicit = "$check->label->expression($condition->right)"
    return runner(args, db, [
        RenderFault("ignore-implicit-appended-slot", checked, "$declared->key() === $label->columnName->key()", "true",
                    {**invalid_binding(), "boundary": "must identify the resolved column", "probe": "appended-check-construction"},
                    "sqlite-appended-implicit-construction-escaped"),
        RenderFault("permit-implicit-after-named", checked, "Check::input(!$inheritsName,", "Check::input(true,",
                    {**invalid_order(), "boundary": "cannot follow a named CHECK", "probe": "appended-check-construction"},
                    "sqlite-appended-implicit-construction-escaped"),
        RenderFault("quote-implicit-appended-label", lexical, "$this->quoted ?", "true ?", item, "meaning-mismatch"),
        RenderFault("unpad-implicit-appended-label", lexical, "str_repeat('0', $this->leadingZeroes)", "''", item, "meaning-mismatch"),
        RenderFault("freeze-implicit-appended-label", renderer, "return 'CHECK (' . " + implicit,
                    """return 'CONSTRAINT "operand =000" CHECK (' . """ + implicit, item, "meaning-mismatch"),
        RenderFault("forget-inherited-appended-label", lowerer, "$checks[] = new CheckConstraint($name, $condition, $owner);",
                    "$checks[] = new CheckConstraint($name, $condition, $owner);\n                $label = null;",
                    carry, "sqlite-appended-implicit-structure", request=inherited_request(carry)),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("appended-implicit-checks", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, ImplicitAdditionDatabase)
