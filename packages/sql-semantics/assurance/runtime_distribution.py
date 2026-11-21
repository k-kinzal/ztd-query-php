"""Validate that the runtime checkout and the worker use the same package layout."""

import json
import os
from pathlib import Path

PACKAGE_CONTRACT = "2ba8b065f40ff838d58150a7e388fe7c3e40bab0f88297ebb6cc508da6e79b98"


def checked_paths(root, packages, require, digest, tool):
    contract = tool / "runtime-package-v1.json"
    require(digest(contract) == PACKAGE_CONTRACT, "runtime-package-contract-mismatch")
    specification = json.loads(contract.read_text())
    require(set(specification["packages"]) == set(packages), "runtime-package-set-mismatch")
    require(specification["checkedPaths"] == ["composer.json", "composer.lock", "src/", "resources/"],
            "runtime-package-path-contract-mismatch")
    package_root = root / "packages"
    require(package_root.is_dir() and not package_root.is_symlink(), "invalid-package-root", str(package_root))
    for package, prefix in specification["packages"].items():
        folder = package_root / package
        require(folder.is_dir() and not folder.is_symlink(), "invalid-package-directory", package)
        files = [folder / "composer.json", folder / "composer.lock"]
        for file in files:
            require(file.is_file() and not file.is_symlink(), "invalid-package-file", str(file))
        actual = json.loads(files[0].read_text())
        requirements = specification["runtimeRequirements"].copy()
        if package not in ("sql-semantics", "sql-parser"):
            requirements.update(specification["dialectRequirements"])
        require(actual.get("name") == "k-kinzal/" + package and actual.get("type") == "library"
                and actual.get("autoload") == {"psr-4": {prefix: "src/"}} and actual.get("require") == requirements,
                "runtime-package-bootstrap-mismatch", package)
        yield from files
        for tree in ("src", "resources"):
            directory = folder / tree
            require(not directory.is_symlink() and (tree != "src" or directory.is_dir()),
                    "invalid-runtime-tree", str(directory))
            if not directory.exists():
                continue
            require(directory.is_dir(), "invalid-runtime-tree", str(directory))
            for current, subdirs, names in os.walk(directory, followlinks=False, onerror=walk_error):
                current_dir = Path(current)
                for name in subdirs:
                    require(not (current_dir / name).is_symlink(), "symlink-in-distribution", str(current_dir / name))
                for name in names:
                    file = current_dir / name
                    require(file.is_file() and not file.is_symlink(), "invalid-runtime-file", str(file))
                    yield file


def walk_error(error):
    raise error
