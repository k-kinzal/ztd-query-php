"""Test DISTINCT, ordering and row bounds through the isolated candidate worker."""

import argparse
import difflib
import json
import subprocess
import time
from collections import Counter
from pathlib import Path

from run import IMAGE, TOOL, Failure, Ledger, Worker, compare, digest, manifest, require, shape, verify_distribution, write_json
from sqlite_engine import Database, typed
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


def execute(args, specification, db):
    settings = specification["exploration"]
    finite = list(finite_cases())
    items = [(f"finite/{index}", item) for index, item in enumerate(finite)]
    items.extend((f"seed/{seed}/{index}", item)
                 for seed in settings["seeds"]
                 for index, item in enumerate(seeded_cases(seed, settings["casesPerSeed"])))
    seen = Counter()
    coverage = Counter()
    current = None
    with (args.out / "events.jsonl").open("w") as stream, Worker(args, time.monotonic() + settings["timeoutSeconds"]) as worker:
        ledger = Ledger(stream)
        try:
            for start in range(0, len(items), 12):
                batch = items[start:start + 12]
                for key, item in batch:
                    ledger.record(key, "attempted", input=item)
                replies = worker.request([item for _, item in batch])
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
            for key, path in ledger.events.items():
                require(path in (["attempted", "constructed", "observed", "compared"], ["attempted", "rejected"]),
                        "result-unbalanced-execution", {"id": key, "events": path})
        except Exception as failure:
            write_json(args.out / "counterexample.json", {
                "original": current, "failure": str(failure), "meta": getattr(failure, "detail", None), "shrunk": None,
            })
            raise
        return {"counts": dict(ledger.counts), "finiteIds": sorted(seen), "coverage": dict(coverage), "php": worker.environment}


def check_mutations(args, db):
    fixtures = [
        ("distinct", "$select->distinct ? 'DISTINCT ' : ''", "$select->distinct ? '' : ''",
         projection(True), "row-bound-mismatch"),
        ("count-and-offset", "'LIMIT ' . $offset . ', ' . $count", "'LIMIT ' . $count . ', ' . $offset",
         projection(order=ORDERS[2], limit=LIMITS[5]), "row-bound-mismatch"),
        ("binding-order", "'LIMIT ' . $offset . ', ' . $count", "'LIMIT ' . $count . ' OFFSET ' . $offset",
         projection(order=ORDERS[2], limit=[["parameter", "2"], ["parameter", "02"], True], bindings={"2": 2}),
         "meaning-mismatch"),
    ]
    path = "sql-semantics-sqlite/src/Ir/SelectSql.php"
    original_file = args.candidate / "packages" / path
    original = original_file.read_text()
    root = args.out / "faults"
    results = []
    with Worker(args, time.monotonic() + 45) as worker:
        for _, _, _, item, _ in fixtures:
            probe(item, worker.request(item), db)
    for name, before, after, item, signal in fixtures:
        require(original.count(before) == 1, "result-mutation-anchor", name)
        mutant = original.replace(before, after)
        overlay = root / name
        mutated = overlay / path
        mutated.parent.mkdir(parents=True)
        mutated.write_text(mutant)
        require(digest(mutated) != digest(original_file), "result-mutation-absent", name)
        (overlay / "injected.diff").write_text("".join(difflib.unified_diff(
            original.splitlines(keepends=True), mutant.splitlines(keepends=True), f"a/{path}", f"b/{path}")))
        with Worker(args, time.monotonic() + 45, overlay) as worker:
            try:
                probe(item, worker.request(item), db)
            except Failure as failure:
                row = {"case": name, "file": path, "source": digest(original_file), "mutation": digest(mutated),
                       "input": item, "control": "passed", "expected": signal, "observed": failure.signal, "detail": failure.detail}
                write_json(overlay / "result.json", row)
                require(failure.signal == signal, "wrong-result-mutation-signal", row)
                results.append(row)
            else:
                raise Failure("undetected-result-mutation", name)
    return results


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
    require(args.out != args.candidate and args.candidate not in args.out.parents, "evidence-directory-inside-candidate")
    args.out.mkdir(parents=True, exist_ok=False)
    contract = TOOL / "sqlite-result-v1.json"
    require(digest(contract) == CONTRACT, "result-contract-mismatch")
    specification = json.loads(contract.read_text())
    commit = subprocess.check_output(["git", "-C", str(args.candidate), "rev-parse", "HEAD"], text=True).strip()
    distribution = manifest(args.candidate)
    write_json(args.out / "distribution.json", distribution)
    db = Database(args.sqlite_library)
    require(db.version == "3.47.2", "result-engine-version-mismatch", db.version)
    db.execute(SCHEMA)
    db.execute(PAGE_DDL + ";" + PAGE_ROWS)
    scope = shape(args)
    checked = execute(args, specification, db)
    faults = check_mutations(args, db)
    verify_distribution(distribution, manifest(args.candidate))
    write_json(args.out / "index.json", {
        "status": "provisional-sqlite-result-domain", "candidate": commit, "contract": CONTRACT,
        "distribution": digest(args.out / "distribution.json"), "isolation": "none" if args.local else IMAGE,
        "engine": {"version": db.version, "source": db.source, "libraryDigest": db.digest},
        "tools": {path.name: digest(path) for path in TOOL.iterdir() if path.suffix in (".php", ".py", ".json")},
        "shape": scope, "checks": checked, "faults": faults,
    })
    counts = checked["counts"]
    (args.out / "summary.md").write_text(
        f"# SQLite result evidence\n\nCandidate `{commit}`.\n\n"
        f"All {len(checked['finiteIds'])} finite identities observed. The runner compared {counts['compared']} results, "
        f"confirmed {counts['rejected']} specified construction rejections and detected all {len(faults)} injected faults.\n\n"
        "[Evidence index](index.json) · [Events](events.jsonl)\n"
    )
    print(args.out)


if __name__ == "__main__":
    main()
