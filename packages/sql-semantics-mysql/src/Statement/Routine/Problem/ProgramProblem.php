<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Routine\Problem;

use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\MySql\Model\Statement\Snapshot;

/**
 * A stored program statement MySQL rejects because it breaks a rule of the language.
 *
 * @visibility SqlSemantics
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
