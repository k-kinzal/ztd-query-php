"""Verify exact fault overlays and replay recorded SQLite renderer witnesses."""

import difflib
import json
import re
from types import SimpleNamespace

from evidence_transcript import canonical
from renderer_fault_dispatch import verify as verify_fault_dispatch
from run import Failure, TOOL, digest, require
from sqlite_relational_mutations import RESTORATION, RESTORATION_GUARD, fixtures as relational_fixtures

CONTRACT = "173cdac8a6d03b95061ddcf215799e3f803bead8a644795f6cc3d5c17a70a714"
CONTRACT_NAME = "sqlite-fault-replay-v1.json"
CANDIDATE_SOURCE = re.compile(r"sql-semantics-sqlite/src/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+\.php")
INITIAL_TOOLS = {"cases.py", "run.py", "contract-v1.json"}
INITIAL = {
    "F01": ("Ir/Select.php", "negative-boundary-mismatch"),
    "F02": ("Ir/From/SelectBounds.php", "positive-construction-failed"),
    "F03": ("Ir/SelectSql.php", "meaning-mismatch"),
    "F04": ("Ir/TableSource.php", "shape-mismatch"),
    "F05": ("Ir/Select.php", "shape-mismatch"),
    "F06": ("Ir/Select.php", "meaning-mismatch"),
    "F07": ("Ir/SelectSql.php", "meaning-mismatch"),
    "F08": ("cases.py", "finite-domain-incomplete"),
    "F09": ("run.py", "comparator-accepted-known-difference"),
    "F10": ("run.py", "unbalanced-execution"),
    "F11": ("contract-v1.json", "contract-mismatch"),
    "F12": ("Ir/Select.php", "distribution-mismatch"),
}



def contract():
    file = TOOL / CONTRACT_NAME
    require(digest(file) == CONTRACT, "sqlite-fault-replay-contract-mismatch")
    return json.loads(file.read_text())


def checked_file(directory, file):
    path = file.resolve()
    require(directory.resolve() in path.parents and path.is_file(), "sqlite-fault-artifact-missing-or-external", str(file))
    return path


def verify_artifacts(folder, relative, source, original, changed, *, patched, prefixes):
    if prefixes:
        left, right = f"a/{relative}", f"b/{relative}"
    else:
        left = right = relative
    text = source.read_text()
    mutation = checked_file(folder, folder / relative)
    diff = checked_file(folder, folder / "injected.diff")
    alteration = mutation.read_text()
    reconstructed = "".join(difflib.unified_diff(text.splitlines(keepends=True), alteration.splitlines(keepends=True), left, right))
    require(digest(source) == original and digest(mutation) == changed and original != changed,
            "sqlite-fault-source-digest", relative)
    require(checked_file(folder, folder / "result.json").is_file() and bool(reconstructed) and diff.read_text() == reconstructed,
            "sqlite-fault-patch-mismatch", relative)
    require(patched is None or alteration == patched, "sqlite-fault-injection-mismatch", relative)
    return {"source": original, "mutation": changed, "patch": digest(diff)}


def links(folder, root, relative, witness):
    return {
        file.relative_to(root).as_posix(): checksum
        for file, checksum in (
            (folder / relative, witness["mutation"]),
            (folder / "injected.diff", witness["patch"]),
            (folder / "result.json", witness["receipt"]),
        )
    }


def exact_patch(source, before, after, name):
    original = source.read_text()
    require(original.count(before) == 1, "sqlite-fault-patch-anchor", name)
    return original.replace(before, after)


def observed_fault(database, case, request, healthy, altered, probe, signal):
    require(type(healthy) is dict and type(altered) is dict, "sqlite-fault-witness-missing", case)
    probe(request, healthy, database)
    try:
        probe(request, altered, database)
    except Failure as error:
        require(error.signal == signal, "sqlite-fault-replay-signal", {"case": case, "actual": error.signal, "expected": signal})
        return error.detail
    raise Failure("sqlite-fault-replay-accepted-mutant", case)


