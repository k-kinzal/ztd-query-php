<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem;

use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Snapshot;

/**
 * A call that uses a clause the function called does not accept.
 *
 * @visibility public
 * @example Describing a window function called without OVER
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\CallMisuse(
 *         \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Problem\CallMisuseKind::WindowWithoutOver,
 *         new \SqlSemantics\Statement\Identifier\Name('rank'),
 *     ))->message() // => 'window function rank requires an OVER clause'
 */
final class CallMisuse implements Diagnostic
{
    use Snapshot;

    /**
     * @param CallMisuseKind $kind The clause and the kind of function
     * @param Name $function The name of the function called
     */
    public function __construct(public readonly CallMisuseKind $kind, public readonly Name $function)
    {
    }

    /**
     * Describes the problem as the server does.
     */
    public function message(): string
    {
        return str_replace('%s', $this->function->value, $this->kind->value);
    }
}
