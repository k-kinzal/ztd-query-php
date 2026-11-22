"""Compare complete native joins against an independent relational evaluator."""

import argparse
import json
import time
from collections import Counter
from pathlib import Path

from run import IDENTITY_CONTRACT, IMAGE, TOOL, Failure, Ledger, Worker, candidate_distribution, digest, require, shape, verify_candidate, write_json
from run_server import CONTRACT as PROJECTION
from run_server import Observer, check_profile, same
from run_server_delete import tools, version_tag
from server_cases import observation
from server_join_cases import case, expected, finite_id, finite_ids, fixture, is_join, join, leaves, requests, slot, table
from sqlite_domain import FAULT_EXECUTION_SECONDS, FAULT_STARTUP_SECONDS, RenderFault, check_render_faults

CONTRACT = "5b39afadadce8151ab181298a97955489a11919287b3b47566923468e7ee9554"


def specifications():
    path, projection = TOOL / "server-join-v1.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(projection) == PROJECTION, "server-join-contract-mismatch")
    return json.loads(path.read_text()), json.loads(projection.read_text())["profiles"]


def check_value(item, response):
    require(response["status"] == "constructed", "server-join-positive-rejected", response)
    require(response["profile"].startswith(item["release"] + "/"), "server-join-profile-mismatch", response)
    require(all(item[field] == response[field] for field in ("source", "columns", "where")), "server-join-shape-mismatch",
            {"expected": item, "response": response})


def probe(item, response, db):
    check_value(item, response)
    actual = observation(db.request({"sql": response["sql"]}))
    require(actual["status"] == "rows", "server-join-native-rejection", {"sql": response["sql"], "observation": actual})
    prediction = expected(item)
    try:
        same(prediction, actual)
    except Failure as failure:
        raise Failure(failure.signal, {"sql": response["sql"], "expected": prediction, "observed": actual}) from failure
    return actual


def required_forms(engine):
    kinds = ("CROSS", "INNER", "LEFT", "RIGHT") + (("FULL",) if engine == "postgres" else ())
    return {"width/binary", "width/nested", "nested/left", "nested/right", "filter/absent", "filter/present",
            "projection/first", "projection/later", *("kind/" + kind for kind in kinds)}


def sample_forms(forms, response):
    def operands(expression):
        if expression[0] == "slot":
            forms["projection/" + ("later" if len(expression) == 3 else "first")] += 1
        elif expression[0] == "operation":
            for child in expression[2:]:
                operands(child)

    def visit(source):
        if not is_join(source):
            return
        forms["kind/" + source["kind"]] += 1
        for side in ("left", "right"):
            if is_join(source[side]):
                forms["nested/" + side] += 1
            visit(source[side])

    forms["width/" + ("binary" if len(leaves(response["source"])) == 2 else "nested")] += 1
    forms["filter/" + ("absent" if response["where"] is None else "present")] += 1
    visit(response["source"])
    for expression, _ in response["columns"]:
        operands(expression)


def execute(args, specification, tag, worker, db):
    seen, forms = Counter(), Counter()
    current = None
    with (args.out / "events.jsonl").open("w") as output:
        ledger = Ledger(output)
        try:
            for key, current, item, rejection in requests(args.engine, tag, specification):
                ledger.record(key, "attempted", input=current)
                response = worker.request(current)
                if rejection is not None:
                    require(response["status"] == "rejected" and rejection in response["message"], "server-join-negative-boundary", response)
                    ledger.record(key, "rejected", response=response)
                    continue
                check_value(item, response)
                ledger.record(key, "constructed", response=response)
                details = {"observation": probe(item, response, db)}
                if "sql" in current:
                    original = observation(db.request({"sql": current["sql"]}))
                    same(expected(item), original)
                    details["original"] = original
                ledger.record(key, "compared", **details)
                if key.startswith("finite/"):
                    seen[finite_id(args.engine, response)] += 1
                elif key.startswith("seed/"):
                    sample_forms(forms, response)
            require(set(seen) == finite_ids(args.engine, specification) and set(seen.values()) == {1},
                    "server-join-finite-incomplete", list(seen.items()))
            require(required_forms(args.engine) <= forms.keys(), "server-join-exploration-incomplete",
                    sorted(required_forms(args.engine) - forms.keys()))
            execution = ledger.verify()
            require(execution["rejected"] == len(specification["negative"]), "server-join-rejections-incomplete")
            return {"finiteIds": sorted(seen), "coverage": dict(forms), "execution": execution}
        except Exception as error:
            write_json(args.out / "counterexample.json", {
                "original": current, "failure": str(error), "shrunk": None,
                "signal": error.signal if isinstance(error, Failure) else "unexpected-error",
            })
            raise


