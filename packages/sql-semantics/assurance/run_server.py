"""Run the signed-integer projection acceptance checks on both disposable servers."""

import argparse
import collections
import json
from pathlib import Path
import subprocess
import time

from run import TOOL, IMAGE, Worker, Ledger, compare, digest, manifest, require, shape, verify_distribution, write_json
from server_cases import (
    case, direct_cases, expected, explore, finite_ids, fixtures, node_key, source, source_key,
    observation, VERSIONS, SCHEMAS, analyzed, walk,
)

CONTRACT = "4f46d75542c1cc47518a2a91c8faaf318800891281e3b06ef409d9f9236f3e97"
DIALECTS = ("mysql", "postgres")


class Observer(Worker):
    def __init__(self, dialect, deadline):
        self.deadline = deadline
        self.proc = subprocess.Popen(
            ["php", str(TOOL / "server_observer.php"), dialect],
            stdin=subprocess.PIPE, stdout=subprocess.PIPE, bufsize=0,
        )
        self.buffer = b""

    def __enter__(self):
        super().__enter__()
        require("database" in self.environment, "observer-handshake", self.environment)
        return self


def same(expected_value, actual_value):
    compare(json.dumps(expected_value, sort_keys=True), json.dumps(actual_value, sort_keys=True))


def check_server(dialect, release, db):
    settings = db.environment["database"]
    if dialect == "mysql":
        require(settings == [release, 0, "", "utf8mb4", "utf8mb4"], "mysql-profile-mismatch", settings)
    else:
        major, minor = map(int, release.split("."))
        require(settings == [str(major * 10000 + minor), "UTF8", "UTF8", "on", "pg_catalog, public"],
                "postgres-profile-mismatch", settings)
    same({"status": "fixture"}, db.request({"fixture": fixtures(dialect)}))


def check_direct(dialect, item, key, worker, db, ledger, distribution):
    ledger.record(key, "attempted", input=item)
    result = worker.request(item)
    require(result["status"] == "constructed", "server-positive-rejected", result)
    ledger.record(key, "constructed", response=result)
    require(result["profile"].startswith(item["release"] + "/"), "server-release-mismatch", result)
    require(result["columns"] == item["columns"] and result["source"] == item["source"], "server-shape-mismatch", result)
    actual = observation(db.request({"sql": result["sql"]}))
    same(expected(item), actual)
    ledger.record(key, "compared", observation=actual)
    for operand, _ in result["columns"]:
        distribution.update(walk(operand))
    return result


def check_analyzed(dialect, tag, worker, db, ledger):
    table = SCHEMAS[dialect] + ".ir_evidence_accounts"
    inputs = [
        f"SELECT id, ir_evidence_accounts.id, (ir_evidence_accounts.id - delta) * 3 AS magnitude FROM {table}",
        f"SELECT A.delta, (A.id) FROM {table} AS A",
        "SELECT ALL FALSE, TRUE, NULL, -2147483648, ((2 + 3) * (1 - 4))",
    ]
    inputs += ([f"SELECT id FROM ONLY {table}", f"SELECT id FROM {table}"]
               if dialect == "postgres" else ["SELECT 1 AS ' alias ', 2 AS '', 007, +1, -0"])
    for index, sql in enumerate(inputs):
        key = f"analysis/{index}"
        item = analyzed(dialect, sql, version=tag)
        ledger.record(key, "attempted", input=item)
        result = worker.request(item)
        require(result["status"] == "constructed", "server-analysis-rejected", result)
        ledger.record(key, "constructed", response=result)
        if index == 0:
            require(result["columns"][0][0] == result["columns"][1][0] == ["slot", 0],
                    "qualified-column-mismatch", result)
        before = observation(db.request({"sql": sql}))
        after = observation(db.request({"sql": result["sql"]}))
        require(before["status"] == "rows", "server-original-rejected", before)
        same(before, after)
        ledger.record(key, "compared", observation=after)


def negatives(dialect):
    table = SCHEMAS[dialect] + ".ir_evidence_accounts"
    invalid = [
        (case(dialect, None, []), "nonempty list"),
        (case(dialect, None, [["slot", 0]]), "belong to the SELECT source"),
        (case(dialect, source(dialect), [["slot", 2]]), "belong to the SELECT source"),
        (case(dialect, None, [["integer", "2147483648"]]), "32-bit range"),
        (case(dialect, None, [["integer", "-2147483649"]]), "32-bit range"),
        (case(dialect, None, [["integer", "1"]], ["n\0ame"]), "no NUL" if dialect == "mysql" else "without NUL"),
        (analyzed(dialect, f"SELECT id FROM {table}", schemas=False), "resolved, complete base table"),
        (analyzed(dialect, f"SELECT poison FROM {table}"), "signed 32-bit integer type"),
        (analyzed(dialect, "SELECT DISTINCT 1"), "bag projections"),
    ]
    if dialect == "mysql":
        invalid.append((case(dialect, None, [["integer", "1"]], [" before"]), "cannot start with SQL whitespace"))
    else:
        invalid.append((case(dialect, None, [["integer", "1"]], [""]), "nonempty"))
        invalid.append((analyzed(dialect, "SELECT 1 OPERATOR(public.+) 2"), "pg_catalog operator"))
    return invalid


