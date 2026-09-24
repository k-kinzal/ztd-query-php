<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Utility;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Tablespace as Statement;
use SqlSemantics\Serialization\Definition\Ownership\OwnershipCommands;
use SqlSemantics\Serialization\Definition\Storage;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes PostgreSQL tablespace operations from their typed operands.
 * @visibility SqlSemantics
 */
final class TablespaceCommands
{
    /**
     * Returns null for statements outside the tablespace family.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Statement\CreateTablespaceStatement => new Tree('create-tablespace', [
                Build::keyword('CREATE TABLESPACE'),
                DatabaseCommands::name($statement->name),
                ...($statement->owner === null ? [] : [Build::keyword('OWNER'), OwnershipCommands::role($statement->owner)]),
                Build::keyword('LOCATION'),
                Expressions::write($statement->location),
                ...($statement->parameters === [] ? [] : [Build::keyword('WITH'), Build::parentheses(Storage::parameters($statement->parameters, Dialect::PostgreSql))]),
            ]),
            $statement instanceof Statement\SetTablespaceOptionsStatement => new Tree('alter-tablespace', [self::target($statement->name), Build::keyword('SET'), Build::parentheses(Storage::parameters($statement->parameters, Dialect::PostgreSql))]),
            $statement instanceof Statement\ResetTablespaceOptionsStatement => new Tree('alter-tablespace', [self::target($statement->name), Build::keyword('RESET'), Build::parentheses(Build::separated(array_map(static fn ($name): Tree => Build::identifier($name->parts, Dialect::PostgreSql), $statement->names)))]),
            $statement instanceof Statement\DropTablespaceStatement => new Tree('drop-tablespace', [Build::keyword('DROP TABLESPACE'), ...($statement->ifExists ? [Build::keyword('IF EXISTS')] : []), DatabaseCommands::name($statement->name)]),
            default => null,
        };
    }

    /**
     * Writes the ALTER TABLESPACE prefix.
     */
    public static function target(string $name): Tree
    {
        return new Tree('tablespace', [Build::keyword('ALTER TABLESPACE'), DatabaseCommands::name($name)]);
    }
}
