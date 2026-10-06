<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * One behavior of an SQL/JSON function: a fixed result, an error, or `DEFAULT expression`.
 *
 * Mirrors PostgreSQL's `JsonBehavior` node.
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-QUERYING.
 *
 * @visibility public
 * @example Reading a default behavior
 *     $behavior = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehavior(\SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehaviorKind::Default, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral());
 *     $behavior->kind->value // => 'DEFAULT'
 * @example Rejecting an expression on a fixed behavior
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehavior(\SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehaviorKind::Error, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class JsonBehavior implements Clause
{
    use Snapshot;

    /**
     * @param JsonBehaviorKind $kind The behavior
     * @param Scalar|null $default The expression of DEFAULT; given exactly for that kind
     */
    public function __construct(public readonly JsonBehaviorKind $kind, public readonly ?Scalar $default = null)
    {
        Check::input(($kind === JsonBehaviorKind::Default) === ($default !== null), 'A JSON behavior has an expression exactly when it is DEFAULT.');
    }

    /**
     * Derives the default expression.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        if ($this->default !== null) {
            $derivation->scalar($this->default, $environment);
        }
    }

    /**
     * Writes the behavior.
     */
    public function render(Output $out): void
    {
        $out->keyword(...explode(' ', $this->kind->value))->node($this->default);
    }
}
