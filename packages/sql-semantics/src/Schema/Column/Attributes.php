<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Column;

/**
 * Declared presentation and storage properties of one column.
 *
 * @visibility public
 * @example Reading declared column attributes
 *     $column = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build("CREATE TABLE t(name VARCHAR(10) COMMENT 'label' INVISIBLE COLUMN_FORMAT FIXED STORAGE DISK)")->tables[0]->columns[0];
 *     $column->attributes->comment // => 'label'
 *     $column->attributes->visible // => false
 *     $column->attributes->format // => \SqlSemantics\Schema\Column\Format::Fixed
 *     $column->attributes->storage // => \SqlSemantics\Schema\Column\Storage::Disk
 */
final class Attributes
{
    /**
     * Constructs a valid declaration.

     */
    public function __construct(
        public readonly ?\SqlSemantics\Model\Relation\QualifiedName $collation = null,
        public readonly ?string $characterSet = null,
        public readonly ?string $comment = null,
        public readonly ?bool $visible = null,
        public readonly ?Storage $storage = null,
        public readonly ?Format $format = null,
        public readonly ?string $compression = null,
        public readonly ?string $engineAttribute = null,
        public readonly ?string $secondaryEngineAttribute = null,
        public readonly ?int $spatialReferenceId = null,
        public readonly bool $zeroFill = false,
        public readonly bool $binary = false,
    ) {
    }
}
