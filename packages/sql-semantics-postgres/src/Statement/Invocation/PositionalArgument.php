<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation;

use SqlSemantics\Platform\PostgreSql\Model\Construction\Derivation;
use SqlSemantics\Platform\PostgreSql\Model\Rendering\Output;
use SqlSemantics\Platform\PostgreSql\Model\Resolution\Environment;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Identifier\Name;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Snapshot;

/**
 * An argument passed by position: `func_arg_expr: a_expr`.
 *
 * Source: https://www.postgresql.org/docs/17/sql-syntax-calling-funcs.html#SQL-SYNTAX-CALLING-FUNCS-POSITIONAL.
 *
 * @visibility SqlSemantics
 * @example Reading a positional argument
 *     $argument = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\PositionalArgument(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral());
 *     [$argument->name(), $argument->value() instanceof \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral] // => [null, true]
 */
final class PositionalArgument implements Argument
{
    use Snapshot;

    /**
     * @param Scalar $value The value passed
     */
    public function __construct(public readonly Scalar $value)
    {
    }

    /**
     * Answers the value passed.
     */
    public function value(): Scalar
    {
        return $this->value;
    }

    /**
     * Answers null: a positional argument names no parameter.
     */
    public function name(): ?Name
    {
        return null;
    }

    /**
     * Derives the value.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $derivation->scalar($this->value, $environment);
    }

    /**
     * Writes the value.
     */
    public function render(Output $out): void
    {
        $out->node($this->value);
    }
}
