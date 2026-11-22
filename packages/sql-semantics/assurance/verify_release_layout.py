"""Verify exact split-package bytes and exercise installed publication through Composer."""

import argparse
from difflib import unified_diff
import json
import os
from pathlib import Path
import shutil
import subprocess
from tempfile import TemporaryDirectory

from run import IMAGE, PACKAGES, TOOL, Failure, candidate_distribution, digest, require, verify_candidate, write_json

CONTRACT = "3cd8905bf72414457cea1458b392c29dbb3aa7d6b4a33521ae47e66778585aef"
DIALECTS = {"MySql": "mysql", "PostgreSql": "postgres", "Sqlite": "sqlite"}
COMMON = {"Select", "Rows\\Compound", "Mutation\\Delete", "Mutation\\Insert", "Mutation\\Update",
          "Schema\\CreateTable", "Schema\\DropTable", "Transaction\\Transaction"}


def checked_recipe(candidate, contract, out):
    path, identity = contract["recipe"]["path"], contract["recipe"]["sha256"]
    trusted = TOOL.parents[2] / path
    require(digest(trusted) == identity and digest(candidate / path) == identity, "release-recipe-mismatch")
    text = trusted.read_text()
    section = text.split("      - name: Transform composer.json\n", 1)[1].split("      - name: Remove the monorepo", 1)[0]
    lines = section.split("        run: |\n", 1)[1].splitlines()
    commands = "\n".join(line.removeprefix(" " * 10) for line in lines).strip()
    file = 'FILE="packages/${{ matrix.package.local_path }}/composer.json"'
    version = 'CONSTRAINT="${{ steps.version.outputs.constraint }}"'
    require(commands.count(file) == 1 and commands.count(version) == 1, "unexpected-split-transform")
    script = out / "transform-composer.sh"
    script.write_text('set -euo pipefail\n' + commands.replace(file, 'FILE="$ZTD_SPLIT_FILE"').replace(version, 'CONSTRAINT="$ZTD_SPLIT_CONSTRAINT"') + "\n")
    shutil.copyfile(trusted, out / "split.yml")
    return script


def runtime_bytes(directory):
    files = {}
    for name in ("src", "resources"):
        tree = directory / name
        require(not tree.is_symlink(), "unexpected-package-link", str(tree))
        for path in tree.rglob("*"):
            require(not path.is_symlink(), "unexpected-package-link", str(path))
            if path.is_file():
                files[path.relative_to(directory).as_posix()] = digest(path)
    return files


def verify_bytes(directory, expected):
    current = runtime_bytes(directory)
    require(current == expected, "installed-distribution-mismatch", {
        key: {"expected": expected.get(key), "actual": current.get(key)}
        for key in sorted(expected.keys() | current.keys()) if expected.get(key) != current.get(key)
    })


def command(arguments, cwd, output=None, **variables):
    result = subprocess.run(arguments, cwd=cwd, env={**os.environ, **variables}, text=True, capture_output=True, timeout=120)
    if output is not None:
        output.write_text(result.stdout + result.stderr)
    require(result.returncode == 0, "release-layout-command-failed", {"command": arguments[0], "status": result.returncode})
    return result.stdout


def stage_packages(candidate, stage, manifest, transform, constraint, version):
    expected, repositories = {}, []
    for package in PACKAGES:
        source = candidate / "packages" / package
        target = stage / "packages" / package
        target.mkdir(parents=True)
        for name in ("src", "resources"):
            if (source / name).is_dir():
                shutil.copytree(source / name, target / name)
        metadata = target / "composer.json"
        shutil.copyfile(source / "composer.json", metadata)
        command(["bash", str(transform)], stage, ZTD_SPLIT_FILE=str(metadata), ZTD_SPLIT_CONSTRAINT=constraint)
        original = json.loads((source / "composer.json").read_bytes())
        rewritten = json.loads(metadata.read_bytes())
        requirements = {name: (constraint or value) if name.startswith("k-kinzal/") else value
                        for name, value in original["require"].items()}
        require(rewritten["name"] == original["name"] and rewritten["autoload"] == original["autoload"]
                and rewritten["type"] == "library" and rewritten["require"] == requirements,
                "split-runtime-metadata-mismatch", package)
        prefix = f"packages/{package}/"
        expected[package] = {name.removeprefix(prefix): value for name, value in manifest.items()
                             if name.startswith((prefix + "src/", prefix + "resources/"))}
        verify_bytes(target, expected[package])
        repositories.append({"type": "path", "url": f"packages/{package}",
                             "options": {"symlink": False, "versions": {rewritten["name"]: version}}})
    write_json(stage / "composer.json", {"require": {f"k-kinzal/{package}": version for package in PACKAGES},
                                        "repositories": [*repositories, {"packagist.org": False}]})
    return expected


