"""Build an index of the required SQLite domain evidence for one distribution."""

import argparse
import json
import os
from pathlib import Path
from typing import NamedTuple

from evidence_transcript import REPLAY_CONTRACT, load_replay_contract
from replay_sqlite_evidence import replay
from replay_sqlite_faults import CONTRACT as FAULT_REPLAY_CONTRACT
from run import IDENTITY_CONTRACT, IMAGE, TOOL, candidate_distribution, digest, require, verify_candidate, verify_distribution, write_json
from scope import CONTRACTS


class Domain(NamedTuple):
    directory: str
    contract: str
    finite: int
    compared: int
    rejected: int
    faults: int


DOMAINS = (
    Domain("semantics-ir-evidence", "contract-v1.json", 308, 703, 18, 12),
    Domain("semantics-ir-sqlite-relational-evidence", "sqlite-relational-v4.json", 135, 335, 10, 5),
    Domain("semantics-ir-sqlite-merged-join-evidence", "sqlite-merged-join-v3.json", 48, 148, 7, 4),
    Domain("semantics-ir-sqlite-merged-prefix-join-evidence", "sqlite-merged-prefix-join-v1.json", 36, 133, 6, 4),
    Domain("semantics-ir-sqlite-merged-right-join-evidence", "sqlite-merged-right-join-v2.json", 24, 104, 4, 4),
    Domain("semantics-ir-sqlite-merged-full-join-evidence", "sqlite-merged-full-join-v1.json", 24, 108, 4, 4),
    Domain("semantics-ir-sqlite-result-evidence", "sqlite-result-v1.json", 108, 264, 3, 3),
    Domain("semantics-ir-sqlite-delete-evidence", "sqlite-delete-v1.json", 36, 161, 4, 3),
    Domain("semantics-ir-sqlite-values-evidence", "sqlite-values-v1.json", 292, 392, 4, 2),
    Domain("semantics-ir-sqlite-joined-local-source-evidence", "sqlite-joined-local-source-v1.json", 120, 260, 6, 4),
    Domain("semantics-ir-sqlite-local-source-evidence", "sqlite-local-source-v2.json", 180, 275, 4, 2),
    Domain("semantics-ir-sqlite-recursive-source-evidence", "sqlite-recursive-source-v1.json", 96, 248, 9, 3),
    Domain("semantics-ir-sqlite-insert-query-evidence", "sqlite-insert-query-v1.json", 48, 123, 5, 4),
    Domain("semantics-ir-sqlite-insert-input-evidence", "sqlite-insert-input-v2.json", 42, 95, 4, 3),
    Domain("semantics-ir-sqlite-insert-evidence", "sqlite-insert-v1.json", 54, 155, 5, 3),
    Domain("semantics-ir-sqlite-insert-returning-evidence", "sqlite-insert-returning-v1.json", 72, 167, 7, 4),
    Domain("semantics-ir-sqlite-update-evidence", "sqlite-update-v1.json", 54, 168, 7, 4),
    Domain("semantics-ir-sqlite-update-returning-evidence", "sqlite-update-returning-v1.json", 72, 149, 5, 4),
    Domain("semantics-ir-sqlite-drop-view-evidence", "sqlite-drop-view-v1.json", 16, 100, 6, 4),
    Domain("semantics-ir-sqlite-create-view-evidence", "sqlite-create-view-v2.json", 32, 152, 17, 4),
    Domain("semantics-ir-sqlite-create-view-grouped-evidence", "sqlite-create-view-grouped-v1.json", 24, 102, 5, 4),
    Domain("semantics-ir-sqlite-create-view-compound-evidence", "sqlite-create-view-compound-v1.json", 64, 154, 8, 4),
    Domain("semantics-ir-sqlite-create-view-local-evidence", "sqlite-create-view-local-v1.json", 20, 123, 6, 4),
    Domain("semantics-ir-sqlite-resolved-view-input-evidence", "sqlite-resolved-view-input-v2.json", 24, 114, 4, 4),
    Domain("semantics-ir-sqlite-drop-table-evidence", "sqlite-drop-table-v2.json", 12, 89, 5, 2),
    Domain("semantics-ir-sqlite-create-table-evidence", "sqlite-create-table-v3.json", 40, 117, 5, 4),
    Domain("semantics-ir-sqlite-create-as-evidence", "sqlite-create-as-v5.json", 576, 674, 5, 4),
    Domain("semantics-ir-sqlite-strict-tables-evidence", "sqlite-strict-tables-v2.json", 312, 390, 4, 4),
    Domain("semantics-ir-sqlite-rename-evidence", "sqlite-rename-v4.json", 48, 245, 68, 5),
    Domain("semantics-ir-sqlite-add-column-evidence", "sqlite-add-column-v3.json", 2496, 2580, 18, 6),
    Domain("semantics-ir-sqlite-appended-checks-evidence", "sqlite-appended-column-checks-v2.json", 512, 591, 11, 6),
    Domain("semantics-ir-sqlite-appended-implicit-checks-evidence", "sqlite-appended-implicit-checks-v1.json", 1536, 1618, 9, 6),
    Domain("semantics-ir-sqlite-create-index-evidence", "sqlite-create-index-v5.json", 256, 352, 21, 8),
    Domain("semantics-ir-sqlite-index-expression-spelling-evidence", "sqlite-index-expression-spelling-v1.json", 288, 378, 5, 6),
    Domain("semantics-ir-sqlite-drop-index-evidence", "sqlite-drop-index-v3.json", 264, 364, 10, 6),
    Domain("semantics-ir-sqlite-constraint-index-names-evidence", "sqlite-constraint-index-names-v2.json", 540, 874, 208, 6),
    Domain("semantics-ir-sqlite-reindex-evidence", "sqlite-reindex-v2.json", 112, 207, 6, 7),
    Domain("semantics-ir-sqlite-analyze-statistics-evidence", "sqlite-analyze-statistics-v1.json", 84, 184, 6, 7),
    Domain("semantics-ir-sqlite-flag-pragmas-evidence", "sqlite-flag-pragmas-v1.json", 880, 2264, 6, 7),
    Domain("semantics-ir-sqlite-table-metadata-evidence", "sqlite-table-metadata-v2.json", 360, 1073, 7, 7),
    Domain("semantics-ir-sqlite-inventory-evidence", "sqlite-connection-inventory-v1.json", 216, 756, 4, 5),
    Domain("semantics-ir-sqlite-index-metadata-evidence", "sqlite-index-metadata-v1.json", 1024, 2456, 6, 6),
    Domain("semantics-ir-sqlite-fk-audit-evidence", "sqlite-foreign-key-audit-v1.json", 480, 1323, 7, 6),
    Domain("semantics-ir-sqlite-integrity-audit-evidence", "sqlite-integrity-audit-v2.json", 648, 1656, 10, 7),
    Domain("semantics-ir-sqlite-header-pragmas-evidence", "sqlite-header-pragmas-v1.json", 1920, 4204, 5, 7),
    Domain("semantics-ir-sqlite-vacuum-evidence", "sqlite-vacuum-v1.json", 384, 1060, 7, 7),
    Domain("semantics-ir-sqlite-connection-commands-evidence", "sqlite-connection-commands-v1.json", 120, 600, 6, 7),
    Domain("semantics-ir-sqlite-drop-column-evidence", "sqlite-drop-column-v3.json", 1440, 1523, 20, 5),
    Domain("semantics-ir-sqlite-add-generated-column-evidence", "sqlite-add-generated-column-v1.json", 1152, 1231, 13, 6),
    Domain("semantics-ir-sqlite-generated-columns-evidence", "sqlite-generated-columns-v2.json", 96, 211, 8, 4),
    Domain("semantics-ir-sqlite-implicit-checks-evidence", "sqlite-implicit-checks-v1.json", 192, 297, 6, 4),
    Domain("semantics-ir-sqlite-folded-checks-evidence", "sqlite-folded-checks-v1.json", 72, 175, 4, 4),
    Domain("semantics-ir-sqlite-named-checks-evidence", "sqlite-named-checks-v4.json", 64, 168, 8, 5),
    Domain("semantics-ir-sqlite-unique-keys-evidence", "sqlite-unique-keys-v1.json", 216, 332, 7, 5),
    Domain("semantics-ir-sqlite-primary-keys-evidence", "sqlite-primary-keys-v2.json", 800, 903, 7, 5),
    Domain("semantics-ir-sqlite-auto-rowid-evidence", "sqlite-auto-rowid-v1.json", 288, 365, 5, 4),
    Domain("semantics-ir-sqlite-scalars-evidence", "sqlite-scalars-v2.json", 2280, 2483, 8, 4),
    Domain("semantics-ir-sqlite-numeric-evidence", "sqlite-numeric-operators-v1.json", 1728, 1832, 4, 4),
    Domain("semantics-ir-sqlite-mutation-conflicts-evidence", "sqlite-mutation-conflicts-v2.json", 96, 197, 3, 4),
    Domain("semantics-ir-sqlite-upsert-nothing-evidence", "sqlite-upsert-nothing-v1.json", 192, 292, 4, 4),
    Domain("semantics-ir-sqlite-pattern-evidence", "sqlite-pattern-v1.json", 810, 912, 6, 4),
    Domain("semantics-ir-sqlite-unary-evidence", "sqlite-unary-v2.json", 120, 213, 5, 4),
    Domain("semantics-ir-sqlite-concatenation-evidence", "sqlite-concatenation-v1.json", 200, 281, 4, 4),
    Domain("semantics-ir-sqlite-collation-evidence", "sqlite-collation-v1.json", 1200, 1284, 4, 4),
    Domain("semantics-ir-sqlite-folded-membership-evidence", "sqlite-folded-membership-v1.json", 52, 133, 4, 4),
    Domain("semantics-ir-sqlite-membership-evidence", "sqlite-membership-v1.json", 1418, 1498, 6, 4),
    Domain("semantics-ir-sqlite-between-evidence", "sqlite-between-v1.json", 1186, 1266, 5, 4),
    Domain("semantics-ir-sqlite-truth-evidence", "sqlite-truth-v2.json", 80, 163, 4, 4),
    Domain("semantics-ir-sqlite-compound-evidence", "sqlite-compound-v1.json", 1008, 1171, 9, 3),
    Domain("semantics-ir-sqlite-wildcard-evidence", "wildcard-projection-v2.json", 12, 67, 3, 3),
    Domain("semantics-ir-sqlite-grouped-evidence", "sqlite-grouped-v2.json", 80, 159, 9, 4),
    Domain("semantics-ir-sqlite-group-filter-evidence", "sqlite-group-filter-v1.json", 384, 476, 6, 4),
    Domain("semantics-ir-sqlite-transaction-evidence", "sqlite-transaction-v1.json", 80, 213, 6, 3),
    Domain("semantics-ir-sqlite-coalesce-evidence", "sqlite-coalesce-v1.json", 48, 180, 7, 4),
    Domain("semantics-ir-sqlite-case-evidence", "sqlite-case-v1.json", 400, 503, 5, 4),
    Domain("semantics-ir-sqlite-cast-evidence", "sqlite-cast-v1.json", 140, 219, 4, 3),
    Domain("semantics-ir-sqlite-defaults-evidence", "sqlite-defaults-v4.json", 280, 345, 5, 3),
    Domain("semantics-ir-sqlite-computed-defaults-evidence", "sqlite-computed-column-default-v3.json", 576, 657, 8, 5),
    Domain("semantics-ir-sqlite-not-null-conflict-evidence", "sqlite-not-null-conflict-v2.json", 864, 966, 3, 4),
    Domain("semantics-ir-sqlite-delete-returning-evidence", "sqlite-delete-returning-v1.json", 40, 117, 5, 4),
)


