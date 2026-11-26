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
from importlib import import_module
from pathlib import Path
from types import SimpleNamespace
from uuid import uuid4

from check_native_workflow import audit as audit_workflow
from native_postgres_fixture import DOMAINS as FIXTURE_DOMAINS, ENV as FIXTURE_CONTAINER, IMAGES as POSTGRES_IMAGES
from native_postgres_fixture import STORAGE_CONTRACT, observe_storage, storage_option, verify_storage
from native_schedule import NATIVE, SINGLE_ENGINE, release_subset
from run import TOOL, Failure, candidate_distribution, digest, require, verify_candidate, write_json
from run_server import CONTRACT as PROJECTION, check_profile
from run_server_delete import version_tag
from run_server_join import checked_fault_plan
from scope import CONTRACTS

PORT_RECOVERY = "b26ae212606640f07c92c9ad48774964a96116a3adb07a05958b09813a3e4ee9"
PORT_COLLISION = re.compile(r"RootlessKit PortManager\.AddPort\(\): listen tcp4 127\.0\.0\.1:([0-9]{1,5}): bind: address already in use")
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
        require(engine != "postgres" or POSTGRES_IMAGES[version] == image, "native-launch-postgres-fixture-image", key)
        releases[key] = {"engine": engine, "release": version, "port": int(port), "image": image}
    require(releases.keys() == expected, "native-launch-fixture-release-set", sorted(expected - releases.keys()))
    return releases


def release_jobs(profile, series):
    engine, version = profile
    for domain in NATIVE:
        if engine != SINGLE_ENGINE.get(domain, engine):
            continue
        subset = release_subset(domain)
        if subset is not None and version not in subset:
            continue
        flags = ["--engine", engine, "--release", version]
        if domain == "recursive_series" and version in series["unavailableSyntax"].get(engine, []):
            yield "recursive_unavailable", "check_recursive_availability.py", ["--release", version]
        else:
            runner = "run_server.py" if domain == "projection" else f"run_server_{domain}.py"
            yield domain, runner, flags


def docker(*arguments, timeout=60):
    return subprocess.run(["docker", *arguments], capture_output=True, text=True, check=True, timeout=timeout).stdout.strip()


def command_failure(error):
    return {"exitCode": error.returncode, "stdout": error.stdout, "stderr": error.stderr}


def absent_fixture(error):
    message = error.stderr.casefold()
    return error.returncode == 1 and ("no such object:" in message or "no such container:" in message)


def recoverable_port_collision(error):
    message = PORT_COLLISION.search(error.stderr)
    return error.returncode == 125 and message is not None and 0 < int(message[1]) <= 65535


def fixture_port_recovery():
    path = TOOL / "native-fixture-port-recovery-v1.json"
    require(digest(path) == PORT_RECOVERY, "native-fixture-port-recovery-contract")
    conditions = json.loads(path.read_text())["conditions"]
    require(conditions["phase"] == "container-launch" and conditions["commandExit"] == 125
            and conditions["maximumRetries"] == 1 and conditions["setupTimeoutSeconds"] == FIXTURE_STARTUP_SECONDS,
            "native-fixture-port-recovery-conditions")


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


def startup_remaining(deadline):
    remaining = deadline - time.monotonic()
    require(remaining > 0, "native-launch-fixture-timeout")
    return remaining


def ready(engine, port, deadline):
    while time.monotonic() < deadline:
        try:
            with socket.create_connection(("127.0.0.1", port), timeout=min(2, startup_remaining(deadline))) as connection:
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
        time.sleep(min(1, max(0, deadline - time.monotonic())))
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


def check_fixture_connection(engine, release, environment, deadline, path):
    """Require a native PDO session before spending the registered domain budgets."""
    with path.open("w") as log:
        while True:
            remaining = startup_remaining(deadline)
            try:
                probe = subprocess.run(
                    ["php", str(TOOL / "server_observer.php"), engine],
                    input="", text=True, capture_output=True, check=False, env=environment, timeout=min(10, remaining),
                )
            except subprocess.TimeoutExpired:
                event = {"status": "timeout"}
                log.write(json.dumps(event) + "\n")
                log.flush()
                continue
            event = {"status": "exited", "code": probe.returncode, "stdout": probe.stdout, "stderr": probe.stderr}
            log.write(json.dumps(event) + "\n")
            log.flush()
            if probe.returncode == 0:
                observed = json.loads(probe.stdout)
                check_profile(engine, release, SimpleNamespace(environment=observed))
                return observed
            require("Connection refused" in probe.stderr or "database system is starting up" in probe.stderr,
                    "native-launch-fixture-connection", {"engine": engine, "release": release, "log": path.name})
            time.sleep(min(1, startup_remaining(deadline)))


