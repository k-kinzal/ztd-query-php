"""Replay all native join ledgers against the independent relational model."""

import argparse
import difflib
import json
from collections import Counter
from os.path import relpath
from pathlib import Path
from types import SimpleNamespace

from evidence_transcript import canonical, events, load_replay_contract
from native_postgres_fixture import verify_link as verify_fixture
from run import IDENTITY_CONTRACT, IMAGE, Failure, candidate_distribution, digest, require, verify_candidate, verify_distribution, write_json
from renderer_fault_dispatch import verify as verify_fault_dispatch
from run_server import check_profile, check_transport, rejects, same
import run_server_join
from run_server_join import check_fault_obligations, checked_fault_plan, direct_identities, native_rejection_sql, negative_count, observe_finite_id, request_batches, seed_ids, tools, version_tag


def replay(path, report, specification, domain=run_server_join):
    label = domain.LABEL
    engine = report["engine"]
    tag = version_tag(engine, report["release"])
    stream = iter(events(path))
    seen, direct, forms, execution = Counter(), Counter(), Counter(), Counter()
    observe_direct = getattr(domain, "direct_finite_id", None)
    seeds = set()
    observe_rejection = getattr(domain, 'observe_rejection', None)
    sample_native_forms = getattr(domain, "sample_native_forms", None)
    refusals = Counter()

    def matched(key, kind):
        frame = next(stream, None)
        require(frame is not None and frame["id"] == key and frame["event"] == kind,
                f"server-{label}-replay-event", {"expected": [key, kind], "actual": frame})
        execution[kind] += 1
        return frame

    transport = check_transport()
    same(transport, report["transport"])
    for batch in request_batches(domain.requests(engine, tag, specification), transport["maximumBatch"]):
        for key, request, _, _ in batch:
            require(canonical(matched(key, "attempted")["input"]) == canonical(request), f"server-{label}-replay-request", key)
        if batch[0][3] is not None:
            key, request, item, rejection = batch[0]
            frame = matched(key, "rejected")
            require(rejects(frame["response"], rejection), f"server-{label}-replay-rejection", key)
            if observe_rejection is not None and item is not None:
                require("nativeRejection" in frame, f"server-{label}-replay-native-rejection-missing", key)
                sql = native_rejection_sql(domain, request, item)
                if "sql" not in request:
                    same(sql, frame.get("nativeRejectionSql"))
                coordinate = observe_rejection(specification, item, frame["nativeRejection"], sql)
                refusals[coordinate] += 1
            else:
                require("nativeRejection" not in frame, f"server-{label}-replay-unexpected-native-rejection", key)
            continue
        replies = []
        for key, _, item, _ in batch:
            response = matched(key, "constructed")["response"]
            domain.check_value(item, response)
            replies.append(response)
        for (key, request, item, _), response in zip(batch, replies):
            compared = matched(key, "compared")
            witness = domain.observation(compared["observation"])
            domain.check_observation(item, witness, response["sql"])
            require(("sql" in request) == ("original" in compared), f"server-{label}-replay-original-missing", key)
            if "sql" in request:
                domain.check_observation(item, domain.observation(compared["original"]), request["sql"])
            if key.startswith("finite/"):
                seen[observe_finite_id(domain, engine, response, item, request)] += 1
            elif key.startswith("direct/") and observe_direct is not None:
                direct[observe_direct(engine, response, item, request)] += 1
            elif key.startswith(("seed/", "direct-seed/")):
                domain.sample_forms(forms, response, engine, item)
                if sample_native_forms is not None:
                    sample_native_forms(forms, witness, engine, item)
                require(key not in seeds, f"server-{label}-duplicate-seed-case", key)
                seeds.add(key)
    require(next(stream, None) is None, f"server-{label}-replay-unconsumed-events", str(path))
    require(set(seen) == domain.finite_ids(engine, specification, tag) and set(seen.values()) == {1}, f"server-{label}-replay-incomplete")
    if observe_rejection is not None:
        require(refusals == Counter(domain.rejected_finite_ids(specification, tag)),
                f"server-{label}-replay-negative-finite-incomplete", list(refusals.items()))
        require(canonical(sorted(refusals)) == canonical(report["rejectedFiniteIds"]), f"server-{label}-replay-negative-finite-receipt")
    if observe_direct is not None:
        require(set(direct) == direct_identities(domain, engine, specification, tag) and set(direct.values()) == {1},
                f"server-{label}-replay-direct-incomplete", list(direct.items()))
        require(canonical(sorted(direct)) == canonical(report["directFiniteIds"]), f"server-{label}-replay-direct-receipt")
    if observe_direct is not None or getattr(domain, "COMPLETE_SEEDS", False):
        require(seeds == seed_ids(specification), f"server-{label}-replay-seed-budget")
    require(domain.required_forms(engine, tag) <= forms.keys(), f"server-{label}-replay-coverage-incomplete")
    require(execution["rejected"] == negative_count(specification, engine, tag), f"server-{label}-replay-negative-count")
    require(canonical(sorted(seen)) == canonical(report["finiteIds"]) and forms == report["coverage"]
            and execution == report["execution"], f"server-{label}-replay-summary-mismatch", str(path))
    return dict(execution)


