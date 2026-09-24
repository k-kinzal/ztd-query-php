<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Storage;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Tablespace properties an alteration changes; each omitted option is left unchanged.
 * @visibility public
 * @example Reading a requested growth step
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("ALTER TABLESPACE ts AUTOEXTEND_SIZE = 4M ENCRYPTION = 'Y'");
 *     [$statement->changes->autoextendSize, $statement->changes->encryption->value] // => [4194304, 'Y']
 */
final class TablespaceChanges
{
    /**
     * Sizes count bytes.
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly ?int $initialSize = null,
        public readonly ?int $autoextendSize = null,
        public readonly ?int $maxSize = null,
        public readonly ?string $engine = null,
        public readonly ?StorageEncryption $encryption = null,
        public readonly ?string $engineAttribute = null,
        public readonly CompletionWait $waiting = CompletionWait::Wait,
    ) {
        StorageInvariant::quantities($initialSize, $autoextendSize, $maxSize);
        StorageInvariant::engine($engine, $engineAttribute);
    }
}
