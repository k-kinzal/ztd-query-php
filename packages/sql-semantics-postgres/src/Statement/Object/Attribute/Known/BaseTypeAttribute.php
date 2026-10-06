<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known;

use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\KnownAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;

/**
 * An attribute `CREATE TYPE name ( ... )` recognizes for a base type.
 *
 * `DefineType` reads the support functions (`input`, `output`,
 * `receive`, `send`, `typmod_in`, `typmod_out`, `analyze` or `analyse`,
 * `subscript`) as routine names, `like` and `element` as type names,
 * `internallength` as a length or `variable`, `category`, `delimiter` and
 * `default` as text, `alignment` and `storage` as fixed words and the rest as
 * Booleans. Each attribute may be given once. Any other attribute draws a
 * warning and is ignored.
 * Source: https://www.postgresql.org/docs/17/sql-createtype.html, `DefineType` in `src/backend/commands/typecmds.c` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading how the command reads an attribute
 *     \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\BaseTypeAttribute::from('element')->reading() // => \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading::Type
 */
enum BaseTypeAttribute: string implements KnownAttribute
{
    case Like = 'like';
    case Internallength = 'internallength';
    case Input = 'input';
    case Output = 'output';
    case Receive = 'receive';
    case Send = 'send';
    case TypmodIn = 'typmod_in';
    case TypmodOut = 'typmod_out';
    case Analyze = 'analyze';
    case Analyse = 'analyse';
    case Subscript = 'subscript';
    case Category = 'category';
    case Preferred = 'preferred';
    case Delimiter = 'delimiter';
    case Element = 'element';
    case Default = 'default';
    case Passedbyvalue = 'passedbyvalue';
    case Alignment = 'alignment';
    case Storage = 'storage';
    case Collatable = 'collatable';

    /**
     * How the command reads each attribute.
     */
    private const READINGS = [
        'like' => Reading::Type,
        'internallength' => Reading::Length,
        'input' => Reading::Function,
        'output' => Reading::Function,
        'receive' => Reading::Function,
        'send' => Reading::Function,
        'typmod_in' => Reading::Function,
        'typmod_out' => Reading::Function,
        'analyze' => Reading::Function,
        'analyse' => Reading::Function,
        'subscript' => Reading::Function,
        'category' => Reading::Text,
        'preferred' => Reading::Boolean,
        'delimiter' => Reading::Text,
        'element' => Reading::Type,
        'default' => Reading::Text,
        'passedbyvalue' => Reading::Boolean,
        'alignment' => Reading::Alignment,
        'storage' => Reading::Storage,
        'collatable' => Reading::Boolean,
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
