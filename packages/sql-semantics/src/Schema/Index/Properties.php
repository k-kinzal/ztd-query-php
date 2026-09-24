<?php

declare(strict_types=1);

namespace SqlSemantics\Schema\Index;

/**
 * Index storage and access properties.
 *
 * @visibility public
 * @example Reading MySQL index options
 *     $index = (new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build("CREATE TABLE t(id INT); CREATE INDEX ix ON t(id) KEY_BLOCK_SIZE=8 COMMENT 'c' INVISIBLE")->tables[0]->indexes[0];
 *     $index->properties->keyBlockSize // => 8
 *     $index->properties->comment // => 'c'
 *     $index->properties->visible // => false
 */
final class Properties
{
    /**
     * @param list<\SqlSemantics\Schema\Storage\Parameter> $storageParameters
     * Constructs a valid declaration.

     */
    public function __construct(
        public readonly Kind $kind = Kind::Ordinary,
        public readonly ?bool $visible = null,
        public readonly ?int $keyBlockSize = null,
        public readonly ?string $comment = null,
        public readonly ?\SqlSemantics\Model\Relation\QualifiedName $parser = null,
        public readonly ?string $tablespace = null,
        public readonly ?string $engineAttribute = null,
        public readonly ?string $secondaryEngineAttribute = null,
        public readonly bool $nullsDistinct = true,
        public readonly array $storageParameters = [],
    ) {
    }
}
