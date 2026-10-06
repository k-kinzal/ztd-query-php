<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * An SQL/JSON expression the server rejects.
 *
 * @visibility public
 * @example Describing an invalid ON ERROR behavior
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\JsonProblem(\SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\JsonProblemKind::InvalidBehavior, ['ON ERROR']))->message() // => 'invalid ON ERROR behavior'
 */
final class JsonProblem implements Diagnostic
{
    use Snapshot;

    /**
     * @var list<string> The subjects the message names, in order
     */
    public readonly array $subjects;

    /**
     * @param JsonProblemKind $kind The mistake
     * @param list<string> $subjects The clause, column or function names the message names, in order
     */
    public function __construct(public readonly JsonProblemKind $kind, array $subjects = [])
    {
        $values = [];
        foreach ($subjects as $subject) {
            $values[] = $subject;
        }
        Check::input(count($values) === substr_count($kind->value, '%s'), 'A JSON problem names exactly the subjects its message has.');
        $this->subjects = $values;
    }

    /**
     * Describes the problem as the server does.
     */
    public function message(): string
    {
        return vsprintf($this->kind->value, $this->subjects);
    }
}
