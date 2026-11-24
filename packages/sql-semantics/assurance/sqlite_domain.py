"""Shared runtime and fault evidence for independently specified SQLite domains."""

import argparse
import difflib
import json
import time
from pathlib import Path
from typing import NamedTuple, Optional

from run import IDENTITY_CONTRACT, IMAGE, TOOL, Failure, Worker, candidate_distribution, digest, require, shape, verify_candidate, write_json
from renderer_fault_dispatch import FAILURE_NAME, PLAN_NAME, plan as dispatch_plan, receipt as dispatch_receipt
from sqlite_engine import Database

FAULT_STARTUP_SECONDS = 180
FAULT_EXECUTION_SECONDS = 45


class RenderFault(NamedTuple):
    name: str
    path: str
    before: str
    replacement: str
    item: dict
    signal: str
    request: Optional[dict] = None


def check_render_faults(args, db, fixtures, probe, script="worker.php"):
    fixtures = tuple(fixtures)
    schedule = dispatch_plan(fixtures, script)
    path = args.out / PLAN_NAME
    write_json(path, schedule)
    checksum = digest(path)
    attempts = tuple(zip(fixtures, schedule["faults"]))
    entry, phase = None, "healthy-startup"
    healthy = {}
    results = []
    try:
        with Worker(args, time.monotonic() + FAULT_EXECUTION_SECONDS, script=script, startup_timeout=FAULT_STARTUP_SECONDS) as worker:
            for fault, entry in attempts:
                phase = "healthy-request"
                response = worker.request(entry["request"])
                phase = "healthy-probe"
                probe(fault.item, response, db)
                healthy[fault.name] = response
        for fault, entry in attempts:
            phase = "inject"
            original_path = args.candidate / "packages" / fault.path
            original = original_path.read_text()
            require(original.count(fault.before) == 1, "render-fault-anchor", fault.name)
            mutant = original.replace(fault.before, fault.replacement)
            overlay = args.out / "faults" / fault.name
            mutation = overlay / fault.path
            mutation.parent.mkdir(parents=True)
            mutation.write_text(mutant)
            require(digest(mutation) != digest(original_path), "render-fault-not-applied", fault.name)
            (overlay / "injected.diff").write_text("".join(difflib.unified_diff(
                original.splitlines(keepends=True), mutant.splitlines(keepends=True),
                f"a/{fault.path}", f"b/{fault.path}",
            )))
            phase = "mutant-startup"
            with Worker(args, time.monotonic() + FAULT_EXECUTION_SECONDS, overlay, script=script, startup_timeout=FAULT_STARTUP_SECONDS) as worker:
                phase = "mutant-request"
                response = worker.request(entry["request"])
                phase = "mutant-probe"
                try:
                    probe(fault.item, response, db)
                except Failure as failure:
                    result = {
                        "case": fault.name, "file": fault.path, "source": digest(original_path), "mutation": digest(mutation),
                        "input": fault.item, "control": "passed", "expected": fault.signal,
                        "observed": failure.signal, "detail": failure.detail,
                        "healthyResponse": healthy[fault.name], "mutantResponse": response,
                        "dispatch": dispatch_receipt(checksum, entry),
                    }
                    if fault.request is not None:
                        result["request"] = fault.request
                    write_json(overlay / "result.json", result)
                    require(failure.signal == fault.signal, "wrong-render-fault-signal", result)
                    results.append(result)
                else:
                    raise Failure("undetected-render-fault", fault.name)
    except Exception as error:
        write_json(args.out / FAILURE_NAME, {
            "phase": phase,
            "fault": entry,
            "planSha256": checksum,
            "exception": type(error).__name__,
            "signal": error.signal if isinstance(error, Failure) else "unexpected-error",
            "message": str(error),
        })
        raise
    return results


def complete_observations(ledger, settings):
    require(bool(ledger.events), "no-domain-execution")
    for key, events in ledger.events.items():
        require(events in (["attempted", "constructed", "observed", "compared"], ["attempted", "rejected"]),
                "unbalanced-domain-execution", {"id": key, "events": events})
    expected = {
        f"seed/{seed}/{index}" for seed in settings["seeds"] for index in range(settings["casesPerSeed"])
    }
    compared = {key for key, events in ledger.events.items() if key.startswith("seed/") and events[-1] == "compared"}
    require(compared == expected, "incomplete-domain-exploration", sorted(expected - compared))
    return dict(ledger.counts)


def run_domain(label, contract_name, sha256, schema_sql, execute, check_faults, database_factory=Database):
    parser = argparse.ArgumentParser(description=f"Collect SQLite {label} evidence")
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
    contract = TOOL / contract_name
    require(digest(contract) == sha256, "contract-mismatch", contract_name)
    specification = json.loads(contract.read_text())
    commit, distribution = candidate_distribution(args.candidate)
    write_json(args.out / "distribution.json", distribution)
    db = database_factory(args.sqlite_library)
    require(db.version == "3.47.2", "engine-version-mismatch", db.version)
    if schema_sql:
        db.execute(schema_sql)
    try:
        scope = shape(args)
        checks = execute(args, specification, db)
        expected_rejections = (len(specification["negative"]) + len(specification.get("analysisNegative", ()))
                               + specification.get("scheduledRejections", 0))
        observed_rejections = checks["counts"].get("rejected", 0)
        require(observed_rejections == expected_rejections, "incomplete-domain-rejections",
                {"contract": expected_rejections, "observed": observed_rejections})
        faults = check_faults(args, db)
        require(len(faults) == len(specification["faults"]), "incomplete-domain-faults")
    except Exception as failure:
        write_json(args.out / "run-failure.json", {
            "candidate": commit, "contract": sha256, "failure": str(failure),
            "signal": failure.signal if isinstance(failure, Failure) else "unexpected-error",
        })
        raise
    verify_candidate(args.candidate, commit, distribution)
    write_json(args.out / "index.json", {
        "status": f"provisional-sqlite-{label.lower()}-domain", "candidate": commit, "contract": sha256,
        "candidateIdentityContract": IDENTITY_CONTRACT,
        "distribution": digest(args.out / "distribution.json"), "isolation": "none" if args.local else IMAGE,
        "engine": {"version": db.version, "source": db.source, "libraryDigest": db.digest, "observerPolicy": db.policy_digest, "textObservation": db.text_observation_digest},
        "tools": {path.name: digest(path) for path in TOOL.iterdir() if path.suffix in (".php", ".py", ".json")},
        "shape": scope, "checks": checks, "faults": faults,
        "faultBudgets": {"startupSeconds": FAULT_STARTUP_SECONDS, "executionSeconds": FAULT_EXECUTION_SECONDS},
    })
    counts = checks["counts"]
    (args.out / "summary.md").write_text(
        f"# SQLite {label} evidence\n\nCandidate `{commit}`.\n\n"
        f"All {len(checks['finiteIds'])} finite identities observed. The runner recorded {counts['compared']} comparisons, "
        f"{counts['rejected']} specified construction rejections, and all {len(faults)} expected fault detections.\n\n"
        "[Evidence index](index.json) · [Events](events.jsonl)\n"
    )
    print(args.out)
