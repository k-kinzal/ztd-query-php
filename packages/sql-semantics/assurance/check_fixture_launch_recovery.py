"""Execute protected control cases for the one allowed native fixture startup retry."""

import json
import subprocess
from tempfile import TemporaryDirectory
from pathlib import Path
from unittest.mock import patch

from native_postgres_fixture import IMAGES, fixed_storage, storage_option, verify_storage
from run import require
from run_native_matrix import PORT_RECOVERY, run_release

IMAGE = "sha256:" + "a" * 64
COLLISION = "RootlessKit PortManager.AddPort(): listen tcp4 127.0.0.1:32796: bind: address already in use"
PROFILE = {"engine": "mysql", "release": "8.0.44", "image": "mysql@" + IMAGE, "port": 3306}
POSTGRES = {"engine": "postgres", "release": "17.2", "image": IMAGES["17.2"], "port": 5432}
CONTROL_CASES = {
    "restart-recoverable-fixture": "observed",
    "exhaust-fixture-retry": "incomplete",
    "retain-a-residual-fixture": "incomplete",
    "unrelated-fixture-setup-error": "incomplete",
    "fixture-launch-timeout": "incomplete",
    "completed-launch-then-domain-failure": "incomplete",
    "registered-postgres-storage": "observed",
    "unexpected-postgres-filesystem": "incomplete",
}


def simulated(outcomes, existing=False, domain_failure=False, profile=PROFILE, filesystem="tmpfs"):
    launch_names, calls, connections = [], [], []
    engine, release, port = (profile[key] for key in ("engine", "release", "port"))
    jobs = [("result", "run_server_result.py", ["--engine", engine, "--release", release])]

    def docker(operation, *arguments, **_):
        if operation == "run":
            name = arguments[arguments.index("--name") + 1]
            launch_names.append(name)
            attempt = outcomes[len(launch_names) - 1]
            require(arguments[-1] == profile["image"] and arguments[arguments.index("--publish") + 1] == f"127.0.0.1::{port}",
                    "fixture-control-launch-input")
            if engine == "postgres":
                require(arguments[arguments.index("--tmpfs") + 1] == storage_option(), "fixture-control-storage-option")
            if attempt == "timeout":
                raise subprocess.TimeoutExpired([operation, *arguments], 900)
            if attempt != "launch":
                raise subprocess.CalledProcessError(125, [operation, *arguments], "assigned-name", attempt)
            return "good-fixture"
        if operation == "inspect":
            query, name = arguments[-2:]
            if query == "{{.Image}}" and name == "good-fixture":
                return IMAGE
            if engine == "postgres" and query == "{{json .HostConfig.Tmpfs}}" and name == "good-fixture":
                return json.dumps(fixed_storage()["mount"])
            if query == "{{.Id}}" and existing:
                return "residual-fixture"
            raise subprocess.CalledProcessError(1, [operation, *arguments], "", f"Error: no such object: {name}")
        if operation == "port":
            return "127.0.0.1:34567"
        if operation == "exec" and engine == "postgres":
            directory, = fixed_storage()["mount"]
            require(arguments == ("--user", "postgres", "good-fixture", "stat", "-f", "-c", "%T", directory),
                    "fixture-control-storage-probe", arguments)
            return filesystem
        if operation == "rm":
            calls.append(arguments[-1])
            return arguments[-1]
        raise AssertionError((operation, arguments))

    def connect(*_):
        connections.append("profile-verified")
        return {"status": "verified"}

    def execute(plan, _, _raw, _logs, _env, receipts, container):
        require(plan == jobs and connections == ["profile-verified"], "fixture-control-profile-gate")
        require(container == "good-fixture", "fixture-control-execution-container", container)
        receipts.extend({"domain": scope, "runner": task, "outcome": "exited", "exitCode": 1 if domain_failure else 0}
                        for scope, task, _ in jobs)

    with TemporaryDirectory(prefix="semantics-ir-fixture-recovery-") as folder:
        root = Path(folder)
        raw, logs = root / "raw", root / "logs"
        raw.mkdir()
        logs.mkdir()
        with (patch("run_native_matrix.docker", docker), patch("run_native_matrix.image_identity", return_value=(IMAGE, "arm64")),
              patch("run_native_matrix.ready"), patch("run_native_matrix.check_fixture_connection", connect),
              patch("run_native_matrix.run_jobs", execute)):
            _, run = run_release(profile, jobs, None, raw, logs)
        receipt = json.loads((logs / run["file"]).read_text())
    return {"launchNames": launch_names, "cleanup": calls, "observedProfiles": connections, "receipt": receipt}


def controls():
    outcomes = {
        "restart-recoverable-fixture": ((COLLISION, "launch"), {}, "observed", 2, 1),
        "exhaust-fixture-retry": ((COLLISION, COLLISION), {}, "incomplete", 2, 0),
        "retain-a-residual-fixture": ((COLLISION,), {"existing": True}, "incomplete", 1, 0),
        "unrelated-fixture-setup-error": (("denied by the local daemon",), {}, "incomplete", 1, 0),
        "fixture-launch-timeout": (("timeout",), {}, "incomplete", 1, 0),
        "completed-launch-then-domain-failure": (("launch",), {"domain_failure": True}, "incomplete", 1, 1),
        "registered-postgres-storage": (("launch",), {"profile": POSTGRES}, "observed", 1, 1),
        "unexpected-postgres-filesystem": (("launch",), {"profile": POSTGRES, "filesystem": "ext2/ext3"}, "incomplete", 1, 0),
    }
    evidence = []
    for name, (responses, options, status, launches, executions) in outcomes.items():
        observed = simulated(responses, **options)
        receipt = observed["receipt"]
        require(receipt["status"] == status == CONTROL_CASES[name] and len(observed["launchNames"]) == launches
                and len(receipt["executions"]) == executions
                and len(observed["observedProfiles"]) == executions, "fixture-control-expected-outcome", name)
        if responses[0] == COLLISION:
            first = receipt["failedLaunch"]
            require(first["contract"] == PORT_RECOVERY and first["containerName"] == observed["launchNames"][0]
                    and first["failure"]["stderr"] == COLLISION
                    and (first.get("retriedAs") == observed["launchNames"][1] if launches == 2 else "retriedAs" not in first),
                    "fixture-control-preserved-attempt", name)
        else:
            require("failedLaunch" not in receipt, "fixture-control-unsafe-retry", name)
        if options.get("existing"):
            require(observed["cleanup"] == ["residual-fixture"], "fixture-control-residual-cleanup", name)
        verify_storage_control(name, receipt)
        evidence.append({"case": name, "signal": f"native-fixture-{status}", "observed": observed})
    return evidence


def verify_storage_control(name, receipt):
    if name == "registered-postgres-storage":
        verify_storage(receipt["fixtureStorage"], name)
    if name == "unexpected-postgres-filesystem":
        require(receipt["fixtureStorage"] == {**fixed_storage(), "filesystem": "ext2/ext3"}
                and not receipt["executions"] and receipt["failure"]["phase"] == "fixture-storage"
                and receipt["failure"]["detail"].partition(": ")[0] == "native-fixture-storage-mismatch",
                "fixture-control-rejects-filesystem", name)


if __name__ == "__main__":
    print(json.dumps(controls(), indent=2))
