"""Compare SQLite scalar list membership and numbered binding registration."""

from functools import partial

from run import Failure, Worker, compare, require
from run_sqlite_case import probe
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_membership_cases import (
    FIXTURE, analyzed, boundaries, expected, finite_cases, finite_id, finite_ids, forms, membership, negatives, seeded, statement,
)
from sqlite_result_cases import integer
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-membership-v1.json"
CONTRACT = "a4164002596a004a50c1d2d0a85fa0a4578a18980415a0dfc59784c76854dcf7"
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
            *(f"length/{length}" for length in range(5)),
            *(f"{position}/{member}" for position in ("tested", "member")
              for member in ("column", "integer", "parameter", "membership", "null", "text"))}


def controls():
    operand = ["parameter", "02"]
    unknown = membership(integer(2), [integer(3), ["null"]])
    hit = membership(integer(2), [["null"], integer(2)])
    item = statement("values", [[membership(operand, []), unknown, hit]])
    correct = expected(item)
    compare(correct, correct)
    false, missing, true = correct["rows"][0]
    counterexamples = {
        "forgot-empty-parameters": {**correct, "parameters": {}, "parameterCount": 0},
        "unknown-as-false": {**correct, "rows": [[false, false, true]]},
        "null-overrides-match": {**correct, "rows": [[false, missing, missing]]},
    }
    for name, wrong in counterexamples.items():
        try:
            compare(correct, wrong)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-membership-control-signal", name)
        else:
            raise Failure("sqlite-membership-comparator-accepted", name)
    return {"input": item, "expected": correct, "knownDisagreements": counterexamples}


def execute(args, specification, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return execute_schedule(args, specification, db, worker_factory, schedule, observe)


def check_faults(args, db, runner=check_render_faults):
    renderer = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    list_sql = "implode(', ', $members)"
    negation = "($expression->negated ? ' NOT IN (' : ' IN (')"
    output = f"'(' . $operand . {negation} . {list_sql} . '))'"
    empty = statement("select-unit", [[membership(["parameter", "02"], [])]])
    empty["bindings"] = {}
    hit = statement("select-unit", [[membership(integer(7), [integer(4), integer(7)])]])
    inverse = statement("select-unit", [[membership(integer(7), [integer(7)], True)]])
    unknown = statement("select-unit", [[membership(integer(7), [integer(3), ["null"]])]])
    return runner(args, db, [
        RenderFault("erase-empty-membership-bindings", renderer, output, f"($members === [] ? '0' : ({output}))",
                    empty, "meaning-mismatch"),
        RenderFault("append-only-first-member", renderer, list_sql, "implode(', ', array_slice($members, 0, 1))",
                    hit, "meaning-mismatch"),
        RenderFault("ignore-membership-negation", renderer, negation, "' IN ('", inverse, "meaning-mismatch"),
        RenderFault("coerce-an-unknown-membership-result", renderer, output, f"'(CASE '. ({output}) . ' WHEN 1 THEN 1 ELSE 0 END)'",
                    unknown, "meaning-mismatch"),
    ], observe)


if __name__ == "__main__":
    run_domain("membership", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults)
