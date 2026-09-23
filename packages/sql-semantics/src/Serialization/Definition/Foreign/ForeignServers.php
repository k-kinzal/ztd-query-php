<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Foreign;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Foreign\AddForeignOption;
use SqlSemantics\Model\Definition\Foreign\DropForeignOption;
use SqlSemantics\Model\Definition\Foreign\ForeignOption;
use SqlSemantics\Model\Definition\Foreign\ServerVersionChange;
use SqlSemantics\Model\Definition\Foreign\SetForeignOption;
use SqlSemantics\Model\Scalar\Value\Literal;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\PostgreSql\AlterForeignServerStatement;
use SqlSemantics\Model\Statement\Definition\PostgreSql\CreateForeignServerStatement;
use SqlSemantics\Serialization\Expressions;

/**
 * Serializes foreign server declarations and explicit version/option changes.
 * @visibility SqlSemantics
 */
final class ForeignServers
{
    /**
     * Uses only native operands, preserving unchanged versus absent version semantics.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        if (!$statement instanceof CreateForeignServerStatement && !$statement instanceof AlterForeignServerStatement) {
            return null;
        }
        $creating = $statement instanceof CreateForeignServerStatement;
        $options = $creating
            ? array_map(static fn (ForeignOption $option): Tree => WrapperOptions::option($option), $statement->options)
            : array_map(static fn (AddForeignOption|SetForeignOption|DropForeignOption $option): Tree => WrapperOptions::change($option), $statement->options);
        $version = $statement->version;
        return new Tree('foreign-server', [
            Build::keyword($creating ? 'CREATE SERVER' . ($statement->ifNotExists ? ' IF NOT EXISTS' : '') : 'ALTER SERVER'), Build::identifier([$statement->name], Dialect::PostgreSql),
            ...($creating && $statement->serverType !== null ? [Build::keyword('TYPE'), Expressions::write($statement->serverType)] : []),
            ...($version instanceof Literal ? [Build::keyword('VERSION'), Expressions::write($version)] : ($version === ServerVersionChange::Remove ? [Build::keyword('VERSION NULL')] : [])),
            ...($creating ? [Build::keyword('FOREIGN DATA WRAPPER'), Build::identifier([$statement->wrapper], Dialect::PostgreSql)] : []),
            ...($options === [] ? [] : [Build::keyword('OPTIONS'), Build::parentheses(Build::separated($options))]),
        ]);
    }
}
