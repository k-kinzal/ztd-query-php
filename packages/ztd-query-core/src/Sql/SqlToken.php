<?php

declare(strict_types=1);

namespace ZtdQuery\Sql;

/**
 * One lexeme, with where it was written and how deeply it was nested.
 *
 * The offset is what lets a rewriter put a statement back together around a
 * token it wants to change, and the two depths are what let it tell a clause
 * of the statement itself from the same words inside a subquery or a
 * parenthesised list.
 */
final class SqlToken
{
    /**
     * @param SqlTokenKind $kind What kind of lexeme this is
     * @param string $text The lexeme, exactly as it was written
     * @param int $offset Where in the statement it starts
     * @param int $depth How many parentheses it is inside
     * @param int $bracketDepth How many brackets it is inside
     */
    public function __construct(
        public readonly SqlTokenKind $kind,
        public readonly string $text,
        public readonly int $offset,
        public readonly int $depth,
        public readonly int $bracketDepth,
    ) {
    }

    /**
     * Answers the token a stretch of the statement was read as.
     *
     * @param string $sql The statement, as written
     * @param SqlTokenKind $kind What kind of lexeme the stretch was read as
     * @param int $start Where the lexeme starts
     * @param int $end Where it ends, just past its last byte
     * @param int $depth How many parentheses it is inside
     * @param int $bracketDepth How many brackets it is inside
     *
     * @return self The lexeme, carrying the text it was written as
     */
    public static function slice(
        string $sql,
        SqlTokenKind $kind,
        int $start,
        int $end,
        int $depth,
        int $bracketDepth,
    ): self {
        return new self($kind, substr($sql, $start, $end - $start), $start, $depth, $bracketDepth);
    }

    /**
     * Answers where in the statement the lexeme ends.
     *
     * @return int The offset just past its last byte
     */
    public function endOffset(): int
    {
        return $this->offset + strlen($this->text);
    }

    /**
     * Reports whether the lexeme belongs to the statement itself.
     *
     * @return bool True when it is inside no parenthesis and no bracket
     */
    public function isTopLevel(): bool
    {
        return $this->depth === 0 && $this->bracketDepth === 0;
    }

    /**
     * Reports whether the lexeme is a given keyword.
     *
     * SQL keywords are not case-sensitive, and neither is this.
     *
     * @param string $keyword Keyword to test against
     *
     * @return bool True when the lexeme is a word spelling that keyword
     */
    public function isKeyword(string $keyword): bool
    {
        return $this->kind === SqlTokenKind::Word && strcasecmp($this->text, $keyword) === 0;
    }
}
