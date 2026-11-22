"""Compare complete native joins against an independent relational evaluator."""

import argparse
import json
import sys
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

LABEL = "join"
OBSERVER = "server_observer.php"
CONTRACT = "1dd8c227ba1638bb1591d4f12c3f9f199ba85e50fc6d24fb9e03b57ff8705b71"


def specifications():
    path, projection = TOOL / "server-join-v1.json", TOOL / "server-projection-v1.json"
    require(digest(path) == CONTRACT and digest(projection) == PROJECTION, "server-join-contract-mismatch")
    return json.loads(path.read_text()), json.loads(projection.read_text())["profiles"]


def check_value(item, response):
    require(response["status"] == "constructed", "server-join-positive-rejected", response)
    require(response["profile"].startswith(item["release"] + "/"), "server-join-profile-mismatch", response)
    require(all(item[field] == response[field] for field in ("source", "columns", "where")), "server-join-shape-mismatch",
            {"expected": item, "response": response})


def check_observation(item, actual, sql):
    require(actual["status"] == "rows", "server-join-native-rejection", {"sql": sql, "observation": actual})
    prediction = expected(item)
    try:
        same(prediction, actual)
    except Failure as failure:
        raise Failure(failure.signal, {"sql": sql, "expected": prediction, "observed": actual}) from failure


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "rows", "server-join-fault-native")


def controls():
    return {}


def prepare(engine, db):
    response = db.request({"fixture": fixture(engine)})
    same({"status": "fixture"}, response)
    return response


def check_preparation(_, environment, preparation):
    same({"status": "fixture"}, preparation)


def probe(item, response, db, domain):
    domain.check_value(item, response)
    actual = domain.observation(db.request({"sql": response["sql"]}))
    domain.check_observation(item, actual, response["sql"])
    return actual


def required_forms(engine):
    kinds = ("CROSS", "INNER", "LEFT", "RIGHT") + (("FULL",) if engine == "postgres" else ())
    return {"width/binary", "width/nested", "nested/left", "nested/right", "filter/absent", "filter/present",
            "projection/first", "projection/later", *("kind/" + kind for kind in kinds)}


def sample_forms(forms, response, _):
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


def execute(args, specification, tag, worker, db, domain=None):
    if domain is None:
        domain = sys.modules[__name__]
    label = domain.LABEL
    seen, forms = Counter(), Counter()
    key = current = None
    with (args.out / "events.jsonl").open("w") as output:
        ledger = Ledger(output)
        try:
            for key, current, item, rejection in domain.requests(args.engine, tag, specification):
                ledger.record(key, "attempted", input=current)
                response = worker.request(current)
                if rejection is not None:
                    require(response["status"] == "rejected" and rejection in response["message"], f"server-{label}-negative-boundary", response)
                    ledger.record(key, "rejected", response=response)
                    continue
                domain.check_value(item, response)
                ledger.record(key, "constructed", response=response)
                details = {"observation": probe(item, response, db, domain)}
                if "sql" in current:
                    original = domain.observation(db.request({"sql": current["sql"]}))
                    domain.check_observation(item, original, current["sql"])
                    details["original"] = original
                ledger.record(key, "compared", **details)
                if key.startswith("finite/"):
                    seen[domain.finite_id(args.engine, response)] += 1
                elif key.startswith("seed/"):
                    domain.sample_forms(forms, response, args.engine)
            require(set(seen) == domain.finite_ids(args.engine, specification) and set(seen.values()) == {1},
                    f"server-{label}-finite-incomplete", list(seen.items()))
            require(domain.required_forms(args.engine) <= forms.keys(), f"server-{label}-exploration-incomplete",
                    sorted(domain.required_forms(args.engine) - forms.keys()))
            execution = ledger.verify()
            registered = len(specification["negative"])
            if args.engine == "postgres":
                registered += len(specification.get("additionalPostgreSqlNegatives", ()))
            require(execution["rejected"] == registered, f"server-{label}-rejections-incomplete")
            return {"finiteIds": sorted(seen), "coverage": dict(forms), "execution": execution}
        except Exception as error:
            signal = error.signal if isinstance(error, Failure) else "unexpected-error"
            detail = error.detail if isinstance(error, Failure) else None
            if key in ledger.events and ledger.events[key][-1] not in {"compared", "rejected"}:
                ledger.record(key, "timeout" if signal == "timeout" else "failed", signal=signal, detail=detail)
            write_json(args.out / "counterexample.json", {
                "id": key, "original": current, "failure": str(error), "detail": detail, "shrunk": None,
                "signal": signal, "execution": dict(ledger.counts),
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


def main(domain):
    label = domain.LABEL
    parser = argparse.ArgumentParser(description=domain.__doc__)
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
    specification, profiles = domain.specifications()
    require(args.release in profiles[args.engine]["releases"], f"server-{label}-release-not-registered", args.release)
    commit, manifest = candidate_distribution(args.candidate)
    write_json(args.out / "distribution.json", manifest)
    tag = version_tag(args.engine, args.release)
    mutations = domain.faults(args.engine, tag)
    try:
        comparator_controls = domain.controls()
        structure = shape(args, dialect=args.engine)
        deadline = time.monotonic() + specification["exploration"]["timeoutSecondsPerRelease"]
        with Observer(args.engine, deadline, domain.OBSERVER) as db:
            check_profile(args.engine, args.release, db)
            preparation = domain.prepare(args.engine, db)
            domain.check_preparation(args.engine, db.environment, preparation)
            with Worker(args, deadline, script="server_worker.php") as php:
                result = execute(args, specification, tag, php, db, domain)
                result.update(candidateEnvironment=php.environment, observerEnvironment=db.environment, comparatorControls=comparator_controls, observerPreparation=preparation)
            db.deadline = time.monotonic() + (len(mutations) + 1) * (FAULT_STARTUP_SECONDS + FAULT_EXECUTION_SECONDS)
            result["faults"] = check_render_faults(args, db, mutations, lambda item, value, db: probe(item, value, db, domain), script="server_worker.php")
            require(len(result["faults"]) == len(specification["faults"]), f"server-{label}-faults-incomplete")
    except Exception as error:
        write_json(args.out / "run-failure.json", {
            "candidate": commit, "contract": domain.CONTRACT, "engine": args.engine, "release": args.release,
            "signal": error.signal if isinstance(error, Failure) else "unexpected-error", "failure": str(error),
        })
        raise
    verify_candidate(args.candidate, commit, manifest)
    write_json(args.out / "index.json", {
        "status": f"provisional-server-{label}", "candidate": commit, "candidateIdentityContract": IDENTITY_CONTRACT,
        "contract": domain.CONTRACT, "distribution": digest(args.out / "distribution.json"), "toolDigests": tools(),
        "engine": args.engine, "release": args.release, "isolation": "none" if args.local else IMAGE,
        "shape": structure, "eventsDigest": digest(args.out / "events.jsonl"), **result,
    })
    (args.out / "summary.md").write_text(
        f"# Native {args.engine} {args.release} {label} evidence\n\nCandidate `{commit}`.\n\n"
        f"All {len(result['finiteIds'])} finite identities observed. The ledger recorded "
        f"{result['execution']['compared']} comparisons and {result['execution']['rejected']} specified rejections. "
        f"All {len(result['faults'])} registered faults were detected.\n\n[Evidence index](index.json) · [Events](events.jsonl)\n"
    )
    print(args.out)


if __name__ == "__main__":
    main(sys.modules[__name__])
