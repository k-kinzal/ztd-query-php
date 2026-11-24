"""Index replayed evidence and identify outstanding acceptance requirements."""

import argparse
from difflib import unified_diff
from hashlib import sha256
import json
from pathlib import Path
import re

from compare_assurance_revisions import review
from check_fixture_launch_recovery import CONTROL_CASES as NATIVE_FIXTURE_CONTROLS
from check_native_workflow import audit as audit_workflow
from checked_scalar_dispatch import certificate as dispatch_certificate
from checked_value_graph import certificate
from constructor_trace import check_scope, trace
from evidence_transcript import events as execution_events
from grammar_scope import inventory as grammar_inventory
from native_counterexample_reduction import CONTROL_RESULTS as NATIVE_REDUCTION_CONTROLS, DIGEST as NATIVE_REDUCTION_DIGEST
from native_schedule import NATIVE, SINGLE_ENGINE, release_subset, reports as native_reports
from preflight_sqlite_faults import preflight as sqlite_fault_plan
from replay_sqlite_faults import CONTRACT as SQLITE_FAULT_REPLAY
from replay_native_boundary_faults import replay as replay_native_faults
from run_native_matrix import FIXTURE_STARTUP_SECONDS, PORT_COLLISION, PORT_RECOVERY, RUNNER_SECONDS, release_fixtures, release_jobs
from run_server import projection_publication
from run_sqlite_matrix import RUNNER_SECONDS as SQLITE_RUNNER_SECONDS, job_schedule as sqlite_jobs
from scope import CONTRACTS
from run import IMAGE, candidate_distribution, digest, require, verify_candidate, verify_distribution, write_json
from verify_release_layout import CONTRACT as RELEASE_LAYOUT, CONTRACT_NAME as RELEASE_LAYOUT_NAME, runtime_catalog, verify_probe


def verify_fixture_recovery(result, key):
    entry = result.get("failedLaunch")
    if entry is None:
        return
    name, failure = entry["containerName"], entry["failure"]
    collision = PORT_COLLISION.search(failure["stderr"])
    require(entry["contract"] == PORT_RECOVERY and re.fullmatch(r"ztd-ir-fixture-[0-9a-f]{32}", name)
            and failure["exitCode"] == 125 and isinstance(failure["stdout"], str)
            and collision and 0 < int(collision[1]) <= 65535, "bundle-native-fixture-original-launch", key)
    if "retriedAs" in entry:
        new_name = entry["retriedAs"]
        inspect = entry["inspect"]
        require(new_name != name and re.fullmatch(r"ztd-ir-fixture-[0-9a-f]{32}", new_name)
                and inspect["exitCode"] == 1 and inspect["stdout"] == ""
                and re.search(r"no such (?:container|object):", inspect["stderr"], re.IGNORECASE)
                and "recoveryWithheld" not in result, "bundle-native-fixture-retry-boundary", key)
    else:
        require(result["status"] == "incomplete" and not result["executions"], "bundle-native-fixture-invalid-recovery", key)


