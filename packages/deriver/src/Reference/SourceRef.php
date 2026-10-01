<?php

declare(strict_types=1);

namespace Deriver\Reference;

use Deriver\Exception\InvalidInputException;

/**
 * A half-open byte range in an immutable source snapshot.
 *
 * @visibility public
 * @example Referring to the first byte
 *     (new \Deriver\Reference\SourceRef('snapshot', 'example.php', 0, 1))->end // => 1
 */
final class SourceRef
{
    /**
     * @param string $snapshotId Snapshot owning the range
     * @param string $path Normalized source path
     * @param int $start Inclusive byte offset
     * @param int $end Exclusive byte offset
     * @param int $line One-based display line
     * @param int $column One-based display column
     * @throws InvalidInputException If the range or display coordinates are invalid
     */
    public function __construct(
        public readonly string $snapshotId,
        public readonly string $path,
        public readonly int $start,
        public readonly int $end,
        public readonly int $line = 1,
        public readonly int $column = 1,
    ) {
        if ($start < 0 || $end < $start || $line < 1 || $column < 1) {
            throw new InvalidInputException('Source ranges must be half-open and coordinates positive.');
        }
    }

    /**
     * Identifies this range within its snapshot.
     * @return string Stable source identity
     */
    public function id(): string
    {
        return $this->snapshotId . ':' . $this->path . ':' . $this->start . ':' . $this->end;
    }
}
