"""Observe unary roles, NULL tests, and the native affinity distinction."""

from functools import partial

from run import Failure, Worker, compare, require
from run_sqlite_case import probe
from run_sqlite_scalars import query
from sqlite_case_cases import FIXTURE
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_result_cases import integer
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule
from sqlite_unary_cases import (
    affinity_witness, analyzed, boundaries, expected, expression, finite_cases, finite_id, finite_ids, forms, negatives, seeded,
)

CONTRACT_NAME = "sqlite-unary-v2.json"
CONTRACT = "56a291fac5dfef57b8616bd3c2ac4172b4790240437a59b289096fb0db9bc3a0"
observe = partial(probe, prediction=expected)


def controls():
    item = affinity_witness()
    correct = expected(item)
    compare(correct, correct)
    altered = {
        "lose-affinity-distinction": {**correct, "rows": sorted([[["integer", "1"]], [["integer", "1"]], [["integer", "0"]], [["null", None]]])},
        "collapse-null": {**correct, "rows": [[["integer", "0"]]] * 4},
        "wrong-label": {**correct, "names": ["number"]},
    }
    for name, observation in altered.items():
        try:
            compare(correct, observation)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-unary-control-signal", name)
        else:
            raise Failure("sqlite-unary-comparator-accepted", name)
    return {"input": item, "expected": correct, "rejected": altered}


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


def required(_):
    return {"root/select", "root/values", "nested", *("operator/" + token for token in ("+", "NOT", "~", "-", "IS", "IS NOT")),
            *("operand/" + kind for kind in ("integer", "real", "null", "text", "blob", "parameter", "column"))}


def execute(args, specification, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return execute_schedule(args, specification, db, worker_factory, schedule, observe)


def check_faults(args, db, runner=check_render_faults):
    renderer = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    unary = "$expression->kind->value . ' '"
    operand = "$this->expression($expression->operand, $source, $joins)"
    emission = f"{unary} . {operand}"
    binary = "$expression->operator->value"
    return runner(args, db, [
        RenderFault("erase-plus-affinity-rule", renderer, emission, f"($expression->kind->value === '+' ? '' : {unary}) . {operand}",
                    affinity_witness(), "meaning-mismatch"),
        RenderFault("complement-instead-of-not", renderer, emission, f"($expression->kind->value === 'NOT' ? '~ ' : {unary}) . {operand}",
                    query("select", [[expression("not", integer(1))]]), "meaning-mismatch"),
        RenderFault("reverse-null-test", renderer, binary, f"({binary} === 'IS' ? 'IS NOT' : {binary})",
                    query("select", [[expression("is-null", ["null"])]]), "meaning-mismatch"),
        RenderFault("replace-computed-negation", renderer, emission, f"($expression->kind->value === '-' ? '+ ' : {unary}) . {operand}",
                    query("select", [[expression("negate", integer(7))]]), "meaning-mismatch"),
    ], observe)


if __name__ == "__main__":
    run_domain("unary", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults)
