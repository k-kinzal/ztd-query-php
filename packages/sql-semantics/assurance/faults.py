"""Known faults with independently checked signals and preserved patches."""

import collections
import difflib
import hashlib
import io
import json
import time

from cases import MAIN, case, expected, finite_identity
from run import (
    TOOL, Failure, Ledger, Worker, check_comparator, compare_response, digest, finite_complete, manifest, negative,
    positive, require, shape, verify_contract, verify_distribution, write_json,
)


IR = "sql-semantics-sqlite/src/Ir/"
BUDGET_RECORD = "initial-fault-budget-v1.json"
BUDGET_DIGEST = "9bce1f665cc57c3631bab051f505fb5cd35e277c3e81ccad9ef4a97ee46553ad"


def fault_budget():
    path = TOOL / BUDGET_RECORD
    require(digest(path) == BUDGET_DIGEST, "initial-fault-budget-mismatch")
    data = json.loads(path.read_text())
    return {"record": BUDGET_RECORD, "sha256": BUDGET_DIGEST,
            "startupSeconds": data["workerStartupSeconds"], "executionSeconds": data["workerExecutionSeconds"]}



def replace_once(source, before, after):
    require(source.count(before) == 1, "fault-patch-anchor", before)
    return source.replace(before, after, 1)


def expanded_ids(worker, db, generate):
    observed = collections.Counter()
    inputs = list(generate())
    responses = worker.request(inputs)
    require(isinstance(responses, list) and len(responses) == len(inputs), "worker-batch-incomplete")
    for item, response in zip(inputs, responses):
        require(time.monotonic() < worker.deadline, "timeout", "finite fault control exhausted its time budget")
        response, _ = compare_response(db, item, response)
        observed[finite_identity(response["source"], response["columns"])] += 1
    finite_complete(observed)


def check_events(ledger_type):
    ledger = ledger_type(io.StringIO())
    ledger.record("comparison-case", "attempted")
    ledger.record("comparison-case", "constructed")
    ledger.record("comparison-case", "compared")
    ledger.verify()


