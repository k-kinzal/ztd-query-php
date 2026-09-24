<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Table;

use Override;

/**
 * MySQL table storage options with classified values.
 *
 * @visibility public
 * @example Reading MySQL table options
 *     $table = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build('CREATE TEMPORARY TABLE t(id INT) ENGINE=InnoDB ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8')->tables[0];
 *     $table->properties instanceof \SqlSemantics\Schema\Table\MySqlProperties // => true
 *     $table->properties->temporary // => true
 *     $table->properties->engine // => 'InnoDB'
 *     $table->properties->rowFormat // => \SqlSemantics\Schema\Table\RowFormat::Compressed
 *     $table->properties->keyBlockSize // => 8
 */
final class MySqlProperties implements Properties
{
    /**
     * Constructs a valid declaration; UNION lists the tables of a MERGE table and AUTOEXTEND_SIZE counts bytes.
     * @param list<\SqlSemantics\Model\Relation\QualifiedName>|null $union
     * @throws \SqlSemantics\Model\Validation\InvalidStructure
     */
    public function __construct(
        public readonly bool $temporary = false,
        public readonly ?string $engine = null,
        public readonly ?string $characterSet = null,
        public readonly ?string $collation = null,
        public readonly ?string $comment = null,
        public readonly ?string $compression = null,
        public readonly ?string $encryption = null,
        public readonly ?string $connection = null,
        public readonly ?string $dataDirectory = null,
        public readonly ?string $indexDirectory = null,
        public readonly ?string $password = null,
        public readonly ?string $tablespace = null,
        public readonly ?string $engineAttribute = null,
        public readonly ?string $secondaryEngineAttribute = null,
        public readonly ?int $autoIncrement = null,
        public readonly ?int $averageRowLength = null,
        public readonly ?int $checksum = null,
        public readonly ?int $delayKeyWrite = null,
        public readonly ?int $keyBlockSize = null,
        public readonly ?int $maxRows = null,
        public readonly ?int $minRows = null,
        public readonly ?RowFormat $rowFormat = null,
        public readonly ?bool $packKeys = null,
        public readonly ?bool $statsAutoRecalc = null,
        public readonly ?bool $statsPersistent = null,
        public readonly ?int $statsSamplePages = null,
        public readonly ?TableStorage $storage = null,
        public readonly ?string $secondaryEngine = null,
        public readonly ?MergeInsertMethod $insertMethod = null,
        public readonly ?array $union = null,
        public readonly bool $startTransaction = false,
        public readonly ?int $autoextendSize = null,
        public readonly ?\SqlSemantics\Model\Definition\MySqlTable\Partition\TablePartitioning $partitioning = null,
    ) {
        if ($union !== null) {
            \SqlSemantics\Model\Validation\Collections::objects($union, \SqlSemantics\Model\Relation\QualifiedName::class);
        }
    }
    /**
     * Returns the SQL dialect that defines these options.
     */
    #[Override]
    public function dialect(): \SqlSemantics\Dialect
    {
        return \SqlSemantics\Dialect::MySql;
    }
}
