<?php

declare(strict_types=1);

namespace SqlSemantics\Core\Ast;

use SqlParser\Parser\Node;
use SqlParser\Parser\SqlParser;
use SqlSemantics\Core\Dialect;

/**
 * Selects and reuses the syntax parser for the semantic phase's language context.
 *
 * @visibility SqlSemantics
 */
final class DialectParser
{
    private readonly SqlParser $parser;

    /**
     * @param string|null $version A release tag shipped by sql-parser
     */
    public function __construct(Dialect $dialect, ?string $version = null)
    {
        $this->parser = $dialect->platform()->parser($version);
    }

    /**
     * Parses SQL without discarding source trivia or locations.
     *
     * @throws \SqlParser\Lexer\LexicalException When SQL contains invalid tokens
     * @throws \SqlParser\Parser\SyntaxException When SQL does not match the grammar
     */
    public function parse(string $sql): Node
    {
        return $this->parser->parse($sql);
    }

    /**
     * Reads a script using lexer boundaries and complete grammar acceptance.
     * Compound statements keep their internal semicolons because incomplete
     * prefixes cannot be parsed as complete commands.
     * @return list<Node>
     * @throws \SqlParser\Lexer\SourceException When any command is invalid
     */
    public function parseScript(string $sql): array
    {
        try {
            return [$this->parse($sql)];
        } catch (\SqlParser\Parser\SyntaxException $original) {
            $trees = [];
            $start = 0;
            foreach ($this->parser->tokenize($sql) as $token) {
                if ($token->text !== ';') {
                    continue;
                }
                try {
                    $tree = $this->parse(substr($sql, $start, $token->end() - $start));
                } catch (\SqlParser\Parser\SyntaxException) {
                    continue;
                }
                $trees[] = $tree;
                $start = $token->end();
            }
            if ($start === 0) {
                throw $original;
            }
            $tail = substr($sql, $start);
            $tokens = $this->parser->tokenize($tail);
            if (array_filter($tokens, static fn ($token): bool => $token->text !== '') !== []) {
                $trees[] = $this->parse($tail);
            }
            return $trees;
        }
    }

    /**
     * Returns the resolved grammar release so binding can reuse the declaration language.
     */
    public function version(): string
    {
        return $this->parser->version();
    }
}
