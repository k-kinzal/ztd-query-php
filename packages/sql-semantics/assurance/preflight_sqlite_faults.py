"""Verify all declared SQLite render mutations can be applied to the exact candidate."""

import argparse
from pathlib import Path

from replay_sqlite_evidence import DOMAINS, relational
from replay_sqlite_faults import CANDIDATE_SOURCE, contract
from run import candidate_distribution, digest, require, verify_candidate, write_json
from sqlite_relational_mutations import RESTORATION, RESTORATION_GUARD, fixtures


def preflight(candidate, manifest):
    contract()
    records, failures = {}, []

    def check(domain, name, file, before, after):
        path = candidate / "packages" / file
        require(CANDIDATE_SOURCE.fullmatch(file) is not None, "sqlite-fault-source-path", file)
        require(manifest.get("packages/" + file) == digest(path), "sqlite-fault-preflight-source-mismatch", file)
        content = path.read_text()
        hits = content.count(before)
        entry = {"case": name, "file": file, "anchorMatches": hits, "sourceDigest": digest(path)}
        records.setdefault(domain, []).append(entry)
        if hits != 1 or before == after:
            failures.append({"domain": domain, **entry})

    for domain, (module, _) in DOMAINS.items():
        if module is relational:
            plan = fixtures()
            check(domain, "restore", RESTORATION, RESTORATION_GUARD, "return;")
        else:
            plan = module.check_faults(None, None, runner=lambda _args, _db, cases, _probe: cases)
        require(bool(plan) and len({fault.name for fault in plan}) == len(plan), "sqlite-fault-preflight-case-set", domain)
        for fault in plan:
            check(domain, fault.name, fault.path, fault.before, fault.replacement)
    return records, failures


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate", required=True, type=Path)
    parser.add_argument("--out", required=True, type=Path)
    args = parser.parse_args()
    candidate, out = args.candidate.resolve(), args.out.resolve()
    require(candidate != out and candidate not in out.parents, "evidence-directory-inside-candidate")
    commit, manifest = candidate_distribution(candidate)
    out.mkdir(parents=True, exist_ok=False)
    records, failures = preflight(candidate, manifest)
    verify_candidate(candidate, commit, manifest)
    write_json(out / "index.json", {"candidate": commit, "status": "incomplete" if failures else "verified",
                                   "domains": records, "failures": failures})
    require(not failures, "sqlite-fault-preflight-unapplied", failures)


if __name__ == "__main__":
    main()
