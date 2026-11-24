"""Compare computed column defaults in CREATE and ALTER with pinned SQLite."""

import copy
import hashlib

from run import Failure, Worker, compare, require
from run_sqlite_add_column import AddColumnDatabase, CATALOG, FIXTURE
from run_sqlite_create_table import quoted
from run_sqlite_unique_keys import values_sql
from sqlite_computed_defaults_cases import (
    BASE_NAMES, SCHEMAS, declarations, direct, expected, finite_id, finite_ids, forms, item, negatives, observed_fields,
    old_rows, requests, required, written,
)
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import typed
from sqlite_generated_column_cases import arithmetic, integer
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-computed-column-default-v3.json"
CONTRACT = "fdbe4b24fe5bb64a4a5d4a8509ff9f1ad083805e911f2e665f20c38301b1c512"
CATALOG_FIELDS = frozenset((b"type", b"name", b"tbl_name", b"rootpage", b"sql"))


class DefaultDatabase(AddColumnDatabase):
    """Permit catalog changes only for the selected ordinary table declaration."""

    policy_digest = hashlib.sha256(f"{AddColumnDatabase.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library):
        super().__init__(library)
        self.creation = None
        self.created = False

    def authorize(self, ctx, action, first, second, schema, origin):
        if self.creation is None:
            return super().authorize(ctx, action, first, second, schema, origin)
        namespace, table = self.creation
        if schema == namespace and origin is None:
            if action in (2, 4) and first == table and second is None:
                self.created = True
                return 0
            if not self.created and action == 18 and first == b"sqlite_master" and second is None:
                return 0
            if self.created and first == CATALOG[namespace]:
                if action == 23 and second in CATALOG_FIELDS or action == 20 and second == b"ROWID":
                    return 0
        if self.denied is None:
            self.denied = (action, [value.decode(errors="replace") if value is not None else None
                                    for value in (first, second, schema, origin)])
        return 1

    def apply(self, plan, sql):
        if plan["placement"] != "create":
            return self.add(sql, plan["schema"], plan["table"])
        self.creation = (plan["schema"].encode(), plan["table"].encode())
        try:
            result = self.observe(sql, {}, capture_evaluation_error=True)
            require(self.created, "sqlite-computed-default-create-not-observed", sql)
            return result
        finally:
            self.creation = None
            self.created = False


def setup(db, plan):
    db.execute(FIXTURE)
    table = quoted(plan["table"])
    db.execute("; ".join(declarations(plan)))
    for schema in SCHEMAS:
        rows = old_rows(plan, schema)
        if rows:
            db.execute(f"INSERT INTO {schema}.{table} VALUES {values_sql(rows)}")


def snapshot(db, plan):
    name = plan["table"]
    table, order = quoted(name), quoted(BASE_NAMES[0])
    state = {}
    for schema in SCHEMAS:
        details = db.read(f"SELECT type,name,tbl_name,rootpage FROM {schema}.sqlite_schema WHERE name=?1", {"1": name})["rows"]
        if not details:
            state[schema] = None
            continue
        modes = db.read(f"PRAGMA {schema}.table_list({table})")["rows"]
        require(len(details) == 1 and len(modes) == 1 and modes[0][2] == typed("table"),
                "sqlite-computed-default-catalog", {"roots": details, "modes": modes})
        native = db.metadata("table_xinfo", schema=schema, table=name)
        columns = [[*row[:4], row[4] != typed(None), *row[5:]] for row in native]
        rowset = db.read(f"SELECT * FROM {schema}.{table} ORDER BY {order}")
        state[schema] = {"root": details[0], "fields": rowset["names"], "strict": modes[0][5] == typed(1),
                         "columns": columns, "rows": rowset["rows"]}
    return state


def observe(template, plan, sql):
    db = DefaultDatabase(template.library)
    try:
        setup(db, plan)
        before = snapshot(db, plan)
        db.execute("BEGIN")
        result = db.apply(plan, sql)
        after = snapshot(db, plan)
        active = not bool(db.get_autocommit(db.db))
        if active:
            db.execute("ROLLBACK")
        rolled_back = snapshot(db, plan)
        observed_writes = None
        if result["evaluationError"] is None:
            require(db.apply(plan, sql) == result, "sqlite-computed-default-repeat")
            target = f"{plan['schema']}.{quoted(plan['table'])}"
            ordinal, field = quoted(BASE_NAMES[0]), quoted(plan["column"][0])
            omitted = db._observe(f"INSERT INTO {target}({ordinal}) VALUES (99)", {}, capture_evaluation_error=True)
            null = db._observe(f"INSERT INTO {target}({ordinal}, {field}) VALUES (101, NULL)", {}, capture_evaluation_error=True)
            observed_writes = {"omitted": omitted, "null": null, "rows": snapshot(db, plan)}
        return {"before": before, "statement": result, "after": after, "rolledBack": rolled_back,
                "transaction": active, "writes": observed_writes}
    finally:
        db.close(db.db)


def probe(plan, reply, db, ledger=None, key=None, original=None):
    source = direct(plan)
    require(reply["status"] == "constructed" and all(reply.get(field) == source[field]
            for field in ("schema", "table", "strict", "operation"))
            and observed_fields(reply) == plan["column"][:4], "sqlite-computed-default-structure-mismatch", reply)
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    observed = {"observation": observe(db, plan, reply["sql"])}
    if original is not None:
        observed["original"] = observe(db, plan, original)
    if ledger is not None:
        ledger.record(key, "observed", **observed)
    prediction = expected(plan)
    for result in observed.values():
        compare(prediction, result)
    if ledger is not None:
        ledger.record(key, "compared")
    return observed["observation"]


def controls(db):
    plan = item("alter-populated", kind="TEXT", expression=["cast", "INTEGER", ["null"]], policy="FAIL")
    native = observe(db, plan, written(plan, "CAST(NULL AS INTEGER)"))
    fixed = expected(plan)
    compare(fixed, native)
    invalid = copy.deepcopy(native)
    invalid["writes"]["rows"]["main"]["rows"][0][-1] = typed("0")
    try:
        compare(fixed, invalid)
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "sqlite-computed-default-control-signal", failure.detail)
        return {"healthy": native, "changedExistingValue": {"signal": failure.signal, "input": invalid}}
    raise Failure("sqlite-computed-default-comparator-accepted-changed-fallback")