SQLITE = {
    "sqlite-pattern-v1.json", "sqlite-mutation-conflicts-v2.json", "sqlite-upsert-nothing-v1.json",
    "sqlite-case-v1.json", "sqlite-coalesce-v1.json", "sqlite-cast-v1.json", "sqlite-defaults-v4.json", "sqlite-computed-column-default-v3.json", "sqlite-not-null-conflict-v2.json", "sqlite-delete-returning-v1.json", "sqlite-group-filter-v1.json",
    "sqlite-update-returning-v1.json", "sqlite-insert-returning-v1.json", "sqlite-numeric-operators-v1.json", "sqlite-unary-v2.json",
    "sqlite-truth-v2.json", "sqlite-between-v1.json", "sqlite-membership-v1.json", "sqlite-folded-membership-v1.json", "sqlite-collation-v1.json", "sqlite-concatenation-v1.json",
    "contract-v1.json", "sqlite-compound-v1.json", "sqlite-create-table-v3.json", "sqlite-create-as-v5.json", "sqlite-strict-tables-v2.json", "sqlite-primary-keys-v2.json", "sqlite-auto-rowid-v1.json", "sqlite-unique-keys-v1.json", "sqlite-named-checks-v4.json", "sqlite-folded-checks-v1.json", "sqlite-implicit-checks-v1.json", "sqlite-generated-columns-v2.json", "sqlite-rename-v4.json", "sqlite-add-column-v3.json", "sqlite-add-generated-column-v1.json", "sqlite-appended-column-checks-v2.json", "sqlite-appended-implicit-checks-v1.json", "sqlite-drop-column-v3.json", "sqlite-create-index-v5.json", "sqlite-index-expression-spelling-v1.json", "sqlite-drop-index-v3.json", "sqlite-constraint-index-names-v2.json", "sqlite-reindex-v2.json", "sqlite-analyze-statistics-v1.json", "sqlite-flag-pragmas-v2.json", "sqlite-table-metadata-v2.json", "sqlite-connection-inventory-v1.json", "sqlite-index-metadata-v1.json", "sqlite-foreign-key-audit-v1.json", "sqlite-integrity-audit-v2.json", "sqlite-header-pragmas-v1.json", "sqlite-vacuum-v2.json", "sqlite-connection-commands-v1.json", "sqlite-page-pragmas-v2.json", "sqlite-reclaim-pragmas-v2.json", "sqlite-cache-pragmas-v2.json", "sqlite-audit-triggers-v2.json", "sqlite-drop-triggers-v4.json", "sqlite-trigger-mutations-v2.json", "sqlite-delete-v1.json",
    "sqlite-drop-table-v2.json", "sqlite-drop-view-v1.json", "sqlite-create-view-v2.json", "sqlite-create-view-grouped-v1.json", "sqlite-create-view-compound-v1.json", "sqlite-create-view-local-v1.json", "sqlite-resolved-view-input-v2.json", "sqlite-insert-v1.json", "sqlite-local-source-v2.json", "sqlite-joined-local-source-v1.json",
    "sqlite-recursive-source-v1.json", "sqlite-relational-v4.json", "sqlite-merged-join-v3.json", "sqlite-merged-prefix-join-v1.json", "sqlite-merged-right-join-v2.json", "sqlite-merged-full-join-v1.json", "sqlite-result-v1.json", "sqlite-scalars-v2.json",
    "wildcard-projection-v2.json", "sqlite-grouped-v2.json", "sqlite-insert-query-v1.json", "sqlite-insert-input-v2.json",
    "sqlite-transaction-v1.json", "sqlite-update-v1.json", "sqlite-values-v1.json",
}
BASE = "semantics-ir-evidence"
LIMITS = {
    "P01": "Resolved occurrences, binders, and operand roles in the checked domains",
    "P02": "Closed role types, checked construction, explicit rejection boundaries and finite admission cases",
    "P03": "Closed value storage and the verified dependency and publication boundaries",
    "P04": "Checked composition rules and independently observed operand structure",
    "P05": "Readonly storage, checked owned lists and restricted snapshot paths",
    "P06": "Closed emission rules and independently compared native observations",
    "Q01": "Checked constructor shapes, signed decimal interval derivation and independent negative cases",
    "Q02": "Replayed positive requests, observed finite identities and signed decimal interval derivation",
    "Q03": "Checked composition, scoped references and recorded nested operands",
    "Q04": "Native type, value and effect comparisons in the named domains",
    "Q05": "Exact candidate distribution metadata and staged Composer installation checks",
}


