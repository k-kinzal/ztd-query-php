"""Run the fixed native evidence schedule against disposable, pinned Docker databases."""

import argparse
import json
import os
import re
import socket
import struct
import subprocess
import sys
import time
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

from native_schedule import NATIVE, SINGLE_ENGINE
from run import TOOL, Failure, candidate_distribution, digest, require, verify_candidate, write_json
from run_server import CONTRACT as PROJECTION
from scope import CONTRACTS

FIXTURE_STARTUP_SECONDS = 900
RUNNER_SECONDS = 3600
PORTS = {"mysql": 3306, "postgres": 5432}
RELEASE_ROW = re.compile(
    r"^ {10}- engine: (mysql|postgres)\n"
    r" {12}release: '([0-9]+\.[0-9]+(?:\.[0-9]+)?)'\n"
    r" {12}port: ([0-9]+)\n"
    r" {12}image: ((?:[a-z0-9.-]+/)*[a-z0-9][a-z0-9.-]*@sha256:[a-f0-9]{64})$",
    re.MULTILINE,
)


def release_fixtures(workflow, projection):
    require(digest(projection) == PROJECTION, "native-launch-profile-digest", str(projection))
    contracts = json.loads(projection.read_text())["profiles"]
    expected = {(engine, version) for engine, spec in contracts.items() for version in spec["releases"]}
    releases = {}
    for engine, version, port, image in RELEASE_ROW.findall(workflow.read_text()):
        key = (engine, version)
        repository = "container-registry.oracle.com/mysql/community-server" if key == ("mysql", "9.1.0") else engine
        require(key in expected and key not in releases and image.split("@")[0] == repository and int(port) == PORTS[engine],
                "native-launch-unregistered-fixture", (key, port, image))
        releases[key] = {"engine": engine, "release": version, "port": int(port), "image": image}
    require(releases.keys() == expected, "native-launch-fixture-release-set", sorted(expected - releases.keys()))
    return releases


def release_jobs(profile, series):
    engine, version = profile
    for domain in NATIVE:
        if engine != SINGLE_ENGINE.get(domain, engine):
            continue
        flags = ["--engine", engine, "--release", version]
        if domain == "recursive_series" and version in series["unavailableSyntax"].get(engine, []):
            yield "recursive_unavailable", "check_recursive_availability.py", ["--release", version]
        else:
            runner = "run_server.py" if domain == "projection" else f"run_server_{domain}.py"
            yield domain, runner, flags


def docker(*arguments, timeout=60):
    return subprocess.run(["docker", *arguments], capture_output=True, text=True, check=True, timeout=timeout).stdout.strip()


def image_identity(name):
    try:
        image = docker("image", "inspect", "--format", "{{.Id}} {{.Architecture}}", name)
    except subprocess.CalledProcessError:
        docker("pull", name, timeout=600)
        image = docker("image", "inspect", "--format", "{{.Id}} {{.Architecture}}", name)
    identity, architecture = image.split()
    require(re.fullmatch(r"sha256:[a-f0-9]{64}", identity) and architecture in {"amd64", "arm64"},
            "native-launch-invalid-local-image", image)
    return identity, architecture


def ready(engine, port):
    deadline = time.monotonic() + FIXTURE_STARTUP_SECONDS
    while time.monotonic() < deadline:
        try:
            with socket.create_connection(("127.0.0.1", port), timeout=2) as connection:
                if engine == "postgres":
                    connection.sendall(struct.pack(">II", 8, 80877103))
                    if connection.recv(1) in (b"S", b"N"):
                        return
                else:
                    with connection.makefile("rb") as stream:
                        greeting = stream.read(5)
                        if len(greeting) == 5 and greeting[4] == 10:
                            return
        except OSError:
            pass
        time.sleep(1)
    raise Failure("native-launch-fixture-timeout", {"engine": engine, "port": port})


def connection(engine, local):
    prefix = "POSTGRES" if engine == "postgres" else "MYSQL"
    dsn = f"{'pgsql' if engine == 'postgres' else 'mysql'}:host=127.0.0.1;port={local};dbname=ir_fixture"
    if engine == "mysql":
        dsn += ";charset=utf8mb4"
    return {
        **{key: value for key, value in os.environ.items() if not key.startswith("ZTD_IR_")},
        "PYTHONDONTWRITEBYTECODE": "1", "XDEBUG_MODE": "off",
        f"ZTD_IR_{prefix}_DSN": dsn, f"ZTD_IR_{prefix}_PASSWORD": "ir_fixture",
        f"ZTD_IR_{prefix}_USER": "postgres" if engine == "postgres" else "root",
    }


def run_jobs(jobs, args, raw, logs, environment, result):
    for domain, runner, flags in jobs:
        path = logs / f"{domain}.log"
        entry = {"domain": domain, "runner": runner, "runnerDigest": digest(TOOL / runner), "log": path.name}
        result.append(entry)
        with path.open("w") as output:
            try:
                run = subprocess.run(
                    [sys.executable, str(TOOL / runner), "--candidate", str(args.candidate),
                     "--tool-autoload", str(args.tool_autoload), *flags, "--out", str(raw / domain)],
                    env=environment, stdout=output, stderr=subprocess.STDOUT, timeout=RUNNER_SECONDS, check=False,
                )
                entry.update(outcome="exited", exitCode=run.returncode)
            except subprocess.TimeoutExpired:
                entry.update(outcome="timeout", exitCode=None)
        entry["logDigest"] = digest(path)


