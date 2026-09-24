<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Schema;

use SqlSemantics\Ast\Declaration\TableConstraint as ParsedConstraint;
use SqlSemantics\Ast\Tree;
use SqlSemantics\Binding\ExpressionBinder;
use SqlSemantics\Binding\ExpressionRules;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Binding\Statement\UnclassifiedSql;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Schema\Constraint;
use SqlSemantics\Schema\ConstraintKind;
use SqlSemantics\Schema\TableConstraint;

/**
 * Selects a native integrity-condition type with mandatory operands.
 *
 * @visibility SqlSemantics
 */
final class ConstraintBinder
{
    /**
     * Binds CHECK expressions against the complete table declaration.
     * @throws UnclassifiedSql
     * @throws \SqlSemantics\InvalidSql
     */
    public static function bind(ParsedConstraint $constraint, Scope $scope): TableConstraint
    {
        if ($constraint->kind === ConstraintKind::ForeignKey && $constraint->referencedColumns !== [] && count($constraint->columns) !== count($constraint->referencedColumns)) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::ForeignKeyWidth, $constraint->source);
        }
        if ($constraint->noInherit && $constraint->kind !== ConstraintKind::Check) {
            throw new \SqlSemantics\InvalidSql(\SqlSemantics\Model\Validation\InputViolation::ConstraintAttribute, $constraint->source);
        }
        $checking = !$constraint->deferrable ? Constraint\CheckingTime::Immediate : ($constraint->initiallyDeferred ? Constraint\CheckingTime::DeferrableDeferred : Constraint\CheckingTime::DeferrableImmediate);
        if ($constraint->kind === ConstraintKind::Check) {
            $predicate = (new ExpressionBinder())->bind($constraint->expression ?? throw new UnclassifiedSql('A CHECK requires a predicate.'), $scope);
            (new ExpressionRules($scope->identifiers->dialect, $scope->diagnostics()))->predicate($predicate);
            return new Constraint\Check($predicate, $constraint->enforced, $constraint->noInherit, name: $constraint->name, source: $constraint->source, onConflict: self::resolution($constraint->source));
        }
        return match ($constraint->kind) {
            ConstraintKind::PrimaryKey => new Constraint\PrimaryKey(self::keys($constraint, $scope), $checking, name: $constraint->name, source: $constraint->source, onConflict: self::resolution($constraint->source), index: \SqlSemantics\Binding\Schema\Constraint\KeyIndexBinder::bind($constraint->source, $scope, true)),
            ConstraintKind::Unique => new Constraint\UniqueKey(self::keys($constraint, $scope), $checking, nullsDistinct: (\SqlSemantics\Ast\Definition\OptionReader::read($constraint->source, $scope->identifiers)['nulls_distinct'] ?? true) !== false, name: $constraint->name, source: $constraint->source, onConflict: self::resolution($constraint->source), index: \SqlSemantics\Binding\Schema\Constraint\KeyIndexBinder::bind($constraint->source, $scope, false)),
            ConstraintKind::ForeignKey => new Constraint\ForeignKey($constraint->columns, new QualifiedName($constraint->referencedTable), $constraint->referencedColumns, $constraint->onDelete, $constraint->onUpdate, $scope->identifiers->dialect === \SqlSemantics\Dialect::Sqlite ? Constraint\MatchMode::Simple : Constraint\MatchMode::from($constraint->match), $checking, $constraint->deleteColumns, $constraint->name, $constraint->source, \SqlSemantics\Binding\Schema\Constraint\KeyIndexBinder::name($constraint->source, $scope)),
        };
    }
    /**
     * Reads the SQLite ON CONFLICT resolution declared by a constraint; Default when the constraint declares none.
     */
    public static function resolution(\SqlParser\Parser\Node $source): \SqlSemantics\Model\Write\Policy\ConstraintResponse
    {
        $tokens = (Tree::outer($source, ['onconf'])[0] ?? null)?->tokens() ?? [];
        return $tokens === [] ? \SqlSemantics\Model\Write\Policy\ConstraintResponse::Default : \SqlSemantics\Model\Write\Policy\ConstraintResponse::from(strtoupper($tokens[count($tokens) - 1]->text));
    }

    /**
     * Returns the key elements; a SQLite column-level PRIMARY KEY keeps its written direction.
     * @return non-empty-list<\SqlSemantics\Schema\IndexElement>
     * @throws UnclassifiedSql
     */
    public static function keys(ParsedConstraint $constraint, Scope $scope): array
    {
        $parsed = \SqlSemantics\Ast\Definition\IndexKeys::read($constraint->source, $scope->identifiers);
        if ($parsed !== []) {
            return array_map(static fn ($key): \SqlSemantics\Schema\IndexElement => IndexBinder::element($key, $scope), $parsed);
        }
        $result = [];
        foreach ($constraint->columns as $name) {
            $column = $scope->column([$name], $constraint->source);
            if (!$column instanceof \SqlSemantics\Model\Scalar\Reference\ColumnReference && !$column instanceof \SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference) {
                throw new UnclassifiedSql('A key requires a column reference.');
            }
            $order = strtoupper(trim(Tree::text(Tree::outer($constraint->source, ['sortorder'])[0] ?? new \SqlParser\Parser\Node('sortorder', 0, []))));
            $result[] = new \SqlSemantics\Schema\Index\ColumnKey($column, direction: $order === '' ? null : \SqlSemantics\Schema\Index\Direction::from($order), source: $constraint->source);
        }
        if ($result === []) {
            throw new UnclassifiedSql('A key requires at least one column.');
        }
        return $result;
    }

}
