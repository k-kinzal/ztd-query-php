<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Statement\Definition\Statistics;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Definition\Catalog\CatalogInvariant;
use SqlSemantics\Model\Expression;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Scalar\Reference\ColumnReference;
use SqlSemantics\Model\Scalar\Reference\UnresolvedColumnReference;
use SqlSemantics\Model\Statement\Origin;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Serialization\Expressions;

/**
 * Shared rules of PostgreSQL extended statistics objects.
 * @visibility SqlSemantics
 */
final class StatisticsInvariant
{
    /**
     * Extended statistics objects are PostgreSQL catalog objects.
     * @throws InvalidStructure
     */
    public static function dialect(Origin $origin): void
    {
        if ($origin->dialect !== Dialect::PostgreSql) {
            throw new InvalidStructure('Extended statistics require PostgreSQL.');
        }
    }

    /**
     * A statistics object is named by up to a database, a schema, and a name; IF NOT EXISTS requires the name.
     * @throws InvalidStructure
     */
    public static function name(?QualifiedName $name, bool $ifNotExists): void
    {
        if ($name === null) {
            if ($ifNotExists) {
                throw new InvalidStructure('IF NOT EXISTS requires a statistics name.');
            }
            return;
        }
        CatalogInvariant::name($name, 3);
    }

    /**
     * Whether an element is a plain column rather than an expression.
     */
    public static function column(Expression $element): bool
    {
        return $element instanceof ColumnReference || $element instanceof UnresolvedColumnReference;
    }

    /**
     * Elements are distinct PostgreSQL columns or expressions, at most eight of them.
     * @param list<Expression> $elements
     * @throws InvalidStructure
     */
    public static function elements(array $elements): void
    {
        if (count($elements) > 8) {
            throw new InvalidStructure('Extended statistics cover at most eight columns and expressions.');
        }
        $seen = [];
        foreach ($elements as $element) {
            if ($element->type->dialect !== Dialect::PostgreSql) {
                throw new InvalidStructure('A statistics element requires a PostgreSQL expression.');
            }
            $key = self::key($element);
            if (isset($seen[$key])) {
                throw new InvalidStructure('A statistics element is given at most once.');
            }
            $seen[$key] = true;
        }
    }

    /**
     * Identifies a column by its name and an expression by its written structure.
     * @throws InvalidStructure
     */
    public static function key(Expression $element): string
    {
        if ($element instanceof ColumnReference) {
            return 'column:' . $element->binding->column->name;
        }
        if ($element instanceof UnresolvedColumnReference) {
            return 'column:' . $element->name[count($element->name) - 1];
        }
        return 'expression:' . Expressions::write($element)->toString();
    }
}
