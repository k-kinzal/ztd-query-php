"""Build an index of the required SQLite domain evidence for one distribution."""

import argparse
import json
import os
from pathlib import Path
from typing import NamedTuple

from run import IMAGE, TOOL, digest, require, write_json
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
    Domain("semantics-ir-sqlite-relational-evidence", "sqlite-relational-v1.json", 135, 335, 10, 5),
    Domain("semantics-ir-sqlite-result-evidence", "sqlite-result-v1.json", 108, 264, 3, 3),
    Domain("semantics-ir-sqlite-delete-evidence", "sqlite-delete-v1.json", 36, 161, 4, 3),
    Domain("semantics-ir-sqlite-values-evidence", "sqlite-values-v1.json", 292, 392, 4, 2),
    Domain("semantics-ir-sqlite-local-source-evidence", "sqlite-local-source-v1.json", 180, 274, 4, 2),
    Domain("semantics-ir-sqlite-insert-evidence", "sqlite-insert-v1.json", 54, 155, 5, 3),
    Domain("semantics-ir-sqlite-update-evidence", "sqlite-update-v1.json", 54, 168, 7, 4),
    Domain("semantics-ir-sqlite-drop-table-evidence", "sqlite-drop-table-v1.json", 12, 89, 5, 2),
    Domain("semantics-ir-sqlite-create-table-evidence", "sqlite-create-table-v1.json", 40, 117, 5, 4),
)


def collect(path, domain, commit, tools, allow_local):
    index_path = path / "index.json"
    distribution = digest(path / "distribution.json")
    index = json.loads(index_path.read_text())
    require(index["status"].startswith("provisional-"), "evidence-status-mismatch", str(index_path))
    require(index.get("candidate", index.get("commit")) == commit, "evidence-candidate-mismatch", str(index_path))
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
    return {
        "index": str(index_path), "indexDigest": digest(index_path), "eventsDigest": digest(path / "events.jsonl"),
        "contract": index["contract"], "distribution": distribution, "engine": index["engine"]["libraryDigest"],
        "isolation": isolation, "finite": len(finite_ids), "compared": counts["compared"],
        "rejected": counts["rejected"], "faults": len(fault_records),
    }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--root", required=True, type=Path, help="Parent of the standard evidence directories")
    parser.add_argument("--candidate", required=True)
    parser.add_argument("--out", required=True, type=Path)
    parser.add_argument("--allow-local", action="store_true", help="Include diagnostic evidence without container isolation")
    args = parser.parse_args()
    tools = {file.name: digest(file) for file in TOOL.iterdir() if file.suffix in (".py", ".php", ".json")}
    domains = {domain.contract: collect(args.root / domain.directory, domain, args.candidate, tools, args.allow_local)
               for domain in DOMAINS}
    require(len({record["distribution"] for record in domains.values()}) == 1, "mixed-evidence-distribution")
    require(len({record["engine"] for record in domains.values()}) == 1, "mixed-evidence-engine")
    args.out.mkdir(parents=True, exist_ok=False)
    for row in domains.values():
        row["index"] = os.path.relpath(row["index"], args.out)
    write_json(args.out / "index.json", {
        "candidate": args.candidate, "status": "provisional", "domains": domains,
        "repositoryAcceptanceProtection": "unconfigured", "humanApproval": "pending",
        "classification": "independent database evidence in the checked domains",
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
