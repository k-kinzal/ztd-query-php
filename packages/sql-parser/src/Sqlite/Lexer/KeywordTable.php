<?php

declare(strict_types=1);

namespace SqlParser\Sqlite\Lexer;

use RuntimeException;

/**
 * The words SQLite's tokenizer turns into keyword tokens.
 *
 * Several words share a token: the join words are all `JOIN_KW`, and
 * `LIKE`, `GLOB` and `REGEXP` are all `LIKE_KW`. Which keywords may fall
 * back to identifiers is the grammar's business.
 *
 * @visibility root
 */
final class KeywordTable
{
    /**
     * @param array<string, string> $keywords Terminal name by upper-cased keyword
     */
    public function __construct(private readonly array $keywords)
    {
    }

    /**
     * Loads the table generated for one release.
     *
     * @param string $path Keyword resource file
     *
     * @return self The table
     *
     * @throws RuntimeException When the file is missing or malformed
     */
    public static function load(string $path): self
    {
        if (!is_file($path)) {
            throw new RuntimeException("Keyword table not found: {$path}");
        }
        /** @var array{keywords: array<string, string>} $data */
        $data = require $path;

        return new self($data['keywords']);
    }

    /**
     * Answers the terminal a word stands for, if it is a keyword.
     *
     * @param string $word Word as written
     *
     * @return string|null Terminal name, or null for an identifier
     */
    public function lookup(string $word): ?string
    {
        return $this->keywords[strtoupper($word)] ?? null;
    }
}
