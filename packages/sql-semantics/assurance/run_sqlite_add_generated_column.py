"""Compare generated column additions and dependency writes against SQLite."""

import copy
import hashlib

from run import Failure, Worker, compare, require
from run_sqlite_add_column import AddColumnDatabase, FIXTURE as ADD_FIXTURE
from run_sqlite_create_table import quoted
from run_sqlite_unique_keys import values_sql
from sqlite_add_column_cases import SCHEMAS, TARGET_FIELDS, binding_projection, check_binding_contract
from sqlite_add_generated_column_cases import (
    declarations, expected, finite_id, finite_ids, forms, input_rows, item, negatives, requests, required, table_topology,
    write_values,
)
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import ObserverAuthorizationError, typed
from sqlite_generated_column_cases import column
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-add-generated-column-v2.json"
CONTRACT = "a4cefb6be815783ddecb77b0bdde88f864e4f3a4f71911431b81233a848fef27"
FIXTURE = ADD_FIXTURE


class GeneratedAddDatabase(AddColumnDatabase):
    """Allow the native addition to read explicitly declared input slots of its target."""

    policy_digest = hashlib.sha256(f"{AddColumnDatabase.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library):
        super().__init__(library)
        self.fields = frozenset()

    def authorize(self, context, action, first, second, schema, origin):
        if self.authorized and action == 20 and origin is None:
            expected_schema, expected_table = self.target
            if schema == expected_schema and first == expected_table and second in self.fields:
                return 0
        return super().authorize(context, action, first, second, schema, origin)

    def append(self, case, statement):
        self.fields = frozenset(name.encode() for name in case["priorHeading"])
        try:
            return self.add(statement, case["schema"], case["table"])
        finally:
            self.fields = frozenset()


def writable_names(case, schema):
    return [quoted(name) for slot, name in enumerate(case["priorHeading"])
            if slot != 2 or table_topology(case, schema) != "prior-generated"]


def setup(db, case):
    table = quoted(case["table"])
    db.execute(FIXTURE)
    db.execute("; ".join(declarations(case)))
    for schema in SCHEMAS:
        rows = input_rows(case, schema)
        if rows:
            columns = ", ".join(writable_names(case, schema))
            db.execute(f"INSERT INTO {schema}.{table}({columns}) VALUES {values_sql(rows)}")


def snapshot(db, case):
    table_name = case["table"]
    table = quoted(table_name)
    ordinal = quoted(case["priorHeading"][0])
    member = case["column"][0]
    response = {}
    for schema in SCHEMAS:
        shape = db.read(f"PRAGMA {schema}.table_list({table})")["rows"]
        details = db.read(f"SELECT type, name, tbl_name, rootpage FROM {schema}.sqlite_schema WHERE name=?1",
                          {"1": table_name})["rows"]
        require(len(details) == 1 and len(shape) == 1, "sqlite-add-generated-missing-table", {"details": details, "shape": shape})
        fields = db.metadata("table_xinfo", schema=schema, table=table_name)
        catalog = [[*field[:4], field[4] != typed(None), *field[5:]] for field in fields]
        data = db.read(f"SELECT * FROM {schema}.{table} ORDER BY {ordinal}")
        equals = None
        if schema == case["schema"] and member in data["names"]:
            name = quoted(member)
            equals = db.read(f"SELECT {ordinal}, {name}='a', {name}='A' FROM {schema}.{table} ORDER BY {ordinal}")["rows"]
        response[schema] = {"root": details[0], "strict": shape[0][5] == typed(1), "noRowid": shape[0][4] == typed(1),
                            "fields": data["names"], "columns": catalog, "rows": data["rows"], "equals": equals}
    return response


def writes(db, case):
    schema = case["schema"]
    table = f"{schema}.{quoted(case['table'])}"
    prior = ", ".join(writable_names(case, schema))
    ordinal, operand, *_ = map(quoted, case["priorHeading"])
    member = quoted(case["column"][0])
    db.execute("BEGIN")
    inserted = db._observe(f"INSERT INTO {table}({prior}) VALUES {values_sql(write_values(case))}", {}, capture_evaluation_error=True)
    updated = db._observe(f"UPDATE {table} SET {operand}=20 WHERE {ordinal}=91", {}, capture_evaluation_error=True)
    refused = None
    try:
        db._observe(f"INSERT INTO {table}({ordinal}, {operand}, {member}) VALUES (94, 30, 11)", {}, capture_evaluation_error=True)
    except RuntimeError as cause:
        refused = str(cause)
    rows = snapshot(db, case)
    active = not bool(db.get_autocommit(db.db))
    if active:
        db.execute("ROLLBACK")
    return {"insert": inserted, "update": updated, "active": active, "rows": rows, "explicit": refused,
            "rolledBack": snapshot(db, case)}


def observe(template, case, sql):
    db = GeneratedAddDatabase(template.library)
    try:
        setup(db, case)
        before = snapshot(db, case)
        db.execute("BEGIN")
        try:
            actual = db.append(case, sql)
        except ObserverAuthorizationError as exc:
            if exc.action == 26:
                raise Failure("sqlite-add-generated-target-mismatch", exc.arguments) from exc
            raise
        after = snapshot(db, case)
        active = not bool(db.get_autocommit(db.db))
        if active:
            db.execute("ROLLBACK")
        restored = snapshot(db, case)
        written = None
        if actual["evaluationError"] is None:
            require(db.append(case, sql) == actual, "sqlite-add-generated-repeat-effect")
            written = writes(db, case)
        return {"before": before, "statement": actual, "after": after, "rolledBack": restored,
                "transaction": active, "writes": written}
    finally:
        db.close(db.db)


def probe(case, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and all(reply.get(field) == case[field] for field in TARGET_FIELDS)
            and (reply.get("slot"), reply.get("bindings")) == binding_projection(case),
            "sqlite-add-generated-structure-mismatch", reply)
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    result = {"observation": observe(db, case, reply["sql"])}
    if original is not None:
        result["original"] = observe(db, case, original)
    if ledger is not None:
        ledger.record(key, "observed", **result)
    prediction = expected(case)
    for actual in result.values():
        compare(prediction, actual)
    if ledger is not None:
        ledger.record(key, "compared")
    return result["observation"]


def controls(db):
    case = item(expression=column(1), policy="FAIL")
    sql = "ALTER TABLE main.ir_append ADD computed INTEGER AS (operand) VIRTUAL NOT NULL ON CONFLICT FAIL"
    prediction = expected(case)
    observation = observe(db, case, sql)
    compare(prediction, observation)
    manipulated = copy.deepcopy(observation)
    manipulated["writes"]["rows"]["main"]["rows"][-1][-1] = typed(9)
    try:
        compare(prediction, manipulated)
    except Failure as exc:
        require(exc.signal == "meaning-mismatch", "sqlite-add-generated-invalid-comparator-signal", exc.detail)
        return {"normal": observation, "staleGeneratedValue": {"signal": exc.signal, "witness": manipulated}}
    raise Failure("sqlite-add-generated-comparator-accepted-stale-input")


def execute(args, specification, db, worker_factory=Worker):
    check_binding_contract(specification)
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, lambda: controls(db))
    return execute_schedule(args, specification, db, worker_factory, schedule, probe,
                            measured_input=lambda reply, observation: (reply, observation))


