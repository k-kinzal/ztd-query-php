"""Compare a resolved SQLite DROP COLUMN with the ordered declaration and row model."""

import copy
import hashlib

from run import TOOL, digest, Failure, Worker, compare, require
from run_sqlite_add_column import CATALOG, FIXTURE
from run_sqlite_create_table import quoted
from run_sqlite_unique_keys import values_sql
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_drop_column_cases import (
    DEPENDENT, FIELDS, SCHEMAS, case, definitions, expected, finite_id, finite_ids, forms, input_rows,
    malformed_generated, malformed_protected, negatives, requests, required,
)
from sqlite_engine import Database, ObserverAuthorizationError, typed
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-drop-column-v4.json"
CONTRACT = "4dd63d8c4bcd8925a0a8e0caa5600b11a8618436163a7533a8ee8220fe515319"
FUNCTIONS = frozenset((b"like", b"sqlite_rename_test", b"sqlite_rename_quotefix", b"sqlite_drop_column"))
CATALOG_FIELDS = frozenset((b"name", b"type", b"sql", b"tbl_name"))
INVALID = "SqlSemantics\\Platform\\Sqlite\\Model\\Diagnostic\\InvalidConstruction"


class DropDatabase(Database):
    """Limit the ALTER to the measured target and the native schema rewrite calls."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library):
        super().__init__(library)
        self.target = None
        self.authorized = False

    def authorize(self, _, action, first, second, schema, origin):
        target_schema, table, member = self.target
        if origin is None:
            if action == 26 and first == target_schema and second is not None and second.lower() == table and schema is not None and schema.lower() == member:
                self.authorized = True
                return 0
            if self.authorized:
                if action == 21 and first is None and second is None and schema is None:
                    return 0
                if action == 31 and first is None and second in FUNCTIONS and schema is None:
                    return 0
                affected = schema == target_schema or (target_schema == b"main" and schema == b"temp")
                if affected and first == CATALOG.get(schema):
                    if (action == 20 and second in CATALOG_FIELDS) or (action == 23 and second == b"sql"):
                        return 0
        if self.denied is None:
            self.denied = (action, [value.decode(errors="replace") if value is not None else None
                                    for value in (first, second, schema, origin)])
        return 1

    def drop(self, sql, item):
        member = item["heading"][item["slot"]][0]
        self.target = (item["schema"].encode(), item["table"].encode().lower(), member.encode().lower())
        try:
            result = self.observe(sql, {}, capture_evaluation_error=True)
            require(self.authorized, "sqlite-drop-column-no-authorized-operation", sql)
            return result
        except ObserverAuthorizationError as error:
            if error.action == 26:
                raise Failure("sqlite-drop-column-target-mismatch", error.arguments) from error
            raise
        finally:
            self.target = None
            self.authorized = False

    def read(self, sql, bound=None):
        result = self._observe(sql, {} if bound is None else bound, preserve_order=True)
        require(result["readOnly"] and result["changes"] == 0, "sqlite-drop-column-observer-read", result)
        return result


def setup(db, item):
    target = f"{item['schema']}.{quoted(item['table'])}"
    db.execute(FIXTURE)
    db.execute("; ".join(definitions(item)))
    selected = item["schema"]
    sibling = next(schema for schema in SCHEMAS if schema != selected)
    db.execute(f"INSERT INTO {sibling}.{quoted(item['table'])} VALUES (73, 'shadow')")
    rows = input_rows(item)
    if rows:
        available = [ordinal for ordinal, member in enumerate(item["heading"]) if not member[1]]
        names = [quoted(item["heading"][ordinal][0]) for ordinal in available]
        projected = [tuple(row[slot] for slot in available) for row in rows]
        if item["topology"] == "rowid":
            names.insert(0, "_rowid_")
            projected = [(20 + i, *row) for i, row in enumerate(projected)]
        db.execute(f"INSERT INTO {target}({', '.join(names)}) VALUES {values_sql(projected)}")
    dependency = item["dependency"]
    if dependency == "blocked-view":
        name = quoted(item["heading"][item["slot"]][0])
        table = quoted(item["table"])
        db.execute(f"CREATE VIEW {selected}.{DEPENDENT} AS SELECT {table}.{name} FROM {table}")
    elif dependency != "none":
        slot = item["slot"] if dependency == "blocked-index" else item["retainedIndexSlot"]
        name = quoted(item["heading"][slot][0])
        db.execute(f"CREATE INDEX {selected}.{DEPENDENT} ON {quoted(item['table'])}({name})")


def dependency_state(db, schema):
    value = db.read(f"SELECT type FROM {schema}.sqlite_schema WHERE name=?1", {"1": DEPENDENT})["rows"]
    if not value:
        return None
    require(value in ([[typed("view")]], [[typed("index")]]), "sqlite-drop-column-unknown-dependency", value)
    role = value[0][0][1]
    slot = None
    if role == "index":
        index = db.read(f"PRAGMA {schema}.index_info({DEPENDENT})")["rows"]
        require(len(index) == 1 and index[0][1][0] == "integer", "sqlite-drop-column-index-observation", index)
        slot = int(index[0][1][1])
    return {"name": DEPENDENT, "kind": role, "column": slot}


def guards(db, target, columns):
    if len(columns) >= 1999:
        return None
    writable = [slot for slot, column in enumerate(columns) if column[6] == typed(0)]
    names = ", ".join(quoted(columns[ordinal][1][1]) for ordinal in writable)
    receipts = [None] * len(columns)
    for slot in writable:
        inputs = tuple(-1 if candidate == slot else 140 + candidate for candidate in writable)
        db.execute("SAVEPOINT distinguish_check")
        try:
            receipts[slot] = db._observe(f"INSERT INTO {target}({names}) VALUES {values_sql((inputs,))}", {},
                                        capture_evaluation_error=True)
        finally:
            db.execute("ROLLBACK TO distinguish_check; RELEASE distinguish_check")
    return receipts


def snapshot(db, item):
    target = item["table"]
    table = quoted(target)
    picture = {}
    for schema in SCHEMAS:
        absolute = f"{schema}.{table}"
        root = db.read(f"SELECT type, name, tbl_name, rootpage FROM {schema}.sqlite_schema WHERE name=?1", {"1": target})["rows"]
        shape = db.read(f"PRAGMA {schema}.table_list({table})")["rows"]
        require(len(root) == len(shape) == 1 and shape[0][2] == typed("table"),
                "sqlite-drop-column-observer-table-identity", {"root": root, "shape": shape})
        columns = db.metadata("table_xinfo", schema=schema, table=target)
        without = shape[0][4] == typed(1)
        order = quoted(columns[0][1][1]) if without else "_rowid_"
        data = db.read(f"SELECT * FROM {absolute} ORDER BY {order}")
        rowid = None if without else db.read(f"SELECT rowid, _rowid_, oid FROM {absolute} ORDER BY {order}")["rows"]
        picture[schema] = {"root": root[0], "fields": data["names"], "rows": data["rows"], "aliases": rowid,
                           "strict": shape[0][5] == typed(1), "withoutRowid": without,
                           "columns": [[*col[:4], col[4] != typed(None), *col[5:]] for col in columns],
                           "guards": guards(db, absolute, columns), "dependency": dependency_state(db, schema),
                           "integrity": db.read(f"PRAGMA {schema}.integrity_check")["rows"]}
    return picture


def write(db, item, before, after):
    schema = item["schema"]
    target = f"{schema}.{quoted(item['table'])}"
    original = before[schema]["fields"]
    resulting = after[schema]
    names, values = [], []
    for column in resulting["columns"]:
        if column[6] == typed(0):
            label = column[1][1]
            names.append(quoted(label))
            values.append(97 + original.index(label))
    if not resulting["withoutRowid"] and all(column[5] == typed(0) for column in resulting["columns"]):
        names.insert(0, "_rowid_")
        values.insert(0, 97)
    receipt = db._observe(f"INSERT INTO {target}({', '.join(names)}) VALUES {values_sql((tuple(values),))}", {},
                          capture_evaluation_error=True)
    return {"result": receipt, "state": snapshot(db, item)}


def observe(template, item, sql):
    db = DropDatabase(template.library)
    try:
        setup(db, item)
        before = snapshot(db, item)
        db.execute("BEGIN")
        result = db.drop(sql, item)
        after = snapshot(db, item)
        subsequent = write(db, item, before, after) if result["evaluationError"] is None else None
        active = not bool(db.get_autocommit(db.db))
        if active:
            db.execute("ROLLBACK")
        return {"before": before, "statement": result, "after": after, "write": subsequent, "transaction": active,
                "rolledBack": snapshot(db, item)}
    finally:
        db.close(db.db)


def matches_drop_value(item, reply):
    return (all(reply.get(field) == item[field] for field in FIELDS)
            and reply.get("column") == item["heading"][item["slot"]][:2])


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and matches_drop_value(item, reply),
            "sqlite-drop-column-structure", {"expected": item, "actual": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    results = {"observation": observe(db, item, reply["sql"])}
    if original is not None:
        results["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **results)
    target = expected(item)
    for actual in results.values():
        compare(target, actual)
    if ledger is not None:
        ledger.record(key, "compared")
    return results["observation"]


def state_controls(comparator, target, witnesses):
    outcomes = {}
    for name, changed in witnesses.items():
        try:
            comparator(target, changed)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-drop-column-comparator-signal", error.detail)
            outcomes[name] = error.signal
        else:
            raise Failure("sqlite-drop-column-comparator-accepted-state-change", name)
    return outcomes


def controls(db):
    item = case(definition="surviving-generated")
    observed = observe(db, item, "ALTER TABLE main.ir_drop DROP COLUMN old")
    prediction = expected(item)
    compare(prediction, observed)
    storage = copy.deepcopy(observed)
    storage["after"]["main"]["columns"][-1][-1] = typed(2)
    row = copy.deepcopy(observed)
    row["after"]["main"]["rows"][0][-1] = typed(777)
    witnesses = {"erased-stored-generated-kind": storage, "erased-retained-computed-row": row}
    healthy = state_controls(compare, prediction, witnesses)

    def ignore_final_catalog(expected, actual):
        mask = lambda payload: {**payload, "after": {schema: {key: value for key, value in state.items() if key != "columns"}
                                                     for schema, state in payload["after"].items()}}
        compare(mask(expected), mask(actual))

    try:
        state_controls(ignore_final_catalog, prediction, witnesses)
    except Failure as error:
        require(error.signal == "sqlite-drop-column-comparator-accepted-state-change" and error.detail == "erased-stored-generated-kind",
                "sqlite-drop-column-wrong-injected-comparator-signal", error.detail)
        return {"original": observed, "witnesses": witnesses, "healthy": healthy,
                "injectedComparator": {"expected": error.signal, "observed": error.signal, "case": error.detail,
                                       "change": "Remove the compared columns from the after state."}}
    raise Failure("sqlite-drop-column-unobserved-comparator-injection")


def execute(args, specification, db, worker_factory=Worker):
    require(specification["extends"] == {"sqlite-drop-column-v3.json": "ec3c4a2625f4ecca94d5b40bbbf25ed6394295f4e4f38c69167cab66e78a1fac"},
            "sqlite-drop-column-prior-contract-pointer")
    for version, expected_digest in specification["extends"].items():
        require(digest(TOOL / version) == expected_digest, "sqlite-drop-column-prior-contract-digest", version)
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, lambda: controls(db))
    return execute_schedule(args, specification, db, worker_factory, schedule, probe,
                            measured_input=lambda reply, observation: (reply, observation))


def shape_fault(item, reply, db):
    if "boundary" not in item:
        probe(item, reply, db)
        return
    if reply["status"] == "constructed":
        require(matches_drop_value(item, reply) and "sql" not in reply,
                "sqlite-drop-column-unexpected-value", reply)
        raise Failure("sqlite-drop-column-construction-escaped", reply)
    require(reply["status"] == "rejected" and reply["exception"] == INVALID and item["boundary"] in reply["message"],
            "sqlite-drop-column-fault-wrong-rejection", reply)


def check_faults(args, db, runner=check_render_faults):
    base = "sql-semantics-sqlite/src/Ir/Schema/Alter/"
    root = base + "DropColumn.php"
    lower = base + "DropColumnLowering.php"
    protected = "declaration-local removal restriction"
    direct = {**malformed_protected(), "boundary": protected, "probe": "drop-column-construction"}
    only_ordinary = {**malformed_generated(), "boundary": "must retain an ordinary column", "probe": "drop-column-construction"}
    dependency = {**case(), "boundary": protected, "probe": "drop-column-construction"}
    return runner(args, db, [
        RenderFault("omit-declaration-local-drop-restriction", root, "!$members[$slot]->protected", "true",
                    direct, "sqlite-drop-column-construction-escaped"),
        RenderFault("allow-dropping-last-ordinary-member", root, "Check::input($ordinary,", "Check::input(true,",
                    only_ordinary, "sqlite-drop-column-construction-escaped"),
        RenderFault("drop-adjacent-column", root, "$this->column->quoted()", "'\"amount\"'",
                    case(), "sqlite-drop-column-target-mismatch"),
        RenderFault("drop-column-in-shadow-schema", root, "$this->schema->quoted()", "'temp'",
                    case(sibling=("id", "old")), "sqlite-drop-column-target-mismatch"),
        RenderFault("forget-bound-drop-restrictions", lower, "isset($restrictions->protected[$ordinal])", "$column->name->value === 'id'",
                    {**dependency, "heading": [["id", False, True], ["old", False, False], ["amount", False, False], ["total", False, False]]},
                    "sqlite-drop-column-construction-escaped",
                    request={"sql": "ALTER TABLE main.ir_drop DROP old", "probe": "drop-column-construction",
                             "declarations": ["CREATE TABLE main.ir_drop (id INTEGER PRIMARY KEY, old INTEGER, amount INTEGER, total INTEGER, CHECK (old>0))"]}),
    ], shape_fault)


if __name__ == "__main__":
    run_domain("drop-column", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, DropDatabase)
