"""Test DISTINCT, ordering and row bounds through the isolated candidate worker."""

import time
from collections import Counter

from run import Failure, Ledger, Worker, compare, require, write_json
from sqlite_domain import RenderFault, check_render_faults, complete_observations, run_domain
from sqlite_engine import typed
from sqlite_relational_cases import DECLARATIONS, SCHEMA, case
from sqlite_result_cases import (
    GRAPH_FIELDS, LIMITS, ORDERS, PAGE, PAGE_DDL, PAGE_ROWS, PROJECTIONS,
    check_meaning, finite_cases, finite_id, finite_ids, integer, seeded_cases,
)

CONTRACT = "8cd76f55067fe32c08d14b648be3a0b886b6e34785db7bb8707f20edc2c37237"


def probe(item, response, db, ledger=None, key=None):
    require(response["status"] == "constructed", "result-positive-rejected", response)
    if ledger is not None:
        ledger.record(key, "constructed", response=response)
    for field in GRAPH_FIELDS:
        require(item[field] == response[field], "result-shape-mismatch", {"field": field, "response": response})
    observed = db.observe(response["sql"], item["bindings"], preserve_order=True)
    if ledger is not None:
        ledger.record(key, "observed", observation=observed)
    check_meaning(item, observed)
    if ledger is not None:
        ledger.record(key, "compared")


def projection(distinct=False, order=None, limit=None, bindings=None):
    return dict(case(source=PAGE, columns=PROJECTIONS[0], orders=order or (), bindings=bindings),
                distinct=distinct, limit=limit)


def analyzed_cases():
    yield "SELECT DISTINCT key AS p, value AS v FROM ir_page ORDER BY 1 ASC NULLS LAST", projection(True, ORDERS[1])
    yield ("SELECT ALL key AS p, value AS v FROM ir_page ORDER BY 1 LIMIT ?02, ?2",
           projection(order=[[1, False, True]], limit=[["parameter", "2"], ["parameter", "02"], True], bindings={"2": 2}))
    yield ("SELECT key AS p, value AS v FROM ir_page LIMIT (+3) OFFSET (-1)",
           projection(limit=[integer(3), integer(-1), False]))
    yield ("SELECT DISTINCT key AS p, value AS v FROM ir_page ORDER BY 1 DESC NULLS FIRST, 2 LIMIT -1 OFFSET 9223372036854775807",
           projection(True, ORDERS[2], [integer(-1), integer(9223372036854775807), False]))
    yield ("SELECT DISTINCT key AS p, value AS v FROM ir_page ORDER BY 1 DESC NULLS FIRST, 2 LIMIT -9223372036854775808",
           projection(True, ORDERS[2], [integer(-9223372036854775808), None, False]))
    yield ("SELECT DISTINCT key AS p, value AS v FROM ir_page ORDER BY 1 LIMIT ?2 OFFSET ?01",
           projection(True, [[1, False, True]], [["parameter", "2"], ["parameter", "01"], False], {"1": -4, "2": 3}))


def check_analyzed(sql, item, worker, db, ledger, key):
    request = {"sql": sql, "declarations": [*DECLARATIONS, PAGE_DDL], "bindings": item["bindings"]}
    ledger.record(key, "attempted", input=request, expected=item)
    response = worker.request(request)
    require(response["status"] == "constructed", "result-analysis-rejected", response)
    ledger.record(key, "constructed", response=response)
    for role in ("distinct", "limit", "orderBy"):
        require(response[role] == item[role], "result-analysis-shape-mismatch", {"role": role, "response": response})
    original = db.observe(sql, item["bindings"], preserve_order=True)
    actual = db.observe(response["sql"], item["bindings"], preserve_order=True)
    ledger.record(key, "observed", original=original, observation=actual)
    check_meaning(item, original)
    check_meaning(item, actual)
    ledger.record(key, "compared")


def check_collation(worker, db, ledger):
    key = "analysis/nocase"
    sql = "SELECT DISTINCT word AS p FROM ir_text ORDER BY 1 LIMIT 1"
    request = {"sql": sql, "declarations": DECLARATIONS, "bindings": {}}
    ledger.record(key, "attempted", input=request)
    response = worker.request(request)
    require(response["status"] == "constructed", "result-analysis-rejected", response)
    ledger.record(key, "constructed", response=response)
    before = db.observe(sql, {}, preserve_order=True)
    after = db.observe(response["sql"], {}, preserve_order=True)
    ledger.record(key, "observed", original=before, observation=after)
    for observed in (before, after):
        require(observed["rows"] in ([[typed("alpha")]], [[typed("Alpha")]]), "distinct-collation-mismatch", observed)
        compare({"names": ["p"], "parameterCount": 0, "parameters": {}, "readOnly": True, "changes": 0},
                {name: value for name, value in observed.items() if name != "rows"})
    ledger.record(key, "compared")


