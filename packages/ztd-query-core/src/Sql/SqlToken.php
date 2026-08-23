<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

/**
 * A token with its original byte span and nesting level.
 */
final class SqlToken
{
    public function __construct(
        public readonly SqlTokenKind $kind,
        public readonly string $text,
        public readonly int $offset,
        public readonly int $depth,
        public readonly int $bracketDepth,
    ) {
    }

    public function endOffset(): int
    {
        return $this->offset + strlen($this->text);
    }

    public function isTopLevel(): bool
    {
        return $this->depth === 0 && $this->bracketDepth === 0;
    }

    public function isKeyword(string $keyword): bool
    {
        return $this->kind === SqlTokenKind::Word && strcasecmp($this->text, $keyword) === 0;
    }
}
