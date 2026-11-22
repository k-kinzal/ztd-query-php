"""Replay every required native DELETE ledger against the independent effect model."""

import argparse
import json
from collections import Counter
from pathlib import Path
from types import SimpleNamespace

from evidence_transcript import canonical, events, load_replay_contract
from run import IDENTITY_CONTRACT, IMAGE, candidate_distribution, digest, require, verify_candidate, verify_distribution, write_json
from run_server import check_profile, same
from run_server_delete import CONTRACT, check_value, faults, observer_probes, required_forms, sample_forms, specifications, tools, version_tag
from server_delete_cases import ROWS, expected, finite_id, finite_ids, observation, ordered, requests


def replay(path, report, specification):
    engine = report["engine"]
    tag = version_tag(engine, report["release"])
    stream = iter(events(path))
    seen, forms, execution = Counter(), Counter(), Counter()

    def matched(key, kind):
        frame = next(stream, None)
        require(frame is not None and frame["id"] == key and frame["event"] == kind,
                "server-delete-replay-event", {"expected": [key, kind], "actual": frame})
        execution[kind] += 1
        return frame

    for key, request, item, rejection in requests(engine, tag, specification):
        require(canonical(matched(key, "attempted")["input"]) == canonical(request), "server-delete-replay-request", key)
        if rejection is not None:
            response = matched(key, "rejected")["response"]
            require(response["status"] == "rejected" and rejection in response["message"], "server-delete-replay-rejection", key)
            continue
        response = matched(key, "constructed")["response"]
        check_value(item, response)
        compared = matched(key, "compared")
        witness = observation(compared["observation"])
        same(expected(item), witness)
        require(("sql" in request) == ("original" in compared), "server-delete-replay-original-missing", key)
        if "sql" in request:
            same(expected(item), observation(compared["original"]))
        if key.startswith("finite/"):
            seen[finite_id(response)] += 1
        elif key.startswith("seed/"):
            sample_forms(forms, response, witness)
    require(next(stream, None) is None, "server-delete-replay-unconsumed-events", str(path))
    require(set(seen) == finite_ids(engine, specification) and set(seen.values()) == {1}, "server-delete-replay-incomplete")
    require(required_forms(engine) <= forms.keys(), "server-delete-replay-coverage-incomplete")
    require(canonical(sorted(seen)) == canonical(report["finiteIds"]) and forms == report["coverage"]
            and execution == report["execution"], "server-delete-replay-summary-mismatch", str(path))
    return dict(execution)


def check_faults(path, report, distribution):
    definitions = {fault.name: fault for fault in faults(report["engine"], version_tag(report["engine"], report["release"]))}
    records = {entry["case"]: entry for entry in report["faults"]}
    require(len(records) == len(report["faults"]) and records.keys() == definitions.keys(), "server-delete-fault-set", str(path))
    for name, rule in definitions.items():
        record = records[name]
        folder = path / "faults" / name
        require(record == json.loads((folder / "result.json").read_text())
                and record["control"] == "passed" and record["expected"] == record["observed"] == rule.signal
                and record["file"] == rule.path and record["input"] == rule.item
                and distribution[f"packages/{rule.path}"] == record["source"]
                and record["source"] != record["mutation"] == digest(folder / rule.path)
                and (folder / "injected.diff").is_file(), "server-delete-fault-evidence", name)


def combine(candidate, checkout, paths, output):
    load_replay_contract()
    specification, profiles = specifications()
    required = {(engine, release) for engine, profile in profiles.items() for release in profile["releases"]}
    _, manifest = candidate_distribution(checkout, candidate)
    registered_tools = tools()
    releases, counts = {}, Counter()
    for path in paths:
        raw = json.loads(path.read_text())
        role = raw["engine"], raw["release"]
        require(role in required and role not in releases, "duplicate-or-unregistered-server-delete-release", role)
        require(raw["status"] == "provisional-server-delete" and raw["candidate"] == candidate
                and raw["candidateIdentityContract"] == IDENTITY_CONTRACT and raw["contract"] == CONTRACT
                and raw["isolation"] == IMAGE and raw["toolDigests"] == registered_tools, "server-delete-evidence-target-mismatch", str(path))
        distribution = path.parent / "distribution.json"
        require(raw["distribution"] == digest(distribution), "server-delete-evidence-manifest-mismatch", str(path))
        verify_distribution(json.loads(distribution.read_text()), manifest)
        ledger = path.parent / "events.jsonl"
        require(raw["eventsDigest"] == digest(ledger), "server-delete-evidence-ledger-mismatch", str(path))
        check_profile(*role, SimpleNamespace(environment=raw["observerEnvironment"]))
        same(ordered(ROWS), ordered(raw["observerEnvironment"]["initial"]))
        same({probe: "outside-observer-scope" for probe in observer_probes(raw["engine"])}, raw["observerControls"])
        results = replay(ledger, raw, specification)
        check_faults(path.parent, raw, manifest)
        counts.update(results)
        releases[role] = {"index": str(path), "indexDigest": digest(path), "eventsDigest": digest(ledger),
                          "finite": len(raw["finiteIds"]), "faults": len(raw["faults"]), "execution": results}
    verify_candidate(checkout, candidate, manifest)
    missing = sorted(required - releases.keys())
    status = "incomplete" if missing else "provisional-native-delete-matrix"
    write_json(output, {"status": status, "candidate": candidate, "contract": CONTRACT,
                        "execution": dict(counts), "missing": missing,
                        "releaseReports": {" ".join(role): value for role, value in sorted(releases.items())}})
    require(not missing, "server-delete-release-evidence-missing", missing)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate", required=True)
    parser.add_argument("--candidate-root", type=Path, required=True)
    parser.add_argument("--out", type=Path, required=True)
    parser.add_argument("indexes", type=Path, nargs="+")
    args = parser.parse_args()
    candidate = args.candidate_root.resolve()
    paths = [path.resolve() for path in args.indexes]
    output = args.out.resolve()
    require(all(path != candidate and candidate not in path.parents for path in [output, *paths]), "evidence-directory-inside-candidate")
    combine(args.candidate, candidate, paths, output)


if __name__ == "__main__":
    main()