def check_binding(worker, db, ledger):
    for index, binding in enumerate((2.0, "2", None, 2.5, "invalid")):
        key = f"binding/{index}"
        item = projection(order=ORDERS[2], limit=[["parameter", "1"], integer(1), False], bindings={"1": binding})
        ledger.record(key, "attempted", input=item)
        response = worker.request(item)
        require(response["status"] == "constructed" and response["limit"] == item["limit"], "result-binding-shape-mismatch", response)
        ledger.record(key, "constructed", response=response)
        if index < 2:
            actual = db.observe(response["sql"], item["bindings"], preserve_order=True)
            ledger.record(key, "observed", observation=actual)
            check_meaning(dict(item, bindings={"1": 2}), actual)
        else:
            try:
                actual = db.observe(response["sql"], item["bindings"], preserve_order=True)
            except RuntimeError as error:
                require(str(error) == "SQLite 20: datatype mismatch", "unexpected-binding-error", str(error))
                ledger.record(key, "observed", evaluationError=str(error))
            else:
                raise Failure("missing-binding-error", actual)
        ledger.record(key, "compared")


def negative_cases():
    yield projection(limit=[integer(1), None, True]), "offset-first limit requires an offset"
    yield {"sql": "SELECT 1 LIMIT 1+1", "declarations": []}, "LIMIT requires an integer literal"
    yield {"sql": "SELECT 1 LIMIT 1 OFFSET NULL", "declarations": []}, "OFFSET requires an integer literal"


def execute(args, specification, db, worker_factory=Worker):
    settings = specification["exploration"]
    finite = list(finite_cases())
    items = [(f"finite/{index}", item) for index, item in enumerate(finite)]
    items.extend((f"seed/{seed}/{index}", item)
                 for seed in settings["seeds"]
                 for index, item in enumerate(seeded_cases(seed, settings["casesPerSeed"])))
    seen = Counter()
    coverage = Counter()
    current = None
    with (args.out / "events.jsonl").open("w") as stream, worker_factory(args, time.monotonic() + settings["timeoutSeconds"]) as worker:
        ledger = Ledger(stream)
        try:
            for start in range(0, len(items), 12):
                batch = items[start:start + 12]
                current = {"batch": [item for _, item in batch]}
                for key, item in batch:
                    ledger.record(key, "attempted", input=item)
                replies = worker.request(current["batch"])
                require(isinstance(replies, list) and len(replies) == len(batch), "result-batch-incomplete", replies)
                for (key, current), response in zip(batch, replies):
                    probe(current, response, db, ledger, key)
                    if key.startswith("finite/"):
                        seen[finite_id(response)] += 1
                    limit = response["limit"]
                    coverage[f"distinct/{response['distinct']}"] += 1
                    coverage["limit/none" if limit is None else f"limit/{'comma' if limit[2] else 'count-first'}"] += 1
                    if limit is not None and limit[0][0] == "parameter":
                        coverage["limit/numbered"] += 1
            require(set(seen) == finite_ids() and set(seen.values()) == {1}, "result-finite-incomplete", list(seen.items()))
            require({"distinct/False", "distinct/True", "limit/none", "limit/comma", "limit/count-first", "limit/numbered"}
                    <= coverage.keys(), "result-structural-coverage-incomplete", dict(coverage))
            for index, (sql, item) in enumerate(analyzed_cases()):
                current = {"sql": sql, "expected": item}
                check_analyzed(sql, item, worker, db, ledger, f"analysis/{index}")
            current = {"phase": "collation and binding probes"}
            check_collation(worker, db, ledger)
            check_binding(worker, db, ledger)
            for index, (current, message) in enumerate(negative_cases()):
                key = f"negative/{index}"
                ledger.record(key, "attempted", input=current)
                result = worker.request(current)
                require(result["status"] == "rejected" and message in result["message"], "result-negative-boundary-mismatch", result)
                ledger.record(key, "rejected", response=result)
            counts = complete_observations(ledger, settings)
        except Exception as failure:
            write_json(args.out / "counterexample.json", {
                "original": current, "failure": str(failure), "meta": getattr(failure, "detail", None), "shrunk": None,
            })
            raise
        return {"counts": counts, "finiteIds": sorted(seen), "coverage": dict(coverage), "php": worker.environment}


def check_faults(args, db, runner=check_render_faults):
    path = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    fixtures = [
        RenderFault("distinct", path, "$select->distinct ? 'DISTINCT ' : ''", "$select->distinct ? '' : ''",
                    projection(True), "row-bound-mismatch"),
        RenderFault("count-and-offset", path, "'LIMIT ' . $offset . ', ' . $count", "'LIMIT ' . $count . ', ' . $offset",
                    projection(order=ORDERS[2], limit=LIMITS[5]), "row-bound-mismatch"),
        RenderFault("binding-order", path, "'LIMIT ' . $offset . ', ' . $count", "'LIMIT ' . $count . ' OFFSET ' . $offset",
                    projection(order=ORDERS[2], limit=[["parameter", "2"], ["parameter", "02"], True], bindings={"2": 2}),
                    "meaning-mismatch"),
    ]
    return runner(args, db, fixtures, probe)


if __name__ == "__main__":
    run_domain("result", "sqlite-result-v1.json", CONTRACT, SCHEMA + PAGE_DDL + ";" + PAGE_ROWS, execute, check_faults)
