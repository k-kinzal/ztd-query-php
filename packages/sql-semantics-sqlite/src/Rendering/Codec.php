<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Rendering;

use SqlSemantics\Contract\NameUse;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Spells decoded names as SQLite identifiers.
 *
 * Rule: SQLITE-NAME-CODEC-001. A name made of ASCII letters, digits and
 * underscores that does not start with a digit and is no keyword is written
 * bare. Every other name is written in backticks with embedded backticks
 * doubled: unlike a double-quoted word, a backtick-quoted word is always an
 * identifier and never falls back to a string literal. Source:
 * https://sqlite.org/lang_keywords.html, https://sqlite.org/quirks.html#dblquote.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Codec implements \SqlSemantics\Contract\Codec
{
    /**
     * Spells a decoded name; every position uses the same rule.
     */
    public function name(Name $name, NameUse $use): string
    {
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $name->value) === 1 && !(new Keywords())->reserved($name->value)) {
            return $name->value;
        }

        return '`' . str_replace('`', '``', $name->value) . '`';
    }
}