def exercise(args, db, distribution):
    budget = fault_budget()
    root = args.out / "faults"
    root.mkdir()
    simple = case(None, [["integer", "42"]])
    column = case(MAIN, [["column", 0]])
    arithmetic = case(MAIN, [["arithmetic", "-", ["column", 0], ["column", 1]]])
    empty = case(None, [])

    def worker_check(test, overlay):
        with Worker(args, time.monotonic() + budget["executionSeconds"], overlay,
                    startup_timeout=budget["startupSeconds"]) as worker:
            return test(worker)

    def record(key, path, original, mutant, signal, fixture, control, check):
        folder = root / key
        folder.mkdir(mode=0o755)
        mutation = folder / path
        mutation.parent.mkdir(parents=True, exist_ok=True)
        mutation.write_text(mutant)
        patch = "".join(difflib.unified_diff(original.splitlines(True), mutant.splitlines(True), f"a/{path}", f"b/{path}"))
        require(bool(patch) and mutation.read_text() == mutant, "fault-not-applied", key)
        (folder / "injected.diff").write_text(patch)
        record = {
            "id": key, "affectedPath": path, "mutatedDigest": digest(mutation),
            "baselineDigest": hashlib.sha256(original.encode()).hexdigest(),
            "input": fixture, "expectedSignal": signal,
        }
        try:
            control()
        except Failure as failure:
            record["control"] = "failed"
            record["controlSignal"] = failure.signal
            record["detail"] = failure.detail
            write_json(folder / "result.json", record)
            raise
        record["control"] = "passed"
        try:
            check(folder, mutation)
        except Failure as fault:
            record["actualSignal"] = fault.signal
            record["detail"] = fault.detail
            write_json(folder / "result.json", record)
            require(fault.signal == signal, "incorrect-fault-signal", record)
        else:
            raise Failure("undetected-required-fault", record)
        return record

    results = []

    def patch_model(key, filename, before, after, signal, fixture, test, static=False):
        path = IR + filename + ".php"
        original = (args.candidate / "packages" / path).read_text()
        mutant = replace_once(original, before, after)
        if static:
            control = lambda: shape(args)
            check = lambda folder, _: shape(args, folder)
        else:
            control = lambda: worker_check(test, None)
            check = lambda folder, _: worker_check(test, folder)
        results.append(record(key, path, original, mutant, signal, fixture, control, check))

    patch_model("F01", "Select",
                "result columns.', 1)", "result columns.', 0)", "negative-boundary-mismatch",
                empty, lambda worker: negative(worker, empty, "nonempty list"))
    patch_model("F02", "From/SelectBounds",
                "count($results) <= 2000", "false", "positive-construction-failed",
                simple, lambda worker: positive(worker, db, simple))
    patch_model("F03", "SelectSql",
                "$source->columns[$expression->slot]->quoted()", "$source->columns[1 - $expression->slot]->quoted()",
                "meaning-mismatch", column, lambda worker: positive(worker, db, column))
    patch_model("F04", "TableSource",
                "readonly array $columns", "array $columns", "shape-mismatch",
                "source column list can change", None, static=True)
    patch_model("F05", "Select",
                "public readonly TableSource|LocalSource|null $source = null",
                "public readonly TableSource|LocalSource|null $source = null, private readonly string $originalSql = ''",
                "shape-mismatch", "raw original SQL is retained in a root property", None, static=True)
    patch_model("F06", "Select",
                "return (new SelectSql())->render($this);", 'return "SELECT 1 AS ignored";',
                "meaning-mismatch", simple, lambda worker: positive(worker, db, simple))
    patch_model("F07", "SelectSql",
                """'(' . $this->expression($expression->left, $source, $joins) . ' ' . $expression->operator->value . ' ' . $this->expression($expression->right, $source, $joins) . ')'""",
                "$this->expression($expression->left, $source, $joins)", "meaning-mismatch",
                arithmetic, lambda worker: positive(worker, db, arithmetic))

    def execute_tool(original_path, source):
        namespace = {"__name__": "mutated_checker", "__file__": str(original_path)}
        exec(compile(source, str(original_path), "exec"), namespace)
        namespace["Failure"] = Failure
        return namespace

    def patch_tool(key, file, before, after, signal, fixture, verify):
        path = TOOL / file
        original = path.read_text()
        mutant = replace_once(original, before, after)
        results.append(record(key, file, original, mutant, signal, fixture,
                              lambda: verify(execute_tool(path, original)),
                              lambda _, mutation: verify(execute_tool(path, mutation.read_text()))))

    patch_tool("F08", "cases.py", "for atom in atoms:", "for atom in atoms[:1]:", "finite-domain-incomplete",
               "both source choices, omitting all but one one-atom projection",
               lambda ns: worker_check(lambda worker: expanded_ids(worker, db, ns["finite_cases"]), None))
    patch_tool("F09", "run.py", "require(expected_observation == actual,", "require(True,", "comparator-accepted-known-difference",
               expected(case(MAIN, [["column", 0], ["parameter", "02"]], {"2": -6})),
               lambda ns: check_comparator(ns["compare"]))
    patch_tool("F10", "run.py", "        self.events.setdefault(key, []).append(event)",
               '        if event == "compared":\n            return\n        self.events.setdefault(key, []).append(event)',
               "unbalanced-execution", "one completed comparison without its observed terminal event",
               lambda ns: check_events(ns["Ledger"]))

    contract = TOOL / "contract-v1.json"
    source = contract.read_text()
    weak = replace_once(source, "SQLite storage classes and values including NULL", "displayed values only")
    results.append(record("F11", contract.name, source, weak, "contract-mismatch",
                          "storage-class observation removed", lambda: verify_contract(contract),
                          lambda _, mutation: verify_contract(mutation)))

    path = IR + "Select.php"
    original = (args.candidate / "packages" / path).read_text()
    results.append(record("F12", path, original, original + "\n", "distribution-mismatch",
                          "a byte added to the distributed SELECT after evidence collection",
                          lambda: verify_distribution(distribution, manifest(args.candidate)),
                          lambda folder, _: verify_distribution(distribution, manifest(args.candidate, folder))))
    return [{"id": fault["id"], "signal": fault["actualSignal"], "record": f"faults/{fault['id']}/result.json"} for fault in results]