def run_dialect(dialect, release, args, settings, path):
    tag = ("pg-" if dialect == "postgres" else "mysql-") + release
    deadline = time.monotonic() + settings["timeoutSeconds"]
    exported = collections.Counter()
    histogram = collections.Counter()
    with path.open("w") as stream, Observer(dialect, deadline) as db, Worker(args, deadline, script="server_worker.php") as worker:
        ledger = Ledger(stream)
        check_server(dialect, release, db)
        current_input = None
        try:
            for index, item in enumerate(direct_cases(dialect)):
                item["release"] = tag
                current_input = item
                result = check_direct(dialect, item, f"finite/{index}", worker, db, ledger, histogram)
                exported[(source_key(result["source"]), node_key(result["columns"][0][0]))] += 1
            require(set(exported) == finite_ids(dialect) and set(exported.values()) == {1}, "server-finite-incomplete",
                    {"missing": list(finite_ids(dialect) - set(exported)), "extra": list(set(exported) - finite_ids(dialect))})
            for seed in settings["seeds"]:
                for index, item in enumerate(explore(dialect, seed, settings["attemptsPerSeed"], settings["maxDepth"], settings["maxWidth"])):
                    item["release"] = tag
                    current_input = item
                    check_direct(dialect, item, f"seed/{seed}/{index}", worker, db, ledger, histogram)
                actual = sum(1 for key, events in ledger.events.items() if key.startswith(f"seed/{seed}/") and events[-1] == "compared")
                require(actual == settings["attemptsPerSeed"], "server-seed-budget-mismatch", {"seed": seed, "compared": actual})
            for index, item in enumerate([
                case(dialect, None, [["integer", "-2147483648"], ["integer", "2147483647"]]),
                case(dialect, None, [["operation", "*", ["integer", "-2147483648"], ["integer", "-2147483648"]]]),
                case(dialect, None, [["null"]] * 1024),
                case(dialect, None, [["integer", "1"]], ["a" * (256 if dialect == "mysql" else 63)]),
            ]):
                item["release"] = tag
                current_input = item
                check_direct(dialect, item, f"boundary/{index}", worker, db, ledger, histogram)
            current_input = None
            check_analyzed(dialect, tag, worker, db, ledger)
            for index, (current_input, signal) in enumerate(negatives(dialect)):
                key = f"negative/{index}"
                current_input["release"] = tag
                ledger.record(key, "attempted", input=current_input)
                response = worker.request(current_input)
                require(response["status"] == "rejected" and signal in response["message"], "server-negative-mismatch", response)
                ledger.record(key, "rejected", response=response)
            require(histogram.keys() == {"integer", "boolean", "null", "slot", "operation"}, "server-unobserved-operands", dict(histogram))
        except Exception as error:
            write_json(path.with_suffix(".counterexample.json"), {"original": current_input, "failure": str(error)})
            raise
        counts = ledger.verify()
        return {
            "finiteIds": sorted(exported), "distribution": dict(histogram), "execution": counts,
            "engine": db.environment, "release": release, "php": worker.environment,
        }


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate", required=True, type=Path)
    parser.add_argument("--tool-autoload", required=True, type=Path)
    parser.add_argument("--out", required=True, type=Path)
    parser.add_argument("--engine", choices=DIALECTS, help="Run one engine rather than both fixture releases.")
    parser.add_argument("--release", help="Select one required release of the selected engine.")
    parser.add_argument("--local", action="store_true")
    args = parser.parse_args()
    args.candidate = args.candidate.resolve()
    args.tool_autoload = args.tool_autoload.resolve()
    args.out = args.out.resolve()
    require(args.candidate != args.out and args.candidate not in args.out.parents, "evidence-directory-inside-candidate")
    args.out.mkdir(parents=True, exist_ok=False)
    contract = TOOL / "server-projection-v1.json"
    require(digest(contract) == CONTRACT, "server-contract-mismatch")
    specification = json.loads(contract.read_text())
    settings = specification["exploration"]
    require(not args.release or args.engine, "release-needs-engine")
    distribution = manifest(args.candidate)
    write_json(args.out / "distribution.json", distribution)
    results = {}
    for dialect in (args.engine,) if args.engine else DIALECTS:
        release = args.release or VERSIONS[dialect].split("-", 1)[1]
        require(release in specification["profiles"][dialect]["releases"], "unregistered-engine-release", release)
        code_shape = shape(args, dialect=dialect)
        results[dialect] = run_dialect(dialect, release, args, settings, args.out / f"{dialect}.jsonl")
        results[dialect]["shape"] = code_shape
    verify_distribution(distribution, manifest(args.candidate))
    commit = subprocess.check_output(["git", "-C", str(args.candidate), "rev-parse", "HEAD"], text=True).strip()
    index = {
        "status": "provisional-server-projections", "candidate": commit,
        "distribution": digest(args.out / "distribution.json"), "contract": CONTRACT,
        "toolDigests": {path.name: digest(path) for path in TOOL.iterdir() if path.suffix in {".php", ".py", ".json", ".md"}},
        "isolation": "none" if args.local else IMAGE, "results": results,
        "open": ["other release and mode server comparisons", "other statement roles", "independently approved repository acceptance"],
    }
    write_json(args.out / "index.json", index)
    summary = ["# Server projection evidence", "", f"Candidate `{commit}`.", "", "This run is provisional.", ""]
    for dialect, evidence in results.items():
        summary.append(f"- {dialect} {evidence['release']} observed all {len(evidence['finiteIds'])} finite IDs. "
                       f"The ledger recorded {evidence['execution']['compared']} comparisons and "
                       f"{evidence['execution']['rejected']} specified rejections. [{dialect} events]({dialect}.jsonl)")
    (args.out / "summary.md").write_text("\n".join(summary + ["", "[Machine-readable evidence](index.json)", ""]))
    print(args.out)


if __name__ == "__main__":
    main()