class Evidence:
    """Reads evidence links under one root and verifies their exact bytes."""

    def __init__(self, root, candidate, distribution, tool_root):
        self.root = root
        self.candidate = candidate
        self.distribution = distribution
        self.tool_root = tool_root
        self.tools = {file.name: digest(file) for file in tool_root.iterdir()
                      if file.suffix in (".php", ".py", ".json", ".md")}
        self.missing = []

    def checked(self, path, identity=None):
        source = Path(path)
        if not source.is_absolute():
            source = self.root / source
        resolved = source.resolve()
        require(self.root in resolved.parents and resolved.is_file(), "bundle-evidence-link", str(resolved))
        actual = digest(resolved)
        require(identity is None or identity == actual, "bundle-evidence-digest", str(resolved))
        return resolved, {"file": resolved.relative_to(self.root).as_posix(), "sha256": actual}

    def record(self, path):
        path, origin = self.checked(path)
        report = json.loads(path.read_text())
        require(report.get("candidate", report.get("commit")) == self.candidate, "bundle-candidate-mismatch", origin)
        return path, origin, report

    def optional(self, path):
        if (self.root / path).is_file():
            return self.record(path)
        self.missing.append(path)
        return None

    def raw(self, path, index_digest, ledger, ledger_digest, contract, tools):
        path, origin, raw = self.record(path)
        self.checked(path, index_digest)
        self.checked(path.parent / ledger, ledger_digest)
        require(raw["distribution"] == self.distribution, "bundle-runtime-mismatch", origin)
        self.checked(path.parent / "distribution.json", self.distribution)
        require(raw["contract"] == contract, "bundle-receipt-contract-mismatch", origin)
        require(raw.get("tools", raw.get("toolDigests")) == tools, "bundle-tool-mismatch", origin)
        return origin

    def sqlite(self):
        item = self.optional("sqlite-index/index.json")
        if item is None:
            return None
        path, origin, report = item
        require(report["status"] == "provisional" and set(report["domains"]) == SQLITE
                and report["faultReplayContract"] == SQLITE_FAULT_REPLAY,
                "bundle-sqlite-domain-mismatch", origin)
        domains = {}
        tools = {name: sha for name, sha in self.tools.items() if not name.endswith(".md")}
        for contract, receipt in sorted(report["domains"].items()):
            origin_ = self.raw(path.parent / receipt["index"], receipt["indexDigest"],
                               "events.jsonl", receipt["eventsDigest"], self.tools[contract], tools)
            require(receipt["contract"] == self.tools[contract] and receipt["compared"] > 0,
                    "bundle-sqlite-contract-mismatch", origin_)
            raw_index = (self.root / origin_["file"]).parent
            fault_replay = receipt["faultReplay"]
            require(len(fault_replay) == receipt["faults"], "bundle-sqlite-fault-replay-count", contract)
            fault_paths = {}
            for case, record in fault_replay.items():
                prefix = f"relational-faults/{case}/" if case == "restore" and contract == "sqlite-relational-v4.json" else f"faults/{case}/"
                expected_mode = "overlay-audited" if contract == "contract-v1.json" or prefix.startswith("relational-faults/") else "native-replayed"
                artifacts = record["artifacts"]
                require(len(artifacts) == 3 and all(name.startswith(prefix) for name in artifacts)
                        and artifacts.get(prefix + "injected.diff") == record["patch"]
                        and artifacts.get(prefix + "result.json") == record["receipt"] and record["status"] == expected_mode,
                        "bundle-sqlite-fault-replay-artifacts", {"contract": contract, "case": case})
                fault_paths[case] = {**record, "files": [self.checked(raw_index / name, sha)[1] for name, sha in artifacts.items()]}
            domains[contract] = {"receipt": origin_, "finite": receipt["finite"], "compared": receipt["compared"],
                                 "rejected": receipt["rejected"], "faults": receipt["faults"], "faultReplay": fault_paths}
        return {"replay": origin, "domains": domains}

    def sqlite_launch(self):
        receipt = self.optional("sqlite-launch/index.json")
        if receipt is None:
            return None
        path, origin, report = receipt
        require(report["distribution"] == self.distribution
                and report["launcherDigest"] == self.tools["run_sqlite_matrix.py"]
                and report["runnerTimeoutSeconds"] == SQLITE_RUNNER_SECONDS
                and re.fullmatch(r"[a-f0-9]{64}", report["sqliteLibraryDigest"]) is not None,
                "bundle-sqlite-launch-identity", origin)
        self.checked(path.parent / "distribution.json", self.distribution)
        omitted = report["omittedDomains"]
        require(omitted == sorted(set(omitted)) and set(omitted) < SQLITE, "bundle-sqlite-launch-domain-set", omitted)
        expected = sqlite_jobs(omitted)
        plan, _ = self.checked(path.parent / "plan.json", report["planDigest"])
        require(json.loads(plan.read_text()) == {
            "candidate": self.candidate, "distribution": self.distribution, "jobs": [list(job) for job in expected],
            "omittedDomains": omitted, "runnerTimeoutSeconds": SQLITE_RUNNER_SECONDS,
        }, "bundle-sqlite-launch-plan", origin)
        journal_file, _ = self.checked(path.parent / "events.jsonl", report["journalDigest"])
        entries = report["executions"]
        require([(entry["job"], entry["runner"], entry["directory"]) for entry in entries] == expected,
                "bundle-sqlite-launch-schedule", origin)
        failed = []
        for ordinal, entry in enumerate(entries):
            name, directory = entry["job"], entry["directory"]
            log = f"{ordinal:02d}-{directory}.log"
            code, outcome = entry["exitCode"], entry["outcome"]
            require(entry["runnerDigest"] == self.tools[entry["runner"]] and entry["log"] == log
                    and (outcome == "exited" and type(code) is int
                         or outcome in {"timeout", "launch-error"} and code is None),
                    "bundle-sqlite-launch-execution", name)
            self.checked(path.parent / log, entry["logDigest"])
            artifact = self.root / directory / "index.json"
            fingerprint = entry["artifactDigest"]
            require(artifact.is_file() == (fingerprint is not None), "bundle-sqlite-launch-artifact", name)
            if fingerprint is not None:
                self.checked(artifact, fingerprint)
                _, _, receipt = self.record(artifact)
                if name in SQLITE:
                    require(receipt["engine"]["libraryDigest"] == report["sqliteLibraryDigest"]
                            and receipt["distribution"] == self.distribution
                            and receipt["contract"] == self.tools[name],
                            "bundle-sqlite-launch-engine-or-contract", name)
            if code != 0 or fingerprint is None:
                failed.append(name)
        journal = []
        for ordinal, ((name, runner, directory), entry) in enumerate(zip(expected, entries)):
            journal.extend(({"id": name, "event": "started", "runner": runner, "directory": directory, "ordinal": ordinal},
                            {"id": name, "event": "finished", "receipt": entry}))
        require(list(execution_events(journal_file)) == journal, "bundle-sqlite-launch-journal", origin)
        expected_status = "incomplete" if failed else "observed-subset" if omitted else "observed-matrix"
        require(report["failed"] == failed and report["status"] == expected_status, "bundle-sqlite-launch-balance", origin)
        self.missing.extend(f"sqlite-launch/{key}" for key in [*failed, *omitted])
        return origin

    def native_launch(self):
        index = self.root / "native-launch/index.json"
        if not index.is_file():
            return None
        path, origin, report = self.record(index)
        workflow = self.tool_root.parents[2] / ".github/workflows/semantics-ir-release-matrix.yml"
        series = self.tool_root / "server-recursive-series-v2.json"
        fixtures = release_fixtures(workflow, self.tool_root / "server-projection-v1.json")
        profiles = {f"{engine}-{release}": (engine, release) for engine, release in fixtures}
        require(report["distribution"] == self.distribution
                and report["workflowDigest"] == digest(workflow)
                and report["recursiveContractDigest"] == CONTRACTS[series.name] == digest(series)
                and report["fixtureRecoveryContractDigest"] == PORT_RECOVERY == self.tools["native-fixture-port-recovery-v1.json"]
                and report["launcherDigest"] == self.tools["run_native_matrix.py"],
                "bundle-native-launch-identity", origin)
        self.checked(path.parent / "distribution.json", self.distribution)
        route_archive = report["workflowRouteAudit"]
        require(route_archive["path"] == "workflow-routes.json", "bundle-native-workflow-route-path", origin)
        routes, _ = self.checked(path.parent / route_archive["path"], route_archive["sha256"])
        contract = json.loads(series.read_text())
        all_jobs = {profile: list(release_jobs(profile, contract)) for profile in fixtures}
        require(json.loads(routes.read_text()) == audit_workflow(workflow, all_jobs), "bundle-native-workflow-route-audit", origin)
        omitted = sorted(profiles.keys() - report["releases"].keys())
        require(not report["releases"].keys() - profiles.keys() and report["omittedProfiles"] == omitted,
                "bundle-native-launch-profile-set", origin)
        failed = []
        for key, record in sorted(report["releases"].items()):
            profile = profiles[key]
            require(record["file"] == f"{key}/index.json", "bundle-native-launch-location", key)
            source, _ = self.checked(path.parent / record["file"], record["sha256"])
            result = json.loads(source.read_text())
            planned = [(domain, runner) for domain, runner, _ in all_jobs[profile]]
            executed = [(entry["domain"], entry["runner"]) for entry in result["executions"]]
            require(result["fixture"] == fixtures[profile] and result["planned"] == [domain for domain, _ in planned]
                    and executed == planned[:len(executed)]
                    and result["setupTimeoutSeconds"] == FIXTURE_STARTUP_SECONDS
                    and result["runnerTimeoutSeconds"] == RUNNER_SECONDS,
                    "bundle-native-launch-schedule", key)
            verify_fixture_recovery(result, key)
            success = (result["failure"] is None and "cleanupFailure" not in result and executed == planned
                       and all(entry["outcome"] == "exited" and entry["exitCode"] == 0 for entry in result["executions"]))
            require(result["status"] == record["status"] == ("observed" if success else "incomplete"),
                    "bundle-native-launch-release-status", key)
            if success:
                require(re.fullmatch(r"sha256:[a-f0-9]{64}", result["imageIdentity"])
                        and result["architecture"] in {"amd64", "arm64"}
                        and 0 <= result["startupSeconds"] <= FIXTURE_STARTUP_SECONDS,
                        "bundle-native-launch-fixture-identity", key)
            else:
                failed.append(key)
            for entry in result["executions"]:
                require(entry["runnerDigest"] == self.tools[entry["runner"]]
                        and entry["log"] == f"{entry['domain']}.log"
                        and (entry["outcome"] == "exited" and type(entry["exitCode"]) is int
                             or entry["outcome"] == "timeout" and entry["exitCode"] is None),
                        "bundle-native-launch-execution", entry)
                self.checked(source.parent / entry["log"], entry["logDigest"])
        expected = "incomplete" if failed else "observed-subset" if omitted else "observed-matrix"
        require(report["failed"] == failed and report["status"] == expected, "bundle-native-launch-balance", origin)
        self.missing.extend(f"native-launch/{key}" for key in [*failed, *omitted])
        return origin

    def native_collection(self):
        item = self.optional("native-replay/index.json")
        if item is None:
            return None
        path, origin, report = item
        records, layout = report["records"], report["layout"]
        require(records.keys() == NATIVE.keys() and layout in {"release", "workflow"}
                and report["collector"] == self.tools["collect_native_replays.py"],
                "bundle-native-collection-identity", origin)
        raw = self.root / "native"
        for domain, result in records.items():
            require(result["records"] == [index.relative_to(raw).as_posix() for index in native_reports(raw, layout, domain)],
                    "bundle-native-collection-records", domain)
            unavailable = native_reports(raw, layout, "recursive_unavailable") if domain == "recursive_series" else ()
            require(result["unavailable"] == [index.relative_to(raw).as_posix() for index in unavailable],
                    "bundle-native-collection-availability", domain)
            self.checked(path.parent / f"{domain}.log", result["log"]["sha256"])
            receipt = result["receipt"]
            if receipt is not None:
                require(receipt["file"] == f"{domain}.json", "bundle-native-collection-receipt", domain)
                self.checked(path.parent / receipt["file"], receipt["sha256"])
        failed = [domain for domain in NATIVE if records[domain]["exitCode"] or records[domain]["receipt"] is None]
        require(failed == report["failed"], "bundle-native-collection-balance", origin)
        self.missing.extend(f"native-replay/{domain}/collector" for domain in failed)
        return origin

    def native(self, profiles):
        domains = {}
        for domain, contract in NATIVE.items():
            item = self.optional(f"native-replay/{domain}.json")
            if item is None:
                continue
            path, origin, report = item
            require(report["contract"] == CONTRACTS[contract], "bundle-unregistered-native-contract", origin)
            if domain == "projection":
                require(report["admissionContract"] == CONTRACTS["server-projection-admission-v1.json"],
                        "bundle-projection-admission-mismatch", origin)
                require(report["publication"] == projection_publication(), "bundle-projection-publication-mismatch", origin)
            registered = {engine: settings["releases"] for engine, settings in profiles.items()}
            single_engine = SINGLE_ENGINE.get(domain)
            if single_engine:
                registered = {single_engine: registered[single_engine]}
            subset = release_subset(domain)
            if subset is not None:
                require(single_engine is not None and set(subset) <= set(registered[single_engine]), "bundle-invalid-native-release-scope", origin)
                registered = {single_engine: subset}
            unavailable = {}
            if domain == "recursive_series":
                series = json.loads((self.tool_root / "server-recursive-series-v2.json").read_text())
                registered = series["profiles"]
                receipts = report["unavailableProfiles"]
                require(receipts.keys() == set(series["unavailableSyntax"]["mysql"]), "bundle-recursive-availability-mismatch")
                for release, receipt in receipts.items():
                    legacy, link, raw = self.record(path.parent / receipt["index"])
                    self.checked(legacy, receipt["indexDigest"])
                    self.checked(legacy.parent / "observations.json", receipt["observationsDigest"])
                    require(raw["toolDigests"] == self.tools and raw["distribution"] == self.distribution
                            and raw["contract"] == report["contract"], "bundle-recursive-availability-identity", release)
                    unavailable[release] = link
            required = {f"{engine} {release}" for engine, releases in registered.items() for release in releases}
            reports = report["releaseReports"]
            missing = sorted(required - reports.keys())
            require(not (reports.keys() - required) and report["missing"] == [list(role.split(" ")) for role in missing],
                    "bundle-native-release-mismatch", origin)
            if missing:
                self.missing.extend(f"{domain}/{release}" for release in missing)
            else:
                require(report["execution"]["compared"] > 0, "bundle-native-no-comparisons", origin)
            if missing:
                require(report["status"] == "incomplete", "bundle-native-status-mismatch", origin)
            else:
                status = f"provisional-native-{domain.replace('_', '-')}-matrix"
                require(report["status"] == status, "bundle-native-status-mismatch", origin)
            releases = {}
            for release, receipt in sorted(reports.items()):
                require(receipt["execution"]["compared"] > 0, "bundle-native-no-comparisons", release)
                if domain == "projection":
                    engine = release.split(" ")[0]
                    releases[release] = self.raw(path.parent / receipt["path"], receipt["indexSha256"],
                                                 f"{engine}.jsonl", receipt["eventsSha256"], report["contract"], self.tools)
                else:
                    releases[release] = self.raw(path.parent / receipt["index"], receipt["indexDigest"],
                                                 "events.jsonl", receipt["eventsDigest"], report["contract"], self.tools)
            domains[domain] = {"replay": origin, "contract": contract, "releases": releases, "execution": report["execution"], "missing": missing}
            if unavailable:
                domains[domain]["unavailableProfiles"] = unavailable
        return domains

    def artifact(self, path, status, distribution=False):
        item = self.optional(path)
        if item is None:
            return None
        path, origin, report = item
        require(report["status"] == status, "bundle-unexpected-status", origin)
        if distribution:
            require(report["distribution"] == self.distribution, "bundle-runtime-mismatch", origin)
            self.checked(path.parent / "distribution.json", self.distribution)
        procedures = report.get("procedures", report.get("tools"))
        if procedures is not None:
            require(procedures and all(self.tools.get(name) == sha256 for name, sha256 in procedures.items()),
                    "bundle-artifact-tool-mismatch", origin)
        return item