def faults(engine, tag):
    path = f"sql-semantics-{engine}/src/Ir/SelectSql.php"
    left, right, empty = (table(engine, name) for name in ("accounts", "independent", "empty"))
    return [
        RenderFault("erase-left-null-extension", path, "$source->kind->value . ' JOIN '",
                    "($source->kind->value === 'LEFT' ? 'INNER' : $source->kind->value) . ' JOIN '",
                    case(engine, tag, join("LEFT", left, empty, ["boolean", True]), [[slot(0, 1), "result"]]), "meaning-mismatch"),
        RenderFault("render-wrong-join-occurrence", path, "$this->alias($offset + $expression->occurrence)",
                    "$this->alias($offset)",
                    case(engine, tag, join("CROSS", left, right), [[slot(0, 1), "result"]]), "meaning-mismatch"),
        RenderFault("omit-join-filter", path, "if ($select->where !== null)", "if (false)",
                    case(engine, tag, join("CROSS", left, right), [[slot(0), "result"]], ["boolean", False]), "meaning-mismatch"),
    ]


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate", required=True, type=Path)
    parser.add_argument("--tool-autoload", required=True, type=Path)
    parser.add_argument("--out", required=True, type=Path)
    parser.add_argument("--engine", required=True, choices=("mysql", "postgres"))
    parser.add_argument("--release", required=True)
    parser.add_argument("--local", action="store_true")
    args = parser.parse_args()
    args.candidate, args.tool_autoload, args.out = args.candidate.resolve(), args.tool_autoload.resolve(), args.out.resolve()
    require(args.out != args.candidate and args.candidate not in args.out.parents, "evidence-directory-inside-candidate")
    args.out.mkdir(parents=True, exist_ok=False)
    specification, profiles = specifications()
    require(args.release in profiles[args.engine]["releases"], "server-join-release-not-registered", args.release)
    commit, manifest = candidate_distribution(args.candidate)
    write_json(args.out / "distribution.json", manifest)
    tag = version_tag(args.engine, args.release)
    mutations = faults(args.engine, tag)
    try:
        structure = shape(args, dialect=args.engine)
        deadline = time.monotonic() + specification["exploration"]["timeoutSecondsPerRelease"]
        with Observer(args.engine, deadline) as db:
            check_profile(args.engine, args.release, db)
            same({"status": "fixture"}, db.request({"fixture": fixture(args.engine)}))
            with Worker(args, deadline, script="server_worker.php") as php:
                result = execute(args, specification, tag, php, db)
                result.update(candidateEnvironment=php.environment, observerEnvironment=db.environment)
            db.deadline = time.monotonic() + (len(mutations) + 1) * (FAULT_STARTUP_SECONDS + FAULT_EXECUTION_SECONDS)
            result["faults"] = check_render_faults(args, db, mutations, probe, script="server_worker.php")
            require(len(result["faults"]) == len(specification["faults"]), "server-join-faults-incomplete")
    except Exception as error:
        write_json(args.out / "run-failure.json", {
            "candidate": commit, "contract": CONTRACT, "engine": args.engine, "release": args.release,
            "signal": error.signal if isinstance(error, Failure) else "unexpected-error", "failure": str(error),
        })
        raise
    verify_candidate(args.candidate, commit, manifest)
    write_json(args.out / "index.json", {
        "status": "provisional-server-join", "candidate": commit, "candidateIdentityContract": IDENTITY_CONTRACT,
        "contract": CONTRACT, "distribution": digest(args.out / "distribution.json"), "toolDigests": tools(),
        "engine": args.engine, "release": args.release, "isolation": "none" if args.local else IMAGE,
        "shape": structure, "eventsDigest": digest(args.out / "events.jsonl"), **result,
    })
    (args.out / "summary.md").write_text(
        f"# Native {args.engine} {args.release} join evidence\n\nCandidate `{commit}`.\n\n"
        f"All {len(result['finiteIds'])} finite identities observed. The ledger recorded "
        f"{result['execution']['compared']} comparisons and {result['execution']['rejected']} specified rejections. "
        f"All {len(result['faults'])} registered faults were detected.\n\n[Evidence index](index.json) · [Events](events.jsonl)\n"
    )
    print(args.out)


if __name__ == "__main__":
    main()
