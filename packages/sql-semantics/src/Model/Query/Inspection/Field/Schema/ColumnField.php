<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Schema;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;
use SqlSemantics\Type\Nullability;

/**
 * Result fields of SHOW COLUMNS; FULL adds collation, privileges, and comment.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Schema\ColumnField::Name->label() // => 'Field'
 */
enum ColumnField: string implements MetadataField
{
    use TextField;

    case Name = 'Field';
    case Type = 'Type';
    case Collation = 'Collation';
    case Null = 'Null';
    case Key = 'Key';
    case Default = 'Default';
    case Extra = 'Extra';
    case Privileges = 'Privileges';
    case Comment = 'Comment';

    /**
     * Collation and default are absent when a column has none.
     */
    public function nullability(): Nullability
    {
        return match ($this) {
            self::Collation, self::Default => Nullability::MaybeNull,
            self::Name, self::Type, self::Null, self::Key, self::Extra, self::Privileges, self::Comment => Nullability::NotNull,
        };
    }

    /**
     * @return list<self> Fields in result order for the requested detail
     */
    public static function listing(bool $full): array
    {
        return $full ? self::cases() : [self::Name, self::Type, self::Null, self::Key, self::Default, self::Extra];
    }
}
