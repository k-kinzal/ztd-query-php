"""Exercise SQLite view removal and scope against independently computed effects."""

from copy import deepcopy
import hashlib

from run import Failure, Worker, compare, require
from run_sqlite_create_table import quoted
from run_sqlite_unique_keys import values_sql
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_drop_view_cases import (
    BASE, NEIGHBOR, SCHEMAS, catalogue, declarations, expected, finite_case, finite_id, finite_ids,
    negatives, requests, trigger, watch,
)
from sqlite_engine import Database, ObserverAuthorizationError, typed
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-drop-view-v1.json"
CONTRACT = "cbb70e7fe7d396931d6f1f591aa91e4e244f553df17410bce835cef96f356e73"
FIXTURE = ""


class DropViewDatabase(Database):
    """Adds native DROP VIEW actions for registered disposable view identities."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library):
        super().__init__(library)
        self.targets = frozenset()

    def authorize(self, context, action, first, second, schema, origin):
        if ((action, schema) in {(17, b"main"), (15, b"temp")}
                and first in self.targets and second is None and origin is None):
            return 0
        return super().authorize(context, action, first, second, schema, origin)

    def drop(self, sql, name):
        self.targets = {name.encode(), NEIGHBOR.encode()}
        try:
            return self.observe(sql, {}, capture_evaluation_error=True)
        finally:
            self.targets = frozenset()


def catalog(db):
    return {
        schema: db.observe(f"SELECT type,name,tbl_name FROM {schema}.sqlite_schema ORDER BY type,name,tbl_name",
                           {}, preserve_order=True)["rows"]
        for schema in SCHEMAS
    }


def base_rows(db, schema):
    return db.observe(f"SELECT payload FROM {schema}.{BASE} ORDER BY seq", {}, preserve_order=True)["rows"]


def setup(db, item):
    db.execute("; ".join(declarations(item)))
    for schema in SCHEMAS:
        values = item["values"] if schema == item["schema"] else (890, 891)
        db.execute(f"INSERT INTO {schema}.{BASE} VALUES " + values_sql(enumerate(values)))
    schema, name = item["schema"], item["view"]
    for index in range(item["triggers"]):
        db.execute(f"CREATE TRIGGER {schema}.{trigger(index)} INSTEAD OF INSERT ON {quoted(name)} "
                   f"BEGIN INSERT INTO {BASE} VALUES ({100 + index},NEW.payload); END")
    for index in range(item["depth"]):
        parent = quoted(name if index == 0 else watch(index - 1))
        db.execute(f"CREATE VIEW {schema}.{watch(index)} AS SELECT payload FROM {schema}.{parent}")


def protected_failure(measure, prefix):
    try:
        return measure(), None
    except ObserverAuthorizationError:
        raise
    except RuntimeError as failure:
        require(str(failure).startswith(prefix), "unexpected-native-drop-view-failure", str(failure))
        return None, str(failure)


def observe(db, item, sql):
    db.execute("SAVEPOINT fixture_drop_view")
    try:
        setup(db, item)
        before = catalog(db)
        schema = item["schema"]
        statement, error = protected_failure(lambda: db.drop(sql, item["view"]), "SQLite 1: no such view: ")
        after = catalog(db)
        values = {schema: base_rows(db, schema) for schema in SCHEMAS}
        neighbors = {}
        for namespace in SCHEMAS:
            outcome, missing = protected_failure(
                lambda: db.observe(f"SELECT payload FROM {namespace}.{NEIGHBOR}", {}, preserve_order=True),
                f"SQLite 1: no such table: {namespace}.{NEIGHBOR}",
            )
            neighbors[namespace] = missing if missing is not None else outcome["rows"]
        dependents = []
        for ordinal in range(item["depth"]):
            outcome, problem = protected_failure(
                lambda: db.observe(f"SELECT payload FROM {schema}.{watch(ordinal)}", {}, preserve_order=True),
                "SQLite 1: no such table: ",
            )
            dependents.append(problem if problem is not None else outcome)
        write = db.observe(f"INSERT INTO {schema}.{BASE} VALUES (710,701)", {})
        return {
            "before": before, "after": after, "statement": statement, "prepareError": error, "base": values,
            "neighbors": neighbors, "dependents": dependents,
            "baseWrite": {"changes": write["changes"], "rows": base_rows(db, schema)},
        }
    finally:
        db.execute("ROLLBACK TO fixture_drop_view; RELEASE fixture_drop_view")


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed", "sqlite-drop-view-positive-rejected", reply)
    fields = ("operation", "schema", "view", "ifExists")
    require(all(reply[field] == item[field] for field in fields), "sqlite-drop-view-structure-mismatch", reply)
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    observations = {"observation": observe(db, item, reply["sql"])}
    if original is not None:
        observations["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **observations)
    prediction = expected(item)
    for outcome in observations.values():
        compare(prediction, outcome)
    if ledger is not None:
        ledger.record(key, "compared")
    return observations["observation"]


def actual_shape(reply, outcome):
    schema, name = reply["schema"], typed(reply["view"])
    target = outcome["before"][schema]
    peers = outcome["before"]["temp" if schema == "main" else "main"]
    present = [typed("view"), name, name] in target
    owned = sum(record[0] == typed("trigger") and record[2] == name for record in target)
    shadow = next((record[0][1] for record in peers if record[1] == name and record[2] == name), None)
    layout = "absent" if not present else "owned-trigger" if owned else "shadow-view" if shadow == "view" else "view"
    return {**reply, "layout": layout, "owned": owned, "shadow": shadow, "depth": len(outcome["dependents"])}


def forms(measurement):
    yield f"schema/{measurement['schema']}"
    yield f"layout/{measurement['layout']}"
    yield f"owned/{measurement['owned']}"
    yield f"guard/{measurement['ifExists']}"
    yield f"shadow/{measurement['shadow']}"
    yield f"depth/{measurement['depth']}"
    if '"' in measurement["view"]:
        yield "identifier/delimiter"


def required(_domain):
    return {"schema/main", "schema/temp", "layout/absent", "owned/0", "owned/3", "guard/True", "guard/False",
            "shadow/table", "shadow/view", "depth/0", "depth/3", "identifier/delimiter"}


def controls():
    item = finite_case("main", "owned-trigger", True)
    normal = expected(item)
    compare(normal, deepcopy(normal))
    altered = {**normal, "after": catalogue(item, False)}
    try:
        compare(normal, altered)
    except Failure as error:
        require(error.signal == "meaning-mismatch", "wrong-drop-view-comparator-failure", error.detail)
    else:
        raise Failure("drop-view-comparator-ignored-catalog")
    return {"input": item, "healthy": normal, "mismatched": altered}


def execute(args, contract, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return execute_schedule(args, contract, db, worker_factory, schedule, probe, measured_input=actual_shape)


def check_faults(args, db, runner=check_render_faults):
    renderer = "sql-semantics-sqlite/src/Ir/Schema/DropView.php"
    present = finite_case("main", "shadow-view", False)
    missing = finite_case("temp", "absent", True)
    exact = "return 'DROP VIEW ' . ($this->ifExists ? 'IF EXISTS ' : '') . $this->schema->quoted() . '.' . $this->view->quoted();"
    return runner(args, db, [
        RenderFault("drop-view-discard-if-exists", renderer, "($this->ifExists ? 'IF EXISTS ' : '')", "''", missing, "meaning-mismatch"),
        RenderFault("drop-view-redirect-schema", renderer, "$this->schema->quoted()", "'temp'", present, "meaning-mismatch"),
        RenderFault("drop-view-remove-another-object", renderer, "$this->view->quoted()", f"'{NEIGHBOR}'", present, "meaning-mismatch"),
        RenderFault("drop-view-emit-a-read", renderer, exact, "return 'SELECT 1';", present, "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("drop-view", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, DropViewDatabase)
