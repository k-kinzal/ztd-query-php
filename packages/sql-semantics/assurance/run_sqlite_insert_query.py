"""Compare SQLite INSERT from a checked query with typed, independently assigned rows."""

import time
from collections import Counter

from run import Failure, Ledger, Worker, require, write_json
from run_sqlite_insert import observe as execute_write
from sqlite_domain import RenderFault, check_render_faults, complete_observations, run_domain
from sqlite_insert_query_cases import (
    DECLARATIONS, FIXTURE, LEFT, analyzed, case, column, expected, finite_cases, finite_id, finite_ids,
    negatives, query, seeded, shape,
)
from sqlite_result_cases import integer

CONTRACT_NAME = "sqlite-insert-query-v1.json"
CONTRACT = "009a9763066a4f9bd707f389f002f30dd9ca71382d5693318dbd7656ac077699"


def observe(db, sql, bindings):
    result = execute_write(db, sql, bindings)
    restored = {}
    for name, relation in (("main", "main.ir_insert"), ("temp", "temp.ir_insert"), ("log", "main.ir_insert_log")):
        restored[name] = db.observe("SELECT id,payload,computed FROM " + relation, {})["rows"]
    return {**result, "restored": restored}


def compare(item, actual, sql):
    prediction = expected(item)
    require(actual == prediction, "meaning-mismatch", {"sql": sql, "expected": prediction, "observed": actual})


def probe(item, response, db, ledger=None, key=None, original=None):
    require(response["status"] == "constructed", "sqlite-query-insert-positive-rejected", response)
    require(all(response[field] == item[field] for field in ("operation", "target", "conflict"))
            and response["query"] == shape(item), "sqlite-query-insert-shape-mismatch", {"request": item, "response": response})
    if ledger is not None:
        ledger.record(key, "constructed", response=response)
    observed = {"observation": observe(db, response["sql"], item["bindings"])}
    if original is not None:
        observed["original"] = observe(db, original, item["bindings"])
    if ledger is not None:
        ledger.record(key, "observed", **observed)
    for name, state in observed.items():
        compare(item, state, original if name == "original" else response["sql"])
    if ledger is not None:
        ledger.record(key, "compared")


def controls():
    item = case(query(None, [[integer(7), "p0"], [integer(12), "p1"]]))
    proof = expected(item)
    compare(item, proof, "known query effect")
    changed = {
        "changed-count": {**proof, "statement": {**proof["statement"], "changes": 0}},
        "lost-trigger": {**proof, "log": []},
        "not-restored": {**proof, "restored": {**proof["restored"], "main": proof["main"]}},
        "changed-shadow-table": {**proof, "temp": []},
    }
    signals = {}
    for label, record in changed.items():
        try:
            compare(item, record, label)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "sqlite-query-insert-fault-control", label)
            signals[label] = failure.signal
        else:
            raise Failure("sqlite-query-insert-comparator-accepted", label)
    return {"input": item, "prediction": proof, "mutants": changed, "signals": signals}


def requests(settings):
    for index, item in enumerate(finite_cases()):
        yield f"finite/{index}", item, item
    for seed in settings["seeds"]:
        for index, item in enumerate(seeded(seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item
    for index, (sql, item) in enumerate(analyzed()):
        yield f"analysis/{index}", {"sql": sql, "bindings": item["bindings"], "declarations": DECLARATIONS}, item


def sample(forms, reply):
    item = reply["query"]
    forms["scope/" + reply["target"][0]] += 1
    forms["conflict/" + reply["conflict"]] += 1
    forms["input/" + ("unit" if item["source"] is None else item["source"][1])] += 1
    for key in ("where", "limit", "distinct", "orderBy"):
        forms[f"{key}/{bool(item[key])}"] += 1
    for value, _ in item["columns"]:
        forms["field/" + value[0]] += 1


def execute(args, specification, db, worker_factory=Worker):
    settings = specification["exploration"]
    seen, forms = Counter(), Counter()
    comparison = controls()
    key = current = None
    with (args.out / "events.jsonl").open("w") as output, worker_factory(args, time.monotonic() + settings["timeoutSeconds"]) as worker:
        ledger = Ledger(output)
        try:
            for key, current, item in requests(settings):
                ledger.record(key, "attempted", input=current, expected=item)
                response = worker.request(current)
                probe(item, response, db, ledger, key, original=current.get("sql"))
                if key.startswith("finite/"):
                    seen[finite_id(response)] += 1
                if key.startswith("seed/"):
                    sample(forms, response)
            for index, (current, status, reason) in enumerate(negatives()):
                key = f"negative/{index}"
                ledger.record(key, "attempted", input=current)
                response = worker.request(current)
                require(response["status"] == status and reason in response["message"], "sqlite-query-insert-negative-boundary", response)
                ledger.record(key, "rejected", response=response)
            require(seen.keys() == finite_ids(specification) and set(seen.values()) == {1}, "sqlite-query-insert-finite-incomplete", list(seen.items()))
            mandatory = {"scope/main", "scope/temp", "conflict/inherit", "conflict/abort", "conflict/ignore",
                         "input/unit", "input/ir_left", "input/ir_empty", "field/parameter", "field/column",
                         "field/text", "field/null", "field/integer",
                         *(f"{role}/{flag}" for role in ("where", "limit", "distinct", "orderBy") for flag in (True, False))}
            require(mandatory <= forms.keys(), "sqlite-query-insert-structure-incomplete", sorted(mandatory - forms.keys()))
            counts = complete_observations(ledger, settings)
        except Exception as error:
            signal = error.signal if isinstance(error, Failure) else "unexpected-error"
            detail = error.detail if isinstance(error, Failure) else None
            if key in ledger.events and ledger.events[key][-1] not in ("compared", "rejected"):
                ledger.record(key, "timeout" if signal == "timeout" else "failed", signal=signal, detail=detail)
            write_json(args.out / "counterexample.json", {"id": key, "original": current, "signal": signal, "detail": detail,
                                                        "failure": str(error), "shrunk": None})
            raise
        return {"counts": counts, "finiteIds": sorted(seen), "forms": dict(forms),
                "php": worker.environment, "controls": comparison}


def check_faults(args, db, runner=check_render_faults):
    insert = "sql-semantics-sqlite/src/Ir/Mutation/Insert.php"
    renderer = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    example = case(query(None, [[integer(7), "p0"], [integer(12), "p1"]]))
    source = case(query(LEFT, [[column(1), "p0"]], order=[[1,False,True]]), ("payload",))
    distinct = case(query(LEFT, [[column(0), "p0"]], distinct=True, order=[[1,False,True]]), ("payload",))
    filtered = case(query(None, [[integer(7), "p0"]], where=integer(0)), ("id",))
    return runner(args, db, [
        RenderFault("reverse-query-insert-columns", insert, "implode(', ', $columns)", "implode(', ', array_reverse($columns))",
                    example, "meaning-mismatch"),
        RenderFault("wrong-query-insert-field", renderer, "$source->columns[$expression->slot]->quoted()",
                    "$source->columns[0]->quoted()", source, "meaning-mismatch"),
        RenderFault("omit-query-insert-filter", renderer, "if ($select->where !== null)", "if (false)",
                    filtered, "meaning-mismatch"),
        RenderFault("omit-query-insert-distinct", renderer, "($select->distinct ? 'DISTINCT ' : '')", "''",
                    distinct, "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("insert-query", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults)
