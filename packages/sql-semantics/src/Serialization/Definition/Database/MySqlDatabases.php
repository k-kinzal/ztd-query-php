<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Database;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Database\DatabaseCharacterSet;
use SqlSemantics\Model\Definition\Database\DatabaseCollation;
use SqlSemantics\Model\Definition\Database\DatabaseEncryption;
use SqlSemantics\Model\Definition\Database\DatabaseReadOnly;
use SqlSemantics\Model\Definition\Database\ServerCharacterInheritance;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\MySql as Statement;

/**
 * Writes database requests from their explicit target and option domains.
 * @visibility SqlSemantics
 */
final class MySqlDatabases
{
    /**
     * Preserves default request order and separates upgrades from option changes.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        if ($statement instanceof Statement\UpgradeDatabaseDirectoryStatement) {
            return new Tree('database-directory-upgrade', [Build::keyword('ALTER DATABASE'), Build::identifier([$statement->name], Dialect::MySql), Build::keyword('UPGRADE DATA DIRECTORY NAME')]);
        }
        if (!$statement instanceof Statement\CreateDatabaseStatement && !$statement instanceof Statement\AlterDatabaseStatement) {
            return null;
        }
        return new Tree('database-defaults', [
            Build::keyword($statement instanceof Statement\CreateDatabaseStatement ? 'CREATE DATABASE' . ($statement->ifNotExists ? ' IF NOT EXISTS' : '') : 'ALTER DATABASE'),
            ...(is_string($statement->name) ? [Build::identifier([$statement->name], Dialect::MySql)] : []),
            ...array_map(self::option(...), $statement->options),
        ]);
    }

    /**
     * Writes each classified role with an identifier or fixed value rather than arbitrary SQL.
     */
    public static function option(DatabaseCharacterSet|DatabaseCollation|DatabaseEncryption|DatabaseReadOnly $option): Tree
    {
        if ($option instanceof DatabaseEncryption) {
            return new Tree('database-encryption', [Build::keyword('ENCRYPTION'), new Atom('literal', "'" . $option->value . "'")]);
        }
        if ($option instanceof DatabaseReadOnly) {
            return new Tree('database-access', [Build::keyword('READ ONLY'), new Atom('literal', $option->value)]);
        }
        return new Tree('database-character-default', [Build::keyword($option instanceof DatabaseCharacterSet ? 'CHARACTER SET' : 'COLLATE'), $option->name instanceof ServerCharacterInheritance ? Build::keyword('DEFAULT') : Build::identifier([$option->name], Dialect::MySql)]);
    }
}
