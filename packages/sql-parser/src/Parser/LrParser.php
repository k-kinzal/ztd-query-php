<?php

declare(strict_types=1);

namespace SqlParser\Parser;

use SqlParser\Lexer\Token;
use SqlParser\Table\ActionCode;
use SqlParser\Table\ParseTable;

/**
 * Drives a parse table over a token stream and builds the syntax tree.
 *
 * This is the shift-reduce loop every LALR(1) parser runs. A shift pushes
 * the token; a reduce pops the symbols of a rule and pushes a node for its
 * nonterminal, unless the rule stands in for a mid-rule action, which leaves
 * no node. Accepting yields the node of the grammar's start symbol, which
 * takes over the trivia of the end marker the augmented start rule ends with,
 * so the text after the last token of the stream belongs to the tree too.
 *
 * @visibility root
 */
final class LrParser
{
    /**
     * @param ParseTable $table Table to drive
     */
    public function __construct(private readonly ParseTable $table)
    {
    }

    /**
     * Parses a token stream.
     *
     * @param list<Token> $tokens Tokens ending with the end marker
     * @param string $source The SQL text, for error positions
     *
     * @return Node The node of the start symbol
     *
     * @throws SyntaxException When a token is not allowed where it stands
     */
    public function parse(array $tokens, string $source = ''): Node
    {
        try {
            return $this->preferred($tokens, $source);
        } catch (SyntaxException $error) {
            $alternative = $this->table->alternatives === [] ? null : (new AlternativeParser($this->table))->parse($tokens);
            if ($alternative === null) {
                throw $error;
            }
            return $alternative;
        }
    }

    /**
     * Runs the ordinary LALR derivation, preserving its precedence and diagnostics.
     *
     * @param list<Token> $tokens Complete token stream
     * @throws SyntaxException
     */
    public function preferred(array $tokens, string $source): Node
    {
        $table = $this->table;
        $states = [0];
        $nodes = [];
        $index = 0;
        $token = $tokens[0] ?? new Token(0, $table->symbols->name(0), '', strlen($source));
        while (true) {
            $state = $states[count($states) - 1];
            $action = $table->action($state, $token->symbol);
            if ($action === ActionCode::ERROR) {
                throw new SyntaxException($token, $this->expected($state), $source);
            }
            if (ActionCode::isShift($action)) {
                $states[] = $action;
                $nodes[] = $token;
                $token = $tokens[++$index] ?? new Token(0, $table->symbols->name(0), '', $token->end());
                continue;
            }
            $rule = $table->rules[ActionCode::rule($action)];
            $children = [];
            if ($rule->length > 0) {
                $children = array_values(array_filter(array_splice($nodes, -$rule->length), static fn ($child): bool => $child !== null));
                array_splice($states, -$rule->length);
            }
            if ($action === ActionCode::ACCEPT) {
                $end = array_pop($children);
                $trailing = $end instanceof Token ? $end->leading : '';
                $start = $children[0] ?? null;

                return $start instanceof Node
                    ? new Node($start->name, $start->ordinal, $start->children, $trailing)
                    : new Node($table->symbols->name($rule->lhs), 0, $children, $trailing);
            }
            $states[] = $table->action($states[count($states) - 1], $rule->lhs);
            $nodes[] = $rule->hidden ? null : new Node($table->symbols->name($rule->lhs), $rule->ordinal, $children);
        }
    }

    /**
     * Names the terminals a state accepts, for an error message.
     *
     * @param int $state State the parser was in
     *
     * @return list<string> Terminal names
     */
    public function expected(int $state): array
    {
        return array_map(fn (int $terminal): string => $this->table->symbols->name($terminal), $this->table->expectedTerminals($state));
    }
}
