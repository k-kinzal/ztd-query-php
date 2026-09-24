<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Definition\Extensibility;

use SqlSemantics\Dialect;
use SqlSemantics\Model\BoundStatement;
use SqlSemantics\Model\Definition\Relation\Identity;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Definition\Sequence as Statement;
use SqlSemantics\Serialization\Expressions;
use SqlSemantics\Serialization\TypeDeclaration;

/**
 * Writes CREATE SEQUENCE and ALTER SEQUENCE from their typed options.
 * @visibility SqlSemantics
 */
final class Sequences
{
    /**
     * Returns null for statements outside the sequence forms.
     */
    public static function write(BoundStatement $statement): ?Tree
    {
        $dialect = Dialect::PostgreSql;
        return match (true) {
            $statement instanceof Statement\CreateSequenceStatement => new Tree('create-sequence', [
                Build::keyword(trim('CREATE ' . $statement->persistence->value) . ' SEQUENCE' . ($statement->ifNotExists ? ' IF NOT EXISTS' : '')),
                Build::identifier($statement->name->parts, $dialect),
                ...array_map(self::option(...), $statement->options),
            ]),
            $statement instanceof Statement\AlterSequenceStatement => new Tree('alter-sequence', [
                Build::keyword('ALTER SEQUENCE' . ($statement->ifExists ? ' IF EXISTS' : '')),
                Build::identifier($statement->name->parts, $dialect),
                ...array_map(self::option(...), $statement->options),
            ]),
            default => null,
        };
    }

    /**
     * Writes one option with its canonical keywords.
     */
    public static function option(Identity\SequenceValueChange|Identity\SequenceFlag|Identity\SequenceStorage|Identity\SetSequenceOwner|Identity\RestartIdentity $option): Tree
    {
        return match (true) {
            $option instanceof Identity\SequenceValueChange => new Tree('sequence-option', [Build::keyword($option->attribute->value), Expressions::write($option->value)]),
            $option instanceof Identity\SequenceFlag => Build::keyword($option->value),
            $option instanceof Identity\SequenceStorage => new Tree('sequence-option', [Build::keyword('AS'), TypeDeclaration::write($option->type)]),
            $option instanceof Identity\SetSequenceOwner => new Tree('sequence-option', [Build::keyword('OWNED BY'), $option->column === null ? Build::keyword('NONE') : Build::identifier($option->column->parts, Dialect::PostgreSql)]),
            $option instanceof Identity\RestartIdentity => new Tree('sequence-option', [Build::keyword('RESTART'), ...($option->value === null ? [] : [Build::keyword('WITH'), Expressions::write($option->value)])]),
        };
    }
}
