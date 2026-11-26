"""Verify SQLite rename identity, dependent schema effects and rollback."""

import copy
import ctypes
import hashlib

from run import Failure, Worker, compare, require
from run_sqlite_create_table import quoted
from run_sqlite_unique_keys import values_sql
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import Database, ObserverAuthorizationError
from sqlite_rename_cases import (
    CHILD, FIXTURE, INDEX, LOG, SCHEMAS, TRIGGER, VIEW, data, declarations, expected,
    finite_id, finite_ids, fixture_table, negatives, problem, rename, requests,
)
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-rename-v4.json"
CONTRACT = "10291e93677e2e133e691a175dc16a2e78039dccabc6eeefef8534402f148861"
CATALOG_NAMES = {b"main": b"sqlite_master", b"temp": b"sqlite_temp_master"}
CATALOG_FIELDS = frozenset({b"name", b"sql", b"tbl_name", b"type"})
RENAME_FUNCTIONS = frozenset({b"like", b"substr", b"sqlite_rename_table", b"sqlite_rename_column",
                             b"sqlite_rename_quotefix", b"sqlite_rename_test"})


class RenameDatabase(Database):
    """Admits the engine's catalog rewrite after a scoped ALTER authorization."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library):
        super().__init__(library)
        self.rename_target = None
        self.alter_authorized = False

    def authorize(self, _, action, first, second, schema, origin):
        if (action == 26 and self.rename_target is not None and first in CATALOG_NAMES
                and second == self.rename_target and schema is None and origin is None):
            self.alter_authorized = True
            return 0
        if self.alter_authorized and origin is None:
            if action == 21 and first is None and second is None and schema is None:
                return 0
            if action in (20, 23) and schema in CATALOG_NAMES and first == CATALOG_NAMES[schema] and second in CATALOG_FIELDS:
                return 0
            if action == 31 and first is None and second in RENAME_FUNCTIONS and schema is None:
                return 0
        if self.denied is None:
            self.denied = (action, [value.decode(errors="replace") if value is not None else None
                                    for value in (first, second, schema, origin)])
        return 1

    def alter(self, sql, table):
        self.rename_target = table.encode()
        try:
            return self.observe(sql, {}, capture_evaluation_error=True)
        finally:
            self.rename_target = None
            self.alter_authorized = False

    def read(self, sql):
        result = self._observe(sql, {}, preserve_order=True)
        require(result["readOnly"] and result["parameterCount"] == 0 and result["changes"] == 0,
                "sqlite-rename-observer-read-contract", result)
        return result


def stated_rows(db, schema, table):
    return db.read(f"SELECT * FROM {schema}.{quoted(table)} ORDER BY 1")


def setup(db, item):
    db.execute("; ".join(declarations(item)))
    table = quoted(item["table"])
    schema = item["schema"]
    for namespace in SCHEMAS:
        subject = quoted(fixture_table(item, namespace))
        db.execute(f"INSERT INTO {namespace}.{subject} VALUES {values_sql(data(namespace, len(item['columns'])))}")
    mode = item["topology"]
    first, second = map(quoted, item["columns"][:2])
    if mode in ("dependent", "shadow-trigger", "shadow-index"):
        db.execute(f"CREATE INDEX {schema}.{INDEX} ON {table}({second}); "
                   f"CREATE TABLE {schema}.{CHILD}(parent INTEGER REFERENCES {table}({first})); "
                   f"INSERT INTO {schema}.{CHILD} VALUES({data(schema, len(item['columns']))[0][0]});")
        if schema == "main":
            trigger_target = table if mode == "shadow-trigger" else f"main.{table}"
            db.execute(f"CREATE TABLE main.{LOG}(value INTEGER); "
                       f"CREATE VIEW main.{VIEW} AS SELECT {first} AS ordinal, {second} AS watched FROM main.{table}; "
                       f"CREATE TRIGGER main.{TRIGGER} AFTER INSERT ON {trigger_target} "
                       f"BEGIN INSERT INTO {LOG} VALUES(NEW.{second}); END;")
    elif mode == "ambiguous-view":
        db.execute(f"CREATE TABLE main.{LOG}(other INTEGER); INSERT INTO main.{LOG} VALUES(13); "
                   f"CREATE VIEW main.{VIEW} AS SELECT {first} AS ordinal, other AS watched FROM main.{table}, main.{LOG};")
    elif mode == "temp-view":
        db.execute(f"CREATE VIEW temp.{VIEW} AS SELECT c0 AS ordinal, c1 AS watched FROM temp.{table}")
    elif mode == "collision":
        db.execute(f"CREATE INDEX {schema}.{quoted(item['destination'])} ON {table}({second})")


def snapshot(db, item):
    table, destination = item["table"], item["destination"]
    schema = item["schema"]
    catalog = {}
    objects = {}
    for namespace in SCHEMAS:
        catalog[namespace] = db.read(f"SELECT type, name, tbl_name FROM {namespace}.sqlite_schema ORDER BY type, name")["rows"]
        tables = {entry[1][1] for entry in catalog[namespace] if entry[0] == ["text", "table"]}
        nodes = {}
        for name in (table, destination, fixture_table(item, namespace)):
            if name in tables:
                selected = stated_rows(db, namespace, name)
                nodes[name] = {"fields": selected["names"], "rows": selected["rows"],
                               "columns": db.metadata("table_xinfo", schema=namespace, table=name)}
            else:
                nodes[name] = None
        objects[namespace] = nodes
    columns = db.read(f"PRAGMA {schema}.index_info({quoted(INDEX)})")["rows"]
    foreign = db.read(f"PRAGMA {schema}.foreign_key_list({quoted(CHILD)})")["rows"]
    related = {entry[1][1] for entry in catalog[schema]}
    views = db.read(f"SELECT watched FROM {schema}.{VIEW} ORDER BY ordinal")["rows"] if VIEW in related else []
    logs = stated_rows(db, schema, LOG)["rows"] if TRIGGER in related else []
    children = stated_rows(db, schema, CHILD)["rows"] if CHILD in related else []
    return {"catalog": catalog, "subjects": objects, "dependencies": {
        "key": columns, "foreign": foreign, "view": views, "log": logs, "children": children,
    }}


def observe(db, item, sql):
    schema, table = item["schema"], item["table"]
    require(schema in SCHEMAS, "sqlite-rename-observer-schema", schema)
    db.execute("SAVEPOINT fixture")
    try:
        setup(db, item)
        before = snapshot(db, item)
        db.execute("SAVEPOINT algorithm")
        statement = message = None
        try:
            statement = db.alter(sql, table)
        except ObserverAuthorizationError:
            raise
        except RuntimeError as error:
            phase, diagnosis = problem(item)
            require(phase == "prepare" and str(error) == diagnosis, "sqlite-rename-unexpected-compile-result", str(error))
            message = str(error)
        after = snapshot(db, item)
        written = None
        selected = item["destination"] if item["column"] is None else table
        if statement is not None and statement["evaluationError"] is None and after["subjects"][schema].get(selected) is not None:
            row = (99, *(120 + slot for slot in range(1, len(item["columns"]))))
            db.execute(f"INSERT INTO {schema}.{quoted(selected)} VALUES {values_sql([row])}")
            if item["topology"] in ("dependent", "shadow-index", "shadow-trigger"):
                db.execute(f"INSERT INTO {schema}.{CHILD} VALUES(99)")
            written = snapshot(db, item)
        active = not db.get_autocommit(db.db)
        db.execute("ROLLBACK TO algorithm; RELEASE algorithm")
        return {"before": before, "statement": statement, "prepare": message, "after": after, "written": written,
                "rolledBack": snapshot(db, item), "transaction": active}
    finally:
        db.execute("ROLLBACK TO fixture; RELEASE fixture")


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed" and reply["operation"] == "rename", "sqlite-rename-positive-rejected", reply)
    fields = ("operation", "schema", "table", "column", "destination", "quoteReferences")
    require(all(reply[name] == item[name] for name in fields), "sqlite-rename-structure-mismatch", reply)
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    probe_result = {"observation": observe(db, item, reply["sql"])}
    if original is not None:
        probe_result["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **probe_result)
    prediction = expected(item)
    for actual in probe_result.values():
        compare(prediction, actual)
    if ledger is not None:
        ledger.record(key, "compared")
    return probe_result["observation"]


def forms(measured):
    reply, observation = measured
    yield f"schema/{reply['schema']}"
    target = reply["column"]
    if target is None:
        yield "role/table"
    else:
        subject = observation["before"]["subjects"][reply["schema"]][reply["table"]]
        yield f"column/slot-{subject['fields'].index(target)}"
        yield "role/column"
        yield f"quote/{reply['quoteReferences']}"
    yield f"dependency/{bool(observation['after']['dependencies']['foreign'])}"
    yield f"destination/empty-{reply['destination'] == ''}"
    if '"' in reply["destination"]:
        yield "destination/quote"
    current = observation["before"]["subjects"][reply["schema"]][reply["table"]]
    yield f"width/{len(current['fields'])}"


def required(domain):
    return {
        *(f"schema/{schema}" for schema in domain["schemas"]), "role/table", "role/column", "width/2", "width/6",
        "column/slot-0", "column/slot-5", "dependency/False", "dependency/True",
        "destination/empty-True", "destination/empty-False", "destination/quote", "quote/True", "quote/False",
    }


def observe_keywords(db, expected):
    count = db.lib.sqlite3_keyword_count
    count.restype = ctypes.c_int
    keyword = db.lib.sqlite3_keyword_name
    keyword.restype = ctypes.c_int
    keyword.argtypes = [ctypes.c_int, ctypes.POINTER(ctypes.c_char_p), ctypes.POINTER(ctypes.c_int)]
    name, length = ctypes.c_char_p(), ctypes.c_int()
    observed = {}
    db.execute("SAVEPOINT keyword_fixture; CREATE TABLE main.ir_rename (c0 INTEGER, c1 INTEGER)")
    try:
        for index in range(count()):
            require(keyword(index, ctypes.byref(name), ctypes.byref(length)) == 0, "sqlite-rename-missing-native-keyword", index)
            word = ctypes.string_at(name, length.value).decode("ascii")
            require(word.replace("_", "").isalpha() and word.isupper() and word not in observed, "sqlite-rename-unexpected-native-keyword", word)
            db.execute("SAVEPOINT keyword_rename")
            sql = f"ALTER TABLE main.ir_rename RENAME COLUMN c1 TO {word}"
            try:
                result = db.alter(sql, "ir_rename")
                require(result["evaluationError"] is None, "sqlite-rename-keyword-step-error", {"sql": sql, "result": result})
                observed[word] = {"input": sql, "admitted": True}
            except ObserverAuthorizationError:
                raise
            except RuntimeError as error:
                require(str(error) == f'SQLite 1: near "{word}": syntax error',
                        "sqlite-rename-unexpected-keyword-error", {"sql": sql, "error": str(error)})
                observed[word] = {"input": sql, "admitted": False, "error": str(error)}
            finally:
                db.execute("ROLLBACK TO keyword_rename; RELEASE keyword_rename")
    finally:
        db.execute("ROLLBACK TO keyword_fixture; RELEASE keyword_fixture")
    allowed = sorted(name for name, observation in observed.items() if observation["admitted"])
    rejected = sorted(set(observed) - set(allowed))
    require(allowed == expected["bareAllowed"] and rejected == expected["bareRejected"],
            "sqlite-rename-keyword-admission", {"allowed": allowed, "rejected": rejected})
    return observed


def controls(db, lexical):
    item = rename("temp", "replacement", 1, topology="dependent")
    healthy = expected(item)
    compare(healthy, copy.deepcopy(healthy))
    altered = {**healthy, "rolledBack": {}}
    try:
        compare(healthy, altered)
    except Failure as error:
        require(error.signal == "meaning-mismatch", "sqlite-rename-comparator-signal", error.detail)
    else:
        raise Failure("sqlite-rename-comparator-undetected")
    db.execute("CREATE TABLE main.ir_other(x)")
    unauthorized = "ALTER TABLE main.ir_other RENAME TO ir_incorrect"
    try:
        try:
            db.alter(unauthorized, item["table"])
        except ObserverAuthorizationError as error:
            require(error.action == 26 and error.arguments[:2] == ["main", "ir_other"],
                    "sqlite-rename-observer-denial", error.arguments)
        else:
            raise Failure("sqlite-rename-observer-outside-target")
    finally:
        db.execute("DROP TABLE main.ir_other")
    return {"healthy": healthy, "mutated": altered, "nativeKeywords": observe_keywords(db, lexical), "observer": {"sql": unauthorized, "deniedAction": 26}}


def execute(args, specification, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, lambda: negatives(specification), finite_id, finite_ids, forms, required,
                              lambda: controls(db, specification["lexicalDomain"]))
    return execute_schedule(args, specification, db, worker_factory, schedule, probe,
                            measured_input=lambda reply, observation: (reply, observation))


def check_faults(args, db, runner=check_render_faults):
    table = "sql-semantics-sqlite/src/Ir/Schema/Alter/RenameTable.php"
    column = "sql-semantics-sqlite/src/Ir/Schema/Alter/RenameColumn.php"
    return runner(args, db, [
        RenderFault("move-rename-to-other-schema", table, "$this->schema->quoted()", "'temp'",
                    rename("main", "new_table"), "meaning-mismatch"),
        RenderFault("rename-other-member", column, "$this->column->quoted()", "'c1'",
                    rename("main", "changed", 0), "meaning-mismatch"),
        RenderFault("change-rename-destination", table, "$this->destination->quoted()", "'different'",
                    rename("main", "intended"), "meaning-mismatch"),
        RenderFault("rename-relation-instead-of-member", column, "' RENAME COLUMN ' . $this->column->quoted() . ' TO '",
                    "' RENAME TO '", rename("main", "changed", 1), "meaning-mismatch"),
        RenderFault("force-quoted-column-references", column,
                    "$this->quoteReferences ? $this->destination->quoted() : $this->destination->value",
                    "$this->destination->quoted()", rename("temp", "new_value", 1, topology="temp-view", quote=False),
                    "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("rename", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, RenameDatabase)
