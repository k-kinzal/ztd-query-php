"""Compare complete SQLite range roles and their native SQL observations."""

from functools import partial

from run import Failure, Worker, compare, require
from run_sqlite_case import probe
from sqlite_between_cases import FIXTURE, analyzed, between, boundaries, expected, finite_cases, finite_id, finite_ids, forms, negatives, seeded, statement
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_result_cases import integer
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-between-v1.json"
CONTRACT = "8cf61799f6bd352e6b182ce1c8e9fc1f43d2015b1c9b3b7fa835fd38920cd639"
observe = partial(probe, prediction=expected)


def requests(specification):
    domain, settings = specification["finite"], specification["exploration"]
    for index, item in enumerate(finite_cases(domain)):
        yield f"finite/{index}", item, item
    for seed in settings["seeds"]:
        for index, item in enumerate(seeded(domain, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item
    for index, item in enumerate(boundaries()):
        yield f"boundary/{index}", item, item
    for index, (sql, declarations, item) in enumerate(analyzed()):
        yield f"analysis/{index}", {"sql": sql, "declarations": declarations, "bindings": item["bindings"]}, item


def required(domain):
    return {*(f"root/{root}" for root in domain["roots"]), "negated/False", "negated/True",
            *(f"operand/{position}/{member}" for position in ("value", "lower", "upper")
              for member in ("column", "integer", "parameter", "between", "null", "text"))}


def controls():
    item = statement("values", [[between(integer(1), ["null"], integer(-1)),
                                 between(integer(1), integer(-1), ["null"]),
                                 between(integer(2), integer(2), integer(2))]])
    correct = expected(item)
    compare(correct, correct)
    finite, unknown, inclusive = correct["rows"][0]
    variants = {"false-and-unknown": [unknown, unknown, inclusive],
                "erase-unknown": [finite, finite, inclusive], "exclusive": [finite, unknown, finite]}
    for name, row in variants.items():
        try:
            compare(correct, {**correct, "rows": [row]})
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-between-control-signal", name)
        else:
            raise Failure("sqlite-between-comparator-accepted", name)
    return {"input": item, "expected": correct, "knownDisagreementRows": variants}


def execute(args, specification, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return execute_schedule(args, specification, db, worker_factory, schedule, observe)


def check_faults(args, db, runner=check_render_faults):
    renderer = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    tested = "$this->expression($expression->operand, $source, $joins)"
    lower = "$this->expression($expression->lower, $source, $joins)"
    upper = "$this->expression($expression->upper, $source, $joins)"
    flag = "($expression->negated ? ' NOT BETWEEN ' : ' BETWEEN ')"
    body = f"return '(' . {tested}\n            . {flag}\n            . {lower} . ' AND '\n            . {upper} . ')';"

    def witness(value, low, high, negated=False):
        return statement("select-unit", [[between(integer(value), integer(low), integer(high), negated)]])

    return runner(args, db, [
        RenderFault("drop-upper-range-bound", renderer, body, f"return '(' . {tested} . ' >= ' . {lower} . ')';",
                    witness(7, 2, 4), "meaning-mismatch"),
        RenderFault("drop-lower-range-bound", renderer, body, f"return '(' . {tested} . ' <= ' . {upper} . ')';",
                    witness(1, 2, 4), "meaning-mismatch"),
        RenderFault("ignore-range-negation", renderer, body, body.replace(flag, "' BETWEEN '"),
                    witness(3, 2, 4, True), "meaning-mismatch"),
        RenderFault("swap-ordered-range-bounds", renderer, body, body.replace(lower, "PLACEHOLDER").replace(upper, lower).replace("PLACEHOLDER", upper),
                    witness(3, 2, 4), "meaning-mismatch"),
    ], observe)


if __name__ == "__main__":
    run_domain("between", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults)
