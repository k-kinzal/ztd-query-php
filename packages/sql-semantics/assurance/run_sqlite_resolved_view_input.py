"""Compare absolute SQLite view references, source roles and write-target placement."""

from collections import defaultdict
import hashlib
from itertools import product
import random

from run import Failure, Worker, compare, require
from run_sqlite_create_table import quoted
from run_sqlite_create_view import CreateViewDatabase, DDL_RESULT
from run_sqlite_unique_keys import values_sql
from sqlite_create_view_cases import col, integer, projection, item as create_item
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_engine import typed
from sqlite_relational_cases import DATA, DECLARATIONS, SCHEMA, expected as relational
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-resolved-view-input-v2.json"
CONTRACT = "adea66f4c8d06b0eeb66d17aa0a5f1da015fde3fe49d2447e1928e36023cee78"
RAW = {
    "main": [(-1, "older"), (1, "main"), (1, "main"), (2, None), (3, 31), (None, "null-key")],
    "temp": [(-2, None), (1, "temp"), (1, "temp"), (2, 79), (3, None), (None, 92)],
}
VIEWS = {f"{schema}.mirror": [row for row in rows if row[0] is not None and row[0] >= 1]
         for schema, rows in RAW.items()}
VIEWS["main.derived_view"] = DATA["ir_left"]
VIEW_DECLARATIONS = [*DECLARATIONS, "CREATE VIEW derived_view AS SELECT key, value FROM ir_left"]
definitions = []
for namespace, rows in RAW.items():
    temporary = "TEMP " if namespace == "temp" else ""
    table = f"{namespace}.view_feed"
    definitions.extend([
        f"CREATE {temporary}TABLE view_feed(key BLOB,value BLOB)",
        f"INSERT INTO {table} VALUES {values_sql(rows)}",
        f"CREATE {temporary}VIEW mirror AS SELECT key,value FROM {table} WHERE key >= 1",
    ])
    VIEW_DECLARATIONS.extend((definitions[-3], definitions[-1]))
FIXTURE = SCHEMA + "CREATE VIEW derived_view AS SELECT key,value FROM ir_left; " + "; ".join(definitions)
PROJECTIONS = ("ordered-fields", "reverse-fields", "repeat-slot")
LAYOUTS = ("single", "self-join")


