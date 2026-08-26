<?php

declare(strict_types=1);

namespace ZtdQuery\Platform;

/**
 * What a COPY statement is written against.
 *
 * The relation as it was named, which may be qualified, and the columns the
 * rows carry in the order they carry them.
 */
final class CopyTarget
{
    /**
     * @param non-empty-list<string> $relation
     * @param non-empty-list<string> $columns
     */
    public function __construct(
        public readonly array $relation,
        public readonly array $columns,
    ) {
    }

    public function tableName(): string
    {
        return $this->relation[count($this->relation) - 1];
    }
}
