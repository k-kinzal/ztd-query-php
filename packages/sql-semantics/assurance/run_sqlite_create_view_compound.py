"""Check completed CREATE VIEW bodies containing SQLite compound queries."""

from itertools import product
import random

from run import Failure, Worker, compare, require
from run_sqlite_create_view import CreateViewDatabase, actual_shape, expected, probe
from sqlite_create_view_cases import SCHEMAS, analyzed, col, integer, item, literal, projection, source, values
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-create-view-compound-v1.json"
CONTRACT = "6725b2032c6ae39953af88c52a1a373688701f87e1587be15604983d6ca89288"
FIXTURE = ""
OPERATIONS = ("UNION ALL", "UNION", "INTERSECT", "EXCEPT")
ARM_KINDS = ("select", "values")


def compound(first, steps, order=(), limit=None):
    return {"operation": "compound", "first": first, "steps": steps, "orderBy": list(order), "limit": limit}


def make_arm(form, rows):
    if form == "values":
        return values(rows)

    return projection([[term, f"field_{slot}"] for slot, term in enumerate(rows[0])])


def finite_case(schema, operator, forms, guard):
    left, right = forms.split("-")
    a = [integer(1), ["null"]]
    b = [integer(2), ["null"]]
    first = make_arm(left, [a, a, b])
    last = make_arm(right, [a, a, [integer(4), ["text", "side"]]])
    return item(schema, compound(first, [[operator, last]]), ["one", "two"], guard, guard and left == "values")


def finite_ids(domain):
    return set(product(domain["schemas"], domain["operators"], domain["armPairs"], domain["guard"]))


def finite_id(measured, _):
    body = measured["query"]
    require(body["operation"] == "compound" and len(body["steps"]) == 1, "unexpected-finite-compound-view-shape", body)
    [[operation, second]] = body["steps"]
    pair = "-".join((body["first"]["operation"], second["operation"]))
    return measured["schema"], operation, pair, measured["ifNotExists"]


def analysis_cases():
    one = make_arm("select", [[integer(1)]])
    rows = values([[integer(1)], [integer(1)]])
    q = compound(rows, [["UNION ALL", one]])
    yield analyzed("main", "CREATE VIEW observed_view AS VALUES(1),(1) UNION ALL SELECT 1 AS field_0", q, ["column1"])
    q = compound(make_arm("select", [[["null"]]]), [["UNION", values([[["null"]]])]])
    yield analyzed("temp", "CREATE TEMP VIEW observed_view AS SELECT NULL AS field_0 UNION VALUES(NULL)", q, ["field_0"])
    q = compound(one, [["INTERSECT", values([[integer(1)], [integer(3)]])]])
    yield analyzed("main", "CREATE VIEW observed_view AS SELECT 1 AS field_0 INTERSECT VALUES(1),(3)", q, ["field_0"])
    first = values([[integer(1)], [integer(4)], [integer(12)], [integer(12)]])
    q = compound(first, [["EXCEPT", one]], [[1, True, False]], [integer(1), None, False])
    yield analyzed("main", "CREATE VIEW observed_view(k) AS VALUES(1),(4),(12),(12) EXCEPT SELECT 1 AS field_0 ORDER BY 1 DESC LIMIT 1",
                   q, ["k"])
    bound = projection([[integer(7), "x"]], source("main", columns=("id",)), where=["comparison", ">=", col(0), integer(3)])
    q = compound(bound, [["UNION ALL", one]])
    yield analyzed("main", "CREATE VIEW observed_view AS SELECT 7 AS x FROM base WHERE id >= 3 UNION ALL SELECT 1 AS field_0",
                   q, ["x"])
    yield analyzed("temp", "CREATE TEMP VIEW observed_view AS SELECT 7 AS x FROM main.base WHERE id >= 3 UNION ALL SELECT 1 AS field_0",
                   q, ["x"])


def requests(specification):
    domain = specification["finite"]
    for index, case in enumerate(product(domain["schemas"], domain["operators"], domain["armPairs"], domain["guard"])):
        value = finite_case(*case)
        yield f"finite/{index}", value, value
    for index, (statement, result) in enumerate(analysis_cases()):
        yield f"analyze/{index}", statement, result
    for seed in specification["exploration"]["seeds"]:
        sample = random.Random(seed)
        for index in range(specification["exploration"]["casesPerSeed"]):
            width = sample.randrange(1, 5)
            arms = []
            for _ in range(sample.randrange(2, 6)):
                form = sample.choice(ARM_KINDS)
                rows = [[literal(sample.choice((None, -12, 0, 1, 13, 'y"z', "1", "cube"))) for _ in range(width)]
                        for _ in range(1 if form == "select" else sample.randrange(1, 6))]
                arms.append(make_arm(form, rows))
            query = compound(arms[0], [[sample.choice(OPERATIONS), arm] for arm in arms[1:]])
            value = item(sample.choice(SCHEMAS), query, [f'field "{slot}' for slot in range(width)],
                         sample.choice((False, True)), sample.choice((False, True)), f'merged "{sample.randrange(99999)}')
            yield f"seed/{seed}/{index}", value, value


