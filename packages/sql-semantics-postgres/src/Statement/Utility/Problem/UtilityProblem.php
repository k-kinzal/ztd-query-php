<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Utility\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A utility command that PostgreSQL rejects because it breaks a rule of the command.
 *
 * @visibility public
 * @example Reading the problem of an unknown EXPLAIN option
 *     $operation = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('EXPLAIN (fast) SELECT 1');
 *     $operation->facts->diagnostics[0]->message() // => 'unrecognized EXPLAIN option "fast"'
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
     * @param array<array-key, mixed> $subjects The texts the message names, one for each `%s` of the rule
     */
    public function __construct(public readonly UtilityProblemKind $kind, array $subjects = [])
    {
        Check::input(array_is_list($subjects) && count($subjects) === substr_count($kind->value, '%s'), 'A utility problem names one subject for each place of its message.');
        $texts = [];
        foreach ($subjects as $subject) {
            Check::input(is_string($subject), 'A subject of a utility problem is a text.');
            $texts[] = $subject;
        }
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
