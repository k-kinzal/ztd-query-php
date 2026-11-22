"""Verify pinned native constructor shapes and reject concrete guard faults."""

import argparse
import difflib
from pathlib import Path
from typing import NamedTuple

from run import TOOL, Failure, candidate_distribution, digest, require, shape, verify_candidate, write_json


class Fault(NamedTuple):
    name: str
    role: str
    before: str
    after: str


def faults(engine):
    shared = [
        Fault("ignore-negative-ordinal", "ColumnReference::__construct", "$occurrence >= 0,", "true,"),
        Fault("reverse-join-occurrences", "From/Join::inputs", "array_merge($left, $right)", "array_merge($right, $left)"),
        Fault("ignore-join-scope", "From/OccurrenceScope::predicate", "isset($inputs[$operand->occurrence]->columns[$operand->slot])", "true"),
        Fault("ignore-insert-row-width", "Mutation/Insert::__construct", "count($row->values) === $width,", "true,"),
        Fault("ignore-update-write-scope", "Mutation/Update::__construct", "isset($target->columns[$slot]),", "true,"),
        Fault("ignore-update-read-occurrence", "Mutation/UpdateAssignment::__construct", "$value->occurrence === 0,", "true,"),
    ]
    if engine == "postgres":
        shared.append(Fault("accept-same-side-full-join", "From/Join::__construct",
                            "!== ($on->right->occurrence < $leftWidth)", "=== ($on->right->occurrence < $leftWidth)"))
        shared.append(Fault("allow-repeated-pg-update-targets", "Mutation/Update::__construct", "!isset($written[$slot]),", "true,"))
    return shared


def check_fault(args, engine, fault, distribution):
    model, _ = fault.role.split("::")
    relative = Path(f"sql-semantics-{engine}/src/Ir/{model}.php")
    original = args.candidate / "packages" / relative
    source = original.read_text()
    require(source.count(fault.before) == 1, "native-boundary-fault-anchor", fault.name)
    text = source.replace(fault.before, fault.after)
    overlay = args.out / engine / fault.name
    altered = overlay / relative
    altered.parent.mkdir(parents=True)
    altered.write_text(text)
    before, after = digest(original), digest(altered)
    require(distribution[f"packages/{relative.as_posix()}"] == before and before != after,
            "invalid-native-boundary-mutation", fault.name)
    diff = difflib.unified_diff(source.splitlines(keepends=True), text.splitlines(keepends=True),
                                original.as_posix(), altered.as_posix())
    (overlay / "injected.diff").write_text("".join(diff))

    try:
        shape(args, overlay, engine)
    except Failure as failure:
        expected = {"status": "shape-mismatch", "message": f"Native boundary differs in {fault.role}"}
        require(failure.signal == "shape-mismatch" and failure.detail == expected, "wrong-native-boundary-fault-signal",
                {"fault": fault.name, "response": failure.detail})
        record = {"name": fault.name, "role": fault.role, "healthy": "verified", "path": relative.as_posix(),
                  "source": before, "mutation": after, "expected": expected, "observed": failure.detail}
        write_json(overlay / "result.json", record)
        return record
    raise Failure("native-boundary-fault-not-detected", fault.name)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--candidate", required=True, type=Path)
    parser.add_argument("--tool-autoload", required=True, type=Path)
    parser.add_argument("--out", required=True, type=Path)
    args = parser.parse_args()
    args.candidate, args.tool_autoload, args.out = args.candidate.resolve(), args.tool_autoload.resolve(), args.out.resolve()
    require(args.out != args.candidate and args.candidate not in args.out.parents, "evidence-directory-inside-candidate")
    args.out.mkdir(parents=True, exist_ok=False)
    identity, manifest = candidate_distribution(args.candidate)
    write_json(args.out / "distribution.json", manifest)
    results = {}
    for engine in ("mysql", "postgres"):
        healthy = shape(args, dialect=engine)
        cases = faults(engine)
        require({fault.role for fault in cases} <= set(healthy["checkedBoundaries"]), "native-boundary-audit-incomplete", engine)
        results[engine] = {"healthy": healthy, "faults": [check_fault(args, engine, fault, manifest) for fault in cases]}
    verify_candidate(args.candidate, identity, manifest)
    write_json(args.out / "index.json", {
        "status": "provisional-native-construction-shape",
        "candidate": identity, "distribution": digest(args.out / "distribution.json"),
        "procedures": {name: digest(TOOL / name) for name in ("shape.php", "native_boundaries.php", "check_native_boundaries.py",
                       "native-boundaries-v1.json", "value_storage.php", "value-storage-v6.json", "value-primitives.json")},
        "results": results,
    })
    print(args.out)


if __name__ == "__main__":
    main()
