"""Replay the fixed native suite from local receipts or downloaded workflow artifacts."""

import argparse
import subprocess
import sys
from pathlib import Path

from native_postgres_fixture import DOMAINS as FIXTURE_DOMAINS
from native_schedule import NATIVE, reports
from run import TOOL, digest, require, write_json


def collect(candidate, checkout, raw, layout, output):
    records = {}
    for domain in NATIVE:
        script = "combine_server_evidence.py" if domain == "projection" else f"combine_server_{domain}_evidence.py"
        indexes = list(reports(raw, layout, domain))
        unavailable = list(reports(raw, layout, "recursive_unavailable")) if domain == "recursive_series" else []
        receipt, log = output / f"{domain}.json", output / f"{domain}.log"
        command = [sys.executable, str(TOOL / script), "--candidate", candidate,
                   "--candidate-root", str(checkout), "--out", str(receipt)]
        if domain in FIXTURE_DOMAINS:
            command.append("--require-fixture-reset")
        for path in unavailable:
            command.extend(["--unavailable", str(path)])
        with log.open("w") as destination:
            result = subprocess.run([*command, *map(str, indexes)], stdout=destination, stderr=subprocess.STDOUT, check=False)
        records[domain] = {
            "exitCode": result.returncode,
            "records": [path.relative_to(raw).as_posix() for path in indexes],
            "unavailable": [path.relative_to(raw).as_posix() for path in unavailable],
            "log": {"file": log.name, "sha256": digest(log)},
            "receipt": {"file": receipt.name, "sha256": digest(receipt)} if receipt.is_file() else None,
        }
    return records


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate", required=True)
    parser.add_argument("--candidate-root", required=True, type=Path)
    parser.add_argument("--evidence-root", required=True, type=Path)
    parser.add_argument("--layout", choices=("release", "workflow"), required=True)
    args = parser.parse_args()
    checkout, evidence = args.candidate_root.resolve(), args.evidence_root.resolve()
    require(evidence != checkout and checkout not in evidence.parents, "evidence-directory-inside-candidate")
    raw, output = evidence / "native", evidence / "native-replay"
    require(raw.is_dir() and raw.resolve() == raw, "native-replay-record-directory", str(raw))
    output.mkdir(exist_ok=False)
    records = collect(args.candidate, checkout, raw, args.layout, output)
    failed = [name for name, record in records.items() if record["exitCode"] or record["receipt"] is None]
    write_json(output / "index.json", {"candidate": args.candidate, "layout": args.layout,
                                      "collector": digest(Path(__file__)), "records": records, "failed": failed})
    require(not failed, "native-replay-failures", failed)


if __name__ == "__main__":
    main()
