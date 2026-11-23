"""Verify pinned native constructor shapes and reject concrete guard faults."""

import argparse
import difflib
from hashlib import sha256
from pathlib import Path
from typing import NamedTuple, Optional

from checked_scalar_dispatch import certificate as dispatch_certificate
from checked_value_graph import DIALECTS, certificate
from scope import declared_constructors
from run import TOOL, Failure, candidate_distribution, digest, require, shape, verify_candidate, write_json


ENGINES = ("mysql", "postgres", "sqlite")
FAULT_REPLAY_NAME = "native-boundary-fault-replay-v1.json"
FAULT_REPLAY = "24245e8ebb14fe057a5e6e7b0545f936b2176ee2c179ac4fc2d99659befe3d51"


class Fault(NamedTuple):
    name: str
    role: str
    before: str
    after: str
    artifact: Optional[str] = None
    message: Optional[str] = None


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
            Fault("reverse-bound-merge-operand", "From/JoinKeyLowering::predicate",
                  "new ColumnReference($binding->slot($occurrence, $column), $occurrence),",
                  "new ColumnReference($binding->slot($rightOccurrence, $right[$key]), $rightOccurrence),"),
            Fault("replace-first-prefix-key-with-last", "From/SourceDeclarations::prefixHeading",
                  "$fields[$name] ??= [$occurrence, $column];", "$fields[$name] = [$occurrence, $column];"),
            Fault("allow-unsupported-merged-prefix", "From/JoinKeyLowering::predicate",
                  "count($chain->steps) === 1", "count($chain->steps) >= 1"),
            Fault("replace-full-merge-right-operand", "SourceBinding::value",
                  "$this->value($merge->right)", "$this->value($merge->left)"),
            Fault("ignore-right-merged-representative", "VisibleRelation::resolved",
                  "$merged ? ($this->representatives[$position] ?? null) : null", "null",
                  "src/Model/Resolution/VisibleRelation.php", "Merged join binding source differs in src/Model/Resolution/VisibleRelation.php"),
            Fault("forget-right-merged-slot", "Joining::join", "[...$hidden->hidden, $into[1]]", "$hidden->hidden",
                  "src/Rules/Resolution/Joining.php", "Merged join binding source differs in src/Rules/Resolution/Joining.php"),
            Fault("use-physical-name-for-full-merge", "ResultNames::resolved",
                  "$resolution->merge === null ? $resolution->slot->name : $this->span($column)", "$resolution->slot->name",
                  "src/Rules/Query/ResultNames.php", "SQLite view binding source differs: src/Rules/Query/ResultNames.php"),
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
            Fault("ignore-joined-local-in-insert-input", "Mutation/Insert::__construct", "(new LocalInputs())->occurrences($input->source, $input->joins) === []", "!$input->source instanceof LocalSource"),
            Fault("ignore-local-in-compound-arm", "Rows/CompoundBounds::check", "(new LocalInputs())->occurrences($arm->source, $arm->joins) === []", "!$arm->source instanceof LocalSource"),
            Fault("rebind-local-occurrence-to-first", "SourceBinding::localSlot", "new ColumnReference($index, $source)", "new ColumnReference($index)"),
            Fault("allow-two-local-definitions", "Rows/LocalInputs::checked", "$use === $local", "true"),
            Fault("skip-sqlite-select-expression-scope", "From/SelectBounds::check", "(new ExpressionScope())->check($widths, $pending, $allowParameters, $allowParameters);", ""),
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
            Fault("ignore-view-ownership-during-binding", "Derivation::table",
                  "$owningSchema = $environment->owningSchema();", "$owningSchema = null;",
                  "src/Model/Construction/Derivation.php", "SQLite view binding source differs: src/Model/Construction/Derivation.php"),
            Fault("ignore-persistent-view-source-schema", "Schema/View/ViewQueryBounds::source",
                  "$schema->key() === 'temp' || $schema->key() === $source->schema->key()", "true"),
            Fault("accept-view-in-write-boundary", "Mutation/Insert::__construct",
                  "$target->kind === ExternalRelationKind::BaseTable", "true"),
            Fault("forget-view-kind-during-binding", "SourceBinding::source",
                  "RelationKind::View => ExternalRelationKind::View", "RelationKind::View => ExternalRelationKind::BaseTable"),
            Fault("ignore-local-view-placement-scope", "Schema/View/ViewQueryBounds::check",
                  "$query->where, $query->orderBy, $query->limit, false);",
                  "$query->where, $query->orderBy, $query->limit, true);"),
            Fault("allow-compound-view-affinity", "Schema/View/CompoundViewBounds::types",
                  "!$value instanceof ColumnReference && !$value instanceof CastExpression", "true"),
            Fault("ignore-compound-view-header-budget", "Rows/CompoundBounds::check",
                  "$bytes = $prefixBytes;", "$bytes = 0;"),
            Fault("allow-grouped-view-parameters", "Schema/View/ViewQueryBounds::grouped",
                  "(new ExpressionScope())->check($widths, $pending, false, false);",
                  "(new ExpressionScope())->check($widths, $pending);"),
            Fault("allow-inert-view-parameters", "Schema/View/ViewQueryBounds::check",
                  "(new ExpressionScope())->check([], $pending, false, false);",
                  "(new ExpressionScope())->check([], $pending, false);"),
            Fault("ignore-drop-view-kind", "Schema/DropRelationLowering::lower",
                  "$resolution->table->kind === $kind", "true"),
            Fault("allow-reserved-drop-view", "Schema/DropView::__construct",
                  "!str_starts_with($view->key(), 'sqlite_')", "true"),
            Fault("skip-generated-cycle-check", "Schema/Generation/GeneratedGraph::check",
                  "$ordered === count($remaining)", "true"),
            Fault("allow-generated-default", "Schema/TableColumn::__construct",
                  "$generated === null || $default === null", "true"),
            Fault("accept-runtime-generated-marker", "Schema/Generation/ColumnGeneration::__construct",
                  "(new ExpressionScope())->check([2000], [[$expression, 0, 1]], false);",
                  "(new ExpressionScope())->check([2000], [[$expression, 0, 1]]);"),
            Fault("skip-generation-dependency-validation", "Schema/CreateTable::__construct",
                  "(new GeneratedGraph())->check($checked, $primaryKey);", ""),
            Fault("ignore-implicit-check-column", "Schema/Predicate/CheckConstraint::checkPresentation",
                  "$declared !== null && $declared->name->key() === $label->columnName->key()", "true"),
            Fault("ignore-implicit-check-operation", "Schema/Predicate/CheckConstraint::__construct",
                  "&& $condition->operator === $label->operation()", ""),
            Fault("skip-implicit-check-binding", "Schema/CreateTable::__construct", "$check->checkPresentation($checked);", ""),
            Fault("allow-unbound-check-operand", "Schema/Predicate/CheckConstraint::__construct",
                  "(new ExpressionScope())->check([2000], [[$condition, 0, 1]], false);", "(new ExpressionScope())->check([2000], [[$condition, 0, 1]]);"),
            Fault("allow-all-check-parameters", "Predicate/ExpressionScope::parameterBytes",
                  "$allowLiveParameters || ($allowRegistrations && $registration)", "true"),
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
            Fault("admit-excessive-coalesce-arity", "Expression/Nulls/Coalesce::__construct", "count($arguments) <= 127", "true"),
            Fault("skip-coalesce-argument-scope", "Predicate/ExpressionScope::coalesce",
                  "$operands[] = [$argument->expression, $height, $visibility];", ""),
            Fault("omit-coalesce-branch", "SelectSql::expression",
                  "$expression instanceof Coalesce => $this->coalesce($expression, $source, $joins),", "",
                  message="Incomplete scalar dispatch in expression"),
            Fault("allow-excessive-case-branches", "Expression/CaseExpression::__construct", "count($branches) <= 32,", "true,"),
            Fault("skip-conditional-result-scope", "Predicate/ExpressionScope::choice",
                  "$operands[] = [$branch->then, $height, $visibility];", ""),
            Fault("ignore-query-write-width", "Mutation/Insert::__construct", "$width === $inputWidth,", "true,"),
            Fault("allow-local-write-query", "Mutation/Insert::__construct",
                  "(new LocalInputs())->occurrences($input->source, $input->joins) === [],", "true,"),
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
              "$value->argument === null || $source->columnKind($value->argument->slot) === ColumnKind::Int4" if engine == "postgres"
              else "$value->argument === null || isset($source->columns[$value->argument->slot]),", "true" if engine == "postgres" else "true,"),
        Fault("ignore-unjoined-result-key", "Aggregation/GroupedSelect::__construct",
              "$value->occurrence === 0 && isset($members[$value->slot]),", "true,"),
        Fault("ignore-negative-ordinal", "ColumnReference::__construct", "$occurrence >= 0,", "true,"),
        Fault("reverse-join-occurrences", "From/Join::inputs", "array_merge($left, $right)", "array_merge($right, $left)"),
        Fault("ignore-join-scope", "From/OccurrenceScope::checkTree",
              "$this->column($operand, $inputs, $invalidSlot);" if engine == "postgres"
              else "$this->hasSlot($inputs[$operand->occurrence] ?? null, $operand->slot)",
              "" if engine == "postgres" else "true"),
        Fault("allow-computed-derived-slot", "From/DerivedSource::__construct",
              "|| $column->expression instanceof TextColumnReference," if engine == "postgres"
              else "$column->expression instanceof IntegerLiteral || $column->expression instanceof ColumnReference,",
              "|| true," if engine == "postgres" else "true,"),
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
        Fault("ignore-update-write-scope", "Mutation/Update::__construct",
              "$scope->column($assignment->target, [$target], 'An UPDATE write slot must belong to the target.');"
              if engine == "postgres" else "isset($target->columns[$slot]),", "" if engine == "postgres" else "true,"),
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
                            "$relation->columnKind($column->slot) === $column->kind", "true"))
        shared.append(Fault("admit-duplicate-pg-write-slot", "Mutation/Insert/Destination::__construct",
                            "!isset($seen[$column->slot]),", "true,"))
        shared.append(Fault("skip-insert-return-layout", "Mutation/Insert::__construct",
                            "(new ReturningLayout())->check($target->relation, $checked);", ""))
        shared.append(Fault("skip-query-insert-return-layout", "Mutation/InsertQuery::__construct",
                            "(new ReturningLayout())->check($target->relation, $checked);", ""))
        shared.append(Fault("allow-non-target-return-slot", "Mutation/ReturnedColumn::__construct",
                            "$reference->occurrence === 0,", "true,"))
        shared.append(Fault("allow-absent-pg-return-slot", "Mutation/ReturningLayout::check",
                            "$target->columnKind($field->reference->slot) === $kind,", "true,"))
        shared.append(Fault("admit-nonlocal-postgres-write-slot", "Mutation/Insert/WriteSlot::__construct",
                            "$occurrence === 0,", "true,"))
        shared.append(Fault("ignore-postgres-insert-cell-kind", "Mutation/Insert::__construct",
                            "$cell->value === null || ($cell->value instanceof TextLiteral) === ($target->written[$index]->kind === ColumnKind::Text),",
                            "true,"))
        shared.append(Fault("ignore-postgres-update-read-native-kind", "Mutation/Update::__construct",
                            "$scope->column($value, [$target], 'An UPDATE read slot must belong to the target.');", ""))
        shared.append(Fault("ignore-postgres-assignment-kind", "Mutation/UpdateAssignment::__construct",
                            "$value instanceof NullLiteral || ($target instanceof TextColumnReference) === ($value instanceof TextColumnReference || $value instanceof TextLiteral),",
                            "true,"))
        shared.append(Fault("ignore-postgres-returned-column-kind", "Mutation/ReturningLayout::check",
                            "$target->columnKind($field->reference->slot) === $kind",
                            "$target->columnKind($field->reference->slot) !== null"))
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
        shared.append(Fault("ignore-complete-postgres-declarations", "Catalog::build",
                            "$scope = $platform->context($this->profile, $this->searchPath, $tables, $closed);",
                            "$scope = $platform->context($this->profile, $this->searchPath, $tables, false);",
                            "src/Model/Facade/Catalog.php", "PostgreSQL declaration binding source differs in src/Model/Facade/Catalog.php"))
        shared.append(Fault("ignore-postgres-temporary-type-shadow", "TypeLookup::find",
                            "$schema->value !== 'pg_temp' || $context->declared($qualified, $schema) !== []",
                            "$schema->value !== 'pg_temp'", "src/Rules/Typing/TypeLookup.php"))
        shared.append(Fault("allow-postgres-text-presence-on-integer-slot", "From/OccurrenceScope::column",
                            "$input?->columnKind($column->slot) === ColumnKind::Text",
                            "$input?->columnKind($column->slot) !== null"))
        shared.append(Fault("coerce-postgres-derived-text-kind", "From/DerivedSource::columnKind",
                            "$column->expression instanceof TextColumnReference ? ColumnKind::Text : ColumnKind::Int4",
                            "ColumnKind::Int4"))
        shared.append(Fault("skip-postgres-column-kind-width", "TableSource::__construct",
                            "$kinds === [] || count($kinds) === count($names),", "true,"))
        shared.append(Fault("allow-postgres-integer-on-text-slot", "From/OccurrenceScope::hasSlot",
                            "$input?->columnKind($slot) === ColumnKind::Int4", "$input?->columnKind($slot) !== null"))
        shared.append(Fault("admit-invalid-postgres-text-utf8", "Expression/TextLiteral::__construct",
                            "preg_match('//u', $value) === 1", "true"))
        shared.append(Fault("ignore-postgres-text-output-context", "ExpressionLowering::lower", "&& $simpleOutput", ""))
        shared.append(Fault("allow-typed-text-in-numeric-cast", "Expression/BuiltinCast::__construct",
                            "$source !== CastDestination::Text || $target === CastDestination::Text,", "true,"))
        shared.append(Fault("erase-postgres-text-parameter-type", "Binding/ParameterLowering::target",
                            "Builtin::Text => CastDestination::Text,", "Builtin::Text => CastDestination::Integer,"))
        shared.append(Fault("omit-postgres-text-compound-kind", "Rows/CompoundShape::type",
                            "$column instanceof TextLiteral, $column instanceof TextColumnReference => ResultKind::Text,",
                            "$column instanceof TextLiteral, $column instanceof TextColumnReference => ResultKind::Integer,"))
        shared.append(Fault("ignore-wildcard-occurrence-ambiguity", "From/JoinBinding::resolved", "$expanded === null,", "true,"))
        shared.append(Fault("stop-before-postgres-merged-origin", "From/JoinBinding::resolved", "$slot = $slot->origin)", "$slot = null)"))
        shared.append(Fault("use-left-operand-for-postgres-merge", "From/MergedJoinLowering::predicate",
                            "new ColumnReference($right->slotFor($rightHeading[$key])->slot, 1)",
                            "new ColumnReference($left->slotFor($leftHeading[$key])->slot, 0)"))
        shared.append(Fault("use-left-provenance-for-postgres-right-merge", "Joining::merged",
                            "$kind === JoinKind::Right ? $right : $left", "$left",
                            "src/Rules/Resolution/Joining.php", "PostgreSQL join binding source differs in src/Rules/Resolution/Joining.php"))
        shared.append(Fault("allow-postgres-non-table-in-from-list", "From/JoinBinding::bind", "$group instanceof TableInput || $group instanceof ParsedJoin,", "true,"))
        shared.append(Fault("accept-unbounded-postgres-from-list", "From/JoinBinding::bind", "count($items) <= 61,", "true,"))
        shared.append(Fault("accept-postgres-nested-product-in-derived-input", "From/DerivedBinding::__construct",
                            " && !$from instanceof RelationList,", ","))
        shared.append(Fault("permit-disjunction-in-postgres-full", "From/Join::__construct",
                            "$condition->operator === LogicalOperator::And", "$condition->operator === LogicalOperator::Or"))
        shared.append(Fault("omit-postgres-full-projection-alternate", "From/JoinBinding::projected",
                            "new IntegerCase([new IntegerCaseArm(new NullTest($left, true), $left)], $right)",
                            "new IntegerCase([new IntegerCaseArm(new NullTest($left, true), $left)], $left)"))
        shared.append(Fault("erase-postgres-full-analysis-alternate", "Joining::merged",
                            "coalescedWith: $kind === JoinKind::Full ? $right : null", "coalescedWith: null",
                            "src/Rules/Resolution/Joining.php", "PostgreSQL join binding source differs in src/Rules/Resolution/Joining.php"))
        shared.append(Fault("miss-postgres-joined-list-field-origin", "From/JoinBinding::resolved",
                            "in_array(self::ungroup($resolution->relation), $this->joined, true)",
                            "self::ungroup($resolution->relation) === $this->join"))
        shared.append(Fault("accept-same-side-full-join", "From/Join::__construct",
                            "!== ($condition->right->occurrence < $leftWidth)", "=== ($condition->right->occurrence < $leftWidth)"))
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
    if engine == "mysql":
        shared.extend([
            Fault("use-left-operand-for-mysql-merge", "From/MergedJoinLowering::predicate",
                  "new ColumnReference($right->slotFor($rightHeading[$key])->slot, 1)",
                  "new ColumnReference($left->slotFor($leftHeading[$key])->slot, 0)"),
            Fault("use-written-order-for-mysql-merged-heading", "Joining::join",
                  "[...$ordered, ...$first, ...$second]", "[...$merged, ...$first, ...$second]",
                  "src/Rules/Query/From/Joining.php", "MySQL FROM binding source differs in src/Rules/Query/From/Joining.php"),
            Fault("accept-unbounded-mysql-from-list", "From/JoinBinding::bind", "count($members) <= 61,", "true,"),
            Fault("accept-mysql-nested-product-in-derived-input", "From/DerivedBinding::__construct",
                  " && !$from instanceof TableList,", ","),
            Fault("ignore-mysql-local-join-visibility", "FromScope::enter",
                  "[...$first->visible, ...$second->visible])), $derivation);",
                  "[...$left, ...$first->visible, ...$second->visible])), $derivation);",
                  "src/Rules/Query/From/FromScope.php", "MySQL FROM binding source differs in src/Rules/Query/From/FromScope.php"),
        ])
    return shared