def replay_installed_mutation(fault, patch, manifest, checkout, specification):
    package, relative = specification["mutationReplay"]["package"], specification["mutationReplay"]["path"]
    path = f"packages/{package}/{relative}"
    original = (checkout / path).read_bytes()
    changed = original + b"\n"
    before, after = sha256(original).hexdigest(), sha256(changed).hexdigest()
    changed_text = changed.decode().splitlines(keepends=True)
    original_text = original.decode().splitlines(keepends=True)
    expected_patch = "".join(unified_diff(original_text, changed_text, f"a/{relative}", f"b/{relative}"))
    require(patch == expected_patch, "bundle-installed-fault-patch", relative)
    require(before == manifest[path] and after != before and fault == {
        "injection": "installed-mutation.diff",
        "control": "verified",
        "detected": "installed-distribution-mismatch",
        "package": package,
        "path": relative,
        "before": before,
        "after": after,
        "detail": {relative: {"expected": before, "actual": after}},
    }, "bundle-installed-fault-result", relative)


def replay_profile_mutation(evidence, profile_path, record, manifest, checkout, specification):
    paths = record["installedMutation"]
    require(set(paths) == {"installed-mutation.diff", "installed-mutation.json"},
            "bundle-installed-fault-artifacts", profile_path.name)
    files = {name: evidence.checked(profile_path / name, checksum)[0] for name, checksum in paths.items()}
    fault = json.loads(files["installed-mutation.json"].read_text())
    patch = files["installed-mutation.diff"].read_text()
    replay_installed_mutation(fault, patch, manifest, checkout, specification)

    return fault, patch


