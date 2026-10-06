<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known;

use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\KnownAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;

/**
 * An attribute `CREATE TEXT SEARCH TEMPLATE` recognizes.
 *
 * `DefineTSTemplate` reads `init` and `lexize` as the names of the
 * template's functions. Any other attribute is an error.
 * Source: https://www.postgresql.org/docs/17/sql-createtstemplate.html, `DefineTSTemplate` in `src/backend/commands/tsearchcmds.c` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading how the command reads an attribute
 *     \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\TemplateAttribute::from('lexize')->reading() // => \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading::Function
 */
enum TemplateAttribute: string implements KnownAttribute
{
    case Init = 'init';
    case Lexize = 'lexize';

    /**
     * How the command reads each attribute.
     */
    private const READINGS = [
        'init' => Reading::Function,
        'lexize' => Reading::Function,
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
