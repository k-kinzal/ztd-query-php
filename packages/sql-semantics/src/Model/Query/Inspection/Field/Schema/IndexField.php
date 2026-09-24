<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Query\Inspection\Field\Schema;

use SqlSemantics\Model\Query\Inspection\MetadataField;
use SqlSemantics\Model\Query\Inspection\TextField;
use SqlSemantics\Type\Nullability;

/**
 * Result fields of SHOW INDEX; EXTENDED adds hidden index columns rather than fields.
 * @visibility public
 * @example Inspecting a result label
 *     \SqlSemantics\Model\Query\Inspection\Field\Schema\IndexField::Table->label() // => 'Table'
 */
enum IndexField: string implements MetadataField
{
    use TextField;

    case Table = 'Table';
    case NonUnique = 'Non_unique';
    case KeyName = 'Key_name';
    case SequenceInIndex = 'Seq_in_index';
    case ColumnName = 'Column_name';
    case Collation = 'Collation';
    case Cardinality = 'Cardinality';
    case SubPart = 'Sub_part';
    case Packed = 'Packed';
    case Null = 'Null';
    case IndexType = 'Index_type';
    case Comment = 'Comment';
    case IndexComment = 'Index_comment';
    case Visible = 'Visible';
    case Expression = 'Expression';

    /**
     * Counters and positions are integers.
     */
    public function type(): string
    {
        return match ($this) {
            self::NonUnique, self::SequenceInIndex, self::Cardinality, self::SubPart => 'bigint',
            self::Table, self::KeyName, self::ColumnName, self::Collation, self::Packed, self::Null, self::IndexType, self::Comment, self::IndexComment, self::Visible, self::Expression => 'varchar',
        };
    }

    /**
     * Column-specific facts are absent for functional key parts and unknown statistics.
     */
    public function nullability(): Nullability
    {
        return match ($this) {
            self::ColumnName, self::Collation, self::Cardinality, self::SubPart, self::Packed, self::Expression => Nullability::MaybeNull,
            self::Table, self::NonUnique, self::KeyName, self::SequenceInIndex, self::Null, self::IndexType, self::Comment, self::IndexComment, self::Visible => Nullability::NotNull,
        };
    }

    /**
     * @param bool $legacy Whether the release predates invisible indexes and functional key parts
     * @return list<self> Fields in result order for the release
     */
    public static function listing(bool $legacy): array
    {
        return $legacy ? [self::Table, self::NonUnique, self::KeyName, self::SequenceInIndex, self::ColumnName, self::Collation, self::Cardinality, self::SubPart, self::Packed, self::Null, self::IndexType, self::Comment, self::IndexComment] : self::cases();
    }
}
