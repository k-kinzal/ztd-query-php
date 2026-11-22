"""Compare SQLite scalar concatenation and native TEXT transport."""

from functools import partial

from run import Failure, Worker, compare, require
from run_sqlite_case import probe
from sqlite_concatenation_cases import (
    FIXTURE, analyzed, boundaries, concat, expected, finite_cases, finite_id, finite_ids, forms, negatives, seeded, statement, text,
)
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-concatenation-v1.json"
CONTRACT = "0e6cf551291823e3fa6a60bcb9ffc0624b0583533c244aa68fc27c47a24842e4"
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
    return {*(f"root/{name}" for name in domain["roots"]),
            *(f"{side}/{name}" for side in ("left", "right") for name in ("concat", "integer", "null", "parameter", "text", "blob", "column"))}


def controls():
    item = statement("select-unit", [[concat(["blob", "C3"], ["blob", "A9"]), concat(["blob", "FF00"], text("b")), concat(text("x"), ["null"])]])
    prediction = expected(item)
    compare(prediction, prediction)
    complete, bytes_result, absent = prediction["rows"][0]
    variants = {
        "forget-utf8-reconstitution": [["text-bytes", "c3a9"], bytes_result, absent],
        "replace-raw-bytes": [complete, ["text", "�\0b"], absent],
        "empty-for-null": [complete, bytes_result, ["text", "x"]],
    }
    for name, row in variants.items():
        try:
            compare(prediction, {**prediction, "rows": [row]})
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-concatenation-control-signal", name)
        else:
            raise Failure("sqlite-concatenation-comparator-accepted", name)
    return {"input": item, "expected": prediction, "knownDisagreementRows": variants}


def execute(args, specification, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return execute_schedule(args, specification, db, worker_factory, schedule, observe)


def check_faults(args, db, runner=check_render_faults):
    renderer = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    left = "$this->expression($expression->left, $source, $joins)"
    right = "$this->expression($expression->right, $source, $joins)"
    body = f"'(' . {left}\n                . ' || ' . {right} . ')'"

    def witness(a, b):
        return statement("select-unit", [[concat(a, b)]])

    return runner(args, db, [
        RenderFault("reverse-text-operands", renderer, body, f"'(' . {right} . ' || ' . {left} . ')'",
                    witness(text("left"), text("right")), "meaning-mismatch"),
        RenderFault("drop-right-text-operand", renderer, body, left, witness(text("hi"), text(" all")), "meaning-mismatch"),
        RenderFault("add-instead-of-concatenate", renderer, body, f"'(' . {left} . ' + ' . {right} . ')'",
                    witness(text("2"), text("3")), "meaning-mismatch"),
        RenderFault("treat-null-as-empty-text", renderer, body,
                    f"'(' . {left} . ' || (CASE WHEN ' . {right} . ' IS NULL THEN ' . \"''\" . ' ELSE ' . {right} . ' END))'",
                    witness(text("hi"), ["null"]), "meaning-mismatch"),
    ], observe)


if __name__ == "__main__":
    run_domain("concatenation", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults)
