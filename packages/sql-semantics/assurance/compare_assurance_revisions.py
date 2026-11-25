"""Record assurance changes between two reviewer-selected verifier archives."""

import argparse
import ast
import hashlib
import json
import re
from pathlib import Path

from run import digest, require, write_json

CONTRACT_FAMILY = re.compile(r"^(.+)-v[0-9]+\.json$")
EVIDENCE_CONTRACTS = {
    "mysql-native-truth-evidence-v1.json": "04ef1d090ad68a3d9cf44ee4a4ec521bb5b3a9073c31368c2938811fbe49b576",
    "mysql-signed-case-evidence-v1.json": "db3e34e6cbab5a3225d9cf31e5dcd359486a1f621bbc9647404c1ef55eb4e702",
    "mysql-signed-comparison-widths-evidence-v2.json": "c20091712f5939bbd445b4eca72a7070e03dcc758489c8657c5b6896803b4ba7",
    "mysql-signed-membership-evidence-v1.json": "7690a15d3d4d3520375e08b4275a04693011c92a10b2cb82baeca7e4cd896bd4",
    "postgres-signed-membership-evidence-v1.json": "afacf1e6c7706ceede0c99a6cdedf537085715d2a1358d2095b17f3425ddf36a",
}
TOOL_FORMATS = {".php", ".py", ".json", ".md", ".c"}
SUPPORT_FILES = (
    ".github/workflows/semantics-ir-assurance.yml",
    ".github/workflows/semantics-ir-release-matrix.yml",
    ".github/workflows/split.yml",
    "packages/sql-semantics-sqlite/composer.lock",
)
SCOPE_FIELDS = ("scope", "approval", "profile", "profiles", "release", "mode", "open", "trust", "externalInterpretation",
                "conditions", "invariants", "meaning", "sql", "observations", "observation", "finite",
                "exploration", "negative", "faults", "dialectFaults", "allowedExclusions", "obligations", "formalProof",
                "composition", "rules", "predecessorCheck", "changeFromV1", "initialExecutableDomain",
                "arrayRoutes", "unconstrainedReturns", "backedEnums", "single", "batch", "context", "surface", "forbidden",
                "runtimeRoles", "enumRoutes", "recipe", "acceptance", "reference", "negativeCount", "legacyFaults",
                "legacyReleases", "sourceFaults", "boundaryInputs", "boundaryWitnesses", "seedPlacements",
                "seedCompositions", "calibration")


def literal_value(path, variable):
    matches = [
        node.value for node in ast.parse(path.read_text()).body
        if isinstance(node, ast.Assign)
        and any(isinstance(target, ast.Name) and target.id == variable for target in node.targets)
    ]
    require(len(matches) == 1, "revision-registry-definition", {"file": path.name, "name": variable})
    return ast.literal_eval(matches[0])


def literal_registry(path, variable):
    value = literal_value(path, variable)
    require(type(value) is dict and value and all(type(key) is str and type(entry) is str for key, entry in value.items()),
            "revision-registry-shape", variable)
    return value


def declared_contracts(tools):
    registry = literal_registry(tools / "scope.py", "CONTRACTS")
    evidence = literal_registry(tools / "compare_assurance_revisions.py", "EVIDENCE_CONTRACTS")
    require(not (registry.keys() & evidence.keys()), "revision-duplicate-contract-roles")
    records = {}
    for family, script, symbol in (
        ("publication", "scope.py", "PUBLICATION"),
        ("release-layout", "verify_release_layout.py", "CONTRACT"),
    ):
        fingerprint = literal_value(tools / script, symbol)
        matches = [path for path in tools.glob(f"{family}-v*.json")
                   if path.is_file() and not path.is_symlink() and digest(path) == fingerprint]
        require(len(matches) == 1, "revision-audit-contract-selection", {"family": family, "sha256": fingerprint})
        registry[matches[0].name] = fingerprint
    for name, claimed in sorted((registry | evidence).items()):
        path = tools / name
        require(Path(name).name == name and path.is_file() and not path.is_symlink(), "revision-missing-contract", name)
        fingerprint = digest(path)
        require(fingerprint == claimed, "revision-contract-digest", name)
        record = json.loads(path.read_text())
        require(type(record) is dict and type(record.get("revision")) is str,
                "revision-contract-shape", name)
        if name in evidence:
            reference = record.get("reference")
            require(type(reference) is dict and reference.keys() == {"file", "sha256"}
                    and type(reference["file"]) is str and reference["file"] in registry
                    and registry[reference["file"]] == reference["sha256"] and "constructors" not in record,
                    "revision-evidence-contract-reference", name)
        family = CONTRACT_FAMILY.fullmatch(name)
        require(family is not None, "revision-contract-name", name)
        records.setdefault(family[1], {})[name] = {"sha256": fingerprint, "data": record}
    return records


def snapshot(tools):
    require(tools.is_dir(), "revision-tool-directory", str(tools))
    contracts = declared_contracts(tools)
    native = literal_registry(tools / "native_schedule.py", "NATIVE")
    known = {name for group in contracts.values() for name in group}
    require(set(native.values()) <= known, "revision-unregistered-native-contract", sorted(set(native.values()) - known))
    archive = tools.parents[2]
    sources = {path.name: digest(path) for path in sorted(tools.iterdir())
               if path.suffix in TOOL_FORMATS and path.is_file() and not path.is_symlink()}
    config = {name: digest(archive / name) if (archive / name).is_file() else None for name in SUPPORT_FILES}
    identity = hashlib.sha256(json.dumps({"tools": sources, "support": config}, sort_keys=True, separators=(",", ":")).encode()).hexdigest()
    return {"contracts": contracts, "native": native, "tools": sources, "support": config, "fingerprint": identity}


