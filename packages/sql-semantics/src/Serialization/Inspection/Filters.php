<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Inspection;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Query\Inspection\Filter\ConditionFilter;
use SqlSemantics\Model\Query\Inspection\Filter\PatternFilter;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes the optional restriction and database selector of SHOW listings.
 * @visibility SqlSemantics
 */
final class Filters
{
    /**
     * @return list<Tree> A LIKE pattern with its original spelling, a WHERE condition, or nothing
     */
    public static function write(PatternFilter|ConditionFilter|null $filter): array
    {
        return match (true) {
            $filter === null => [],
            $filter instanceof PatternFilter => [Build::keyword('LIKE'), Expressions::write($filter->pattern)],
            $filter instanceof ConditionFilter => [Build::keyword('WHERE'), Expressions::write($filter->condition)],
        };
    }

    /**
     * @return list<Tree> FROM and the quoted database, or nothing for the current database
     */
    public static function database(?string $database): array
    {
        return $database === null ? [] : [Build::keyword('FROM'), Build::identifier([$database], Dialect::MySql)];
    }
}
