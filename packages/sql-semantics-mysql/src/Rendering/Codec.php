<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rendering;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Spells decoded names as MySQL identifiers of one grammar release.
 *
 * Rule: MYSQL-NAME-CODEC-001. A name made of ASCII letters, digits and
 * underscores that starts with a letter and is no keyword, function name or
 * introducer of the release (MYSQL-KEYWORDS-001) is written bare. Every other
 * name is written in backticks with embedded backticks doubled. A backtick
 * word is an identifier under every sql_mode, at every identifier position,
 * after `@` and after `@@`, and it decodes (MYSQL-IDENTIFIER-DECODE-001) to
 * the same name, so the spelling does not depend on ANSI_QUOTES or on the
 * position. A name position that accepts only a string token is not a name
 * of this codec: its structure holds a string literal.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/identifiers.html.
 * Status: Implemented.
 *
 * @visibility SqlSemantics
 */
final class Codec implements \SqlSemantics\Contract\Codec
{
    private readonly Keywords $keywords;

    /**
     * @param GrammarRelease $release The grammar release whose keywords decide quoting
     */
    public function __construct(GrammarRelease $release)
    {
        $this->keywords = new Keywords($release);
    }

    /**
     * Spells a decoded name; every position uses the same rule.
     */
    public function name(Name $name, NameUse $use): string
    {
        if (preg_match('/\A[A-Za-z][A-Za-z0-9_]*\z/', $name->value) === 1 && !$this->keywords->reserved($name->value)) {
            return $name->value;
        }

        return '`' . str_replace('`', '``', $name->value) . '`';
    }
}
