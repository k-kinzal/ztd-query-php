"""Compare each INSERT input role with its returned projection and stored effects."""

import time
from collections import Counter

from run import Failure, Ledger, Worker, compare, require, write_json
from run_sqlite_insert_query import observe
from sqlite_domain import RenderFault, check_render_faults, complete_observations, run_domain
from sqlite_insert_input_cases import input_shape
from sqlite_insert_returning_cases import (
    DECLARATIONS, FIXTURE, LAYOUTS, analyzed, boundaries, expected, finite_cases, finite_id, finite_ids, negatives, role, seeded, with_returning,
)
from run_sqlite_insert import case as values
from sqlite_result_cases import integer

CONTRACT_NAME = "sqlite-insert-returning-v1.json"
CONTRACT = "178495ad0e99d5efb98330019f52d32294b3c2aa33defbb12c8d903512c60401"
NEGATIVE = "SqlSemantics\\Platform\\Sqlite\\Model\\Diagnostic\\InvalidConstruction"


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed", "sqlite-insert-returning-positive-rejected", reply)
    field, value = input_shape(item)
    require(all(reply[key] == item[key] for key in ("operation", "target", "writes", "conflict", "returning"))
            and reply[field] == value, "sqlite-insert-returning-shape", {"expected": item, "actual": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    observations = {"observation": observe(db, reply["sql"], item["bindings"])}
    if original is not None:
        observations["original"] = observe(db, original, item["bindings"])
    if ledger is not None:
        ledger.record(key, "observed", **observations)
    for observation in observations.values():
        compare(expected(item), observation)
    if ledger is not None:
        ledger.record(key, "compared")


def controls():
    item = with_returning(values([[["null"], integer(5)]] * 2), LAYOUTS["all"])
    effect = expected(item)
    statement = effect["statement"]
    compare(effect, effect)
    stored = [row for row in effect["main"] if row[0] == ["null", None]]
    altered = {
        "drop-duplicate": {**effect, "statement": {**statement, "rows": statement["rows"][:1]}},
        "read-after-trigger": {**effect, "statement": {**statement, "rows": stored}},
        "rename-result": {**effect, "statement": {**statement, "names": ["id", "changed", "computed"]}},
        "drop-audit-log": {**effect, "log": []},
        "leave-written-rows": {**effect, "restored": {**effect["restored"], "main": effect["main"]}},
    }
    for name, observation in altered.items():
        try:
            compare(effect, observation)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-insert-returning-control-signal", name)
        else:
            raise Failure("sqlite-insert-returning-comparator-accepted", name)
    return {"input": item, "expected": effect, "rejected": altered}


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
        yield f"analysis/{index}", {"sql": sql, "declarations": DECLARATIONS, "bindings": item["bindings"]}, item


def forms(reply):
    yield "schema/" + reply["target"][0]
    yield "policy/" + reply["conflict"]
    yield "input/" + role(reply)
    fields = reply["returning"]
    slots, labels = [expr[1] for expr, _ in fields], [name for _, name in fields]
    layout = reply["target"][2]
    writes = [expr[1] for expr in reply["writes"]]
    yield f"width/{len(fields)}"
    yield f"repeated/{len(slots) > len(set(slots))}"
    yield f"reused-label/{len(labels) > len(set(labels))}"
    yield f"outside-writes/{bool(set(slots) - set(writes))}"
    yield "writes/" + ",".join(layout[slot] for slot in writes)
    for slot in slots:
        yield "column/" + layout[slot]
    if "rows" in reply:
        yield f"rows/{len(reply['rows'])}"
    if "query" in reply:
        yield f"filter/{reply['query']['where'] is not None}"


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
                    observed.update(forms(reply))
            for index, (current, reason) in enumerate(negatives()):
                key = f"negative/{index}"
                ledger.record(key, "attempted", input=current)
                reply = worker.request(current)
                require(reply["status"] == "rejected" and reply["exception"] == NEGATIVE and reason in reply["message"],
                        "sqlite-insert-returning-negative-boundary", reply)
                ledger.record(key, "rejected", response=reply)
            require(seen.keys() == finite_ids(domain) and set(seen.values()) == {1}, "sqlite-insert-returning-finite-incomplete")
            required = {"schema/main", "schema/temp", "policy/inherit", "policy/abort", "policy/ignore",
                        "input/defaults", "input/values", "input/select",
                        "width/0", "width/5", "repeated/True", "reused-label/True", "outside-writes/True",
                        "column/id", "column/payload", "column/computed", "rows/1", "rows/5",
                        "writes/", "writes/id,payload", "writes/payload,id", "filter/True", "filter/False"}
            require(required <= observed.keys(), "sqlite-insert-returning-forms", sorted(required - observed.keys()))
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
    root = "sql-semantics-sqlite/src/Ir/Mutation/Insert.php"
    renderer = "sql-semantics-sqlite/src/Ir/Mutation/ReturningSql.php"
    field = "$target->columns[$column->reference->slot]->quoted()"
    item = with_returning(values([[integer(5), integer(41)]], columns=("payload", "id")), LAYOUTS["all"])
    return runner(args, db, [
        RenderFault("omit-inserted-row-output", root, "$source . $action . $returning;", "$source . $action;", item, "meaning-mismatch"),
        RenderFault("read-other-inserted-field", renderer, field, "$target->columns[0]->quoted()", item, "meaning-mismatch"),
        RenderFault("change-insert-result-label", renderer, "$column->name->quoted()", "'\"changed\"'", item, "meaning-mismatch"),
        RenderFault("reorder-write-slots", root, "foreach ($this->writes as $column)", "foreach (array_reverse($this->writes) as $column)",
                    item, "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("insert-returning", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults)
