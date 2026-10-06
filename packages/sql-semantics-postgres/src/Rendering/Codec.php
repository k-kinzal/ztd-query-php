<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Rendering;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Identifiers;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Spells a decoded PostgreSQL name so that it decodes to the same name at its position.
 *
 * Rule: PG-NAME-CODEC-001. A name is written without quotes only when it is
 * a lower-case word of ASCII letters, digits, `_` and `$` that does not start
 * with a digit or `$`, and the grammar reads that word as a name at the
 * position: a keyword must belong to a category the position accepts.
 * Anything else is written between double quotes with each quote doubled,
 * which the server reads literally. The empty name, a name containing a zero
 * byte and a name longer than the server stores cannot be spelled and are
 * rejected. Source: https://www.postgresql.org/docs/17/sql-syntax-lexical.html#SQL-SYNTAX-IDENTIFIERS.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Codec implements \SqlSemantics\Contract\Codec
{
    /**
     * @param GrammarRelease $release The grammar release whose keywords decide quoting
     */
    public function __construct(private readonly GrammarRelease $release)
    {
    }

    /**
     * Spells a decoded name for a position, quoting only as the grammar requires.
     */
    public function name(Name $name, NameUse $use): string
    {
        $value = $name->value;
        Check::input($value !== '' && !str_contains($value, "\0") && strlen($value) <= Identifiers::LIMIT, 'A PostgreSQL name holds 1 to 63 bytes and no zero byte.');
        if (preg_match('/\A[a-z_][a-z0-9_$]*\z/', $value) === 1 && (new Keywords($this->release))->bare($value, $use)) {
            return $value;
        }

        return '"' . str_replace('"', '""', $value) . '"';
    }
}
