"""Replay the native recursive-series observations and unavailable-profile controls."""

import argparse
import json
from os.path import relpath
from pathlib import Path

from check_recursive_availability import CONTROL, QUERY, classifications
from combine_server_join_evidence import combine
from run import IDENTITY_CONTRACT, candidate_distribution, digest, require, verify_distribution, write_json
import run_server_recursive_series as domain
from run_server_delete import tools
from server_recursive_series_cases import case


def unavailable(paths, checkout, candidate, specification, output):
    expected = set(specification["unavailableSyntax"]["mysql"])
    _, manifest = candidate_distribution(checkout, candidate)
    reports = {}
    for index in paths:
        report = json.loads(index.read_text())
        release = report["release"]
        require(release in expected and release not in reports, "recursive-availability-unregistered-release", release)
        require(report["status"] == "provisional-recursive-syntax-unavailable" and report["candidate"] == candidate
                and report["candidateIdentityContract"] == IDENTITY_CONTRACT and report["contract"] == domain.CONTRACT
                and report["toolDigests"] == tools(), "recursive-availability-evidence-identity", str(index))
        distribution = index.parent / "distribution.json"
        require(report["distribution"] == digest(distribution), "recursive-availability-distribution-digest", str(index))
        verify_distribution(json.loads(distribution.read_text()), manifest)
        observations = index.parent / "observations.json"
        require(report["observationsDigest"] == digest(observations), "recursive-availability-observations-digest", str(index))
        measured = json.loads(observations.read_text())
        require(json.loads((index.parent / "observer.stdout").read_text()) == measured["native"],
                "recursive-availability-native-record-mismatch", str(index))
        tag = "mysql-" + release
        parsed = {"dialect": "mysql", "release": tag, "declarations": []}
        require(measured["requests"] == [case("mysql", tag, 1, 1, 3), {**parsed, "sql": QUERY}, {**parsed, "sql": CONTROL}],
                "recursive-availability-request-mismatch", str(index))
        classifications(release, measured["native"], *measured["responses"])
        reports[release] = {"index": relpath(index.resolve(), output.parent.resolve()), "indexDigest": digest(index),
                            "observationsDigest": digest(observations)}
    require(reports.keys() == expected, "recursive-availability-evidence-missing", sorted(expected - reports.keys()))
    return reports


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate", required=True)
    parser.add_argument("--candidate-root", type=Path, required=True)
    parser.add_argument("--out", type=Path, required=True)
    parser.add_argument("--unavailable", type=Path, action="append", required=True)
    parser.add_argument("indexes", type=Path, nargs="+")
    args = parser.parse_args()
    args.candidate_root = args.candidate_root.resolve()
    specification, _ = domain.specifications()
    missing_syntax = unavailable(args.unavailable, args.candidate_root, args.candidate, specification, args.out)
    combine(args.candidate, args.candidate_root, args.indexes, args.out, domain)
    results = json.loads(args.out.read_text())
    write_json(args.out, {**results, "unavailableProfiles": missing_syntax})


if __name__ == "__main__":
    main()
