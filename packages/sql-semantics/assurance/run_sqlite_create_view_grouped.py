"""Check grouping and aggregation in SQLite view declarations against native reads."""

from itertools import product
import hashlib
import random

from run import Failure, Worker, compare, require
from run_sqlite_create_view import CreateViewDatabase, actual_shape, expected, probe
from sqlite_create_view_cases import SCHEMAS, analyzed, col, integer, item, source
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-create-view-grouped-v1.json"
CONTRACT = "61a910409bdbf41ecbe55b2a192a668a58434910375a7d465985c6955ffea525"
FIXTURE = ""
BODIES = ("global-count", "grouped-count", "having")
COUNT_STAR = ["count", None, None]


class GroupedViewDatabase(CreateViewDatabase):
    """Permit the pinned count function only while reading the registered view."""

    policy_digest = hashlib.sha256(f"{CreateViewDatabase.policy_digest}:{CONTRACT}".encode()).hexdigest()

    def __init__(self, library):
        super().__init__(library)
        self.read_target = None

    def authorize(self, context, action, first, second, schema, origin):
        if action == 31 and first is None and second == b"count" and schema is None and origin is not None and origin == self.read_target:
            return 0
        return super().authorize(context, action, first, second, schema, origin)

    def read(self, sql, name, ordered):
        self.read_target = name.encode()
        try:
            return super().read(sql, name, ordered)
        finally:
            self.read_target = None


def grouped(first, columns, keys=(), where=None, having=None):
    return {"operation": "grouped", "source": first, "columns": columns, "keys": list(keys),
            "where": where, "having": having}


def query_for(shape, schema, sample=None):
    table = "peer" if sample is None or shape == "having" else sample.choice(("base", "peer"))
    relation = source(schema, table)
    column_count = ["count", col(1), None]
    if sample is not None:
        column_count = ["count", col(sample.randrange(2)), sample.choice((None, "BINARY", "NOCASE", "RTRIM"))]
    fields = [[COUNT_STAR, "total"], [column_count, "seen"]]
    keys = [] if shape == "global-count" else [[col(0), "BINARY" if sample is None else sample.choice(("BINARY", "NOCASE"))]]
    if keys:
        fields.insert(0, [col(0), "key"])
    threshold = 1 if sample is None else sample.randrange(1, 4)
    condition = None if shape != "having" else [COUNT_STAR, ">", integer(threshold), False]
    where = None if sample is None else ["comparison", ">=", col(0), integer(sample.randrange(1, 7))]
    if sample is not None and shape == "global-count" and sample.randrange(3) == 0:
        return grouped(None, [[COUNT_STAR, "total"]], where=integer(sample.randrange(2)))

    return grouped(relation, fields, keys, where, condition)


def finite_case(schema, shape, guard, occupied):
    query = query_for(shape, schema)
    return item(schema, query, [label for _, label in query["columns"]], guard, occupied)


def finite_ids(domain):
    return set(product(domain["schemas"], domain["bodies"], domain["guard"], domain["occupied"]))


def body_kind(query):
    if not query["keys"]:
        return "global-count"
    return "grouped-count" if query["having"] is None else "having"


def finite_id(measured, _):
    return measured["schema"], body_kind(measured["query"]), measured["ifNotExists"], measured["occupied"]


def analysis_cases():
    global_count = grouped(None, [[COUNT_STAR, "n"]])
    yield analyzed("main", "CREATE VIEW observed_view AS SELECT count(*) AS n", global_count, ["n"])
    bound = source("main", columns=("id",))
    global_empty = grouped(bound, [[COUNT_STAR, "n"]], where=["comparison", "<", col(0), integer(0)])
    yield analyzed("main", "CREATE VIEW observed_view AS SELECT count(*) AS n FROM base WHERE id < 0", global_empty, ["n"])
    grouped_count = grouped(source("main"), [[col(0), "id"], [["count", col(1), None], "n"]], [[col(0), "BINARY"]])
    yield analyzed("main", "CREATE VIEW observed_view AS SELECT id, count(v) AS n FROM base GROUP BY id",
                   grouped_count, ["id", "n"])
    distinct_count = grouped(source("main", columns=("v",)), [[["count", col(0), "BINARY"], "n"]])
    yield analyzed("temp", "CREATE TEMP VIEW observed_view AS SELECT count(DISTINCT v) AS n FROM main.base",
                   distinct_count, ["n"])
    having = grouped(source("main", "peer", ("id",)), [[col(0), "id"], [COUNT_STAR, "n"]], [[col(0), "BINARY"]],
                     having=[COUNT_STAR, "<", integer(1), True])
    yield analyzed("main", "CREATE VIEW observed_view AS SELECT id,count(*) AS n FROM peer GROUP BY id HAVING 1 < count(*)",
                   having, ["id", "n"])
    named = grouped(source("temp", "peer", ("id",)), [[col(0), "id"], [COUNT_STAR, "n"]], [[col(0), "BINARY"]])
    yield analyzed("temp", 'CREATE TEMP VIEW observed_view("partition", "total") AS SELECT id,count(*) AS n FROM peer GROUP BY id',
                   named, ["partition", "total"])


