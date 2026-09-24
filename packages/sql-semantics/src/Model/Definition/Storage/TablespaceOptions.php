<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Storage;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Initial tablespace properties; each omitted option keeps the storage engine's default.
 * @visibility public
 * @example Reading sizes in bytes
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CREATE TABLESPACE ts ADD DATAFILE 'ts.ibd' INITIAL_SIZE 1M FILE_BLOCK_SIZE = 8192 NO_WAIT");
 *     [$statement->options->initialSize, $statement->options->fileBlockSize, $statement->options->waiting->value] // => [1048576, 8192, 'NO_WAIT']
 */
final class TablespaceOptions
{
    /**
     * Sizes count bytes; the node group and comment apply to NDB cluster storage.
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly ?int $initialSize = null,
        public readonly ?int $autoextendSize = null,
        public readonly ?int $maxSize = null,
        public readonly ?int $extentSize = null,
        public readonly ?int $fileBlockSize = null,
        public readonly ?int $nodegroup = null,
        public readonly ?string $engine = null,
        public readonly ?string $comment = null,
        public readonly ?StorageEncryption $encryption = null,
        public readonly ?string $engineAttribute = null,
        public readonly CompletionWait $waiting = CompletionWait::Wait,
    ) {
        StorageInvariant::quantities($initialSize, $autoextendSize, $maxSize, $extentSize, $fileBlockSize, $nodegroup);
        StorageInvariant::engine($engine, $engineAttribute);
    }
}
