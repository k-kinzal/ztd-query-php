"""Verify pinned native constructor shapes and reject concrete guard faults."""

import argparse
import difflib
from pathlib import Path
from typing import NamedTuple, Optional

from run import TOOL, Failure, candidate_distribution, digest, require, shape, verify_candidate, write_json


class Fault(NamedTuple):
    name: str
    role: str
    before: str
    after: str
    artifact: Optional[str] = None


def faults(engine):
    delimiter = "`" if engine == "mysql" else '"'
    quoting = f"'{delimiter}' . str_replace('{delimiter}', '{delimiter}{delimiter}', $this->value) . '{delimiter}'"
    identifier = [
        Fault("discard-quoted-identifier-value", "Identifier::quoted", quoting, f"'{delimiter}{delimiter}'"),
        Fault("admit-unchecked-identifier-encoding", "Identifier::__construct",
              " === 1,", " !== 2,"),
    ]
    if engine != "postgres":
        identifier.append(Fault("drop-identifier-folding", "Identifier::key",
                                "Comparison::AsciiInsensitive->fold($this->value)", "$this->value"))
    if engine == "sqlite":
        return [*identifier,
            Fault("allow-handler-after-default-values", "Mutation/Insert::__construct",
                  "$handler === InsertConflictHandler::None,", "true,"),
            Fault("ignore-targeted-upsert", "Mutation/InsertLowering::handler",
                  "&& $clause->target === null", ""),
            Fault("ignore-replace-verb", "Mutation/InsertLowering::lower",
                  "$into->replace ? ConflictResolution::Replace : $into->resolution", "$into->resolution"),
            Fault("lower-update-fail-as-ignore", "Mutation/UpdateLowering::lower",
                  "ConflictResolution::Fail => UpdateConflict::Fail", "ConflictResolution::Fail => UpdateConflict::Ignore"),
            Fault("admit-glob-escape", "Predicate/Pattern/PatternTest::__construct",
                  "$kind !== PatternKind::Glob || $escape === null,", "true,"),
            Fault("skip-like-escape-scope", "Predicate/ExpressionScope::pattern",
                  "$operands[] = [$expression->escape, $height, $visibility];", ""),
            Fault("discard-live-membership-operand", "Platform::discardedBindings",
                  "$object->items === []", "$object->items !== []", "src/Platform.php"),
            Fault("skip-full-syntax-audit", "Publication::facts",
                  "$objects = $graph->objects($statement);", "$objects = [$statement];", "src/Model/Validation/Publication.php"),
            Fault("skip-sqlite-select-expression-scope", "From/SelectBounds::check", "(new ExpressionScope())->check($widths, $pending);", ""),
            Fault("skip-sqlite-select-boundary", "Select::__construct",
                  "(new SelectBounds())->check($results, $source, $this->joins, $where, $this->orderBy, $limit);", ""),
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
            Fault("skip-table-default-budget", "Schema/CreateTable::__construct", "(new ExpressionScope())->check([count($checked)], $conditions, false);", ""),
            Fault("allow-unbound-check-operand", "Schema/Predicate/CheckConstraint::__construct",
                  "(new ExpressionScope())->check([2000], [[$condition, 0, 1]], false);", "(new ExpressionScope())->check([2000], [[$condition, 0, 1]]);"),
            Fault("allow-all-check-parameters", "Predicate/ExpressionScope::parameterBytes",
                  "$allowLiveParameters || $registration", "true"),
            Fault("deny-folded-check-registration", "Predicate/ExpressionScope::branches",
                  "[$binding, $height, $visibility, true]", "[$binding, $height, $visibility]"),
            Fault("ignore-owning-check-slot", "Schema/CreateTable::__construct",
                  "($check->column === null || isset($checked[$owner])) && $owner >= $lastOwner", "$owner >= $lastOwner"),
            Fault("retain-check-list-reference", "Schema/CreateTable::__construct",
                  "$this->checks = $orderedChecks;", "$this->checks = $checks;"),
            Fault("rename-always-quotes", "Schema/Alter/RenameLowering::lower",
                  "$destination, $statement->quoteReferences", "$destination, true"),
            Fault("admit-case-equivalent-table-rename", "Schema/Alter/RenameTable::__construct",
                  "$table->key() !== $destination->key()", "true"),
            Fault("allow-arbitrary-bare-rename-destination", "Schema/Alter/RenameColumn::__construct",
                  "$quoteReferences || (", "true || ("),
            Fault("allow-external-population-source", "Schema/Query/CreateTableAs::__construct",
                  "!$query instanceof Select || $query->source === null,", "true,"),
            Fault("skip-native-population-layout", "Schema/Query/CreateTableAs::__construct",
                  "(new CreateAsLayout())->columns($query);", ""),
            Fault("ignore-first-row-affinity", "Schema/Query/CreateAsLayout::values",
                  "count($query->rows) > 1 && $this->hasAffinity($query, $types)", "false"),
            Fault("allow-nonexistent-key-slot", "Schema/Key/TableKeyLayout::terms", "isset($columns[$term->slot]),", "true,"),
            Fault("allow-keyless-without-rowid", "Schema/Key/TableKeyLayout::check", "!$withoutRowid || $primary !== null,", "true,"),
            Fault("allow-several-inline-key-terms", "Schema/Key/PrimaryKey::__construct", "!$inline || count($checked) === 1,", "true,"),
            Fault("allow-automatic-non-alias", "Schema/Key/TableKeyLayout::check",
                  "!$key->autoIncrement || $rowidAlias,", "true,"),
            Fault("accept-conflicting-index-policies", "Schema/Key/TableKeyLayout::check",
                  "$prior === null || $key->conflict === null || $prior === $key->conflict,", "true,"),
            Fault("skip-key-layout-placement", "Schema/Key/TableKeyLayout::check", "$placement >= $lastInline,", "true,"),
            Fault("ignore-key-list-copy", "Schema/CreateTable::__construct", "$this->keys = $orderedKeys;", "$this->keys = $keys;"),
            Fault("allow-automatic-inline-descending-key", "Schema/Key/PrimaryKey::__construct",
                  "!$autoIncrement || !$inline || !$checked[0]->descending,", "true,"),
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
    shared = [*identifier,
        Fault("skip-range-upper-slot", "From/OccurrenceScope::checkTree",
              "[$operand->subject, $operand->lower, $operand->upper]", "[$operand->subject, $operand->lower]"),
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
              "$value instanceof ColumnReference || $value instanceof Arithmetic || "
              + ("$value instanceof IntegerCase || " if engine == "postgres" else "")
              + "$value instanceof IntegerLiteral || $value instanceof NullLiteral,", "true,"),
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
        shared.extend([
            Fault("allow-conflicting-input-type", "Binding/ParameterSignature::typeFor",
                  "$current === null || $current === $parameter->input", "true"),
            Fault("drop-compound-input-check", "Rows/Compound::__construct", "ParameterSignature::check($left, $right);", ""),
            Fault("overwrite-inferred-input-type", "Binding/ParameterLowering::lower",
                  "$inputTypes[$position] ??= $targets[0]", "$inputTypes[$position] = $targets[0]"),
            Fault("lose-wide-token-magnitude", "Numerals::integer",
                  "$this->integral($text) ? $this->decimal($text) : null", "$this->integral($text) ? '2147483648' : null",
                  "src/Rules/Lexical/Numerals.php"),
            Fault("allow-nonexistent-wide-boolean-cast", "Expression/BuiltinCast::__construct",
                  "!($source === CastDestination::Boolean && $target === CastDestination::WideInteger)", "true"),
            Fault("reject-upper-bigint-endpoint", "Expression/WideIntegerLiteral::__construct",
                  "'9223372036854775807'", "'9223372036854775806'"),
            Fault("derive-bigint-cast-as-int4", "Rows/CompoundShape::type",
                  "CastDestination::WideInteger => ResultKind::WideInteger", "CastDestination::WideInteger => ResultKind::Integer"),
        ])
        shared.append(Fault("skip-cast-child-scope", "SelectBounds::check",
                            "$expression instanceof BuiltinCast => $pending[] = [$expression->operand, $height + 1],",
                            "$expression instanceof BuiltinCast => null,"))
        shared.append(Fault("replace-builtin-bool-cast-target", "Expression/BuiltinCastLowering::lower",
                            "Builtin::Bool => CastDestination::Boolean,", "Builtin::Bool => CastDestination::Integer,"))
        shared.append(Fault("lose-compound-cast-type", "Rows/CompoundShape::type",
                            "CastDestination::Boolean => ResultKind::Boolean,", "CastDestination::Boolean => ResultKind::Integer,"))
        shared.append(Fault("ignore-truth-test-child", "From/OccurrenceScope::checkTree",
                            "$operand instanceof NullTest, $operand instanceof TruthTest => [$operand->operand]",
                            "$operand instanceof NullTest => [$operand->operand], $operand instanceof TruthTest => []"))
        shared.append(Fault("lower-foreign-cast-as-boolean-null", "Predicate/TruthTestLowering::lower",
                            "$type->descriptor === Builtin::Bool", "$type->descriptor === Builtin::Int4"))
        shared.append(Fault("skip-distinctness-right-slot", "From/OccurrenceScope::checkTree",
                            "$operand instanceof IntegerComparison, $operand instanceof IntegerDistinctness => [$operand->left, $operand->right]",
                            "$operand instanceof IntegerComparison => [$operand->left, $operand->right], $operand instanceof IntegerDistinctness => [$operand->left]"))
        shared.append(Fault("accept-foreign-distinctness", "Predicate/IntegerDistinctnessLowering::lower",
                            "Check::input($catalogFirst,", "Check::input(true,"))
        shared.append(Fault("accept-unknown-comparison-pair", "Predicate/IntegerRangeLowering::lower",
                            "$knownSubject || ($knownLower && $knownUpper)", "true"))
        shared.append(Fault("admit-invisible-pg-write-slot", "Mutation/Insert/Destination::__construct",
                            "$column->occurrence === 0 && isset($relation->columns[$column->slot]),", "true,"))
        shared.append(Fault("admit-duplicate-pg-write-slot", "Mutation/Insert/Destination::__construct",
                            "!isset($seen[$column->slot]),", "true,"))
        shared.append(Fault("skip-insert-return-layout", "Mutation/Insert::__construct",
                            "(new ReturningLayout())->check($target->relation, $checked);", ""))
        shared.append(Fault("skip-query-insert-return-layout", "Mutation/InsertQuery::__construct",
                            "(new ReturningLayout())->check($target->relation, $checked);", ""))
        shared.append(Fault("allow-non-target-return-slot", "Mutation/ReturnedColumn::__construct",
                            "$reference->occurrence === 0,", "true,"))
        shared.append(Fault("allow-absent-pg-return-slot", "Mutation/ReturningLayout::check",
                            "isset($target->columns[$field->reference->slot]),", "true,"))
        shared.append(Fault("skip-delete-return-layout", "Mutation/Delete::__construct",
                            "(new ReturningLayout())->check($target, $checked);", ""))
        shared.append(Fault("skip-update-return-layout", "Mutation/Update::__construct",
                            "(new ReturningLayout())->check($target, $checked);", ""))
        shared.append(Fault("allow-excessive-int4-case-arms", "Expression/IntegerCase::__construct",
                            "count($arms) <= 32,", "true,"))
        shared.append(Fault("ignore-case-condition-scope", "SelectBounds::check",
                            "$nodes = $scope->checkTree($arm->condition, $inputs, 'A CASE condition slot must belong to the SELECT source.', $height + 1, $nodes);", ""))
        shared.append(Fault("ignore-case-result-scope", "SelectBounds::check",
                            "$pending[] = [$arm->result, $height + 1];", ""))
        shared.append(Fault("allow-foreign-int4-operator", "ExpressionLowering::binary",
                            "($qualifiers === [] && $catalogFirst) || $qualifiers === ['pg_catalog'],", "true,"))
        shared.append(Fault("ignore-wildcard-occurrence-ambiguity", "From/JoinBinding::resolved", "$expanded === null,", "true,"))
        shared.append(Fault("accept-same-side-full-join", "From/Join::__construct",
                            "!== ($on->right->occurrence < $leftWidth)", "=== ($on->right->occurrence < $leftWidth)"))
        shared.append(Fault("allow-repeated-pg-update-targets", "Mutation/Update::__construct", "!isset($written[$slot]),", "true,"))
        shared.append(Fault("accept-unrelated-pg-compound-types", "Rows/CompoundShape::merge",
                            "$right === ResultKind::Unknown || $left === $right,", "true,"))
    if engine == "mysql":
        shared.append(Fault("ignore-signed-cast-slot", "SelectBounds::check",
                            "$expression instanceof SignedCast, $expression instanceof UnsignedCast => $pending[] = [$expression->operand, $height + 1],",
                            "$expression instanceof SignedCast, $expression instanceof UnsignedCast => null,"))
        shared.append(Fault("admit-unchecked-integer-cast-role", "Expression/IntegralCastLowering::lower",
                            "($target === CastKind::Signed || $target === CastKind::Unsigned)", "true"))
        shared.append(Fault("erase-unsigned-set-promotion", "Rows/CompoundShape::merge",
                            "ResultKind::Integer, ResultKind::WideInteger => ResultKind::DecimalInteger,",
                            "ResultKind::Integer, ResultKind::WideInteger => ResultKind::UnsignedInteger,"))
        shared.append(Fault("erase-signed-compound-type", "Rows/CompoundShape::type",
                            ", $column instanceof SignedCast => ResultKind::WideInteger", " => ResultKind::WideInteger"))
        shared.append(Fault("admit-an-unsigned-bound", "Predicate/RangeIntegerLiteral::__construct",
                            "'9223372036854775807'", "'9223372036854775808'"))
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
    relative = Path(f"sql-semantics-{engine}") / (fault.artifact or f"src/Ir/{model}.php")
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
        label = {"sqlite": "Folded binding", "postgres": "Integer binding"}.get(engine)
        message = f"{label} source differs in {fault.artifact}" if fault.artifact else f"Native boundary differs in {fault.role}"
        expected = {"status": "shape-mismatch", "message": message}
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
        require({fault.role for fault in cases if not fault.artifact} <= set(healthy["checkedBoundaries"])
                and {fault.artifact for fault in cases if fault.artifact} <= healthy["checkedBindingSources"].keys(),
                "native-boundary-audit-incomplete", engine)
        results[engine] = {"healthy": healthy, "faults": [check_fault(args, engine, fault, manifest) for fault in cases]}
    verify_candidate(args.candidate, identity, manifest)
    write_json(args.out / "index.json", {
        "status": "provisional-native-construction-shape",
        "candidate": identity, "distribution": digest(args.out / "distribution.json"),
        "procedures": {name: digest(TOOL / name) for name in ("shape.php", "native_boundaries.php", "check_native_boundaries.py",
                       "native-boundaries-v27.json", "identifier-boundaries-v1.json", "postgres-mutation-returning-boundaries-v2.json", "native-insert-query-boundaries-v4.json", "sqlite-grouped-boundaries-v5.json", "sqlite-wildcard-boundaries-v1.json", "sqlite-insert-slots-boundaries-v3.json", "sqlite-scalar-boundaries-v13.json", "sqlite-numeric-boundaries-v2.json", "sqlite-default-boundaries-v8.json", "sqlite-rename-boundaries-v1.json", "sqlite-rename-v4.json", "sqlite-mutation-returning-boundaries-v2.json", "sqlite-query-scope-boundaries-v1.json", "sqlite-conflict-boundaries-v2.json", "sqlite-folded-binding-sources-v2.json", "postgres-integer-binding-sources-v1.json", "value_storage.php", "value-storage-v25.json", "value-primitives.json")},
        "results": results,
    })
    print(args.out)


if __name__ == "__main__":
    main()
