<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem;

use SqlSemantics\Platform\PostgreSql\Model\Statement\Fact\Diagnostic;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * Arguments named in a way every routine rejects: a positional argument after a named one, or a name used twice.
 *
 * @visibility SqlSemantics
 * @example Describing a repeated parameter name
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\ArgumentProblem(
 *         \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\ArgumentProblemKind::RepeatedName,
 *         new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('a'),
 *     ))->message() // => 'argument name "a" used more than once'
 */
final class ArgumentProblem implements Diagnostic
{
    use Snapshot;

    /**
     * @param ArgumentProblemKind $kind The mistake
     * @param Name $argument The argument concerned: the repeated name, or the name before the positional argument
     */
    public function __construct(public readonly ArgumentProblemKind $kind, public readonly Name $argument)
    {
    }

    /**
     * Describes the problem as the server does.
     */
    public function message(): string
    {
        return str_replace('%s', $this->argument->value, $this->kind->value);
    }
}
