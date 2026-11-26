"""Check signed membership in four kernels and reconcile the candidate PHP."""

import argparse
import json
import os
from pathlib import Path
import shutil

from run import TOOL, candidate_distribution, digest, require, shape, verify_candidate, write_json
from verify_postgres_reindex_proof import CHECKERS, LEAN_TOOLS, PRIMITIVES, build_sandbox, compare, measured
from verify_postgres_sequence_order_proof import link_php, write_difference

FORMAL = TOOL / "formal/postgres_signed_membership"
CONTRACT = "54f6de1e2a2d27ab5ec2abd0f330d6b9d60e2f0742c45d873bbec8ef317f6393"
THEOREMS = ("SqlMembership.positive", "SqlMembership.negative")
STATUS = "provisional-machine-checked-postgres-signed-membership-proof"


def fixed_contract():
    contract_path = FORMAL / "contract-v1.json"
    require(digest(contract_path) == CONTRACT, "postgres-membership-proof-contract")
    fixed = json.loads(contract_path.read_text())
    for name, checksum in fixed["contractArtifacts"].items():
        require(digest(FORMAL / name) == checksum, "postgres-membership-proof-artifact", name)
    config = json.loads((FORMAL / "comparator.json").read_text())
    require(tuple(config["theorem_names"]) == THEOREMS
            and config["permitted_axioms"] == list(fixed["axioms"]) == ["propext"],
            "postgres-membership-proof-controlled-conclusion")
    return fixed, config


def stage(folder, fixed):
    folder.mkdir()
    for name in fixed["contractArtifacts"]:
        shutil.copyfile(FORMAL / name, folder / name)


def build(folder, toolchain, bwrap, config, modules):
    sandbox, binaries = build_sandbox(folder, toolchain, bwrap)
    for module in ("MembershipSpec", *modules):
        code, _, errors = measured([*sandbox, str(binaries / "lean"), "-o", module + ".olean", module + ".lean"],
                                   folder, "build-" + module)
        require(code == 0, "postgres-membership-proof-build", {"module": module, "stderr": errors.name})
    for module in modules:
        code, _, errors = measured(
            [*sandbox, str(binaries / "leanexport"), module, "--", *THEOREMS,
             *config["definition_names"], *PRIMITIVES, "Int", "Option", "Bool", "SqlMembership.Truth"],
            folder, "export-" + module, ".ndjson",
        )
        require(code == 0, "postgres-membership-proof-export", {"module": module, "stderr": errors.name})


def mutated_solutions(original):
    start = original.index("theorem negative ")
    end = original.index("\n#print axioms", start)
    signature = original[start:original.index(" := by", start)]
    yield "weakened-theorem", original[:start] + "theorem negative : True := True.intro\n" + original[end:]
    yield "unfinished-proof", original[:start] + signature + " := by sorry\n" + original[end:]


def check_proof(args, fixed, config, bwrap):
    output = args.out / "proof"
    stage(output, fixed)
    build(output, args.lean, bwrap, config, ("Challenge", "Solution"))
    challenge = output / "export-Challenge.ndjson"
    exit_code, stdout, stderr = compare(output, args.lean, challenge, config)
    verdict = stdout.read_text() + stderr.read_text()
    require(exit_code == 0 and "Sandbox disabled" not in verdict
            and all(f"{kernel} kernel accepts the solution" in verdict for kernel in CHECKERS),
            "postgres-membership-proof-kernels", verdict)
    reported = (output / "build-Solution.out").read_text() + (output / "build-Solution.err").read_text()
    require(all(f"'{theorem}' depends on axioms: [propext]" in reported for theorem in THEOREMS)
            and "sorry" not in reported, "postgres-membership-proof-axiom-receipt", reported)
    original = (FORMAL / "Solution.lean").read_text()
    controls = {}
    for name, altered in mutated_solutions(original):
        folder = args.out / name
        stage(folder, fixed)
        solution = folder / "Solution.lean"
        solution.write_text(altered)
        patch = write_difference(folder, solution.name, original, altered)
        build(folder, args.lean, bwrap, config, ("Solution",))
        code, stdout, stderr = compare(folder, args.lean, challenge, config)
        observation = stdout.read_text() + stderr.read_text()
        signal = fixed["controls"][name]
        require(code == 1 and signal in observation and "Sandbox disabled" not in observation,
                "postgres-membership-proof-control", {"control": name, "observation": observation})
        controls[name] = {"signal": signal, "original": fixed["contractArtifacts"]["Solution.lean"],
                          "mutation": digest(solution), "patch": digest(patch), "comparison": digest(folder / "compare.command.json")}
    return {"challenge": digest(challenge), "solution": digest(output / "export-Solution.ndjson"),
            "comparison": digest(output / "compare.command.json"), "theorems": list(THEOREMS),
            "allowedAxioms": config["permitted_axioms"], "kernels": list(CHECKERS), "controls": controls}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate-sha", required=True)
    for argument in ("candidate", "tool-autoload", "lean", "archive", "out"):
        parser.add_argument(f"--{argument}", type=lambda value: Path(value).resolve(), required=True)
    args = parser.parse_args()
    require(all(path != args.candidate and args.candidate not in path.parents
                for path in (TOOL, args.tool_autoload, args.lean, args.archive, args.out)),
            "postgres-membership-proof-verifier-overlap")
    fixed, config = fixed_contract()
    require(os.uname().sysname == "Linux" and os.uname().machine == "aarch64", "postgres-membership-proof-profile")
    require(digest(args.archive) == fixed["toolchain"]["linuxAarch64"]["sha256"], "postgres-membership-proof-toolchain-archive")
    bwrap = shutil.which("bwrap")
    require(bwrap is not None, "postgres-membership-proof-sandbox")
    sha, manifest = candidate_distribution(args.candidate, args.candidate_sha)
    args.out.mkdir(parents=True, exist_ok=False)
    write_json(args.out / "distribution.json", manifest)
    binaries = args.lean / "bin"
    result, version, _ = measured([str(binaries / "lean"), "--version"], args.out, "lean-version")
    require(result == 0 and f"version {fixed['toolchain']['version']}," in version.read_text()
            and fixed["toolchain"]["commit"] in version.read_text(), "postgres-membership-proof-toolchain-version")
    checked = shape(args, dialect="postgres")
    write_json(args.out / "php-shape.json", checked)
    linked = link_php(fixed, manifest, checked)
    proof = check_proof(args, fixed, config, bwrap)
    verify_candidate(args.candidate, sha, manifest)
    write_json(args.out / "index.json", {
        "status": STATUS, "candidate": sha, "contract": CONTRACT, "distribution": digest(args.out / "distribution.json"),
        "phpShape": digest(args.out / "php-shape.json"), "phpLinkage": linked, "proof": proof, "toolchain": fixed["toolchain"],
        "tools": {binary: digest(binaries / binary) for binary in LEAN_TOOLS} | {
            "bubblewrap": digest(Path(bwrap)), "shape": digest(TOOL / "shape.php"), "checker": digest(Path(__file__)),
            "kernelRunner": digest(TOOL / "verify_postgres_reindex_proof.py"),
            "linker": digest(TOOL / "verify_postgres_sequence_order_proof.py"),
        },
    })


if __name__ == "__main__":
    main()
