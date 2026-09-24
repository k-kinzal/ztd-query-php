<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Inspection;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Inspection\Definition;
use SqlSemantics\Serialization\Definition\MySqlRemovals;
use SqlSemantics\Serialization\Query\Relations;

/**
 * Writes SHOW CREATE requests and stored routine listings from their name operands.
 * @visibility SqlSemantics
 */
final class Definitions
{
    /**
     * Object names are quoted as identifiers; accounts keep their string form.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Definition\ShowCreateDatabaseStatement => new Tree('show', [Build::keyword('SHOW CREATE DATABASE'), ...($statement->ifNotExists ? [Build::keyword('IF NOT EXISTS')] : []), Build::identifier([$statement->database], Dialect::MySql)]),
            $statement instanceof Definition\ShowCreateEventStatement => self::named('SHOW CREATE EVENT', $statement->event),
            $statement instanceof Definition\ShowCreateFunctionStatement => self::named('SHOW CREATE FUNCTION', $statement->function),
            $statement instanceof Definition\ShowCreateProcedureStatement => self::named('SHOW CREATE PROCEDURE', $statement->procedure),
            $statement instanceof Definition\ShowCreateTriggerStatement => self::named('SHOW CREATE TRIGGER', $statement->trigger),
            $statement instanceof Definition\ShowCreateTableStatement => new Tree('show', [Build::keyword('SHOW CREATE TABLE'), Relations::target($statement->table, Dialect::MySql)]),
            $statement instanceof Definition\ShowCreateViewStatement => new Tree('show', [Build::keyword('SHOW CREATE VIEW'), Relations::target($statement->view, Dialect::MySql)]),
            $statement instanceof Definition\ShowCreateUserStatement => new Tree('show', [Build::keyword('SHOW CREATE USER'), MySqlRemovals::accounts([$statement->account])]),
            $statement instanceof Definition\ShowRoutineStatusStatement => new Tree('show', [Build::keyword('SHOW ' . $statement->routine->value . ' STATUS'), ...Filters::write($statement->filter)]),
            $statement instanceof Definition\ShowRoutineCodeStatement => self::named('SHOW ' . $statement->routine->value . ' CODE', $statement->name),
            default => null,
        };
    }

    /**
     * Writes a keyword followed by a qualified object name.
     */
    public static function named(string $keyword, QualifiedName $name): Tree
    {
        return new Tree('show', [Build::keyword($keyword), Build::identifier($name->parts, Dialect::MySql)]);
    }
}
