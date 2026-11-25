"""Run the signed-integer projection acceptance checks on both disposable servers."""

import argparse
import collections
import itertools
import json
from pathlib import Path
import subprocess
import time

from mysql_physical_text_admission import negative as mysql_text_negative
from run import IDENTITY_CONTRACT, TOOL, IMAGE, Worker, Ledger, Failure, compare, candidate_distribution, digest, require, shape, verify_candidate, write_json
from postgres_physical_text_admission import negative as postgres_text_negative
from scope import PUBLICATION, publication_definition
from server_cases import (
    case, direct_cases, expected, explore, finite_ids, fixtures, node_key, POISON_VALUES, source, source_key,
    observation, VERSIONS, SCHEMAS, analyzed, walk,
)

CONTRACT = "4f46d75542c1cc47518a2a91c8faaf318800891281e3b06ef409d9f9236f3e97"
ADMISSION_CONTRACT = "cb6cb546e11007dcd00983f2f2ed0acf1d193cb7b17676b035b6384e4e2d46dd"
PUBLICATION_AMENDMENT = "044933c3fbc6ee9dddf578ccfea32e06e9e45cf0c61c20bbba7b9bae03f25656"
DIALECTS = ("mysql", "postgres")
TRANSPORT = "64a958eac4238161c3c1fb4242af55d7c4042ed1123d984e671e1b5338c9da1e"


def projection_publication():
    contract = TOOL / "server-projection-publication-v1.json"
    require(digest(contract) == PUBLICATION_AMENDMENT, "server-publication-amendment-mismatch")
    amended = json.loads(contract.read_text())
    baseline = TOOL / "publication-v25.json"
    require(digest(baseline) == amended["publicationContract"], "server-publication-baseline-mismatch")
    original = json.loads(baseline.read_text())
    publication = publication_definition()
    require(publication["single"]["method"] == original["single"]["method"] == "Semantics::analyze"
            and all(publication[key] == original[key] for key in ("analyzerInterface", "interfaceResult", "surface", "forbidden")),
            "server-publication-route-mismatch")
    return {"amendment": PUBLICATION_AMENDMENT, "contract": PUBLICATION}


class Observer(Worker):
    def __init__(self, dialect, deadline, script="server_observer.php"):
        self.deadline = deadline
        self.startup_timeout = None
        self.extension = Path(script).suffix
        require(self.extension in (".php", ".py"), "observer-program-type", script)
        executable = {".php": "php", ".py": "python3"}[self.extension]
        self.proc = subprocess.Popen(
            [executable, str(TOOL / script), dialect],
            stdin=subprocess.PIPE, stdout=subprocess.PIPE, bufsize=0,
        )
        self.buffer = b""

    def check_environment(self):
        if self.extension == ".php":
            super().check_environment()
        else:
            require(isinstance(self.environment.get("python"), str) and type(self.environment.get("libpq")) is int
                    and self.environment["libpq"] > 0, "observer-python-handshake")
        require("database" in self.environment, "observer-handshake")


def same(expected_value, actual_value):
    compare(json.dumps(expected_value, sort_keys=True), json.dumps(actual_value, sort_keys=True))


def rejects(response, boundary):
    if response["status"] != "rejected":
        return False
    if isinstance(boundary, str):
        return boundary in response["message"]
    return response["exception"] == boundary["exception"] and boundary["message"] in response["message"]


def check_profile(dialect, release, db):
    settings = db.environment["database"]
    if dialect == "mysql":
        require(settings == [release, 0, "", "utf8mb4", "utf8mb4"], "mysql-profile-mismatch", settings)
    else:
        major, minor = map(int, release.split("."))
        require(settings == [str(major * 10000 + minor), "UTF8", "UTF8", "on", "pg_catalog, public"],
                "postgres-profile-mismatch", settings)


def check_server(dialect, release, db):
    check_profile(dialect, release, db)
    same({"status": "fixture"}, db.request({"fixture": fixtures(dialect)}))


def responses(request, inputs, signal):
    observed = request(inputs)
    require(type(observed) is list and len(observed) == len(inputs) and all(type(value) is dict for value in observed),
            signal, {"expected": len(inputs), "observed": len(observed) if type(observed) is list else type(observed).__name__})
    return observed


def check_transport():
    contract = TOOL / "server-projection-transport-v1.json"
    require(digest(contract) == TRANSPORT, "server-transport-contract-mismatch")
    specification = json.loads(contract.read_text())
    inputs = [{"sql": "SELECT 1"}, {"sql": "SELECT 2"}]
    normal = [{"fields": [["first", "LONG"]]}, {"fields": [["second", "LONG"]]}]
    same(normal, responses(lambda items: normal if items == inputs else [], inputs, "server-worker-batch-mismatch"))
    for incorrect, signal in (
        (normal[:1], "server-worker-batch-mismatch"),
        ([*normal, normal[0]], "server-observer-batch-mismatch"),
    ):
        try:
            responses(lambda _, result=incorrect: result, inputs, signal)
        except Failure as failure:
            require(failure.signal == signal, "server-transport-control-failed", str(failure))
        else:
            raise Failure("server-batch-control-accepted", signal)
    return {"contract": TRANSPORT, "maximumBatch": specification["maximumBatch"], "controls": specification["controls"]}


