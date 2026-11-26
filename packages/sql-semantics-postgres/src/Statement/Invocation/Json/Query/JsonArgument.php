<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Contract\NameUse;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression;

/**
 * A variable passed to a JSON path with PASSING: `value AS name`.
 *
 * Mirrors PostgreSQL's `JsonArgument` node.
 * Source: https://www.postgresql.org/docs/17/functions-json.html#FUNCTIONS-SQLJSON-QUERYING.
 *
 * @visibility SqlSemantics
 * @example Reading a path variable
 *     $argument = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\Query\JsonArgument(new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\Json\JsonValueExpression(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral()), new \SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name('x'));
 *     $argument->name->value // => 'x'
 */
final class JsonArgument implements Clause
{
    use Snapshot;

    /**
     * @param JsonValueExpression $value The value
     * @param Name $name The variable name the path uses
     */
    public function __construct(public readonly JsonValueExpression $value, public readonly Name $name)
    {
    }

    /**
     * Derives the value.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $this->value->deriveValue($derivation, $environment);
    }

    /**
     * Writes the value and its name.
     */
    public function render(Output $out): void
    {
        $out->node($this->value)->keyword('AS')->name($this->name, NameUse::Label);
    }
}