def relative_path(engine, fault):
    model, _ = fault.role.split("::")
    return f"sql-semantics-{engine}/" + (fault.artifact or f"src/Ir/{model}.php")


def expected_rejection(engine, fault):
    label = {"sqlite": "Folded binding", "postgres": "Integer binding"}.get(engine)
    message = f"{label} source differs in {fault.artifact}" if fault.artifact else f"Native boundary differs in {fault.role}"
    return {"status": "shape-mismatch", "message": fault.message or message}


def fault_plan(candidate, distribution):
    require(digest(TOOL / FAULT_REPLAY_NAME) == FAULT_REPLAY, "native-boundary-fault-replay-contract")
    planned = {}
    for engine in ENGINES:
        entries = []
        for fault in faults(engine):
            relative = relative_path(engine, fault)
            source = candidate / "packages" / relative
            text = source.read_text()
            require(text.count(fault.before) == 1, "native-boundary-fault-anchor", fault.name)
            mutated = text.replace(fault.before, fault.after).encode()
            before = distribution.get(f"packages/{relative}")
            after = sha256(mutated).hexdigest()
            require(before == digest(source) and after != before, "native-boundary-fault-source", fault.name)
            entries.append({"name": fault.name, "role": fault.role, "path": relative, "source": before,
                            "mutation": after, "expected": expected_rejection(engine, fault)})
        require(entries and len({entry["name"] for entry in entries}) == len(entries), "native-boundary-fault-plan", engine)
        planned[engine] = entries
    return planned


