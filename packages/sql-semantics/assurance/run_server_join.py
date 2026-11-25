"""Compare complete native joins against an independent relational evaluator."""

import argparse
import json
import os
import sys
import time
from collections import Counter
from pathlib import Path

from native_counterexample_reduction import record as record_reduction
from native_postgres_fixture import ENV as FIXTURE_CONTAINER, renew as renew_fixture
from run import IDENTITY_CONTRACT, IMAGE, TOOL, Failure, Ledger, Worker, candidate_distribution, digest, require, shape, verify_candidate, write_json
from run_server import CONTRACT as PROJECTION
from run_server import Observer, check_profile, check_transport, rejects, responses, same
from run_server_delete import tools, version_tag
from server_cases import observation
from server_join_cases import case, expected, finite_id, finite_ids, fixture, is_join, join, leaves, requests, slot, table
from sqlite_domain import FAULT_EXECUTION_SECONDS, FAULT_STARTUP_SECONDS, RenderFault, check_render_faults

LABEL = "join"
OBSERVER = "server_observer.php"
CONTRACT = "43bfd3ddd7271b70a61fb411e918a0a2acac55b2cd01bf5e7069baadb5f93747"


def specifications():
    path, projection = TOOL / "server-join-v2.json", TOOL / "server-projection-v1.json"
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


def observer_request(_, sql):
    return {"sql": sql}


def probe(item, response, db, domain):
    domain.check_value(item, response)
    actual = domain.observation(db.request(domain.observer_request(item, response["sql"])))
    domain.check_observation(item, actual, response["sql"])
    return actual


def required_forms(engine, _tag):
    kinds = ("CROSS", "INNER", "LEFT", "RIGHT") + (("FULL",) if engine == "postgres" else ())
    return {"width/binary", "width/nested", "nested/left", "nested/right", "filter/absent", "filter/present",
            "projection/first", "projection/later", *("kind/" + kind for kind in kinds)}


def sample_forms(forms, response, _engine, _item):
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


def request_batches(cases, maximum):
    pending = []
    for case in cases:
        _, request, _, rejection = case
        if rejection is not None or "sql" in request:
            if pending:
                yield pending
                pending = []
            yield [case]
            continue
        pending.append(case)
        if len(pending) == maximum:
            yield pending
            pending = []
    if pending:
        yield pending


def observe_finite_id(domain, engine, response, item, request):
    if "sql" in request:
        return domain.analysis_finite_id(engine, response, item, request["sql"])
    return domain.finite_id(engine, response, item)


def seed_ids(specification):
    exploration = specification["exploration"]
    budgets = (("seed", exploration["casesPerSeed"]), ("direct-seed", exploration.get("directCasesPerSeed", 0)))
    return {f"{group}/{seed}/{index}" for group, count in budgets for seed in exploration["seeds"] for index in range(count)}


def direct_identities(domain, engine, specification, tag):
    expected = getattr(domain, "direct_finite_ids", domain.finite_ids)
    return expected(engine, specification, tag)


def negative_count(specification, engine, tag):
    if "negativeCount" in specification:
        count = specification["negativeCount"]
        if isinstance(count, dict):
            count = count.get(tag, count.get("default"))
        require(type(count) is int and count > 0, "invalid-fixed-rejection-count")
        return count
    specific = specification.get("negativeByRelease", {})
    dialect = {"postgres": "additionalPostgreSqlNegatives", "mysql": "additionalMySqlNegatives"}[engine]
    return len(specification["negative"]) + len(specific.get(tag, specific.get("default", ()))) + len(specification.get(dialect, ()))


