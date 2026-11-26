"""Compare complete SQLite views that contain shared local and recursive inputs."""

import hashlib
from itertools import product
import random

from run import Failure, Worker, compare, require
from run_sqlite_create_view import CreateViewDatabase, actual_shape, expected, probe
from run_sqlite_recursive_source import RecursiveDatabase
from sqlite_create_view_cases import SCHEMAS, analyzed, col, integer, item, literal, projection, source
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-create-view-local-v1.json"
CONTRACT = "eb0af4ed7ec9e543a9f1e45536cfcc24606abccb6629a551aba6533efad3c4a6"
FIXTURE = ""
LAYOUTS = ("values-only", "values-repeated", "values-left", "values-right", "recursive-only")
LOCAL = ["local", 0]


class LocalViewDatabase(CreateViewDatabase, RecursiveDatabase):
    """Observe registered SQLite views under the existing recursion progress budget."""

    policy_digest = hashlib.sha256(f"{CreateViewDatabase.policy_digest}:{RecursiveDatabase.policy_digest}:{CONTRACT}".encode()).hexdigest()


def local_values(rows, names=("k", "v")):
    return {"names": list(names), "rows": [[literal(value) for value in row] for row in rows]}


def recursive(anchor, end, increment=1, union_all=True):
    return {**local_values([[anchor]], ("n",)), "step": [["arithmetic", "+", col(0), integer(increment)]],
            "where": ["comparison", "<", col(0), integer(end)], "unionAll": union_all}


def one_local(definition, field):
    return {**projection(field), "local": definition}


def joined(query, definition):
    return {**query, "locals": [definition]}


def query_for(layout, schema, sample=None):
    if layout == "recursive-only":
        start = 1 if sample is None else sample.randrange(-4, 3)
        bound = start + (3 if sample is None else sample.randrange(8))
        local = recursive(start, bound, 1 if sample is None else sample.randrange(1, 4),
                          True if sample is None else sample.choice((True, False)))
        return one_local(local, [[col(0), "n"]])
    raw = [(1, 11), (1, 11), (2, None), (3, "extra")]
    if sample is not None:
        raw = [(sample.randrange(1, 6), sample.choice((None, 1, 9, "r", 'a"b'))) for _ in range(sample.randrange(1, 7))]
    definition = local_values(raw)
    if layout == "values-only":
        return one_local(definition, [[col(1), "from_local"], [col(0), "local_key"]])
    first = source(schema) if layout == "values-right" else LOCAL
    right = source(schema) if layout == "values-left" else LOCAL
    outputs = [[col(1), "left"], [col(1, 1), "right"]]
    choice = "CROSS" if layout == "values-repeated" else "INNER"
    on = ["comparison", "=", col(0), col(0, 1)] if choice == "INNER" else None
    return joined(projection(outputs, first, [[choice, right, on]]), definition)


def finite_case(schema, layout, guard):
    query = query_for(layout, schema)
    return item(schema, query, [f"v{index}" for index, _ in enumerate(query["columns"])],
                guard, guard and layout in ("values-right", "recursive-only"))


def finite_ids(domain):
    return set(product(domain["schemas"], domain["layouts"], domain["guard"]))


def observed_layout(query):
    if not query["joins"]:
        return "recursive-only" if "step" in query["local"] else "values-only"
    require(len(query["locals"]) == 1 and len(query["joins"]) == 1, "view-local-definition-not-shared", query)
    left, right = query["source"], query["joins"][0][1]
    if left == right == LOCAL:
        return "values-repeated"
    require(left == LOCAL or right == LOCAL, "view-local-occurrence-missing", query)
    return "values-left" if left == LOCAL else "values-right"


def finite_id(measured, _):
    return measured["schema"], observed_layout(measured["query"]), measured["ifNotExists"]


def analysis_cases():
    body = one_local(local_values([[21, "local"], [21, "local"], [4, None]]), [[col(1), "v"]])
    yield analyzed("main", "CREATE VIEW observed_view AS WITH base(k,v) AS (VALUES(21,'local'),(21,'local'),(4,NULL)) SELECT v FROM base",
                   body, ["v"])
    definition = local_values([[1, "left"], [2, "right"]])
    repeated = joined(projection([[col(0), "first"], [col(1, 1), "second"]], LOCAL, [["CROSS", LOCAL, None]]), definition)
    yield analyzed("main", "CREATE VIEW observed_view AS WITH shared(k,v) AS (VALUES(1,'left'),(2,'right')) "
                   "SELECT l.k AS first, r.v AS second FROM shared l CROSS JOIN shared r", repeated, ["first", "second"])
    pair = joined(projection([[col(1), "x"], [col(0, 1), "y"]], LOCAL,
                             [["CROSS", source("main", columns=("v",)), None]]), definition)
    prefix = "WITH shared(k,v) AS (VALUES(1,'left'),(2,'right')) SELECT c.v AS x, b.v AS y FROM shared c CROSS JOIN "
    yield analyzed("main", "CREATE VIEW observed_view AS " + prefix + "base b", pair, ["x", "y"])
    yield analyzed("temp", "CREATE TEMP VIEW observed_view AS " + prefix + "main.base b", pair, ["x", "y"])
    body = one_local(recursive(1, 4), [[col(0), "n"]])
    yield analyzed("main", "CREATE VIEW observed_view AS WITH RECURSIVE walk(n) AS "
                   "(VALUES(1) UNION ALL SELECT n+1 FROM walk WHERE n<4) SELECT n FROM walk", body, ["n"])
    body = one_local(local_values([[8]], ("id",)), [[col(0), "id"]])
    yield analyzed("main", "CREATE VIEW observed_view AS WITH peer(id) AS (VALUES(8)) SELECT id FROM peer", body, ["id"])


