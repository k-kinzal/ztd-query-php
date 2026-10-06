<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known;

use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\KnownAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;

/**
 * An attribute `CREATE TEXT SEARCH PARSER` recognizes.
 *
 * `DefineTSParser` reads each attribute as the name of one of the
 * parser's functions. Any other attribute is an error.
 * Source: https://www.postgresql.org/docs/17/sql-createtsparser.html, `DefineTSParser` in `src/backend/commands/tsearchcmds.c` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading how the command reads an attribute
 *     \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\ParserAttribute::from('gettoken')->reading() // => \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading::Function
 */
enum ParserAttribute: string implements KnownAttribute
{
    case Start = 'start';
    case Gettoken = 'gettoken';
    case End = 'end';
    case Headline = 'headline';
    case Lextypes = 'lextypes';

    /**
     * How the command reads each attribute.
     */
    private const READINGS = [
        'start' => Reading::Function,
        'gettoken' => Reading::Function,
        'end' => Reading::Function,
        'headline' => Reading::Function,
        'lextypes' => Reading::Function,
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