def execute(args, specification, tag, worker, db, domain=None):
    if domain is None:
        domain = sys.modules[__name__]
    label = domain.LABEL
    transport = check_transport()
    seen, direct, forms = Counter(), Counter(), Counter()
    observe_direct = getattr(domain, "direct_finite_id", None)
    observe_rejection = getattr(domain, 'observe_rejection', None)
    sample_native_forms = getattr(domain, "sample_native_forms", None)
    refusals = Counter()
    key = current = comparison = None
    batch = []
    with (args.out / "events.jsonl").open("w") as output:
        ledger = Ledger(output)
        try:
            for batch in request_batches(domain.requests(args.engine, tag, specification), transport["maximumBatch"]):
                for key, current, _, _ in batch:
                    ledger.record(key, "attempted", input=current)
                inputs = [entry[1] for entry in batch]
                if len(inputs) == 1:
                    replies = [worker.request(inputs[0])]
                else:
                    replies = responses(worker.request, inputs, "server-worker-batch-mismatch")
                if batch[0][3] is not None:
                    key, current, item, rejection = batch[0]
                    response = replies[0]
                    require(rejects(response, rejection), f"server-{label}-negative-boundary", response)
                    witness = {}
                    if observe_rejection is not None and item is not None:
                        verdict = db.request(domain.observer_request(item, current["sql"]))
                        coordinate = observe_rejection(specification, item, verdict, current["sql"])
                        refusals[coordinate] += 1
                        witness["nativeRejection"] = verdict
                    ledger.record(key, "rejected", response=response, **witness)
                    continue
                for (key, current, item, _), response in zip(batch, replies):
                    domain.check_value(item, response)
                    ledger.record(key, "constructed", response=response)
                statements = [domain.observer_request(item, response["sql"]) for (_, _, item, _), response in zip(batch, replies)]
                if len(statements) == 1:
                    native = [db.request(statements[0])]
                else:
                    native = responses(db.request, statements, "server-observer-batch-mismatch")
                for (key, current, item, _), response, raw in zip(batch, replies, native):
                    actual = domain.observation(raw)
                    comparison = item
                    domain.check_observation(item, actual, response["sql"])
                    comparison = None
                    details = {"observation": actual}
                    if "sql" in current:
                        original = domain.observation(db.request(domain.observer_request(item, current["sql"])))
                        domain.check_observation(item, original, current["sql"])
                        details["original"] = original
                    ledger.record(key, "compared", **details)
                    if key.startswith("finite/"):
                        seen[observe_finite_id(domain, args.engine, response, item, current)] += 1
                    elif key.startswith("direct/") and observe_direct is not None:
                        direct[observe_direct(args.engine, response, item, current)] += 1
                    elif key.startswith(("seed/", "direct-seed/")):
                        domain.sample_forms(forms, response, args.engine, item)
                        if sample_native_forms is not None:
                            sample_native_forms(forms, actual, args.engine, item)
            require(set(seen) == domain.finite_ids(args.engine, specification, tag) and set(seen.values()) == {1},
                    f"server-{label}-finite-incomplete", list(seen.items()))
            if observe_direct is not None:
                require(set(direct) == direct_identities(domain, args.engine, specification, tag) and set(direct.values()) == {1},
                        f"server-{label}-direct-finite-incomplete", list(direct.items()))
            if observe_direct is not None or getattr(domain, "COMPLETE_SEEDS", False):
                expected_seeds = seed_ids(specification)
                actual_seeds = {key for key, events in ledger.events.items() if key.startswith(("seed/", "direct-seed/")) and events[-1] == "compared"}
                require(actual_seeds == expected_seeds, f"server-{label}-seed-budget", sorted(expected_seeds ^ actual_seeds))
            require(domain.required_forms(args.engine, tag) <= forms.keys(), f"server-{label}-exploration-incomplete",
                    sorted(domain.required_forms(args.engine, tag) - forms.keys()))
            if observe_rejection is not None:
                require(refusals == Counter(domain.rejected_finite_ids(specification, tag)),
                        f"server-{label}-negative-finite-incomplete", list(refusals.items()))
            execution = ledger.verify()
            require(execution["rejected"] == negative_count(specification, args.engine, tag), f"server-{label}-rejections-incomplete")
            return {
                "finiteIds": sorted(seen), "coverage": dict(forms), "execution": execution, "transport": transport,
                **({"rejectedFiniteIds": sorted(refusals)} if observe_rejection is not None else {}),
                **({"directFiniteIds": sorted(direct)} if observe_direct is not None else {}),
            }
        except Exception as error:
            signal = error.signal if isinstance(error, Failure) else "unexpected-error"
            detail = error.detail if isinstance(error, Failure) else None
            for pending, _, _, _ in batch:
                if pending in ledger.events and ledger.events[pending][-1] not in {"compared", "rejected"}:
                    ledger.record(pending, "timeout" if signal == "timeout" else "failed", signal=signal, detail=detail)
            record_reduction(args.out / "counterexample.json", {
                "id": key, "original": current, "failure": str(error), "detail": detail,
                "signal": signal, "execution": dict(ledger.counts),
                "attemptedBatch": [entry[1] for entry in batch],
            }, comparison, lambda item: probe(item, worker.request(item), db, domain))
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


