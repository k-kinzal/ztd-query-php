<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A data-modifying, COPY, prepared-statement or cursor command that PostgreSQL rejects because it breaks a rule of the language.
 *
 * @visibility public
 * @example Reading the problem of a column written twice
 *     $insert = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\PostgreSql\Dialect::PostgreSql))->analyze('INSERT INTO t (a, a) VALUES (1, 2)');
 *     $insert->facts->diagnostics[0]->message() // => 'column "a" specified more than once'
 * @example Refusing a message without its subjects
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuse(\SqlSemantics\Platform\PostgreSql\Statement\Manipulation\Problem\ManipulationMisuseRule::RepeatedInsertColumn) // throws \SqlSemantics\Diagnostic\InvalidConstruction
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
