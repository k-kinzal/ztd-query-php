<?php

declare(strict_types=1);

namespace ZtdQuery\Platform;

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
