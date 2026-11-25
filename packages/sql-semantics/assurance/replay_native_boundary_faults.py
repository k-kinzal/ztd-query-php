"""Reconstruct the exact source mutations and audit native boundary execution receipts."""

import argparse
from difflib import unified_diff
from hashlib import sha256
import json
from pathlib import Path
from tempfile import TemporaryDirectory

from check_native_boundaries import ENGINES, FAULT_REPLAY, fault_plan, faults
from mysql_signed_storage_certificate import certificate as signed_storage_certificate
from replay_sqlite_faults import checked_file, exact_patch, links, verify_artifacts
from run import Failure, candidate_distribution, digest, require, verify_distribution, write_json


def checked_receipts(engine, plan, observed):
    wanted = [{**entry, "healthy": "verified", "observed": entry["expected"]} for entry in plan]
    require(len(observed) == len(wanted), "native-fault-replay-missing-case", engine)
    require(observed == wanted, "native-fault-replay-receipt-mismatch", engine)


def expect_failure(call, signal):
    try:
        call()
    except Failure as failure:
        require(failure.signal == signal, "native-fault-replay-control-wrong-signal", failure.detail)
        return signal
    raise Failure("native-fault-replay-control-accepted", signal)


def comparator_controls(checkout, plan, observed):
    missing = expect_failure(lambda: checked_receipts("sqlite", plan, observed[1:]), "native-fault-replay-missing-case")
    changed = {**observed[0], "mutation": "0" * 64}
    corrupt = expect_failure(lambda: checked_receipts("sqlite", plan, [changed, *observed[1:]]),
                             "native-fault-replay-receipt-mismatch")
    first = plan[0]
    relative = first["path"]
    fault = faults("sqlite")[0]
    source = checkout / "packages" / relative
    healthy = source.read_text()
    patched = exact_patch(source, fault.before, fault.after, fault.name)
    replacement = patched + "\n"
    identity = sha256(replacement.encode()).hexdigest()
    patch = "".join(unified_diff(healthy.splitlines(keepends=True), replacement.splitlines(keepends=True),
                                 f"a/{relative}", f"b/{relative}"))
    with TemporaryDirectory(prefix="semantics-ir-native-fault-replay-") as temporary:
        directory = Path(temporary)
        altered = directory / relative
        altered.parent.mkdir(parents=True)
        altered.write_text(replacement)
        (directory / "injected.diff").write_text(patch)
        write_json(directory / "result.json", {**observed[0], "mutation": identity})
        wrong = expect_failure(lambda: verify_artifacts(directory, relative, source, first["source"], identity,
                                                        patched=patched, prefixes=True), "sqlite-fault-injection-mismatch")
    return {"case": first["name"], "missingCase": missing, "changedReceipt": corrupt,
            "changedReplacement": {"signal": wrong, "unexpectedMutation": identity, "patch": sha256(patch.encode()).hexdigest()}}


def replay(checkout, directory, index, distribution):
    require(index["status"] == "provisional-native-construction-shape", "native-fault-replay-index-status")
    descriptor = index["faultPlan"]
    require(descriptor["path"] == "fault-plan.json", "native-fault-replay-plan-path")
    record = checked_file(directory, directory / descriptor["path"])
    require(digest(record) == descriptor["sha256"], "native-fault-replay-plan-digest")
    planned = fault_plan(checkout, distribution)
    require(json.loads(record.read_text()) == {"candidate": index["candidate"], "contract": FAULT_REPLAY, "faults": planned},
            "native-fault-replay-plan-mismatch")
    require(index["results"].keys() == planned.keys(), "native-fault-replay-dialect-set")
    signed = index["mysqlSignedStorage"]
    require(signed["path"] == "mysql-signed-storage.json", "mysql-signed-certificate-path")
    proof = checked_file(directory, directory / signed["path"])
    require(digest(proof) == signed["sha256"] and json.loads(proof.read_text()) == {
        "candidate": index["candidate"], "distribution": index["distribution"],
        "certificate": signed_storage_certificate(distribution, index["results"]["mysql"]["healthy"]),
    }, "mysql-signed-certificate-mismatch")
    results = {}
    for engine in ENGINES:
        shape = index["results"][engine]
        observed = shape["faults"]
        require(shape["healthy"]["status"] == "verified", "native-fault-replay-healthy-control", engine)
        checked_receipts(engine, planned[engine], observed)
        first = next(entry for entry in planned[engine] if entry["mechanism"] == "registered-method-template")
        expected_control = {"case": first["name"], "role": first["role"], "source": first["source"], "mutation": first["mutation"],
                            "healthyTarget": {"status": "method-target-verified", "boundary": first["role"]},
                            "targetedRejection": first["expected"], "fullRejection": first["expected"]}
        require(shape.get("methodControl") == expected_control, "native-fault-replay-method-equivalence", engine)
        witnesses = {}
        for receipt, fault in zip(observed, faults(engine)):
            relative = receipt["path"]
            folder = directory / engine / fault.name
            path = checked_file(folder, folder / "result.json")
            require(json.loads(path.read_text()) == receipt, "native-fault-replay-raw-receipt", fault.name)
            source = checkout / "packages" / relative
            patched = exact_patch(source, fault.before, fault.after, fault.name)
            files = verify_artifacts(folder, relative, source, receipt["source"], receipt["mutation"],
                                     patched=patched, prefixes=True)
            witnesses[fault.name] = {"role": fault.role, "signal": receipt["observed"], "mechanism": receipt["mechanism"],
                                     "artifacts": links(folder, directory, relative, {**files, "receipt": digest(path)})}
        results[engine] = witnesses
    return {"status": "provisional-native-fault-replay", "candidate": index["candidate"], "contract": FAULT_REPLAY,
            "plan": descriptor, "dialects": results, "mysqlSignedStorage": signed,
            "methodEquivalence": {engine: index["results"][engine]["methodControl"] for engine in ENGINES},
            "controls": comparator_controls(checkout, planned["sqlite"], index["results"]["sqlite"]["faults"])}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    for flag in ("--candidate", "--evidence", "--out"):
        parser.add_argument(flag, type=Path, required=True)
    args = parser.parse_args()
    checkout, directory, output = (item.resolve() for item in (args.candidate, args.evidence, args.out))
    require(checkout != directory and checkout not in directory.parents and directory in output.parents
            and not output.exists(), "native-fault-replay-output-path")
    commit, distribution = candidate_distribution(checkout)
    manifest = checked_file(directory, directory / "distribution.json")
    verify_distribution(json.loads(manifest.read_text()), distribution)
    index = json.loads(checked_file(directory, directory / "index.json").read_text())
    require(index["candidate"] == commit and index["distribution"] == digest(manifest), "native-fault-replay-candidate")
    write_json(output, replay(checkout, directory, index, distribution))


if __name__ == "__main__":
    main()
