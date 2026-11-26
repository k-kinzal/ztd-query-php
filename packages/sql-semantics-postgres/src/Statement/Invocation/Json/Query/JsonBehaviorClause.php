<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;

/**
 * `behavior ON EMPTY` and `behavior ON ERROR` of an SQL/JSON function or JSON_TABLE column.
 *
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-QUERYING.
 *
 * @visibility SqlSemantics
 * @example Reading an ON ERROR behavior
 *     $clause = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehaviorClause(null, new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehavior(\SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehaviorKind::Error));
 *     [$clause->onEmpty, $clause->onError->kind->value] // => [null, 'ERROR']
 * @example Rejecting a clause without a behavior
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonBehaviorClause(null, null) // throws \SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction
 */
final class JsonBehaviorClause implements Clause
{
    use Snapshot;

    /**
     * @param JsonBehavior|null $onEmpty The behavior when the path finds nothing
     * @param JsonBehavior|null $onError The behavior when an error occurs
     */
    public function __construct(public readonly ?JsonBehavior $onEmpty, public readonly ?JsonBehavior $onError)
    {
        Check::input($onEmpty !== null || $onError !== null, 'A behavior clause has at least one behavior.');
    }

    /**
     * Derives the default expressions.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->onEmpty?->deriveClause($derivation, $environment);
        $this->onError?->deriveClause($derivation, $environment);
    }

    /**
     * Writes the behaviors with ON EMPTY and ON ERROR.
     */
    public function render(Output $out): void
    {
        if ($this->onEmpty !== null) {
            $out->node($this->onEmpty)->keyword('ON', 'EMPTY');
        }
        if ($this->onError !== null) {
            $out->node($this->onError)->keyword('ON', 'ERROR');
        }
    }
}
