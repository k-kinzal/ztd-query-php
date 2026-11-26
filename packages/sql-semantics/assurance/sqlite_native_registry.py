"""Extract SQLite function, collation and module registrations from the pinned amalgamation."""
import argparse
import hashlib
import json
from pathlib import Path
from shutil import copyfile
import subprocess
import sys
from tempfile import TemporaryDirectory

from sqlite_pragma_inventory import ENGINE_SOURCE, SOURCE_DIGEST

DRIVER = Path(__file__).with_name("sqlite_native_registry.c")
DRIVER_DIGEST = "15bc673ad3ca4aadda5d02fa62e1ced612bf2f0a8be15fa2840fa8528b4d3a40"
EXPECTED_NATIVE_BYTES = "7b7d0ac080ff2f1175a7f9bce1fcb8b0325a57340b09c61b72c9e67c566cbe9b"


def snapshot(raw):
    digest = hashlib.sha256(raw).hexdigest()
    if digest != EXPECTED_NATIVE_BYTES:
        raise ValueError("Native registry differs from the fixed SQLite build profiles")
    header, *lines = [line.split("\t") for line in raw.decode().splitlines()]
    if header != ["S", ENGINE_SOURCE]:
        raise ValueError("Native registry source ID differs from the fixed release")
    functions, collations, modules = [], [], []
    for kind, *fields in lines:
        if kind == "F":
            name, builtin, role, encoding, arity, flags = fields
            functions.append([name, int(builtin), role, encoding, int(arity), int(flags)])
        elif kind == "C":
            name, = fields
            collations.append(name)
        elif kind == "M":
            name, = fields
            modules.append(name)
        else:
            raise ValueError(f"Unknown native registry row kind: {kind}")
    return {"revision": "SemanticsIR/sqlite-native-registry-1", "amalgamation": SOURCE_DIGEST,
            "source": ENGINE_SOURCE, "driver": DRIVER_DIGEST, "nativeOutput": digest,
            "functions": functions, "collations": collations, "modules": modules}


def regenerate(source, compiler):
    if hashlib.sha256(source.read_bytes()).hexdigest() != SOURCE_DIGEST:
        raise ValueError("SQLite amalgamation differs from the fixed release")
    if hashlib.sha256(DRIVER.read_bytes()).hexdigest() != DRIVER_DIGEST:
        raise ValueError("Native registry driver differs from the fixed contract")
    with TemporaryDirectory(prefix="sqlite-native-registry-") as directory:
        root = Path(directory)
        copyfile(source, root / "sqlite3.c")
        driver = root / "registry.c"
        copyfile(DRIVER, driver)
        program = root / "registry"
        flags = ["-pthread", "-ldl", "-lm"] if sys.platform.startswith("linux") else []
        subprocess.run([compiler, "-O0", "-I", str(root), str(driver), "-o", str(program), *flags], check=True)
        return snapshot(subprocess.check_output([str(program)]))


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--sqlite-source", required=True, type=Path)
    parser.add_argument("--compiler", default="cc")
    parser.add_argument("--out", required=True, type=Path)
    args = parser.parse_args()
    data = regenerate(args.sqlite_source, args.compiler)
    with args.out.open("x") as output:
        json.dump(data, output, indent=2, ensure_ascii=False)
        output.write("\n")


if __name__ == "__main__":
    main()