def execute(args, specification, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, lambda: controls(db))
    return execute_schedule(args, specification, db, worker_factory, schedule, probe,
                            measured_input=lambda reply, observed: (reply, observed))


def fault_probe(plan, reply, db):
    if plan.get("boundary") == "unbound":
        if reply["status"] == "constructed":
            require(observed_fields(reply) == plan["column"][:4] and "sql" not in reply,
                    "sqlite-computed-default-unexpected-escaped-value", reply)
            raise Failure("sqlite-computed-default-unbound", reply)
        exact = "SqlSemantics\\Platform\\Sqlite\\Model\\Diagnostic\\InvalidConstruction"
        require(reply["status"] == "rejected" and reply["exception"] == exact and "visible occurrence" in reply["message"],
                "sqlite-computed-default-unexpected-boundary", reply)
    else:
        probe(plan, reply, db)


def check_faults(args, db, runner=check_render_faults):
    base = "sql-semantics-sqlite/src/Ir/"
    computed = base + "Schema/ColumnDefaultExpression.php"
    renderer = base + "Schema/TableDeclarationSql.php"
    select = base + "SelectSql.php"
    unbound = item("alter-empty", expression=arithmetic("+", ["column", 0], integer(3)))
    nested = arithmetic("-", integer(10), arithmetic("-", integer(7), integer(2)))
    binary = "$this->expression($expression->left, $source, $joins) . ' ' . $expression->operator->value . ' ' . $this->expression($expression->right, $source, $joins)"
    return runner(args, db, [
        RenderFault("allow-unbound-default", computed, "(new ExpressionScope())->check([], [[$expression, 0, 0]], false);", "",
                    {**unbound, "boundary": "unbound"}, "sqlite-computed-default-unbound",
                    {**direct(unbound), "probe": "computed-default-construction"}),
        RenderFault("omit-computed-column-default", renderer,
                    "' DEFAULT (' . $expressions->expression($operand->expression, null) . ')'", "''",
                    item("create"), "meaning-mismatch", direct(item("create"))),
        RenderFault("change-default-cast-target", select, "$expression->target->value", "'TEXT'",
                    item("create", kind="TEXT", expression=["cast", "INTEGER", ["text", "002"]]), "meaning-mismatch",
                    direct(item("create", kind="TEXT", expression=["cast", "INTEGER", ["text", "002"]]))),
        RenderFault("remove-default-arithmetic-grouping", select, "'(' . " + binary + " . ')'", binary,
                    item("alter-empty", expression=nested), "meaning-mismatch", direct(item("alter-empty", expression=nested))),
        RenderFault("erase-default-parameter-registrations", select, "if ($expression->bindings === []) {", "if (true) {",
                    item("create", expression=["folded-membership", True, ["09", "001", "009"]]), "meaning-mismatch",
                    direct(item("create", expression=["folded-membership", True, ["09", "001", "009"]]))),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("computed-defaults", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, DefaultDatabase)
