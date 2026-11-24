"""Reproduce provisional SemanticsIR evidence with an isolated PHP candidate."""

import argparse
import collections
import hashlib
import json
import os
from pathlib import Path
import select
import subprocess
import sys
import time

from cases import (
    MAIN, MAX, MIN, SCHEMA, TEMP, case, expected, expected_finite_ids, finite_cases,
    finite_identity, slots, structural_cases, walk,
)
from grammar_scope import inventory as grammar_inventory
from runtime_distribution import IDENTITY_CONTRACT, PACKAGE_FILES, checked_bytes, checked_commit, checked_paths
from scope import public_scope
from sqlite_engine import OBSERVER_CONTRACT, Database

TOOL = Path(__file__).resolve().parent
CONTRACT_DIGEST = "7f607339a5a5424cfc1dba25823cab5cc469c20e1e01d3b314755801a6f9b849"
IMAGE = "php@sha256:f1ef73e865793418aac8cf6d8dbb44b33b527cdac62dbcf2e47061ed8f40b81a"
PROFILE = "sqlite-3.47.2/utf8/short-column-names/numbered-parameters"
PACKAGES = ("sql-semantics", "sql-semantics-mysql", "sql-semantics-postgres", "sql-semantics-sqlite", "sql-parser")


class Failure(Exception):
    def __init__(self, signal, detail=None):
        super().__init__(f"{signal}: {detail}")
        self.signal = signal
        self.detail = detail


def require(condition, signal, detail=None):
    if not condition:
        raise Failure(signal, detail)


def digest(path):
    sha256 = hashlib.sha256()
    with path.open("rb") as stream:
        for block in iter(lambda: stream.read(65536), b""):
            sha256.update(block)
    return sha256.hexdigest()


def verify_contract(path):
    require(digest(path) == CONTRACT_DIGEST, "contract-mismatch", str(path))


def manifest(root, overlay=None):
    files = {}
    for path in checked_paths(root, PACKAGES, require, digest, TOOL):
        relative = path.relative_to(root)
        replacement = None if overlay is None else overlay / relative.relative_to("packages")
        files[str(relative)] = digest(replacement if replacement is not None and replacement.is_file() else path)
    return files


def candidate_distribution(root, expected=None):
    commit, object_format, committed = checked_commit(root, PACKAGES, require, digest, TOOL, expected)
    measured, observed = {}, {}
    for path in checked_paths(root, PACKAGES, require, digest, TOOL):
        relative = path.relative_to(root).as_posix()
        measured[relative], observed[relative] = checked_bytes(path, object_format)
    require(committed == observed, "candidate-commit-content-mismatch", sorted(
        key for key in committed.keys() | observed.keys() if committed.get(key) != observed.get(key)
    ))
    return commit, measured


def verify_candidate(root, commit, distribution):
    verify_distribution(distribution, candidate_distribution(root, commit)[1])


def verify_distribution(recorded, actual):
    require(recorded == actual, "distribution-mismatch", sorted(
        key for key in recorded.keys() | actual.keys() if recorded.get(key) != actual.get(key)
    ))


def write_json(path, value):
    path.write_text(json.dumps(value, ensure_ascii=True, indent=2, sort_keys=True) + "\n")


