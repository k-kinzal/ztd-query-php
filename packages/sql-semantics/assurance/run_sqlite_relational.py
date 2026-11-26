"""Check joined SQLite IR, predicates and ordering against independent observations."""

import argparse
import collections
import json
from pathlib import Path
import time

from sqlite_relational_mutations import check as check_mutations
from run import IDENTITY_CONTRACT, IMAGE, TOOL, Failure, Ledger, Worker, compare, candidate_distribution, digest, require, shape, verify_candidate, write_json
from sqlite_engine import OBSERVER_CONTRACT, Database
from sqlite_relational_cases import (
    DECLARATIONS, EMPTY, LEFT, RIGHT, SCHEMA, case, children, expected,
    explore, finite_cases, finite_id, finite_ids, ordered, untyped,
)

CONTRACT = "0ff018427fb2cb7578a779d6e72330bd8cc14ac69afe9848afd1681feb144f1a"


def inspect_direct(worker, db, item, ledger=None, key=None):
    if ledger is not None:
        ledger.record(key, "attempted", input=item)
    return inspect_response(item, worker.request(item), db, ledger, key)


def inspect_response(item, result, db, ledger=None, key=None):
    require(result["status"] == "constructed", "relational-positive-rejected", result)
    if ledger is not None:
        ledger.record(key, "constructed", response=result)
    for field in ("source", "joins", "where", "orderBy", "columns"):
        require(result[field] == item[field], "relational-graph-differs", {"field": field, "input": item, "result": result})
    actual = db.observe(result["sql"], item["bindings"], preserve_order=True)
    if ledger is not None:
        ledger.record(key, "observed", observation=actual)
    compare(expected(item), dict(actual, rows=sorted(actual["rows"])))
    require(ordered(actual["rows"], item["orderBy"]), "relational-order-mismatch", actual["rows"])
    if ledger is not None:
        ledger.record(key, "compared")
    return result


def record_case(ledger, key, item):
    ledger.record(key, "attempted", input=item)


def analyzed_cases():
    yield (
        "SELECT a.key, b.key AS right_key, b.value FROM ir_left AS a LEFT JOIN ir_left AS b "
        "ON a.key = b.key WHERE (a.value > ?02 AND b.value IS NOT NULL) OR b.key IS NULL "
        "ORDER BY 1 DESC NULLS FIRST, 3 ASC NULLS LAST",
        {"2": 2},
    )
    yield (
        "SELECT a.key, b.key, c.value FROM ir_left a FULL JOIN ir_right b ON a.key IS b.key "
        "RIGHT JOIN ir_left c ON b.key = c.key WHERE a.value - ?2 ORDER BY 2, 3 DESC",
        {"2": 2},
    )
    yield "SELECT a.key, b.key FROM ir_left a LEFT JOIN ir_empty b ORDER BY 2 DESC, 1", {}
    yield "SELECT a.key, b.key FROM ir_left a CROSS JOIN ir_right b ON a.key != b.key ORDER BY 2, 1", {}
    yield "SELECT ?02 AS p FROM ir_left a JOIN ir_right b ON b.key = ?2 WHERE a.key <> ?003 ORDER BY 1", {"2": 1, "3": 2}
    yield "SELECT value FROM ir_left WHERE key ORDER BY 1 DESC NULLS FIRST", {}
    yield "SELECT word FROM ir_text ORDER BY 1", {}


def check_analysis(key, worker, db, sql, binds, ledger):
    item = {"sql": sql, "declarations": DECLARATIONS, "bindings": binds}
    record_case(ledger, key, item)
    response = worker.request(item)
    require(response["status"] == "constructed", "relational-analysis-rejected", response)
    ledger.record(key, "constructed", response=response)
    before = db.observe(sql, binds, preserve_order=True)
    after = db.observe(response["sql"], binds, preserve_order=True)
    ledger.record(key, "observed", original=before, observation=after)
    compare(dict(before, rows=sorted(before["rows"])), dict(after, rows=sorted(after["rows"])))
    if sql == "SELECT word FROM ir_text ORDER BY 1":
        for observation in (before, after):
            keys = [untyped(row[0]).lower() for row in observation["rows"]]
            require(keys == sorted(keys), "relational-collation-order-mismatch", observation)
    else:
        require(ordered(before["rows"], response["orderBy"]) and ordered(after["rows"], response["orderBy"]),
                "relational-order-mismatch", after)
    if key == "analysis/0":
        require(response["columns"][0][0] == ["column", 0] and response["columns"][1][0] == ["column", 0, 1]
                and response["source"][:2] == response["joins"][0][1][:2], "self-join-occurrence-mismatch", response)
    ledger.record(key, "compared")


