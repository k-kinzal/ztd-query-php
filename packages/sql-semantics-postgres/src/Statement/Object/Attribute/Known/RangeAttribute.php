<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known;

use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\KnownAttribute;
use SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading;

/**
 * An attribute `CREATE TYPE name AS RANGE ( ... )` recognizes.
 *
 * `DefineRange` reads `subtype` as a type name, `subtype_opclass` as an
 * operator class, `collation` as a collation, `canonical` and `subtype_diff`
 * as routine names and `multirange_type_name` as the name of the multirange
 * type it creates. Each attribute may be given once; any other attribute is
 * an error.
 * Source: https://www.postgresql.org/docs/17/sql-createtype.html, `DefineRange` in `src/backend/commands/typecmds.c` of PostgreSQL 17.
 *
 * @visibility public
 * @example Reading how the command reads an attribute
 *     \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Known\RangeAttribute::from('multirange_type_name')->reading() // => \SqlSemantics\Platform\PostgreSql\Statement\Object\Attribute\Reading::CreatedType
 */
enum RangeAttribute: string implements KnownAttribute
{
    case Subtype = 'subtype';
    case SubtypeOpclass = 'subtype_opclass';
    case Collation = 'collation';
    case Canonical = 'canonical';
    case SubtypeDiff = 'subtype_diff';
    case MultirangeTypeName = 'multirange_type_name';

    /**
     * How the command reads each attribute.
     */
    private const READINGS = [
        'subtype' => Reading::Type,
        'subtype_opclass' => Reading::OperatorClass,
        'collation' => Reading::Collation,
        'canonical' => Reading::Function,
        'subtype_diff' => Reading::Function,
        'multirange_type_name' => Reading::CreatedType,
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