def fault_probe(case, reply, db):
    if case.get("boundary") == "outside-heading":
        if reply["status"] == "constructed":
            require(all(reply.get(field) == case[field] for field in TARGET_FIELDS)
                    and (reply.get("slot"), reply.get("bindings")) == binding_projection(case) and "sql" not in reply,
                    "sqlite-add-generated-unexpected-mutant-value", reply)
            raise Failure("sqlite-add-generated-scope-escape", reply)
        expected = "SqlSemantics\\Platform\\Sqlite\\Model\\Diagnostic\\InvalidConstruction"
        require(reply["status"] == "rejected" and reply["exception"] == expected and "visible occurrence" in reply["message"],
                "sqlite-add-generated-unexpected-rejection", reply)
    else:
        probe(case, reply, db)


def check_faults(args, db, runner=check_render_faults):
    base = "sql-semantics-sqlite/src/Ir/"
    generated = base + "Schema/Alter/AddGeneratedColumn.php"
    renderer = base + "Schema/TableDeclarationSql.php"
    return runner(args, db, [
        RenderFault("allow-added-slot-as-own-input", generated,
                    "$inputs = (new ExpressionScope())->check([count($heading)],",
                    "$inputs = (new ExpressionScope())->check([count($heading) + 1],",
                    {**item(expression=column(2)), "boundary": "outside-heading", "probe": "add-generated-construction"},
                    "sqlite-add-generated-scope-escape"),
        RenderFault("redirect-generated-alter-to-temp", generated, "$this->schema->quoted()", "'temp'",
                    item(), "sqlite-add-generated-target-mismatch"),
        RenderFault("persist-virtual-generated-column", renderer, "$generated->mode->value", "'STORED'",
                    item(), "meaning-mismatch"),
        RenderFault("reverse-generated-input-bindings", renderer, "new DefinitionSource($bindings)",
                    "new DefinitionSource([0 => new Identifier('operand')] + $bindings)",
                    item(expression=column(0)), "meaning-mismatch"),
        RenderFault("drop-generated-null-policy", renderer, "$type . $constraint . $default", "$type . $default",
                    item(expression=column(1), policy="FAIL"), "meaning-mismatch"),
        RenderFault("fold-away-generated-registrations", base + "SelectSql.php", "if ($expression->bindings === []) {", "if (true) {",
                    item(expression=["folded-membership", True, ["09", "001", "009"]]), "meaning-mismatch"),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("add-generated-column", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, GeneratedAddDatabase)
