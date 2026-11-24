"""Execute protected control cases for the one allowed native fixture startup retry."""

import json
import subprocess
from tempfile import TemporaryDirectory
from pathlib import Path
from unittest.mock import patch

from run import require
from run_native_matrix import PORT_RECOVERY, run_release

IMAGE = "sha256:" + "a" * 64
COLLISION = "RootlessKit PortManager.AddPort(): listen tcp4 127.0.0.1:32796: bind: address already in use"
PROFILE = {"engine": "mysql", "release": "8.0.44", "image": "mysql@" + IMAGE, "port": 3306}
JOBS = [("result", "run_server_result.py", ["--engine", "mysql", "--release", "8.0.44"])]
CONTROL_CASES = {
    "restart-recoverable-fixture": "observed",
    "exhaust-fixture-retry": "incomplete",
    "retain-a-residual-fixture": "incomplete",
    "unrelated-fixture-setup-error": "incomplete",
    "fixture-launch-timeout": "incomplete",
    "completed-launch-then-domain-failure": "incomplete",
}


def simulated(outcomes, existing=False, domain_failure=False):
    launch_names, calls, connections = [], [], []

    def docker(operation, *arguments, **_):
        if operation == "run":
            name = arguments[arguments.index("--name") + 1]
            launch_names.append(name)
            attempt = outcomes[len(launch_names) - 1]
            require(arguments[-1] == PROFILE["image"] and arguments[arguments.index("--publish") + 1] == "127.0.0.1::3306",
                    "fixture-control-launch-input")
            if attempt == "timeout":
                raise subprocess.TimeoutExpired([operation, *arguments], 900)
            if attempt != "launch":
                raise subprocess.CalledProcessError(125, [operation, *arguments], "assigned-name", attempt)
            return "good-fixture"
        if operation == "inspect":
            query, name = arguments[-2:]
            if query == "{{.Image}}" and name == "good-fixture":
                return IMAGE
            if query == "{{.Id}}" and existing:
                return "residual-fixture"
            raise subprocess.CalledProcessError(1, [operation, *arguments], "", f"Error: no such object: {name}")
        if operation == "port":
            return "127.0.0.1:34567"
        if operation == "rm":
            calls.append(arguments[-1])
            return arguments[-1]
        raise AssertionError((operation, arguments))

    def connect(*_):
        connections.append("profile-verified")
        return {"status": "verified"}

    def execute(jobs, _, _raw, _logs, _env, receipts, container):
        require(jobs == JOBS and connections == ["profile-verified"], "fixture-control-profile-gate")
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
            _, run = run_release(PROFILE, JOBS, None, raw, logs)
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
        evidence.append({"case": name, "signal": f"native-fixture-{status}", "observed": observed})
    return evidence


if __name__ == "__main__":
    print(json.dumps(controls(), indent=2))
