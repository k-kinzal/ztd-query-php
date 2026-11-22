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
        Fault("ignore-join-scope", "From/OccurrenceScope::checkTree", "isset($inputs[$operand->occurrence]->columns[$operand->slot])", "true"),
        Fault("ignore-insert-row-width", "Mutation/Insert::__construct", "count($row->values) === $width,", "true,"),
        Fault("ignore-update-write-scope", "Mutation/Update::__construct", "isset($target->columns[$slot]),", "true,"),
        Fault("ignore-update-read-occurrence", "Mutation/UpdateAssignment::__construct", "$value->occurrence === 0,", "true,"),
        Fault("allow-repeated-drop-targets", "Schema/DropTable::__construct", "!isset($seen[$key]),", "true,"),
        Fault("allow-invalid-savepoint-role", "Transaction/Transaction::__construct", "$named === ($name !== null),", "true,"),
        Fault("accept-mismatched-compound-width", "Rows/Compound::__construct",
              "CompoundShape::width($left) === CompoundShape::width($right),", "true,"),
        Fault("accept-excessive-compound-size", "Rows/Compound::__construct",
              "CompoundShape::arms($left) + CompoundShape::arms($right) <= 64,", "true,"),
        Fault("accept-out-of-range-smallint-default", "Schema/TableColumn::__construct",
              "$integer >= -32768 && $integer <= 32767,", "true,"),
        Fault("accept-excessive-table-width", "Schema/CreateTable::__construct", "count($checked) <= 128,", "true,"),
        Fault("allow-zero-parameter-position", "Binding/Parameter::__construct", "$position >= 1 && $position <= 1024,", "$position >= 0 && $position <= 1024,"),
        Fault("allow-missing-local-slot", "Rows/LocalProjection::__construct", "isset($definition->columns[$column->slot]),", "true,"),
        Fault("allow-negative-local-slot", "Rows/LocalResultColumn::__construct", "$slot >= 0,", "true,"),
        Fault("ignore-local-chain-bound", "Rows/LocalProjection::__construct", "++$layers <= 16,", "true,"),
        Fault("ignore-truth-operator-arity", "Predicate/LogicalPredicate::__construct",
              "($operator === LogicalOperator::Not) === ($right === null),", "true,"),
        Fault("ignore-expanded-predicate-size", "From/OccurrenceScope::checkTree",
              "$depth <= 32 && ++$count <= 8192,", "$depth <= 32,"),
    ]
    if engine == "postgres":
        shared.append(Fault("accept-same-side-full-join", "From/Join::__construct",
                            "!== ($on->right->occurrence < $leftWidth)", "=== ($on->right->occurrence < $leftWidth)"))
        shared.append(Fault("allow-repeated-pg-update-targets", "Mutation/Update::__construct", "!isset($written[$slot]),", "true,"))
        shared.append(Fault("accept-unrelated-pg-compound-types", "Rows/CompoundShape::merge",
                            "$right === ResultKind::Unknown || $left === $right,", "true,"))
    if engine == "mysql":
        shared.append(Fault("ignore-mysql-parameter-order", "SelectBounds::check", "$expression->position === ++$position,", "true,"))
        shared.append(Fault("allow-negative-integer-padding", "IntegerLiteral::__construct",
                            "$leadingZeroes >= 0 && $leadingZeroes <= self::MAX_LEADING_ZEROES,", "true,"))
    else:
        shared.append(Fault("ignore-postgres-slot-holes", "SelectBounds::check", "isset($positions[$position]),", "true,"))
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
                       "native-boundaries-v8.json", "value_storage.php", "value-storage-v9.json", "value-primitives.json")},
        "results": results,
    })
    print(args.out)


if __name__ == "__main__":
    main()