def check_faults(path, report, distribution, checkout, domain):
    label = domain.LABEL
    engine, release = report["engine"], report["release"]
    definitions = {fault.name: fault for fault in checked_fault_plan(domain, engine, version_tag(engine, release))}
    dispatch = verify_fault_dispatch(path, definitions.values(), "server_worker.php", report["faults"])
    records = {entry["case"]: entry for entry in report["faults"]}
    require(len(records) == len(report["faults"]) and records.keys() == definitions.keys(), f"server-{label}-fault-set", str(path))
    for name, rule in definitions.items():
        record = records[name]
        folder = path / "faults" / name
        before = (checkout / "packages" / rule.path).read_text()
        require(before.count(rule.before) == 1, f"server-{label}-fault-anchor", name)
        after = before.replace(rule.before, rule.replacement)
        delta = "".join(difflib.unified_diff(
            before.splitlines(keepends=True), after.splitlines(keepends=True), f"a/{rule.path}", f"b/{rule.path}",
        ))
        witness = record["detail"]
        structural = rule.request is not None and rule.request.get("constructionOnly") is True
        if structural:
            observed = record["mutantResponse"]
            require(observed["status"] == "constructed" and observed["sql"] is None and witness == {
                "expected": rule.item["boundary"], "observed": observed, "sql": None,
            }, f"server-{label}-fault-construction-evidence", name)
        else:
            require(type(witness["sql"]) is str and bool(witness["sql"]), f"server-{label}-fault-sql-evidence", name)
        require(record == json.loads((folder / "result.json").read_text())
                and record["control"] == "passed" and record["expected"] == record["observed"] == rule.signal
                and record["file"] == rule.path and record["input"] == rule.item
                and record.get("request") == rule.request
                and distribution[f"packages/{rule.path}"] == record["source"]
                and record["source"] != record["mutation"] == digest(folder / rule.path)
                and (folder / rule.path).read_text() == after
                and (folder / "injected.diff").read_text() == delta, f"server-{label}-fault-evidence", name)
        replay_fault = getattr(domain, "replay_fault", None)
        if replay_fault is not None:
            replay_fault(rule, record)
            continue
        domain.check_fault_witness(rule.item, witness)
        try:
            domain.check_observation(rule.item, witness["observed"], witness["sql"])
        except Failure as failure:
            require(failure.signal == rule.signal, f"server-{label}-wrong-fault-signal", name)
        else:
            raise Failure(f"server-{label}-replayed-fault-not-detected", name)
    return dispatch


