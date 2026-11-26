"""Validate that the runtime checkout and the worker use the same package layout."""

import hashlib
import json
import os
import re
import subprocess
from pathlib import Path

PACKAGE_CONTRACT = "2ba8b065f40ff838d58150a7e388fe7c3e40bab0f88297ebb6cc508da6e79b98"
IDENTITY_CONTRACT = "46f1b5767faadd2cc4f3c2cefd501cef98015582c8d8449649650cfa999baebf"
PACKAGE_FILES = ("composer.json", "composer.lock", "src", "resources")
GIT_FORMATS = {"sha1": 40, "sha256": 64}


def checked_paths(root, packages, require, digest, tool):
    contract = tool / "runtime-package-v1.json"
    require(digest(contract) == PACKAGE_CONTRACT, "runtime-package-contract-mismatch")
    specification = json.loads(contract.read_text())
    require(set(specification["packages"]) == set(packages), "runtime-package-set-mismatch")
    require(specification["checkedPaths"] == list(PACKAGE_FILES[:2]) + [tree + "/" for tree in PACKAGE_FILES[2:]],
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
        for tree in PACKAGE_FILES[2:]:
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


def checked_commit(root, packages, require, digest, tool, expected):
    require(digest(tool / "candidate-identity-v1.json") == IDENTITY_CONTRACT, "candidate-identity-contract-mismatch")

    def git(*arguments):
        return subprocess.check_output(
            ["git", "--no-replace-objects", "-C", str(root), *arguments], timeout=60,
        )

    top, commit, object_format = git("rev-parse", "--show-toplevel", "HEAD^{commit}", "--show-object-format").decode().splitlines()
    require(Path(top).resolve() == root, "candidate-repository-root-mismatch", str(root))
    require(object_format in GIT_FORMATS and re.fullmatch(r"[0-9a-f]+", commit) is not None
            and len(commit) == GIT_FORMATS[object_format], "unsupported-candidate-object-format", object_format)
    require(expected is None or expected == commit, "candidate-checkout-mismatch", {"expected": expected, "observed": commit})
    paths = [f"packages/{package}/{target}" for package in packages for target in PACKAGE_FILES]
    entries = git("ls-tree", "-z", "-r", "--full-tree", commit, "--", *paths)
    committed = {}
    for entry in entries.split(b"\x00")[:-1]:
        metadata, name = entry.split(b"\t", 1)
        mode, kind, identifier = metadata.decode().split()
        relative = os.fsdecode(name)
        require(kind == "blob" and mode in ("100644", "100755"), "invalid-committed-runtime-entry", relative)
        committed[relative] = identifier
    return commit, object_format, committed


def checked_bytes(path, object_format):
    content = hashlib.sha256()
    with path.open("rb") as stream:
        git_blob = hashlib.new(object_format)
        git_blob.update(f"blob {os.fstat(stream.fileno()).st_size}\0".encode("ascii"))
        for block in iter(lambda: stream.read(65536), b""):
            content.update(block)
            git_blob.update(block)
    return content.hexdigest(), git_blob.hexdigest()
