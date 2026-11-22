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
    if engine == "sqlite":
        return [
            Fault("allow-invalid-updated-return-slot", "Mutation/Update::__construct",
                  "isset($target->columns[$column->reference->slot]),", "true,"),
            Fault("skip-group-row-filter-scope", "Aggregation/GroupedSelect::__construct", "$predicates[] = [$where, 0, count($widths)];", ""),
            Fault("ignore-having-count-slot", "Aggregation/GroupedSelect::__construct", "$argument === null || isset($source->columns[$argument->slot]),", "true,"),
            Fault("skip-truth-operand-scope", "Predicate/ExpressionScope::check", ", $expression instanceof TruthTest", ""),
            Fault("skip-unary-operand-scope", "Predicate/ExpressionScope::check",
                  ", $expression instanceof UnaryExpression", ""),
            Fault("substitute-numeric-operation", "Expression/BinaryOperatorLowering::lower",
                  "ArithmeticOperator::from($operator->value)", "ArithmeticOperator::from($operator === BinaryOperator::Divide ? '%' : $operator->value)"),
            Fault("ignore-inserted-output-slot", "Mutation/Insert::__construct",
                  "isset($target->columns[$column->reference->slot]),", "true,"),
            Fault("allow-duplicate-insert-slot", "Mutation/Insert::__construct", "!isset($seen[$column->slot]),", "true,"),
            Fault("allow-supplied-default-slot", "Mutation/Insert::__construct", "$width === 0,", "true,"),
            Fault("allow-generated-implicit-target", "SourceBinding::implicitWrites", "if (!$column->generated)", "if (true)"),
            Fault("skip-cast-operand-scope", "Predicate/ExpressionScope::check",
                  "$expression instanceof CastExpression, ", ""),
            Fault("allow-invalid-returning-slot", "Mutation/Delete::__construct", "isset($target->columns[$column->reference->slot]),", "true,"),
            Fault("allow-non-target-returned-field", "Mutation/ReturnedColumn::__construct", "$reference->source === 0,", "true,"),
            Fault("skip-table-default-budget", "Schema/CreateTable::__construct", "(new ExpressionScope())->check([], $defaults);", ""),
            Fault("replace-declared-default", "Schema/CreateColumnLowering::lower", "$default === null,", "true,"),
            Fault("allow-excessive-case-branches", "Expression/CaseExpression::__construct", "count($branches) <= 32,", "true,"),
            Fault("skip-conditional-result-scope", "Predicate/ExpressionScope::choice",
                  "$operands[] = [$branch->then, $height, $visibility];", ""),
            Fault("ignore-query-write-width", "Mutation/Insert::__construct", "$width === $inputWidth,", "true,"),
            Fault("allow-local-write-query", "Mutation/Insert::__construct", "!$input->source instanceof LocalSource,", "true,"),
            Fault("allow-distinct-count-star", "Aggregation/Count::__construct", "$argument !== null || $distinctCollation === null,", "true,"),
            Fault("allow-group-key-other-occurrence", "Aggregation/GroupKey::__construct", "$column->source === 0,", "true,"),
            Fault("ignore-absent-count-slot", "Aggregation/GroupedSelect::__construct",
                  "$value->argument === null || isset($source->columns[$value->argument->slot]),", "true,"),
            Fault("ignore-resolved-collation", "Aggregation/GroupBinding::key", "$explicit ?? $default", "$default"),
        ]
    shared = [
        Fault("allow-argumentless-sum", "Aggregation/Aggregate::__construct",
              "$operator === AggregateOperator::Count && !$distinct", "!$distinct"),
        Fault("ignore-aggregate-scope", "Aggregation/GroupedSelect::__construct",
              "$value->argument === null || isset($source->columns[$value->argument->slot]),", "true,"),
        Fault("ignore-unjoined-result-key", "Aggregation/GroupedSelect::__construct",
              "$value->occurrence === 0 && isset($members[$value->slot]),", "true,"),
        Fault("ignore-negative-ordinal", "ColumnReference::__construct", "$occurrence >= 0,", "true,"),
        Fault("reverse-join-occurrences", "From/Join::inputs", "array_merge($left, $right)", "array_merge($right, $left)"),
        Fault("ignore-join-scope", "From/OccurrenceScope::checkTree", "$this->hasSlot($inputs[$operand->occurrence] ?? null, $operand->slot)", "true"),
        Fault("allow-computed-derived-slot", "From/DerivedSource::__construct",
              "$column->expression instanceof IntegerLiteral || $column->expression instanceof ColumnReference,", "true,"),
        Fault("ignore-derived-binding-identity", "From/DerivedBinding::resolved",
              "$resolution->relation === $this->input,", "true,"),
        Fault("ignore-derived-profile", "Select::__construct",
              "!$input instanceof DerivedSource || $input->definition->release === $release,", "true,"),
        Fault("ignore-query-insert-width", "Mutation/InsertQuery::__construct",
              "count($query->columns) === $width,", "true,"),
        Fault("allow-foreign-query-insert-field", "Mutation/InsertQuery::__construct",
              "$value instanceof ColumnReference || $value instanceof Arithmetic || $value instanceof IntegerLiteral || $value instanceof NullLiteral,", "true,"),
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
        Fault("ignore-recursive-working-slot", "Rows/RecursiveSeries::__construct", "$column->slot === 0,", "true,"),
        Fault("ignore-recursive-progress", "Rows/RecursiveSeries::__construct",
              "($increment->decimal[0] === '-') === ($comparison === IntegerOperator::Greater),", "true,"),
        Fault("ignore-truth-operator-arity", "Predicate/LogicalPredicate::__construct",
              "($operator === LogicalOperator::Not) === ($right === null),", "true,"),
        Fault("ignore-expanded-predicate-size", "From/OccurrenceScope::checkTree",
              "$depth <= 32 && ++$count <= 8192,", "$depth <= 32,"),
    ]
    if engine == "postgres":
        shared.append(Fault("allow-foreign-int4-operator", "ExpressionLowering::binary",
                            "($qualifiers === [] && $catalogFirst) || $qualifiers === ['pg_catalog'],", "true,"))
        shared.append(Fault("ignore-wildcard-occurrence-ambiguity", "From/JoinBinding::resolved", "$expanded === null,", "true,"))
        shared.append(Fault("accept-same-side-full-join", "From/Join::__construct",
                            "!== ($on->right->occurrence < $leftWidth)", "=== ($on->right->occurrence < $leftWidth)"))
        shared.append(Fault("allow-repeated-pg-update-targets", "Mutation/Update::__construct", "!isset($written[$slot]),", "true,"))
        shared.append(Fault("accept-unrelated-pg-compound-types", "Rows/CompoundShape::merge",
                            "$right === ResultKind::Unknown || $left === $right,", "true,"))
    if engine == "mysql":
        shared.append(Fault("ignore-intrinsic-result-label", "SelectBounds::check",
                            "$table instanceof TableSource && ($table->columns[$reference->slot]->value ?? null) === $label,", "true,"))
        shared.append(Fault("ignore-intrinsic-group-label", "Aggregation/GroupedSelect::__construct",
                            "$source->columns[$value->slot]->value === $name,", "true,"))
        shared.append(Fault("ignore-mysql-parameter-order", "SelectBounds::check", "$expression->position === ++$position,", "true,"))
        shared.append(Fault("ignore-recursive-release-capability", "Rows/RecursiveSeries::__construct",
                            "Check::input($available,", "Check::input(true,"))
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
    for engine in ("mysql", "postgres", "sqlite"):
        healthy = shape(args, dialect=engine)
        cases = faults(engine)
        require({fault.role for fault in cases} <= set(healthy["checkedBoundaries"]), "native-boundary-audit-incomplete", engine)
        results[engine] = {"healthy": healthy, "faults": [check_fault(args, engine, fault, manifest) for fault in cases]}
    verify_candidate(args.candidate, identity, manifest)
    write_json(args.out / "index.json", {
        "status": "provisional-native-construction-shape",
        "candidate": identity, "distribution": digest(args.out / "distribution.json"),
        "procedures": {name: digest(TOOL / name) for name in ("shape.php", "native_boundaries.php", "check_native_boundaries.py",
                       "native-boundaries-v13.json", "postgres-int4-boundaries-v1.json", "native-insert-query-boundaries-v2.json", "sqlite-grouped-boundaries-v4.json", "sqlite-wildcard-boundaries-v1.json", "sqlite-insert-slots-boundaries-v1.json", "sqlite-scalar-boundaries-v4.json", "sqlite-numeric-boundaries-v2.json", "sqlite-default-boundaries-v1.json", "sqlite-mutation-returning-boundaries-v1.json", "value_storage.php", "value-storage-v16.json", "value-primitives.json")},
        "results": results,
    })
    print(args.out)


if __name__ == "__main__":
    main()
