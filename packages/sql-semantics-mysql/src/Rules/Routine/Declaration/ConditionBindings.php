<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine\Declaration;

use SqlSemantics\Platform\MySql\Rules\Routine\ReturnSearch;
use SqlSemantics\Platform\MySql\Rules\Typing\Ordering\GeneralWeights;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;

/**
 * Checks declaration order, uniqueness and lexical instruction bindings.
 *
 * A routine cannot acquire an undeclared local, cursor or condition from database state.
 * The check includes inactive branches and handler instructions, preserves block
 * shadowing, and accepts parameter bindings. Observed through SQL on MySQL
 * 5.6.51, 5.7.44, 8.0.44, 8.1.0, 8.4.7 and 9.1.0.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/get-diagnostics.html,
 * https://dev.mysql.com/doc/refman/8.4/en/signal.html.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class ConditionBindings
{
    /**
     * Finds the first unbound target or invalid named condition in declaration order.
     *
     * @param list<Name> $locals Parameter and enclosing block variables
     * @param array<string, M\Routine\Condition\ErrorCode|M\Routine\Condition\SqlState> $conditions Folded condition names
     * @param list<Name> $cursors Cursors visible in enclosing blocks
     * @return array{M\Routine\Problem\ProgramProblem, Node}|null The condition and the offending semantic operand
     */
    public function find(Node $body, array $locals = [], array $conditions = [], array $cursors = []): ?array
    {
        if ($body instanceof M\Routine\Program\Block) {
            $names = [];
            $stage = 0;
            $handled = [];
            foreach ($body->declarations as $declaration) {
                $problem = (new DeclarationNames())->add($declaration, $names, $stage);
                if ($problem !== null) {
                    return [$problem, $declaration];
                }
                if ($declaration instanceof M\Routine\Program\VariableDeclaration) {
                    array_push($locals, ...$declaration->names);
                } elseif ($declaration instanceof M\Routine\Program\ConditionDeclaration) {
                    $conditions[(new GeneralWeights())->key($declaration->name->value)] = $declaration->value;
                } elseif ($declaration instanceof M\Routine\Program\Cursor\CursorDeclaration) {
                    $cursors[] = $declaration->name;
                } elseif ($declaration instanceof M\Routine\Program\HandlerDeclaration) {
                    $problem = $this->handler($declaration, $conditions, $handled);
                    if ($problem !== null) {
                        return $problem;
                    }
                    $problem = $this->find($declaration->statement, $locals, $conditions, $cursors);
                    if ($problem !== null) {
                        return $problem;
                    }
                }
            }
        }
        $problem = $this->instruction($body, $locals, $conditions, $cursors);
        if ($problem !== null) {
            return $problem;
        }
        foreach ($body instanceof M\Routine\Program\Block ? $body->statements : (new ReturnSearch())->nested($body) as $nested) {
            $problem = $this->find($nested, $locals, $conditions, $cursors);
            if ($problem !== null) {
                return $problem;
            }
        }
        return null;
    }

    /**
     * Checks one instruction without executing a condition number or SIGNAL value.
     *
     * @param list<Name> $locals Variables visible in this instruction
     * @param array<string, M\Routine\Condition\ErrorCode|M\Routine\Condition\SqlState> $conditions Folded condition names
     * @param list<Name> $cursors Cursors visible in this instruction
     * @return array{M\Routine\Problem\ProgramProblem, Node}|null
     */
    public function instruction(Node $body, array $locals, array $conditions, array $cursors = []): ?array
    {
        $weights = new GeneralWeights();
        if ($body instanceof M\Routine\Program\Cursor\OpenCursor || $body instanceof M\Routine\Program\Cursor\CloseCursor || $body instanceof M\Routine\Program\Cursor\FetchCursor) {
            $keys = array_map(static fn (Name $name): string => $weights->key($name->value), $cursors);
            if (!in_array($weights->key($body->cursor->value), $keys, true)) {
                return [new M\Routine\Problem\ProgramProblem(M\Routine\Problem\ProgramRule::UndefinedCursor, $body->cursor->value), $body];
            }
            $keys = array_map(static fn (Name $name): string => $weights->key($name->value), $locals);
            foreach ($body instanceof M\Routine\Program\Cursor\FetchCursor ? $body->targets : [] as $target) {
                if (!in_array($weights->key($target->value), $keys, true)) {
                    return [new M\Routine\Problem\ProgramProblem(M\Routine\Problem\ProgramRule::UndeclaredVariable, $target->value), $body];
                }
            }
        }
        if ($body instanceof M\Routine\Condition\Diagnostics\GetDiagnostics) {
            $keys = array_map(static fn (Name $name): string => $weights->key($name->value), $locals);
            foreach ($body->information->items as $item) {
                if ($item->target instanceof Name && !in_array($weights->key($item->target->value), $keys, true)) {
                    return [new M\Routine\Problem\ProgramProblem(M\Routine\Problem\ProgramRule::UndeclaredVariable, $item->target->value), $item];
                }
            }
        }
        if (($body instanceof M\Routine\Condition\Signal || $body instanceof M\Routine\Condition\Resignal) && $body->condition instanceof M\Routine\Condition\ConditionName) {
            $meaning = $conditions[$weights->key($body->condition->name->value)] ?? null;
            if ($meaning === null) {
                return [new M\Routine\Problem\ProgramProblem(M\Routine\Problem\ProgramRule::UndefinedCondition, $body->condition->name->value), $body->condition];
            }
            if ($meaning instanceof M\Routine\Condition\ErrorCode) {
                return [new M\Routine\Problem\ProgramProblem(M\Routine\Problem\ProgramRule::SignalConditionKind), $body->condition];
            }
        }
        return null;
    }

    /**
     * Resolves and checks each handled condition before interpreting its instruction.
     *
     * @param array<string, M\Routine\Condition\ErrorCode|M\Routine\Condition\SqlState> $conditions Folded visible condition names
     * @param list<M\Routine\Condition\Condition> $handled The resolved conditions of earlier handlers in this block
     * @return array{M\Routine\Problem\ProgramProblem, Node}|null
     */
    public function handler(M\Routine\Program\HandlerDeclaration $handler, array $conditions, array &$handled): ?array
    {
        foreach ($handler->conditions as $condition) {
            $meaning = $condition instanceof M\Routine\Condition\ConditionName ? ($conditions[(new GeneralWeights())->key($condition->name->value)] ?? null) : $condition;
            if ($meaning === null) {
                return [new M\Routine\Problem\ProgramProblem(M\Routine\Problem\ProgramRule::UndefinedCondition, $condition->name->value), $condition];
            }
            foreach ($handled as $earlier) {
                if ((new \SqlSemantics\Platform\MySql\Rules\Routine\ConditionFacts())->same($earlier, $meaning)) {
                    return [new M\Routine\Problem\ProgramProblem(M\Routine\Problem\ProgramRule::DuplicateHandler), $condition];
                }
            }
            $handled[] = $meaning;
        }
        return null;
    }
}
