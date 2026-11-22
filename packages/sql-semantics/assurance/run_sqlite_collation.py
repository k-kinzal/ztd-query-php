"""Compare explicit SQLite builtin collation roles with native evaluation."""

from functools import partial

from run import Failure, Worker, compare, require
from run_sqlite_case import probe
from sqlite_collation_cases import (
    FIXTURE, analyzed, boundaries, collate, compare as expression, expected, finite_cases, finite_id, finite_ids,
    forms, negatives, nul_witness, seeded, statement, text,
)
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-collation-v1.json"
CONTRACT = "e6095ab2d70045c9ab7f0d49ea8c06248dd2195dd0828968690a35221d45dcd6"
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
    return {*(f"root/{name}" for name in domain["roots"]), *(f"collation/{name}" for name in domain["collations"]),
            *(f"operator/{name}" for name in domain["operators"]),
            *(f"operand/{name}" for name in ("case", "collate", "integer", "null", "parameter", "text"))}


def controls():
    item = nul_witness()
    prediction = expected(item)
    compare(prediction, prediction)
    equal, longer, binary = prediction["rows"][0]
    disagreements = {"fold-after-null": [binary, longer, binary],
                     "ignore-length": [equal, equal, binary], "fold-binary": [equal, longer, equal]}
    for name, row in disagreements.items():
        try:
            compare(prediction, {**prediction, "rows": [row]})
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-collation-control-signal", name)
        else:
            raise Failure("sqlite-collation-comparator-accepted", name)
    return {"input": item, "expected": prediction, "knownDisagreementRows": disagreements}


def execute(args, specification, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return execute_schedule(args, specification, db, worker_factory, schedule, observe)


def check_faults(args, db, runner=check_render_faults):
    renderer = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    label = "$expression->collation->value"

    def witness(sequence, left, right):
        return statement("select-unit", [[expression("=", collate(sequence, left), right)]])

    return runner(args, db, [
        RenderFault("binary-for-nocase", renderer, "' COLLATE ' . " + label, "' COLLATE BINARY'",
                    witness("NOCASE", text("a"), text("A")), "meaning-mismatch"),
        RenderFault("binary-for-rtrim", renderer, "' COLLATE ' . " + label, "' COLLATE BINARY'",
                    witness("RTRIM", text("a "), text("a")), "meaning-mismatch"),
        RenderFault("ignore-outer-collation", renderer, "' COLLATE ' . " + label,
                    "' COLLATE ' . ($expression->operand instanceof CollatedExpression ? $expression->operand->collation->value : " + label + ")",
                    witness("BINARY", collate("NOCASE", text("a")), text("A")), "meaning-mismatch"),
        RenderFault("erase-explicit-collation", renderer, " . ' COLLATE ' . " + label, "",
                    witness("NOCASE", text("a"), text("A")), "meaning-mismatch"),
    ], observe)


if __name__ == "__main__":
    run_domain("collation", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults)
