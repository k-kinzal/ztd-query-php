"""Index replayed evidence and identify outstanding acceptance requirements."""

import argparse
import json
from pathlib import Path

from constructor_trace import check_scope, trace
from scope import CONTRACTS
from run import candidate_distribution, digest, require, verify_candidate, verify_distribution, write_json


NATIVE = {
    "projection": "server-projection-v1.json",
    "delete": "server-delete-v2.json",
    "join": "server-join-v1.json",
    "result": "server-result-v1.json",
    "insert": "server-insert-v1.json",
    "update": "server-update-v2.json",
    "transaction": "server-transaction-v1.json",
    "drop_table": "server-drop-table-v1.json",
    "create_table": "server-create-table-v1.json",
    "parameter": "server-parameter-v1.json",
    "compound": "server-compound-v2.json",
    "local_projection": "server-local-projection-v4.json",
    "truth": "server-truth-v2.json",
    "recursive_series": "server-recursive-series-v2.json",
    "derived_join": "server-derived-join-v2.json",
    "grouped": "server-grouped-v3.json",
    "insert_query": "server-insert-query-v2.json",
    "wildcard": "wildcard-projection-v2.json",
}
SQLITE = {
    "sqlite-case-v1.json", "sqlite-cast-v1.json", "sqlite-defaults-v1.json", "sqlite-delete-returning-v1.json",
    "contract-v1.json", "sqlite-compound-v1.json", "sqlite-create-table-v1.json", "sqlite-delete-v1.json",
    "sqlite-drop-table-v1.json", "sqlite-insert-v1.json", "sqlite-local-source-v1.json",
    "sqlite-recursive-source-v1.json", "sqlite-relational-v1.json", "sqlite-result-v1.json", "sqlite-scalars-v1.json",
    "wildcard-projection-v2.json", "sqlite-grouped-v1.json", "sqlite-insert-query-v1.json",
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
    "Q01": "Checked constructor shapes and independent negative cases",
    "Q02": "Replayed positive requests and observed finite identities",
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
        require(report["status"] == "provisional" and set(report["domains"]) == SQLITE,
                "bundle-sqlite-domain-mismatch", origin)
        domains = {}
        tools = {name: sha for name, sha in self.tools.items() if not name.endswith(".md")}
        for contract, receipt in sorted(report["domains"].items()):
            origin_ = self.raw(path.parent / receipt["index"], receipt["indexDigest"],
                               "events.jsonl", receipt["eventsDigest"], self.tools[contract], tools)
            require(receipt["contract"] == self.tools[contract] and receipt["compared"] > 0,
                    "bundle-sqlite-contract-mismatch", origin_)
            domains[contract] = {"receipt": origin_, "finite": receipt["finite"], "compared": receipt["compared"],
                                 "rejected": receipt["rejected"], "faults": receipt["faults"]}
        return {"replay": origin, "domains": domains}

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
            registered = {engine: settings["releases"] for engine, settings in profiles.items()}
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


def main():
    cli = argparse.ArgumentParser(description=__doc__)
    cli.add_argument("--candidate", required=True, type=Path)
    cli.add_argument("--evidence-root", required=True, type=Path)
    cli.add_argument("--verifier", required=True, type=Path, help="The archived packages/sql-semantics/assurance directory.")
    cli.add_argument("--out", required=True, type=Path)
    args = cli.parse_args()
    root, checkout, tools, output = (item.resolve() for item in (args.evidence_root, args.candidate, args.verifier, args.out))
    require(tools == Path(__file__).resolve().parent, "bundle-verifier-location-mismatch", str(tools))
    require(root in output.parents and checkout not in root.parents and checkout != root, "bundle-output-location")
    identity, manifest = candidate_distribution(checkout)
    dist = root / BASE / "distribution.json"
    verify_distribution(json.loads(dist.read_text()), manifest)
    evidence = Evidence(root, identity, digest(dist), tools)
    _, original, base = evidence.record(f"{BASE}/index.json")
    require(base["status"] == "provisional-initial-domain" and base["distribution"] == evidence.distribution
            and base["contract"] == CONTRACTS["contract-v1.json"], "bundle-initial-domain-mismatch", original)
    require(base["toolDigests"] == {name: sha for name, sha in evidence.tools.items() if not name.endswith(".md")},
            "bundle-initial-tools-mismatch", original)
    _, grammar = evidence.checked(f"{BASE}/grammar-scope.json", base["grammarScope"]["sha256"])
    inventory_file, inventory = evidence.checked(f"{BASE}/inventory.json", base["inventoryArtifacts"]["inventory.json"])
    scope_file, scope = evidence.checked(f"{BASE}/scope.json", base["inventoryArtifacts"]["scope.json"])
    roles = json.loads(scope_file.read_text())
    check_scope(json.loads(inventory_file.read_text()), roles, manifest)
    faults = {}
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
    sqlite, native = evidence.sqlite(), evidence.native(profiles)
    controls = evidence.artifact("assurance-controls/index.json", "verified")
    guards = evidence.artifact("native-boundaries/index.json", "provisional-native-construction-shape", True)
    staged = evidence.artifact("release-layout/index.json", "provisional-staged-composer-layout", True)
    role_trace = trace(roles, manifest, tools, sqlite, native, guards, staged)
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
        "routes": roles["publicationRoutes"], "sqlite": sqlite, "native": native,
        "nativeConstructorAudit": guards[1] if guards else None, "stagedDistribution": staged[1] if staged else None,
        "requiredFaults": faults, "faultAndIntegrityControls": controls[1] if controls else None, "formalProof": base["proof"],
        "observedBranchRules": rule_link, "requiredBranchStatuses": statuses,
        "missingEvidence": evidence.missing, "humanReview": "pending", "acceptanceProtection": "unverified",
        "requiredScope": base["scope"], "unresolved": base["open"],
        "obligations": {key: {"scopeStatus": "open", "availableEvidence": value} for key, value in LIMITS.items()},
    }
    verify_candidate(checkout, identity, manifest)
    output.mkdir(parents=True, exist_ok=False)
    write_json(output / "constructors.json", role_trace)
    report["constructorTrace"] = evidence.checked(output / "constructors.json")[1]
    write_json(output / "index.json", report)
    lines = [
        "# SemanticsIR evidence", "", f"Tested candidate `{identity}`.", "",
        "Acceptance is incomplete. The registered evidence has a bounded language scope. Human review and repository protection require independent confirmation.",
        "", f"- {len(report['publicRoots'])} published roots and {len(report['constructors'])} registered constructors in the measured inventory.",
        f"- {base['grammarScope']['totalAlternatives']} grammar alternatives in the fixed syntax inventory.",
        f"- {len(sqlite['domains']) if sqlite else 0} replayed SQLite domains.",
        f"- {sum(not record['missing'] for record in native.values())} native domains with every required release replayed.",
        f"- {len(evidence.missing)} missing evidence records or release entries.",
        "", "The [machine-readable index](index.json) links to the source inventory, replay summaries, original requests and recorded database observations.",
        "The [constructor trace](constructors.json) identifies contract fields, required obligations, related domain receipts and audited methods for each public role.",
    ]
    (output / "summary.md").write_text("\n".join(lines) + "\n")


if __name__ == "__main__":
    main()
