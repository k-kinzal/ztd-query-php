"""Compare checked SQLite column additions and subsequent writes with the pinned engine."""

import copy
import hashlib

from run import Failure, Worker, compare, require
from run_sqlite_add_column import FIXTURE
from run_sqlite_add_generated_column import GeneratedAddDatabase
from run_sqlite_create_table import quoted
from run_sqlite_unique_keys import values_sql
from sqlite_add_column_cases import binding_projection, check_binding_contract
from sqlite_appended_checks_cases import (
    FIELDS, SCHEMAS, before_rows, case, entry, expected, finite_id, finite_ids, forms, negatives,
    prior_definitions, requests, required, write_inputs,
)
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import ObserverAuthorizationError, typed
from sqlite_generated_column_cases import column, integer
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-appended-column-checks-v3.json"
CONTRACT = "8def6d35fcfb83cd4eeb8b569894d898a8f628b2a4f2605b99cb9b109fd50c02"


class CheckedAdditionDatabase(GeneratedAddDatabase):
    """Scope native reads to the target's prior and new operands during ALTER."""

    policy_digest = hashlib.sha256(f"{GeneratedAddDatabase.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def append(self, item, statement):
        self.fields = frozenset(part.encode() for part in (*item["priorHeading"], item["column"][0]))
        try:
            return self.add(statement, item["schema"], item["table"])
        finally:
            self.fields = frozenset()


def setup(db, item):
    table = quoted(item["table"])
    db.execute(FIXTURE)
    db.execute("; ".join(prior_definitions(item)))
    for schema in SCHEMAS:
        rows = before_rows(item, schema)
        if rows:
            db.execute(f"INSERT INTO {schema}.{table} VALUES {values_sql(rows)}")


def snapshot(db, item):
    name = item["table"]
    table = quoted(name)
    ordinal = quoted(item["priorHeading"][0])
    picture = {}
    for schema in SCHEMAS:
        details = db.read(f"SELECT type, name, tbl_name, rootpage FROM {schema}.sqlite_schema WHERE name=?1",
                          {"1": name})["rows"]
        catalog = db.read(f"PRAGMA {schema}.table_list({table})")["rows"]
        require(len(details) == len(catalog) == 1 and catalog[0][2] == typed("table"),
                "sqlite-appended-checks-catalog-identity", {"details": details, "catalog": catalog})
        fields = db.metadata("table_xinfo", schema=schema, table=name)
        columns = [[*field[:4], field[4] != typed(None), *field[5:]] for field in fields]
        data = db.read(f"SELECT * FROM {schema}.{table} ORDER BY {ordinal}")
        picture[schema] = {"root": details[0], "strict": catalog[0][5] == typed(1),
                           "fields": data["names"], "columns": columns, "rows": data["rows"]}
    return picture


def writes(db, item):
    target = f"{item['schema']}.{quoted(item['table'])}"
    prior = tuple(map(quoted, item["priorHeading"]))
    new_member = quoted(item["column"][0])
    statements = {}
    db.execute("BEGIN")
    for name, row, update, explicit, supplied in write_inputs(item):
        if update:
            sql = f"UPDATE {target} SET {prior[1]}={row[1]} WHERE {prior[0]}={row[0]}"
        else:
            columns = (*prior, new_member) if supplied else prior
            values = (*row, explicit) if supplied else row
            sql = f"INSERT INTO {target}({', '.join(columns)}) VALUES {values_sql((values,))}"
        statements[name] = db._observe(sql, {}, capture_evaluation_error=True)
    active = not bool(db.get_autocommit(db.db))
    visible = snapshot(db, item)
    if active:
        db.execute("ROLLBACK")
    return {"transaction": active, "statements": statements, "rows": visible, "rolledBack": snapshot(db, item)}


def observe(template, item, sql, renamed=None):
    db = CheckedAdditionDatabase(template.library)
    try:
        setup(db, item)
        before = snapshot(db, item)
        db.execute("BEGIN")
        try:
            statement = db.append(item, sql)
        except ObserverAuthorizationError as exc:
            if exc.action == 26:
                raise Failure("sqlite-appended-checks-target-mismatch", exc.arguments) from exc
            raise
        active = not bool(db.get_autocommit(db.db))
        after = snapshot(db, item)
        if active:
            db.execute("ROLLBACK")
        restored = snapshot(db, item)
        subsequent = None
        if statement["evaluationError"] is None:
            require(db.append(item, sql) == statement, "sqlite-appended-checks-repeat-effect")
            if renamed is None:
                subsequent = writes(db, item)
            else:
                subsequent = writes(db, rename(db, item, renamed))
        return {"before": before, "statement": statement, "after": after, "rolledBack": restored,
                "transaction": active, "writes": subsequent}
    finally:
        db.close(db.db)


def rename(db, item, change):
    slot, destination, delimited = change
    names = [*item["priorHeading"], item["column"][0]]
    source = quoted(names[slot])
    target = f"{item['schema']}.{quoted(item['table'])}"
    db.execute(f"ALTER TABLE {target} RENAME COLUMN {source} TO {quoted(destination) if delimited else destination}")
    names[slot] = destination
    return {**item, "priorHeading": names[:-1], "column": [names[-1], *item["column"][1:]]}


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and all(reply.get(field) == item[field] for field in FIELDS)
            and (reply.get("slot"), reply.get("bindings")) == binding_projection(item),
            "sqlite-appended-checks-structure-mismatch", {"expected": item, "actual": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    results = {"observation": observe(db, item, reply["sql"])}
    if original is not None:
        results["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **results)
    prediction = expected(item)
    for actual in results.values():
        compare(prediction, actual)
    if ledger is not None:
        ledger.record(key, "compared")
    return results["observation"]


def controls(db):
    item = case(population="empty", checks=2)
    sql = """ALTER TABLE main.ir_add_checks ADD checked INTEGER DEFAULT 2
             CONSTRAINT "first constraint" CHECK (checked > operand)
             CONSTRAINT "second constraint" CHECK (operand < 8)"""
    prediction = expected(item)
    observation = observe(db, item, sql)
    compare(prediction, observation)
    swapped = copy.deepcopy(observation)
    swapped["writes"]["statements"]["insert-large"]["evaluationError"]["message"] = "CHECK constraint failed: second constraint"
    try:
        compare(prediction, swapped)
    except Failure as error:
        require(error.signal == "meaning-mismatch", "sqlite-appended-checks-comparator-signal", error.detail)
        return {"normal": observation, "wrongFailureLabel": {"signal": error.signal, "witness": swapped}}
    raise Failure("sqlite-appended-checks-comparator-accepted-wrong-failure-label")


def execute(args, specification, db, worker_factory=Worker):
    check_binding_contract(specification)
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, lambda: controls(db))
    return execute_schedule(args, specification, db, worker_factory, schedule, probe,
                            measured_input=lambda reply, observation: (reply, observation))


def fault_probe(item, reply, db):
    if item.get("boundary") != "wrong-owner":
        probe(item, reply, db)
        return
    if reply["status"] == "constructed":
        require(all(reply.get(field) == item[field] for field in FIELDS)
                    and (reply.get("slot"), reply.get("bindings")) == binding_projection(item) and "sql" not in reply,
                "sqlite-appended-checks-unexpected-mutant-value", reply)
        raise Failure("sqlite-appended-checks-owner-escape", reply)
    require(reply["status"] == "rejected" and reply["exception"] ==
            "SqlSemantics\\Platform\\Sqlite\\Model\\Diagnostic\\InvalidConstruction"
            and "belong to the new column slot" in reply["message"], "sqlite-appended-checks-unexpected-rejection", reply)


def check_faults(args, db, runner=check_render_faults):
    base = "sql-semantics-sqlite/src/Ir/"
    renderer = base + "Schema/TableDeclarationSql.php"
    guard = base + "Schema/Alter/AppendColumnBounds.php"
    iteration = "foreach ($conditions as $check) {\n            $definition .="
    return runner(args, db, [
        RenderFault("omit-appended-check-owner", guard, "$check->column === $owner", "true",
                    {**case(), "checks": [entry("wrong owner", None, integer(1))],
                     "probe": "appended-check-construction", "boundary": "wrong-owner"},
                    "sqlite-appended-checks-owner-escape"),
        RenderFault("reverse-appended-check-inputs", renderer, "new DefinitionSource($bindings)",
                    "new DefinitionSource([1 => new Identifier('id')] + $bindings)",
                    case(population="below", predicate="old-positive"), "meaning-mismatch"),
        RenderFault("omit-first-appended-check", renderer, iteration,
                    "foreach (array_slice($conditions, 1) as $check) {\n            $definition .=",
                    case(population="mixed", checks=2), "meaning-mismatch"),
        RenderFault("omit-later-appended-check", renderer, iteration,
                    "foreach (array_slice($conditions, 0, 1) as $check) {\n            $definition .=",
                    case(population="empty", predicate="new-positive", checks=2), "meaning-mismatch"),
        RenderFault("rewrite-appended-check-label", renderer, "$check->label->quoted()", "'\"changed\"'",
                    case(population="empty"), "meaning-mismatch"),
        RenderFault("erase-appended-check-registrations", base + "SelectSql.php", "if ($expression->bindings === []) {",
                    "if (true) {", case(predicate="folded-registered"), "meaning-mismatch"),
    ], fault_probe)


if __name__ == "__main__":
    run_domain("appended-checks", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, CheckedAdditionDatabase)
