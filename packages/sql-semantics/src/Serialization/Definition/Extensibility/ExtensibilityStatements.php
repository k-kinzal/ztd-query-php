<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Extensibility;

use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Routes PostgreSQL routine, extension, statistics, sequence, and assertion definitions to their writers.
 * @visibility SqlSemantics
 */
final class ExtensibilityStatements
{
    /**
     * Returns null for statements outside these definition forms.
     * @throws InvalidStructure
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return Extensions::write($statement)
            ?? StatisticsDefinitions::write($statement)
            ?? Assertions::write($statement)
            ?? Sequences::write($statement)
            ?? RoutineDefinitions::write($statement)
            ?? RoutineAttributes::alteration($statement);
    }
}
