"""Launch the archived SQLite assurance schedule and preserve execution receipts."""

import argparse
import os
import subprocess
import sys
from pathlib import Path

from combine_sqlite_evidence import DOMAINS
from replay_sqlite_evidence import DOMAINS as REPLAY_DOMAINS
from run import TOOL, Ledger, candidate_distribution, digest, require, verify_candidate, write_json

RUNNER_SECONDS = 3600
INITIAL = "contract-v1.json"
PREFIX = "semantics-ir-sqlite-"
SUFFIX = "-evidence"
PREFLIGHT = ("fault-preflight", "preflight_sqlite_faults.py", "sqlite-fault-preflight")
REPLAY = ("replay", "combine_sqlite_evidence.py", "sqlite-index")


def domain_schedule():
    schedule = {}
    for domain in DOMAINS:
        if domain.contract == INITIAL:
            require(domain.directory == "semantics-ir-evidence", "sqlite-launch-initial-location", domain.directory)
            runner = "run.py"
        else:
            require(domain.directory.startswith(PREFIX) and domain.directory.endswith(SUFFIX),
                    "sqlite-launch-domain-location", domain.directory)
            key = domain.directory[len(PREFIX):-len(SUFFIX)].replace("-", "_")
            runner = f"run_sqlite_{key}.py"
        require((TOOL / runner).is_file() and domain.contract not in schedule,
                "sqlite-launch-domain-runner", domain.contract)
        schedule[domain.contract] = (domain.contract, runner, domain.directory)
    require(schedule.keys() == REPLAY_DOMAINS.keys() | {INITIAL} and len({row[2] for row in schedule.values()}) == len(schedule),
            "sqlite-launch-domain-set")
    return schedule


def job_schedule(omitted):
    selected = domain_schedule()
    require(set(omitted) < selected.keys(), "sqlite-launch-unknown-or-empty-selection", omitted)
    jobs = [PREFLIGHT, *(row for name, row in selected.items() if name not in omitted)]
    return jobs if omitted else [*jobs, REPLAY]


def command(args, root, job):
    name, runner, directory = job
    output = root / directory
    if name == PREFLIGHT[0]:
        flags = ["--candidate", args.candidate]
    elif name == REPLAY[0]:
        flags = ["--root", root, "--candidate", args.candidate_sha, "--candidate-root", args.candidate,
                 "--sqlite-library", args.sqlite_library]
    else:
        flags = ["--candidate", args.candidate, "--tool-autoload", args.tool_autoload,
                 "--sqlite-library", args.sqlite_library]
    return [sys.executable, TOOL / runner, *flags, "--out", output]


def execute(args, jobs, receipts, ledger):
    root = args.evidence_root
    env = {**os.environ, "PYTHONDONTWRITEBYTECODE": "1", "XDEBUG_MODE": "off"}
    executions = []
    for ordinal, job in enumerate(jobs):
        name, runner, directory = job
        log = receipts / f"{ordinal:02d}-{directory}.log"
        ledger.record(name, "started", runner=runner, directory=directory, ordinal=ordinal)
        print(f"Running {name}", flush=True)
        with log.open("w") as stream:
            try:
                status = subprocess.run(command(args, root, job), env=env, stdout=stream, stderr=subprocess.STDOUT,
                                        timeout=RUNNER_SECONDS, check=False)
                outcome, code = "exited", status.returncode
            except subprocess.TimeoutExpired:
                outcome, code = "timeout", None
            except OSError as error:
                print(f"{type(error).__name__}: {error}", file=stream)
                outcome, code = "launch-error", None
        artifact = root / directory / "index.json"
        executions.append({
            "job": name, "runner": runner, "runnerDigest": digest(TOOL / runner), "directory": directory,
            "log": log.name, "logDigest": digest(log), "outcome": outcome, "exitCode": code,
            "artifactDigest": digest(artifact) if artifact.is_file() else None,
        })
        ledger.record(name, "finished", receipt=executions[-1])
        print(f"{name} {outcome} {code}", flush=True)
    return executions


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate", required=True, type=Path)
    parser.add_argument("--candidate-sha", required=True)
    parser.add_argument("--tool-autoload", required=True, type=Path)
    parser.add_argument("--sqlite-library", required=True, type=Path)
    parser.add_argument("--evidence-root", required=True, type=Path)
    parser.add_argument("--diagnostic", action="append", choices=tuple(domain_schedule()),
                        help="Run selected contracts and record all omitted domains.")
    args = parser.parse_args()
    for option in ("candidate", "tool_autoload", "sqlite_library", "evidence_root"):
        setattr(args, option, getattr(args, option).resolve())
    require(all(path != args.candidate and args.candidate not in path.parents
                for path in (TOOL, args.tool_autoload, args.sqlite_library, args.evidence_root)),
            "sqlite-launch-verifier-input-inside-candidate")
    require(not args.diagnostic or len(args.diagnostic) == len(set(args.diagnostic)), "sqlite-launch-repeated-diagnostic")
    omitted = sorted(domain_schedule().keys() - set(args.diagnostic)) if args.diagnostic else []
    jobs = job_schedule(omitted)
    sha, distribution = candidate_distribution(args.candidate, args.candidate_sha)
    root = args.evidence_root
    require(all(not (root / directory).exists() for _, _, directory in jobs), "sqlite-launch-existing-domain")
    receipts = root / "sqlite-launch"
    receipts.mkdir(parents=True, exist_ok=False)
    manifest = receipts / "distribution.json"
    write_json(manifest, distribution)
    engine = digest(args.sqlite_library)
    plan = receipts / "plan.json"
    journal = receipts / "events.jsonl"
    write_json(plan, {"candidate": sha, "distribution": digest(manifest), "jobs": jobs,
                      "omittedDomains": omitted, "runnerTimeoutSeconds": RUNNER_SECONDS})
    with journal.open("x") as stream:
        runs = execute(args, jobs, receipts, Ledger(stream))
    failed = [run["job"] for run in runs if run["exitCode"] != 0 or run["artifactDigest"] is None]
    verify_candidate(args.candidate, sha, distribution)
    require(engine == digest(args.sqlite_library), "sqlite-launch-engine-changed")
    write_json(receipts / "index.json", {
        "candidate": sha, "distribution": digest(manifest), "launcherDigest": digest(Path(__file__)),
        "sqliteLibraryDigest": engine, "runnerTimeoutSeconds": RUNNER_SECONDS,
        "status": "incomplete" if failed else "observed-subset" if omitted else "observed-matrix",
        "omittedDomains": omitted, "executions": runs, "failed": failed,
        "planDigest": digest(plan), "journalDigest": digest(journal),
    })
    require(not failed, "sqlite-launch-incomplete-matrix", failed)


if __name__ == "__main__":
    main()
