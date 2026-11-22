"""Replay SQLite scalar conditionals against an independent typed evaluator."""

import time
from collections import Counter

from run import Failure, Ledger, Worker, compare, require, write_json
from run_sqlite_scalars import PROFILE, query
from sqlite_case_cases import (
    FIXTURE, analyzed, boundaries, choice, expected, finite_cases, finite_id, finite_ids, forms, negatives, seeded,
)
from sqlite_domain import RenderFault, check_render_faults, complete_observations, run_domain
from sqlite_result_cases import integer

CONTRACT_NAME = "sqlite-case-v1.json"
CONTRACT = "a8a4eddad1e405a41566c48439c7b521f2c787a606d90b93b8fcbbf875fa9179"
EXCEPTION = "SqlSemantics\\Platform\\Sqlite\\Model\\Diagnostic\\"


def probe(item, reply, db, ledger=None, key=None, original=None, prediction=expected):
    require(reply["status"] == "constructed" and reply["profile"] == PROFILE, "sqlite-case-positive-rejected", reply)
    fields = ["rows", "operation"] if item.get("operation") == "values" else ["source", "columns", "where", "orderBy", "joins"]
    require(all(reply[field] == item[field] for field in fields), "sqlite-case-structure-mismatch",
            {"expected": item, "actual": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    preserve_order = bool(item.get("orderBy"))
    observed = {"observation": db.observe(reply["sql"], item["bindings"], preserve_order=preserve_order)}
    if original is not None:
        observed["original"] = db.observe(original, item["bindings"], preserve_order=preserve_order)
    if ledger is not None:
        ledger.record(key, "observed", **observed)
    reference = prediction(item)
    for observation in observed.values():
        compare(reference, observation)
    if ledger is not None:
        ledger.record(key, "compared")


def controls():
    item = query("select", [[choice(None, [[integer(1), ["parameter", "02"]]], ["parameter", "2"])]])
    observation = expected(item)
    compare(observation, observation)
    cases = {
        "lost-parameter-spelling": {**observation, "parameters": {"2": "?2"}},
        "wrong-type": {**observation, "rows": [[["text", "3"]]]},
        "dropped-row": {**observation, "rows": []},
    }
    for name, observed in cases.items():
        try:
            compare(observation, observed)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-case-control-signal", name)
        else:
            raise Failure("sqlite-case-comparator-accepted", name)
    return {"input": item, "expected": observation, "rejected": cases}


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


def execute(args, specification, db, worker_factory=Worker):
    settings = specification["exploration"]
    observed, coverage = Counter(), Counter()
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
                    observed[finite_id(reply, specification["finite"])] += 1
                if key.startswith("seed/"):
                    coverage.update(forms(reply))
            for index, (current, status, reason) in enumerate(negatives()):
                key = f"negative/{index}"
                ledger.record(key, "attempted", input=current)
                reply = worker.request(current)
                exception = EXCEPTION + ("InvalidConstruction" if status == "rejected" else "SemanticException")
                require(reply["status"] == status and reply["exception"] == exception and reason in reply["message"],
                        "sqlite-case-negative-boundary", reply)
                ledger.record(key, "rejected", response=reply)
            require(observed.keys() == finite_ids(specification["finite"]) and set(observed.values()) == {1},
                    "sqlite-case-incomplete-finite-domain", list(observed.items()))
            required = {"nested", "arity/one", "arity/multiple", "base/absent", "base/present", "fallback/absent",
                        "fallback/present", "target/select", "target/values", "operand/text", "operand/real",
                        "operand/blob", "operand/null", "operand/integer", "operand/parameter", "operand/arithmetic",
                        "operand/comparison"}
            require(required <= coverage.keys(), "sqlite-case-incomplete-exploration", sorted(required - coverage.keys()))
            counts = complete_observations(ledger, settings)
        except Exception as error:
            signal = error.signal if isinstance(error, Failure) else "unexpected-error"
            detail = error.detail if isinstance(error, Failure) else None
            if key in ledger.events and ledger.events[key][-1] not in ("compared", "rejected"):
                ledger.record(key, "timeout" if signal == "timeout" else "failed", signal=signal, detail=detail)
            write_json(args.out / "counterexample.json",
                       {"id": key, "original": current, "signal": signal, "detail": detail, "failure": str(error), "shrunk": None})
            raise
        return {"counts": counts, "finiteIds": sorted(observed), "forms": dict(coverage), "php": worker.environment,
                "controls": comparator}


def check_faults(args, db, runner=check_render_faults):
    renderer = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    first = query("select", [[choice(None, [[integer(1), integer(7)], [integer(1), integer(9)]])]])
    missing = query("select", [[choice(None, [[integer(0), integer(7)]], integer(11))]])
    base = query("select", [[choice(integer(2), [[integer(1), integer(7)], [integer(2), integer(9)]])]])
    return runner(args, db, [
        RenderFault("reverse-case-precedence", renderer, "foreach ($expression->branches as $branch)",
                    "foreach (array_reverse($expression->branches) as $branch)", first, "meaning-mismatch"),
        RenderFault("use-condition-as-result", renderer, "$this->expression($branch->then, $source, $joins)",
                    "$this->expression($branch->when, $source, $joins)", first, "meaning-mismatch"),
        RenderFault("omit-case-fallback", renderer, "if ($expression->otherwise !== null)", "if (false)",
                    missing, "meaning-mismatch"),
        RenderFault("erase-case-base", renderer, "if ($expression->base !== null)", "if (false)",
                    base, "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("case", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults)
