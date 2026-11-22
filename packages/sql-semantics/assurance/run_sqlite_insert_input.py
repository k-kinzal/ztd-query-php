"""Check SQLite default-input and implicit target INSERT effects."""

import time
from collections import Counter

from run import Failure, Ledger, Worker, compare, require, write_json
from run_sqlite_insert_query import observe
from sqlite_domain import RenderFault, check_render_faults, complete_observations, run_domain
from sqlite_insert_input_cases import (
    DECLARATIONS, FIXTURE, analyzed, defaults, expected, finite_cases, finite_id, finite_ids, input_shape, negatives, seeded,
)

CONTRACT_NAME = "sqlite-insert-input-v2.json"
CONTRACT = "6f2cf3ff540134b21770acd7afa69cb72ad5bf0c8a51340a48b542cea0eaa7de"
EXCEPTION = "SqlSemantics\\Platform\\Sqlite\\Model\\Diagnostic\\"


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed", "sqlite-insert-input-positive-rejected", reply)
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    observed = {"observation": observe(db, reply["sql"], item["bindings"])}
    if original is not None:
        observed["original"] = observe(db, original, item["bindings"])
    if ledger is not None:
        ledger.record(key, "observed", **observed)
    for effect in observed.values():
        compare(expected(item), effect)
    role, structure = input_shape(item)
    require(all(item[field] == reply[field] for field in ("target", "conflict", "operation"))
            and reply[role] == structure, "sqlite-insert-input-shape-mismatch", {"expected": item, "actual": reply})
    if ledger is not None:
        ledger.record(key, "compared")


def controls():
    item = defaults("temp")
    effect = expected(item)
    compare(effect, effect)
    bad = {"omit-default-write": {**effect, "statement": {**effect["statement"], "changes": 0}},
           "ignore-default-generated-column": {**effect, "temp": [[*row[:2], ["null", None]] for row in effect["temp"]]},
           "rollback-lost": {**effect, "restored": {**effect["restored"], "temp": effect["temp"]}}}
    for name, record in bad.items():
        try:
            compare(effect, record)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-insert-input-control-signal", name)
        else:
            raise Failure("sqlite-insert-input-comparator-accepted", name)
    return {"input": item, "expected": effect, "rejected": bad}


def requests(specification):
    finite, settings = specification["finite"], specification["exploration"]
    for index, item in enumerate(finite_cases(finite)):
        yield f"finite/{index}", item, item
    for seed in settings["seeds"]:
        for index, item in enumerate(seeded(finite, seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item
    for index, (sql, item) in enumerate(analyzed()):
        yield f"analysis/{index}", {"sql": sql, "declarations": DECLARATIONS, "bindings": item["bindings"]}, item


def forms(reply, item):
    yield "schema/" + reply["target"][0]
    yield "policy/" + reply["conflict"]
    yield "bindings/" + str(bool(item["bindings"]))
    if reply.get("defaults"):
        yield "input/defaults"
    elif "rows" in reply:
        yield "input/values"
    else:
        yield "input/select"
        yield "filter/" + str(reply["query"]["where"] is not None)


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
                    seen[finite_id(reply)] += 1
                if key.startswith("seed/"):
                    observed.update(forms(reply, item))
            for index, (current, status, reason) in enumerate(negatives()):
                key = f"negative/{index}"
                ledger.record(key, "attempted", input=current)
                reply = worker.request(current)
                exception = EXCEPTION + ("InvalidConstruction" if status == "rejected" else "SemanticException")
                require(reply["status"] == status and reply["exception"] == exception and reason in reply["message"],
                        "sqlite-insert-input-negative-boundary", reply)
                ledger.record(key, "rejected", response=reply)
            require(seen.keys() == finite_ids(domain) and set(seen.values()) == {1}, "sqlite-insert-input-finite-incomplete")
            mandatory = {*(f"schema/{schema}" for schema in domain["schemas"]),
                         *(f"policy/{role}" for role in domain["policies"]), *(f"input/{role}" for role in domain["inputs"]),
                         "bindings/True", "bindings/False", "filter/True", "filter/False"}
            require(mandatory <= observed.keys(), "sqlite-insert-input-unobserved-forms", sorted(mandatory - observed.keys()))
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


def check_faults(args, db, runner=check_render_faults):
    renderer = "sql-semantics-sqlite/src/Ir/Mutation/Insert.php"
    lower = "sql-semantics-sqlite/src/Ir/SourceBinding.php"
    sql, input = list(analyzed())[3]
    statement = {"sql": sql, "declarations": DECLARATIONS, "bindings": {}, "expected": input}
    return runner(args, db, [
        RenderFault("redirect-default-table", renderer, "$this->target->schema->quoted()", "'\"main\"'",
                    defaults("temp"), "meaning-mismatch"),
        RenderFault("override-default-conflict-policy", renderer, "InsertConflict::Abort => ' OR ABORT'",
                    "InsertConflict::Abort => ' OR IGNORE'", defaults(conflict="abort"), "meaning-mismatch"),
        RenderFault("reverse-implicit-write-order", lower, "foreach ($table->columns as $column)",
                    "foreach (array_reverse($table->columns) as $column)", statement, "meaning-mismatch"),
    ], lambda item, reply, database: probe(item.get("expected", item), reply, database, original=item.get("sql")))


if __name__ == "__main__":
    run_domain("insert-input", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults)