def requests(contract):
    domain = contract["finite"]
    for index, case in enumerate(product(domain["schemas"], domain["bodies"], domain["guard"], domain["occupied"])):
        value = finite_case(*case)
        yield f"finite/{index}", value, value
    for index, (query, value) in enumerate(analysis_cases()):
        yield f"analyze/{index}", query, value
    for seed in contract["exploration"]["seeds"]:
        sample = random.Random(seed)
        for index in range(contract["exploration"]["casesPerSeed"]):
            schema, shape = sample.choice(SCHEMAS), sample.choice(BODIES)
            relation_schema = "main" if schema == "main" else sample.choice(SCHEMAS)
            query = query_for(shape, relation_schema, sample)
            names = [f'c"{i}' for i, _ in enumerate(query["columns"])]
            value = item(schema, query, names, sample.choice((False, True)), sample.choice((False, True)),
                         f'group "{sample.randrange(9999)}', sample.randrange(100))
            if sample.choice((False, True)):
                values = value["data"][relation_schema]["peer"]
                values[1] = values[0]
            yield f"seed/{seed}/{index}", value, value


def negatives():
    good = finite_case("main", "grouped-count", False, False)
    yield {**good, "columns": ["only_one"]}, "rejected", "match the query width"
    yield {**good, "query": {**good["query"], "source": source("temp")}}, "rejected", "tables in its schema"
    for role, operand in (("where", ["parameter", "1"]), ("where", ["folded-membership", False, ["02"]]),
                          ("having", [COUNT_STAR, ">", ["parameter", "03"], False])):
        yield {**good, "query": {**good["query"], role: operand}}, "rejected", "cannot contain a parameter"


def forms(measured):
    query = measured["query"]
    yield f"body/{body_kind(query)}"
    yield f"schema/{measured['schema']}"
    yield f"guard/{measured['ifNotExists']}"
    yield f"occupied/{measured['occupied']}"
    if query["source"] is None:
        yield "source/implicit"
    else:
        yield f"{measured['schema']}-reads/{query['source'][0]}"
    if query["having"] is not None:
        yield "having/comparison"
    if query["where"] is not None:
        yield "where/filter"
    for expression, _ in query["columns"]:
        if expression[0] == "count":
            yield f"count/{expression[1] is None}/{expression[2]}"


def required(domain):
    return {*(f"body/{shape}" for shape in domain["bodies"]), "schema/main", "schema/temp", "source/implicit",
            "temp-reads/main", "temp-reads/temp", "guard/True", "guard/False", "occupied/True", "occupied/False",
            "count/True/None", "count/False/None", "count/False/BINARY", "count/False/NOCASE", "count/False/RTRIM",
            "where/filter", "having/comparison"}


def controls():
    value = finite_case("main", "having", False, False)
    healthy = expected(value)
    changed = {**healthy, "read": {**healthy["read"], "rows": []}}
    compare(healthy, healthy)
    try:
        compare(healthy, changed)
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "wrong-grouped-view-comparator-failure", failure.detail)
    else:
        raise Failure("grouped-view-comparator-ignored-post-group-rows")
    return {"input": value, "healthy": healthy, "mismatched": changed}


def execute(args, contract, db, worker_factory=Worker):
    require(len(list(analysis_cases())) == len(contract["analysis"]), "incomplete-grouped-view-analysis-schedule")
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return execute_schedule(args, contract, db, worker_factory, schedule, probe, measured_input=actual_shape)


def check_faults(args, db, runner=check_render_faults):
    header = "sql-semantics-sqlite/src/Ir/Schema/View/ViewSql.php"
    grouped_sql = "sql-semantics-sqlite/src/Ir/Aggregation/GroupedSql.php"
    no_guard = finite_case("main", "grouped-count", False, False)
    guarded = finite_case("main", "grouped-count", True, True)
    filter_case = finite_case("main", "having", False, False)
    return runner(args, db, [
        RenderFault("erase-grouped-view-guard", header, "($ifNotExists ? 'IF NOT EXISTS ' : '')", "''", guarded, "meaning-mismatch"),
        RenderFault("redirect-grouped-view-schema", header, "$schema->quoted()", "'temp'", no_guard, "meaning-mismatch"),
        RenderFault("erase-view-grouping", grouped_sql, "if ($keys !== [])", "if (false)", no_guard, "meaning-mismatch"),
        RenderFault("erase-view-having", grouped_sql, "if ($query->having !== null)", "if (false)", filter_case, "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("create-view-grouped", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, GroupedViewDatabase)
