<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rules\Routine\AggregateChecks;
use SqlSemantics\Platform\PostgreSql\Statement\Clause;
use SqlSemantics\Platform\PostgreSql\Statement\Routine\FunctionParameter;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Snapshot;

/**
 * The argument list of an aggregate: `(*)`, `(args)`, `(ORDER BY args)` or `(args ORDER BY args)`.
 *
 * Mirrors the two-part list `aggr_args` builds: the direct arguments and,
 * for an ordered-set aggregate, the aggregated arguments after ORDER BY. `*`
 * is an aggregate without arguments. The arguments are kept as written; the
 * server folds a repeated VARIADIC argument of an ordered-set aggregate into
 * one.
 * Source: https://www.postgresql.org/docs/17/sql-createaggregate.html.
 *
 * @visibility public
 * @example Telling the star form
 *     (new \SqlSemantics\Platform\PostgreSql\Statement\Routine\Signature\AggregateArguments([]))->star() // => true
 */
final class AggregateArguments implements Clause
{
    use Snapshot;

    /**
     * @var list<FunctionParameter> The direct arguments
     */
    public readonly array $direct;

    /**
     * @var list<FunctionParameter>|null The aggregated arguments after ORDER BY; null without ORDER BY
     */
    public readonly ?array $ordered;

    /**
     * @param list<FunctionParameter> $direct The direct arguments; empty with neither ORDER BY nor arguments for `*`
     * @param list<FunctionParameter>|null $ordered The arguments after ORDER BY, at least one; null without ORDER BY
     */
    public function __construct(array $direct, ?array $ordered = null)
    {
        $this->direct = Check::listOf($direct, FunctionParameter::class, 'Aggregate arguments are function parameters.');
        $this->ordered = $ordered === null ? null : Check::listOf($ordered, FunctionParameter::class, 'ORDER BY is followed by at least one aggregate argument.', 1);
        foreach ([...$this->direct, ...($this->ordered ?? [])] as $argument) {
            Check::input($argument->default === null, 'An aggregate argument has no default value.');
        }
    }

    /**
     * Tells whether the list is `(*)`: an aggregate without arguments.
     */
    public function star(): bool
    {
        return $this->direct === [] && $this->ordered === null;
    }

    /**
     * Derives the argument types and reports argument lists the server rejects.
     */
    public function deriveClause(Derivation $derivation, Environment $environment): void
    {
        foreach ([...$this->direct, ...($this->ordered ?? [])] as $argument) {
            $argument->deriveClause($derivation, $environment);
        }
        (new AggregateChecks())->arguments($this, $derivation);
    }

    /**
     * Writes the list in parentheses.
     */
    public function render(Output $out): void
    {
        $out->symbol('(');
        if ($this->star()) {
            $out->symbol('*');
        }
        $out->list($this->direct);
        if ($this->ordered !== null) {
            $out->keyword('ORDER', 'BY')->list($this->ordered);
        }
        $out->symbol(')');
    }
}
