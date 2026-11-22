"""Check original SQLite wildcard SQL and its explicit complete result projection."""

import time
from collections import Counter

from run import Failure, Ledger, Worker, require, write_json
from server_wildcard_cases import PROJECTIONS, written_form
from sqlite_domain import RenderFault, check_render_faults, complete_observations, run_domain
from sqlite_wildcard_cases import SCHEMA, analyzed, expected, finite_cases, finite_id, finite_ids, negatives, seeded, shape

CONTRACT_NAME = "wildcard-projection-v2.json"
CONTRACT = "6cb667142ac182d5e6d5b4eb950963c621cec3b4f62303ad72acb8c3ce166789"


def check_observation(item, observed, sql):
    wanted = expected(item)
    require(wanted == observed, "meaning-mismatch", {"sql": sql, "expected": wanted, "observed": observed})


def probe(item, response, db, ledger=None, key=None, sql=None):
    require(response["status"] == "constructed", "sqlite-wildcard-positive-rejected", response)
    fields = ("source", "joins", "columns", "where", "orderBy")
    require(all(item[field] == response[field] for field in fields), "sqlite-wildcard-shape-mismatch",
            {"expected": {field: item[field] for field in fields}, "response": response})
    if ledger is not None:
        ledger.record(key, "constructed", response=response)
    observations = {"observation": db.observe(response["sql"], item["bindings"])}
    if sql is not None:
        observations["original"] = db.observe(sql, item["bindings"])
    if ledger is not None:
        ledger.record(key, "observed", **observations)
    for name, observed in observations.items():
        check_observation(item, observed, sql if name == "original" else response["sql"])
    if ledger is not None:
        ledger.record(key, "compared")


def controls():
    _, proof = shape("ordinary","repeated")
    measured = expected(proof)
    check_observation(proof, measured, "known wildcard layout")
    mismatches = {"removed-repeat": {**measured, "rows": measured["rows"][1:]},
                  "changed-label-order": {**measured, "names": list(reversed(measured["names"]))},
                  "removed-field": {**measured, "names": measured["names"][1:]}}
    signals = {}
    for name, observation in mismatches.items():
        try:
            check_observation(proof, observation, name)
        except Failure as failure:
            require(failure.signal == "meaning-mismatch", "sqlite-wildcard-control-signal", name)
            signals[name] = failure.signal
        else:
            raise Failure("sqlite-wildcard-comparator-accepted", name)
    return {"input": proof, "healthy": measured, "mismatches": mismatches, "signals": signals}


def requests(settings):
    for index, (request, item) in enumerate(finite_cases()):
        yield f"finite/{index}", request, item
    for seed in settings["seeds"]:
        for index, (request, item) in enumerate(seeded(seed, settings["casesPerSeed"])):
            yield f"seed/{seed}/{index}", request, item
    for index, (request, item) in enumerate(analyzed()):
        yield f"analysis/{index}", request, item


def execute(args, specification, db, worker_factory=Worker):
    domain = specification["sqlite"]
    settings = domain["exploration"]
    finite, forms = Counter(), Counter()
    comparison = controls()
    current = None
    with (args.out / "events.jsonl").open("w") as events, worker_factory(args, time.monotonic() + settings["timeoutSeconds"]) as worker:
        ledger = Ledger(events)
        try:
            for key, current, item in requests(settings):
                ledger.record(key, "attempted", input=current, expected=item)
                response = worker.request(current)
                probe(item, response, db, ledger, key, current["sql"])
                if key.startswith("finite/"):
                    finite[finite_id(response, current)] += 1
                if key.startswith("seed/"):
                    forms["projection/" + written_form(current["sql"])] += 1
                    forms[f"join/{bool(response['joins'])}"] += 1
                    forms[f"filter/{response['where'] is not None}"] += 1
            for index, (current, status, message) in enumerate(negatives()):
                key = f"negative/{index}"
                ledger.record(key, "attempted", input=current)
                reply = worker.request(current)
                require(reply["status"] == status and message in reply["message"], "sqlite-wildcard-negative-boundary", reply)
                ledger.record(key, "rejected", response=reply)
            require(finite.keys() == finite_ids(domain) and set(finite.values()) == {1}, "sqlite-wildcard-finite-incomplete", list(finite.items()))
            mandatory = {"join/True", "join/False", "filter/True", "filter/False", *(f"projection/{form}" for form in PROJECTIONS)}
            require(mandatory <= forms.keys(), "sqlite-wildcard-forms-incomplete", sorted(mandatory - forms.keys()))
            counts = complete_observations(ledger, settings)
        except Exception as error:
            signal = error.signal if isinstance(error, Failure) else "unexpected-error"
            detail = error.detail if isinstance(error, Failure) else None
            if key in ledger.events and ledger.events[key][-1] not in ("rejected", "compared"):
                ledger.record(key, "timeout" if signal == "timeout" else "failed", signal=signal, detail=detail)
            write_json(args.out / "counterexample.json", {"original": current, "signal": signal,
                                                        "failure": str(error), "detail": detail, "shrunk": None})
            raise
        return {"counts": counts, "finiteIds": sorted(finite), "forms": dict(forms),
                "php": worker.environment, "controls": comparison}


def check_faults(args, db, runner=check_render_faults):
    path = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    _, ordinary = shape("ordinary", "unqualified")
    _, joined = shape("self-join", "qualified")
    return runner(args, db, [
        RenderFault("reverse-expanded-field-order", path, "foreach ($select->columns as $column)",
                    "foreach (array_reverse($select->columns) as $column)", ordinary, "meaning-mismatch"),
        RenderFault("use-first-expanded-occurrence", path, "$this->alias($expression->source)", "$this->alias(0)",
                    joined, "meaning-mismatch"),
        RenderFault("drop-expanded-final-field", path, "foreach ($select->columns as $column)",
                    "foreach (array_slice($select->columns, 0, -1) as $column)", ordinary, "meaning-mismatch"),
    ], probe)


if __name__ == "__main__":
    run_domain("wildcard", CONTRACT_NAME, CONTRACT, SCHEMA, execute, check_faults)
