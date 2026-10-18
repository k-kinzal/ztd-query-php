<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine\Declaration;

use SqlSemantics\Platform\MySql\Rules\Routine\ReturnSearch;
use SqlSemantics\Platform\MySql\Rules\Typing\Ordering\GeneralWeights;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Node;

/**
 * Resolves diagnostic targets and named conditions against lexical declarations.
 *
 * A routine cannot acquire an undeclared local or condition from database state.
 * The check includes inactive branches and handler instructions, preserves block
 * shadowing, and accepts parameter bindings. Observed through SQL on MySQL
 * 8.1.0, 8.4.7 and 9.1.0.
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
     * @return array{M\Routine\Problem\ProgramProblem, Node}|null The condition and the offending semantic operand
     */
    public function find(Node $body, array $locals = [], array $conditions = []): ?array
    {
        if ($body instanceof M\Routine\Program\Block) {
            foreach ($body->declarations as $declaration) {
                if ($declaration instanceof M\Routine\Program\VariableDeclaration) {
                    array_push($locals, ...$declaration->names);
                } elseif ($declaration instanceof M\Routine\Program\ConditionDeclaration) {
                    $conditions[(new GeneralWeights())->key($declaration->name->value)] = $declaration->value;
                } elseif ($declaration instanceof M\Routine\Program\HandlerDeclaration) {
                    foreach ($declaration->conditions as $condition) {
                        if ($condition instanceof M\Routine\Condition\ConditionName && !isset($conditions[(new GeneralWeights())->key($condition->name->value)])) {
                            return [new M\Routine\Problem\ProgramProblem(M\Routine\Problem\ProgramRule::UndefinedCondition, $condition->name->value), $condition];
                        }
                    }
                }
            }
        }
        $problem = $this->instruction($body, $locals, $conditions);
        if ($problem !== null) {
            return $problem;
        }
        foreach ((new ReturnSearch())->nested($body) as $nested) {
            $problem = $this->find($nested, $locals, $conditions);
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
     * @return array{M\Routine\Problem\ProgramProblem, Node}|null
     */
    public function instruction(Node $body, array $locals, array $conditions): ?array
    {
        $weights = new GeneralWeights();
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
}
