"""Check signed decimal order in independent kernels against the audited PHP."""

import argparse
from difflib import unified_diff
import json
import os
from pathlib import Path
import shutil

from run import TOOL, Failure, candidate_distribution, digest, require, shape, verify_candidate, write_json
from verify_postgres_reindex_proof import CHECKERS, LEAN_TOOLS, PRIMITIVES, build_sandbox, compare, measured

FORMAL = TOOL / "formal" / "postgres_sequence_order"
CONTRACT = "0f2f97c1d5cfd05dff52e2b8411dbecee8ee933dcaec993912a0ee6a96c6f041"
RECEIPT_STATUS = "provisional-machine-checked-postgres-sequence-order-proof"
ORDER = "Schema/Sequence/SequenceIntegerOrder::compare"
PROOF_CONTROLS = ("weakened-theorem", "unfinished-proof")
THEOREM = "SignedDecimal.decimalOrder"


def fixed_contract():
    source = FORMAL / "contract-v1.json"
    require(digest(source) == CONTRACT, "postgres-sequence-proof-contract")
    frozen = json.loads(source.read_text())
    for name, checksum in frozen["contractArtifacts"].items():
        require(digest(FORMAL / name) == checksum, "postgres-sequence-proof-artifact", name)
    config = json.loads((FORMAL / "comparator.json").read_text())
    require(config["permitted_axioms"] == list(frozen["axioms"]) == ["propext"],
            "postgres-sequence-proof-axioms")
    return frozen, config


def link_php(contract, manifest, checked):
    require(checked["status"] == "verified" and contract["bindings"].keys() <= set(checked["checkedBoundaries"]),
            "postgres-sequence-proof-php-shape")
    for role, binding in contract["bindings"].items():
        record = TOOL / binding["template"]
        require(digest(record) == binding["templateDigest"]
                and manifest[binding["source"]] == binding["sourceDigest"], "postgres-sequence-proof-php-identity", role)
        rule = json.loads(record.read_text())["rules"][role]
        require(rule.get("common", []) + rule.get("postgres", []) == binding["body"],
                "postgres-sequence-proof-php-template", role)
    return contract["bindings"]


def linked_php(args, contract, manifest):
    checked = shape(args, dialect="postgres")
    write_json(args.out / "php-shape.json", checked)
    linkage = link_php(contract, manifest, checked)
    source = args.candidate / linkage[ORDER]["source"]
    before = source.read_text()
    expected = contract["controls"]["reversed-negative-order"]
    anchor = "return $negative ? -$magnitude : $magnitude;"
    require(before.count(anchor) == 1, "postgres-sequence-proof-php-control-anchor")
    after = before.replace(anchor, "return $negative ? $magnitude : -$magnitude;")
    overlay = args.out / "reversed-negative-order"
    mutant = overlay / source.relative_to(args.candidate).relative_to("packages")
    mutant.parent.mkdir(parents=True)
    mutant.write_text(after)
    delta = write_difference(overlay, source.name, before, after)
    try:
        shape(args, overlay, dialect="postgres")
    except Failure as error:
        require(error.detail.get("message") == expected, "postgres-sequence-proof-php-control-reason", error.detail)
        receipt = overlay / "result.json"
        write_json(receipt, {"expected": expected, "message": error.detail["message"], "original": digest(source),
                             "mutant": digest(mutant), "patch": digest(delta)})
    else:
        raise Failure("postgres-sequence-proof-php-control-accepted")
    return linkage, digest(receipt)


def write_difference(folder, name, before, after):
    delta = folder / "injected.diff"
    delta.write_text("".join(unified_diff(before.splitlines(True), after.splitlines(True), f"a/{name}", f"b/{name}")))
    return delta


def stage(folder, contract):
    folder.mkdir()
    for name in contract["contractArtifacts"]:
        shutil.copyfile(FORMAL / name, folder / name)


def build(args, folder, bwrap, config, modules):
    sandbox, binaries = build_sandbox(folder, args.lean, bwrap)
    for module in ("SignedDecimalSpec", *modules):
        status, _, _ = measured([*sandbox, str(binaries / "lean"), "-o", module + ".olean", module + ".lean"],
                                folder, "build-" + module)
        require(status == 0, "postgres-sequence-proof-build", module)
    for module in modules:
        status, _, _ = measured([*sandbox, str(binaries / "leanexport"), module, "--", *config["theorem_names"],
                                 *config["definition_names"], *PRIMITIVES, "Int", "Fin", "Bool"],
                                folder, "export-" + module, ".ndjson")
        require(status == 0, "postgres-sequence-proof-export", module)


