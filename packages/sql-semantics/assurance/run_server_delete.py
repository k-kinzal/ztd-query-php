"""Collect complete-value DELETE effects on an isolated native fixture."""

import argparse
import json
import time
from collections import Counter
from pathlib import Path

from evidence_transcript import canonical
from run import IDENTITY_CONTRACT, IMAGE, TOOL, Failure, Ledger, Worker, candidate_distribution, digest, require, shape, verify_candidate, write_json
from run_server import CONTRACT as PROJECTION, Observer, check_profile, rejects, same
from server_delete_cases import ROWS, case, expected, finite_id, finite_ids, literal, observation, ordered, requests
from sqlite_domain import FAULT_EXECUTION_SECONDS, FAULT_STARTUP_SECONDS, RenderFault, check_render_faults

LABEL = "delete"
CONTRACT_NAME = "server-delete-v2.json"
CONTRACT = "3a5763e35bcea1af1f7f1127dd8a157349dd8b2e79451c61e2388ec9b4780858"


def specifications():
    deletion, projection = TOOL / CONTRACT_NAME, TOOL / "server-projection-v1.json"
    require(digest(deletion) == CONTRACT and digest(projection) == PROJECTION, "server-delete-contract-mismatch")
    return json.loads(deletion.read_text()), json.loads(projection.read_text())["profiles"]


def tools():
    return {path.name: digest(path) for path in TOOL.iterdir() if path.suffix in {".php", ".py", ".json", ".md"}}


def version_tag(engine, release):
    return ("mysql-" if engine == "mysql" else "pg-") + release


def check_value(item, response):
    require(response["status"] == "constructed", "server-delete-positive-rejected", response)
    require(response["profile"].startswith(item["release"] + "/"), "server-delete-profile-mismatch", response)
    require(response["where"] == item["where"] and response["target"] == item["target"], "server-delete-shape-mismatch", response)


def probe(item, response, db):
    check_value(item, response)
    actual = observation(db.request({"sql": response["sql"]}))
    check_observation(item, actual, response["sql"])
    return actual


def check_observation(item, actual, sql):
    prediction = expected(item)
    require(actual["status"] == "deleted", "server-delete-fault-native", actual)
    require(canonical(prediction) == canonical(actual), "meaning-mismatch",
            {"sql": sql, "expected": prediction, "observed": actual})


def check_fault_witness(item, witness):
    same(expected(item), witness["expected"])
    require(witness["observed"]["status"] == "deleted", "server-delete-fault-native")


def required_forms(engine):
    values = {"target/target", "target/child", "target/control", "where/None", "where/comparison",
              "where/boolean", "where/null", "operand/literal", "operand/slot", "count/zero", "count/positive"}
    values.update({"policy/False", "policy/True"} if engine == "postgres" else {"policy/False"})
    return values


def sample_forms(forms, response, actual):
    predicate = response["where"]
    target = response["target"]
    forms["target/" + target["table"].removeprefix("ir_delete_")] += 1
    forms[f"policy/{target.get('only', False)}"] += 1
    forms[f"where/{predicate[0] if predicate is not None else 'None'}"] += 1
    forms["count/" + ("positive" if actual["count"] else "zero")] += 1
    if predicate is not None and predicate[0] == "comparison":
        for operand in predicate[2:]:
            forms["operand/" + ("slot" if operand[0] == "slot" else "literal")] += 1


def execute(args, specification, engine, tag, worker, db):
    seen, forms = Counter(), Counter()
    current = None
    with (args.out / "events.jsonl").open("w") as output:
        ledger = Ledger(output)
        try:
            for key, current, item, rejection in requests(engine, tag, specification):
                ledger.record(key, "attempted", input=current)
                response = worker.request(current)
                if rejection is not None:
                    require(rejects(response, rejection),
                            "server-delete-negative-boundary", response)
                    ledger.record(key, "rejected", response=response)
                    continue
                check_value(item, response)
                ledger.record(key, "constructed", response=response)
                actual = probe(item, response, db)
                details = {"observation": actual}
                if "sql" in current:
                    original = observation(db.request({"sql": current["sql"]}))
                    same(expected(item), original)
                    details["original"] = original
                ledger.record(key, "compared", **details)
                if key.startswith("finite/"):
                    seen[finite_id(response)] += 1
                elif key.startswith("seed/"):
                    sample_forms(forms, response, actual)
            require(set(seen) == finite_ids(engine, specification) and set(seen.values()) == {1},
                    "server-delete-finite-incomplete", list(seen.items()))
            require(required_forms(engine) <= forms.keys(), "server-delete-exploration-incomplete", sorted(required_forms(engine) - forms.keys()))
            return {"finiteIds": sorted(seen), "coverage": dict(forms), "execution": ledger.verify()}
        except Exception as error:
            write_json(args.out / "counterexample.json", {
                "original": current, "failure": str(error), "shrunk": None,
                "signal": error.signal if isinstance(error, Failure) else "unexpected-error",
            })
            raise