def run_jobs(jobs, args, raw, logs, environment, result, container):
    for domain, runner, flags in jobs:
        require(fixture_state(container)["running"], "native-fixture-stopped-before-domain", domain)
        path = logs / f"{domain}.log"
        entry = {"domain": domain, "runner": runner, "runnerDigest": digest(TOOL / runner), "log": path.name}
        result.append(entry)
        job_environment = {**environment, FIXTURE_CONTAINER: container} if domain in FIXTURE_DOMAINS else environment
        with path.open("w") as output:
            try:
                run = subprocess.run(
                    [sys.executable, str(TOOL / runner), "--candidate", str(args.candidate),
                     "--tool-autoload", str(args.tool_autoload), *flags, "--out", str(raw / domain)],
                    env=job_environment, stdout=output, stderr=subprocess.STDOUT, timeout=RUNNER_SECONDS, check=False,
                )
                entry.update(outcome="exited", exitCode=run.returncode)
            except subprocess.TimeoutExpired:
                entry.update(outcome="timeout", exitCode=None)
        entry["logDigest"] = digest(path)


def fixture_state(container):
    state = json.loads(docker("inspect", "--format", "{{json .State}}", container))
    return {"status": state["Status"], "running": state["Running"], "oomKilled": state["OOMKilled"],
            "exitCode": state["ExitCode"], "error": state["Error"], "finishedAt": state["FinishedAt"]}


def run_release(fixture, jobs, args, raw_root, logs_root):
    engine, version, image = (fixture[key] for key in ("engine", "release", "image"))
    key = f"{engine}-{version}"
    raw, logs = raw_root / key, logs_root / key
    raw.mkdir()
    logs.mkdir()
    receipt = {"fixture": fixture, "runnerTimeoutSeconds": RUNNER_SECONDS, "setupTimeoutSeconds": FIXTURE_STARTUP_SECONDS,
               "planned": [domain for domain, _, _ in jobs], "executions": [], "failure": None}
    container = None
    phase = "fixture-image"
    launch_name = f"ztd-ir-fixture-{uuid4().hex}"
    try:
        identity, arch = image_identity(image)
        receipt.update(imageIdentity=identity, architecture=arch)
        flags = (
            ["--tmpfs", storage_option(), "-e", "POSTGRES_PASSWORD=ir_fixture", "-e", "POSTGRES_DB=ir_fixture"]
            if engine == "postgres" else [
                "--tmpfs", "/var/lib/mysql:rw,nosuid,size=1073741824,mode=0750", "-e", "MYSQL_ROOT_PASSWORD=ir_fixture",
                "-e", "MYSQL_ROOT_HOST=%", "-e", "MYSQL_DATABASE=ir_fixture",
            ]
        )
        port = fixture["port"]
        start = time.monotonic()
        deadline = start + FIXTURE_STARTUP_SECONDS
        phase = "container-launch"
        fixture_port_recovery()
        for attempt in range(2):
            try:
                container = docker("run", "--pull=never", "--name", launch_name, "--platform", f"linux/{arch}", "--detach",
                                   "--publish", f"127.0.0.1::{port}", *flags, image, timeout=startup_remaining(deadline))
            except subprocess.CalledProcessError as error:
                if attempt != 0 or not recoverable_port_collision(error):
                    raise
                failed_launch = {"contract": PORT_RECOVERY, "containerName": launch_name, "failure": command_failure(error)}
                receipt["failedLaunch"] = failed_launch
                try:
                    container = docker("inspect", "--format", "{{.Id}}", launch_name, timeout=startup_remaining(deadline))
                except subprocess.CalledProcessError as lookup:
                    if not absent_fixture(lookup):
                        receipt["recoveryWithheld"] = command_failure(lookup)
                        raise error from lookup
                    launch_name = f"ztd-ir-fixture-{uuid4().hex}"
                    failed_launch.update(inspect=command_failure(lookup), retriedAs=launch_name)
                else:
                    receipt["recoveryWithheld"] = {"container": container}
                    raise
            else:
                break
        phase = "fixture-ready"
        require(docker("inspect", "--format", "{{.Image}}", container, timeout=startup_remaining(deadline)) == identity,
                "native-launch-running-image", key)
        address = docker("port", container, f"{port}/tcp", timeout=startup_remaining(deadline))
        local = re.fullmatch(r"127\.0\.0\.1:([0-9]+)", address)
        require(local is not None, "native-launch-unexpected-port", address)
        external = int(local[1])
        ready(engine, external, deadline)
        if engine == "postgres":
            phase = "fixture-storage"
            receipt["fixtureStorage"] = observe_storage(
                lambda *argv: docker(*argv, timeout=startup_remaining(deadline)), container
            )
            verify_storage(receipt["fixtureStorage"], key)
        phase = "fixture-profile"
        environment = connection(engine, external)
        probe_log = logs / "fixture-probe.jsonl"
        profile = check_fixture_connection(engine, version, environment, deadline, probe_log)
        startup_remaining(deadline)
        receipt["observedProfile"] = profile
        receipt["startupSeconds"] = round(time.monotonic() - start, 3)
        phase = "execution"
        run_jobs(jobs, args, raw, logs, environment, receipt["executions"], container)
    except (Failure, OSError, ValueError, KeyError, TypeError, subprocess.SubprocessError) as error:
        receipt["failure"] = {"phase": phase, "type": type(error).__name__, "detail": str(error)}
        if isinstance(error, subprocess.CalledProcessError):
            receipt["failure"].update(command_failure(error))
        if container is None and phase == "container-launch":
            # The daemon may have assigned the known name before the client timed out.
            try:
                container = docker("inspect", "--format", "{{.Id}}", launch_name)
            except subprocess.CalledProcessError as lookup:
                if not absent_fixture(lookup):
                    receipt["cleanupFailure"] = lookup.stderr
            except (OSError, subprocess.SubprocessError) as cleanup:
                receipt["cleanupFailure"] = str(cleanup)
    finally:
        if container:
            try:
                state = fixture_state(container)
                receipt["fixtureStateAtCleanup"] = state
                if not state["running"] and receipt["failure"] is None:
                    receipt["failure"] = {"phase": "fixture-stopped", "type": "Failure",
                                          "detail": "The fixture exited before the launcher cleaned up."}
            except (OSError, ValueError, KeyError, TypeError, subprocess.SubprocessError) as inspection:
                receipt["fixtureStateUnavailable"] = str(inspection)
                if receipt["failure"] is None:
                    receipt["failure"] = {"phase": "fixture-state", "type": type(inspection).__name__,
                                          "detail": str(inspection)}
            try:
                docker("rm", "-f", "-v", container)
            except (OSError, subprocess.SubprocessError) as error:
                receipt["cleanupFailure"] = str(error)

    probe_log = logs / "fixture-probe.jsonl"
    if probe_log.is_file():
        receipt["fixtureProbe"] = {"path": probe_log.name, "sha256": digest(probe_log)}
    good = (receipt["failure"] is None and "cleanupFailure" not in receipt
            and [entry["domain"] for entry in receipt["executions"]] == receipt["planned"]
            and all(entry["outcome"] == "exited" and entry["exitCode"] == 0 for entry in receipt["executions"]))
    receipt["status"] = "observed" if good else "incomplete"
    note = logs / "index.json"
    write_json(note, receipt)
    return key, {"file": str(note.relative_to(logs_root)), "sha256": digest(note), "status": receipt["status"]}


