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
 * @example Reading a PostgreSQL storage strategy and compression method
 *     $column = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::PostgreSql))->build('CREATE TABLE t(body text STORAGE EXTERNAL COMPRESSION pglz)')->tables[0]->columns[0];
 *     [$column->attributes->storageStrategy, $column->attributes->compression] // => [\SqlSemantics\Model\Definition\Relation\Column\ColumnStorageMode::External, 'pglz']
 */
final class Attributes
{
    /**
     * Constructs a valid declaration; the MySQL storage medium and the PostgreSQL storage strategy are never declared together.
     *
     * @param \SqlSemantics\Model\Definition\Relation\Column\ColumnStorageMode|null $storageStrategy PostgreSQL TOAST strategy (STORAGE PLAIN, EXTERNAL, EXTENDED, MAIN or DEFAULT)
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
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
        public readonly ?\SqlSemantics\Model\Definition\Relation\Column\ColumnStorageMode $storageStrategy = null,
    ) {
        if ($storage !== null && $storageStrategy !== null) {
            throw new \SqlSemantics\Model\Validation\InvalidStructure('A column declares either a MySQL storage medium or a PostgreSQL storage strategy.');
        }
    }
}