def combine(candidate, checkout, paths, output, domain, require_fixture_reset=False):
    label = domain.LABEL
    load_replay_contract()
    specification, profiles = domain.specifications()
    required = {(engine, release) for engine, profile in profiles.items() for release in profile["releases"]}
    _, manifest = candidate_distribution(checkout, candidate)
    registered_tools = tools()
    releases, counts = {}, Counter()
    for path in paths:
        raw = json.loads(path.read_text())
        role = raw["engine"], raw["release"]
        require(role in required and role not in releases, f"duplicate-or-unregistered-server-{label}-release", role)
        require(raw["status"] == f"provisional-server-{label}" and raw["candidate"] == candidate
                and raw["candidateIdentityContract"] == IDENTITY_CONTRACT and raw["contract"] == domain.CONTRACT
                and raw["isolation"] == IMAGE and raw["toolDigests"] == registered_tools, f"server-{label}-evidence-target-mismatch", str(path))
        verify_fixture(path.parent, raw.get("fixtureIsolation"), label.replace("-", "_"), role[1], require_fixture_reset)
        distribution = path.parent / "distribution.json"
        require(raw["distribution"] == digest(distribution), f"server-{label}-evidence-manifest-mismatch", str(path))
        verify_distribution(json.loads(distribution.read_text()), manifest)
        ledger = path.parent / "events.jsonl"
        require(raw["eventsDigest"] == digest(ledger), f"server-{label}-evidence-ledger-mismatch", str(path))
        check_profile(*role, SimpleNamespace(environment=raw["observerEnvironment"]))
        same(domain.controls(), raw["comparatorControls"])
        if getattr(domain, "COMPARATOR_FAULTS", None):
            comparator_faults = check_fault_obligations(specification, raw["engine"], domain, raw["comparatorControls"], raw["faults"])
            same(comparator_faults, raw["comparatorFaults"])
        domain.check_preparation(raw["engine"], raw["observerEnvironment"], raw["observerPreparation"])
        results = replay(ledger, raw, specification, domain)
        dispatch = check_faults(path.parent, raw, manifest, checkout, domain)
        counts.update(results)
        releases[role] = {"index": relpath(path, output.parent), "indexDigest": digest(path), "eventsDigest": digest(ledger),
                          "finite": len(raw["finiteIds"]), "faults": len(raw["faults"]), "faultDispatch": dispatch,
                          "execution": results,
                          **({"fixtureIsolation": raw["fixtureIsolation"]} if "fixtureIsolation" in raw else {}),
                          **({"comparatorFaults": raw["comparatorFaults"]} if "comparatorFaults" in raw else {}),
                          **({"directFinite": len(raw["directFiniteIds"])} if "directFiniteIds" in raw else {}),
                          **({"rejectedFinite": len(raw["rejectedFiniteIds"])} if "rejectedFiniteIds" in raw else {})}
    verify_candidate(checkout, candidate, manifest)
    missing = sorted(required - releases.keys())
    status = "incomplete" if missing else f"provisional-native-{label}-matrix"
    write_json(output, {"status": status, "candidate": candidate, "contract": domain.CONTRACT,
                        "execution": dict(counts), "missing": missing,
                        "releaseReports": {" ".join(role): value for role, value in sorted(releases.items())}})
    require(not missing, f"server-{label}-release-evidence-missing", missing)


def main(domain):
    parser = argparse.ArgumentParser(description=f"Replay the exact native {domain.LABEL} release set.")
    parser.add_argument("--candidate", required=True)
    parser.add_argument("--candidate-root", type=Path, required=True)
    parser.add_argument("--out", type=Path, required=True)
    parser.add_argument("--require-fixture-reset", action="store_true")
    parser.add_argument("indexes", type=Path, nargs="+")
    args = parser.parse_args()
    candidate = args.candidate_root.resolve()
    paths = [path.resolve() for path in args.indexes]
    output = args.out.resolve()
    require(all(path != candidate and candidate not in path.parents for path in [output, *paths]), "evidence-directory-inside-candidate")
    combine(args.candidate, candidate, paths, output, domain, args.require_fixture_reset)


if __name__ == "__main__":
    main(run_server_join)
