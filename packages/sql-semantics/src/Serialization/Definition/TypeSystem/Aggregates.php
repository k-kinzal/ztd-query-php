<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\TypeSystem;

use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Aggregate\CreateAggregateStatement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Serialization\Definition\Routines;

/**
 * Writes aggregate definitions in the signature syntax.
 * @visibility SqlSemantics
 */
final class Aggregates
{
    /**
     * Returns null for statements outside this form.
     * @throws InvalidStructure
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        if (!$statement instanceof CreateAggregateStatement) {
            return null;
        }
        return new Tree('create-aggregate', [Build::keyword($statement->orReplace ? 'CREATE OR REPLACE AGGREGATE' : 'CREATE AGGREGATE'), Routines::aggregate($statement->aggregate), DefinitionLists::write($statement->options)]);
    }
}
