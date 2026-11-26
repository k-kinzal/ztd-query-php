"""Exercise bound SQLite index removal and guarded absence on the pinned native engine."""

import copy
import hashlib
import re

from run import Failure, Worker, compare, require
from run_sqlite_create_table import quoted
from run_sqlite_unique_keys import values_sql
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_drop_index_cases import (
    FIELDS, OWNERS, SCHEMAS, case, control, declarations, expected, finite_id, finite_ids, forms,
    future_rows, initial_rows, negatives, requests, required, schema_name, schema_sql, table_sql,
)
from sqlite_engine import Database, ObserverAuthorizationError
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-drop-index-v3.json"
CONTRACT = "50d18761f064c86d44b621cd90c8a012e9d9edc8ddbb3849ccb1921f5dfa9d78"
FIXTURE = ""
DROP_ACTIONS = frozenset((10, 12))
CATALOG_FIELDS = frozenset((b"name", b"type", b"rootpage"))
MISSING = re.compile(r"\ASQLite 1: no such index: .*\Z", re.DOTALL)
INVALID = "SqlSemantics\\Platform\\Sqlite\\Model\\Diagnostic\\InvalidConstruction"


class DropIndexDatabase(Database):
    """Permits the explicitly selected index removal and required catalog operations."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library):
        super().__init__(library)
        self.target = None
        self.events = []

    def authorize(self, _, action, first, second, schema, origin):
        if self.target is not None and origin is None:
            scope, index, table = self.target
            catalog = b"sqlite_temp_master" if scope == b"temp" else b"sqlite_master"
            if schema == scope:
                if action == 9 and second is None and first == catalog:
                    return 0
                if action == (12 if scope == b"temp" else 10) and first == index and second == table:
                    self.events.append([action, first.decode(), second.decode(), schema.decode()])
                    return 0
                if self.events:
                    if action == 20 and first == catalog and second in CATALOG_FIELDS:
                        return 0
                    if action == 23 and first == catalog and second == b"rootpage":
                        return 0
                    if first == b"sqlite_stat1" and ((action == 9 and second is None) or (action == 20 and second == b"idx")):
                        return 0
        if self.denied is None:
            self.denied = (action, [part.decode(errors="replace") if part is not None else None
                                    for part in (first, second, schema, origin)])
        return 1

    def drop(self, sql, item):
        self.target = None if item["targetRole"] == "absent" else tuple(
            item[field].encode() for field in ("schema", "name", "table")
        )
        self.events = []
        try:
            plan = self.observe("EXPLAIN " + sql, {}, preserve_order=True)
            verification = [int(row[2][1]) for row in plan["rows"] if row[1] == ["text", "Transaction"]]
            self.events = []
            result = self.observe(sql, {}, capture_evaluation_error=True)
            return result, verification, self.events.copy()
        except ObserverAuthorizationError as error:
            if error.action in DROP_ACTIONS or (error.action == 9 and error.arguments[0] in ("sqlite_master", "sqlite_temp_master")):
                raise Failure("sqlite-drop-index-target-mismatch", error.arguments) from error
            raise
        except RuntimeError as error:
            if MISSING.fullmatch(str(error)) is None:
                raise
            return {"prepareError": str(error)}, [], []
        finally:
            self.target = None


def setup(db, item):
    db.execute(f"ATTACH ':memory:' AS {schema_sql(item, 'extension')}")
    db.execute("; ".join(declarations(item)))
    for scope in SCHEMAS:
        db.execute(f"INSERT INTO {table_sql(item, scope)} VALUES " + values_sql(initial_rows(item, scope)))
        if item["statistics"]:
            db.execute(f"ANALYZE {schema_sql(item, scope)}")


def read(db, sql):
    result = db._observe(sql, {}, preserve_order=True)
    require(result["readOnly"] and result["changes"] == 0, "sqlite-drop-index-observer-read", sql)
    return result


def snapshot(db, item):
    result = {}
    for scope in SCHEMAS:
        namespace = schema_sql(item, scope)
        has_statistics = read(db, f"SELECT name FROM {namespace}.sqlite_schema WHERE name='sqlite_stat1'")["rows"]
        data = read(db, f'SELECT * FROM {table_sql(item, scope)} ORDER BY "id"')
        result[scope] = {
            "catalog": read(db, f"SELECT name,tbl_name FROM {namespace}.sqlite_schema WHERE type='index' ORDER BY name")["rows"],
            "indexes": read(db, f"PRAGMA {namespace}.index_list({quoted(OWNERS[scope])})")["rows"],
            "statistics": read(db, f"SELECT tbl,idx,stat FROM {namespace}.sqlite_stat1 ORDER BY tbl,idx")["rows"]
            if has_statistics else None,
            "heading": data["names"], "rows": data["rows"],
        }
    return result


def observe(template, item, sql):
    db = type(template)(template.library)
    try:
        setup(db, item)
        before = snapshot(db, item)
        db.execute("BEGIN")
        statement, verification, authorization = db.drop(sql, item)
        after = snapshot(db, item)
        writes = {}
        for scope in SCHEMAS:
            writes[scope] = [db._observe(f"INSERT INTO {table_sql(item, scope)} VALUES {values_sql((row,))}", {},
                                         capture_evaluation_error=True) for row in future_rows(item, scope)]
        later = snapshot(db, item)
        active = not bool(db.get_autocommit(db.db))
        if active:
            db.execute("ROLLBACK")
        return {"before": before, "statement": statement, "verification": verification, "after": after, "writes": writes,
                "later": later, "rolledBack": snapshot(db, item), "active": active, "authorization": authorization}
    finally:
        db.close(db.db)


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and all(reply.get(field) == item[field] for field in FIELDS),
            "sqlite-drop-index-structure", {"expected": item, "actual": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    observations = {"observation": observe(db, item, reply["sql"])}
    if original is not None:
        observations["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **observations)
    prediction = expected(item)
    for result in observations.values():
        compare(prediction, result)
    if ledger is not None:
        ledger.record(key, "compared")
    return observations["observation"]


def controls(db):
    item = case("all-absence", siblings="both")
    original = observe(db, item, "DROP INDEX IF EXISTS Tag")
    predicted = expected(item)
    compare(predicted, original)
    errors = {}
    modified = copy.deepcopy(original)
    modified["verification"] = [0]
    other = copy.deepcopy(original)
    other["after"]["main"]["catalog"] = other["after"]["main"]["catalog"][:-1]
    for name, known_wrong in (("verification-scope", modified), ("surviving-index", other)):
        try:
            compare(predicted, known_wrong)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "sqlite-drop-index-comparator-signal", failure.signal)
            errors[name] = {"signal": failure.signal, "witness": known_wrong}
        else:
            raise Failure("sqlite-drop-index-comparator-accepted", name)
    return {"healthy": original, "rejected": errors}


def execute(args, specification, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, lambda: controls(db))
    return execute_schedule(args, specification, db, worker_factory, schedule, probe,
                            measured_input=lambda reply, observation: (reply, observation))


def fault_probe(item, reply, db):
    if item.get("probe") != "drop-index-construction":
        return probe(item, reply, db)
    if reply["status"] == "constructed":
        require(all(reply.get(field) == item[field] for field in FIELDS) and "sql" not in reply,
                "sqlite-drop-index-unexpected-value", reply)
        raise Failure("sqlite-drop-index-invalid-absence-published", reply)
    require(reply["status"] == "rejected" and reply["exception"] == INVALID and "requires IF EXISTS" in reply["message"],
            "sqlite-drop-index-fault-wrong-rejection", reply)


def check_faults(args, db, runner=check_render_faults):
    root = "sql-semantics-sqlite/src/Ir/Schema/Index/"
    installed = case("main-required", siblings="both")
    absent = case("all-absence")
    unknown = case("unknown-absence")
    return runner(args, db, [
        RenderFault("drop-index-from-shadow-schema", root + "IndexReference.php", "$this->schema->quoted()", "'temp'",
                    installed, "sqlite-drop-index-target-mismatch"),
        RenderFault("drop-adjacent-index", root + "IndexReference.php", "$this->name->quoted()", "'control_main'",
                    installed, "sqlite-drop-index-target-mismatch"),
        RenderFault("omit-drop-index-guard", root + "DropIndex.php", "($this->ifExists ? 'IF EXISTS ' : '')", "''",
                    absent, "meaning-mismatch"),
        RenderFault("restrict-all-schema-absence", root + "MissingIndex.php", "$this->schema === null ? ''",
                    "$this->schema === null ? 'main.'", absent, "meaning-mismatch"),
        RenderFault("ignore-explicit-index-absence-schema", root + "MissingIndex.php",
                    "($this->schema === null ? '' : $this->schema->quoted() . '.') . ", "", unknown, "meaning-mismatch"),
        RenderFault("allow-unguarded-index-absence", root + "DropIndex.php", "$ifExists || $target instanceof IndexReference",
                    "true", {**absent, "ifExists": False, "probe": "drop-index-construction"}, "sqlite-drop-index-invalid-absence-published"),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("drop-index", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, DropIndexDatabase)
