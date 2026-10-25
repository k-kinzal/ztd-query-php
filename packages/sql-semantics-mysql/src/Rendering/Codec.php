<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rendering;

use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LexicalSettings;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Platform\MySql\Rules\Strings;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Spells decoded names as MySQL identifiers of one grammar release.
 *
 * Rule: MYSQL-NAME-CODEC-001. A name with an explicit delimiter choice is
 * reconstructed from its decoded value and that choice. When no choice is
 * specified, a name made of ASCII letters, digits and
 * underscores that starts with a letter or underscore and is no keyword, function name or
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
final class Codec implements \SqlSemantics\Contract\StringCodec
{
    private readonly Keywords $keywords;

    /**
     * @param GrammarRelease $release The grammar release whose keywords decide quoting
     * @param LexicalSettings $lexical The settings used to spell decoded string bytes
     * @param \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset|null $client The character set used to write decoded names
     */
    public function __construct(GrammarRelease $release, private readonly LexicalSettings $lexical = new LexicalSettings(''), private readonly ?\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset $client = null)
    {
        $this->keywords = new Keywords($release);
    }

    /**
     * Quotes decoded bytes using the escape rule of the output profile.
     */
    public function quote(string $value): string
    {
        return (new Strings())->encode($value, !$this->lexical->noBackslashEscapes);
    }

    /**
     * Reconstructs an explicit delimiter choice, or chooses a valid spelling when unspecified.
     */
    public function name(Name $name, NameUse $use): string
    {
        if ($name->quote !== null) {
            $value = $this->spelling($name->value);
            return match ($name->quote) {
                \SqlSemantics\Statement\Identifier\NameQuote::Bare => $value,
                \SqlSemantics\Statement\Identifier\NameQuote::Backtick => '`' . str_replace('`', '``', $value) . '`',
                \SqlSemantics\Statement\Identifier\NameQuote::DoubleQuote => '"' . str_replace('"', '""', $value) . '"',
            };
        }
        if (str_contains($name->value, "\0") && ($use === NameUse::Alias || $use === NameUse::Label)) {
            return $this->quote($this->spelling($name->value));
        }
        if (preg_match('/\A[A-Za-z_][A-Za-z0-9_]*\z/', $name->value) === 1 && !$this->keywords->reserved($name->value)) {
            return $name->value;
        }

        return '`' . str_replace('`', '``', $this->spelling($name->value)) . '`';
    }

    /**
     * Encodes identifier spelling while preserving its quote marks and escapes.
     *
     * @throws \SqlSemantics\Diagnostic\UnrepresentableName When the client character set cannot represent the name
     */
    public function spelling(string $text): string
    {
        if ($this->client === null) {
            return $text;
        }
        $encoding = new \SqlSemantics\Platform\MySql\Rules\Text\NameEncoding();
        $bytes = $encoding->encode($text, $this->client);
        if ($encoding->decode($bytes, $this->client) !== $text) {
            throw new \SqlSemantics\Diagnostic\UnrepresentableName('A name cannot be written in ' . $this->client->name . '.');
        }

        return $bytes;
    }

}