def check_fault(args, engine, fault, distribution):
    relative = relative_path(engine, fault)
    original = args.candidate / "packages" / relative
    source = original.read_text()
    require(source.count(fault.before) == 1, "native-boundary-fault-anchor", fault.name)
    text = source.replace(fault.before, fault.after)
    overlay = args.out / engine / fault.name
    altered = overlay / relative
    altered.parent.mkdir(parents=True)
    altered.write_text(text)
    before, after = digest(original), digest(altered)
    require(distribution[f"packages/{relative}"] == before and before != after,
            "invalid-native-boundary-mutation", fault.name)
    diff = difflib.unified_diff(source.splitlines(keepends=True), text.splitlines(keepends=True),
                                f"a/{relative}", f"b/{relative}")
    (overlay / "injected.diff").write_text("".join(diff))

    try:
        shape(args, overlay, engine)
    except Failure as failure:
        expected = expected_rejection(engine, fault)
        require(failure.signal == "shape-mismatch" and failure.detail == expected, "wrong-native-boundary-fault-signal",
                {"fault": fault.name, "response": failure.detail})
        record = {"name": fault.name, "role": fault.role, "healthy": "verified", "path": relative,
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
    plan_path = args.out / "fault-plan.json"
    write_json(plan_path, {"candidate": identity, "contract": FAULT_REPLAY, "faults": fault_plan(args.candidate, manifest)})
    results = {}
    for engine in ENGINES:
        healthy = shape(args, dialect=engine)
        cases = faults(engine)
        require({fault.role for fault in cases if not fault.artifact} <= set(healthy["checkedBoundaries"])
                and {fault.artifact for fault in cases if fault.artifact} <= healthy["checkedBindingSources"].keys(),
                "native-boundary-audit-incomplete", engine)
        results[engine] = {"healthy": healthy, "faults": [check_fault(args, engine, fault, manifest) for fault in cases]}
    constructors, _, _ = declared_constructors(DIALECTS.values())
    outcomes = {engine: result["healthy"] for engine, result in results.items()}
    values = certificate(manifest, constructors, outcomes)
    path = args.out / "value-closure.json"
    envelope = {"candidate": identity, "distribution": digest(args.out / "distribution.json")}
    write_json(path, {**envelope, "certificate": values})
    dispatch = args.out / "scalar-dispatch.json"
    write_json(dispatch, {**envelope, "certificate": dispatch_certificate(outcomes, values)})
    verify_candidate(args.candidate, identity, manifest)
    write_json(args.out / "index.json", {
        "status": "provisional-native-construction-shape",
        "candidate": identity, "distribution": digest(args.out / "distribution.json"),
        "procedures": {name: digest(TOOL / name) for name in ("shape.php", "native_boundaries.php", "check_native_boundaries.py",
                       "sqlite-drop-view-boundaries-v1.json", "sqlite-create-view-boundaries-v4.json", "sqlite-view-binding-sources-v2.json", "native-boundaries-v31.json", "mysql-from-binding-v2.json", "mysql-from-list-boundaries-v1.json", "postgres-join-binding-v3.json", "postgres-query-boundaries-v9.json", "postgres-derived-text-boundaries-v1.json", "postgres-text-returning-boundaries-v1.json", "postgres-text-update-boundaries-v1.json", "postgres-text-insert-boundaries-v1.json", FAULT_REPLAY_NAME, "identifier-boundaries-v1.json", "postgres-mutation-returning-boundaries-v2.json", "native-insert-query-boundaries-v4.json", "sqlite-grouped-boundaries-v5.json", "sqlite-wildcard-boundaries-v4.json", "sqlite-insert-slots-boundaries-v5.json", "sqlite-scalar-boundaries-v17.json", "sqlite-numeric-boundaries-v2.json", "sqlite-default-boundaries-v10.json", "sqlite-rename-boundaries-v1.json", "sqlite-rename-v4.json", "sqlite-mutation-returning-boundaries-v3.json", "sqlite-query-scope-boundaries-v9.json", "sqlite-merged-join-binding-v3.json", "sqlite-conflict-boundaries-v3.json", "sqlite-folded-binding-sources-v3.json", "postgres-integer-binding-sources-v1.json", "postgres-declaration-binding-sources-v1.json", "closed-value-graph-v1.json", "checked_value_graph.py", "scalar-dispatch-v3.json", "checked_scalar_dispatch.py", "scalar_dispatch.php", "value_storage.php", "value-storage-v29.json", "value-primitives.json")},
        "faultPlan": {"path": plan_path.name, "sha256": digest(plan_path)},
        "results": results, "valueClosure": {"path": path.name, "sha256": digest(path)},
        "scalarDispatch": {"path": dispatch.name, "sha256": digest(dispatch)},
    })
    print(args.out)


if __name__ == "__main__":
    main()
