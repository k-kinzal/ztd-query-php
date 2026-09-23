<?php

declare(strict_types=1);

namespace SqlSemantics\Binding\Query\Joining;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Binding\ProjectionBinder;
use SqlSemantics\Binding\Scope;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\OutputColumn;
use SqlSemantics\Model\Scalar\ExpressionFacts;
use SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference;
use SqlSemantics\Type\Nullability;
use SqlSemantics\Type\TypeDescriptor;

/**
 * Retains the ordered visible row of a joined relation, including duplicate column names.
 * @visibility SqlSemantics
 */
final class RowNamespace
{
    /**
     * @return list<OutputColumn> Joined outputs or the base relation's declared columns
     */
    public static function read(Scope $scope, Node $source): array
    {
        return $scope->outputs ?? ($scope->relations === [] ? [] : (new ProjectionBinder())->star([], $scope, $source, 0));
    }

    /**
     * Resolves against visible joined outputs rather than suppressed underlying columns.
     */
    public static function resolve(Scope $scope, string $name, Node|Token $source): ?Expression
    {
        if ($scope->outputs === null) {
            return $scope->mergedColumn($name);
        }
        $matches = array_values(array_filter($scope->outputs, static fn (OutputColumn $column): bool => $column->name !== null && $scope->identifiers->equal($column->name, $name)));
        if (count($matches) < 2) {
            return $matches[0]->expression ?? null;
        }
        $scope->diagnostics()->report('ambiguous-column', 'Cannot resolve column unambiguously: ' . $name, $source);
        return new UnresolvedColumnReference(new ExpressionFacts(TypeDescriptor::builtin($scope->identifiers->dialect, 'unknown'), Nullability::Unknown), $source, [$name]);
    }

    /**
     * @return list<OutputColumn> Left row followed by right row, preserving repeated labels
     */
    public static function combine(Scope $left, Scope $right, Node $source): array
    {
        return self::positions([...self::read($left, $source), ...self::read($right, $source)]);
    }

    /**
     * @param list<OutputColumn> $columns Ordered visible columns
     * @return list<OutputColumn> Columns carrying the additional outer-join NULL provenance
     */
    public static function extend(array $columns, string $joinId): array
    {
        return array_map(static fn (OutputColumn $column): OutputColumn => new OutputColumn($column->ordinal, $column->name, $column->expression->withFacts(new ExpressionFacts($column->expression->type, Nullability::MaybeNull, [...$column->expression->nullExtendedBy, $joinId]))), $columns);
    }

    /**
     * @param list<OutputColumn> $columns Ordered columns, possibly from several inputs
     * @return list<OutputColumn> Consecutive zero-based positions
     */
    public static function positions(array $columns): array
    {
        return array_map(static fn (OutputColumn $column, int $ordinal): OutputColumn => new OutputColumn($ordinal, $column->name, $column->expression), $columns, array_keys($columns));
    }
}
