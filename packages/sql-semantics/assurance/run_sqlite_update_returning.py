"""Check UPDATE new-row projection and the separate effects of AFTER triggers."""

import time
from collections import Counter

from run import Failure, Ledger, Worker, compare, require, write_json
from run_sqlite_update import observe as update
from sqlite_domain import RenderFault, check_render_faults, complete_observations, run_domain
from sqlite_update_returning_cases import (
    DECLARATIONS, FIXTURE, analyzed, boundaries, expected, finite_cases, finite_id, finite_ids, negatives, request, seeded,
)

CONTRACT_NAME = "sqlite-update-returning-v1.json"
CONTRACT = "5ef470c45a745b5348762aa4ee28499df0e2e5a1756ab2a7f7ba38e79bf36333"
NEGATIVE = "SqlSemantics\\Platform\\Sqlite\\Model\\Diagnostic\\InvalidConstruction"


def observe(db, item, sql):
    try:
        result = update(db, sql, item["bindings"])
    except RuntimeError as error:
        if not item["returning"]:
            raise
        position = item["returning"][0][0][1]
        column = item["target"][2][position]
        if str(error) != f"SQLite 1: no such column: __ztd_source.{column}":
            raise
        raise Failure("native-update-returning-alias-rejected", {"sql": sql, "error": str(error)}) from error
    restored = {
        "main": db.observe("SELECT left_value,right_value,key,computed FROM main.ir_update", {})["rows"],
        "temp": db.observe("SELECT left_value,right_value,key,computed FROM temp.ir_update", {})["rows"],
        "log": db.observe("SELECT key,old_left,old_right,new_left,new_right FROM main.ir_update_log", {})["rows"],
    }
    return {**result, "restored": restored}


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed", "sqlite-update-returning-positive-rejected", reply)
    roles = ("operation", "target", "assignments", "conflict", "where", "returning")
    require(all(item[role] == reply[role] for role in roles), "sqlite-update-returning-shape",
            {"expected": item, "actual": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    observations = {"observation": observe(db, item, reply["sql"])}
    if original is not None:
        observations["original"] = observe(db, item, original)
    if ledger is not None:
        ledger.record(key, "observed", **observations)
    for observation in observations.values():
        compare(expected(item), observation)
    if ledger is not None:
        ledger.record(key, "compared")


def controls():
    item = request()
    effect = expected(item)
    compare(effect, effect)
    observed = effect["statement"]
    later = sorted([[row[1], row[0]] for row in effect["main"]])
    altered = {
        "projection-uses-post-trigger-row": {**effect, "statement": {**observed, "rows": later}},
        "result-names-reversed": {**effect, "statement": {**observed, "names": ["l", "r"]}},
        "trigger-effect-erased": {**effect, "log": []},
        "duplicate-return-removed": {**effect, "statement": {**observed, "rows": [row for i, row in enumerate(observed["rows"])
                                                                                 if i == 0 or row != observed["rows"][i-1]]}},
        "main-not-restored": {**effect, "restored": {**effect["restored"], "main": effect["main"]}},
    }
    for name, observation in altered.items():
        try:
            compare(effect, observation)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-update-returning-control-signal", name)
        else:
            raise Failure("sqlite-update-returning-comparator-accepted", name)
    return {"input": item, "expected": effect, "rejected": altered}


def requests(specification):
    domain, settings = specification["finite"], specification["exploration"]
    for index, item in enumerate(finite_cases(domain)):
        yield f"finite/{index}", item, item
    for seed in settings["seeds"]:
        for index, item in enumerate(seeded(seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", item, item
    for index, item in enumerate(boundaries()):
        yield f"boundary/{index}", item, item
    for index, (sql, item) in enumerate(analyzed()):
        yield f"analysis/{index}", {"sql": sql, "declarations": DECLARATIONS, "bindings": item["bindings"]}, item


def forms(reply):
    yield "schema/" + reply["target"][0]
    yield "policy/" + reply["conflict"]
    fields = reply["returning"]
    slots, labels = [expr[1] for expr, _ in fields], [name for _, name in fields]
    writes = [slot for slot, _ in reply["assignments"]]
    yield f"width/{len(slots)}"
    yield f"repeated/{len(set(slots)) < len(slots)}"
    yield f"reused-label/{len(set(labels)) < len(labels)}"
    yield f"combined/{bool(set(slots) & set(writes))}"
    yield f"filter/{reply['where'] is not None}"
    for slot in slots:
        yield "column/" + reply["target"][2][slot]


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
                        "sqlite-update-returning-negative-boundary", reply)
                ledger.record(key, "rejected", response=reply)
            require(seen.keys() == finite_ids(domain) and set(seen.values()) == {1}, "sqlite-update-returning-finite-incomplete")
            required = {"schema/main", "schema/temp", "policy/inherit", "policy/abort", "policy/ignore",
                        "width/0", "width/5", "repeated/True", "reused-label/True", "filter/True", "filter/False",
                        "combined/True", "combined/False", "column/left_value", "column/right_value", "column/computed", "column/key"}
            require(required <= observed.keys(), "sqlite-update-returning-forms", sorted(required - observed.keys()))
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
    root = "sql-semantics-sqlite/src/Ir/Mutation/Update.php"
    renderer = "sql-semantics-sqlite/src/Ir/Mutation/ReturningSql.php"
    field = "$target->columns[$column->reference->slot]->quoted()"
    item = request()
    return runner(args, db, [
        RenderFault("omit-new-row-output", root, " . $where . $returning;", " . $where;", item, "meaning-mismatch"),
        RenderFault("read-another-return-slot", renderer, field, "$target->columns[0]->quoted()", item, "meaning-mismatch"),
        RenderFault("replace-return-label", renderer, "$column->name->quoted()", "'\"changed\"'", item, "meaning-mismatch"),
        RenderFault("qualify-new-row-output", renderer, field, "'\"__ztd_source\".' . " + field,
                    item, "native-update-returning-alias-rejected"),
    ], probe)


if __name__ == "__main__":
    run_domain("update-returning", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults)
