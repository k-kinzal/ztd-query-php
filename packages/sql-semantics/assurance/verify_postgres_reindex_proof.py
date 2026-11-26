"""Check the fixed lexical proof in independent kernels and link it to candidate PHP."""

import argparse
from difflib import unified_diff
import json
import os
from pathlib import Path
import shutil
import subprocess

from postgres_reindex_lexical_link import PHP_SOURCE, verify_spelling
from run import TOOL, Failure, candidate_distribution, digest, require, shape, verify_candidate, write_json

FORMAL = TOOL / "formal" / "postgres_reindex"
CONTRACT_DIGEST = "0dfe342eaa69fa3f9543f3ef318efa41fa319960b68894f0c5e310456f621f65"
BASE_CONTRACT_DIGEST = "754f61f2d92fc37d3a7b3c2b4ed7f66535bb45005f39f076b5b78b03cefd2e5a"
SOLUTION_DIGEST = "f077c5c0fd0827bf6956ccc3e730970275b9a508b1daa0fc0dbe0e9ee0cf4f75"
PLACEMENT_SOLUTION_DIGEST = "b03829e609eb8fca3ba8f6614d73818af54a8ca5e2ce606f0980a36bd7e57144"
PROOF_SOURCES = {"Solution.lean": SOLUTION_DIGEST, "PlacementSolution.lean": PLACEMENT_SOLUTION_DIGEST}
PROOF_CONTROLS = {
    "weakened-theorem": ("Solution.lean", "theorem statement do not match"),
    "unfinished-proof": ("Solution.lean", "sorryAx"),
    "weakened-placement-theorem": ("PlacementSolution.lean", "theorem statement do not match"),
    "unfinished-placement-proof": ("PlacementSolution.lean", "sorryAx"),
}
EXTERNAL_KERNELS = ("lean4lean", "con-leche", "con-ron")
CHECKERS = (*EXTERNAL_KERNELS, "Lean default")
LEAN_TOOLS = ("lean", "lake", "leanexport", "leanchecker", *EXTERNAL_KERNELS)
PRIMITIVES = (
    "Nat.add", "Nat.sub", "Nat.mul", "Nat.pow", "Nat.gcd", "Nat.div", "Nat.mod", "Nat.beq", "Nat.ble",
    "Nat.land", "Nat.lor", "Nat.xor", "Nat.shiftLeft", "Nat.shiftRight", "String.ofList", "Char.ofNat",
    "List", "eagerReduce", "Nat", "String", "String.mk", "Char", "optParam", "autoParam", "semiOutParam",
    "outParam",
)
TIMEOUT = 150


def fixed_contract(filename, checksum, comparator):
    path = FORMAL / filename
    require(digest(path) == checksum, "postgres-reindex-formal-contract", filename)
    contract = json.loads(path.read_text())
    for name, wanted in contract["contractArtifacts"].items():
        require(digest(FORMAL / name) == wanted, "postgres-reindex-formal-artifact", name)
    config = json.loads((FORMAL / comparator).read_text())
    require(config["permitted_axioms"] == [], "postgres-reindex-formal-axiom-allowance")
    return contract, config


def load_contract():
    for proof, checksum in PROOF_SOURCES.items():
        require(digest(FORMAL / proof) == checksum, "postgres-reindex-formal-proof-source", proof)
    return fixed_contract("contract-v7.json", CONTRACT_DIGEST, "placement-comparator.json")


def measured(command, folder, label, stdout_suffix=".out"):
    stdout = folder / (label + stdout_suffix)
    stderr = folder / (label + ".err")
    with stdout.open("wb") as output, stderr.open("wb") as errors:
        try:
            completed = subprocess.run(command, cwd=folder, stdout=output, stderr=errors, timeout=TIMEOUT, check=False)
        except subprocess.TimeoutExpired as cause:
            write_json(folder / (label + ".command.json"), {"argv": command, "timeoutSeconds": TIMEOUT, "exit": "timeout"})
            raise Failure("postgres-reindex-formal-timeout", label) from cause
    report = {"argv": command, "exit": completed.returncode, "stdout": digest(stdout), "stderr": digest(stderr)}
    write_json(folder / (label + ".command.json"), report)
    return completed.returncode, stdout, stderr


def build_sandbox(folder, toolchain, bwrap):
    binaries = toolchain / "bin"
    command = [str(bwrap), "--unshare-all", "--die-with-parent", "--cap-drop", "ALL",
               "--ro-bind", "/usr", "/usr", "--ro-bind-try", "/lib", "/lib", "--ro-bind-try", "/lib64", "/lib64",
               "--dev", "/dev", "--proc", "/proc", "--tmpfs", "/tmp",
               "--ro-bind", str(toolchain), str(toolchain), "--bind", str(folder), str(folder), "--chdir", str(folder),
               "--clearenv", "--setenv", "PATH", str(binaries) + ":/usr/bin",
               "--setenv", "LEAN_PATH", str(folder), "--setenv", "LEAN_ABORT_ON_PANIC", "1"]
    return command, binaries