def check_direct(records, worker, db, ledger, distribution):
    for key, item in records:
        ledger.record(key, "attempted", input=item)
    replies = responses(worker.request, [item for _, item in records], "server-worker-batch-mismatch")
    for (key, item), result in zip(records, replies):
        require(result["status"] == "constructed", "server-positive-rejected", {"case": key, "result": result})
        ledger.record(key, "constructed", response=result)
        require(result["profile"].startswith(item["release"] + "/"), "server-release-mismatch", result)
        require(result["columns"] == item["columns"] and result["source"] == item["source"], "server-shape-mismatch", result)
    native = responses(db.request, [{"sql": result["sql"]} for result in replies], "server-observer-batch-mismatch")
    for (key, item), result, row in zip(records, replies, native):
        actual = observation(row)
        same(expected(item), actual)
        ledger.record(key, "compared", observation=actual)
        for operand, _ in result["columns"]:
            distribution.update(walk(operand))
    return replies


def check_analyzed(dialect, tag, worker, db, ledger):
    table = SCHEMAS[dialect] + ".ir_evidence_accounts"
    inputs = [
        f"SELECT id, ir_evidence_accounts.id, (ir_evidence_accounts.id - delta) * 3 AS magnitude FROM {table}",
        f"SELECT A.delta, (A.id) FROM {table} AS A",
        "SELECT ALL FALSE, TRUE, NULL, -2147483648, ((2 + 3) * (1 - 4))",
    ]
    inputs += ([f"SELECT id FROM ONLY {table}", f"SELECT id FROM {table}"]
               if dialect == "postgres" else ["SELECT 1 AS ' alias ', 2 AS '', 007, +1, -0"])
    text_projection = f"SELECT poison, a.poison FROM {table} AS a"
    if dialect == "postgres":
        inputs.append(text_projection)
    for index, sql in enumerate(inputs):
        key = f"analysis/{index}"
        item = analyzed(dialect, sql, version=tag)
        ledger.record(key, "attempted", input=item)
        result = worker.request(item)
        require(result["status"] == "constructed", "server-analysis-rejected", result)
        require(result["profile"].startswith(tag + "/"), "server-analysis-release-mismatch", result)
        ledger.record(key, "constructed", response=result)
        if index == 0:
            require(result["columns"][0][0] == result["columns"][1][0] == ["slot", 0],
                    "qualified-column-mismatch", result)
        before = observation(db.request({"sql": sql}))
        after = observation(db.request({"sql": result["sql"]}))
        require(before["status"] == "rows", "server-original-rejected", before)
        if sql == text_projection:
            reference = [["text-slot", 0, 0], "poison"]
            require(result["columns"] == [reference, reference]
                    and result["source"] == {**source(dialect), "columns": ["poison"], "columnKinds": ["text"]},
                    "server-text-column-resolution", result)
            same(observation({"status": "rows", "fields": [["poison", "text"], ["poison", "text"]],
                              "rows": [[value, value] for value in POISON_VALUES]}), before)
        same(before, after)
        ledger.record(key, "compared", original=before, observation=after)


def grouped_admission():
    contract = TOOL / "server-projection-admission-v1.json"
    require(digest(contract) == ADMISSION_CONTRACT, "server-admission-contract-mismatch")
    return json.loads(contract.read_text())


def negatives(dialect):
    table = SCHEMAS[dialect] + ".ir_evidence_accounts"
    rejection = grouped_admission()["reject"]
    namespace = "MySql" if dialect == "mysql" else "PostgreSql"
    grouped = analyzed(dialect, rejection["sql"].format(table=table))
    exception = f"SqlSemantics\\Platform\\{namespace}\\Model\\Diagnostic\\{rejection['exception']}"
    field = analyzed(dialect, f"SELECT poison FROM {table}")
    kind, boundary = mysql_text_negative() if dialect == "mysql" else postgres_text_negative("projection")
    field["declarations"][0] = f"CREATE TABLE {table} (id INT, delta INT, poison {kind})"
    missing = analyzed(dialect, f"SELECT id FROM {table}", schemas=False)
    invalid = [(item, message, None) for item, message in [
        (case(dialect, None, []), "nonempty list"),
        (case(dialect, None, [["slot", 0]]), "belong to the SELECT source"),
        (case(dialect, source(dialect), [["slot", 2]]), "belong to the SELECT source"),
        (case(dialect, None, [["integer", "2147483648"]]), "32-bit range"),
        (case(dialect, None, [["integer", "-2147483649"]]), "32-bit range"),
        (case(dialect, None, [["integer", "1"]], ["n\0ame"]), "no NUL" if dialect == "mysql" else "without NUL"),
    ]]
    diagnostics = f"SqlSemantics\\Platform\\{namespace}\\Model\\Diagnostic\\"
    invalid.append(({**missing, "complete": False}, "resolved, complete local relation declaration",
                    diagnostics + "InvalidConstruction"))
    invalid.extend([(field, boundary["message"], boundary["exception"]), (grouped, rejection["message"], exception)])
    if dialect == "mysql":
        invalid.append((case(dialect, None, [["integer", "1"]], [" before"]),
                        "A MySQL result name must begin with a native graphic character or be empty.",
                        diagnostics + "InvalidConstruction"))
    else:
        invalid.append((case(dialect, None, [["integer", "1"]], [""]), "nonempty", None))
        invalid.append((analyzed(dialect, "SELECT 1 OPERATOR(public.+) 2"), "pg_catalog operator", None))
    invalid.append((missing, "Relation ir_evidence_accounts does not exist.", diagnostics + "SemanticException"))
    return invalid


