<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * An argument passed by parameter name: `name => value` or `name := value`.
 *
 * Mirrors PostgreSQL's `NamedArgExpr` node; which parameter the name
 * designates is decided by the routine's declaration.
 * Source: https://www.postgresql.org/docs/17/sql-syntax-calling-funcs.html#SQL-SYNTAX-CALLING-FUNCS-NAMED.
 *
 * @visibility public
 * @example Reading a named argument
 *     $argument = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\NamedArgument(
 *         new \SqlSemantics\Statement\Identifier\Name('days'),
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Literal\Constant(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\IntegerConstant('2')),
 *     );
 *     [$argument->name()->value, $argument->spelling->value] // => ['days', '=>']
 */
final class NamedArgument implements Argument
{
    use Snapshot;

    /**
     * @param Name $parameter The parameter name
     * @param Scalar $value The value passed
     * @param ArgumentSpelling $spelling How the name joins the value
     */
    public function __construct(public readonly Name $parameter, public readonly Scalar $value, public readonly ArgumentSpelling $spelling = ArgumentSpelling::Arrow)
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
     * Answers the parameter name.
     */
    public function name(): Name
    {
        return $this->parameter;
    }

    /**
     * Derives the value.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        $derivation->scalar($this->value, $environment);
    }

    /**
     * Writes the name, the joining symbol and the value.
     */
    public function render(Output $out): void
    {
        $out->name($this->parameter, NameUse::Routine)->symbol($this->spelling->value)->node($this->value);
    }
}
