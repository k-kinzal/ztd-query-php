"""Replay a host Composer diagnostic and confirm the isolated acceptance boundary."""

import argparse
import json
from pathlib import Path

from index_evidence import Evidence, replay_installed_mutation, replay_profile_mutation, replay_staged
from run import Failure, TOOL, candidate_distribution, digest, require, verify_distribution, write_json
from verify_release_layout import CONTRACT, CONTRACT_NAME, checked_publication_scope, runtime_catalog, verify_probe


def expect_failure(call, expected):
    try:
        call()
    except Failure as failure:
        require(failure.signal == expected, "release-diagnostic-control-signal", failure.detail)
        return failure.signal
    raise Failure("release-diagnostic-control-accepted", expected)


def relocated_catalog(base, measured):
    require(measured.keys() == base.keys() and base, "release-diagnostic-catalog-role-set")
    example = next(iter(base))
    stage = next((path for path in Path(measured[example]["path"]).parents
                  if path.name.startswith("ztd-ir-package-")), None)
    require(stage is not None and stage.is_absolute(), "release-diagnostic-stage-path")
    expected = {role: {**entry, "path": str(stage / Path(entry["path"]).relative_to("/stage"))}
                for role, entry in base.items()}
    require(measured == expected, "release-diagnostic-catalog-relocation")

    return expected


def replay(checkout, directory):
    commit, manifest = candidate_distribution(checkout)
    source = directory / "distribution.json"
    verify_distribution(json.loads(source.read_text()), manifest)
    evidence = Evidence(directory, commit, digest(source), TOOL)
    artifact = evidence.artifact("index.json", "provisional-local-staged-composer-layout", True)
    require(artifact is not None, "release-diagnostic-index-missing")
    path, origin, index = artifact
    require(index["contract"] == CONTRACT == evidence.tools[CONTRACT_NAME] and index.get("workerImage") is None
            and index.get("localPhp"), "release-diagnostic-identity")
    specification = json.loads((TOOL / CONTRACT_NAME).read_text())
    inventory, _ = evidence.checked("inventory.json", index["inventory"])
    catalog_path, _ = evidence.checked("runtime-catalog.json", index["runtimeCatalog"])
    inventory = json.loads(inventory.read_text())
    publication, _ = evidence.checked("publication-scope.json", index["publicationScope"])
    require(json.loads(publication.read_text()) == checked_publication_scope(inventory),
            "release-diagnostic-publication-scope-mismatch")
    catalog = runtime_catalog(specification, manifest, inventory)
    require(json.loads(catalog_path.read_text()) == catalog, "release-diagnostic-catalog-mismatch")
    require(index["profiles"].keys() == specification["profiles"].keys(), "release-diagnostic-profile-mismatch")
    profiles = {}
    for profile, record in index["profiles"].items():
        profile_path = path.parent / profile
        installed, _ = evidence.checked(profile_path / "local-runtime-catalog.json", record["localRuntimeCatalog"])
        raw, probe_link = evidence.checked(profile_path / "probe.log", record["probeDigest"])
        roles = relocated_catalog(catalog, json.loads(installed.read_text()))
        observed, concrete, controls = verify_probe(raw.read_text(), roles)
        require((observed, concrete, controls) == (record["runtime"], record["roles"], record["roleControls"]),
                "release-diagnostic-probe-content", profile)
        fault, patch = replay_profile_mutation(evidence, profile_path, record, manifest, checkout, specification)
        bad_receipt = {**fault, "after": "0" * 64}
        profiles[profile] = {
            "publication": len(observed), "roles": len(concrete), "probe": probe_link,
            "installedMutation": fault["detected"],
            "alteredReceipt": expect_failure(
                lambda: replay_installed_mutation(bad_receipt, patch, manifest, checkout, specification),
                "bundle-installed-fault-result",
            ),
            "alteredPatch": expect_failure(
                lambda: replay_installed_mutation(fault, patch + "\n", manifest, checkout, specification),
                "bundle-installed-fault-patch",
            ),
        }

    gate = expect_failure(lambda: replay_staged(evidence, artifact, manifest, inventory, checkout),
                          "bundle-installed-probe-isolation")
    return {"candidate": commit, "status": "provisional-host-diagnostic-replay", "source": origin,
            "contract": CONTRACT, "php": index["localPhp"], "profiles": profiles, "acceptanceGate": gate,
            "replayer": digest(Path(__file__))}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate", required=True, type=Path)
    parser.add_argument("--evidence", required=True, type=Path)
    parser.add_argument("--out", required=True, type=Path)
    arguments = parser.parse_args()
    checkout, directory, output = (path.resolve() for path in (arguments.candidate, arguments.evidence, arguments.out))
    require(directory != checkout and checkout not in directory.parents and directory in output.parents
            and not output.exists(), "release-diagnostic-output-location")
    write_json(output, replay(checkout, directory))


if __name__ == "__main__":
    main()