def replay_staged(evidence, artifact, manifest, inventory, checkout):
    if artifact is None:
        return None
    path, origin, report = artifact
    require(report["contract"] == RELEASE_LAYOUT == evidence.tools[RELEASE_LAYOUT_NAME], "bundle-release-layout-contract", origin)
    require(report.get("workerImage") == IMAGE, "bundle-installed-probe-isolation", origin)
    specification = json.loads((evidence.tool_root / RELEASE_LAYOUT_NAME).read_text())
    recorded, inventory_link = evidence.checked(path.parent / "inventory.json", report["inventory"])
    require(json.loads(recorded.read_text()) == inventory, "bundle-installed-inventory-mismatch", inventory_link)
    expected = runtime_catalog(specification, manifest, inventory)
    catalog, catalog_link = evidence.checked(path.parent / "runtime-catalog.json", report["runtimeCatalog"])
    require(json.loads(catalog.read_text()) == expected, "bundle-installed-role-catalog-mismatch", catalog_link)
    require(report["profiles"].keys() == specification["profiles"].keys(), "bundle-installed-profile-mismatch", origin)
    for profile, record in report["profiles"].items():
        raw, _ = evidence.checked(path.parent / profile / "probe.log", record["probeDigest"])
        roots, roles, controls = verify_probe(raw.read_text(), expected)
        require(roots == record["runtime"] and roles == record["roles"] and controls == record["roleControls"],
                "bundle-installed-probe-replay-mismatch", profile)
        replay_profile_mutation(evidence, path.parent / profile, record, manifest, checkout, specification)
    return artifact