def faults(engine, tag):
    path = f"sql-semantics-{engine}/src/Ir/Mutation/Delete.php"
    renderer = f"sql-semantics-{engine}/src/Ir/SelectSql.php"
    quote = chr(96) if engine == "mysql" else '"'
    fork = case(engine, tag, ["comparison", "<", ["slot", 0], literal(1)])
    comparison = "$left . ' ' . $operator . ' ' . $right"
    swapped = "$right . ' ' . $operator . ' ' . $left"
    if engine == "mysql":
        comparison = "$left . ' ' . $test->operator->value . ' ' . $right"
        swapped = "$right . ' ' . $test->operator->value . ' ' . $left"
    elif engine == "postgres":
        operator = "$operator = 'OPERATOR(pg_catalog.' . $condition->operator->value . ')';"
        prefix = operator + "\n\n            return '(' . "
        comparison, swapped = prefix + comparison, prefix + swapped
    result = [
        RenderFault("wrong-delete-target", path, "$this->target->table->quoted()", f"'{quote}ir_delete_control{quote}'",
                    case(engine, tag, None), "meaning-mismatch"),
        RenderFault("omit-delete-filter", path, "if ($this->where === null)", "if (true)",
                    case(engine, tag, ["boolean", False]), "meaning-mismatch"),
        RenderFault("reverse-delete-operands", renderer, comparison, swapped, fork, "meaning-mismatch"),
        RenderFault("delete-when-negating-null", renderer, "return '(NOT ' . $left . ')';",
                    "return '(NOT ' . ($left === 'NULL' ? 'FALSE' : $left) . ')';",
                    case(engine, tag, ["logic", "NOT", ["null"], None]), "meaning-mismatch"),
    ]
    if engine == "postgres":
        result.append(RenderFault("omit-delete-only", path, "($this->target->only ? 'ONLY ' : '')", "''",
                                  case(engine, tag, None, True), "meaning-mismatch"))
    return result


def observer_probes(engine):
    schema = "ir_fixture" if engine == "mysql" else "public"
    return (
        "DELETE FROM " + schema + ".ir_unregistered WHERE FALSE",
        "DELETE FROM " + schema + ".ir_delete_target WHERE FALSE; DELETE FROM " + schema + ".ir_delete_control",
        "DELETE FROM " + schema + ".ir_delete_target WHERE NOT EXISTS (SELECT id FROM " + schema + ".ir_delete_control)",
    )


def observer_controls(engine, db):
    result = {}
    for statement in observer_probes(engine):
        reply = db.request({"sql": statement})
        same({"status": "outside-observer-scope"}, reply)
        result[statement] = reply["status"]
    return result


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
    require(args.release in profiles[args.engine]["releases"], "server-delete-release-not-registered", args.release)
    commit, manifest = candidate_distribution(args.candidate)
    write_json(args.out / "distribution.json", manifest)
    tag = version_tag(args.engine, args.release)
    mutations = faults(args.engine, tag)
    try:
        structure = shape(args, dialect=args.engine)
        deadline = time.monotonic() + specification["exploration"]["timeoutSecondsPerRelease"]
        with Observer(args.engine, deadline, "server_delete_observer.php") as db:
            check_profile(args.engine, args.release, db)
            same(ordered(ROWS), ordered(db.environment["initial"]))
            protection = observer_controls(args.engine, db)
            with Worker(args, deadline, script="server_worker.php") as php:
                result = execute(args, specification, args.engine, tag, php, db)
                result.update(candidateEnvironment=php.environment, observerEnvironment=db.environment, observerControls=protection)
            db.deadline = time.monotonic() + (len(mutations) + 1) * (FAULT_STARTUP_SECONDS + FAULT_EXECUTION_SECONDS)
            result["faults"] = check_render_faults(args, db, mutations, probe, script="server_worker.php")
            registered = len(specification["faults"]) + len(specification.get("dialectFaults", {}).get(args.engine, ()))
            require(len(result["faults"]) == registered, "server-delete-faults-incomplete")
    except Exception as error:
        write_json(args.out / "run-failure.json", {
            "candidate": commit, "contract": CONTRACT, "engine": args.engine, "release": args.release,
            "signal": error.signal if isinstance(error, Failure) else "unexpected-error", "failure": str(error),
        })
        raise
    verify_candidate(args.candidate, commit, manifest)
    write_json(args.out / "index.json", {
        "status": "provisional-server-delete", "candidate": commit, "candidateIdentityContract": IDENTITY_CONTRACT,
        "contract": CONTRACT, "distribution": digest(args.out / "distribution.json"), "toolDigests": tools(),
        "engine": args.engine, "release": args.release, "isolation": "none" if args.local else IMAGE,
        "shape": structure, "eventsDigest": digest(args.out / "events.jsonl"), **result,
    })
    (args.out / "summary.md").write_text(
        f"# Native {args.engine} {args.release} DELETE evidence\n\nCandidate `{commit}`.\n\n"
        f"All {len(result['finiteIds'])} finite identities observed. The ledger recorded "
        f"{result['execution']['compared']} comparisons and {result['execution']['rejected']} specified rejections. "
        f"All {len(result['faults'])} registered faults were detected.\n\n[Evidence index](index.json) · [Events](events.jsonl)\n"
    )
    print(args.out)


if __name__ == "__main__":
    main()