class ViewInputDatabase(CreateViewDatabase):
    """Observe native view references and the pinned COUNT control."""

    policy_digest = hashlib.sha256(f"{CreateViewDatabase.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def authorize(self, context, action, first, second, schema, origin):
        if (action, first, second, schema, origin) == (31, None, b"count", None, None):
            return 0
        return super().authorize(context, action, first, second, schema, origin)


def source(schema, table="mirror", fields=("key", "value")):
    return [schema, table, list(fields), "view"]


def select(schema, projected, layout, filtered, sample=None):
    left = source(schema)
    right = source(schema if sample is None else sample.choice(("main", "temp")))
    joined = layout == "self-join"
    join = [] if not joined else [["CROSS", right, None]]
    second = col(1, int(joined))
    fields = {
        "ordered-fields": [col(0), second],
        "reverse-fields": [second, col(0)],
        "repeat-slot": [second, second],
    }[projected]
    labels = ["x", "y"] if sample is None else [f'field "{sample.randrange(9)}' for _ in fields]
    where = ["comparison", "IS NOT", second, ["null"]] if filtered else None
    return projection([[field, label] for field, label in zip(fields, labels)], left, join, where)


def finite_ids(contract):
    return set(product(contract["schemas"], contract["projection"], contract["layout"], contract["nullFilter"]))


def finite_id(measured, _):
    query = measured["query"]
    first, second = [field[0] for field in query["columns"]]
    projection = "repeat-slot" if first == second else "ordered-fields" if first == col(0) else "reverse-fields"
    return query["source"][0], projection, "self-join" if query["joins"] else "single", query["where"] is not None


def analyzed_cases():
    labels = [[col(0), "key"], [col(1), "value"]]
    query = projection(labels, source("main", "derived_view"))
    previous = [*DECLARATIONS, "CREATE VIEW derived_view AS SELECT key, value FROM ir_left"]
    yield {"sql": "SELECT * FROM derived_view", "declarations": previous}, query
    yield {"sql": "SELECT * FROM mirror", "declarations": VIEW_DECLARATIONS}, projection(labels, source("temp"))
    yield {"sql": "SELECT * FROM main.mirror", "declarations": VIEW_DECLARATIONS}, projection(labels, source("main"))
    query = projection([[col(0), "first"], [col(0, 1), "second"]], source("main", fields=("key",)),
                       [["CROSS", source("main", fields=("value",)), None]])
    yield {"sql": "SELECT a.key AS first,b.value AS second FROM main.mirror a CROSS JOIN main.mirror b", "declarations": VIEW_DECLARATIONS}, query
    grouped = {"operation": "grouped", "source": source("main"), "columns": [[col(0), "key"], [["count", col(1), None], "n"]],
               "keys": [[col(0), "BINARY"]], "where": None, "having": None}
    yield {"sql": "SELECT key, count(value) AS n FROM main.mirror GROUP BY key", "declarations": VIEW_DECLARATIONS}, grouped
    view = create_item("main", projection(labels, source("main")), ["key", "value"], name="read_existing")
    yield {"sql": "CREATE VIEW read_existing AS SELECT key,value FROM mirror", "declarations": VIEW_DECLARATIONS}, view


def requests(contract):
    domain = contract["finite"]
    for index, values in enumerate(product(domain["schemas"], domain["projection"], domain["layout"], domain["nullFilter"])):
        query = select(*values)
        yield f"finite/{index}", query, query
    for index, (request, query) in enumerate(analyzed_cases()):
        yield f"analyze/{index}", request, query
    for seed in contract["exploration"]["seeds"]:
        sample = random.Random(seed)
        for index in range(contract["exploration"]["casesPerSeed"]):
            query = select(sample.choice(("main", "temp")), sample.choice(PROJECTIONS), sample.choice(LAYOUTS),
                           sample.choice((False, True)), sample)
            yield f"seed/{seed}/{index}", query, query


def negatives():
    dest = source("main")
    yield {"operation": "insert", "target": dest, "rows": [[integer(1), integer(2)]], "conflict": "inherit"}, "rejected", "must be a base table"
    yield {"operation": "update", "target": dest, "assignments": [[0, integer(12)]], "conflict": "inherit"}, "rejected", "must be a base table"
    yield {"operation": "delete", "target": dest}, "rejected", "must be a base table"
    yield {"sql": "DELETE FROM main.mirror", "declarations": VIEW_DECLARATIONS}, "rejected", "requires a base table"


def fixture_source(relation):
    schema, name, fields, kind = relation
    require(kind == "view", "view-input-fixture-kind", relation)
    return ["main", f"{schema}.{name}", fields]


def expected(query):
    if query["operation"] == "grouped":
        counts = defaultdict(int)
        for key, value in VIEWS["main.mirror"]:
            counts[key] += int(value is not None)
        return {"names": ["key", "n"], "rows": sorted([[typed(key), typed(count)] for key, count in counts.items()]),
                "parameterCount": 0, "parameters": {}, "readOnly": True, "changes": 0}
    normalized = {**query, "source": fixture_source(query["source"]), "bindings": {},
                  "joins": [[kind, fixture_source(relation), on] for kind, relation, on in query["joins"]]}
    return relational(normalized, VIEWS)


def verify_source(db, relation):
    schema, name, _, kind = relation
    observed = db.observe(f"SELECT type FROM {schema}.sqlite_schema WHERE name = ?1", {"1": name})
    require(observed["rows"] == [[typed(kind)]], "view-input-native-relation-kind", observed)


def native_view(db, view, sql):
    db.execute("SAVEPOINT view_reads_view")
    try:
        observe = db.create(sql, view["view"])
        metadata = db.metadata("table_xinfo", schema=view["schema"], table=view["view"])
        read = db.observe(f"SELECT * FROM main.{quoted(view['view'])}", {})
        catalog = db.observe("SELECT type,name,tbl_name FROM main.sqlite_schema WHERE name = ?1", {"1": view["view"]})
        return {"create": observe, "metadata": metadata, "read": read, "catalog": catalog["rows"]}
    finally:
        db.execute("ROLLBACK TO view_reads_view; RELEASE view_reads_view")


def view_expected(view):
    return {
        "create": DDL_RESULT,
        "metadata": [[typed(index), typed(name), typed("BLOB"), typed(0), typed(None), typed(0), typed(0)]
                     for index, name in enumerate(view["columns"])],
        "read": expected(view["query"]),
        "catalog": [[typed("view"), typed(view["view"]), typed(view["view"])]],
    }


def probe(item, response, db, ledger=None, key=None, original=None):
    if item["operation"] == "delete":
        require(response.get("status") == "rejected" and response.get("exception", "").endswith("\\InvalidConstruction")
                and "must be a base table" in response.get("message", ""), "view-target-admission-bypass", response)
        return None
    require(response["status"] == "constructed", "view-input-positive-rejected", response)
    create = item["operation"] == "create-view"
    roles = (("query", "schema", "view", "columns", "ifNotExists") if create else
             tuple(field for field in item if field != "operation"))
    require(all(response[field] == item[field] for field in roles), "view-input-structure-mismatch", response)
    graph = response["query"] if create else response
    verify_source(db, graph["source"])
    for step in graph.get("joins", ()):
        verify_source(db, step[1])
    if ledger is not None:
        ledger.record(key, "constructed", response=response)
    def observe(sql):
        return native_view(db, item, sql) if create else db.observe(sql, {})
    observations = {"observation": observe(response["sql"])}
    if original is not None:
        observations["original"] = observe(original)
    if ledger is not None:
        ledger.record(key, "observed", **observations)
    prediction = view_expected(item) if create else expected(item)
    for measurement in observations.values():
        compare(prediction, measurement)
    if ledger is not None:
        ledger.record(key, "compared")
    return observations["observation"]


def actual_shape(reply, _):
    return {"query": reply}


def forms(measured):
    query = measured["query"]
    yield f"schema/{query['source'][0]}"
    yield f"joined/{bool(query['joins'])}"
    yield f"filtered/{query['where'] is not None}"
    if '"' in query["columns"][0][1]:
        yield "identifier/delimiter"
    for expression, _ in query["columns"]:
        yield f"slot/{expression[1]}/{expression[2] if len(expression) > 2 else 0}"


def required(_):
    return {"schema/main", "schema/temp", "joined/True", "joined/False", "filtered/True", "filtered/False",
            "identifier/delimiter", "slot/0/0", "slot/1/0", "slot/1/1"}


def controls():
    value = select("main", "ordered-fields", "single", False)
    healthy = expected(value)
    changed = expected({**value, "source": source("temp")})
    compare(healthy, healthy)
    try:
        compare(healthy, changed)
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "wrong-view-input-comparator-failure", failure.detail)
    else:
        raise Failure("view-input-comparator-ignored-schema")
    return {"input": value, "healthy": healthy, "mismatched": changed}


