<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Catalog\Access\Problem;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A role or privilege command that PostgreSQL rejects because it breaks a rule of the command.
 *
 * @visibility SqlSemantics
 */
final class AccessProblem implements Diagnostic
{
    use Snapshot;

    /**
     * @var list<string> The texts the message names, in the order the message names them
     */
    public readonly array $subjects;

    /**
     * @param AccessProblemRule $rule The broken rule
     * @param list<string> $subjects The texts the message names, one for each `%s` of the rule
     */
    public function __construct(public readonly AccessProblemRule $rule, array $subjects = [])
    {
        Check::input(count($subjects) === substr_count($rule->value, '%s'), 'An access problem names one subject for each place of its message.');
        $this->subjects = $subjects;
    }

    /**
     * Describes the broken rule in the words of PostgreSQL.
     */
    public function message(): string
    {
        return sprintf($this->rule->value, ...$this->subjects);
    }
}