class Worker:
    def __init__(self, args, deadline, overlay=None, script="worker.php", startup_timeout=None):
        self.deadline = deadline
        self.startup_timeout = startup_timeout
        self.execution_budget = deadline - time.monotonic()
        if args.local:
            command = ["php", str(TOOL / script), str(args.candidate)]
            if overlay:
                command.append(str(overlay))
        else:
            command = [
                "docker", "run", "--pull", "never", "--rm", "-i", "--network", "none",
                "--read-only", "--cap-drop", "ALL", "--security-opt", "no-new-privileges",
                "--user", "65534:65534", "--pids-limit", "64", "--memory", "256m",
                "-v", f"{TOOL / script}:/worker.php:ro",
            ]
            for package in PACKAGES:
                for tree in PACKAGE_FILES[2:]:
                    relative = f"packages/{package}/{tree}"
                    source = args.candidate / relative
                    if source.is_dir():
                        command.extend(["-v", f"{source}:/candidate/{relative}:ro"])
            if overlay:
                command.extend(["-v", f"{overlay}:/overlay:ro"])
            command.extend([IMAGE, "php", "/worker.php", "/candidate"])
            if overlay:
                command.append("/overlay")
        self.proc = subprocess.Popen(command, stdin=subprocess.PIPE, stdout=subprocess.PIPE, bufsize=0)
        self.buffer = b""

    def __enter__(self):
        if self.startup_timeout is not None:
            self.deadline = time.monotonic() + self.startup_timeout
        try:
            self.environment = self.receive()
            self.check_environment()
        except BaseException as failure:
            self.__exit__(None, None, None)
            if self.startup_timeout is not None and isinstance(failure, Failure) and failure.signal == "timeout":
                raise Failure("worker-startup-timeout", self.startup_timeout) from failure
            raise
        if self.startup_timeout is not None:
            self.deadline = time.monotonic() + self.execution_budget
        return self

    def check_environment(self):
        require("php" in self.environment, "worker-handshake", self.environment)

    def __exit__(self, *_):
        self.proc.stdin.close()
        try:
            self.proc.wait(timeout=2)
        except subprocess.TimeoutExpired:
            self.proc.kill()
            self.proc.wait()
        self.proc.stdout.close()

    def receive(self):
        while b"\n" not in self.buffer:
            left = self.deadline - time.monotonic()
            if left <= 0 or not select.select([self.proc.stdout], [], [], left)[0]:
                raise Failure("timeout", "candidate did not finish its request")
            block = os.read(self.proc.stdout.fileno(), 65536)
            require(block, "worker-exit", self.proc.poll())
            self.buffer += block
            require(len(self.buffer) <= 10000000, "response-limit")
        line, self.buffer = self.buffer.split(b"\n", 1)
        return json.loads(line)

    def request(self, item):
        self.proc.stdin.write((json.dumps(item) + "\n").encode())
        return self.receive()


def compare(expected_observation, actual):
    require(expected_observation == actual, "meaning-mismatch", {
        "expected": expected_observation, "actual": actual,
    })


def check_comparator(comparator=compare):
    original = expected(case(MAIN, [["column", 0], ["parameter", "02"]], {"2": -6}))
    comparator(original, original)
    for key, replacement in [
        ("names", ["r1", "r0"]), ("parameters", {"2": "?2"}), ("parameterCount", 3),
        ("rows", original["rows"][1:]), ("readOnly", False), ("changes", 1),
        ("rows", [[["real", "0x1.0000000000000p+2"] if field == ["integer", "4"] else field for field in row]
                  for row in original["rows"]]),
    ]:
        altered = dict(original, **{key: replacement})
        try:
            comparator(original, altered)
        except Failure as error:
            require(error.signal == "meaning-mismatch", "unrelated-comparator-error", error.signal)
        else:
            raise Failure("comparator-accepted-known-difference", key)


def positive(worker, db, item, constructed=None):
    return compare_response(db, item, worker.request(item), constructed)


def compare_response(db, item, response, constructed=None):
    require(response["status"] == "constructed", "positive-construction-failed", response)
    require(response["profile"] == PROFILE, "profile-mismatch", response)
    for field in ("columns", "source"):
        require(response[field] == item[field], "shape-observation-mismatch", {"field": field, "response": response})
    if constructed is not None:
        constructed(response)
    observation = db.observe(response["sql"], item["bindings"])
    compare(expected(item), observation)
    return response, observation


def negative(worker, item, signal, status="rejected"):
    response = worker.request(item)
    require(response["status"] == status and signal in response.get("message", ""), "negative-boundary-mismatch", response)
    return response


def finite_complete(observed):
    missing = expected_finite_ids() - set(observed)
    unexpected = set(observed) - expected_finite_ids()
    repeated = [key for key, count in observed.items() if count != 1]
    require(not missing and not unexpected and not repeated, "finite-domain-incomplete", {
        "missing": list(map(repr, missing)), "extra": list(map(repr, unexpected)), "repeated": list(map(repr, repeated)),
    })