def probe(stage, evidence):
    script = TOOL / "release_layout_probe.php"
    arguments = ["docker", "run", "--pull", "never", "--rm", "--network", "none", "--read-only",
                 "--cap-drop", "ALL", "--security-opt", "no-new-privileges", "--user", "65534:65534",
                 "--pids-limit", "64", "--memory", "256m", "-v", f"{stage / 'vendor'}:/stage/vendor:ro", "-v", f"{script}:/probe.php:ro",
                 IMAGE, "php", "/probe.php"]
    raw = command(arguments, stage, evidence / "probe.log")
    results = [json.loads(line) for line in raw.splitlines()]
    pairs = {(item["platform"], item["role"]) for item in results}
    expected = {(platform, role) for platform in DIALECTS for role in COMMON}
    expected.update((platform, "Aggregation\\GroupedSelect") for platform in DIALECTS)
    expected.add(("Sqlite", "Rows\\ValuesQuery"))
    expected.update((platform, "Rows\\LocalProjection") for platform in ("MySql", "PostgreSql"))
    expected.update((platform, "Rows\\RecursiveSeries") for platform in ("MySql", "PostgreSql"))
    require(pairs == expected and len(results) == len(expected), "incomplete-installed-publication")
    for item in results:
        package = "sql-semantics-" + DIALECTS[item["platform"]]
        path = f"/stage/vendor/k-kinzal/{package}/src/Ir/" + item["role"].replace("\\", "/") + ".php"
        require(item["path"] == path and isinstance(item["sql"], str) and item["sql"] and item["profile"],
                "unexpected-installed-publication", item)
    return results


def digest_fault(installed, expected, directory):
    package = "sql-semantics-sqlite"
    role = "src/Ir/Select.php"
    path = installed / package / role
    original = path.read_bytes()
    modified = original + b"\n"
    path.write_bytes(modified)
    (directory / "installed-mutation.diff").write_text("".join(unified_diff(
        original.decode().splitlines(keepends=True), modified.decode().splitlines(keepends=True), f"a/{role}", f"b/{role}",
    )))
    before, after = expected[package][role], digest(path)
    require(before != after, "release-layout-fault-unapplied")
    try:
        verify_bytes(installed / package, expected[package])
    except Failure as failure:
        require(failure.signal == "installed-distribution-mismatch"
                and failure.detail == {role: {"expected": before, "actual": after}}, "release-layout-wrong-fault-detection", failure.detail)
        write_json(directory / "installed-mutation.json", {"control": "verified", "package": package, "path": role,
                   "injection": "installed-mutation.diff", "before": before, "after": after, "detected": failure.signal, "detail": failure.detail})
        return
    raise Failure("release-layout-fault-missed", role)


def verify(candidate, out):
    path = TOOL / "release-layout-v1.json"
    require(digest(path) == CONTRACT, "release-layout-contract-mismatch")
    specification = json.loads(path.read_bytes())
    sha, manifest = candidate_distribution(candidate)
    write_json(out / "distribution.json", manifest)
    transform = checked_recipe(candidate, specification, out)
    report = {}
    for profile, settings in specification["profiles"].items():
        evidence = out / profile
        evidence.mkdir()
        with TemporaryDirectory(prefix="ztd-ir-package-") as temporary:
            stage = Path(temporary).resolve()
            stage.chmod(0o755)
            version = settings["version"]
            expected = stage_packages(candidate, stage, manifest, transform, settings["constraint"], version)
            command(["composer", "install", "--no-interaction", "--no-plugins", "--no-scripts", "--no-dev", "--no-progress"],
                    stage, evidence / "composer.log", COMPOSER_DISABLE_NETWORK="1", COMPOSER_HOME=str(stage / "composer-home"))
            locked = {row["name"]: row["version"] for row in json.loads((stage / "composer.lock").read_bytes())["packages"]}
            require(locked == {f"k-kinzal/{package}": version for package in PACKAGES}, "installed-versions-mismatch")
            installed = stage / "vendor/k-kinzal"
            metadata = {}
            for package, files in expected.items():
                target = installed / package
                require(target.is_dir() and not target.is_symlink(), "unexpected-package-link", package)
                verify_bytes(target, files)
                metadata[package] = digest(target / "composer.json")
                require(metadata[package] == digest(stage / "packages" / package / "composer.json"),
                        "installed-metadata-mismatch", package)
            report[profile] = {"versions": locked, "packageManifests": metadata,
                               "checkedFiles": {package: len(files) for package, files in expected.items()},
                               "runtime": probe(stage, evidence)}
            digest_fault(installed, expected, evidence)
    verify_candidate(candidate, sha, manifest)
    write_json(out / "index.json", {"status": "provisional-staged-composer-layout", "candidate": sha,
                                   "distribution": digest(out / "distribution.json"), "contract": CONTRACT,
                                   "recipe": specification["recipe"], "workerImage": IMAGE,
                                   "tools": {name: digest(TOOL / name) for name in ("verify_release_layout.py", "release_layout_probe.php")},
                                   "composer": command(["composer", "--version"], out).strip(), "profiles": report})


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate", type=Path, required=True)
    parser.add_argument("--out", type=Path, required=True)
    args = parser.parse_args()
    candidate, out = args.candidate.resolve(), args.out.resolve()
    require(out != candidate and candidate not in out.parents, "evidence-directory-inside-candidate")
    out.mkdir(parents=True, exist_ok=False)
    try:
        verify(candidate, out)
    except Exception as error:
        write_json(out / "run-failure.json", {"signal": error.signal if isinstance(error, Failure) else type(error).__name__,
                                             "failure": str(error)})
        raise
