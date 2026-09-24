<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Extensibility;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\Assertion\CreateAssertionStatement;
use SqlSemantics\Schema\Constraint\CheckingTime;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes CREATE ASSERTION from its name, condition, and checking time.
 * @visibility SqlSemantics
 */
final class Assertions
{
    /**
     * Returns null for other statements; an immediate check is the default and stays unwritten.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        if (!$statement instanceof CreateAssertionStatement) {
            return null;
        }
        $checking = match ($statement->checking) {
            CheckingTime::Immediate => [],
            CheckingTime::DeferrableImmediate => [Build::keyword('DEFERRABLE')],
            CheckingTime::DeferrableDeferred => [Build::keyword('DEFERRABLE INITIALLY DEFERRED')],
        };
        return new Tree('create-assertion', [Build::keyword('CREATE ASSERTION'), Build::identifier($statement->name->parts, Dialect::PostgreSql), Build::keyword('CHECK'), Build::parentheses(Expressions::write($statement->condition)), ...$checking]);
    }
}
