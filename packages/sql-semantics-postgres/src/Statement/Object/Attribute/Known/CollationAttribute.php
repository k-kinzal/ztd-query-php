<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known;

use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\KnownAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;

/**
 * An attribute `CREATE COLLATION name ( ... )` recognizes.
 *
 * `DefineCollation` reads `from` as a collation to copy, `provider` as
 * a fixed word, `deterministic` as a Boolean and the locale attributes,
 * `rules` and `version` as text. Each attribute may be given once; any other
 * attribute is an error.
 * Source: https://www.postgresql.org/docs/17/sql-createcollation.html, `DefineCollation` in `src/backend/commands/collationcmds.c` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading how the command reads an attribute
 *     \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\CollationAttribute::from('provider')->reading() // => \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading::Provider
 */
enum CollationAttribute: string implements KnownAttribute
{
    case From = 'from';
    case Locale = 'locale';
    case LcCollate = 'lc_collate';
    case LcCtype = 'lc_ctype';
    case Provider = 'provider';
    case Deterministic = 'deterministic';
    case Rules = 'rules';
    case Version = 'version';

    /**
     * How the command reads each attribute.
     */
    private const READINGS = [
        'from' => Reading::Collation,
        'locale' => Reading::Text,
        'lc_collate' => Reading::Text,
        'lc_ctype' => Reading::Text,
        'provider' => Reading::Provider,
        'deterministic' => Reading::Boolean,
        'rules' => Reading::Text,
        'version' => Reading::Text,
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
