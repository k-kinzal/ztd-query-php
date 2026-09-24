<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Utility;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Database\PostgreSql\DatabaseOption;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Literal as SqlText;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\Database as Statement;
use SqlSemantics\Model\Validation\InvalidStructure;
use SqlSemantics\Serialization\Settings;

/**
 * Writes PostgreSQL database operations from their typed operands.
 * @visibility SqlSemantics
 */
final class DatabaseCommands
{
    /**
     * Returns null for statements outside the database family.
     * @throws InvalidStructure
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Statement\CreateDatabaseStatement => new Tree('create-database', [Build::keyword('CREATE DATABASE'), self::name($statement->name), ...self::options($statement->options)]),
            $statement instanceof Statement\AlterDatabaseOptionsStatement => new Tree('alter-database', [self::target($statement->name), ...self::options($statement->options)]),
            $statement instanceof Statement\SetDatabaseTablespaceStatement => new Tree('alter-database', [self::target($statement->name), Build::keyword('SET TABLESPACE'), self::name($statement->tablespace)]),
            $statement instanceof Statement\RefreshDatabaseCollationStatement => new Tree('alter-database', [self::target($statement->name), Build::keyword('REFRESH COLLATION VERSION')]),
            $statement instanceof Statement\AlterDatabaseSetStatement => new Tree('alter-database', [self::target($statement->name), Build::keyword('SET'), Settings::assignment($statement->setting, Dialect::PostgreSql)]),
            $statement instanceof Statement\AlterDatabaseResetStatement => new Tree('alter-database', [self::target($statement->name), Build::keyword('RESET'), Build::identifier($statement->setting->name, Dialect::PostgreSql)]),
            $statement instanceof Statement\AlterDatabaseResetAllStatement => new Tree('alter-database', [self::target($statement->name), Build::keyword('RESET ALL')]),
            $statement instanceof Statement\DropDatabaseStatement => new Tree('drop-database', [Build::keyword('DROP DATABASE'), ...($statement->ifExists ? [Build::keyword('IF EXISTS')] : []), self::name($statement->name), ...($statement->force ? [Build::keyword('WITH (FORCE)')] : [])]),
            default => null,
        };
    }

    /**
     * Writes the ALTER DATABASE prefix of an existing database.
     */
    public static function target(string $name): Tree
    {
        return new Tree('database', [Build::keyword('ALTER DATABASE'), self::name($name)]);
    }

    /**
     * Quotes one database, tablespace or role name.
     */
    public static function name(string $name): Tree
    {
        return Build::identifier([$name], Dialect::PostgreSql);
    }

    /**
     * @param list<DatabaseOption> $options
     * @return list<Tree>
     * @throws InvalidStructure
     */
    public static function options(array $options): array
    {
        if ($options === []) {
            return [];
        }
        return [Build::keyword('WITH'), ...array_map(static fn (DatabaseOption $option): Tree => new Tree('database-option', [Build::keyword($option->parameter->value), Build::keyword('='), self::value($option->value)]), $options)];
    }

    /**
     * Writes a decoded option value as a PostgreSQL constant, or DEFAULT for the server default.
     * @throws InvalidStructure
     */
    public static function value(string|int|bool|null $value): Tree
    {
        return $value === null ? Build::keyword('DEFAULT') : new Tree('literal', [new Atom('literal', SqlText::encode($value, Dialect::PostgreSql)[0])]);
    }
}