class Ledger:
    def __init__(self, output):
        self.output = output
        self.events = {}
        self.counts = collections.Counter()

    def record(self, key, event, **data):
        self.events.setdefault(key, []).append(event)
        self.counts[event] += 1
        self.output.write(json.dumps(dict(id=key, event=event, **data)) + "\n")
        self.output.flush()

    def verify(self):
        require(bool(self.events), "empty-execution")
        incomplete = {key: events for key, events in self.events.items() if events not in
                      (["attempted", "constructed", "compared"], ["attempted", "rejected"], ["attempted", "snapshot"])}
        require(not incomplete, "unbalanced-execution", incomplete)
        return dict(self.counts)


def shape(args, overlay=None, dialect="sqlite"):
    command = ["php", str(TOOL / "shape.php"), str(args.tool_autoload), str(args.candidate)]
    command.extend([str(overlay) if overlay else "", dialect])
    result = subprocess.run(command, check=False, capture_output=True, text=True, timeout=60)
    event = json.loads(result.stdout)
    require(result.returncode == 0 and event["status"] == "verified", event["status"], event)
    return event


def coverage(observed, response):
    source = response["source"]
    observed["width"][len(response["columns"])] += 1
    observed["source"][str(source and source[:2])] += 1
    for expression, _ in response["columns"]:
        pending = [(expression, "result", 0)]
        while pending:
            operand, role, height = pending.pop()
            observed["node"][operand[0]] += 1
            observed["depth"][height] += 1
            observed["role"][f"{role}/{operand[0]}"] += 1
            if operand[0] == "arithmetic":
                observed["operator"][operand[1]] += 1
                pending.extend([(operand[2], "left", height + 1), (operand[3], "right", height + 1)])
            if operand[0] in ("column", "parameter"):
                observed["reference"][repr(operand)] += 1


def verify_analyzed(worker, db, ledger):
    ddl = ["CREATE TABLE main.accounts (Id BLOB, delta BLOB)", "CREATE TEMP TABLE accounts (Id BLOB, delta BLOB)"]
    examples = [
        ('SELECT id, accounts.id, (Id - delta) * ?02 AS "d" FROM main.accounts', {"2": -6}),
        ('SELECT a.ID, (a.delta), "Id" AS label FROM main.accounts AS a', {}),
        ('SELECT id, accounts.id FROM accounts', {}),
        ('SELECT ((1)), -9223372036854775808, 1+2 /* name */, NULL, ?03, ?00003', {"3": 9}),
        ('SELECT ALL 007 AS x, 7 AS x, NULL AS ""', {}),
        ('SELECT x."a"" b" FROM "odd"" table" AS x', {}),
    ]
    declarations = ddl + ['CREATE TABLE main."odd"" table" ("a"" b" BLOB)']
    for index, (sql, bindings) in enumerate(examples):
        key = f"analyzed/{index}"
        item = {"sql": sql, "declarations": declarations}
        ledger.record(key, "attempted", input=item, bindings=bindings)
        response = worker.request(item)
        require(response["status"] == "constructed", "positive-analysis-failed", response)
        ledger.record(key, "constructed", response=response)
        compare(db.observe(sql, bindings), db.observe(response["sql"], bindings))
        if index in (0, 2):
            require(response["columns"][0][0] == response["columns"][1][0] == ["column", 0], "qualified-reference-mismatch", response)
            require(response["source"][:2] == (MAIN if index == 0 else TEMP)[:2], "source-resolution-mismatch", response)
        ledger.record(key, "compared", observation=db.observe(response["sql"], bindings))
    return len(examples)


