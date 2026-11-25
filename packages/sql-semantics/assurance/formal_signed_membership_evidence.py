"""Link signed membership proof receipts to the selected distribution."""

import json

from formal_reindex_evidence import command
from run import TOOL, digest, require
from verify_postgres_signed_membership_proof import CHECKERS, CONTRACT, STATUS, THEOREMS, fixed_contract, link_php


def index_membership_proof(evidence, manifest):
    record = evidence.optional("postgres-signed-membership-proof/index.json")
    if record is None:
        return None
    path, origin, index = record
    fixed, config = fixed_contract()
    folder = path.parent
    proof = index["proof"]
    require(index["status"] == STATUS and index["contract"] == CONTRACT
            and index["distribution"] == evidence.distribution and index["toolchain"] == fixed["toolchain"]
            and proof["theorems"] == list(THEOREMS) and proof["allowedAxioms"] == config["permitted_axioms"]
            and proof["kernels"] == list(CHECKERS), "bundle-postgres-membership-proof-identity", origin)
    tools = {"shape": "shape.php", "checker": "verify_postgres_signed_membership_proof.py",
             "kernelRunner": "verify_postgres_reindex_proof.py", "linker": "verify_postgres_sequence_order_proof.py"}
    for name, source in tools.items():
        require(index["tools"][name] == digest(TOOL / source), "bundle-postgres-membership-proof-tool", source)
    evidence.checked(folder / "distribution.json", evidence.distribution)
    php, shape_ref = evidence.checked(folder / "php-shape.json", index["phpShape"])
    linkage = link_php(fixed, manifest, json.loads(php.read_text()))
    require(linkage == index["phpLinkage"], "bundle-postgres-membership-proof-linkage", shape_ref)
    result = folder / "proof"
    for artifact, checksum in fixed["contractArtifacts"].items():
        evidence.checked(result / artifact, checksum)
    evidence.checked(result / "export-Challenge.ndjson", proof["challenge"])
    _, exported = evidence.checked(result / "export-Solution.ndjson", proof["solution"])
    report = command(evidence, result, proof["comparison"], 0)
    require(all(f"{kernel} kernel accepts the solution" in report for kernel in CHECKERS),
            "bundle-postgres-membership-proof-kernels")
    require(proof["controls"].keys() == fixed["controls"].keys(), "bundle-postgres-membership-proof-controls")
    for name, control in proof["controls"].items():
        changed = folder / name
        require(control["signal"] == fixed["controls"][name]
                and control["original"] == fixed["contractArtifacts"]["Solution.lean"]
                and control["mutation"] != control["original"], "bundle-postgres-membership-proof-control-identity", name)
        evidence.checked(changed / "Solution.lean", control["mutation"])
        evidence.checked(changed / "injected.diff", control["patch"])
        require(control["signal"] in command(evidence, changed, control["comparison"], 1),
                "bundle-postgres-membership-proof-control-signal", name)
    return {"receipt": origin, "shape": shape_ref, "export": exported,
            "theorems": proof["theorems"], "allowedAxioms": proof["allowedAxioms"], "methods": sorted(linkage)}
