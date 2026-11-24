"""Link the signed-decimal proof receipt to its distribution and controls."""

import json

from formal_reindex_evidence import command
from run import TOOL, digest, require
from verify_postgres_sequence_order_proof import (
    CHECKERS, CONTRACT, ORDER, PROOF_CONTROLS, RECEIPT_STATUS, fixed_contract, link_php,
)


def index_sequence_order_proof(evidence, manifest):
    record = evidence.optional("postgres-sequence-order-proof/index.json")
    if record is None:
        return None
    entry, origin, index = record
    folder = entry.parent
    contract, config = fixed_contract()
    proof = index["proof"]
    require(index["status"] == RECEIPT_STATUS and index["contract"] == CONTRACT
            and index["distribution"] == evidence.distribution and proof["toolchain"] == contract["toolchain"]
            and proof["allowedAxioms"] == config["permitted_axioms"]
            and proof["theorems"] == config["theorem_names"] and proof["checkedKernels"] == list(CHECKERS),
            "bundle-postgres-sequence-proof-identity", origin)
    for key, name in (("shape", "shape.php"), ("checker", "verify_postgres_sequence_order_proof.py"),
                      ("sharedRunner", "verify_postgres_reindex_proof.py")):
        require(index["tools"][key] == digest(TOOL / name), "bundle-postgres-sequence-proof-tool", key)
    evidence.checked(folder / "distribution.json", evidence.distribution)
    php, shape_link = evidence.checked(folder / "php-shape.json", index["phpShape"])
    linkage = link_php(contract, manifest, json.loads(php.read_text()))
    require(linkage == index["phpLinkage"], "bundle-postgres-sequence-proof-linkage", shape_link)

    proof_folder = folder / "proof"
    for name, checksum in contract["contractArtifacts"].items():
        evidence.checked(proof_folder / name, checksum)
    evidence.checked(proof_folder / "export-Challenge.ndjson", proof["challenge"])
    _, exported = evidence.checked(proof_folder / "export-Solution.ndjson", proof["proof"])
    compared = command(evidence, proof_folder, proof["comparison"], 0)
    require(all(f"{kernel} kernel accepts the solution" in compared for kernel in CHECKERS),
            "bundle-postgres-sequence-proof-kernels", exported)
    require(proof["controls"].keys() == set(PROOF_CONTROLS), "bundle-postgres-sequence-proof-controls")
    for name, result in proof["controls"].items():
        changed = folder / name
        require(result["signal"] == contract["controls"][name]
                and result["original"] == contract["contractArtifacts"]["Solution.lean"]
                and result["mutant"] != result["original"],
                "bundle-postgres-sequence-proof-control-identity", name)
        evidence.checked(changed / "Solution.lean", result["mutant"])
        evidence.checked(changed / "injected.diff", result["patch"])
        require(result["signal"] in command(evidence, changed, result["comparison"], 1),
                "bundle-postgres-sequence-proof-control-signal", name)
    changed = folder / "reversed-negative-order"
    receipt, _ = evidence.checked(changed / "result.json", index["phpControl"])
    change = json.loads(receipt.read_text())
    binding = linkage[ORDER]
    require(change["message"] == change["expected"] == contract["controls"][changed.name]
            and change["original"] == binding["sourceDigest"] and change["mutant"] != change["original"],
            "bundle-postgres-sequence-proof-php-control", origin)
    evidence.checked(changed / "injected.diff", change["patch"])
    evidence.checked(changed / binding["source"].removeprefix("packages/"), change["mutant"])
    return {"receipt": origin, "export": exported, "shape": shape_link, "theorems": proof["theorems"],
            "allowedAxioms": proof["allowedAxioms"], "methods": sorted(linkage)}
