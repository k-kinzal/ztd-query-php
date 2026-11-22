<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem;

use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * A data-modifying, COPY, prepared-statement or cursor command that PostgreSQL rejects because it breaks a rule of the language.
 *
 * @visibility SqlSemantics
 * @example Refusing a message without its subjects
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuse(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuseRule::RepeatedInsertColumn) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 */
final class ManipulationMisuse implements Diagnostic
{
    use Snapshot;

    /**
     * @var list<string> The subjects the message names, in order
     */
    public readonly array $subjects;

    /**
     * @param ManipulationMisuseRule $rule The broken rule
     * @param string ...$subjects The subjects the message names, one per `%s`
     */
    public function __construct(public readonly ManipulationMisuseRule $rule, string ...$subjects)
    {
        $this->subjects = array_values($subjects);
        Check::input(substr_count($rule->value, '%s') === count($this->subjects), 'A problem names one subject per placeholder of its message.');
    }

    /**
     * Describes the broken rule in the words of PostgreSQL.
     */
    public function message(): string
    {
        return vsprintf($this->rule->value, $this->subjects);
    }
}
