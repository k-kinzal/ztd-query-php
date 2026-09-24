<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Utility;

use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Schema as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Serialization\Definition\Ownership\OwnershipCommands;
use SqlSemantics\Serialization\Statements;

/**
 * Writes CREATE SCHEMA with its owner and nested elements.
 * @visibility SqlSemantics
 */
final class SchemaCommands
{
    /**
     * Returns null for statements outside the schema family.
     * @throws InvalidStructure
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        if (!$statement instanceof Statement\CreateSchemaStatement && !$statement instanceof Statement\CreateAuthorizationSchemaStatement) {
            return null;
        }
        $name = $statement instanceof Statement\CreateSchemaStatement ? [DatabaseCommands::name($statement->name)] : [];
        return new Tree('create-schema', [
            Build::keyword('CREATE SCHEMA'),
            ...($statement->ifNotExists ? [Build::keyword('IF NOT EXISTS')] : []),
            ...$name,
            ...($statement->owner === null ? [] : [Build::keyword('AUTHORIZATION'), OwnershipCommands::role($statement->owner)]),
            ...array_map(Statements::write(...), $statement->elements),
        ]);
    }
}