def changed_values(before, after, present=True):
    return {"before": before, "after": after, **({"presenceChanged": True} if not present else {})}


def differences(before, after):
    return {key: changed_values(before.get(key), after.get(key), key in before and key in after)
            for key in sorted(before.keys() | after.keys()) if before.get(key) != after.get(key) or (key in before) != (key in after)}


def semantic_fields(before, after):
    root_fields = (before.keys() | after.keys()) & set(SCOPE_FIELDS)
    selections = {field: changed_values(before.get(field), after.get(field), field in before and field in after)
                  for field in sorted(root_fields)
                  if before.get(field) != after.get(field) or (field in before) != (field in after)}
    constructor_delta = differences(before.get("constructors", {}), after.get("constructors", {}))
    if constructor_delta:
        selections["constructors"] = constructor_delta
    return selections


def notable_changes(family, before, after):
    weakened = []
    old_exploration = before.get("exploration", {})
    new_exploration = after.get("exploration", {})
    if isinstance(old_exploration, dict) and isinstance(new_exploration, dict):
        for metric in ("casesPerSeed", "timeoutSeconds", "timeoutSecondsPerRelease"):
            old, new = old_exploration.get(metric), new_exploration.get(metric)
            if type(old) is int and (type(new) is not int or old > new):
                weakened.append({"family": family, "field": "exploration/" + metric, "before": old, "after": new})
        old_seeds, new_seeds = old_exploration.get("seeds"), new_exploration.get("seeds")
        if isinstance(old_seeds, list) and isinstance(new_seeds, list) and len(new_seeds) < len(old_seeds):
            weakened.append({"family": family, "field": "exploration/seeds", "before": old_seeds, "after": new_seeds})
        first = old_exploration.get("allowedExclusions")
        last = new_exploration.get("allowedExclusions")
        if isinstance(first, list) and isinstance(last, list) and any(entry not in first for entry in last):
            weakened.append({"family": family, "field": "exploration/allowedExclusions", "before": first, "after": last})
    for field in ("faults", "negative", "legacyFaults", "sourceFaults", "boundaryInputs", "boundaryWitnesses",
                  "seedPlacements", "seedCompositions", "observations"):
        old, new = before.get(field), after.get(field)
        if isinstance(old, list) and (not isinstance(new, list) or any(item not in new for item in old)):
            weakened.append({"family": family, "field": field, "before": old, "after": new})
    old, new = before.get("negativeCount"), after.get("negativeCount")
    if type(old) is int and (type(new) is not int or old > new):
        weakened.append({"family": family, "field": "negativeCount", "before": old, "after": new})
    return weakened


def review(previous, current):
    baseline, candidate = snapshot(previous), snapshot(current)
    changed, flagged = {}, []
    first, last = baseline["contracts"], candidate["contracts"]
    for family in sorted(first.keys() | last.keys()):
        old, new = first.get(family, {}), last.get(family, {})
        old_hashes = {name: item["sha256"] for name, item in old.items()}
        new_hashes = {name: item["sha256"] for name, item in new.items()}
        releases = differences(old_hashes, new_hashes)
        if not releases:
            continue
        details = {"records": releases}
        if len(old) <= 1 and len(new) <= 1:
            left = next(iter(old.values()), {"data": {}})["data"]
            right = next(iter(new.values()), {"data": {}})["data"]
            details["observedChanges"] = semantic_fields(left, right)
            if old and new:
                flagged.extend(notable_changes(family, left, right))
        if old and not new:
            flagged.append({"family": family, "field": "contract", "before": sorted(old), "after": None})
        changed[family] = details
    native = differences(baseline["native"], candidate["native"])
    for name in sorted(baseline["native"].keys() - candidate["native"].keys()):
        flagged.append({"field": "native/" + name, **native[name]})
    tools = differences(baseline["tools"], candidate["tools"])
    support = differences(baseline["support"], candidate["support"])
    required = "required-grammars.json"
    if required in tools:
        flagged.append({"field": required, **tools[required]})
    return {
        "status": "review-required" if changed or native or tools or support else "identical-measured-inputs",
        "previousFingerprint": baseline["fingerprint"], "currentFingerprint": candidate["fingerprint"],
        "humanApproval": "unrecorded",
        "comparator": digest(Path(__file__)),
        "contractChanges": changed, "nativeScheduleChanges": native, "verifierFileChanges": tools,
        "supportChanges": support, "observedPotentialReductions": flagged,
        "supportUnavailable": {label: [name for name, identity in branch["support"].items() if identity is None]
                               for label, branch in (("previous", baseline), ("current", candidate))},
        "comparison": "Exact artifact digests and selected contract fields. All changes remain subject to independent review.",
    }


def main():
    cli = argparse.ArgumentParser(description=__doc__)
    cli.add_argument("--previous-verifier", required=True, type=Path)
    cli.add_argument("--current-verifier", required=True, type=Path)
    cli.add_argument("--out", required=True, type=Path)
    args = cli.parse_args()
    before, after, output = (path.resolve() for path in (args.previous_verifier, args.current_verifier, args.out))
    require(not output.exists() and all(base not in output.parents for base in (before, after)), "revision-output-location")
    write_json(output, review(before, after))
    print(output)


if __name__ == "__main__":
    main()
