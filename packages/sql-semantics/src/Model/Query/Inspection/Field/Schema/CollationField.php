<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Schema;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;

/**
 * Result fields of SHOW COLLATION.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Schema\CollationField::Name->label() // => 'Collation'
 */
enum CollationField: string implements MetadataField
{
    use TextField;

    case Name = 'Collation';
    case CharacterSet = 'Charset';
    case Id = 'Id';
    case Default = 'Default';
    case Compiled = 'Compiled';
    case SortLength = 'Sortlen';
    case PadAttribute = 'Pad_attribute';

    /**
     * Identity and sort length are integers.
     */
    public function type(): string
    {
        return match ($this) {
            self::Id, self::SortLength => 'bigint',
            self::Name, self::CharacterSet, self::Default, self::Compiled, self::PadAttribute => 'varchar',
        };
    }

    /**
     * @param bool $legacy Whether the release predates the pad attribute
     * @return list<self> Fields in result order for the release
     */
    public static function listing(bool $legacy): array
    {
        return $legacy ? [self::Name, self::CharacterSet, self::Id, self::Default, self::Compiled, self::SortLength] : self::cases();
    }
}
