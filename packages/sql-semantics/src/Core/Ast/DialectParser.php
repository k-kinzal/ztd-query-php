<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Ast;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlParser\Parser\SqlParser;
use SqlParser\Parser\SyntaxException;
use SqlSemantics\Core\Language;

/**
 * Parses SQL of one language and finds the statement boundaries of a script.
 *
 * A server reads one statement at a time and stops at the semicolon that
 * ends it, while a semicolon inside a compound statement, a trigger body, a
 * rule action, a string, or a comment ends nothing. The boundaries are found
 * the same way: a statement ends at the first semicolon after which the text
 * so far is a complete statement of the grammar.
 *
 * @visibility SqlSemantics
 */
final class DialectParser
{
    private readonly SqlParser $parser;

    /**
     * Parses with the parser of the language.
     */
    public function __construct(Language $language)
    {
        $this->parser = $language->parser();
    }

    /**
     * Parses SQL without discarding source trivia or locations.
     *
     * @throws \SqlParser\Lexer\LexicalException When SQL contains invalid tokens
     * @throws SyntaxException When SQL does not match the grammar
     */
    public function parse(string $sql): Node
    {
        return $this->parser->parse($sql);
    }

    /**
     * Splits a script into the texts of its statements, each ending with its own terminator.
     *
     * The texts partition the script apart from trailing whitespace and
     * comments, which stay with the last statement; a script holding only
     * whitespace and comments has no statements.
     *
     * @return list<string>
     * @throws \SqlParser\Lexer\LexicalException When the script contains invalid tokens
     * @throws SyntaxException When a statement does not match the grammar
     */
    public function split(string $sql): array
    {
        $statements = [];
        $start = 0;
        foreach ($this->parser->tokenize($sql) as $token) {
            if ($token->text !== ';') {
                continue;
            }
            $candidate = substr($sql, $start, $token->end() - $start);
            try {
                $this->parser->parse($candidate);
            } catch (SyntaxException) {
                continue;
            }
            $statements[] = $candidate;
            $start = $token->end();
        }
        $tail = substr($sql, $start);
        if (array_filter($this->parser->tokenize($tail), static fn (Token $token): bool => $token->text !== '') !== []) {
            $this->parser->parse($tail);
            $statements[] = $tail;
        } elseif ($statements !== []) {
            $statements[count($statements) - 1] .= $tail;
        }

        return $statements;
    }

    /**
     * Parses every statement of a script into its own tree.
     *
     * @return list<Node>
     * @throws \SqlParser\Lexer\SourceException When any statement is invalid
     */
    public function parseScript(string $sql): array
    {
        return array_map(fn (string $statement): Node => $this->parse($statement), $this->split($sql));
    }

    /**
     * Returns the resolved grammar release.
     */
    public function version(): string
    {
        return $this->parser->version();
    }
}
