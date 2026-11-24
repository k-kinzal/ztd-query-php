"""Compare checked SQLite CAST roles with typed independent observations."""

from run import Failure, Worker, compare, require
from run_sqlite_case import probe
from run_sqlite_scalars import query
from sqlite_case_cases import FIXTURE, expected
from sqlite_cast_cases import analyzed, boundaries, cast, finite_cases, finite_id, finite_ids, negatives, seeded
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_relational_cases import children
from sqlite_result_cases import integer
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-cast-v1.json"
CONTRACT = "0f90c2950f97a37ccc5f8628ac5c418e7dde88ec9d4d3be8601a808dab05cedd"


def controls():
    item = query("select", [[cast("TEXT", ["blob", "80FF00"])]])
    prediction = expected(item)
    compare(prediction, prediction)
    altered = {
        "storage-class-changed": {**prediction, "rows": [[["blob", "80ff00"]]]},
        "byte-discarded": {**prediction, "rows": [[["text-bytes", "80ff"]]]},
        "utf8-replacement": {**prediction, "rows": [[["text", "��\0"]]]},
    }
    for name, observation in altered.items():
        try:
            compare(prediction, observation)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-cast-control-signal", name)
        else:
            raise Failure("sqlite-cast-comparator-accepted", name)
    return {"input": item, "expected": prediction, "rejected": altered}


def requests(specification):
    domain, schedule = specification["finite"], specification["exploration"]
    for index, item in enumerate(finite_cases(domain)):
        yield f"finite/{index}", item, item
    for seed in schedule["seeds"]:
        for index, item in enumerate(seeded(domain, seed, schedule["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item
    for index, item in enumerate(boundaries()):
        yield f"boundary/{index}", item, item
    for index, (sql, declarations, item) in enumerate(analyzed()):
        yield f"analysis/{index}", {"sql": sql, "declarations": declarations, "bindings": item["bindings"]}, item


def forms(reply):
    root = reply.get("operation", "select")
    yield "root/" + root
    expressions = (expr for row in reply["rows"] for expr in row) if root == "values" else (
        expr for expr, _ in reply["columns"])
    for expression in expressions:
        for operand in children(expression):
            if operand[0] == "cast":
                yield "target/" + operand[1]
                yield "input/" + operand[2][0]


def required(domain):
    return {*("root/" + role for role in domain["roots"]), *("target/" + target for target in domain["targets"]),
            "input/cast", "input/case", "input/parameter", "input/null", "input/blob", "input/text", "input/real", "input/integer"}


def execute(args, specification, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return execute_schedule(args, specification, db, worker_factory, schedule)


def check_faults(args, db, runner=check_render_faults):
    sql = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    input_item = query("select", [[cast("TEXT", integer(7))]])
    render = ("'(CAST(' . $this->expression($expression->operand, $source, $joins)\n"
              "                . ' AS ' . ($expression->typeName?->quoted() ?? $expression->target->value) . '))'")
    return runner(args, db, [
        RenderFault("omit-native-cast", sql, render, "$this->expression($expression->operand, $source, $joins)", input_item, "meaning-mismatch"),
        RenderFault("substitute-cast-affinity", sql, "$expression->target->value", "'BLOB'", input_item, "meaning-mismatch"),
        RenderFault("substitute-cast-operand", sql, render, "'(CAST(0 AS ' . $expression->target->value . '))'", input_item, "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("cast", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults)
