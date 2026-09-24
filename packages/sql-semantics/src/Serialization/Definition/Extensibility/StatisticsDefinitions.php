<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Extensibility;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Scalar\Reference\ColumnReference;
use SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\Statistics as Statement;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\Query\Relations;

/**
 * Writes extended statistics definitions and target changes from their operands.
 * @visibility SqlSemantics
 */
final class StatisticsDefinitions
{
    /**
     * Returns null for statements outside the statistics forms.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Statement\CreateStatisticsStatement => new Tree('create-statistics', [
                ...self::head($statement->name, $statement->ifNotExists),
                ...($statement->kinds === [] ? [] : [Build::parentheses(Build::separated(array_map(static fn (Statement\StatisticsKind $kind): Tree => Build::identifier([$kind->value], Dialect::PostgreSql), $statement->kinds)))]),
                Build::keyword('ON'),
                Build::separated(array_map(self::element(...), $statement->elements)),
                Build::keyword('FROM'),
                Relations::target($statement->table, Dialect::PostgreSql),
            ]),
            $statement instanceof Statement\CreateExpressionStatisticsStatement => new Tree('create-statistics', [...self::head($statement->name, $statement->ifNotExists), Build::keyword('ON'), self::element($statement->expression), Build::keyword('FROM'), Relations::target($statement->table, Dialect::PostgreSql)]),
            $statement instanceof Statement\SetStatisticsTargetStatement => new Tree('alter-statistics', [Build::keyword('ALTER STATISTICS' . ($statement->ifExists ? ' IF EXISTS' : '')), Build::identifier($statement->name->parts, Dialect::PostgreSql), Build::keyword('SET STATISTICS ' . $statement->target)]),
            default => null,
        };
    }

    /**
     * The command keywords and the optional name.
     * @return list<Tree>
     */
    public static function head(?QualifiedName $name, bool $ifNotExists): array
    {
        return [Build::keyword('CREATE STATISTICS' . ($ifNotExists ? ' IF NOT EXISTS' : '')), ...($name === null ? [] : [Build::identifier($name->parts, Dialect::PostgreSql)])];
    }

    /**
     * An unqualified column is written bare; every other element is parenthesized.
     */
    public static function element(Expression $element): Tree
    {
        if (($element instanceof ColumnReference || $element instanceof UnresolvedColumnReference) && count($element->name) === 1) {
            return Build::identifier($element->name, Dialect::PostgreSql);
        }
        return Build::parentheses(Expressions::write($element));
    }
}