def run_dialect(dialect, release, args, settings, path, worker_factory=Worker, observer_factory=Observer):
    tag = ("pg-" if dialect == "postgres" else "mysql-") + release
    transport = check_transport()
    deadline = time.monotonic() + settings["timeoutSeconds"]
    exported = collections.Counter()
    histogram = collections.Counter()
    with path.open("w") as stream, observer_factory(dialect, deadline) as db, worker_factory(args, deadline, script="server_worker.php") as worker:
        ledger = Ledger(stream)
        check_server(dialect, release, db)
        current_input = None
        try:
            def compare_cases(phase, cases):
                nonlocal current_input
                indexed = enumerate(cases)
                while (chunk := list(itertools.islice(indexed, transport["maximumBatch"]))):
                    records = [(f"{phase}/{index}", item | {"release": tag}) for index, item in chunk]
                    current_input = {"batch": records}
                    yield from check_direct(records, worker, db, ledger, histogram)

            for result in compare_cases("finite", direct_cases(dialect)):
                exported[(source_key(result["source"]), node_key(result["columns"][0][0]))] += 1
            require(set(exported) == finite_ids(dialect) and set(exported.values()) == {1}, "server-finite-incomplete",
                    {"missing": list(finite_ids(dialect) - set(exported)), "extra": list(set(exported) - finite_ids(dialect))})
            for seed in settings["seeds"]:
                candidates = explore(dialect, seed, settings["attemptsPerSeed"], settings["maxDepth"], settings["maxWidth"])
                for _ in compare_cases(f"seed/{seed}", candidates):
                    pass
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
                check_direct([(f"boundary/{index}", item)], worker, db, ledger, histogram)
            current_input = None
            check_analyzed(dialect, tag, worker, db, ledger)
            for index, (current_input, signal, exception) in enumerate(negatives(dialect)):
                key = f"negative/{index}"
                current_input["release"] = tag
                ledger.record(key, "attempted", input=current_input)
                response = worker.request(current_input)
                require(response["status"] == "rejected" and signal in response["message"]
                        and (exception is None or response["exception"] == exception), "server-negative-mismatch", response)
                ledger.record(key, "rejected", response=response)
            require(histogram.keys() == {"integer", "boolean", "null", "slot", "operation"}, "server-unobserved-operands", dict(histogram))
        except Exception as error:
            write_json(path.with_suffix(".counterexample.json"), {"original": current_input, "failure": str(error)})
            raise
        counts = ledger.verify()
        return {
            "finiteIds": sorted(exported), "distribution": dict(histogram), "execution": counts,
            "engine": db.environment, "release": release, "php": worker.environment, "transport": transport,
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
    publication = projection_publication()
    specification = json.loads(contract.read_text())
    settings = specification["exploration"]
    require(not args.release or args.engine, "release-needs-engine")
    commit, distribution = candidate_distribution(args.candidate)
    write_json(args.out / "distribution.json", distribution)
    results = {}
    for dialect in (args.engine,) if args.engine else DIALECTS:
        release = args.release or VERSIONS[dialect].split("-", 1)[1]
        require(release in specification["profiles"][dialect]["releases"], "unregistered-engine-release", release)
        code_shape = shape(args, dialect=dialect)
        results[dialect] = run_dialect(dialect, release, args, settings, args.out / f"{dialect}.jsonl")
        results[dialect]["shape"] = code_shape
    verify_candidate(args.candidate, commit, distribution)
    index = {
        "status": "provisional-server-projections", "candidate": commit, "candidateIdentityContract": IDENTITY_CONTRACT,
        "distribution": digest(args.out / "distribution.json"), "contract": CONTRACT, "admissionContract": ADMISSION_CONTRACT,
        "publication": publication,
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
