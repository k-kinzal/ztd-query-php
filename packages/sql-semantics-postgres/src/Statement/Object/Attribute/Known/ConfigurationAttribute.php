<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known;

use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\KnownAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;

/**
 * An attribute `CREATE TEXT SEARCH CONFIGURATION` recognizes.
 *
 * `DefineTSConfiguration` reads `parser` as the name of a parser and
 * `copy` as the name of a configuration to copy. Any other attribute is an
 * error.
 * Source: https://www.postgresql.org/docs/17/sql-createtsconfig.html, `DefineTSConfiguration` in `src/backend/commands/tsearchcmds.c` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading how the command reads an attribute
 *     \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\ConfigurationAttribute::from('copy')->reading() // => \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading::TextSearchConfiguration
 */
enum ConfigurationAttribute: string implements KnownAttribute
{
    case Parser = 'parser';
    case Copy = 'copy';

    /**
     * How the command reads each attribute.
     */
    private const READINGS = [
        'parser' => Reading::TextSearchParser,
        'copy' => Reading::TextSearchConfiguration,
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
