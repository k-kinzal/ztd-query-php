<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Lowering\Leaf;

use SqlParser\Lexer\Token;
use SqlParser\Parser\Node;
use SqlSemantics\Diagnostic\Check;
use WeakMap;

/**
 * Answers the whitespace and comments written after a region of a command.
 *
 * Rule: SQLITE-TRIVIA-001. SQLite takes the name of an unaliased result
 * column from the text between its first token and the start of the token
 * that follows it (`scanpt` in `parse.y`, `sqlite3ExprListSetSpan()` in
 * `expr.c`), so a comment written after the expression is part of the name.
 * The trivia after a region is the leading trivia of the next token of the
 * command; every command ends with a semicolon, which the parser supplies
 * with the trivia at the end of the input when none is written, so a region
 * inside a command is always followed by a token. Source:
 * https://sqlite.org/c3ref/column_name.html. Status: Implemented.
 *
 * @visibility SqlSemantics\Platform\Sqlite
 */
final class TriviaRule
{
    /**
     * @var WeakMap<Token, string> The trivia after each token of the command being lowered
     */
    private WeakMap $following;

    /**
     * Starts without an indexed command.
     */
    public function __construct()
    {
        $this->following = new WeakMap();
    }

    /**
     * Records the trivia after each token of a command before it is lowered.
     */
    public function index(Node $command): void
    {
        $this->following = new WeakMap();
        $previous = null;
        foreach ($command->tokens() as $token) {
            if ($previous !== null) {
                $this->following[$previous] = $token->leading;
            }
            $previous = $token;
        }
    }

    /**
     * Answers the trivia written between the last token of a region and the next token of its command.
     *
     * @throws \SqlSemantics\Diagnostic\InvariantViolation When the region is empty or its command was not indexed
     */
    public function after(Node $region): string
    {
        $tokens = $region->tokens();
        Check::invariant($tokens !== [], 'A region followed by trivia covers at least one token.');
        $last = $tokens[count($tokens) - 1];
        Check::invariant(isset($this->following[$last]), 'The trivia after a region is read from an indexed command.');

        return $this->following[$last];
    }
}
