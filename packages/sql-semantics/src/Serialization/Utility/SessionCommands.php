<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Utility;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Relation\QualifiedName;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Configuration\SetNamedConstraintsStatement;
use SqlSemantics\Model\Statement\Configuration\Show;
use SqlSemantics\Model\Statement\Execution\DoBlockStatement;
use SqlSemantics\Model\Statement\Loading\LoadLibraryStatement;
use SqlSemantics\Model\Statement\Procedural\PostgreSql\CallProcedureStatement;
use SqlSemantics\Model\Statement\Procedural\PostgreSql\ProcedureArgument;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes PostgreSQL parameter displays and session utility commands.
 * @visibility SqlSemantics
 */
final class SessionCommands
{
    /**
     * Returns null for statements outside these session commands.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        return match (true) {
            $statement instanceof Show\ShowSettingStatement => new Tree('show', [Build::keyword('SHOW'), Build::identifier($statement->name, Dialect::PostgreSql)]),
            $statement instanceof Show\ShowAllSettingsStatement => Build::keyword('SHOW ALL'),
            $statement instanceof SetNamedConstraintsStatement => new Tree('constraints', [
                Build::keyword('SET CONSTRAINTS'),
                Build::separated(array_map(static fn (QualifiedName $name): Tree => Build::identifier($name->parts, Dialect::PostgreSql), $statement->constraints)),
                Build::keyword($statement->timing->value),
            ]),
            $statement instanceof LoadLibraryStatement => new Tree('load', [Build::keyword('LOAD'), Expressions::write($statement->file)]),
            $statement instanceof DoBlockStatement => new Tree('do', $statement->language === null ? [Build::keyword('DO'), Expressions::write($statement->code)] : [Build::keyword('DO'), Expressions::write($statement->code), Build::keyword('LANGUAGE'), Build::identifier([$statement->language], Dialect::PostgreSql)]),
            $statement instanceof CallProcedureStatement => new Tree('call', [
                Build::keyword('CALL'),
                Build::identifier($statement->procedure->parts, Dialect::PostgreSql),
                Build::parentheses(Build::separated(array_map(self::argument(...), $statement->arguments))),
            ]),
            default => null,
        };
    }

    /**
     * Writes one argument in positional or named notation, marking a variadic array.
     */
    public static function argument(ProcedureArgument $argument): Tree
    {
        $value = Expressions::write($argument->value);
        $named = $argument->name === null ? $value : new Tree('named-argument', [Build::identifier([$argument->name], Dialect::PostgreSql), Build::keyword('=>'), $value]);
        return $argument->variadic ? new Tree('variadic-argument', [Build::keyword('VARIADIC'), $named]) : $named;
    }
}