def check_fault_obligations(specification, engine, domain, controls, applied):
    """Reconcile applied mutations and explicitly registered comparator witnesses."""
    registrations = specification["faults"] + specification.get("dialectFaults", {}).get(engine, [])
    comparator_faults = getattr(domain, "COMPARATOR_FAULTS", {})
    for description, key in comparator_faults.items():
        require(registrations.count(description) == 1 and key in controls and controls[key],
                f"server-{domain.LABEL}-missing-comparator-fault", key)
    require(len(set(comparator_faults.values())) == len(comparator_faults)
            and len(applied) + len(comparator_faults) == len(registrations),
            f"server-{domain.LABEL}-faults-incomplete")
    return sorted(comparator_faults.values())


def main(domain):
    label = domain.LABEL
    specification, profiles = domain.specifications()
    parser = argparse.ArgumentParser(description=domain.__doc__)
    parser.add_argument("--candidate", required=True, type=Path)
    parser.add_argument("--tool-autoload", required=True, type=Path)
    parser.add_argument("--out", required=True, type=Path)
    parser.add_argument("--engine", required=True, choices=tuple(profiles))
    parser.add_argument("--release", required=True)
    parser.add_argument("--local", action="store_true")
    args = parser.parse_args()
    args.candidate, args.tool_autoload, args.out = args.candidate.resolve(), args.tool_autoload.resolve(), args.out.resolve()
    require(args.out != args.candidate and args.candidate not in args.out.parents, "evidence-directory-inside-candidate")
    args.out.mkdir(parents=True, exist_ok=False)
    require(args.release in profiles[args.engine]["releases"], f"server-{label}-release-not-registered", args.release)
    commit, manifest = candidate_distribution(args.candidate)
    write_json(args.out / "distribution.json", manifest)
    tag = version_tag(args.engine, args.release)
    mutations = domain.faults(args.engine, tag)
    container = os.environ.get(FIXTURE_CONTAINER)
    fixture = None
    try:
        comparator_controls = domain.controls()
        structure = shape(args, dialect=args.engine)
        if container is not None:
            require(args.engine == "postgres" and not args.local, "postgres-fixture-isolated-runner-required")
            fixture = renew_fixture(label.replace("-", "_"), args.release, container, args.out / "fixture-isolation.json")
        deadline = time.monotonic() + specification["exploration"]["timeoutSecondsPerRelease"]
        with Observer(args.engine, deadline, domain.OBSERVER) as db:
            check_profile(args.engine, args.release, db)
            preparation = domain.prepare(args.engine, db)
            write_json(args.out / "observer-preparation.json", preparation)
            domain.check_preparation(args.engine, db.environment, preparation)
            with Worker(args, deadline, script="server_worker.php") as php:
                result = execute(args, specification, tag, php, db, domain)
                result.update(candidateEnvironment=php.environment, observerEnvironment=db.environment, comparatorControls=comparator_controls, observerPreparation=preparation)
            db.deadline = time.monotonic() + (len(mutations) + 1) * (FAULT_STARTUP_SECONDS + FAULT_EXECUTION_SECONDS)
            fault_probe = getattr(domain, "fault_probe", None)
            if fault_probe is None:
                fault_probe = lambda item, value, observer: probe(item, value, observer, domain)
            result["faults"] = check_render_faults(args, db, mutations, fault_probe, script="server_worker.php")
            comparator_faults = check_fault_obligations(specification, args.engine, domain, comparator_controls, result["faults"])
            if comparator_faults:
                result["comparatorFaults"] = comparator_faults
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
        **({"fixtureIsolation": fixture} if fixture else {}),
    })
    (args.out / "summary.md").write_text(
        f"# Native {args.engine} {args.release} {label} evidence\n\nCandidate `{commit}`.\n\n"
        f"All {len(result['finiteIds'])} finite identities observed. The ledger recorded "
        f"{result['execution']['compared']} comparisons and {result['execution']['rejected']} specified rejections. "
        f"Detected {len(result['faults'])} applied mutations and {len(comparator_faults)} registered comparator faults.\n\n"
        "[Evidence index](index.json) · [Events](events.jsonl)\n"
    )
    print(args.out)


if __name__ == "__main__":
    main(sys.modules[__name__])
