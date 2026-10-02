<?php

declare(strict_types=1);

namespace SqlSemantics\Facade;

use SqlParser\Lexer\Token;
use SqlParser\Parser\SqlParser;
use SqlParser\Parser\SyntaxException;

/**
 * Finds statement boundaries by asking the parser which prefixes are complete statements.
 *
 * @visibility namespace
 */
final class Splitter
{
    /**
     * @param SqlParser $parser The parser of the language profile
     */
    public function __construct(private readonly SqlParser $parser)
    {
    }

    /**
     * Splits a script into statement texts, each ending with its own terminator.
     *
     * @return list<string>
     * @throws \SqlParser\Lexer\SourceException When the text after the last boundary is not a statement
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
}
