"""Check resolved SQLite column additions against an independent transition model."""

import copy
import hashlib

from run import Failure, Worker, compare, require
from run_sqlite_create_table import quoted
from run_sqlite_unique_keys import values_sql
from sqlite_add_column_cases import (
    SCHEMAS, TARGET_FIELDS, addition, binding_projection, check_binding_contract, declarations, expected, finite_id, finite_ids, forms, generated,
    negatives, original_rows, probe_values, requests, required,
)
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import Database, ObserverAuthorizationError, typed
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-add-column-v4.json"
CONTRACT = "9d19dcac2a0706782d956878b9dafb26a9e922370765ab7627031f129d65cc5b"
FIXTURE = "PRAGMA foreign_keys=ON; PRAGMA legacy_alter_table=OFF; PRAGMA writable_schema=OFF;"
CATALOG = {b"main": b"sqlite_master", b"temp": b"sqlite_temp_master"}
INTERNAL = frozenset((b"printf", b"substr", b"length", b"glob"))


class AddColumnDatabase(Database):
    """Authorizes only a scoped ALTER and its native schema checks."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library):
        super().__init__(library)
        self.target = None
        self.authorized = False

    def authorize(self, _, action, first, second, schema, origin):
        target_schema, table = self.target
        if origin is None:
            if action == 26 and (first, second, schema) == (target_schema, table, None):
                self.authorized = True
                return 0
            if self.authorized:
                if action == 21 and first is None and second is None and schema is None:
                    return 0
                if action == 31 and first is None and second in INTERNAL and schema is None:
                    return 0
                if action == 20 and (
                    (first, second, schema) == (table, b"", target_schema)
                    or (first, second, schema) == (b"pragma_quick_check", b"quick_check", b"main")
                    or (first == CATALOG[target_schema] and second in (b"sql", b"type", b"name") and schema == target_schema)
                ):
                    return 0
                if action == 23 and (first, second, schema) == (CATALOG[target_schema], b"sql", target_schema):
                    return 0
                if action == 19 and (first, second, schema) == (b"quick_check", table, target_schema):
                    return 0
        if self.denied is None:
            self.denied = (action, [part.decode(errors="replace") if part is not None else None
                                    for part in (first, second, schema, origin)])
        return 1

    def add(self, sql, schema, table):
        require(schema in SCHEMAS, "sqlite-add-column-observer-schema", schema)
        self.target = (schema.encode(), table.encode())
        try:
            result = self.observe(sql, {}, capture_evaluation_error=True)
            require(self.authorized, "sqlite-add-column-operation-not-observed", sql)
            return result
        finally:
            self.target = None
            self.authorized = False

    def read(self, sql, bindings=None):
        outcome = self._observe(sql, {} if bindings is None else bindings, preserve_order=True)
        require(outcome["readOnly"] and outcome["changes"] == 0,
                "sqlite-add-column-observer-read", outcome)
        return outcome


def setup(db, item):
    db.execute(FIXTURE)
    db.execute("; ".join(declarations(item)))
    table = quoted(item["table"])
    for schema in SCHEMAS:
        rows = original_rows(item, schema)
        if rows:
            db.execute(f"INSERT INTO {schema}.{table} VALUES {values_sql(rows)}")


def snapshot(db, item):
    name = item["table"]
    table = quoted(name)
    prior_key = quoted(item["priorHeading"][0])
    column = item["column"][0]
    picture = {}
    for schema in SCHEMAS:
        root = db.read(f"SELECT type, name, tbl_name, rootpage FROM {schema}.sqlite_schema WHERE name=?1",
                       {"1": name})["rows"]
        catalog = db.read(f"PRAGMA {schema}.table_list({table})")["rows"]
        require(len(root) == 1 and len(catalog) == 1 and catalog[0][2] == typed("table"),
                "sqlite-add-column-catalog-identity", {"root": root, "catalog": catalog})
        info = db.metadata("table_xinfo", schema=schema, table=name)
        columns = [[*row[:4], row[4] != typed(None), *row[5:]] for row in info]
        rows = db.read(f"SELECT * FROM {schema}.{table} ORDER BY {prior_key}")
        equals = None
        if schema == item["schema"] and column in rows["names"]:
            actual = quoted(column)
            compared = db.read(f"SELECT {prior_key}, {actual}='a', {actual}='A' FROM {schema}.{table} ORDER BY {prior_key}")
            equals = compared["rows"]
        picture[schema] = {"root": root[0], "strict": catalog[0][5] == typed(1), "fields": rows["names"],
                           "columns": columns, "rows": rows["rows"], "equals": equals}
    return picture


def writes(db, item):
    target = f"{item['schema']}.{quoted(item['table'])}"
    prior, column = quoted(item["priorHeading"][0]), quoted(item["column"][0])
    omitted = db._observe(f"INSERT INTO {target}({prior}) VALUES (89)", {}, capture_evaluation_error=True)
    first, last = probe_values(item)
    group = values_sql(((91, first), (92, None), (93, last)))
    db.execute("BEGIN")
    nulls = db._observe(f"INSERT INTO {target}({prior}, {column}) VALUES {group}", {}, capture_evaluation_error=True)
    active = not bool(db.get_autocommit(db.db))
    visible = snapshot(db, item)
    if active:
        db.execute("ROLLBACK")
    return {"omitted": omitted, "nulls": nulls, "active": active, "visible": visible, "rolledBack": snapshot(db, item)}


def observe(template, item, sql):
    db = AddColumnDatabase(template.library)
    try:
        setup(db, item)
        before = snapshot(db, item)
        db.execute("BEGIN")
        try:
            result = db.add(sql, item["schema"], item["table"])
        except ObserverAuthorizationError as failure:
            if failure.action == 26:
                raise Failure("sqlite-add-column-target-mismatch", failure.arguments) from failure
            raise
        active = not bool(db.get_autocommit(db.db))
        after = snapshot(db, item)
        if active:
            db.execute("ROLLBACK")
        restored = snapshot(db, item)
        inserted = None
        if result["evaluationError"] is None:
            require(db.add(sql, item["schema"], item["table"]) == result, "sqlite-add-column-repeat-effect")
            inserted = writes(db, item)
        return {"before": before, "statement": result, "after": after,
                "rolledBack": restored, "transaction": active, "writes": inserted}
    finally:
        db.close(db.db)


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed", "sqlite-add-column-positive-rejected", reply)
    require(all(reply.get(field) == item[field] for field in TARGET_FIELDS)
            and (reply.get("slot"), reply.get("bindings")) == binding_projection(item),
            "sqlite-add-column-structure-mismatch", reply)
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    result = {"observation": observe(db, item, reply["sql"])}
    if original is not None:
        result["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **result)
    prediction = expected(item)
    for actual in result.values():
        compare(prediction, actual)
    if ledger is not None:
        ledger.record(key, "compared")
    return result["observation"]


def controls(db):
    item = addition(kind="TEXT", default=["text", "A"], policy="IGNORE", collation="NOCASE")
    prediction = expected(item)
    sql = "ALTER TABLE main.ir_add ADD COLUMN added TEXT NOT NULL ON CONFLICT IGNORE DEFAULT 'A' COLLATE NOCASE"
    actual = observe(db, item, sql)
    compare(prediction, actual)
    outcomes = {}
    for key in ("field", "row", "conflict"):
        bad = copy.deepcopy(actual)
        if key == "field":
            bad["after"]["main"]["fields"][-1] = "wrong"
        if key == "row":
            bad["after"]["main"]["rows"].append(bad["after"]["main"]["rows"][-1])
        if key == "conflict":
            bad["writes"]["nulls"]["changes"] = 0
        try:
            compare(prediction, bad)
        except Failure as exc:
            require(exc.signal == "meaning-mismatch", "sqlite-add-column-comparator-signal", exc.detail)
            outcomes[key] = {"input": bad, "signal": exc.signal}
        else:
            raise Failure("sqlite-add-column-comparator-accepted-known-difference", key)
    outside = "ALTER TABLE temp.ir_add ADD COLUMN unrelated INT"
    try:
        observe(db, item, outside)
    except Failure as exc:
        require(exc.signal == "sqlite-add-column-target-mismatch" and exc.detail == ["temp", "ir_add", None, None],
                "sqlite-add-column-authorizer-signal", exc.detail)
        outcomes["outsideTarget"] = {"sql": outside, "signal": exc.signal, "detail": exc.detail}
    else:
        raise Failure("sqlite-add-column-authorizer-accepted-other-target")
    return {"control": actual, "rejected": outcomes}


def execute(args, specification, db, worker_factory=Worker):
    check_binding_contract(specification)
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, lambda: controls(db))
    return execute_schedule(args, specification, db, worker_factory, schedule, probe,
                            measured_input=lambda reply, observation: (reply, observation))


def fault_probe(item, reply, db):
    if len(item["column"]) == 6:
        exception = "SqlSemantics\\Platform\\Sqlite\\Model\\Diagnostic\\InvalidConstruction"
        require(reply["status"] == "rejected" and reply["exception"] == exception
                and "cannot have a generation expression" in reply["message"],
                "sqlite-add-column-negative-boundary", reply)
    else:
        probe(item, reply, db)


def check_faults(args, db, runner=check_render_faults):
    root = "sql-semantics-sqlite/src/Ir/Schema/"
    column, renderer = root + "Alter/AddColumn.php", root + "TableDeclarationSql.php"
    literal = "sql-semantics-sqlite/src/Ir/Literal/RealLiteral.php"
    return runner(args, db, [
        RenderFault("allow-generated-column", column, "$column->generated === null", "true",
                    generated(), "sqlite-add-column-negative-boundary"),
        RenderFault("other-add-column-schema", column, "$this->schema->quoted()", "'temp'",
                    addition(), "sqlite-add-column-target-mismatch"),
        RenderFault("omit-add-column-default", renderer,
                    "$default = ' DEFAULT ' . $expressions->expression($operand, null);", "$default = '';",
                    addition(default=["integer", "8"]), "meaning-mismatch"),
        RenderFault("omit-add-column-not-null", renderer, "$type . $constraint . $default", "$type . $default",
                    addition(default=["integer", "1"], policy="FAIL"), "meaning-mismatch"),
        RenderFault("omit-add-column-collation", renderer, "$default . $collation . $generation", "$default . $generation",
                    addition(kind="TEXT", default=["text", "A"], collation="NOCASE"), "meaning-mismatch"),
        RenderFault("fold-observable-exponent-marker", literal, "($this->uppercaseExponent ? 'E' : 'e')", "'e'",
                    addition(kind="TEXT", default=["real", "1", None, "2", False, True]), "meaning-mismatch"),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("add-column", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, AddColumnDatabase)