def requests(specification):
    domain = specification["finite"]
    for index, identity in enumerate(product(domain["schemas"], domain["layouts"], domain["guard"])):
        value = finite_case(*identity)
        yield f"finite/{index}", value, value
    for index, (request, result) in enumerate(analysis_cases()):
        yield f"analyze/{index}", request, result
    expanded = item("main", one_local(local_values([[1]], ("a",)), [[col(0), "x"]]), ["x"])
    yield "admission/former-local-source-refusal", expanded, expanded
    for seed in specification["exploration"]["seeds"]:
        sample = random.Random(seed)
        for index in range(specification["exploration"]["casesPerSeed"]):
            schema, layout = sample.choice(SCHEMAS), sample.choice(LAYOUTS)
            source_schema = "main" if schema == "main" else sample.choice(SCHEMAS)
            query = query_for(layout, source_schema, sample)
            value = item(schema, query, [f'n "{slot}' for slot, _ in enumerate(query["columns"])],
                         sample.choice((False, True)), sample.choice((False, True)),
                         f'local "{sample.randrange(10000)}', sample.randrange(1, 100))
            yield f"seed/{seed}/{index}", value, value


def negatives():
    def rejected(query):
        value = item("main", query, [label for _, label in query["columns"]])
        return value, "rejected", "cannot contain a parameter"

    field = [[col(0), "x"]]
    for operand in [["parameter", "7"], ["folded-membership", False, ["07"]]]:
        yield rejected(one_local({"names": ["a"], "rows": [[operand]]}, field))
    recurrence = recursive(1, 4)
    yield rejected(one_local({**recurrence, "rows": [[["parameter", "7"]]]}, field))
    yield rejected(one_local({**recurrence, "where": ["comparison", "<", col(0), ["parameter", "6"]]}, field))
    outside = query_for("values-left", "temp")
    yield item("main", outside, ["x", "y"]), "rejected", "tables in its schema"
    duplicated = query_for("values-repeated", "main")
    duplicated["locals"].append(duplicated["locals"][0])
    duplicated["joins"][0][1] = ["local", 1]
    yield item("main", duplicated, ["x", "y"]), "rejected", "must share one local definition"


def forms(measured):
    query = measured["query"]
    layout = observed_layout(query)
    yield f"layout/{layout}"
    yield f"schema/{measured['schema']}"
    yield f"guard/{measured['ifNotExists']}"
    yield f"occupied/{measured['occupied']}"
    definition = query["locals"][0] if query["joins"] else query["local"]
    if "step" in definition:
        yield f"recursive/union-all/{definition['unionAll']}"
    else:
        yield f"definition/rows/{len(definition['rows'])}"
    if layout in ("values-left", "values-right"):
        relation = query["source"] if layout == "values-right" else query["joins"][0][1]
        yield f"{measured['schema']}-reads/{relation[0]}"
    if '"' in measured["view"]:
        yield "identifier/delimiter"


def required(domain):
    return {*(f"layout/{layout}" for layout in domain["layouts"]), "schema/main", "schema/temp", "guard/True",
            "guard/False", "occupied/True", "occupied/False", "recursive/union-all/True", "recursive/union-all/False",
            "definition/rows/1", "definition/rows/6", "temp-reads/main", "temp-reads/temp", "identifier/delimiter"}


def controls():
    value = finite_case("main", "values-repeated", False)
    healthy = expected(value)
    changed = {**healthy, "read": {**healthy["read"], "rows": healthy["read"]["rows"][1:]}}
    compare(healthy, healthy)
    try:
        compare(healthy, changed)
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "wrong-local-view-comparator-failure", failure.detail)
    else:
        raise Failure("local-view-comparator-ignored-duplicate-row")
    return {"input": value, "healthy": healthy, "mismatched": changed}


def execute(args, contract, db, worker_factory=Worker):
    require(len(list(analysis_cases())) == len(contract["analysis"]), "incomplete-local-view-analysis-schedule")
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return execute_schedule(args, contract, db, worker_factory, schedule, probe, measured_input=actual_shape)


def check_faults(args, db, runner=check_render_faults):
    header = "sql-semantics-sqlite/src/Ir/Schema/View/ViewSql.php"
    renderer = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    repeated = finite_case("main", "values-repeated", False)
    guarded = finite_case("main", "values-right", True)
    progress = finite_case("main", "recursive-only", False)
    return runner(args, db, [
        RenderFault("redirect-local-view-schema", header, "$schema->quoted()", "'temp'", repeated, "meaning-mismatch"),
        RenderFault("erase-local-view-guard", header, "($ifNotExists ? 'IF NOT EXISTS ' : '')", "''", guarded, "meaning-mismatch"),
        RenderFault("change-later-local-view-occurrence", renderer, "$this->alias($expression->source) . '.' . $name",
                    "$this->alias($source instanceof LocalSource ? 0 : $expression->source) . '.' . $name", repeated, "meaning-mismatch"),
        RenderFault("change-recursive-view-step", renderer, "$operands[] = $this->expression($column->expression, $source);",
                    "$operands[] = '100';", progress, "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("create-view-local", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, LocalViewDatabase)