def replay_registered(args, db, fixtures, probe, _script="worker.php"):
    dispatch = verify_fault_dispatch(args.directory, fixtures, _script, args.records)
    received = {record["case"]: record for record in args.records}
    declared = {fault.name: fault for fault in fixtures}
    require(len(received) == len(args.records) and len(declared) == len(fixtures) and received.keys() == declared.keys(),
            "sqlite-fault-replay-set-mismatch", sorted(received.keys() ^ declared.keys()))
    evidence = {}
    for case, fault in declared.items():
        record = received[case]
        folder = args.directory / "faults" / case
        source = args.candidate / "packages" / fault.path
        require(CANDIDATE_SOURCE.fullmatch(fault.path) is not None, "sqlite-fault-source-path", fault.path)
        receipt = checked_file(folder, folder / "result.json")
        require(record == json.loads(receipt.read_text()) and record["file"] == fault.path
                and canonical(record["input"]) == canonical(fault.item)
                and record["expected"] == record["observed"] == fault.signal and record["control"] == "passed",
                "sqlite-fault-replay-receipt", case)
        verified = verify_artifacts(folder, fault.path, source, args.manifest["packages/" + fault.path], record["mutation"],
                                    patched=exact_patch(source, fault.before, fault.replacement, case), prefixes=True)
        require(verified["source"] == record["source"], "sqlite-fault-replay-baseline", case)
        detail = observed_fault(db, case, fault.item, record.get("healthyResponse"), record.get("mutantResponse"), probe, fault.signal)
        require(canonical(detail) == canonical(record["detail"]), "sqlite-fault-replay-detail", case)
        witness = {**verified, "receipt": digest(receipt), "signal": fault.signal, "status": "native-replayed"}
        evidence[case] = {**witness, "artifacts": {**links(folder, args.directory, fault.path, witness), dispatch["path"]: dispatch["sha256"]}}
    return evidence


def audit_initial(directory, records, candidate, manifest):
    by_id = {record["id"]: record for record in records}
    expected_ids = INITIAL.keys()
    require(by_id.keys() == expected_ids and len(records) == len(expected_ids), "sqlite-initial-fault-set")
    evidence = {}
    for key, item in sorted(by_id.items()):
        folder = directory / "faults" / key
        receipt = checked_file(folder, folder / "result.json")
        require(item["record"] == f"faults/{key}/result.json", "sqlite-initial-fault-receipt-path", key)
        record = json.loads(receipt.read_text())
        artifact, signal = INITIAL[key]
        relative = artifact if artifact in INITIAL_TOOLS else "sql-semantics-sqlite/src/" + artifact
        require(record["affectedPath"] == relative and item["signal"] == signal, "sqlite-initial-fault-plan", key)
        if relative in INITIAL_TOOLS:
            source, original = TOOL / relative, digest(TOOL / relative)
        else:
            require(CANDIDATE_SOURCE.fullmatch(relative) is not None, "sqlite-fault-source-path", relative)
            source, original = candidate / "packages" / relative, manifest["packages/" + relative]
        require(record["id"] == key and record["control"] == "passed"
                and record["baselineDigest"] == original
                and record["expectedSignal"] == record["actualSignal"] == item["signal"],
                "sqlite-initial-fault-receipt", key)
        verified = verify_artifacts(folder, relative, source, original, record["mutatedDigest"], patched=None, prefixes=True)
        witness = {**verified, "receipt": digest(receipt), "signal": item["signal"], "status": "overlay-audited"}
        evidence[key] = {**witness, "artifacts": links(folder, directory, relative, witness)}
    return evidence


def replay_relational(args, database, probe):
    static = [record for record in args.records if record["case"] == "restore"]
    require(len(static) == 1, "sqlite-relational-restore-receipt-set")
    runtime = [record for record in args.records if record["case"] != "restore"]
    evidence = replay_registered(SimpleNamespace(**{**vars(args), "records": runtime}), database, relational_fixtures(), probe)
    record = static[0]
    folder = args.directory / "relational-faults" / "restore"
    receipt = checked_file(folder, folder / "result.json")
    require(record == json.loads(receipt.read_text()) and record["expected"] == record["observed"] == "shape-mismatch"
            and record["patch"] == "relational-faults/restore/injected.diff" and record["control"] == "passed"
            and "Value primitive differs" in record["detail"]["message"],
            "sqlite-relational-restore-receipt")
    source = args.candidate / "packages" / RESTORATION
    verified = verify_artifacts(folder, RESTORATION, source, args.manifest["packages/" + RESTORATION], record["mutated"],
                                patched=exact_patch(source, RESTORATION_GUARD, "return;", "restore"), prefixes=False)
    require(verified["source"] == record["source"], "sqlite-relational-restore-source")
    witness = {**verified, "receipt": digest(receipt), "status": "overlay-audited", "signal": "shape-mismatch"}
    evidence["restore"] = {**witness, "artifacts": links(folder, args.directory, RESTORATION, witness)}
    return evidence
