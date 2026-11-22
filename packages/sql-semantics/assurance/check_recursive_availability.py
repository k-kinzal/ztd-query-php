"""Check the unavailable recursive syntax profiles through both publication paths and the native server."""

import argparse
import json
from pathlib import Path
import subprocess
import time

from run import IDENTITY_CONTRACT, TOOL, Worker, candidate_distribution, digest, require, shape, verify_candidate, write_json
from run_server import same
from run_server_delete import tools
from run_server_recursive_series import CONTRACT, specifications
from server_recursive_series_cases import case

QUERY = "WITH RECURSIVE t(n) AS (SELECT 1 UNION ALL SELECT n + 1 FROM t WHERE n < 3) SELECT n AS value FROM t"
CONTROL = "SELECT 7 AS control"


def classifications(release, native, constructed, analyzed, control):
    same([release, 0, "", "utf8mb4", "utf8mb4"], native["database"])
    require(native["query"] == QUERY and native["control"] == 7, "recursive-availability-native-control", native)
    same({"status": "database-rejection", "state": "42000", "error": 1064}, native["observation"])
    require(control["status"] == "constructed" and control["profile"].startswith("mysql-" + release + "/"),
            "recursive-availability-positive-control", control)
    require(control["columns"] == [[["integer", "7"], "control"]] and control["source"] is None,
            "recursive-availability-positive-control-shape", control)
    require(constructed["status"] == "rejected" and "Recursive WITH requires MySQL 8.0 or later" in constructed["message"],
            "recursive-availability-constructor-boundary", constructed)
    require(analyzed["status"] == "rejected" and analyzed["exception"].endswith("\\AnalysisException"),
            "recursive-availability-parser-boundary", analyzed)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate", type=Path, required=True)
    parser.add_argument("--tool-autoload", type=Path, required=True)
    parser.add_argument("--out", type=Path, required=True)
    parser.add_argument("--release", required=True)
    args = parser.parse_args()
    args.candidate, args.tool_autoload, args.out = (path.resolve() for path in (args.candidate, args.tool_autoload, args.out))
    require(args.candidate not in args.out.parents and args.candidate != args.out, "evidence-directory-inside-candidate")
    specification, _ = specifications()
    require(args.release in specification["unavailableSyntax"]["mysql"], "recursive-availability-release", args.release)
    args.local = False
    args.out.mkdir(parents=True, exist_ok=False)
    identity, distribution = candidate_distribution(args.candidate)
    write_json(args.out / "distribution.json", distribution)
    audited = shape(args, dialect="mysql")
    deadline = time.monotonic() + specification["exploration"]["timeoutSecondsPerRelease"]
    observed = subprocess.run(["php", str(TOOL / "server_recursive_availability.php")],
                              capture_output=True, text=True, timeout=max(1, deadline - time.monotonic()))
    (args.out / "observer.stdout").write_text(observed.stdout)
    (args.out / "observer.stderr").write_text(observed.stderr)
    require(observed.returncode == 0, "recursive-availability-observer")
    native = json.loads(observed.stdout)
    tag = "mysql-" + args.release
    parsed = {"dialect": "mysql", "release": tag, "declarations": []}
    requests = [case("mysql", tag, 1, 1, 3), {**parsed, "sql": QUERY}, {**parsed, "sql": CONTROL}]
    with Worker(args, deadline, script="server_worker.php") as worker:
        responses = [worker.request(request) for request in requests]
        environment = worker.environment
    write_json(args.out / "observations.json", {"requests": requests, "responses": responses, "native": native})
    classifications(args.release, native, *responses)
    verify_candidate(args.candidate, identity, distribution)
    write_json(args.out / "index.json", {
        "status": "provisional-recursive-syntax-unavailable", "candidate": identity,
        "candidateIdentityContract": IDENTITY_CONTRACT, "contract": CONTRACT, "release": args.release,
        "distribution": digest(args.out / "distribution.json"), "toolDigests": tools(), "shape": audited,
        "candidateEnvironment": environment, "observationsDigest": digest(args.out / "observations.json"),
    })
    print(args.out)


if __name__ == "__main__":
    main()
