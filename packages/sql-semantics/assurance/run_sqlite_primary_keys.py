"""Compare primary-key declarations with independently modeled SQLite effects."""

import hashlib

from run import Failure, Worker, compare, require
from run_sqlite_create_table import quoted
from run_sqlite_defaults import semantic
from run_sqlite_strict_tables import StrictDatabase, literal
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import ObserverAuthorizationError
from sqlite_primary_keys_cases import (
    FIXTURE, LAYOUTS, declaration, effective_terms, expected, finite_id, finite_ids, fixture_rows, mode_of, negatives, requests,
)
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-primary-keys-v2.json"
CONTRACT = "b5ed249813c2bf4d36b971033e76bad276ad617b0cea5cc6c9cbcfc518e3a51b"
MARKER = "main.ir_key_marker"
TRANSACTIONS = (False, True)


class KeyDatabase(StrictDatabase):
    """Allows an implicit primary key autoindex only during its table's creation."""

    policy_digest = hashlib.sha256(f"{StrictDatabase.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library):
        super().__init__(library)
        self.implicit_index = None

    def authorize(self, context, action, first, second, schema, origin):
        if self.implicit_index is not None and origin is None and (action, first, second, schema) == self.implicit_index:
            return 0
        return super().authorize(context, action, first, second, schema, origin)

    def create(self, sql, schema, table):
        require(schema in ("main", "temp"), "sqlite-primary-key-invalid-schema", schema)
        index = "sqlite_autoindex_" + table + "_1"
        self.implicit_index = (1 if schema == "main" else 3, index.encode(), table.encode(), schema.encode())
        try:
            return self.observe(sql, {}, capture_evaluation_error=True)
        finally:
            self.implicit_index = None

    def key_index(self, schema, table):
        require(schema in ("main", "temp"), "sqlite-primary-key-invalid-schema", schema)
        name = quoted("sqlite_autoindex_" + table + "_1")
        return self._observe(f"PRAGMA {schema}.index_xinfo({name})", {}, preserve_order=True)["rows"]


def arguments(values):
    return "(" + ", ".join(map(literal, values)) + ")"


def measure_write(db, statement):
    effect = db.observe(statement, {}, capture_evaluation_error=True)
    return {"changes": db.changes(db.db), "error": effect["evaluationError"]}


def measure_state(db, target, columns):
    fields = ", ".join(map(quoted, columns))
    ordering = f" FROM {target} ORDER BY {quoted(columns[-1])}"
    try:
        state = db.observe(f"SELECT rowid, {fields}" + ordering, {}, preserve_order=True)
        marker = "available"
    except RuntimeError as error:
        require(type(error) is RuntimeError and str(error) == "SQLite 1: no such column: rowid",
                "unexpected-rowid-observation-failure", str(error))
        marker = str(error)
        state = db.observe("SELECT " + fields + ordering, {}, preserve_order=True)
    return {"names": state["names"], "rows": state["rows"], "rowid": marker}


def observe(db, item, sql, explicit):
    schema, table = item["schema"], item["table"]
    columns = [field[0] for field in item["columns"]]
    target = f"{schema}.{quoted(table)}"
    db.execute(f"DELETE FROM {MARKER}")
    created = False
    try:
        creation = db.create(sql, schema, table)
        require(not creation["readOnly"] and creation["changes"] == 0 and creation["evaluationError"] is None,
                "sqlite-primary-key-create-failed", creation)
        created = True
        catalog = {"table": db.table_list(schema, table),
                   "columns": db.metadata("table_xinfo", schema=schema, table=table),
                   "index": db.key_index(schema, table)}
        require(bool(catalog["columns"]), "sqlite-primary-key-missing-table", target)
        base, first, duplicate, last, nulls, omitted = fixture_rows(item)
        db.observe(f"INSERT INTO {target} VALUES {arguments(base)}", {})
        null = measure_write(db, f"INSERT INTO {target} VALUES {arguments(nulls)}")
        active = effective_terms(item)[0][0]
        included = [quoted(column) for slot, column in enumerate(columns) if slot != active]
        values = [value for slot, value in enumerate(omitted) if slot != active]
        omitted_sql = ("(" + ", ".join(included) + ") VALUES " + arguments(values)) if included else "DEFAULT VALUES"
        missed = measure_write(db, f"INSERT INTO {target} {omitted_sql}")
        if explicit:
            db.execute(f"BEGIN; INSERT INTO {MARKER} VALUES (73)")
        require(explicit == (not db.get_autocommit(db.db)), "sqlite-primary-key-probe-transaction")
        statement = ", ".join(map(arguments, (first, duplicate, last)))
        conflicts = measure_write(db, f"INSERT INTO {target} VALUES {statement}")
        return {"catalog": catalog, "state": measure_state(db, target, columns),
                "writes": {"null": null, "omitted": missed, "duplicate": conflicts},
                "active": not db.get_autocommit(db.db), "marker": db.observe(f"SELECT value FROM {MARKER}", {})["rows"]}
    finally:
        if not db.get_autocommit(db.db):
            db.execute("ROLLBACK")
        if created:
            db.execute(f"DROP TABLE IF EXISTS {target}")


def effects(observation):
    return {**observation, "catalog": semantic(observation["catalog"])}


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed", "sqlite-primary-key-positive-rejected", reply)
    roles = ("schema", "table", "columns", "strict", "withoutRowid", "primaryKey", "ifNotExists", "operation")
    require(all(reply[role] == item[role] for role in roles), "sqlite-primary-key-structure-mismatch",
            {"expected": item, "actual": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    results = {"observation": [observe(db, item, reply["sql"], explicit) for explicit in TRANSACTIONS]}
    if original is not None:
        results["original"] = [observe(db, item, original, explicit) for explicit in TRANSACTIONS]
    if ledger is not None:
        ledger.record(key, "observed", **results)
    expected_outcomes = [expected(item, explicit) for explicit in TRANSACTIONS]
    for observations in results.values():
        compare(expected_outcomes, [effects(observation) for observation in observations])
    if ledger is not None:
        ledger.record(key, "compared")


def forms(reply):
    key = reply["primaryKey"]
    yield "mode/" + mode_of(reply)
    yield "conflict/" + key["conflict"]
    yield "inline/" + str(key["inline"])
    yield "schema/" + reply["schema"]
    yield "width/" + str(len(reply["columns"]))
    yield "key-width/" + str(len(key["terms"]))
    for slot, descending, collation in key["terms"]:
        yield f"slot/{slot}"
        yield f"descending/{descending}"
        yield "collation/" + collation


def required(domain):
    return {
        *(f"mode/{mode}" for mode in domain["modes"]), *(f"conflict/{policy}" for policy in domain["conflicts"]),
        *(f"width/{width}" for width in range(2, 7)), *(f"slot/{slot}" for slot in range(5)),
        "key-width/1", "key-width/2", "key-width/3", "inline/True", "inline/False",
        "schema/main", "schema/temp", "descending/True", "descending/False",
        "collation/BINARY", "collation/NOCASE", "collation/RTRIM",
    }


def example(layout, mode="rowid", conflict="ABORT"):
    kind, inline, terms = LAYOUTS[layout]
    return declaration([["a", kind, False], ["b", "TEXT", False], ["stamp", "INTEGER", False]], terms, inline, mode, conflict)


def controls(db):
    item = example("table-pair-reversed", "without-rowid", "ROLLBACK")
    original = expected(item, True)
    altered = {
        "wrong-rowid": {**original, "state": {**original["state"], "rowid": "available"}},
        "unordered-key": {**original, "catalog": {**original["catalog"], "index": list(reversed(original["catalog"]["index"]))}},
        "lost-rollback": {**original, "active": True},
    }
    compare(original, original)
    for name, value in altered.items():
        try:
            compare(original, value)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-primary-key-control-signal", name)
        else:
            raise Failure("sqlite-primary-key-comparator-accepted", name)
    sql = f"CREATE INDEX forbidden_manual ON ir_key_marker (value)"
    try:
        db.observe(sql, {})
    except ObserverAuthorizationError as error:
        require(error.action == 1 and error.arguments[:2] == ["forbidden_manual", "ir_key_marker"],
                "sqlite-primary-key-index-control-signal", error.arguments)
    else:
        raise Failure("sqlite-primary-key-manual-index-accepted")
    return {"input": item, "expected": original, "rejected": altered, "observer": {"sql": sql, "deniedAction": 1}}


def execute(args, specification, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, lambda: controls(db))
    return execute_schedule(args, specification, db, worker_factory, schedule, probe)


def check_faults(args, db, runner=check_render_faults):
    key = "sql-semantics-sqlite/src/Ir/Schema/Key/TableKeySql.php"
    table = "sql-semantics-sqlite/src/Ir/Schema/CreateTable.php"
    return runner(args, db, [
        RenderFault("alias-inline-descending-integer", key, "($key->terms[0]->descending ? ' DESC' : '')", "''",
                    example("inline-integer-descending"), "meaning-mismatch"),
        RenderFault("read-key-slot-zero", key, "$column = $columns[$term->slot];", "$column = $columns[0];",
                    example("table-pair-reversed"), "meaning-mismatch"),
        RenderFault("restore-hidden-rowid", table, "' WITHOUT ROWID, STRICT' : ' WITHOUT ROWID'", "' STRICT' : ''",
                    example("table-integer-descending", "without-rowid"), "meaning-mismatch"),
        RenderFault("drop-key-collation", key, "' COLLATE ' . $term->collation->value", "''",
                    example("table-text-nocase"), "meaning-mismatch"),
        RenderFault("ignore-key-fail-policy", key, "' ON CONFLICT ' . $conflict->value", "''",
                    example("inline-int", conflict="FAIL"), "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("primary-keys", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, KeyDatabase)
