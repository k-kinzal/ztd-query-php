<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Schema;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;
use SqlSemantics\Type\Nullability;

/**
 * Result fields of SHOW TABLE STATUS.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Schema\TableStatusField::Name->label() // => 'Name'
 */
enum TableStatusField: string implements MetadataField
{
    use TextField;

    case Name = 'Name';
    case Engine = 'Engine';
    case Version = 'Version';
    case RowFormat = 'Row_format';
    case Rows = 'Rows';
    case AverageRowLength = 'Avg_row_length';
    case DataLength = 'Data_length';
    case MaximumDataLength = 'Max_data_length';
    case IndexLength = 'Index_length';
    case DataFree = 'Data_free';
    case AutoIncrement = 'Auto_increment';
    case CreateTime = 'Create_time';
    case UpdateTime = 'Update_time';
    case CheckTime = 'Check_time';
    case Collation = 'Collation';
    case Checksum = 'Checksum';
    case CreateOptions = 'Create_options';
    case Comment = 'Comment';

    /**
     * Sizes and counters are integers; maintenance instants are timestamps.
     */
    public function type(): string
    {
        return match ($this) {
            self::Version, self::Rows, self::AverageRowLength, self::DataLength, self::MaximumDataLength, self::IndexLength, self::DataFree, self::AutoIncrement, self::Checksum => 'bigint',
            self::CreateTime, self::UpdateTime, self::CheckTime => 'datetime',
            self::Name, self::Engine, self::RowFormat, self::Collation, self::CreateOptions, self::Comment => 'varchar',
        };
    }

    /**
     * Storage facts are absent for views and engines that do not report them.
     */
    public function nullability(): Nullability
    {
        return $this === self::Name ? Nullability::NotNull : Nullability::MaybeNull;
    }
}
