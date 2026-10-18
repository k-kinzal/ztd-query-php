<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine\Declaration;

use SqlSemantics\Platform\MySql\Rules\Routine\ReturnSearch;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramProblem;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ProgramRule;
use SqlSemantics\Platform\MySql\Statement\Routine\Program as P;
use SqlSemantics\Statement\Node;

/**
 * Resolves the label graph of a complete stored-program body.
 *
 * Blocks and loops own lexical labels; only loop labels admit ITERATE. A
 * handler starts a new label scope, while sibling blocks cannot see each
 * other's labels. Every branch is checked, including unreachable statements.
 * Observed through SQL on MySQL 5.7.44 and 8.4.7.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class BodyLabels
{
    /**
     * Finds a jump, duplicate label or closing label with no coherent lexical binding.
     *
     * @param array<string, bool> $labels Enclosing labels, true for loops
     */
    public function problem(Node $body, array $labels = []): ?ProgramProblem
    {
        if ($body instanceof P\Flow\Leave || $body instanceof P\Flow\Iterate) {
            $loop = $labels[strtolower($body->label->value)] ?? null;
            return $loop === null || ($body instanceof P\Flow\Iterate && !$loop)
                ? new ProgramProblem($body instanceof P\Flow\Iterate ? ProgramRule::IterateWithoutLabel : ProgramRule::LeaveWithoutLabel, $body->label->value) : null;
        }
        $labeled = $body instanceof P\Block || $body instanceof P\Flow\Loop || $body instanceof P\Flow\WhileLoop || $body instanceof P\Flow\RepeatLoop;
        if ($labeled && $body->label !== null) {
            $key = strtolower($body->label->value);
            if (isset($labels[$key])) {
                return new ProgramProblem(ProgramRule::RedefinedLabel, $body->label->value);
            }
            $labels[$key] = !$body instanceof P\Block;
        }
        $problem = $this->nested($body, $labels);
        if ($problem !== null) {
            return $problem;
        }
        if ($labeled && $body->endLabel !== null && ($body->label === null || strcasecmp($body->label->value, $body->endLabel->value) !== 0)) {
            return new ProgramProblem(ProgramRule::EndLabelMismatch, $body->endLabel->value);
        }
        return null;
    }

    /**
     * Visits declarations and statements in order, resetting labels at each handler boundary.
     *
     * @param array<string, bool> $labels Labels visible in ordinary child statements
     */
    public function nested(Node $body, array $labels): ?ProgramProblem
    {
        if ($body instanceof P\Block) {
            foreach ($body->declarations as $declaration) {
                if ($declaration instanceof P\HandlerDeclaration) {
                    $problem = $this->problem($declaration->statement);
                    if ($problem !== null) {
                        return $problem;
                    }
                }
            }
        }
        foreach ($body instanceof P\Block ? $body->statements : (new ReturnSearch())->nested($body) as $statement) {
            $problem = $this->problem($statement, $labels);
            if ($problem !== null) {
                return $problem;
            }
        }
        return null;
    }
}
