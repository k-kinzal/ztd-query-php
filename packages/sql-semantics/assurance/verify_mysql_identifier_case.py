"""Exhaust the native MySQL identifier case alphabet and check the distributed PHP mapping."""

import argparse
from hashlib import sha256
import json
from pathlib import Path
import subprocess

from run import Failure, TOOL, candidate_distribution, digest, require, verify_candidate, write_json


CONTRACT = "mysql-native-index-case-v1.json"
CONTRACT_SHA256 = "a1c9a4243a939dc8d13a1971277bc84f5b91e5366ef1c5a96ab41c21eb34a307"
SOURCE = "packages/sql-semantics-mysql/src/Model/Statement/Identifier/NativeIdentifierCase.php"
TABLES = tuple(
    str(Path(SOURCE).with_name("CaseTable") / (name + ".php"))
    for name in ("LatinGreekCase", "CyrillicArmenianCase", "ExtendedBmpCase")
)
SCALARS = tuple(n for n in range(1, 0x10000) if not 0xD800 <= n < 0xE000)


def specification():
    source = TOOL / CONTRACT
    require(digest(source) == CONTRACT_SHA256, "native-identifier-case-contract")
    fixed = json.loads(source.read_text())
    require(len(SCALARS) == fixed["exhaustion"]["points"], "native-identifier-case-input-size")
    return fixed


def prepare(path):
    points = [(n, chr(n).encode().hex()) for n in SCALARS]
    lines = [
        "SET NAMES utf8mb4",
        "SELECT VERSION(), @@character_set_client, @@character_set_system",
        "CREATE TEMPORARY TABLE folded_identifier (n INT PRIMARY KEY, src VARBINARY(3)) ENGINE=InnoDB",
    ]
    for start in range(0, len(points), 800):
        values = ",".join(f"({i},0x{encoded})" for i, encoded in points[start:start + 800])
        lines.append("INSERT INTO folded_identifier VALUES " + values)
    lines.append("SELECT n, HEX(src), HEX(LOWER(CONVERT(src USING utf8) COLLATE utf8_general_ci)) "
                 "FROM folded_identifier ORDER BY n")
    path.write_text(";\n".join(lines) + ";\n")


def read_native(path, release, fixed):
    expected = iter(SCALARS)
    identity = None
    output = []
    changed = {}
    with path.open() as source:
        for line in source:
            require(not line.startswith("ERROR"), "native-identifier-case-statement-error", line.rstrip())
            if not line or not line[0].isdigit():
                continue
            columns = line.rstrip("\n").split("\t")
            require(len(columns) == 3, "native-identifier-case-result-width", columns)
            n, encoded, lowered = columns
            if "." in n:
                require(identity is None and not output and n == release and encoded == "utf8mb4" and lowered in ("utf8", "utf8mb3"),
                        "native-identifier-case-release", {"expected": release, "observed": columns})
                identity = columns
                continue
            ordinal = next(expected, None)
            before, after = (bytes.fromhex(item).decode() for item in (encoded, lowered))
            require(ordinal is not None and ordinal == int(n) and before == chr(ordinal) and len(after) == 1,
                    "native-identifier-case-scalar", {"expected": ordinal, "observed": columns})
            output.append(after)
            if before != after:
                changed[before] = after

    require(identity is not None and next(expected, None) is None and len(output) == fixed["exhaustion"]["points"],
            "native-identifier-case-domain-incomplete", release)
    mapping = sha256(json.dumps(changed, ensure_ascii=False, separators=(",", ":")).encode()).hexdigest()
    require(mapping == fixed["exhaustion"]["mappingDigest"] and len(changed) == fixed["exhaustion"]["changed"],
            "native-identifier-case-mapping-mismatch", release)
    return output, {"charset": identity[2], "source": digest(path), "points": len(output),
                    "changed": len(changed), "mapping": mapping}


def observed_php(path):
    body = r"""
foreach (array_slice($argv, 1) as $path) {
    require $path;
}
$case = new \SqlSemantics\Platform\MySql\Model\Statement\Identifier\NativeIdentifierCase();
$inputs = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
echo json_encode(array_map($case->fold(...), $inputs), JSON_THROW_ON_ERROR);
"""
    request = [chr(n) for n in SCALARS]
    tables = [path.parent / "CaseTable" / Path(name).name for name in TABLES]
    process = subprocess.run(["php", "-n", "-r", body, *map(str, tables), str(path)], input=json.dumps(request), capture_output=True, text=True, timeout=60)
    require(process.returncode == 0, "native-identifier-case-php-error", process.stderr)
    result = json.loads(process.stdout)
    require(len(result) == len(request), "native-identifier-case-php-domain-incomplete")
    return result


def verify(args, fixed):
    root = args.candidate.resolve()
    require(root not in args.out.resolve().parents, "native-identifier-case-receipt-inside-candidate")
    revision, manifest = candidate_distribution(root, args.commit)
    native = dict(entry.split("=", 1) for entry in args.native)
    require(len(native) == len(args.native) and set(native) == set(fixed["releases"]), "native-identifier-case-release-set")
    path = root / SOURCE
    sources = {name: digest(root / name) for name in (SOURCE, *TABLES)}
    require(all(manifest.get(name) == value for name, value in sources.items()), "native-identifier-case-distribution-mismatch")
    php = observed_php(path)
    evidence = {}
    for release in fixed["releases"]:
        expected, record = read_native(Path(native[release]), release, fixed)
        if php != expected:
            for code, actual, original in zip(SCALARS, php, expected):
                if actual != original:
                    raise Failure("native-identifier-case-php-scalar", {"code": code, "actual": actual, "expected": original})
        evidence[release] = record

    verify_candidate(root, revision, manifest)
    write_json(args.out, {"candidate": revision, "caseSources": sources, "contract": CONTRACT_SHA256,
                          "checker": digest(Path(__file__)), "profiles": evidence, "status": "bounded-exhaustion"})


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    routes = parser.add_subparsers(dest="mode", required=True)
    prepare_route = routes.add_parser("prepare")
    prepare_route.add_argument("--out", required=True, type=Path)
    replay = routes.add_parser("replay")
    replay.add_argument("--candidate", required=True, type=Path)
    replay.add_argument("--commit", required=True)
    replay.add_argument("--out", required=True, type=Path)
    replay.add_argument("--native", action="append", required=True, metavar="RELEASE=PATH")
    args = parser.parse_args()
    fixed = specification()
    if args.mode == "prepare":
        prepare(args.out)
    else:
        verify(args, fixed)


if __name__ == "__main__":
    main()
