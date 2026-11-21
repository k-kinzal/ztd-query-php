"""Bind independent server evidence for every required native projection release."""

import argparse
import collections
import json
from os.path import relpath
from pathlib import Path

from run import digest, require, write_json
from run_server import CONTRACT, same
from server_cases import expected, finite_ids

TOOL = Path(__file__).resolve().parent


def combine(candidate, input_paths, evidence_root):
    contract = TOOL / "server-projection-v1.json"
    require(digest(contract) == CONTRACT, "server-contract-mismatch")
    specification = json.loads(contract.read_text())
    profiles = specification["profiles"]
    settings = specification["exploration"]
    required = {(engine, release) for engine, profile in profiles.items() for release in profile["releases"]}
    comparable = None
    fixtures = collections.Counter()
    releases = {}
    for path in input_paths:
        require(path.is_file(), "server-evidence-file-missing", str(path))
        report = json.loads(path.read_text())
        require(report["status"] == "provisional-server-projections" and report["candidate"] == candidate
                and report["contract"] == CONTRACT, "server-evidence-target-mismatch", str(path))
        require(report["isolation"] != "none", "server-evidence-not-isolated", str(path))
        require(digest(path.parent / "distribution.json") == report["distribution"], "server-manifest-mismatch", str(path))
        identity = [report["distribution"], report["toolDigests"]]
        require(comparable is None or comparable == identity, "server-evidence-build-mismatch", str(path))
        comparable = identity
        for engine, result in report["results"].items():
            key = (engine, result["release"])
            require(key in required and key not in releases, "invalid-server-release-evidence", key)
            ids = list(map(tuple, result["finiteIds"]))
            require(len(ids) == len(finite_ids(engine)) and set(ids) == finite_ids(engine), "server-finite-incomplete", key)
            counts = result["execution"]
            require(counts["constructed"] == counts["compared"] > 0
                    and counts["attempted"] == counts["compared"] + counts["rejected"], "server-unbalanced-execution", key)
            event_path = path.parent / f"{engine}.jsonl"
            events = collections.defaultdict(list)
            inputs = {}
            replayed = 0
            with event_path.open() as stream:
                for line in stream:
                    event = json.loads(line)
                    event_id = event["id"]
                    events[event_id].append(event["event"])
                    if event["event"] == "attempted":
                        inputs[event_id] = event["input"]
                    if event["event"] == "compared" and "sql" not in inputs[event_id]:
                        same(expected(inputs[event_id]), event["observation"])
                        replayed += 1
            require(not any(event not in (["attempted", "constructed", "compared"], ["attempted", "rejected"]) for event in events.values()),
                    "server-event-ledger-incomplete", key)
            observed_counts = collections.Counter(event for values in events.values() for event in values)
            require(observed_counts == counts, "server-event-ledger-mismatch", key)
            for seed in settings["seeds"]:
                expected_keys = {f"seed/{seed}/{case}" for case in range(settings["attemptsPerSeed"])}
                actual_keys = {event for event, sequence in events.items() if event.startswith(f"seed/{seed}/") and sequence[-1] == "compared"}
                require(actual_keys == expected_keys, "server-seed-ledger-incomplete", [*key, seed])
            fixtures.update(counts)
            releases[key] = {
                "path": relpath(path.resolve(), evidence_root),
                "indexSha256": digest(path),
                "eventsSha256": digest(event_path),
                "finiteIds": len(ids),
                "execution": counts,
                "directComparisonsReplayed": replayed,
            }
    missing = sorted(required - set(releases))
    return {
        "status": "provisional-native-projection-matrix" if not missing else "incomplete",
        "candidate": candidate,
        "contract": CONTRACT,
        "distribution": comparable[0] if comparable else None,
        "replayTools": {name: digest(TOOL / name) for name in ["combine_server_evidence.py", "run_server.py", "run.py", "server_cases.py"]},
        "releaseReports": {" ".join(key): report for key, report in sorted(releases.items())},
        "execution": dict(fixtures),
        "missing": missing,
        "scope": "Signed-integer projections under the native profiles. The full contract remains open.",
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate", required=True)
    parser.add_argument("--out", required=True, type=Path)
    parser.add_argument("indexes", nargs="*", type=Path)
    args = parser.parse_args()
    result = combine(args.candidate, args.indexes, args.out.resolve().parent)
    write_json(args.out, result)
    require(not result["missing"], "server-release-evidence-missing", result["missing"])


if __name__ == "__main__":
    main()
