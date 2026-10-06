<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Query\Clause;

/**
 * The kind of table SELECT INTO creates, as written.
 *
 * TEMP and TEMPORARY are the same request, and LOCAL and GLOBAL change
 * nothing; the spelling is kept because the grammar keeps it. Permanent is the
 * absence of any of these words.
 * Source: https://www.postgresql.org/docs/17/sql-selectinto.html, https://www.postgresql.org/docs/17/sql-createtable.html.
 *
 * @visibility public
 * @example Reading the persistence of a temporary target
 *     \SqlSemantics\Platform\PostgreSql\Statement\Query\Clause\IntoPersistence::LocalTemp->temporary() // => true
 */
enum IntoPersistence: string
{
    case Permanent = '';
    case Temporary = 'TEMPORARY';
    case Temp = 'TEMP';
    case LocalTemporary = 'LOCAL TEMPORARY';
    case LocalTemp = 'LOCAL TEMP';
    case GlobalTemporary = 'GLOBAL TEMPORARY';
    case GlobalTemp = 'GLOBAL TEMP';
    case Unlogged = 'UNLOGGED';

    /**
     * Tells whether the new table is temporary.
     */
    public function temporary(): bool
    {
        return match ($this) {
            self::Permanent, self::Unlogged => false,
            self::Temporary, self::Temp, self::LocalTemporary, self::LocalTemp, self::GlobalTemporary, self::GlobalTemp => true,
        };
    }

    /**
     * Answers the keywords as written, none for a permanent table.
     *
     * @return list<string>
     */
    public function keywords(): array
    {
        return $this === self::Permanent ? [] : explode(' ', $this->value);
    }
}
