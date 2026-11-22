"""Compare numeric composition with exact signed and typed native results."""

from run import Failure, Worker, compare, require
from run_sqlite_case import probe
from run_sqlite_scalars import query
from sqlite_case_cases import FIXTURE, expected
from sqlite_domain import RenderFault, check_render_faults, run_domain
from sqlite_numeric_cases import analyzed, boundaries, finite_cases, finite_id, finite_ids, forms, negatives, operation, seeded
from sqlite_result_cases import integer
from sqlite_scalar_schedule import ScalarSchedule, execute_schedule

CONTRACT_NAME = "sqlite-numeric-operators-v1.json"
CONTRACT = "e8e031e469b39e74d2a8406e850f4c37cf3a85e9ab7cd0be1b2b194cf2a2a739"


def controls():
    item = query("select", [[operation("/", integer(-11), integer(3)), operation("%", integer(-11), integer(3))]])
    correct = expected(item)
    compare(correct, correct)
    altered = {
        "floor-division": {**correct, "rows": [[["integer", "-4"], ["integer", "-2"]]]},
        "unsigned-remainder": {**correct, "rows": [[["integer", "-3"], ["integer", "2"]]]},
        "fractional-storage": {**correct, "rows": [[["real", "-0x1.8000000000000p+1"], ["integer", "-2"]]]},
    }
    for name, observation in altered.items():
        try:
            compare(correct, observation)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-numeric-control-signal", name)
        else:
            raise Failure("sqlite-numeric-comparator-accepted", name)
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


def required(domain):
    return {"root/select", "root/values", "nested", *("operator/" + name for name in domain["operators"]),
            *("operand/" + name for name in ("column", "real", "integer", "text", "blob", "parameter", "null"))}


def execute(args, specification, db, worker_factory=Worker):
    schedule = ScalarSchedule(requests, negatives, finite_id, finite_ids, forms, required, controls)
    return execute_schedule(args, specification, db, worker_factory, schedule)


def check_faults(args, db, runner=check_render_faults):
    renderer = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    opcode = "$expression->operator->value"
    left = "$this->expression($expression->left, $source, $joins)"
    right = "$this->expression($expression->right, $source, $joins)"
    cases = [
        RenderFault(name, renderer, opcode, f"({opcode} === '{before}' ? '{after}' : {opcode})",
                    query("select", [[operation(before, integer(11), integer(3))]]), "meaning-mismatch")
        for name, before, after in (("remainder-instead-of-quotient", "/", "%"),
                                    ("intersection-instead-of-union", "|", "&"), ("reverse-shift", "<<", ">>"))
    ]
    cases.append(RenderFault("swap-quotient-operands", renderer, f"{left} . ' ' . {opcode} . ' ' . {right}",
                             f"{right} . ' ' . {opcode} . ' ' . {left}",
                             query("select", [[operation("/", integer(-11), integer(3))]]), "meaning-mismatch"))
    return runner(args, db, cases, probe)


if __name__ == "__main__":
    run_domain("numeric", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults)
