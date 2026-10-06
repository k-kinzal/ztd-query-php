<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known;

use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\KnownAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;

/**
 * A property `ALTER TYPE ... SET` recognizes.
 *
 * `AlterType` reads `storage` as one of the storage words and `receive`,
 * `send`, `typmod_in`, `typmod_out`, `analyze` and `subscript` as functions
 * (without a value, or with NONE, the function is removed). The other
 * attributes of CREATE TYPE are recognized only to be refused: they cannot be
 * changed. Any other property, `analyse` included, is an error.
 * Source: https://www.postgresql.org/docs/17/sql-altertype.html, `AlterType` in `src/backend/commands/typecmds.c` of PostgreSQL 16 and 17.
 *
 * @visibility public
 * @example Reading how the command reads a property
 *     \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\TypeChangeAttribute::from('storage')->reading() // => \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading::Storage
 */
enum TypeChangeAttribute: string implements KnownAttribute
{
    case Storage = 'storage';
    case Receive = 'receive';
    case Send = 'send';
    case TypmodIn = 'typmod_in';
    case TypmodOut = 'typmod_out';
    case Analyze = 'analyze';
    case Subscript = 'subscript';
    case Input = 'input';
    case Output = 'output';
    case InternalLength = 'internallength';
    case PassedByValue = 'passedbyvalue';
    case Alignment = 'alignment';
    case Like = 'like';
    case Category = 'category';
    case Preferred = 'preferred';
    case Default = 'default';
    case Element = 'element';
    case Delimiter = 'delimiter';
    case Collatable = 'collatable';

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
     * Answers how the command reads the property's value; the properties that cannot be changed are not read.
     */
    public function reading(): Reading
    {
        return match ($this) {
            self::Storage => Reading::Storage,
            self::Receive, self::Send, self::TypmodIn, self::TypmodOut, self::Analyze, self::Subscript => Reading::Function,
            self::Input, self::Output, self::InternalLength, self::PassedByValue, self::Alignment, self::Like, self::Category,
            self::Preferred, self::Default, self::Element, self::Delimiter, self::Collatable => Reading::Ignored,
        };
    }
}
