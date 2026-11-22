"""Check resolved truth words, group filters and collision-free SQLite Boolean tests."""

from run import Failure, Worker, compare, require
from run_sqlite_case import probe as scalar
from run_sqlite_group_filter import GroupedDatabase, probe as grouped
from run_sqlite_scalars import query
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_result_cases import integer
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule
from sqlite_truth_cases import (
    FIXTURE, analyzed, boundaries, expected, finite_cases, finite_id, finite_ids, forms, negatives, seeded, shadow_witness, test,
)

CONTRACT_NAME = "sqlite-truth-v2.json"
CONTRACT = "f32372203fcd2896fd116d876d7d74271f4994821c8b8a237f66006a7299685e"


def observe(item, response, db, ledger=None, key=None, original=None):
    if item.get("operation") == "grouped":
        grouped(item, response, db, ledger, key, original)
    else:
        scalar(item, response, db, ledger, key, original, expected)


def controls():
    item = query("select", [[test(["null"], False), test(["null"], True, True), test(integer(7))]])
    correct = expected(item)
    compare(correct, correct)
    a, b, c = correct["rows"][0]
    altered = {
        "null-false": {**correct, "rows": [[["integer", "1"], b, c]]},
        "null-complement": {**correct, "rows": [[a, ["integer", "0"], c]]},
        "compare-with-one": {**correct, "rows": [[a, b, ["integer", "0"]]]},
    }
    for name, result in altered.items():
        try:
            compare(correct, result)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-truth-control-signal", name)
        else:
            raise Failure("sqlite-truth-comparator-accepted", name)
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
    return {"root/select", "root/values", "nested", *(f"truth/{a}/{b}" for a in (False, True) for b in (False, True)),
            *("input/" + name for name in ("column", "arithmetic", "unary", "real", "integer", "text", "blob", "parameter", "null"))}


def execute(args, specification, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return execute_schedule(args, specification, db, worker_factory, schedule, observe)


def check_faults(args, db, runner=check_render_faults):
    renderer = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    output = "return '(CASE WHEN ' . $condition . ' THEN ' . $matched . ' ELSE ' . $unmatched . ' END)';"
    return runner(args, db, [
        RenderFault("mistake-truth-for-integer-equality", renderer, output,
                    "return '(' . $value . ' IS ' . ($expression->desired ? '1' : '0') . ')';",
                    query("select", [[test(integer(7))]]), "meaning-mismatch"),
        RenderFault("treat-null-as-false", renderer, "'(NOT ' . $value . ')'",
                    "'((NOT ' . $value . ') OR (' . $value . ' IS NULL))'",
                    query("select", [[test(["null"], False)]]), "meaning-mismatch"),
        RenderFault("ignore-negated-truth-test", renderer, "$expression->negated ? ['0', '1'] : ['1', '0']",
                    "['1', '0']", query("select", [[test(integer(7), True, True)]]), "meaning-mismatch"),
        RenderFault("resolve-truth-word-again", renderer, output, "return '(' . $value . ' IS TRUE)';",
                    shadow_witness(), "meaning-mismatch"),
    ], observe)


if __name__ == "__main__":
    run_domain("truth", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, GroupedDatabase)
