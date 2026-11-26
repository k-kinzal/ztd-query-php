<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;

/**
 * One behavior of an SQL/JSON function: a fixed result, an error, or `DEFAULT expression`.
 *
 * Mirrors PostgreSQL's `JsonBehavior` node.
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-QUERYING.
 *
 * @visibility SqlSemantics
 * @example Reading a default behavior
 *     $behavior = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehavior(\SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehaviorKind::Default, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral());
 *     $behavior->kind->value // => 'DEFAULT'
 * @example Rejecting an expression on a fixed behavior
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehavior(\SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehaviorKind::Error, new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
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
