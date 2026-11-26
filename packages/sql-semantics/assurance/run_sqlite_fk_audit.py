"""Verify SQLite's native foreign-key audit with an independent child-key model."""
import copy
import hashlib

from run import Failure, Worker, compare, require
from run_sqlite_create_table import quoted
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import Database
from sqlite_fk_audit_cases import analysis, case, expected, finite_id, finite_ids, forms, negatives, requests, required, source
from sqlite_fk_audit_fixture import at, child_columns, declarations, insertions, schemas
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-foreign-key-audit-v1.json"
CONTRACT = "a04f94db9a3058f7826755c4df43f54eeb21491832e93d656301dc0c8428fbe8"
FIXTURE = ""


class AuditDatabase(Database):
    """Restricts candidate SQL to SQLite's native foreign-key audit request."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library):
        super().__init__(library)
        self.schemata = frozenset()
        self.calls = []

    def authorize(self, _, action, first, second, schema, origin):
        if origin is None and action == 19 and first is not None and first.lower() == b"foreign_key_check" and schema in self.schemata:
            self.calls.append([first.decode().lower(), None if schema is None else schema.decode(),
                               None if second is None else second.decode()])
            return 0
        if self.denied is None:
            self.denied = (action, [None if field is None else field.decode(errors="replace")
                                    for field in (first, second, schema, origin)])
        return 1


def rows(db, sql, bindings=None):
    return db._observe(sql, {} if bindings is None else bindings, preserve_order=True)["rows"]


def population(db, item):
    fields = ", ".join(quoted(value) for value in child_columns(item))
    return {schema: rows(db, f"SELECT {fields} FROM {at(schema, item['child'])} ORDER BY id") for schema, _ in schemas(item)}


def enclosure(db, item):
    namespace = quoted(item["attachment"])
    catalog = f"SELECT sql FROM {namespace}.sqlite_schema WHERE type='table' AND name=?1"
    child = rows(db, catalog, {"1": item["child"]})
    parent = rows(db, catalog, {"1": item["parent"]})
    require(len(child) == 1 and len(parent) <= 1, "sqlite-fk-audit-fixture-definition", [child, parent])
    definition = child[0][0][1]
    storage = "without-rowid" if definition.endswith(" WITHOUT ROWID") else "rowid"
    layout = "composite" if "FOREIGN KEY (first_num," in definition else "single"
    state = "missing" if not parent else "valid" if "PRIMARY KEY" in parent[0][0][1] else "mismatched"
    flags = rows(db, "PRAGMA foreign_keys")
    require(flags in ([[["integer", "0"]]], [[["integer", "1"]]]), "sqlite-fk-audit-enforcement", flags)
    return [storage, state, bool(int(flags[0][0][1])), layout]


def setup(db, item):
    attached = item["attachment"]
    db.execute("ATTACH ':memory:' AS " + quoted(attached))
    db.execute("PRAGMA foreign_keys = 0")
    db.execute("; ".join(declarations(item)))
    db.execute("; ".join(insertions(item)))
    db.execute("PRAGMA foreign_keys = " + str(int(item["enforcement"])))
    db.schemata = frozenset((None, b"main", b"temp", attached.encode()))


def inspect_audit(db, item, sql):
    try:
        return db.observe(sql, {}, preserve_order=True, capture_evaluation_error=True)
    except RuntimeError as error:
        model = expected(item)["statement"]
        if str(error) == model.get("prepareError"):
            return {"prepareError": str(error)}
        raise


def observe(template, item, sql):
    db = AuditDatabase(template.library)
    try:
        setup(db, item)
        evidence = enclosure(db, item)
        before, initial = rows(db, "PRAGMA database_list"), population(db, item)
        statement = inspect_audit(db, item, sql)
        require(len(db.calls) == 1, "sqlite-fk-audit-native-operation", db.calls)
        invocation = db.calls[0]
        after, unchanged = rows(db, "PRAGMA database_list"), population(db, item)
        enforcement = enclosure(db, item)[2]
        db.calls.clear()
        program = None if "prepareError" in statement else db.observe("EXPLAIN " + sql, {}, preserve_order=True)["rows"]
        slots = [] if program is None else [int(row[2][1]) for row in program if row[1] == ["text", "Transaction"]]
        require(program is None or db.calls == [invocation], "sqlite-fk-audit-program-coordinate", db.calls)
        return {"statement": statement, "attachedBefore": before, "attachedAfter": after, "slots": slots, "program": program,
                "populationBefore": initial, "populationAfter": unchanged, "fixture": evidence,
                "enforcementAfter": enforcement, "invocation": invocation}
    finally:
        db.check(db.close(db.db))


def semantic(native):
    measured = {key: value for key, value in native.items() if key not in ("program", "invocation")}
    if "rows" in measured["statement"]:
        measured["statement"] = {**measured["statement"], "rows": sorted(measured["statement"]["rows"])}
    return measured


def exact_invocation(item):
    target = item["target"]
    name = "foreign_key_check"
    if target[0] == "schema":
        return [name, target[1], None]
    if target[0] == "relation":
        return [name, target[1], target[2]]
    return [name, target[2], "sqlite_temp_master" if target[1] == "temp" else "sqlite_master"]


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and reply.get("operation") == "foreign-key-audit"
            and reply.get("target") == item["target"], "sqlite-fk-audit-structure", {"expected": item["target"], "actual": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    findings = {"observation": observe(db, item, reply["sql"])}
    if original is not None:
        findings["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **findings)
    compare(exact_invocation(item), findings["observation"]["invocation"])
    if original is not None:
        compare(["foreign_key_check", item["schema"], item["argument"]], findings["original"]["invocation"])
    for observation in findings.values():
        compare(expected(item), semantic(observation))
    if ledger is not None:
        ledger.record(key, "compared")
    return findings.get("original", findings["observation"])


def controls(db):
    item = case("main-child", storage="without-rowid", layout="composite")
    healthy = observe(db, item, source(item))
    wanted = expected(item)
    compare(wanted, semantic(healthy))
    collapse, wrong_fk = copy.deepcopy(healthy), copy.deepcopy(healthy)
    collapse["statement"]["rows"].pop()
    wrong_fk["statement"]["rows"][0][-1] = ["integer", "11"]
    failures = {}
    for label, observation in (("lost-duplicate-null-rowid", collapse), ("changed-constraint-ordinal", wrong_fk)):
        try:
            compare(wanted, semantic(observation))
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "sqlite-fk-audit-comparator-signal", failure.signal)
            failures[label] = {"witness": observation, "signal": failure.signal}
        else:
            raise Failure("sqlite-fk-audit-comparator-accepted", label)
    return {"healthy": healthy, "rejected": failures}


def execute(args, contract, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, lambda: controls(db))
    return execute_schedule(args, contract, db, worker_factory, schedule, probe,
                            measured_input=lambda reply, observation: (reply, observation))


def fault_probe(item, reply, db):
    if item.get("refusal"):
        require(reply["status"] == "rejected" and item["refusal"] in reply.get("message", ""),
                "sqlite-fk-audit-unknown-selector-accepted", reply)
    else:
        probe(item, reply, db)


def check_faults(args, db, runner=check_render_faults):
    root = "sql-semantics-sqlite/src/Ir/Maintenance/Metadata/"
    output, lower = root + "ForeignKeyAudit.php", root + "ForeignKeyAuditLowering.php"
    shadow, named = case("implicit-child", temp="shadowed"), case("main-child")
    missing = {**named, "refusal": "requires a resolved child or schema catalog"}
    return runner(args, db, [
        RenderFault("audit-main-ahead-of-shadowing-child", lower,
                    "(new TableMetadataLowering())->target($context, $name, $scope)",
                    "(new TableMetadataLowering())->target($context, $name, $scope ?? new Identifier('main'))",
                    shadow, "sqlite-fk-audit-structure", analysis(shadow, source(shadow))),
        RenderFault("audit-entire-schema-from-named-child", output,
                    "'.foreign_key_check(' . $target->name->quoted() . ')'",
                    "'.foreign_key_check'", named, "meaning-mismatch"),
        RenderFault("ignore-audit-scope-in-output", output, "'PRAGMA ' . $target->quoted() . '.foreign_key_check'",
                    "'PRAGMA foreign_key_check'", case("attachment-schema"), "meaning-mismatch"),
        RenderFault("audit-sibling-child", output, "$target->name->quoted()", "'secondary_child'", named, "meaning-mismatch"),
        RenderFault("open-temp-for-implicit-catalog-audit", output,
                    "$this->catalogAccess === null ? '' : $this->catalogAccess->quoted() . '.'",
                    "$this->catalogAccess === null ? 'temp.' : $this->catalogAccess->quoted() . '.'",
                    case("implicit-temp-catalog"), "meaning-mismatch"),
        RenderFault("publish-missing-child-as-whole-schema", lower,
                    "Check::input(!$target instanceof MissingMetadataTable, 'Foreign-key audit requires a resolved child or schema catalog.');",
                    "if ($target instanceof MissingMetadataTable) { return new ForeignKeyAudit(new Identifier('main')); }",
                    missing, "sqlite-fk-audit-unknown-selector-accepted",
                    analysis(missing, "PRAGMA main.foreign_key_check(missing_child)")),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("foreign-key-audit", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, AuditDatabase)