def run(args, db, contract, output, worker_factory=Worker):
    observed = collections.defaultdict(collections.Counter)
    ids = collections.Counter()
    deadline = time.monotonic() + contract["exploration"]["timeoutSeconds"]

    def test(item, key, finite=False):
        nonlocal current
        current = (key, item)
        ledger.record(key, "attempted", input=item)
        response, observation = positive(
            worker, db, item, lambda value: ledger.record(key, "constructed", response=value),
        )
        ledger.record(key, "compared", observation=observation)
        coverage(observed, response)
        if finite:
            ids[finite_identity(response["source"], response["columns"])] += 1

    current = None
    with output.open("w") as stream, worker_factory(args, deadline) as worker:
        ledger = Ledger(stream)
        try:
            for index, item in enumerate(finite_cases()):
                test(item, f"finite/{index}", True)
            finite_complete(ids)
            settings = contract["exploration"]
            exploration_start = ledger.counts["compared"]
            for seed in settings["seeds"]:
                for index, item in enumerate(structural_cases(seed, settings["attemptsPerSeed"], settings["height"], settings["projectionWidth"])):
                    test(item, f"seed/{seed}/{index}")
                actual = sum(1 for key in ledger.events if key.startswith(f"seed/{seed}/") and ledger.events[key][-1] == "compared")
                require(actual == settings["attemptsPerSeed"], "seed-budget-mismatch", {"seed": seed, "compared": actual})
            exploration = ledger.counts["compared"] - exploration_start
            require(observed["node"].keys() == {"integer", "null", "parameter", "column", "arithmetic"}
                    and observed["operator"].keys() == {"+", "-", "*"}
                    and settings["height"] in observed["depth"]
                    and settings["projectionWidth"] in observed["width"], "structural-exploration-incomplete", dict(observed))
            chain = ["integer", "1"]
            for _ in range(32):
                chain = ["arithmetic", "-", ["integer", "-1"], chain]
            test(case(None, [chain]), "boundary/depth")
            tree = ["integer", "1"]
            for _ in range(12):
                tree = ["arithmetic", "+", tree, tree]
            test(case(None, [tree, ["null"]]), "boundary/expanded-size")
            test(case(None, [["null"]] * 2000), "boundary/width")
            test(case(None, [["integer", str(MIN)], ["integer", str(MAX)], ["parameter", "32766"]], {"32766": 12}), "boundary/integer-parameter")
            test(case(None, [["integer", "1"]], labels=['"' * 255]), "boundary/identifier-bytes")
            snapshot = case(MAIN, [["column", 0]])
            snapshot["probe"] = "snapshot"
            current = ("special/snapshot", snapshot)
            ledger.record(current[0], "attempted", input=snapshot)
            response = worker.request(snapshot)
            require(response == {"status": "snapshot", "blocked": [True] * 4}, "snapshot-bypass", response)
            ledger.record(current[0], "snapshot", response=response)
            negatives = [
                (case(None, []), "nonempty list", "rejected"),
                (case(None, [["null"]] * 2001), "at most 2000", "rejected"),
                (case(None, [["arithmetic", "-", ["integer", "1"], chain]]), "Expression height", "rejected"),
                (case(None, [tree, ["null"], ["null"]]), "expanded node count", "rejected"),
                (case(None, [["column", 0]]), "belong to the statement source", "rejected"),
                (case(MAIN, [["column", 2]]), "belong to the statement source", "rejected"),
                (case(MAIN, [["column", -1]]), "nonnegative", "rejected"),
                (case(["main", "accounts", ["Id", "ID"]], [["column", 0]]), "repeat a column", "rejected"),
                (case(None, [["integer", str(MAX + 1)]]), "64-bit range", "rejected"),
                (case(None, [["integer", str(MIN - 1)]]), "64-bit range", "rejected"),
                (case(None, [["parameter", "0"]]), "between 1 and 32766", "rejected"),
                (case(None, [["parameter", "32767"]]), "between 1 and 32766", "rejected"),
                (case(None, [["null"]], labels=["a\0b"]), "without NUL", "rejected"),
                (case(None, [["null"]], labels=["a" * 256]), "at most 255 bytes", "rejected"),
                ({"sql": "SELECT id FROM accounts", "declarations": []}, "resolved, complete table or view declaration", "rejected"),
                ({"sql": "SELECT 1 GROUP BY 1", "declarations": []}, "without grouping or windows", "rejected"),
                ({"sql": "SELECT ?", "declarations": []}, "numbered parameter label", "rejected"),
                ({"sql": "SELECT id FROM", "declarations": []}, "Unexpected", "analysis-rejected"),
            ]
            for index, (item, signal, status) in enumerate(negatives):
                current = (f"negative/{index}", item)
                ledger.record(current[0], "attempted", input=item)
                ledger.record(current[0], "rejected", response=negative(worker, item, signal, status))
            current = None
            analyzed = verify_analyzed(worker, db, ledger)
            return {"execution": ledger.verify(), "domain": dict(observed), "finiteIds": sorted(map(repr, ids)),
                    "analyzedComparisons": analyzed, "explorationComparisons": exploration, "candidateEnvironment": worker.environment}
        except BaseException as error:
            key, original = current if current is not None else ("run", None)
            classification = error.signal if isinstance(error, Failure) else "unexpected-error"
            ledger.record(key, "failed", classification=classification, error=str(error))
            from counterexample_reduction import record

            record(output.parent / "counterexample.json", {
                "id": key, "original": original, "classification": classification, "failure": str(error),
                "detail": error.detail if isinstance(error, Failure) else None,
            }, classification, projection_reductions, lambda item: positive(worker, db, item))
            raise


