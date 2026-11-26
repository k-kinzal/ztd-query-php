"""Index the separately kernel-checked rebuild spelling proof and its PHP linkage."""

import json

from postgres_reindex_lexical_link import PHP_SOURCE, verify_spelling
from run import TOOL, digest, require
from verify_postgres_reindex_proof import CHECKERS, CONTRACT_DIGEST, FORMAL, PROOF_CONTROLS, PROOF_SOURCES, load_contract


def command(evidence, directory, identity, expected_exit):
    receipt, origin = evidence.checked(directory / "compare.command.json", identity)
    report = json.loads(receipt.read_text())
    require(report["exit"] == expected_exit, "bundle-postgres-proof-comparator-exit", origin)
    output, _ = evidence.checked(directory / "compare.out", report["stdout"])
    errors, _ = evidence.checked(directory / "compare.err", report["stderr"])
    transcript = output.read_text() + errors.read_text()
    require("Sandbox disabled" not in transcript, "bundle-postgres-proof-unisolated-comparator", origin)
    return transcript


def index_lexical_proof(evidence, manifest):
    record = evidence.optional("postgres-reindex-proof/index.json")
    if record is None:
        return None
    file, origin, report = record
    folder = file.parent
    proof = report["proof"]
    contract, config = load_contract()
    require(report["status"] == "provisional-machine-checked-postgres-reindex-lexical-proof"
            and report["contract"] == CONTRACT_DIGEST and report["distribution"] == evidence.distribution
            and proof["allowedAxioms"] == [] and proof["checkedKernels"] == list(CHECKERS)
            and proof["theorems"] == config["theorem_names"] and proof["toolchain"] == contract["toolchain"],
            "bundle-postgres-proof-identity", origin)
    for field, tool in (("checker", "verify_postgres_reindex_proof.py"), ("shape", "shape.php"),
                        ("linkage", "postgres_reindex_lexical_link.py")):
        require(report["tools"][field] == digest(TOOL / tool), "bundle-postgres-proof-tool", tool)

    evidence.checked(folder / "distribution.json", evidence.distribution)
    shapes, shape_link = evidence.checked(folder / "php-shape.json", report["phpShape"])
    linkage = verify_spelling(manifest, json.loads(shapes.read_text()), FORMAL / "ReindexSpec.lean")
    require(linkage == report["phpLinkage"], "bundle-postgres-proof-php", shape_link)

    proof_dir = folder / "proof"
    for name, checksum in contract["contractArtifacts"].items():
        evidence.checked(proof_dir / name, checksum)
    for name, checksum in PROOF_SOURCES.items():
        evidence.checked(proof_dir / name, checksum)
    evidence.checked(proof_dir / f"export-{config['challenge_module']}.ndjson", proof["challenge"])
    evidence.checked(folder / "base-challenge/export-Challenge.ndjson", proof["baseChallenge"])
    _, proof_link = evidence.checked(proof_dir / f"export-{config['solution_module']}.ndjson", proof["proof"])
    measured = command(evidence, proof_dir, proof["comparison"], 0)
    require(all(f"{kernel} kernel accepts the solution" in measured for kernel in CHECKERS),
            "bundle-postgres-proof-kernel-receipt", proof_link)

    require(proof["controls"].keys() == PROOF_CONTROLS.keys(), "bundle-postgres-proof-control-scope", origin)
    for name, (file, signal) in PROOF_CONTROLS.items():
        result = proof["controls"][name]
        altered = folder / name
        require(result["file"] == file and result["original"] == PROOF_SOURCES[file] and result["mutant"] != result["original"]
                and result["expected"] == result["observed"] == signal,
                "bundle-postgres-proof-control-identity", name)
        evidence.checked(altered / file, result["mutant"])
        evidence.checked(altered / "injected.diff", result["patch"])
        require(signal in command(evidence, altered, result["diagnostics"], 1), "bundle-postgres-proof-control-receipt", name)
    delimiter, _ = evidence.checked(folder / "php-delimiter/result.json", report["phpControl"])
    mutation = json.loads(delimiter.read_text())
    expected = "Native boundary differs in Schema/TableName::quoted"
    source = PHP_SOURCE + "/Schema/TableName.php"
    require(mutation["expected"] == expected and mutation["observed"]["message"] == expected
            and mutation["original"] == manifest[source] and mutation["mutant"] != mutation["original"],
            "bundle-postgres-proof-distribution-control", origin)
    evidence.checked(folder / "php-delimiter/injected.diff", mutation["patch"])
    evidence.checked(folder / "php-delimiter" / source.removeprefix("packages/"), mutation["mutant"])
    return {"receipt": origin, "export": proof_link, "shape": shape_link, "theorems": proof["theorems"],
            "methods": sorted(linkage["methods"])}
