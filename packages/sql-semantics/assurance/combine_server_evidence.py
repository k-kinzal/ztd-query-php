"""Bind independent server evidence for every required native projection release."""

import argparse
import collections
import json
from os.path import relpath
from pathlib import Path

from evidence_transcript import REPLAY_CONTRACT, load_replay_contract
from replay_server_evidence import replay
from run import IDENTITY_CONTRACT, IMAGE, candidate_distribution, digest, require, verify_candidate, verify_distribution, write_json
from run_server import ADMISSION_CONTRACT, CONTRACT, grouped_admission, projection_publication
from server_cases import finite_ids

TOOL = Path(__file__).resolve().parent


def combine(candidate, candidate_root, input_paths, evidence_root):
    load_replay_contract()
    grouped_admission()
    contract = TOOL / "server-projection-v1.json"
    require(digest(contract) == CONTRACT, "server-contract-mismatch")
    publication = projection_publication()
    specification = json.loads(contract.read_text())
    profiles = specification["profiles"]
    settings = specification["exploration"]
    required = {(engine, release) for engine, profile in profiles.items() for release in profile["releases"]}
    comparable = None
    fixtures = collections.Counter()
    releases = {}
    _, actual = candidate_distribution(candidate_root, candidate)
    tools = {path.name: digest(path) for path in TOOL.iterdir() if path.suffix in {".php", ".py", ".json", ".md"}}
    for path in input_paths:
        require(path.is_file(), "server-evidence-file-missing", str(path))
        report = json.loads(path.read_text())
        require(report["status"] == "provisional-server-projections" and report["candidate"] == candidate
                and report["contract"] == CONTRACT and report["admissionContract"] == ADMISSION_CONTRACT
                and report["publication"] == publication
                and report.get("candidateIdentityContract") == IDENTITY_CONTRACT, "server-evidence-target-mismatch", str(path))
        require(report["isolation"] == IMAGE, "server-evidence-not-isolated", str(path))
        require(digest(path.parent / "distribution.json") == report["distribution"], "server-manifest-mismatch", str(path))
        verify_distribution(json.loads((path.parent / "distribution.json").read_text()), actual)
        require(report["toolDigests"] == tools, "server-verifier-mismatch", str(path))
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
            replayed = replay(engine, event_path, result, settings)
            require(replayed == counts["attempted"], "server-replay-count-mismatch", key)
            fixtures.update(counts)
            releases[key] = {
                "path": relpath(path.resolve(), evidence_root),
                "indexSha256": digest(path),
                "eventsSha256": digest(event_path),
                "finiteIds": len(ids),
                "execution": counts,
                "replayedRequests": replayed,
            }
    missing = sorted(required - set(releases))
    verify_candidate(candidate_root, candidate, actual)
    return {
        "status": "provisional-native-projection-matrix" if not missing else "incomplete",
        "candidate": candidate,
        "contract": CONTRACT, "admissionContract": ADMISSION_CONTRACT,
        "publication": publication,
        "replayContract": REPLAY_CONTRACT, "candidateIdentityContract": IDENTITY_CONTRACT,
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
    parser.add_argument("--candidate-root", required=True, type=Path)
    parser.add_argument("--out", required=True, type=Path)
    parser.add_argument("indexes", nargs="*", type=Path)
    args = parser.parse_args()
    candidate_root = args.candidate_root.resolve()
    args.indexes = [path.resolve() for path in args.indexes]
    args.out = args.out.resolve()
    require(all(path != candidate_root and candidate_root not in path.parents for path in [args.out, *args.indexes]),
            "evidence-directory-inside-candidate")
    result = combine(args.candidate, candidate_root, args.indexes, args.out.resolve().parent)
    write_json(args.out, result)
    require(not result["missing"], "server-release-evidence-missing", result["missing"])


if __name__ == "__main__":
    main()
