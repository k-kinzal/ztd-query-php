"""Reconcile exact SQLite domain receipts from an original launch and its retries."""

import argparse
from ast import literal_eval
from pathlib import Path
import json
import os
import re
from shutil import copytree
import subprocess
import sys

from combine_sqlite_evidence import DOMAINS
from index_evidence import Evidence
from run import TOOL, candidate_distribution, digest, require, verify_candidate, write_json

CONTRACT_NAME = "sqlite-launch-reconciliation-v2.json"
CONTRACT = "bc5e3ae8b53482cb8b1c3bf6b70eb28ce8ffe55b92695e484e43dba440b334ff"
LAUNCH = Path("sqlite-launch/index.json")


def load_attempt(root, candidate, distribution, library):
    verifier = Evidence(root, candidate, distribution, TOOL)
    origin = verifier.sqlite_launch()
    require(origin is not None, "sqlite-reconcile-missing-launch", str(root))
    report = json.loads((root / LAUNCH).read_text())
    require(report["sqliteLibraryDigest"] == library, "sqlite-reconcile-library-mismatch", str(root))
    return {"root": root, "origin": origin, "report": report}


def retryable_timeout(attempt, receipt):
    directory = attempt["root"] / receipt["directory"]
    if (directory / "counterexample.json").exists() or receipt["artifactDigest"] is not None:
        return False
    failure = directory / "run-failure.json"
    if receipt["outcome"] == "timeout":
        return not failure.exists()
    if not failure.is_file():
        return False
    data = json.loads(failure.read_text())
    message = data.get("failure", "")
    if not isinstance(message, str):
        return False
    match = re.fullmatch(r"Command '(.*)' timed out after 60 seconds", message)
    if data.get("signal") != "unexpected-error" or match is None:
        return False
    try:
        command = literal_eval(match[1])
    except (SyntaxError, ValueError):
        return False
    report = attempt["report"]
    log = attempt["root"] / LAUNCH.parent / receipt["log"]
    return (data.get("candidate") == report["candidate"] and data.get("contract") == digest(TOOL / receipt["job"])
            and isinstance(command, list) and len(command) == 6 and all(type(part) is str for part in command)
            and command[0] == "php" and Path(command[1]).name == "shape.php" and command[-2:] == ["", "sqlite"]
            and ("subprocess.TimeoutExpired: " + message) in log.read_text())


def choose_domains(attempts):
    require(bool(attempts) and attempts[0]["report"]["omittedDomains"] == [],
            "sqlite-reconcile-missing-original-schedule")
    required = {domain.contract: domain.directory for domain in DOMAINS}
    selected = {}
    for ordinal, attempt in enumerate(attempts):
        for receipt in attempt["report"]["executions"]:
            name = receipt["job"]
            complete = receipt["exitCode"] == 0 and receipt["artifactDigest"] is not None
            if name == "fault-preflight":
                require(complete, "sqlite-reconcile-failed-preflight", ordinal)
            if name not in required:
                continue
            if complete:
                selected.setdefault(name, (ordinal, required[name], receipt["artifactDigest"]))
            else:
                require(retryable_timeout(attempt, receipt), "sqlite-reconcile-unresolved-failure", [ordinal, name])
    require(selected.keys() == required.keys(), "sqlite-reconcile-missing-domains", sorted(required.keys() - selected.keys()))
    return {name: selected[name] for name in required}


def copy_regular_tree(source, destination):
    require(source.is_dir() and not source.is_symlink(), "sqlite-reconcile-tree", str(source))
    for entry in source.rglob("*"):
        require(not entry.is_symlink() and (entry.is_file() or entry.is_dir()), "sqlite-reconcile-tree-entry", str(entry))
    copytree(source, destination)


def preserve_attempts(attempts, out):
    origins = []
    for ordinal, attempt in enumerate(attempts):
        original = attempt["root"]
        archived = out / "attempts" / str(ordinal)
        copy_regular_tree(original / LAUNCH.parent, archived / LAUNCH.parent)
        require(digest(archived / LAUNCH) == attempt["origin"]["sha256"],
                "sqlite-reconcile-attempt-changed", str(original))
        report = attempt["report"]
        for name, expected in (
            ("plan.json", report["planDigest"]),
            ("events.jsonl", report["journalDigest"]),
            ("distribution.json", report["distribution"]),
            *((entry["log"], entry["logDigest"]) for entry in report["executions"]),
        ):
            require(digest(archived / LAUNCH.parent / name) == expected, "sqlite-reconcile-attempt-file-changed", name)
        failures = {}
        for entry in report["executions"]:
            if entry["job"] not in report["failed"]:
                continue
            directory = entry["directory"]
            source = original / directory
            if source.is_dir():
                data = archived / directory
                copy_regular_tree(source, data)
                failures.update({file.relative_to(out).as_posix(): digest(file)
                                 for file in data.rglob("*") if file.is_file()})
        origins.append({"receipt": (archived / LAUNCH).relative_to(out).as_posix(),
                        "sha256": attempt["origin"]["sha256"], "source": os.path.relpath(original, out),
                        "launchStatus": report["status"], "omitted": report["omittedDomains"],
                        "failed": report["failed"], "failureFiles": failures})
    return origins