def verify_sqlite_preflight(observed, candidate, manifest):
    require(observed["status"] == "verified" and not observed["failures"], "bundle-sqlite-fault-preflight-failed")
    expected_plan, failures = sqlite_fault_plan(candidate, manifest)
    require(not failures and observed["domains"] == expected_plan, "bundle-sqlite-fault-preflight-source")


def replay_grammar(path, candidate, summary):
    expected = grammar_inventory(candidate)
    require(json.loads(path.read_text()) == expected, "bundle-grammar-inventory-mismatch", str(path))
    require(summary["totalAlternatives"] == expected["totalAlternatives"], "bundle-grammar-total-mismatch")
    return expected


def main():
    cli = argparse.ArgumentParser(description=__doc__)
    cli.add_argument("--candidate", required=True, type=Path)
    cli.add_argument("--evidence-root", required=True, type=Path)
    cli.add_argument("--verifier", required=True, type=Path, help="The archived packages/sql-semantics/assurance directory.")
    cli.add_argument("--previous-verifier", type=Path, help="The reviewer-selected previous verifier archive for an assurance change report.")
    cli.add_argument("--out", required=True, type=Path)
    args = cli.parse_args()
    root, checkout, tools, output = (item.resolve() for item in (args.evidence_root, args.candidate, args.verifier, args.out))
    require(tools == Path(__file__).resolve().parent, "bundle-verifier-location-mismatch", str(tools))
    require(root in output.parents and checkout not in root.parents and checkout != root, "bundle-output-location")
    previous = args.previous_verifier.resolve() if args.previous_verifier else None
    if previous is not None:
        require(previous != checkout and checkout not in previous.parents, "bundle-previous-verifier-inside-candidate")
    identity, manifest = candidate_distribution(checkout)
    dist = root / BASE / "distribution.json"
    verify_distribution(json.loads(dist.read_text()), manifest)
    evidence = Evidence(root, identity, digest(dist), tools)
    _, original, base = evidence.record(f"{BASE}/index.json")
    require(base["status"] == "provisional-initial-domain" and base["distribution"] == evidence.distribution
            and base["contract"] == CONTRACTS["contract-v1.json"], "bundle-initial-domain-mismatch", original)
    require(base["toolDigests"] == {name: sha for name, sha in evidence.tools.items() if not name.endswith(".md")},
            "bundle-initial-tools-mismatch", original)
    grammar_file, grammar = evidence.checked(f"{BASE}/grammar-scope.json", base["grammarScope"]["sha256"])
    grammar_record = replay_grammar(grammar_file, checkout, base["grammarScope"])
    inventory_file, inventory = evidence.checked(f"{BASE}/inventory.json", base["inventoryArtifacts"]["inventory.json"])
    scope_file, scope = evidence.checked(f"{BASE}/scope.json", base["inventoryArtifacts"]["scope.json"])
    roles = json.loads(scope_file.read_text())
    classes = json.loads(inventory_file.read_text())
    check_scope(classes, roles, manifest)
    faults = {}
    from faults import fault_budget
    require(base.get("initialFaultBudget") == fault_budget(), "bundle-initial-fault-budget")
    for receipt in base["faults"]:
        fault = receipt["id"]
        require(fault not in faults, "bundle-repeated-fault", fault)
        path, origin = evidence.checked(Path(BASE) / receipt["record"])
        observed = json.loads(path.read_text())
        require(observed["id"] == fault and observed["control"] == "passed"
                and observed["actualSignal"] == observed["expectedSignal"] == receipt["signal"],
                "bundle-fault-signal-mismatch", fault)
        faults[fault] = {"receipt": origin, "signal": receipt["signal"]}
    require(set(faults) == {f"F{number:02}" for number in range(1, 13)}, "bundle-missing-required-fault")
    profiles = json.loads((tools / "server-projection-v1.json").read_text())["profiles"]
    native_collection = evidence.native_collection()
    native_launch = evidence.native_launch()
    sqlite, native = evidence.sqlite(), evidence.native(profiles)
    sqlite_launch = evidence.sqlite_launch()
    controls = evidence.artifact("assurance-controls/index.json", "verified")
    if controls:
        cases = json.loads((root / "assurance-controls/index.json").read_text())["controls"]
        fixture_controls = [item for item in cases if item["case"] in NATIVE_FIXTURE_CONTROLS]
        require(len(fixture_controls) == len(NATIVE_FIXTURE_CONTROLS)
                and {item["case"] for item in fixture_controls} == NATIVE_FIXTURE_CONTROLS.keys(), "bundle-missing-fixture-controls")
        for item in fixture_controls:
            key, receipt = item["case"], item["observed"]["receipt"]
            require(receipt["status"] == NATIVE_FIXTURE_CONTROLS[key]
                    and item["signal"] == f"native-fixture-{receipt['status']}", "bundle-native-fixture-control-status", key)
            verify_fixture_recovery(receipt, key)
        selected = {item["case"]: item for item in cases if item["case"] in NATIVE_REDUCTION_CONTROLS}
        require(selected.keys() == NATIVE_REDUCTION_CONTROLS.keys(), "bundle-missing-native-reduction-controls")
        for name, item in selected.items():
            folder = root / "assurance-controls" / name
            evidence.checked(folder / "counterexample.json", item["original"])
            path, _ = evidence.checked(folder / "counterexample.reduction.json", item["reduction"])
            receipt = json.loads(path.read_text())
            require(receipt["originalDigest"] == item["original"] and receipt["contract"] == NATIVE_REDUCTION_DIGEST
                    and item["control"] == "passed" and receipt["status"] == item["observed"] == NATIVE_REDUCTION_CONTROLS[name],
                    "bundle-native-reduction-control-mismatch", name)

    preflight_file = root / "sqlite-fault-preflight/index.json"
    preflight = evidence.record(preflight_file) if preflight_file.is_file() else None
    if preflight:
        _, _, observed = preflight
        verify_sqlite_preflight(observed, checkout, manifest)
    guards = evidence.artifact("native-boundaries/index.json", "provisional-native-construction-shape", True)
    staged = replay_staged(evidence, evidence.artifact("release-layout/index.json", "provisional-staged-composer-layout", True), manifest, classes, checkout)
    role_trace = trace(roles, manifest, tools, sqlite, native, guards, staged)
    require(base.get("activeDeclarations") == role_trace["activeDeclarations"], "bundle-constructor-declaration-receipt")
    branch_rules = root / "effective-main-rules.json"
    _, rule_link = evidence.checked(branch_rules) if branch_rules.is_file() else (None, None)
    statuses = [] if rule_link is None else [
        check["context"] for rule in json.loads(branch_rules.read_text()) if rule["type"] == "required_status_checks"
        for check in rule["parameters"]["required_status_checks"]
    ]
    report = {
        "status": "incomplete", "candidate": identity, "distribution": evidence.checked(dist)[1],
        "indexer": digest(Path(__file__)), "verifierFiles": evidence.tools,
        "inventory": inventory, "scope": scope, "grammar": grammar, "initial": original,
        "publicRoots": roles["completeRootTypes"], "constructors": roles["registeredConstructors"],
        "routes": roles["publicationRoutes"], "unconstrainedRoutes": roles["unconstrainedRoutes"], "sqlite": sqlite, "native": native, "nativeCollection": native_collection,
        "nativeLaunch": native_launch, "sqliteLaunch": sqlite_launch,
        "sqliteFaultPreflight": preflight[1] if preflight else None,
        "nativeConstructorAudit": guards[1] if guards else None, "stagedDistribution": staged[1] if staged else None,
        "requiredFaults": faults, "faultAndIntegrityControls": controls[1] if controls else None, "formalProof": base["proof"],
        "observedBranchRules": rule_link, "requiredBranchStatuses": statuses,
        "missingEvidence": evidence.missing, "humanReview": "pending", "acceptanceProtection": "unverified",
        "requiredScope": base["scope"], "unresolved": base["open"],
        "obligations": {key: {"scopeStatus": "open", "availableEvidence": value} for key, value in LIMITS.items()},
    }
    verify_candidate(checkout, identity, manifest)
    output.mkdir(parents=True, exist_ok=False)
    if previous is not None:
        revision = output / "assurance-changes.json"
        write_json(revision, review(previous, tools))
        report["assuranceChanges"] = evidence.checked(revision)[1]
    else:
        report["assuranceChanges"] = None
    report["valueClosure"] = report["scalarDispatch"] = report["nativeBoundaryFaultReplay"] = report["mysqlSignedStorage"] = None
    if guards:
        audit = output / "native-boundary-fault-replay.json"
        write_json(audit, replay_native_faults(checkout, guards[0].parent, guards[2], manifest))
        report["nativeBoundaryFaultReplay"] = evidence.checked(audit)[1]
        signed = guards[2]["mysqlSignedStorage"]
        report["mysqlSignedStorage"] = evidence.checked(guards[0].parent / signed["path"], signed["sha256"])[1]
        sources = {dialect: result["healthy"] for dialect, result in guards[2]["results"].items()}
        graph = certificate(manifest, roles["registeredConstructors"], sources)
        reference = guards[2]["valueClosure"]
        recorded, _ = evidence.checked(guards[0].parent / reference["path"], reference["sha256"])
        require(json.loads(recorded.read_text()) == {
            "candidate": identity, "distribution": evidence.distribution, "certificate": graph,
        }, "bundle-value-closure-mismatch", str(recorded))
        proof = dispatch_certificate(sources, graph)
        reference = guards[2]["scalarDispatch"]
        recorded, link = evidence.checked(guards[0].parent / reference["path"], reference["sha256"])
        require(json.loads(recorded.read_text()) == {
            "candidate": identity, "distribution": evidence.distribution, "certificate": proof,
        }, "bundle-scalar-dispatch-mismatch", str(recorded))
        report["scalarDispatch"] = link
        graph["candidate"] = identity
        graph["distribution"] = report["distribution"]
        graph["sourceAudit"] = guards[1]
        closure = output / "value-closure.json"
        write_json(closure, graph)
        report["valueClosure"] = evidence.checked(closure)[1]
    write_json(output / "constructors.json", role_trace)
    report["constructorTrace"] = evidence.checked(output / "constructors.json")[1]
    write_json(output / "index.json", report)
    lines = [
        "# SemanticsIR evidence", "", f"Tested candidate `{identity}`.", "",
        "Acceptance is incomplete. The registered evidence has a bounded language scope. Human review and repository protection require independent confirmation.",
        "", f"- {len(report['publicRoots'])} published roots and {len(report['constructors'])} registered constructors in the measured inventory.",
        f"- {base['grammarScope']['totalAlternatives']} grammar alternatives in the fixed syntax inventory.",
        f"- {grammar_record['alternativesWithRelatedDomain']} alternatives linked to one or more bounded domain records.",
        f"- {len(sqlite['domains']) if sqlite else 0} replayed SQLite domains.",
        f"- {sum(not record['missing'] for record in native.values())} native domains with every required release replayed.",
        f"- {len(evidence.missing)} missing evidence records or release entries.",
        "", "The [machine-readable index](index.json) links to the source inventory, replay summaries, original requests and recorded database observations.",
        "The [constructor trace](constructors.json) identifies contract fields, required obligations, related domain receipts and audited methods for each public role.",
    ]
    if report["valueClosure"] is not None:
        lines.append("The [checked value graph](value-closure.json) records the static closure of retained fields under the listed PHP and source-audit premises.")
        lines.append("The native constructor audit also links each checked scalar dispatch to the field types of its listed callers.")
        lines.append("The [native boundary fault replay](native-boundary-fault-replay.json) links all required source patches and raw rejections.")
        lines.append("The native constructor audit also contains a symbolic derivation for every ordinary MySQL signed storage interval under its listed premises.")
    if previous is not None:
        lines.append("The [assurance change report](assurance-changes.json) records differences against the previous selected verifier.")
    (output / "summary.md").write_text("\n".join(lines) + "\n")


if __name__ == "__main__":
    main()
