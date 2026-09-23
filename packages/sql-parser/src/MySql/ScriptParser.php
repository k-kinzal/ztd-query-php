<?php

declare(strict_types=1);

namespace SqlParser\MySql;

use SqlParser\Lexer\Token;
use SqlParser\Parser\LrParser;
use SqlParser\Parser\Node;
use SqlParser\Parser\SyntaxException;

/**
 * Finds script boundaries only when the server grammar accepts a complete statement.
 * @visibility SqlParser
 */
final class ScriptParser
{
    /**
     * Uses the same grammar driver as single-statement parsing.
     */
    public function __construct(private readonly LrParser $parser)
    {
    }

    /**
     * @param list<Token> $tokens The complete script, including its end markers
     * @return list<Node> Statement trees with original absolute token positions
     * @throws SyntaxException
     */
    public function parse(array $tokens, string $source): array
    {
        $markers = array_values(array_filter($tokens, static fn (Token $token): bool => in_array($token->name, ['END_OF_INPUT', '$end'], true)));
        $pending = [];
        $statements = [];
        foreach ($tokens as $token) {
            if (in_array($token->name, ['END_OF_INPUT', '$end'], true)) {
                continue;
            }
            $pending[] = $token;
            if ($token->name !== ';') {
                continue;
            }
            $ending = array_map(static fn (Token $marker): Token => new Token($marker->symbol, $marker->name, '', $token->end()), $markers);
            $statement = $this->candidate([...$pending, ...$ending], $source);
            if ($statement !== null) {
                $statements[] = $statement;
                $pending = [];
            }
        }
        if ($pending !== [] || $statements === []) {
            $statements[] = $this->parser->parse([...$pending, ...$markers], $source);
        } else {
            $last = count($statements) - 1;
            $tree = $statements[$last];
            $tail = implode('', array_map(static fn (Token $marker): string => $marker->leading, $markers));
            $statements[$last] = new Node($tree->name, $tree->ordinal, $tree->children, $tree->trailing . $tail);
        }
        return $statements;
    }

    /**
     * @param list<Token> $tokens One candidate through a semicolon and synthetic end markers
     * @return Node|null Null when the grammar still requires the rest of a compound statement
     * @throws SyntaxException
     */
    public function candidate(array $tokens, string $source): ?Node
    {
        try {
            return $this->parser->parse($tokens, $source);
        } catch (SyntaxException $error) {
            if (!in_array($error->token->name, ['END_OF_INPUT', '$end'], true)) {
                throw $error;
            }
            return null;
        }
    }
}
