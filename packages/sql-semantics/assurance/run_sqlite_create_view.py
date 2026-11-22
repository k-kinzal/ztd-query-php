"""Compare checked view definitions with independent native catalog and row observations."""

from copy import deepcopy
import hashlib
from itertools import product

from run import Failure, Worker, compare, require
from run_sqlite_create_table import quoted
from run_sqlite_unique_keys import values_sql
from sqlite_create_view_cases import SCHEMAS, TABLES, finite_case, finite_id, finite_ids, kind, negatives, requests, storage
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import Database, ObserverAuthorizationError, typed
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-create-view-v1.json"
CONTRACT = "f65e11e8e603f7bd763a96c1ef60c0e0f25c9f0d98d906d8f60f642d513a468d"
FIXTURE = ""
READ_RESULT = {"parameters": {}, "parameterCount": 0, "readOnly": True, "changes": 0}
DDL_RESULT = {**READ_RESULT, "readOnly": False, "names": [], "rows": []}
EXTRA = 607


class CreateViewDatabase(Database):
    """Authorize view creation only under the disposable named fixture target."""

    policy_digest = hashlib.sha256(f"{Database.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library):
        super().__init__(library)
        self.target = None

    def authorize(self, context, action, first, second, schema, origin):
        if ((action, schema) in {(8, b"main"), (8, b"temp"), (6, b"temp")} and first == self.target
                and first is not None and second is None and origin is None):
            return 0
        return super().authorize(context, action, first, second, schema, origin)

    def create(self, sql, name):
        self.target = name.encode()
        try:
            return self.observe(sql, {})
        finally:
            self.target = None


def operand(expression, occurrences):
    tag, *arguments = expression
    if tag == "integer":
        return int(arguments[0])
    if tag == "text":
        return arguments[0]
    if tag == "null":
        return None
    if tag == "column":
        slot, *positions = arguments
        return occurrences[positions[0] if positions else 0][slot]
    if tag == "cast" and arguments[0] == "TEXT":
        return str(operand(arguments[1], occurrences))
    if tag in ("arithmetic", "comparison"):
        operator, left, right = arguments
        a, b = operand(left, occurrences), operand(right, occurrences)
        operations = {"+": lambda: a + b, "-": lambda: a - b, "=": lambda: a == b, ">=": lambda: a >= b}
        return operations[operator]()
    raise AssertionError(f"Unspecified view test expression: {expression}")


def inputs(data, descriptor):
    schema, table, columns = descriptor
    slots = [("id", "v").index(name) for name in columns]
    return [tuple(row[slot] for slot in slots) for row in data[schema][table]]


def projection(query, data):
    occurrences = [()] if query["source"] is None else [(row,) for row in inputs(data, query["source"])]
    for join_kind, source, on in query["joins"]:
        require(join_kind == "INNER", "unmodeled-view-fixture-join", join_kind)
        occurrences = [(*left, right) for left, right in product(occurrences, inputs(data, source))
                       if operand(on, (*left, right))]
    columns = [expression for expression, _ in query["columns"]]
    rows = [[operand(expression, row) for expression in columns]
            for row in occurrences if query["where"] is None or operand(query["where"], row)]
    if query["orderBy"]:
        [[position, descending, nulls]] = query["orderBy"]
        require(nulls is False and None not in [row[position - 1] for row in rows],
                "unmodeled-view-fixture-order", query)
        rows.sort(key=lambda row: row[position - 1], reverse=descending)
    if query["limit"]:
        limit, offset, _ = query["limit"]
        start = 0 if offset is None else operand(offset, ())
        rows = rows[start:start + operand(limit, ())]
    return rows


def declared_type(query, expression):
    if expression[0] == "cast":
        return expression[1]
    if expression[0] == "column":
        _, slot, *occurrences = expression
        sources = [query["source"], *(step[1] for step in query["joins"])]
        schema, _, names = sources[occurrences[0] if occurrences else 0]
        return "INTEGER" if names[slot] == "id" else storage(schema)
    return ""


def rows_for(item, data):
    if item["occupied"]:
        return [[901]]
    query = item["query"]
    if query["operation"] == "values":
        return [[operand(expression, ()) for expression in row] for row in query["rows"]]
    return projection(query, data)


def sorted_rows(rows, ordered=False):
    observed = [[typed(cell) for cell in row] for row in rows]
    return observed if ordered else sorted(observed)


def expected_catalog(item, occupied):
    objects = {schema: [("table", table, table) for table in TABLES] for schema in SCHEMAS}
    objects["temp"].append(("table", "temp_only", "temp_only"))
    if occupied:
        objects[item["schema"]].append(("view", item["view"], item["view"]))
    return {schema: sorted_rows(rows, True) for schema, rows in objects.items()}


def expected(item):
    exists = item["occupied"]
    query = item["query"]
    columns = ["existing"] if exists else item["columns"]
    types = ([""] if exists else [""] * len(columns) if query["operation"] == "values"
             else [declared_type(query, expression) for expression, _ in query["columns"]])
    data = item["data"]
    changed = deepcopy(data)
    for schema in SCHEMAS:
        changed[schema]["base"].append((EXTRA, EXTRA if schema == "main" else "new-value"))
    ordered = not exists and bool(query.get("orderBy"))
    read = lambda source_data: {**READ_RESULT, "names": columns, "rows": sorted_rows(rows_for(item, source_data), ordered)}
    conflict = exists and not item["ifNotExists"]
    return {
        "before": expected_catalog(item, exists),
        "after": expected_catalog(item, True),
        "create": None if conflict else DDL_RESULT,
        "problem": f"SQLite 1: view {item['view']} already exists" if conflict else None,
        "metadata": sorted_rows([(i, name, datatype, 0, None, 0, 0)
                                 for i, (name, datatype) in enumerate(zip(columns, types))], True),
        "read": read(data), "reread": read(changed), "base": {schema: sorted_rows(data[schema]["base"], True) for schema in SCHEMAS},
        "retainedDefinition": True if exists else None,
    }


def catalog(db):
    return {schema: db.observe(f"SELECT type,name,tbl_name FROM {schema}.sqlite_schema ORDER BY type,name,tbl_name",
                               {}, preserve_order=True)["rows"] for schema in SCHEMAS}


def setup(db, item):
    for schema in SCHEMAS:
        for table in TABLES:
            db.execute(f"CREATE TABLE {schema}.{table} (id INTEGER, v {storage(schema)})")
            rows = item["data"][schema][table]
            db.execute(f"INSERT INTO {schema}.{table} VALUES " + values_sql(rows))
    db.execute("CREATE TEMP TABLE temp_only (id INTEGER)")
    if item["occupied"]:
        db.execute(f"CREATE VIEW {item['schema']}.{quoted(item['view'])} AS SELECT 901 AS existing")


def definition(db, schema, name):
    return db.observe(f"SELECT sql FROM {schema}.sqlite_schema WHERE type='view' AND name=?1", {"1": name})["rows"]


def apply(db, sql, item):
    try:
        return db.create(sql, item["view"]), None
    except ObserverAuthorizationError:
        raise
    except RuntimeError as failure:
        names = (item["view"], quoted(item["view"]))
        require(str(failure) in {f"SQLite 1: view {name} already exists" for name in names},
                "unexpected-view-creation-failure", str(failure))
        return None, str(failure)


def observe(db, item, sql):
    schema, name = item["schema"], item["view"]
    target = f"{schema}.{quoted(name)}"
    ordered = not item["occupied"] and bool(item["query"].get("orderBy"))
    db.execute("SAVEPOINT view_fixture")
    try:
        setup(db, item)
        before = catalog(db)
        prior = definition(db, schema, name)
        creation, error = apply(db, sql, item)
        after = catalog(db)
        retained = prior == definition(db, schema, name) if item["occupied"] else None
        metadata = db.metadata("table_xinfo", schema=schema, table=name)
        base = {ns: db.observe(f"SELECT id,v FROM {ns}.base ORDER BY id", {}, preserve_order=True)["rows"] for ns in SCHEMAS}
        query = f"SELECT * FROM {target}"
        first = db.observe(query, {}, preserve_order=ordered) if metadata else None
        db.execute(f"INSERT INTO main.base VALUES ({EXTRA},{EXTRA}); INSERT INTO temp.base VALUES ({EXTRA},'new-value')")
        reread = db.observe(query, {}, preserve_order=ordered) if metadata else None
        return {"before": before, "after": after, "create": creation,
                "problem": f"SQLite 1: view {name} already exists" if error is not None else None,
                "rawProblem": error, "metadata": metadata,
                "read": first, "reread": reread, "base": base, "retainedDefinition": retained}
    finally:
        db.execute("ROLLBACK TO view_fixture; RELEASE view_fixture")


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed", "sqlite-create-view-positive-rejected", reply)
    fields = ("operation", "schema", "view", "ifNotExists", "columns", "query")
    require(all(reply[field] == item[field] for field in fields), "sqlite-create-view-structure-mismatch", reply)
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    outcomes = {"observation": observe(db, item, reply["sql"])}
    if original is not None:
        outcomes["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **outcomes)
    prediction = expected(item)
    for outcome in outcomes.values():
        compare(prediction, {field: value for field, value in outcome.items() if field != "rawProblem"})
    if ledger is not None:
        ledger.record(key, "compared")
    return outcomes["observation"]


def actual_shape(reply, outcome):
    view = typed(reply["view"])
    occupied = [typed("view"), view, view] in outcome["before"][reply["schema"]]
    return {**reply, "occupied": occupied}


def forms(measurement):
    query = measurement["query"]
    yield f"schema/{measurement['schema']}"
    yield f"body/{kind(query)}"
    yield f"guard/{measurement['ifNotExists']}"
    yield f"occupied/{measurement['occupied']}"
    if '"' in measurement["view"]:
        yield "identifier/delimiter"
    if any('"' in name for name in measurement["columns"]):
        yield "column/delimiter"
    if query["operation"] == "values":
        yield f"rows/{len(query['rows'])}"
    else:
        yield f"width/{len(query['columns'])}"
        sources = [query["source"], *(step[1] for step in query["joins"])]
        for source in sources:
            if source:
                yield f"{measurement['schema']}-reads/{source[0]}"
        for expression, _ in query["columns"]:
            if expression[0] == "column":
                yield f"reference/{expression[2] if len(expression) == 3 else 0}/{expression[1]}"


def required(domain):
    return {*(f"schema/{schema}" for schema in domain["schemas"]), *(f"body/{kind}" for kind in domain["queries"]),
            "guard/True", "guard/False", "occupied/True", "occupied/False", "identifier/delimiter", "column/delimiter",
            "rows/1", "rows/7", "width/1", "width/6", "temp-reads/main", "temp-reads/temp", "reference/0/1", "reference/1/1"}


def controls():
    item = finite_case("main", "joined-tables", False, False)
    normal = expected(item)
    altered = deepcopy(normal)
    altered["metadata"][0][2] = typed("TEXT")
    compare(normal, deepcopy(normal))
    try:
        compare(normal, altered)
    except Failure as error:
        require(error.signal == "meaning-mismatch", "wrong-view-comparator-failure", error.detail)
    else:
        raise Failure("view-comparator-ignored-column-type")
    return {"input": item, "healthy": normal, "mismatched": altered}


def execute(args, contract, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return execute_schedule(args, contract, db, worker_factory, schedule, probe, measured_input=actual_shape)


def check_faults(args, db, runner=check_render_faults):
    renderer = "sql-semantics-sqlite/src/Ir/Schema/View/CreateView.php"
    simple = finite_case("main", "projection", False, False)
    guarded = finite_case("main", "projection", True, True)
    return runner(args, db, [
        RenderFault("erase-view-if-not-exists", renderer, "($this->ifNotExists ? 'IF NOT EXISTS ' : '')", "''", guarded, "meaning-mismatch"),
        RenderFault("redirect-view-schema", renderer, "$this->schema->quoted()", "'temp'", simple, "meaning-mismatch"),
        RenderFault("replace-view-query-body", renderer, "$this->query->toString()", "'SELECT 1, 2, 3'", simple, "meaning-mismatch"),
        RenderFault("replace-first-view-column", renderer, "$columns[] = $name->quoted();",
                    "$columns[] = $columns === [] ? 'changed' : $name->quoted();", simple, "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("create-view", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, CreateViewDatabase)