def projection_reductions(original):
    """Offer simpler direct operand trees under the same source declaration."""
    for expression, name in original["columns"]:
        for subexpression in walk(expression):
            item = case(original["source"], [subexpression], labels=[name])
            item["bindings"] = {slot: value for slot, value in original["bindings"].items() if slot in slots(item["columns"])}
            yield item


def write_summary(path, index):
    checks = index["checks"]
    counts = checks["execution"]
    notes = [
        "# SemanticsIR evidence",
        "",
        "This run covers the initial SQLite 3.47.2 projection domain. Human approval is pending.",
        "",
        f"- Candidate commit: `{index['commit']}`",
        f"- Contract SHA-256: `{index['contract']}`",
        f"- Distribution manifest SHA-256: `{index['distribution']}`",
        f"- Candidate PHP: {checks['candidateEnvironment']['php']}",
        f"- Isolation: {index['candidateIsolation']['mode']}",
        f"- Finite domain: all {len(checks['finiteIds'])} symbolic IDs observed exactly once",
        f"- Structural exploration: {checks['explorationComparisons']} compared inputs",
        f"- Analysis path: {checks['analyzedComparisons']} original and emitted SQL comparisons",
        f"- Accounting: {counts['attempted']} attempted, {counts['compared']} compared, "
        f"{counts['rejected']} rejected at the specified boundary and {counts['snapshot']} snapshot probe",
        "",
        "## Required fault cases",
        "",
    ]
    notes.extend(f"- [{fault['id']}]({fault['record']}) detected by `{fault['signal']}`" for fault in index["faults"])
    notes += [
        "", "## Open obligations", "",
        "The wider constructor ledger, other statement families and dialects, proof obligations and the repository-level acceptance gate remain open.",
        "",
        "[Machine-readable index](index.json) · [Case events](events.jsonl) · [Source inventory](inventory.json)",
    ]
    path.write_text("\n".join(notes) + "\n")

