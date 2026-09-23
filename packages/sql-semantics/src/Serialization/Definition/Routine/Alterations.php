<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Routine;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\MySql\AlterFunctionStatement;
use SqlSemantics\Model\Statement\Definition\MySql\AlterProcedureStatement;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes requested routine characteristics without inventing values for unchanged properties.
 * @visibility SqlSemantics
 */
final class Alterations
{
    /**
     * Keeps the named language and comment within their identifier/literal boundaries.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        if (!$statement instanceof AlterFunctionStatement && !$statement instanceof AlterProcedureStatement) {
            return null;
        }
        $changes = $statement->changes;
        return new Tree('routine-alteration', [
            Build::keyword($statement instanceof AlterFunctionStatement ? 'ALTER FUNCTION' : 'ALTER PROCEDURE'), Build::identifier($statement->name->parts, Dialect::MySql),
            ...($changes->language === null ? [] : [Build::keyword('LANGUAGE'), strcasecmp($changes->language, 'SQL') === 0 ? Build::keyword('SQL') : Build::identifier([$changes->language], Dialect::MySql)]),
            ...($changes->dataAccess === null ? [] : [Build::keyword($changes->dataAccess->value)]),
            ...($changes->security === null ? [] : [Build::keyword('SQL SECURITY ' . $changes->security->value)]),
            ...($changes->comment === null ? [] : [Build::keyword('COMMENT'), Expressions::write($changes->comment)]),
        ]);
    }
}