def verify_proof(args, contract, config, bwrap):
    proof = args.out / "proof"
    stage(proof, contract)
    build(args, proof, bwrap, config, ("Challenge", "Solution"))
    challenge = proof / "export-Challenge.ndjson"
    result, out, err = compare(proof, args.lean, challenge, config)
    output = out.read_text() + err.read_text()
    require(result == 0 and "Sandbox disabled" not in output
            and all(f"{name} kernel accepts the solution" in output for name in CHECKERS),
            "postgres-sequence-proof-comparison", result)
    axioms = (proof / "build-Solution.out").read_text() + (proof / "build-Solution.err").read_text()
    require(f"'{THEOREM}' depends on axioms: [propext]" in axioms,
            "postgres-sequence-proof-axiom-receipt", axioms)
    controls = {}
    original = (FORMAL / "Solution.lean").read_text()
    start = original.index("theorem decimalOrder\n")
    end = original.index("\ntheorem representativeDomain", start)
    signature = original[start:original.index(" := by", start)]
    replacements = ("theorem decimalOrder : True := True.intro\n", signature + " := by sorry\n")
    for name, replacement in zip(PROOF_CONTROLS, replacements):
        path = args.out / name
        stage(path, contract)
        changed = original[:start] + replacement + original[end:]
        (path / "Solution.lean").write_text(changed)
        patch = write_difference(path, "Solution.lean", original, changed)
        build(args, path, bwrap, config, ("Solution",))
        status, stdout, stderr = compare(path, args.lean, challenge, config)
        observed = stdout.read_text() + stderr.read_text()
        signal = contract["controls"][name]
        require(status == 1 and signal in observed and "Sandbox disabled" not in observed,
                "postgres-sequence-proof-control", name)
        controls[name] = {"signal": signal, "original": contract["contractArtifacts"]["Solution.lean"],
                          "mutant": digest(path / "Solution.lean"), "patch": digest(patch),
                          "comparison": digest(path / "compare.command.json")}
    return {"challenge": digest(challenge), "proof": digest(proof / "export-Solution.ndjson"),
            "comparison": digest(proof / "compare.command.json"), "theorems": config["theorem_names"],
            "allowedAxioms": config["permitted_axioms"], "checkedKernels": list(CHECKERS), "controls": controls,
            "toolchain": contract["toolchain"], "sandbox": "bubblewrap with no network and no mounted candidate source"}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate", required=True, type=Path)
    parser.add_argument("--candidate-sha", required=True)
    for flag in ("tool-autoload", "lean", "archive", "out"):
        parser.add_argument("--" + flag, required=True, type=Path)
    args = parser.parse_args()
    for attribute in ("candidate", "tool_autoload", "lean", "archive", "out"):
        setattr(args, attribute, getattr(args, attribute).resolve())
    require(all(path != args.candidate and args.candidate not in path.parents
                for path in (TOOL, args.tool_autoload, args.lean, args.archive, args.out)),
            "postgres-sequence-proof-verifier-inside-candidate")
    contract, config = fixed_contract()
    require(os.uname().sysname == "Linux" and os.uname().machine == "aarch64", "postgres-sequence-proof-host-profile")
    require(digest(args.archive) == contract["toolchain"]["linuxAarch64"]["sha256"], "postgres-sequence-proof-toolchain-archive")
    bwrap = shutil.which("bwrap")
    require(bwrap is not None, "postgres-sequence-proof-sandbox-missing")
    sha, manifest = candidate_distribution(args.candidate, args.candidate_sha)
    args.out.mkdir(parents=True, exist_ok=False)
    write_json(args.out / "distribution.json", manifest)
    binaries = args.lean / "bin"
    result, version, _ = measured([str(binaries / "lean"), "--version"], args.out, "lean-version")
    require(result == 0 and f"version {contract['toolchain']['version']}," in version.read_text()
            and contract["toolchain"]["commit"] in version.read_text(), "postgres-sequence-proof-toolchain-version")
    linkage, php_control = linked_php(args, contract, manifest)
    proof = verify_proof(args, contract, config, bwrap)
    verify_candidate(args.candidate, sha, manifest)
    write_json(args.out / "index.json", {
        "status": RECEIPT_STATUS, "candidate": sha, "contract": CONTRACT,
        "distribution": digest(args.out / "distribution.json"), "phpShape": digest(args.out / "php-shape.json"),
        "phpLinkage": linkage, "phpControl": php_control, "proof": proof,
        "tools": {name: digest(binaries / name) for name in LEAN_TOOLS} | {
            "bubblewrap": digest(Path(bwrap)), "shape": digest(TOOL / "shape.php"), "checker": digest(Path(__file__)),
            "sharedRunner": digest(TOOL / "verify_postgres_reindex_proof.py"),
        },
    })


if __name__ == "__main__":
    main()
