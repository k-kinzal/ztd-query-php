<?php

declare(strict_types=1);

namespace SqlFormatter\Compact;

use SqlParser\Lexer\SourceException;
use SqlParser\Lexer\Token;
use SqlParser\MySql\MySqlParser;
use SqlParser\PostgreSql\PostgreSqlParser;
use SqlParser\Sqlite\SqliteParser;

/**
 * Uses the configured lexer to distinguish required separators from layout whitespace.
 *
 * @visibility SqlFormatter
 */
final class Spacing
{
    /**
     * Reuses the caller's grammar release and lexical SQL mode.
     */
    public function __construct(private readonly MySqlParser|PostgreSqlParser|SqliteParser $parser)
    {
    }

    /**
     * Includes lookahead to protect MySQL qualified keywords and function names.
     */
    public function between(Token $left, Token $right, ?Token $next, ?Token $before = null): string
    {
        if ($this->parser instanceof MySqlParser
            && (($left->text === '@' && ($right->text === '@' || $before?->text === '@'))
                || ($before?->text === '@' && $right->text === '.'))) {
            return '';
        }
        $suffix = $next === null ? '' : ($right->text === '.' ? '' : ' ') . $next->text;
        try {
            $joined = $this->signature($left->text . $right->text . $suffix);
            $separate = $this->signature($left->text . ' ' . $right->text . $suffix);
        } catch (SourceException) {
            return ' ';
        }
        if ($joined === $separate) {
            return '';
        }
        if (($joined[0] ?? null) === [$left->name, $left->text] && ($joined[1] ?? null) === [$right->name, $right->text]) {
            return '';
        }
        return ' ';
    }

    /**
     * @return list<array{string, string}>
     * @throws SourceException When a candidate joins tokens into an invalid lexeme
     */
    public function signature(string $sql): array
    {
        return array_map(static fn (Token $token): array => [$token->name, $token->text], $this->parser->tokenize($sql));
    }
}