def execute(args, contract, db, worker_factory=Worker):
    require(len(list(analyzed_cases())) == len(contract["analysis"]), "incomplete-view-input-analysis-schedule")
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return execute_schedule(args, contract, db, worker_factory, schedule, probe, measured_input=actual_shape)


def check_faults(args, db, runner=check_render_faults):
    renderer = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    delete = "sql-semantics-sqlite/src/Ir/Mutation/Delete.php"
    single = select("main", "ordered-fields", "single", False)
    joined = select("main", "ordered-fields", "self-join", False)
    return runner(args, db, [
        RenderFault("redirect-view-read-schema", renderer, "$source->schema->quoted() . '.' . $source->table->quoted()",
                    "'temp.' . $source->table->quoted()", single, "meaning-mismatch"),
        RenderFault("bind-later-view-to-first", renderer, "$this->alias($expression->source)", "$this->alias(0)",
                    joined, "meaning-mismatch"),
        RenderFault("drop-read-view-field", renderer, "foreach ($select->columns as $column)",
                    "foreach (array_slice($select->columns, 1) as $column)", single, "meaning-mismatch"),
        RenderFault("allow-view-mutation-target", delete, "$target->kind === ExternalRelationKind::BaseTable",
                    "true", {"operation": "delete", "target": source("main")}, "view-target-admission-bypass"),
    ], probe)


if __name__ == "__main__":
    run_domain("resolved-view-input", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, ViewInputDatabase)
