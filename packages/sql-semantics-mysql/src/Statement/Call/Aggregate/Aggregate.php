<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Aggregate;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Rules\Call\AggregateResults;
use SqlSemantics\Platform\MySql\Rules\Call\Arguments;
use SqlSemantics\Platform\MySql\Rules\Call\Windows;
use SqlSemantics\Platform\MySql\Rules\Query\Having\HavingScope;
use SqlSemantics\Platform\MySql\Statement\Call\SetFunction;
use SqlSemantics\Platform\MySql\Statement\Call\WindowSpecification;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A call of an aggregate function with one argument, COUNT(*), or COUNT(DISTINCT ...) with several arguments.
 *
 * Rule: MYSQL-AGGREGATE-001. The server builds the Item_sum classes. DISTINCT
 * counts each distinct value once; ALL, written before the argument, is the
 * default. With OVER the call is a window function (MySQL 8.0 and later).
 * The result follows MYSQL-AGGREGATE-RESULT-001. Terminates: the arguments
 * and the window are strict parts.
 * Source: https://dev.mysql.com/doc/refman/8.4/en/aggregate-functions.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Typing COUNT(*)
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT COUNT(*)');
 *     [$query->field(0)->type->descriptor->name(), $query->field(0)->nullability] // => ['BIGINT', \SqlSemantics\Statement\Type\Nullability::NotNull]
 * @example Refusing several arguments without DISTINCT
 *     new \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate(\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Sum, [new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral(), new \SqlSemantics\Platform\MySql\Statement\Literal\NullLiteral()]) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class Aggregate implements SetFunction
{
    use Snapshot;

    /**
     * @var list<Scalar> The arguments in order; none for COUNT(*)
     */
    public readonly array $arguments;

    /**
     * @param AggregateFunction $function The function
     * @param list<Scalar> $arguments The arguments in order; none for COUNT(*)
     * @param bool $distinct Whether DISTINCT is written
     * @param bool $all Whether ALL is written before the argument or the star
     * @param Name|WindowSpecification|null $over The window written after OVER
     */
    public function __construct(
        public readonly AggregateFunction $function,
        array $arguments,
        public readonly bool $distinct = false,
        public readonly bool $all = false,
        public readonly Name|WindowSpecification|null $over = null,
    ) {
        $this->arguments = Check::listOf($arguments, Scalar::class, 'Aggregate arguments are expressions.');
        $star = $arguments === [];
        Check::input(!$star || ($function === AggregateFunction::Count && !$distinct), 'Only COUNT takes the star, and not with DISTINCT.');
        Check::input(count($arguments) <= 1 || ($function === AggregateFunction::Count && $distinct && !$all), 'Only COUNT(DISTINCT ...) takes several arguments.');
        Check::input(!$distinct || $function->distinctive(), 'The grammar does not accept DISTINCT for ' . $function->value . '.');
        Check::input(!($distinct && $all && $function === AggregateFunction::Count), 'COUNT(DISTINCT ...) takes no ALL.');
    }

    /**
     * Tells whether the call is COUNT(*).
     */
    public function star(): bool
    {
        return $this->arguments === [];
    }

    /**
     * Tells whether the call aggregates its query block: it has no window.
     */
    public function aggregates(): bool
    {
        return $this->over === null;
    }

    /**
     * Derives the arguments, the window and the result.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $environment = $this->aggregates() ? (new HavingScope())->leave($environment) : $environment;
        $facts = [];
        foreach ($this->arguments as $argument) {
            $facts[] = (new Arguments())->one($argument, $derivation, $environment);
        }
        (new Windows())->derive($this->over, $derivation, $environment);

        return (new AggregateResults())->aggregate($this, $facts, $derivation);
    }

    /**
     * Writes the call and its window.
     */
    public function render(Output $out): void
    {
        $out->keyword($this->function->value)->glue()->symbol('(');
        if ($this->distinct) {
            $out->keyword('DISTINCT');
        }
        if ($this->all) {
            $out->keyword('ALL');
        }
        if ($this->star()) {
            $out->symbol('*');
        }
        $out->list($this->arguments)->symbol(')');
        (new Windows())->render($this->over, $out);
    }
}