def build_and_export(folder, toolchain, bwrap, config, modules):
    sandbox, binaries = build_sandbox(folder, toolchain, bwrap)
    dependencies = ("ReindexSpec", "PlacementSpec", "Solution") if config["solution_module"] == "PlacementSolution" else ("ReindexSpec",)
    for module in (*dependencies, *modules):
        rc, _, errors = measured([*sandbox, str(binaries / "lean"), "-o", module + ".olean", module + ".lean"],
                                 folder, "build-" + module)
        require(rc == 0, "postgres-reindex-formal-build", {"module": module, "diagnostics": errors.name})
    for module in modules:
        rc, _, errors = measured([*sandbox, str(binaries / "leanexport"), module, "--",
                                   *config["theorem_names"], *PRIMITIVES], folder, "export-" + module, ".ndjson")
        require(rc == 0, "postgres-reindex-formal-export", {"module": module, "diagnostics": errors.name})


def stage(folder, contract, proofs):
    folder.mkdir()
    for name in contract["contractArtifacts"]:
        shutil.copyfile(FORMAL / name, folder / name)
    for name, source in proofs.items():
        (folder / name).write_text(source)


def compare(folder, toolchain, challenge, config):
    active = dict(config)
    binaries = toolchain / "bin"
    active["external_kernels"] = {kernel: [str(binaries / kernel)] for kernel in EXTERNAL_KERNELS}
    active["external_kernels"]["lean4lean"] = ["/usr/bin/env", f"PATH={binaries}:/usr/bin:/bin", str(binaries / "lean4lean"), "--import"]
    chosen = folder / "active-comparator.json"
    write_json(chosen, active)
    return measured(
        [str(toolchain / "bin/lake"), "comparator", "--config", str(chosen),
         "--challenge-from-export", str(challenge), "--solution-from-export",
         str(folder / f"export-{config['solution_module']}.ndjson")],
        folder, "compare",
    )


def check_mutation(args, name, challenge, contract, config, proofs, bwrap):
    proof, signal = PROOF_CONTROLS[name]
    live = proofs[proof]
    theorem = "placementRoundTrip" if proof == "PlacementSolution.lean" else "commandRoundTrip"
    start = live.index(f"theorem {theorem} ")
    end = live.index("\nend PostgreSqlReindex", start)
    signature = live[start:live.index(" := by", start)]
    replacement = signature + " := by sorry\n" if signal == "sorryAx" else f"theorem {theorem} : True := True.intro\n"
    after = live[:start] + replacement + live[end:]
    probe = args.out / name
    stage(probe, contract, {**proofs, proof: after})
    delta = "".join(unified_diff(live.splitlines(True), after.splitlines(True), f"a/{proof}", f"b/{proof}"))
    (probe / "injected.diff").write_text(delta)
    build_and_export(probe, args.lean, bwrap, config, (config["solution_module"],))
    rc, output, errors = compare(probe, args.lean, challenge, config)
    detection = output.read_text() + errors.read_text()
    require(rc == 1 and signal in detection and "Sandbox disabled" not in detection,
            "postgres-reindex-formal-control", {"case": name, "exit": rc, "expected": signal})
    return {"expected": signal, "observed": signal, "file": proof, "original": PROOF_SOURCES[proof],
            "mutant": digest(probe / proof), "patch": digest(probe / "injected.diff"),
            "diagnostics": digest(probe / "compare.command.json")}


def proof_checks(args, contract, config, bwrap):
    sources = {name: (FORMAL / name).read_text() for name in PROOF_SOURCES}
    control = args.out / "proof"
    stage(control, contract, sources)
    challenge_module, solution_module = config["challenge_module"], config["solution_module"]
    build_and_export(control, args.lean, bwrap, config, (challenge_module, solution_module))
    challenge = control / f"export-{challenge_module}.ndjson"
    rc, output, errors = compare(control, args.lean, challenge, config)
    transcript = output.read_text() + errors.read_text()
    require(rc == 0 and "Sandbox disabled" not in transcript and
            all(f"{kernel} kernel accepts the solution" in transcript for kernel in CHECKERS),
            "postgres-reindex-formal-comparison", rc)
    axes = "".join((control / f"build-{module}.{extension}").read_text()
                   for module in ("Solution", solution_module) for extension in ("out", "err"))
    require(all(f"'{name}' does not depend on any axioms" in axes for name in config["theorem_names"]),
            "postgres-reindex-formal-unexpected-axioms", axes)

    legacy, base_config = fixed_contract("contract-v5.json", BASE_CONTRACT_DIGEST, "comparator.json")
    base_test = args.out / "base-challenge"
    stage(base_test, legacy, {})
    build_and_export(base_test, args.lean, bwrap, base_config, ("Challenge",))
    original_challenge = base_test / "export-Challenge.ndjson"
    base_sources = {"Solution.lean": sources["Solution.lean"]}
    controls = {}
    for name, (proof, _) in PROOF_CONTROLS.items():
        if proof == "Solution.lean":
            controls[name] = check_mutation(args, name, original_challenge, legacy, base_config, base_sources, bwrap)
        else:
            controls[name] = check_mutation(args, name, challenge, contract, config, sources, bwrap)
    return {
        "theorems": config["theorem_names"], "allowedAxioms": [], "checkedKernels": list(CHECKERS), "controls": controls,
        "challenge": digest(challenge), "proof": digest(control / f"export-{solution_module}.ndjson"),
        "baseChallenge": digest(original_challenge), "comparison": digest(control / "compare.command.json"),
        "exportPrimitives": list(PRIMITIVES), "sandbox": "bubblewrap with no network and no mounted candidate source",
        "toolchain": contract["toolchain"],
    }


