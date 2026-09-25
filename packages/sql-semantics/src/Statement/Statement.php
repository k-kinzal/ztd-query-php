<?php

declare(strict_types=1);

namespace SqlSemantics\Statement;

/**
 * A complete SQL command whose values are independent of parsing.
 *
 * Fixed syntax is defined by the concrete value classes. Arguments are named
 * fields, finite options are enums, and forwarding grammar rules are removed.
 * No source string, parser node, token, or construction map is retained.
 *
 * @visibility public
 * @example Reconstructing a statement from its values
 *     $statement = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Facade\Dialect::Sqlite))->analyze('SELECT 1');
 *     str_contains($statement->toString(), 'SELECT') // => true
 */
final class Statement
{
    /**
     * Supplies the complete command value, independently of how it was built.
     */
    public function __construct(public readonly Element $command)
    {
    }

    /**
     * Reconstructs SQL solely from the command's fields and options.
     */
    public function toString(): string
    {
        $writer = new Writer();
        $this->command->write($writer);

        return $writer->toString();
    }
}
