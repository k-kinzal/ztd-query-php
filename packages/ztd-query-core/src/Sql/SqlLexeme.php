<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

/**
 * What a reader made of the statement at an offset, and where it left off.
 *
 * A reader is asked what starts at an offset and answers this, or nothing
 * when what starts there is not its concern. The offset it left off at is
 * what the scanner carries on from, so the answer says both what was read
 * and how much of the statement it accounted for.
 */
final class SqlLexeme
{
    /**
     * @param SqlTokenKind $kind What kind of lexeme was read
     * @param int $end Where the reading stopped, just past the last byte read
     */
    public function __construct(
        public readonly SqlTokenKind $kind,
        public readonly int $end,
    ) {
    }
}
