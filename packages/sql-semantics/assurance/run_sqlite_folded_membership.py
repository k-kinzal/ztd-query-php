"""Compare the surviving binding footprint of SQLite empty-list folding."""

from functools import partial

from run import Failure, Worker, compare, require
from run_sqlite_case import probe
from run_sqlite_scalars import query
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_folded_membership_cases import analyzed, boundaries, expected, finite_cases, finite_id, finite_ids, folded, forms, negatives, seeded
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-folded-membership-v1.json"
CONTRACT = "23406667099181edb6465612e4132cb3c9b0c0cf1c58341dd1a158a8040fd084"
FIXTURE = ""
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
    for index, (sql, item) in enumerate(analyzed()):
        yield f"analysis/{index}", {"sql": sql, "declarations": [], "bindings": item["bindings"]}, item


def required(domain):
    return {"root/select", "root/values", "match/False", "match/True", "nested", "repeated-slot",
            *(f"size/{length}" for length in domain["lengths"])}


def controls():
    item = query("select", [[folded(True, "02", "2", "7")]])
    prediction = expected(item)
    compare(prediction, prediction)
    variants = {
        "lose-numbered-label": {**prediction, "parameters": {"2": "?2", "7": "?7"}},
        "forget-param-gap": {**prediction, "parameterCount": 2},
        "forget-value": {**prediction, "rows": [[["integer", "0"]]]},
    }
    for name, mismatch in variants.items():
        try:
            compare(prediction, mismatch)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-folded-membership-control-signal", name)
        else:
            raise Failure("sqlite-folded-membership-comparator-accepted", name)
    return {"input": item, "expected": prediction, "knownDisagreements": variants}


def execute(args, specification, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return execute_schedule(args, specification, db, worker_factory, schedule, observe)


def check_faults(args, db, runner=check_render_faults):
    renderer = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    listing = "foreach ($expression->bindings as $binding)"
    condition = "if ($expression->bindings === [])"
    result = "return $expression->matches ? '1' : '0';"

    def witness(matches, *labels):
        item = query("select", [[folded(matches, *labels)]])
        item["bindings"] = {}
        return item

    return runner(args, db, [
        RenderFault("discard-binding-footprint", renderer, condition, "if (true)", witness(False, "02"), "meaning-mismatch"),
        RenderFault("reorder-lexical-binding-aliases", renderer, listing, "foreach (array_reverse($expression->bindings) as $binding)",
                    witness(True, "02", "2"), "meaning-mismatch"),
        RenderFault("change-folded-value", renderer, result, "return $expression->matches ? '0' : '1';",
                    witness(False), "meaning-mismatch"),
        RenderFault("remove-highest-numbered-slot", renderer, listing, "foreach (array_slice($expression->bindings, 0, -1) as $binding)",
                    witness(True, "02", "7"), "meaning-mismatch"),
    ], observe)


if __name__ == "__main__":
    run_domain("folded-membership", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults)
