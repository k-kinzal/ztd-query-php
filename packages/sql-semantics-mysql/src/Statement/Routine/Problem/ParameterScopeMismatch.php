<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A client parameter occurrence inside a stored routine or trigger body.
 *
 * Such bodies have invocation variables, but no client binding scope. Text
 * inside a literal or an external-language body is not a parameter occurrence.
 * Events have a separate protocol contract and are not covered by this refusal.
 *
 * @visibility public
 * @example Reading the binding-scope constraint
 *     (new \SqlSemantics\Platform\MySql\Statement\Routine\Problem\ParameterScopeMismatch())->message() // => 'A stored routine or trigger body has no client parameter binding scope.'
 */
final class ParameterScopeMismatch implements Diagnostic
{
    use Snapshot;

    /**
     * Describes the missing binding scope without retaining input text.
     */
    public function message(): string
    {
        return 'A stored routine or trigger body has no client parameter binding scope.';
    }
}
