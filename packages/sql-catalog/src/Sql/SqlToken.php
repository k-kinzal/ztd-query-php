<?php

declare(strict_types=1);

namespace SqlCatalog\Sql;

/**
 * One token of a SQL statement, with the nesting it was found at.
 *
 * @visibility root
 */
final class SqlToken
{
    /**
     * @param SqlTokenKind $kind What the token is
     * @param string $text The token exactly as written
     * @param int $offset Where the token starts in the statement
     * @param int $depth How many unclosed parentheses enclose the token
     */
    public function __construct(
        public readonly SqlTokenKind $kind,
        public readonly string $text,
        public readonly int $offset,
        public readonly int $depth,
    ) {
    }

    /**
     * The token upper-cased, for comparing keywords.
     */
    public function keyword(): string
    {
        return strtoupper($this->text);
    }

    /**
     * Whether the token is the given keyword at the outermost nesting level.
     */
    public function isTopLevelKeyword(string $keyword): bool
    {
        return $this->depth === 0 && $this->kind === SqlTokenKind::Word && $this->keyword() === $keyword;
    }
}
