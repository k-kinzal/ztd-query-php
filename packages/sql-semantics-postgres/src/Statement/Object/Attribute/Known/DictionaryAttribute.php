<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known;

use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\KnownAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;

/**
 * The attribute `CREATE TEXT SEARCH DICTIONARY` reads itself.
 *
 * `DefineTSDictionary` reads `template` as the name of the dictionary's
 * template. Every other attribute is an option of the template, which the
 * template's initialization function reads.
 * Source: https://www.postgresql.org/docs/17/sql-createtsdictionary.html, `DefineTSDictionary` in `src/backend/commands/tsearchcmds.c` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading how the command reads an attribute
 *     \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\DictionaryAttribute::from('template')->reading() // => \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading::TextSearchTemplate
 */
enum DictionaryAttribute: string implements KnownAttribute
{
    case Template = 'template';

    /**
     * How the command reads each attribute.
     */
    private const READINGS = [
        'template' => Reading::TextSearchTemplate,
    ];

    /**
     * Answers the member with exactly this name, or null.
     */
    public static function named(string $name): ?self
    {
        return self::tryFrom($name);
    }

    /**
     * Answers the attribute name the command compares with.
     */
    public function text(): string
    {
        return $this->value;
    }

    /**
     * Answers how the command reads the attribute's value.
     */
    public function reading(): Reading
    {
        return self::READINGS[$this->value];
    }
}
