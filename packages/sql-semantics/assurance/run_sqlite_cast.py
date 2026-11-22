"""Compare checked SQLite CAST roles with typed independent observations."""

import time
from collections import Counter

from run import Failure, Ledger, Worker, compare, require, write_json
from run_sqlite_case import probe
from run_sqlite_scalars import query
from sqlite_case_cases import FIXTURE, expected
from sqlite_cast_cases import analyzed, boundaries, cast, finite_cases, finite_id, finite_ids, negatives, seeded
from sqlite_domain import RenderFault, check_render_faults, complete_observations, run_domain
from sqlite_relational_cases import children
from sqlite_result_cases import integer

CONTRACT_NAME = "sqlite-cast-v1.json"
CONTRACT = "0f90c2950f97a37ccc5f8628ac5c418e7dde88ec9d4d3be8601a808dab05cedd"
EXCEPTION = "SqlSemantics\\Platform\\Sqlite\\Model\\Diagnostic\\"


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


def execute(args, specification, db, worker_factory=Worker):
    domain, settings = specification["finite"], specification["exploration"]
    seen, observed = Counter(), Counter()
    comparator = controls()
    key = current = None
    with (args.out / "events.jsonl").open("w") as stream, worker_factory(args, time.monotonic() + settings["timeoutSeconds"]) as worker:
        ledger = Ledger(stream)
        try:
            for key, current, item in requests(specification):
                ledger.record(key, "attempted", input=current, expected=item)
                reply = worker.request(current)
                probe(item, reply, db, ledger, key, original=current.get("sql"))
                if key.startswith("finite/"):
                    seen[finite_id(reply, domain)] += 1
                if key.startswith("seed/"):
                    observed.update(forms(reply))
            for index, (current, status, reason) in enumerate(negatives()):
                key = f"negative/{index}"
                ledger.record(key, "attempted", input=current)
                reply = worker.request(current)
                exception = EXCEPTION + ("InvalidConstruction" if status == "rejected" else "SemanticException")
                require(reply["status"] == status and reply["exception"] == exception and reason in reply["message"],
                        "sqlite-cast-negative-boundary", reply)
                ledger.record(key, "rejected", response=reply)
            require(seen.keys() == finite_ids(domain) and set(seen.values()) == {1}, "sqlite-cast-finite-incomplete")
            required = {*("root/" + role for role in domain["roots"]), *("target/" + target for target in domain["targets"]),
                        "input/cast", "input/case", "input/parameter", "input/null", "input/blob", "input/text", "input/real", "input/integer"}
            require(required <= observed.keys(), "sqlite-cast-unobserved-forms", sorted(required - observed.keys()))
            counts = complete_observations(ledger, settings)
        except Exception as error:
            signal = error.signal if isinstance(error, Failure) else "unexpected-error"
            detail = error.detail if isinstance(error, Failure) else None
            if key in ledger.events and ledger.events[key][-1] not in ("compared", "rejected"):
                ledger.record(key, "timeout" if signal == "timeout" else "failed", signal=signal, detail=detail)
            write_json(args.out / "counterexample.json",
                       {"id": key, "original": current, "signal": signal, "failure": str(error), "detail": detail, "shrunk": None})
            raise
        return {"counts": counts, "finiteIds": sorted(seen), "forms": dict(observed), "php": worker.environment,
                "controls": comparator}


def check_faults(args, db):
    sql = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    input_item = query("select", [[cast("TEXT", integer(7))]])
    render = "'(CAST(' . $this->expression($expression->operand, $source, $joins) . ' AS ' . $expression->target->value . '))'"
    return check_render_faults(args, db, [
        RenderFault("omit-native-cast", sql, render, "$this->expression($expression->operand, $source, $joins)", input_item, "meaning-mismatch"),
        RenderFault("substitute-cast-affinity", sql, "$expression->target->value", "'BLOB'", input_item, "meaning-mismatch"),
        RenderFault("substitute-cast-operand", sql, render, "'(CAST(0 AS ' . $expression->target->value . '))'", input_item, "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("cast", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults)