def check_candidate(args, manifest):
    checked = shape(args, dialect="postgres")
    write_json(args.out / "php-shape.json", checked)
    linkage = verify_spelling(manifest, checked, FORMAL / "ReindexSpec.lean")
    original = args.candidate / PHP_SOURCE / "Schema/TableName.php"
    source = original.read_text()
    stale = " . '.' . "
    require(source.count(stale) == 1, "postgres-reindex-formal-php-control-anchor")
    changed = source.replace(stale, " . ':' . ")
    overlay = args.out / "php-delimiter"
    fault_path = overlay / original.relative_to(args.candidate).relative_to("packages")
    fault_path.parent.mkdir(parents=True)
    fault_path.write_text(changed)
    diff = overlay / "injected.diff"
    diff.write_text("".join(unified_diff(source.splitlines(True), changed.splitlines(True), "a/TableName.php", "b/TableName.php")))
    expected = "Native boundary differs in Schema/TableName::quoted"
    try:
        shape(args, overlay, dialect="postgres")
    except Failure as result:
        require(result.detail.get("message") == expected, "postgres-reindex-formal-php-control-signal", result.detail)
        write_json(overlay / "result.json", {"expected": expected, "observed": result.detail, "patch": digest(diff),
                                            "original": digest(original), "mutant": digest(fault_path)})
    else:
        raise Failure("postgres-reindex-formal-php-control-accepted")
    return linkage


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate", required=True, type=Path)
    parser.add_argument("--candidate-sha", required=True)
    parser.add_argument("--tool-autoload", required=True, type=Path)
    parser.add_argument("--lean", required=True, type=Path, help="Root of the pinned extracted Lean toolchain")
    parser.add_argument("--archive", required=True, type=Path, help="Pinned official toolchain archive")
    parser.add_argument("--out", required=True, type=Path)
    args = parser.parse_args()
    args.candidate, args.tool_autoload, args.lean, args.archive, args.out = (
        path.resolve() for path in (args.candidate, args.tool_autoload, args.lean, args.archive, args.out))
    require(all(path != args.candidate and args.candidate not in path.parents
                for path in (TOOL, args.tool_autoload, args.lean, args.out)), "postgres-reindex-formal-verifier-inside-candidate")
    contract, config = load_contract()
    require(os.uname().sysname == "Linux" and os.uname().machine == "aarch64", "postgres-reindex-formal-host-profile")
    require(digest(args.archive) == contract["toolchain"]["linuxAarch64"]["sha256"], "postgres-reindex-formal-toolchain-archive")
    bwrap = shutil.which("bwrap")
    require(bwrap is not None, "postgres-reindex-formal-sandbox-missing")
    commit, manifest = candidate_distribution(args.candidate, args.candidate_sha)

    args.out.mkdir(parents=True, exist_ok=False)
    write_json(args.out / "distribution.json", manifest)
    binaries = args.lean / "bin"
    rc, version, _ = measured([str(binaries / "lean"), "--version"], args.out, "lean-version")
    require(rc == 0 and f"version {contract['toolchain']['version']}," in version.read_text()
            and contract["toolchain"]["commit"] in version.read_text(), "postgres-reindex-formal-toolchain-version")
    linkage = check_candidate(args, manifest)
    proof = proof_checks(args, contract, config, bwrap)
    verify_candidate(args.candidate, commit, manifest)
    write_json(args.out / "index.json", {
        "status": "provisional-machine-checked-postgres-reindex-lexical-proof",
        "candidate": commit, "contract": CONTRACT_DIGEST, "distribution": digest(args.out / "distribution.json"),
        "phpShape": digest(args.out / "php-shape.json"), "phpLinkage": linkage, "proof": proof,
        "phpControl": digest(args.out / "php-delimiter/result.json"),
        "tools": {name: digest(binaries / name) for name in LEAN_TOOLS} | {
            "bubblewrap": digest(Path(bwrap)), "shape": digest(TOOL / "shape.php"),
            "checker": digest(Path(__file__)), "linkage": digest(TOOL / "postgres_reindex_lexical_link.py"),
        },
    })


if __name__ == "__main__":
    main()
