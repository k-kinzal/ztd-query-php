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
CONTRACT_DIGEST = "459213ebf58e6f94b63067f5552455510533024a8a4b5ad7e2961722fad4d271"
SOLUTION_DIGEST = "f077c5c0fd0827bf6956ccc3e730970275b9a508b1daa0fc0dbe0e9ee0cf4f75"
ROOT = "PostgreSqlReindex.commandRoundTrip"
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


def load_contract():
    path = FORMAL / "contract-v4.json"
    require(digest(path) == CONTRACT_DIGEST, "postgres-reindex-formal-contract")
    contract = json.loads(path.read_text())
    for name, wanted in contract["contractArtifacts"].items():
        require(digest(FORMAL / name) == wanted, "postgres-reindex-formal-artifact", name)
    config = json.loads((FORMAL / "comparator.json").read_text())
    require(config["permitted_axioms"] == [], "postgres-reindex-formal-axiom-allowance")
    require(digest(FORMAL / "Solution.lean") == SOLUTION_DIGEST, "postgres-reindex-formal-proof-source")
    return contract, config


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
    for module in ("ReindexSpec", *modules):
        rc, _, errors = measured([*sandbox, str(binaries / "lean"), "-o", module + ".olean", module + ".lean"],
                                 folder, "build-" + module)
        require(rc == 0, "postgres-reindex-formal-build", {"module": module, "diagnostics": errors.name})
    for module in modules:
        rc, _, errors = measured([*sandbox, str(binaries / "leanexport"), module, "--",
                                   *config["theorem_names"], *PRIMITIVES], folder, "export-" + module, ".ndjson")
        require(rc == 0, "postgres-reindex-formal-export", {"module": module, "diagnostics": errors.name})


def stage(folder, solution):
    folder.mkdir()
    for name in ("ReindexSpec.lean", "Challenge.lean", "comparator.json", "lean-toolchain"):
        shutil.copyfile(FORMAL / name, folder / name)
    (folder / "Solution.lean").write_text(solution)


def compare(folder, toolchain, challenge):
    active = json.loads((folder / "comparator.json").read_text())
    binaries = toolchain / "bin"
    active["external_kernels"] = {kernel: [str(binaries / kernel)] for kernel in EXTERNAL_KERNELS}
    active["external_kernels"]["lean4lean"] = ["/usr/bin/env", f"PATH={binaries}:/usr/bin:/bin", str(binaries / "lean4lean"), "--import"]
    chosen = folder / "active-comparator.json"
    write_json(chosen, active)
    return measured(
        [str(toolchain / "bin/lake"), "comparator", "--config", str(chosen),
         "--challenge-from-export", str(challenge), "--solution-from-export", str(folder / "export-Solution.ndjson")],
        folder, "compare",
    )


def proof_checks(args, contract, config, bwrap):
    source = (FORMAL / "Solution.lean").read_text()
    control = args.out / "proof"
    stage(control, source)
    build_and_export(control, args.lean, bwrap, config, ("Challenge", "Solution"))
    challenge = control / "export-Challenge.ndjson"
    rc, output, errors = compare(control, args.lean, challenge)
    transcript = output.read_text() + errors.read_text()
    require(rc == 0 and "Sandbox disabled" not in transcript and
            all(f"{kernel} kernel accepts the solution" in transcript for kernel in CHECKERS),
            "postgres-reindex-formal-comparison", rc)

    axes = (control / "build-Solution.out").read_text() + (control / "build-Solution.err").read_text()
    require(all(f"'{name}' does not depend on any axioms" in axes for name in config["theorem_names"]),
            "postgres-reindex-formal-unexpected-axioms", axes)

    start = source.index("theorem commandRoundTrip ")
    end = source.index("\nend PostgreSqlReindex", start)
    signature = source[start:source.index(" := by", start)]
    trials = (
        ("weakened-theorem", "theorem commandRoundTrip : True := True.intro\n",
         "theorem statement do not match"),
        ("unfinished-proof", signature + " := by sorry\n", "sorryAx"),
    )
    controls = {}
    for name, replacement, signal in trials:
        probe = args.out / name
        after = source[:start] + replacement + source[end:]
        stage(probe, after)
        delta = "".join(unified_diff(source.splitlines(True), after.splitlines(True), "a/Solution.lean", "b/Solution.lean"))
        (probe / "injected.diff").write_text(delta)
        build_and_export(probe, args.lean, bwrap, config, ("Solution",))
        rc, output, errors = compare(probe, args.lean, challenge)
        detection = output.read_text() + errors.read_text()
        require(rc == 1 and signal in detection and "Sandbox disabled" not in detection,
                "postgres-reindex-formal-control", {"case": name, "exit": rc, "expected": signal})
        controls[name] = {
            "expected": signal, "observed": signal, "original": SOLUTION_DIGEST, "mutant": digest(probe / "Solution.lean"),
            "patch": digest(probe / "injected.diff"), "diagnostics": digest(probe / "compare.command.json"),
        }
    return {
        "theorems": config["theorem_names"], "allowedAxioms": [], "checkedKernels": list(CHECKERS), "controls": controls,
        "challenge": digest(challenge), "proof": digest(control / "export-Solution.ndjson"),
        "comparison": digest(control / "compare.command.json"),
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
