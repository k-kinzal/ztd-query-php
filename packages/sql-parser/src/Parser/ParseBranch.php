<?php

declare(strict_types=1);

namespace SqlParser\Parser;

use SqlParser\Lexer\Token;
use SqlParser\Table\ActionCode;
use SqlParser\Table\ParseTable;

/**
 * One shift/reduce stack used when an unranked conflict needs another derivation.
 *
 * @visibility root
 */
final class ParseBranch
{
    /**
     * @param list<int> $states State stack
     * @param list<Node|Token|null> $nodes Syntax stack, including hidden reductions
     * @param int $index Current token position
     * @param int|null $forced One alternative action to take before consulting the table
     */
    public function __construct(public array $states = [0], public array $nodes = [], public int $index = 0, public ?int $forced = null)
    {
    }

    /**
     * Advances one action and returns the root only on acceptance.
     */
    public function advance(ParseTable $table, int $action, Token $token): ?Node
    {
        if (ActionCode::isShift($action)) {
            $this->states[] = $action;
            $this->nodes[] = $token;
            ++$this->index;
            return null;
        }
        $rule = $table->rules[ActionCode::rule($action)];
        $children = [];
        if ($rule->length > 0) {
            $children = array_values(array_filter(array_splice($this->nodes, -$rule->length), static fn ($child): bool => $child !== null));
            array_splice($this->states, -$rule->length);
        }
        if ($action === ActionCode::ACCEPT) {
            $end = array_pop($children);
            $trailing = $end instanceof Token ? $end->leading : '';
            $start = $children[0] ?? null;
            return $start instanceof Node ? new Node($start->name, $start->ordinal, $start->children, $trailing) : new Node($table->symbols->name($rule->lhs), 0, $children, $trailing);
        }
        $this->states[] = $table->action($this->states[count($this->states) - 1], $rule->lhs);
        $this->nodes[] = $rule->hidden ? null : new Node($table->symbols->name($rule->lhs), $rule->ordinal, $children);
        return null;
    }
}