def renderer_fault_sources(candidate, profiles, plan):
    """Bind the registered renderer mutations to this candidate before execution."""
    inspected, invalid = {}, []
    for key, jobs in plan.items():
        engine, release = profiles[key]
        tag = version_tag(engine, release)
        inspected[key] = {}
        for domain, runner, _ in jobs:
            if domain in {"projection", "recursive_unavailable"}:
                continue
            cases = inspected[key][domain] = []
            try:
                faults = checked_fault_plan(import_module(Path(runner).stem), engine, tag)
            except Failure as error:
                invalid.append({"release": key, "domain": domain, "failure": error.signal, "detail": error.detail})
                continue
            for fault in faults:
                path = candidate / "packages" / fault.path
                source = path.read_text() if path.is_file() else ""
                count = source.count(fault.before) if source else 0
                case = {"id": fault.name, "path": fault.path, "source": digest(path) if source else None,
                        "matches": count, "wouldChangeSource": count == 1 and fault.before != fault.replacement}
                cases.append(case)
                if not case["wouldChangeSource"]:
                    invalid.append({"release": key, "domain": domain, **case})
    return {"status": "incomplete" if invalid else "matched", "releases": inspected, "invalid": invalid}


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
    all_jobs = {profile: list(release_jobs(profile, series)) for profile in fixtures}
    workflow_routes = audit_workflow(workflow, all_jobs)
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
    routes = logs / "workflow-routes.json"
    write_json(routes, workflow_routes)
    plan = {key: all_jobs[profiles[key]] for key in sorted(selected)}
    anchors = logs / "renderer-fault-sources.json"
    anchor_report = renderer_fault_sources(args.candidate, profiles, plan)
    write_json(anchors, {"candidate": sha, **anchor_report})
    for fault in anchor_report["invalid"]:
        print(f"Unmatched renderer fault {fault['release']}/{fault['domain']}/{fault['id']}", flush=True)

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
        "status": "incomplete" if failed or anchor_report["invalid"] else "observed-subset" if selected != profiles.keys() else "observed-matrix",
        "workflowDigest": digest(workflow),
        "workflowRouteAudit": {"path": routes.name, "sha256": digest(routes)},
        "recursiveContractDigest": digest(recursive),
        "fixtureRecoveryContractDigest": PORT_RECOVERY,
        "postgresStorageContractDigest": STORAGE_CONTRACT,
        "launcherDigest": digest(Path(__file__)),
        "rendererFaultSources": {"path": anchors.name, "digest": digest(anchors), "status": anchor_report["status"]},
        "omittedProfiles": sorted(profiles.keys() - selected),
        "releases": runs,
        "failed": failed,
    })
    verify_candidate(args.candidate, sha, distribution)
    require(not failed, "native-launch-incomplete-matrix", failed)
    require(not anchor_report["invalid"], "native-launch-renderer-fault-sources", anchor_report["invalid"])


if __name__ == "__main__":
    main()
