<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A utility command that PostgreSQL rejects because it breaks a rule of the command.
 *
 * @visibility SqlSemantics
 */
final class UtilityProblem implements Diagnostic
{
    use Snapshot;

    /**
     * @var list<string> The texts the message names, in the order the message names them
     */
    public readonly array $subjects;

    /**
     * @param UtilityProblemKind $kind The broken rule
     * @param array<array-key, string> $subjects The texts the message names, one for each `%s` of the rule
     */
    public function __construct(public readonly UtilityProblemKind $kind, array $subjects = [])
    {
        Check::input(array_is_list($subjects) && count($subjects) === substr_count($kind->value, '%s'), 'A utility problem names one subject for each place of its message.');
        $texts = array_values(array_filter($subjects, is_string(...)));
        Check::input(count($texts) === count($subjects), 'A subject of a utility problem is a text.');
        $this->subjects = $texts;
    }

    /**
     * Describes the broken rule in the words of PostgreSQL.
     */
    public function message(): string
    {
        return sprintf($this->kind->value, ...$this->subjects);
    }
}
