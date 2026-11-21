<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Problem;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A stored program statement MySQL rejects because it breaks a rule of the language.
 *
 * @visibility public
 * @example Reading the problem of a LEAVE without a label in scope
 *     (new \SqlSemantics\Platform\MySql\Semantics())->analyze('CREATE PROCEDURE p() LEAVE missing') // throws \SqlSemantics\Platform\MySql\Model\Diagnostic\SemanticException: LEAVE with no matching label: missing
 */
final class ProgramProblem implements Diagnostic
{
    use Snapshot;

    /**
     * @param ProgramRule $rule The broken rule
     * @param string|null $subject The name the problem is about; given exactly when the message of the rule names one
     */
    public function __construct(public readonly ProgramRule $rule, public readonly ?string $subject = null)
    {
        Check::input(($subject !== null) === str_contains($rule->value, '%s'), 'A problem names a subject exactly when its rule is about a name.');
    }

    /**
     * Describes the broken rule in the words of the server.
     */
    public function message(): string
    {
        return str_replace('%s', $this->subject ?? '', $this->rule->value);
    }
}
