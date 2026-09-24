<?php

declare(strict_types=1);

namespace SqlSemantics\Serialization\Procedural;

use SqlSemantics\Dialect;
use SqlSemantics\Model\Sql\Atom;
use SqlSemantics\Model\Sql\Build;
use SqlSemantics\Model\Sql\Literal;
use SqlSemantics\Model\Sql\Tree;
use SqlSemantics\Model\Statement\Loading\ImportTableStatement;
use SqlSemantics\Model\Statement\Locking\LockInstanceStatement;
use SqlSemantics\Model\Statement\Locking\UnlockInstanceStatement;
use SqlSemantics\Model\Statement\Procedural\HelpStatement;
use SqlSemantics\Serialization\Expressions;

/**
 * Writes HELP, IMPORT TABLE and instance backup locks from their operands.
 * @visibility SqlSemantics
 */
final class Requests
{
    /**
     * Spells a help topic as a string, which searches the same topic as an identifier.
     */
    public static function write(HelpStatement|ImportTableStatement|LockInstanceStatement|UnlockInstanceStatement $statement): Tree
    {
        return match (true) {
            $statement instanceof HelpStatement => new Tree('help', [Build::keyword('HELP'), self::text($statement->topic)]),
            $statement instanceof ImportTableStatement => new Tree('import', [Build::keyword('IMPORT TABLE FROM'), Build::separated(array_map(Expressions::write(...), $statement->files))]),
            $statement instanceof LockInstanceStatement => new Tree('lock-instance', [Build::keyword('LOCK INSTANCE FOR BACKUP')]),
            $statement instanceof UnlockInstanceStatement => new Tree('unlock-instance', [Build::keyword('UNLOCK INSTANCE')]),
        };
    }

    /**
     * Encodes a decoded name or text as one MySQL string literal.
     */
    public static function text(string $value): Tree
    {
        return new Tree('literal', [new Atom('literal', Literal::encode($value, Dialect::MySql)[0])]);
    }
}