def collect(path, domain, commit, tools, allow_local, library, candidate, manifest):
    index_path = path / "index.json"
    distribution = digest(path / "distribution.json")
    index = json.loads(index_path.read_text())
    require(index["status"].startswith("provisional-"), "evidence-status-mismatch", str(index_path))
    require(index.get("candidate", index.get("commit")) == commit
            and index.get("candidateIdentityContract") == IDENTITY_CONTRACT, "evidence-candidate-mismatch", str(index_path))
    require(index["distribution"] == distribution, "evidence-distribution-mismatch", str(index_path))
    require(index["contract"] == CONTRACTS[domain.contract], "evidence-contract-mismatch", str(index_path))
    require(index.get("tools", index.get("toolDigests")) == tools, "evidence-tool-mismatch", str(index_path))
    isolation = index.get("isolation", index.get("candidateIsolation", {}).get("image", "none"))
    require(isolation == IMAGE or allow_local and isolation == "none", "evidence-isolation-mismatch", str(index_path))
    require(index["engine"]["version"] == "3.47.2", "evidence-engine-mismatch", str(index_path))
    checks = index["checks"]
    counts = checks.get("counts", checks.get("execution"))
    finite_ids = checks["finiteIds"]
    fault_records = index.get("faults", index.get("mutations"))
    require(len(finite_ids) == domain.finite and len(set(map(json.dumps, finite_ids))) == domain.finite
            and counts["compared"] == domain.compared and counts["rejected"] == domain.rejected
            and len(fault_records) == domain.faults, "incomplete-domain-evidence", str(index_path))
    if domain.contract == "contract-v1.json":
        require({fault["id"] for fault in fault_records} == {f"F{number:02d}" for number in range(1, 13)},
                "required-fault-set-mismatch", str(index_path))
        grammar = index["grammarScope"]
        require(grammar["record"] == "grammar-scope.json" and grammar["sha256"] == digest(path / "grammar-scope.json"),
                "grammar-scope-evidence-mismatch", str(index_path))
    else:
        require(len({fault["case"] for fault in fault_records}) == domain.faults and
                all(fault["control"] == "passed" and fault["expected"] == fault["observed"] for fault in fault_records),
                "domain-fault-signal-mismatch", str(index_path))
    replayed, fault_replay = replay(path, domain.contract, index, library, candidate=candidate, manifest=manifest)
    require(replayed == counts["attempted"] and len(fault_replay) == domain.faults, "evidence-replay-count-mismatch", domain.contract)
    return {
        "index": str(index_path), "indexDigest": digest(index_path), "eventsDigest": digest(path / "events.jsonl"),
        "contract": index["contract"], "distribution": distribution, "engine": index["engine"]["libraryDigest"],
        "isolation": isolation, "finite": len(finite_ids), "compared": counts["compared"],
        "rejected": counts["rejected"], "faults": len(fault_records),
        "replayedRequests": replayed, "faultReplay": fault_replay,
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--root", required=True, type=Path, help="Parent of the standard evidence directories")
    parser.add_argument("--candidate", required=True)
    parser.add_argument("--candidate-root", required=True, type=Path)
    parser.add_argument("--sqlite-library", required=True, type=Path)
    parser.add_argument("--out", required=True, type=Path)
    parser.add_argument("--allow-local", action="store_true", help="Include diagnostic evidence without container isolation")
    args = parser.parse_args()
    load_replay_contract()
    candidate = args.candidate_root.resolve()
    args.root = args.root.resolve()
    args.out = args.out.resolve()
    require(all(path != candidate and candidate not in path.parents for path in (args.root, args.out)),
            "evidence-directory-inside-candidate")
    _, actual = candidate_distribution(candidate, args.candidate)
    initial = args.root / DOMAINS[0].directory / "distribution.json"
    verify_distribution(json.loads(initial.read_text()), actual)
    tools = {file.name: digest(file) for file in TOOL.iterdir() if file.suffix in (".py", ".php", ".json")}
    domains = {domain.contract: collect(args.root / domain.directory, domain, args.candidate, tools, args.allow_local, args.sqlite_library, candidate, actual)
               for domain in DOMAINS}
    require(len({record["distribution"] for record in domains.values()}) == 1, "mixed-evidence-distribution")
    require(len({record["engine"] for record in domains.values()}) == 1, "mixed-evidence-engine")
    verify_candidate(candidate, args.candidate, actual)
    args.out.mkdir(parents=True, exist_ok=False)
    for row in domains.values():
        row["index"] = os.path.relpath(row["index"], args.out)
    write_json(args.out / "index.json", {
        "candidate": args.candidate, "status": "provisional", "domains": domains,
        "repositoryAcceptanceProtection": "unconfigured", "humanApproval": "pending",
        "classification": "independent database evidence in the checked domains",
        "faultReplayContract": FAULT_REPLAY_CONTRACT,
        "replayContract": REPLAY_CONTRACT, "candidateIdentityContract": IDENTITY_CONTRACT,
    })
    lines = [
        "# SQLite assurance evidence", "", f"Candidate `{args.candidate}`.", "",
        "| Domain | Isolation | Finite | Compared | Rejected at construction | Detected faults |",
        "| --- | --- | ---: | ---: | ---: | ---: |",
    ]
    for name, row in domains.items():
        isolation = "local diagnostic" if row["isolation"] == "none" else "read-only container"
        lines.append(f"| [{name}]({row['index']}) | {isolation} | {row['finite']} | {row['compared']} | {row['rejected']} | {row['faults']} |")
    lines.extend(("", "Human approval and repository-level acceptance protection are pending.", ""))
    (args.out / "summary.md").write_text("\n".join(lines))


if __name__ == "__main__":
    main()
