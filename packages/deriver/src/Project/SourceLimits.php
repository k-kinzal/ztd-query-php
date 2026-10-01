<?php

declare(strict_types=1);

namespace Deriver\Project;

use Deriver\Exception\InvalidInputException;

/**
 * Deterministic admission limits for captured source before query evaluation starts.
 *
 * Exceeding a limit rejects opening the project; it never creates a partial declaration world.
 * Runtime source capture remains the caller's responsibility when supplying in-memory strings.
 *
 * @visibility public
 * @example Admitting a larger generated project
 *     (new \Deriver\Project\SourceLimits(nodes: 500000))->nodes // => 500000
 */
final class SourceLimits
{
    /**
     * @param int $files Maximum captured files
     * @param int $bytes Maximum combined captured source bytes
     * @param int $fileBytes Maximum bytes in one parser invocation
     * @param int $nodes Maximum total syntax nodes across the project
     * @param int $depth Maximum syntax nesting before recursive visitors or lowering
     * @throws InvalidInputException If an admission limit is not positive
     */
    public function __construct(public readonly int $files = 10000, public readonly int $bytes = 67108864, public readonly int $fileBytes = 4194304, public readonly int $nodes = 250000, public readonly int $depth = 128)
    {
        if (min($files, $bytes, $fileBytes, $nodes, $depth) < 1) {
            throw new InvalidInputException('Source limits must be positive.');
        }
    }

    /**
     * Validates total source size before hashing, parsing, or indexing any file.
     * @param ProjectInput $input Captured project
     * @throws InvalidInputException If source exceeds the explicit admission policy
     */
    public function check(ProjectInput $input): void
    {
        if (count($input->files) > $this->files) {
            throw new InvalidInputException('SOURCE_LIMIT: captured file count exceeds the admission limit.');
        }
        $remaining = $this->bytes;
        foreach ($input->files as $file) {
            $size = strlen($file->contents);
            if ($size > $this->fileBytes || $size > $remaining) {
                throw new InvalidInputException('SOURCE_LIMIT: captured source bytes exceed the admission limit.');
            }
            $remaining -= $size;
        }
    }
}
