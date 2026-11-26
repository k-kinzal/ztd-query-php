"""Inject faults into the inputs of the actual SQLite attempt reconciler."""

import argparse
from copy import deepcopy
from pathlib import Path
from shutil import copy2, copytree
import tempfile
import json

from reconcile_sqlite_attempts import LAUNCH, choose_domains, load_attempt, stage_domains
from run import Failure, candidate_distribution, digest, require, write_json


def rejected(label, signal, operation, details):
    try:
        operation()
    except Failure as result:
        require(result.signal == signal, "sqlite-reconcile-control-signal", [label, result.signal, result.detail])
        return {"expected": signal, "observed": result.signal, "input": details, "failure": result.detail}
    raise Failure("sqlite-reconcile-control-passed", label)


def controls(candidate, distribution, library, original, retry, out):
    normal = [load_attempt(path, candidate, distribution, digest(library)) for path in (original, retry)]
    all_domains = choose_domains(normal)
    recovered = [name for name, reference in all_domains.items() if reference[0] == 1]
    require(bool(recovered), "sqlite-reconcile-control-no-retry-witness")
    reports = {}

    with tempfile.TemporaryDirectory(dir=out) as scratch:
        control = Path(scratch)
        alternate = control / "sqlite.so"
        copy2(library, alternate)
        with alternate.open("ab") as stream:
            stream.write(b"\n")
        alternate_hash = digest(alternate)
        reports["different-library"] = rejected("different-library", "sqlite-reconcile-library-mismatch",
            lambda: load_attempt(retry, candidate, distribution, alternate_hash),
            {"normalDigest": digest(library), "mutatedDigest": alternate_hash, "appendedHex": "0a"})

        changed = control / "omitted-execution"
        copytree(original / LAUNCH.parent, changed / LAUNCH.parent)
        original_receipt = deepcopy(normal[0]["report"])
        missing = original_receipt["executions"].pop(1)
        write_json(changed / LAUNCH, original_receipt)
        reports["omitted-execution"] = rejected("omitted-execution", "bundle-sqlite-launch-schedule",
            lambda: load_attempt(changed, candidate, distribution, digest(library)),
            {"normalDigest": normal[0]["origin"]["sha256"], "mutatedDigest": digest(changed / LAUNCH), "removed": missing})

        reports["incomplete-domain-set"] = rejected("incomplete-domain-set", "sqlite-reconcile-missing-domains",
            lambda: choose_domains(normal[:1]), {"original": normal[0]["origin"], "omittedRetry": normal[1]["origin"], "stillMissing": recovered})

        selected = recovered[0]
        ordinal, directory, identity = all_domains[selected]
        base = control / "mutated-domain"
        copytree(normal[ordinal]["root"] / directory, base / directory)
        artifact = base / directory / "index.json"
        with artifact.open("ab") as stream:
            stream.write(b"\n")
        altered = [{**attempt, "root": base if number == ordinal else attempt["root"]} for number, attempt in enumerate(normal)]
        reports["changed-selected-artifact"] = rejected("changed-selected-artifact", "sqlite-reconcile-selected-artifact-changed",
            lambda: stage_domains(altered, {selected: all_domains[selected]}, control / "comparison"),
            {"contract": selected, "normalDigest": identity, "mutatedDigest": digest(artifact), "appendedHex": "0a"})

        first_failed = next(entry for entry in normal[0]["report"]["executions"] if entry["job"] == recovered[0])
        changed = control / "changed-failure"
        copytree(original / LAUNCH.parent, changed / LAUNCH.parent)
        for entry in normal[0]["report"]["executions"]:
            name = entry["directory"]
            if entry["job"] in recovered:
                copytree(original / name, changed / name)
        failure_path = changed / first_failed["directory"] / "run-failure.json"
        prior = digest(failure_path)
        failure = json.loads(failure_path.read_text())
        failure.update(signal="meaning-mismatch", failure="A native computed result differs.")
        write_json(failure_path, failure)
        reports["semantic-failure"] = rejected("semantic-failure", "sqlite-reconcile-unresolved-failure",
            lambda: choose_domains([{**normal[0], "root": changed}, normal[1]]),
            {"domain": first_failed["job"], "normalDigest": prior, "mutatedDigest": digest(failure_path),
             "diff": {"signal": "meaning-mismatch", "failure": "A native computed result differs."}})

        reports["lost-original-attempt"] = rejected("lost-original-attempt", "sqlite-reconcile-missing-original-schedule",
            lambda: choose_domains(normal[1:]), {"original": normal[0]["origin"], "onlyRetry": normal[1]["origin"]})

    return {"status": "verified", "candidate": candidate, "healthy": {"selected": len(all_domains), "recovered": recovered},
            "checks": reports}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate-root", required=True, type=Path)
    parser.add_argument("--candidate", required=True)
    parser.add_argument("--sqlite-library", required=True, type=Path)
    parser.add_argument("--original", required=True, type=Path)
    parser.add_argument("--retry", required=True, type=Path)
    parser.add_argument("--out", required=True, type=Path)
    args = parser.parse_args()
    candidate = args.candidate_root.resolve()
    original, retry, library, out = (value.resolve() for value in (args.original, args.retry, args.sqlite_library, args.out))
    for source in (original, retry, library, out):
        require(candidate != source and candidate not in source.parents, "sqlite-reconcile-control-inside-candidate", str(source))
    require(all(out != source and source not in out.parents for source in (original, retry)), "sqlite-reconcile-control-output")
    commit, manifest = candidate_distribution(candidate, args.candidate)
    distribution_file = original / LAUNCH.parent / "distribution.json"
    require(manifest == json.loads(distribution_file.read_text()), "sqlite-reconcile-control-distribution")
    out.mkdir(parents=True, exist_ok=False)
    write_json(out / "index.json", controls(commit, digest(distribution_file), library, original, retry, out))
    print(out)


if __name__ == "__main__":
    main()