def negative_cases():
    col = [[["column", 0], "p"]]
    yield case(source=LEFT, columns=[[["column", 0, 2], "p"]]), "visible occurrence"
    yield case(source=LEFT, joins=[["INNER", RIGHT, None]], columns=[[["column", 4, 1], "p"]]), "visible occurrence"
    yield case(source=None, joins=[["INNER", RIGHT, None]], columns=col), "Joins require a first table"
    yield case(source=LEFT, joins=[["LEFT", RIGHT, ["column", 0, 2]], ["INNER", LEFT, None]], columns=col), "visible occurrence"
    yield case(source=LEFT, columns=col, orders=[[2, False, True]]), "order position must belong to the projection"
    yield case(source=EMPTY, joins=[["CROSS", EMPTY, None]] * 64, columns=col), "at most 64 inputs"
    yield {"sql": "SELECT ir_left.key FROM ir_left JOIN ir_empty ON 1 RIGHT JOIN ir_right USING (key)", "declarations": DECLARATIONS}, "merged prefix requires"
    yield {"sql": "SELECT ir_left.key FROM ir_left JOIN ir_empty ON 1 NATURAL FULL JOIN ir_right", "declarations": DECLARATIONS}, "merged prefix requires"
    yield {"sql": "SELECT key FROM ir_left ORDER BY key", "declarations": DECLARATIONS}, "projection by ordinal"
    yield {"sql": "SELECT a.key FROM ir_left a LEFT JOIN ir_right b ON c.key = b.key LEFT JOIN ir_left c ON 1",
           "declarations": DECLARATIONS}, "visible occurrence"


def summarize_observed(response, coverage):
    coverage["joinCount"][len(response["joins"])] += 1
    coverage["ordering"][len(response["orderBy"])] += 1
    for kind, _, _ in response["joins"]:
        coverage["kinds"][kind] += 1
    roots = [expr for expr, _ in response["columns"]] + [step[2] for step in response["joins"]] + [response["where"]]
    for root in roots:
        for member in children(root):
            coverage["nodes"][member[0]] += 1
            if member[0] in ("comparison", "logical"):
                coverage["operators"][member[1]] += 1
            if member[0] == "column":
                coverage["sources"][member[2] if len(member) == 3 else 0] += 1


def checked_counts(events):
    require(bool(events.events), "relational-no-execution")
    for key, sequence in events.events.items():
        require(sequence in (["attempted", "constructed", "observed", "compared"], ["attempted", "rejected"]),
                "relational-incomplete-execution", {"id": key, "events": sequence})
    return dict(events.counts)


def relational_reductions(item):
    yield dict(item, where=None)
    yield dict(item, orderBy=[])
    yield dict(item, columns=item["columns"][:1], orderBy=[])


