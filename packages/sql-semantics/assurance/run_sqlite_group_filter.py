"""Check native SQLite row filters and aggregate group filters independently."""

import time
from collections import Counter

from run import Failure, Ledger, Worker, require, write_json
from run_sqlite_grouped import FIELDS, GroupedDatabase, NEGATIVE
from sqlite_domain import RenderFault, check_render_faults, complete_observations, run_domain
from sqlite_group_filter_cases import analyzed, boundaries, finite_cases, finite_id, finite_ids, grouped, negatives, seeded
from sqlite_grouped_cases import FIXTURE, compare, count, slot, source
from sqlite_relational_cases import children
from sqlite_result_cases import integer

CONTRACT_NAME = "sqlite-group-filter-v1.json"
CONTRACT = "d68d3d3b61b234694983cb65a82f405b7b700890728849a118b086fa26caf818"


def probe(item, reply, db, ledger=None, key=None, original=None):
    require(reply["status"] == "constructed", "sqlite-group-filter-positive-rejected", reply)
    require(reply["profile"].endswith("/grouped-builtin"), "sqlite-group-filter-profile", reply)
    require(all(item[field] == reply[field] for field in (*FIELDS, "having")),
            "sqlite-group-filter-shape-mismatch", {"expected": item, "actual": reply})
    if ledger is not None:
        ledger.record(key, "constructed", response=reply)
    sql = reply["sql"]
    bindings = item["bindings"]
    observations = {"observation": db.observe(sql, bindings, preserve_order=True)}
    if original is not None:
        observations["original"] = db.observe(original, bindings, preserve_order=True)
    if ledger is not None:
        ledger.record(key, "observed", **observations)
    for name, observation in observations.items():
        compare(item, observation, original if name == "original" else sql)
    if ledger is not None:
        ledger.record(key, "compared")


def controls(db):
    row_filter = ["parameter", "02"]
    group_filter = [count(), "IS NOT", ["parameter", "2"], False]
    item = grouped(None, [], [[count(), "n"]], row_filter, group_filter)
    sql = 'SELECT count(*) AS n WHERE ?02 HAVING count(*) IS NOT ?2'
    item["bindings"]["2"] = 2
    observed = db.observe(sql, item["bindings"])
    compare(item, observed, sql)
    altered = {
        "group-omitted": {**observed, "rows": []},
        "group-duplicated": {**observed, "rows": observed["rows"] * 2},
        "label-changed": {**observed, "names": ["wrong"]},
        "wrong-count-storage": {**observed, "rows": [[["real", "0x1.0000000000000p+0"]]]},
        "later-binding-label": {**observed, "parameters": {"2": "?2"}},
    }
    for name, response in altered.items():
        try:
            compare(item, response, sql)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "sqlite-group-filter-control-signal", name)
        else:
            raise Failure("sqlite-group-filter-comparator-accepted", name)
    return {"input": item, "healthy": observed, "rejected": altered}


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


def forms(reply):
    yield "relation/" + ("unit" if reply["source"] is None else reply["source"][1])
    yield "group/" + ("keyed" if reply["keys"] else "global")
    for node in children(reply["where"]):
        yield "where/" + node[0]
    having = reply["having"]
    if having is None:
        yield "having/absent"
        return
    (_, slot_, collation), operator, threshold, first = having
    yield "operator/" + operator
    yield "threshold/" + threshold[0]
    yield "count/" + (collation or ("rows" if slot_ is None else "nonnull"))
    yield "order/" + ("threshold-first" if first else "count-first")


def execute(args, specification, db, worker_factory=Worker):
    domain, settings = specification["finite"], specification["exploration"]
    seen, observed = Counter(), Counter()
    comparator = controls(db)
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
                        "sqlite-group-filter-negative-boundary", reply)
                ledger.record(key, "rejected", response=reply)
            require(seen.keys() == finite_ids(domain) and set(seen.values()) == {1}, "sqlite-group-filter-finite-incomplete")
            required = {"relation/unit", "relation/ir_group_bag", "relation/ir_group_empty",
                        "group/keyed", "group/global", "where/logical", "where/comparison", "where/column", "where/case", "where/cast",
                        "having/absent", "threshold/integer", "threshold/null", "threshold/parameter",
                        "count/rows", "count/nonnull", "count/BINARY", "count/NOCASE", "count/RTRIM",
                        "order/count-first", "order/threshold-first", *("operator/" + name for name in domain["operators"])}
            require(required <= observed.keys(), "sqlite-group-filter-unobserved-forms", sorted(required - observed.keys()))
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
    target = "sql-semantics-sqlite/src/Ir/Aggregation/GroupedSql.php"
    bag, columns = source("bag"), [[count(), "n"]]
    nonnull = ["comparison", "IS NOT", slot(1), ["null"]]
    return runner(args, db, [
        RenderFault("omit-having", target, "if ($query->having !== null)", "if (false)",
                    grouped(bag, [], columns, None, [count(), ">", integer(20), False]), "meaning-mismatch"),
        RenderFault("reverse-having-order", target, "$condition->thresholdFirst ?", "!$condition->thresholdFirst ?",
                    grouped(bag, [], columns, None, [count(), ">", integer(1), False]), "meaning-mismatch"),
        RenderFault("wrong-having-slot", target, "$this->count($condition->count, $query, $renderer)",
                    "$this->count(new Count(new ColumnReference(0)), $query, $renderer)",
                    grouped(bag, [], columns, nonnull, [count(slot(1)), "=", integer(11), False]), "meaning-mismatch"),
        RenderFault("omit-input-filter", target, "if ($query->where !== null)", "if (false)",
                    grouped(bag, [], columns, nonnull, [count(), ">", integer(1), False]), "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("group-filter", CONTRACT_NAME, CONTRACT, FIXTURE, execute, check_faults, GroupedDatabase)
