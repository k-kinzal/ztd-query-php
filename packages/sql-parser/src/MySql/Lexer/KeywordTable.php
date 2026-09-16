<?php

declare(strict_types=1);

namespace SqlParser\MySql\Lexer;

use RuntimeException;
use SqlParser\MySql\SqlMode;

/**
 * The words and operators MySQL's lexer turns into keyword tokens.
 *
 * Function names are keywords only when a parenthesis follows them at once,
 * which is what lets a column be called `count`. Two lookups depend on the
 * SQL mode: `NOT` binds tightly under `HIGH_NOT_PRECEDENCE`, and `||` is
 * `OR` unless `PIPES_AS_CONCAT` makes it concatenation.
 *
 * @visibility root
 */
final class KeywordTable
{
    /**
     * @param array<string, string> $keywords Terminal name by upper-cased keyword or operator
     * @param array<string, string> $functions Terminal name by upper-cased function name
     */
    public function __construct(
        private readonly array $keywords,
        private readonly array $functions,
    ) {
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
        /** @var array{keywords: array<string, string>, functions: array<string, string>} $data */
        $data = require $path;

        return new self($data['keywords'], $data['functions']);
    }

    /**
     * Answers the terminal a word or operator stands for, if it is a keyword.
     *
     * @param string $word Word or operator as written
     * @param bool $function Whether a parenthesis follows, so function names count too
     * @param SqlMode $mode Mode deciding `NOT` and `||`
     *
     * @return string|null Terminal name, or null for an identifier
     */
    public function lookup(string $word, bool $function, SqlMode $mode): ?string
    {
        $upper = strtoupper($word);
        $terminal = $this->keywords[$upper] ?? ($function ? ($this->functions[$upper] ?? null) : null);
        if ($terminal === 'NOT_SYM' && $mode->highNotPrecedence) {
            return 'NOT2_SYM';
        }
        if ($terminal === 'OR_OR_SYM' && !$mode->pipesAsConcat) {
            return 'OR2_SYM';
        }

        return $terminal;
    }

    /**
     * Reports whether the release knows a keyword or operator at all.
     *
     * @param string $word Word or operator as written
     *
     * @return bool True when it is a keyword, in any lookup
     */
    public function has(string $word): bool
    {
        return isset($this->keywords[strtoupper($word)]);
    }
}