def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate", required=True, type=Path)
    parser.add_argument("--tool-autoload", required=True, type=Path)
    parser.add_argument("--sqlite-library", required=True, type=Path)
    parser.add_argument("--out", required=True, type=Path)
    parser.add_argument("--local", action="store_true", help="Run without candidate isolation and mark the result accordingly.")
    parser.add_argument("--verify-distribution", type=Path, help="Check a previously generated distribution.json and exit.")
    args = parser.parse_args()
    args.candidate = args.candidate.resolve()
    args.tool_autoload = args.tool_autoload.resolve()
    args.out = args.out.resolve()
    if args.verify_distribution:
        verify_distribution(json.loads(args.verify_distribution.read_text()), candidate_distribution(args.candidate)[1])
        return
    require(args.candidate not in args.out.parents and args.candidate != args.out, "evidence-directory-inside-candidate")
    args.out.mkdir(parents=True, exist_ok=False)
    contract_path = TOOL / "contract-v1.json"
    verify_contract(contract_path)
    contract = json.loads(contract_path.read_text())
    commit, distribution = candidate_distribution(args.candidate)
    write_json(args.out / "distribution.json", distribution)
    inventory = args.out / "inventory.json"
    with inventory.open("w") as stream:
        subprocess.run(
            ["php", str(TOOL / "inventory.php"), str(args.candidate), str(args.tool_autoload)],
            stdout=stream, check=True, timeout=60, env={**os.environ, "XDEBUG_MODE": "off"},
        )
    types = json.loads(inventory.read_text())
    scope = public_scope(types, distribution)
    write_json(args.out / "scope.json", scope)
    require(not scope["unregisteredRootTypes"] and not scope["missingRootTypes"], "unexpected-complete-root", scope["completeRootTypes"])
    require(not scope["changedGrammarArtifacts"], "required-grammar-set-changed", scope["changedGrammarArtifacts"])
    grammar_scope = grammar_inventory(args.candidate)
    grammar_path = args.out / "grammar-scope.json"
    write_json(grammar_path, grammar_scope)
    for field in ("unregisteredConstructors", "missingConstructors", "unexpectedPublicationRoutes", "unexpectedUnconstrainedRoutes"):
        require(not scope[field], "ir-constructor-ledger-mismatch", {"field": field, "detail": scope[field]})
    from constructor_trace import active_declarations
    declarations, _, _ = active_declarations(TOOL)
    db = Database(args.sqlite_library)
    require(db.version == contract["initialExecutableDomain"]["profile"]["version"], "engine-version-mismatch", db.version)
    db.execute(SCHEMA)
    check_comparator()
    code_shape = shape(args)
    checks = run(args, db, contract, args.out / "events.jsonl")
    from faults import exercise, fault_budget
    faults = exercise(args, db, distribution)
    verify_candidate(args.candidate, commit, distribution)
    index = {
        "status": "provisional-initial-domain", "commit": commit, "candidateIdentityContract": IDENTITY_CONTRACT,
        "contract": CONTRACT_DIGEST, "distribution": digest(args.out / "distribution.json"),
        "toolDigests": {path.name: digest(path) for path in TOOL.iterdir() if path.suffix in {".py", ".php", ".json"}},
        "scope": contract["requiredScope"], "profile": contract["initialExecutableDomain"]["profile"],
        "grammarScope": {"record": grammar_path.name, "sha256": digest(grammar_path),
                         "totalAlternatives": grammar_scope["totalAlternatives"], "emptyAlternatives": grammar_scope["emptyAlternatives"]},
        "engine": {
            "observerPolicy": OBSERVER_CONTRACT, "textObservation": db.text_observation_digest,
            "version": db.version, "source": db.source, "libraryDigest": db.digest,
            "compileOptions": db.metadata("compile_options"),
        },
        "candidateIsolation": {"mode": "none"} if args.local else {
            "mode": "container", "image": IMAGE, "network": "none",
            "mounts": "read-only", "capabilities": "none", "user": "65534:65534",
            "memory": "256m", "pidsLimit": 64, "evidenceMount": False,
        },
        "repositoryAcceptanceProtection": "unconfigured",
        "activeDeclarations": declarations,
        "inventoryArtifacts": {name: digest(args.out / name) for name in ("inventory.json", "scope.json")},
        "shape": code_shape, "checks": checks, "faults": faults, "initialFaultBudget": fault_budget(),
        "proof": "none", "open": ["full constructor ledger", "server projection evidence is recorded separately", "remaining semantic families and profiles", "human review", "protected acceptance configuration"],
        "files": ["summary.md", "distribution.json", "scope.json", "grammar-scope.json", "inventory.json", "events.jsonl", "faults/"],
        "inventorySettings": {"timeoutSeconds": 60, "xdebugMode": "off"},
    }
    write_json(args.out / "index.json", index)
    write_summary(args.out / "summary.md", index)
    print(f"Provisional SQLite evidence in {args.out}")


if __name__ == "__main__":
    # Imports used by the fault driver share the running module and its checks.
    sys.modules["run"] = sys.modules[__name__]
    main()
