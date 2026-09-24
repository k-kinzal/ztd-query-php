<?php

declare(strict_types=1);

namespace SqlSemantics\Model\Definition\Storage;

use SqlSemantics\Model\Validation\InvalidStructure;

/**
 * Initial NDB logfile group properties; each omitted option keeps the engine's default.
 * @visibility public
 * @example Reading the undo buffer size
 *     $statement = (new \SqlSemantics\Binder((new \SqlSemantics\SchemaBuilder(\SqlSemantics\Dialect::MySql))->build()))->bind("CREATE LOGFILE GROUP lg ADD UNDOFILE 'undo.log' UNDO_BUFFER_SIZE 8M ENGINE NDB");
 *     [$statement->options->undoBufferSize, $statement->options->engine] // => [8388608, 'NDB']
 */
final class LogfileGroupOptions
{
    /**
     * Sizes count bytes.
     * @throws InvalidStructure
     */
    public function __construct(
        public readonly ?int $initialSize = null,
        public readonly ?int $undoBufferSize = null,
        public readonly ?int $redoBufferSize = null,
        public readonly ?int $nodegroup = null,
        public readonly ?string $engine = null,
        public readonly ?string $comment = null,
        public readonly CompletionWait $waiting = CompletionWait::Wait,
    ) {
        StorageInvariant::quantities($initialSize, $undoBufferSize, $redoBufferSize, $nodegroup);
        StorageInvariant::engine($engine);
    }
}
