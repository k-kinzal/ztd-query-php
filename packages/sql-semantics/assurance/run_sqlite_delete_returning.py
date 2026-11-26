"""Compare SQLite DELETE RETURNING with the independent old-row oracle."""

import time
from collections import Counter

from run import Failure, Ledger, Worker, compare, require, write_json
from run_sqlite_delete import DECLARATIONS, RETURNING_CONTRACT, SCHEMA, observe as remove
from sqlite_delete_returning_cases import (
    LAYOUTS, analyzed, boundaries, expected, finite_cases, finite_id, finite_ids, negatives, request, seeded,
)
from sqlite_domain import RenderFault, check_render_faults, complete_observations, run_domain
from sqlite_relational_cases import children

CONTRACT_NAME = "sqlite-delete-returning-v1.json"
EXCEPTION = "SqlSemantics\\Platform\\Sqlite\\Model\\Diagnostic\\InvalidConstruction"


def observe(db, item, sql):
    try:
        result = remove(db, sql, item["bindings"])
    except RuntimeError as error:
        if not item["returning"]:
            raise
        slot = item["returning"][0][0][1]
        name = item["target"][2][slot]
        if str(error) != f"SQLite 1: no such column: __ztd_source.{name}":
            raise
        raise Failure("native-returning-alias-rejected", {"sql": sql, "error": str(error)}) from error

    restored = {key: db.observe(f"SELECT key,value FROM {table}", {})["rows"] for key, table in (
        ("main", "main.ir_delete"), ("temp", "temp.ir_delete"), ("log", "main.ir_delete_log"))}
    return {**result, "restored": restored}


def probe(item, response, db, ledger=None, key=None, original=None):
    require(response["status"] == "constructed", "sqlite-returning-positive-rejected", response)
    require(all(item[role] == response[role] for role in ("target", "where", "returning", "operation")),
            "sqlite-returning-shape-mismatch", {"expected": item, "actual": response})
    if ledger is not None:
        ledger.record(key, "constructed", response=response)
    observed = {"observation": observe(db, item, response["sql"])}
    if original is not None:
        observed["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **observed)
    for result in observed.values():
        compare(expected(item), result)
    if ledger is not None:
        ledger.record(key, "compared")


def controls():
    item = request(returning=LAYOUTS["both"])
    correct = expected(item)
    compare(correct, correct)
    state = correct["statement"]
    broken = {
        "return-lost-field-names": {**correct, "statement": {**state, "names": ["value", "key"]}},
        "return-lost-duplicate": {**correct, "statement": {**state, "rows": [row for i, row in enumerate(state["rows"])
                                                                          if i == 0 or row != state["rows"][i - 1]]}},
        "return-wrong-effect": {**correct, "log": []},
    }
    for name, observed in broken.items():
        try:
            compare(correct, observed)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-returning-control-signal", name)
        else:
            raise Failure("sqlite-returning-comparator-accepted", name)
    return {"input": item, "expected": correct, "rejected": broken}


def requests(specification):
    domain, schedule = specification["finite"], specification["exploration"]
    for index, item in enumerate(finite_cases(domain)):
        yield f"finite/{index}", item, item
    for seed in schedule["seeds"]:
        for index, item in enumerate(seeded(seed, schedule["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item
    for index, item in enumerate(boundaries()):
        yield f"boundary/{index}", item, item
    for index, (sql, item) in enumerate(analyzed(specification)):
        yield f"analysis/{index}", {"sql": sql, "declarations": DECLARATIONS, "bindings": item["bindings"]}, item


def forms(reply):
    yield "schema/" + reply["target"][0]
    slots = [value[1] for value, _ in reply["returning"]]
    names = [name for _, name in reply["returning"]]
    yield f"width/{len(slots)}"
    yield f"repeated/{len(slots) != len(set(slots))}"
    yield f"same-label/{len(names) != len(set(names))}"
    for slot in slots:
        yield f"slot/{slot}"
    for node in children(reply["where"]):
        yield "predicate/" + node[0]


def execute(args, specification, db, worker_factory=Worker):
    finite, settings = specification["finite"], specification["exploration"]
    seen, observed = Counter(), Counter()
    comparator = controls()
    key = current = None
    with (args.out / "events.jsonl").open("w") as stream, worker_factory(args, time.monotonic() + settings["timeoutSeconds"]) as worker:
        ledger = Ledger(stream)
        try:
            for key, current, item in requests(specification):
                ledger.record(key, "attempted", input=current, expected=item)
                response = worker.request(current)
                probe(item, response, db, ledger, key, original=current.get("sql"))
                if key.startswith("finite/"):
                    seen[finite_id(response)] += 1
                if key.startswith("seed/"):
                    observed.update(forms(response))
            for index, (current, reason) in enumerate(negatives(specification, DECLARATIONS)):
                key = f"negative/{index}"
                ledger.record(key, "attempted", input=current)
                response = worker.request(current)
                require(response["status"] == "rejected" and response["exception"] == EXCEPTION
                        and reason in response["message"], "sqlite-returning-negative-boundary", response)
                ledger.record(key, "rejected", response=response)
            require(seen.keys() == finite_ids(finite) and set(seen.values()) == {1}, "sqlite-returning-finite-incomplete")
            minimum = {"schema/main", "schema/temp", "slot/0", "slot/1", "width/0", "width/4", "repeated/True", "same-label/True",
                       "predicate/column", "predicate/integer", "predicate/parameter", "predicate/comparison"}
            require(minimum <= observed.keys(), "sqlite-returning-unobserved-forms", sorted(minimum - observed.keys()))
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
    root = "sql-semantics-sqlite/src/Ir/Mutation/Delete.php"
    renderer = "sql-semantics-sqlite/src/Ir/Mutation/ReturningSql.php"
    item = request(returning=LAYOUTS["both"])
    field = "$target->columns[$column->reference->slot]->quoted()"
    return runner(args, db, [
        RenderFault("erase-delete-returning", root, "$target . $where . $returning;", "$target . $where;", item, "meaning-mismatch"),
        RenderFault("return-wrong-slot", renderer, field, "$target->columns[0]->quoted()", item, "meaning-mismatch"),
        RenderFault("return-wrong-names", renderer, "$column->name->quoted()", "'\"unrelated\"'", item, "meaning-mismatch"),
        RenderFault("qualify-returned-with-delete-alias", renderer, field, "'\"__ztd_source\".' . " + field,
                    item, "native-returning-alias-rejected"),
    ], probe)


if __name__ == "__main__":
    run_domain("delete-returning", CONTRACT_NAME, RETURNING_CONTRACT, SCHEMA, execute, check_faults)
