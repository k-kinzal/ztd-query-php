<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Rules\Routine\Declaration;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Literal\Parameter;
use SqlSemantics\Platform\MySql\Statement\Routine\Problem\ParameterScopeMismatch;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Statement\Node;

/**
 * Keeps client bindings outside stored routine and trigger instructions.
 *
 * Traversal includes scalar subqueries, local defaults and dead branches, but
 * never interprets strings as SQL. Observed through text and prepared protocols
 * on MySQL 5.6, 5.7, 8.0, 8.4 and 9.1. Events retain their distinct semantics.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class BodyBindings
{
    /**
     * Requires every parameter occurrence to have a client binding scope.
     */
    public function check(Node $body, ProgramKind $kind): void
    {
        $problem = $this->problem($body, $kind);
        Check::input($problem === null, $problem?->message() ?? 'The body has no unscoped client parameters.');
    }

    /**
     * Answers a scope contradiction independently of catalog and invocation state.
     */
    public function problem(Node $body, ProgramKind $kind): ?ParameterScopeMismatch
    {
        if ($kind === ProgramKind::Event) {
            return null;
        }
        $pending = [$body];
        while ($pending !== []) {
            $node = array_pop($pending);
            if ($node instanceof Parameter) {
                return new ParameterScopeMismatch();
            }
            if (is_array($node)) {
                array_push($pending, ...array_values($node));
            } elseif ($node instanceof Node) {
                array_push($pending, ...array_values(get_object_vars($node)));
            }
        }
        return null;
    }
}