def run_release(fixture, jobs, args, raw_root, logs_root):
    engine, version, image = (fixture[key] for key in ("engine", "release", "image"))
    key = f"{engine}-{version}"
    raw, logs = raw_root / key, logs_root / key
    raw.mkdir()
    logs.mkdir()
    receipt = {"fixture": fixture, "runnerTimeoutSeconds": RUNNER_SECONDS, "setupTimeoutSeconds": FIXTURE_STARTUP_SECONDS,
               "planned": [domain for domain, _, _ in jobs], "executions": [], "failure": None}
    container = None
    try:
        identity, arch = image_identity(image)
        receipt.update(imageIdentity=identity, architecture=arch)
        flags = ["-e", "POSTGRES_PASSWORD=ir_fixture", "-e", "POSTGRES_DB=ir_fixture"] if engine == "postgres" else [
            "--tmpfs", "/var/lib/mysql:rw,nosuid,size=1073741824,mode=0750", "-e", "MYSQL_ROOT_PASSWORD=ir_fixture",
            "-e", "MYSQL_ROOT_HOST=%", "-e", "MYSQL_DATABASE=ir_fixture",
        ]
        port = fixture["port"]
        start = time.monotonic()
        container = docker("run", "--rm", "--pull=never", "--platform", f"linux/{arch}", "--detach",
                           "--publish", f"127.0.0.1::{port}", *flags, image)
        require(docker("inspect", "--format", "{{.Image}}", container) == identity, "native-launch-running-image", key)
        address = docker("port", container, f"{port}/tcp")
        local = re.fullmatch(r"127\.0\.0\.1:([0-9]+)", address)
        require(local is not None, "native-launch-unexpected-port", address)
        external = int(local[1])
        ready(engine, external)
        receipt["startupSeconds"] = round(time.monotonic() - start, 3)
        run_jobs(jobs, args, raw, logs, connection(engine, external), receipt["executions"])
    except (Failure, OSError, ValueError, subprocess.SubprocessError) as error:
        receipt["failure"] = {"type": type(error).__name__, "detail": str(error)}
    finally:
        if container is not None:
            try:
                docker("rm", "-f", "-v", container)
            except (OSError, subprocess.SubprocessError) as error:
                receipt["cleanupFailure"] = str(error)

    good = (receipt["failure"] is None and "cleanupFailure" not in receipt
            and [entry["domain"] for entry in receipt["executions"]] == receipt["planned"]
            and all(entry["outcome"] == "exited" and entry["exitCode"] == 0 for entry in receipt["executions"]))
    receipt["status"] = "observed" if good else "incomplete"
    note = logs / "index.json"
    write_json(note, receipt)
    return key, {"file": str(note.relative_to(logs_root)), "sha256": digest(note), "status": receipt["status"]}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate", required=True, type=Path)
    parser.add_argument("--candidate-sha", required=True)
    parser.add_argument("--tool-autoload", required=True, type=Path)
    parser.add_argument("--evidence-root", required=True, type=Path)
    parser.add_argument("--profile", action="append", help="Run selected diagnostic profiles such as postgres-16.6.")
    parser.add_argument("--jobs", type=int, choices=range(1, 5), default=2)
    args = parser.parse_args()
    args.candidate, args.tool_autoload, args.evidence_root = (
        path.resolve() for path in (args.candidate, args.tool_autoload, args.evidence_root)
    )
    require(all(path != args.candidate and args.candidate not in path.parents for path in (TOOL, args.tool_autoload, args.evidence_root)),
            "native-launch-verifier-input-inside-candidate")
    repo = TOOL.parents[2]
    workflow = repo / ".github/workflows/semantics-ir-release-matrix.yml"
    fixtures = release_fixtures(workflow, TOOL / "server-projection-v1.json")
    recursive = TOOL / "server-recursive-series-v2.json"
    require(digest(recursive) == CONTRACTS[recursive.name], "native-launch-recursive-profile-digest")
    series = json.loads(recursive.read_text())
    require(set(SINGLE_ENGINE) <= NATIVE.keys() and set(SINGLE_ENGINE.values()) <= PORTS.keys(), "native-launch-invalid-domain-registry")
    profiles = {f"{engine}-{release}": (engine, release) for engine, release in fixtures}
    selected = set(profiles) if not args.profile else set(args.profile)
    require(selected <= profiles.keys() and (not args.profile or len(selected) == len(args.profile)),
            "native-launch-unregistered-profile", sorted(selected - profiles.keys()))
    sha, distribution = candidate_distribution(args.candidate, args.candidate_sha)
    raw = args.evidence_root / "native"
    logs = args.evidence_root / "native-launch"
    raw.mkdir(parents=True, exist_ok=False)
    logs.mkdir(exist_ok=False)
    manifest = logs / "distribution.json"
    write_json(manifest, distribution)
    plan = {key: list(release_jobs(profiles[key], series)) for key in sorted(selected)}

    def run(key):
        print(f"Running {key}", flush=True)
        name, receipt = run_release(fixtures[profiles[key]], plan[key], args, raw, logs)
        print(f"{name} {receipt['status']}", flush=True)
        return name, receipt

    ordered = sorted(plan, key=lambda key: profiles[key][0] != "postgres")
    with ThreadPoolExecutor(max_workers=args.jobs) as pool:
        runs = dict(pool.map(run, ordered))
    failed = [key for key in plan if runs[key]["status"] != "observed"]
    write_json(logs / "index.json", {
        "candidate": sha,
        "distribution": digest(manifest),
        "status": "incomplete" if failed else "observed-subset" if selected != profiles.keys() else "observed-matrix",
        "workflowDigest": digest(workflow),
        "recursiveContractDigest": digest(recursive),
        "launcherDigest": digest(Path(__file__)),
        "omittedProfiles": sorted(profiles.keys() - selected),
        "releases": runs,
        "failed": failed,
    })
    verify_candidate(args.candidate, sha, distribution)
    require(not failed, "native-launch-incomplete-matrix", failed)


if __name__ == "__main__":
    main()