def execute(args, specification, db, path, worker_factory=Worker):
    settings = specification["exploration"]
    deadline = time.monotonic() + settings["timeoutSeconds"]
    observed = collections.Counter()
    coverage = collections.defaultdict(collections.Counter)
    with path.open("w") as stream, worker_factory(args, deadline) as worker:
        ledger = Ledger(stream)
        current = None
        try:
            for index, current in enumerate(finite_cases()):
                response = inspect_direct(worker, db, current, ledger, f"finite/{index}")
                observed[finite_id(response)] += 1
                summarize_observed(response, coverage)
            require(set(observed) == finite_ids() and set(observed.values()) == {1}, "relational-finite-incomplete",
                    sorted(finite_ids() - set(observed)))
            for seed in settings["seeds"]:
                for index, current in enumerate(explore(seed, settings["attemptsPerSeed"], settings["maxJoins"], settings["maxPredicateDepth"], settings["maxOrderTerms"])):
                    response = inspect_direct(worker, db, current, ledger, f"seed/{seed}/{index}")
                    summarize_observed(response, coverage)
                require(all(ledger.events.get(f"seed/{seed}/{index}", [])[-1:] == ["compared"]
                            for index in range(settings["attemptsPerSeed"])), "relational-seed-budget-mismatch", seed)
            require(coverage["sources"].keys() == {0, 1, 2} and coverage["ordering"].keys() == {0, 1, 2, 3}
                    and {"AND", "OR", "IS", "IS NOT"} <= coverage["operators"].keys(),
                    "relational-structural-alternatives-not-observed", dict(coverage))
            current = case(source=EMPTY, joins=[["CROSS", EMPTY, None]] * 63, columns=[[["column", 0, 63], "last"]])
            inspect_direct(worker, db, current, ledger, "boundary/64-sources")
            for index, (sql, bindings) in enumerate(analyzed_cases()):
                current = {"sql": sql, "bindings": bindings, "declarations": DECLARATIONS}
                check_analysis(f"analysis/{index}", worker, db, sql, bindings, ledger)
            for index, (current, reason) in enumerate(negative_cases()):
                key = f"negative/{index}"
                record_case(ledger, key, current)
                result = worker.request(current)
                require(result["status"] == "rejected" and reason in result["message"], "relational-negative-boundary-mismatch", result)
                ledger.record(key, "rejected", response=result)
            counts = checked_counts(ledger)
        except Exception as error:
            signal = error.signal if isinstance(error, Failure) else "unexpected-error"
            from counterexample_reduction import record

            record(path.with_suffix(".counterexample.json"), {
                "original": current, "signal": signal, "failure": str(error),
                "detail": error.detail if isinstance(error, Failure) else None,
            }, signal, relational_reductions, lambda item: inspect_direct(worker, db, item))
            raise
        return {"execution": counts, "finiteIds": sorted(observed), "coverage": dict(coverage), "php": worker.environment}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate", required=True, type=Path)
    parser.add_argument("--tool-autoload", required=True, type=Path)
    parser.add_argument("--sqlite-library", required=True, type=Path)
    parser.add_argument("--out", required=True, type=Path)
    parser.add_argument("--local", action="store_true")
    args = parser.parse_args()
    args.candidate = args.candidate.resolve()
    args.tool_autoload = args.tool_autoload.resolve()
    args.out = args.out.resolve()
    require(args.candidate != args.out and args.candidate not in args.out.parents, "evidence-directory-inside-candidate")
    args.out.mkdir(parents=True, exist_ok=False)
    contract = TOOL / "sqlite-relational-v4.json"
    require(digest(contract) == CONTRACT, "relational-contract-mismatch")
    specification = json.loads(contract.read_text())
    commit, distribution = candidate_distribution(args.candidate)
    write_json(args.out / "distribution.json", distribution)
    db = Database(args.sqlite_library)
    require(db.version == "3.47.2", "relational-engine-version-mismatch", db.version)
    db.execute(SCHEMA)
    scope = shape(args)
    results = execute(args, specification, db, args.out / "events.jsonl")
    mutations = check_mutations(args, db, args.out, inspect_response)
    verify_candidate(args.candidate, commit, distribution)
    write_json(args.out / "index.json", {
        "status": "provisional-sqlite-relational-domain", "candidate": commit, "candidateIdentityContract": IDENTITY_CONTRACT,
        "contract": CONTRACT, "distribution": digest(args.out / "distribution.json"),
        "isolation": "none" if args.local else IMAGE,
        "engine": {"version": db.version, "source": db.source, "libraryDigest": db.digest, "observerPolicy": OBSERVER_CONTRACT, "textObservation": db.text_observation_digest},
        "toolDigests": {p.name: digest(p) for p in TOOL.iterdir() if p.suffix in (".py", ".php", ".json")},
        "shape": scope, "checks": results, "mutations": mutations,
        "sources": ["events.jsonl", "distribution.json", "faults/", "relational-faults/"],
    })
    counts = results["execution"]
    (args.out / "summary.md").write_text(
        f"# SQLite relational evidence\n\nCandidate `{commit}`.\n\n"
        f"All {len(results['finiteIds'])} finite identities observed. {counts['compared']} comparisons and "
        f"{counts['rejected']} specified rejections recorded. "
        f"All {len(mutations)} relational mutations produced their expected signal.\n\n"
        "[Machine-readable index](index.json) · [Events](events.jsonl)\n"
    )
    print(args.out)


if __name__ == "__main__":
    main()