def stage_domains(attempts, selected, out):
    source_index = {}
    for name, (ordinal, directory, expected) in selected.items():
        original = attempts[ordinal]["root"] / directory
        target = out / "domains" / directory
        copy_regular_tree(original, target)
        require(digest(target / "index.json") == expected, "sqlite-reconcile-selected-artifact-changed", name)
        source_index[name] = {"attempt": ordinal, "index": (target / "index.json").relative_to(out).as_posix(), "sha256": expected}
    return source_index


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate", required=True)
    parser.add_argument("--candidate-root", required=True, type=Path)
    parser.add_argument("--sqlite-library", required=True, type=Path)
    parser.add_argument("--out", required=True, type=Path)
    parser.add_argument("--attempt", required=True, action="append", type=Path,
                        help="Evidence root of the original and each retry, in execution order")
    args = parser.parse_args()
    contract = Path(__file__).with_name(CONTRACT_NAME)
    require(digest(contract) == CONTRACT, "sqlite-reconcile-contract")
    candidate = args.candidate_root.resolve()
    out = args.out.resolve()
    attempts = [path.resolve() for path in args.attempt]
    library = args.sqlite_library.resolve()
    require(len(attempts) == len(set(attempts)), "sqlite-reconcile-repeated-attempt")
    for path in [out, library, *attempts]:
        require(candidate != path and candidate not in path.parents, "sqlite-reconcile-verifier-inside-candidate", str(path))
    require(all(out != attempt and attempt not in out.parents and out not in attempt.parents for attempt in attempts),
            "sqlite-reconcile-output-overlaps-attempt")
    commit, manifest = candidate_distribution(candidate, args.candidate)
    dist = digest(attempts[0] / LAUNCH.parent / "distribution.json")
    actual = json.loads((attempts[0] / LAUNCH.parent / "distribution.json").read_text())
    require(actual == manifest, "sqlite-reconcile-candidate-distribution")
    engine = digest(library)
    raw = [load_attempt(attempt, commit, dist, engine) for attempt in attempts]
    out.mkdir(parents=True, exist_ok=False)
    origins = preserve_attempts(raw, out)
    write_json(out / "attempts.json", {"candidate": commit, "distribution": dist, "sqliteLibrary": engine, "origins": origins})
    sources = stage_domains(raw, choose_domains(raw), out)
    write_json(out / "selected.json", sources)
    replay = out / "sqlite-index"
    subprocess.run([sys.executable, str(TOOL / "combine_sqlite_evidence.py"), "--root", str(out / "domains"),
                    "--candidate", commit, "--candidate-root", str(candidate), "--sqlite-library", str(library),
                    "--out", str(replay)], check=True)
    verified = json.loads((replay / "index.json").read_text())
    require(verified["candidate"] == commit and verified["domains"].keys() == sources.keys(),
            "sqlite-reconcile-replay-scope-mismatch")
    for name, source in sources.items():
        row = verified["domains"][name]
        require(row["indexDigest"] == source["sha256"] and row["engine"] == engine
                and digest(out / source["index"]) == source["sha256"],
                "sqlite-reconcile-replay-artifact-mismatch", name)
    verify_candidate(candidate, commit, manifest)
    require(digest(library) == engine, "sqlite-reconcile-library-changed")
    write_json(out / "index.json", {
        "status": "provisional",
        "candidate": commit,
        "distribution": dist,
        "contract": CONTRACT,
        "reconciler": digest(Path(__file__)),
        "attempts": {"file": "attempts.json", "sha256": digest(out / "attempts.json")},
        "selected": {"file": "selected.json", "sha256": digest(out / "selected.json")},
        "replay": {"file": "sqlite-index/index.json", "sha256": digest(replay / "index.json")},
        "repositoryAcceptanceProtection": "unconfigured",
        "humanApproval": "pending",
    })
    (out / "summary.md").write_text(
        "# SQLite domain replay across recorded attempts\n\n"
        f"Candidate `{commit}`. Replayed all {len(sources)} registered domain receipts from {len(origins)} completed launch journals.\n\n"
        "[Launch histories](attempts.json) · [Selected source receipts](selected.json) · [Replay summary](sqlite-index/summary.md)\n"
    )
    print(out)


if __name__ == "__main__":
    main()