def negatives():
    one = make_arm("select", [[integer(1)]])
    right = [["UNION ALL", one]]
    local = lambda query, names=("out",): item("main", query, list(names))
    yield local(compound(one, right), ("a", "b")), "rejected", "match the query width"
    wrong_schema = projection([[integer(7), "f"]], source("temp"))
    yield local(compound(wrong_schema, right)), "rejected", "tables in its schema"
    parameter = make_arm("select", [[["parameter", "1"]]])
    yield local(compound(parameter, right)), "rejected", "cannot contain a parameter"
    inert = values([[integer(6)], [["folded-membership", False, ["07"]]]])
    yield local(compound(one, [["UNION ALL", inert]])), "rejected", "cannot contain a parameter"
    yield local(compound(one, right, limit=[["parameter", "1"], None, False])), "rejected", "cannot contain a parameter"
    typed_column = projection([[col(0), "f"]], source("main"))
    yield local(compound(typed_column, right)), "rejected", "require no declared-type affinity"
    cast = ["cast", "TEXT", integer(1)]
    yield local(compound(make_arm("select", [[cast]]), right)), "rejected", "require no declared-type affinity"
    collated = ["collate", "NOCASE", cast]
    yield local(compound(one, [["UNION ALL", values([[collated]])]])), "rejected", "require no declared-type affinity"


def forms(measured):
    query = measured["query"]
    yield f"schema/{measured['schema']}"
    yield f"guard/{measured['ifNotExists']}"
    yield f"occupied/{measured['occupied']}"
    yield f"arms/{1 + len(query['steps'])}"
    yield f"first/{query['first']['operation']}"
    for operation, arm in query["steps"]:
        yield f"operation/{operation}"
        yield f"later/{arm['operation']}"
        if arm["operation"] == "values":
            yield f"values-rows/{len(arm['rows'])}"


def required(domain):
    return {*(f"schema/{schema}" for schema in domain["schemas"]), *(f"operation/{op}" for op in domain["operators"]),
            "guard/True", "guard/False", "occupied/True", "occupied/False", "arms/2", "arms/5", "first/select", "first/values",
            "later/select", "later/values", "values-rows/1", "values-rows/5"}


def controls():
    item = finite_case("main", "UNION ALL", "values-select", False)
    healthy = expected(item)
    wrong = {**healthy, "read": {**healthy["read"], "rows": healthy["read"]["rows"][1:]}}
    compare(healthy, healthy)
    try:
        compare(healthy, wrong)
    except Failure as failure:
        require(failure.signal == "meaning-mismatch", "wrong-compound-view-comparator-failure", failure.detail)
    else:
        raise Failure("compound-view-comparator-ignored-multiplicity")
    return {"input": item, "healthy": healthy, "mismatched": wrong}


def execute(args, contract, db, worker_factory=Worker):
    require(len(list(analysis_cases())) == len(contract["analysis"]), "incomplete-compound-view-analysis-schedule")
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return execute_schedule(args, contract, db, worker_factory, schedule, probe, measured_input=actual_shape)


def check_faults(args, db, runner=check_render_faults):
    header = "sql-semantics-sqlite/src/Ir/Schema/View/ViewSql.php"
    renderer = "sql-semantics-sqlite/src/Ir/Rows/Compound.php"
    duplicates = finite_case("main", "UNION ALL", "values-select", False)
    limited = list(analysis_cases())[3][1]
    step = "$sql .= ' ' . $step->operator->value . ' ' . $step->query->toString();"
    replacement = "$sql .= ' ' . ($step->operator->value === 'UNION ALL' ? 'UNION' : $step->operator->value) . ' ' . $step->query->toString();"
    return runner(args, db, [
        RenderFault("erase-compound-view-multiplicity", renderer, step, replacement, duplicates, "meaning-mismatch"),
        RenderFault("omit-final-view-arm", renderer, step, "", duplicates, "meaning-mismatch"),
        RenderFault("redirect-compound-view-schema", header, "$schema->quoted()", "'temp'", duplicates, "meaning-mismatch"),
        RenderFault("omit-compound-view-limit", renderer, "($this->limit === null ? '' : ' ' . $renderer->limit($this->limit))",
                    "''", limited, "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("create-view-compound", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, CreateViewDatabase)
