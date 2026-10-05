<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Statement\Invocation;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Rendering\Spelling;
use SqlSemantics\Platform\PostgreSql\Rules\Invocation\CallTyping;
use SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName;
use SqlSemantics\Platform\PostgreSql\Statement\OutputNaming;
use SqlSemantics\Platform\PostgreSql\Statement\Query\SortItem;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;

/**
 * A call of a function, aggregate or window function written with the function's name.
 *
 * Mirrors PostgreSQL's `FuncCall` node: the name as written, the arguments,
 * the aggregate ordering (`agg_order`, inside the parentheses or after
 * WITHIN GROUP), FILTER, OVER, `*`, DISTINCT and VARIADIC. `ALL` before the
 * arguments is the default and builds the same node, so it is not kept.
 *
 * Rule: PG-FUNCTION-CALL-001. The arguments, the ordering, the filter and the
 * window are derived at the call's position. The result is that of
 * PG-CALL-RESULT-001 (`CallTyping`): a `pg_catalog` function the call
 * certainly resolves to has its documented result; every other call depends
 * on the undeclared routine. Diagnostics: a positional argument after a named
 * one, a parameter named twice, and clauses the resolved kind of function
 * does not accept. An unqualified call of cube or rollup is written with the
 * name quoted: in GROUP BY gram.y reads `CUBE (` and `ROLLUP (` as a grouping
 * set. Termination: recursion on the expression tree only.
 * Source: https://www.postgresql.org/docs/17/sql-expressions.html#SQL-EXPRESSIONS-FUNCTION-CALLS,
 * https://www.postgresql.org/docs/17/sql-expressions.html#SYNTAX-AGGREGATES,
 * https://www.postgresql.org/docs/17/sql-expressions.html#SYNTAX-WINDOW-FUNCTIONS. Status: Implemented.
 *
 * @visibility public
 * @example Reading the parts of an aggregate call
 *     $call = new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\FunctionCall(
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('count')]),
 *         star: true,
 *     );
 *     [$call->name->last()->value, $call->star, $call->arguments, $call->outputName()->value] // => ['count', true, [], 'count']
 * @example Rejecting arguments next to the star
 *     new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\FunctionCall(
 *         new \SqlSemantics\Platform\PostgreSql\Statement\Name\DottedName([new \SqlSemantics\Statement\Identifier\Name('count')]),
 *         [new \SqlSemantics\Platform\PostgreSql\Statement\Invocation\PositionalArgument(new \SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral())],
 *         true,
 *     ) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class FunctionCall implements Scalar, OutputNaming
{
    use Snapshot;

    /**
     * @var list<Argument> The arguments in order
     */
    public readonly array $arguments;

    /**
     * @var list<SortItem> The aggregate ordering: inside the parentheses, or after WITHIN GROUP
     */
    public readonly array $order;

    /**
     * @param DottedName $name The function name as written
     * @param list<Argument> $arguments The arguments in order
     * @param bool $star Whether the argument list is `*`
     * @param bool $distinct Whether DISTINCT is written before the arguments
     * @param bool $variadic Whether the last argument is passed with VARIADIC
     * @param list<SortItem> $order The aggregate ordering
     * @param bool $withinGroup Whether the ordering is written after WITHIN GROUP
     * @param Scalar|null $filter The FILTER predicate
     * @param WindowSpecification|Name|null $over The window after OVER: a specification or the name of a window of the WINDOW clause
     */
    public function __construct(
        public readonly DottedName $name,
        array $arguments = [],
        public readonly bool $star = false,
        public readonly bool $distinct = false,
        public readonly bool $variadic = false,
        array $order = [],
        public readonly bool $withinGroup = false,
        public readonly ?Scalar $filter = null,
        public readonly WindowSpecification|Name|null $over = null,
    ) {
        $this->arguments = Check::listOf($arguments, Argument::class, 'Call arguments are arguments.');
        $this->order = Check::listOf($order, SortItem::class, 'An aggregate ordering is a list of sort items.');
        Check::input(!$star || ($this->arguments === [] && !$distinct && !$variadic && ($this->order === [] || $withinGroup)), 'The star form takes no argument, DISTINCT, VARIADIC or ordering inside the parentheses.');
        Check::input(!$withinGroup || ($this->order !== [] && !$distinct && !$variadic), 'WITHIN GROUP holds an ordering and goes with neither DISTINCT nor VARIADIC.');
        Check::input($star || $withinGroup || $this->arguments !== [] || (!$distinct && !$variadic && $this->order === []), 'DISTINCT, VARIADIC and an ordering inside the parentheses need an argument.');
    }

    /**
     * Names an unaliased result column after the function.
     */
    public function outputName(): Name
    {
        return $this->name->last();
    }

    /**
     * Derives the arguments, the ordering, the filter and the window, then the result of the call.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $arguments = [];
        foreach ($this->arguments as $argument) {
            $arguments[] = $derivation->scalar($argument->value(), $environment);
        }
        foreach ($this->order as $item) {
            $item->deriveClause($derivation, $environment);
        }
        if ($this->filter !== null) {
            $derivation->scalar($this->filter, $environment);
        }
        if ($this->over instanceof WindowSpecification) {
            $this->over->deriveClause($derivation, $environment);
        }

        return (new CallTyping())->call($derivation, $this, $arguments);
    }

    /**
     * Writes the call in the order of the grammar.
     */
    public function render(Output $out): void
    {
        $parts = $this->name->parts;
        (new Spelling())->dotted($out, $parts, count($parts) === 1 && in_array($parts[0]->value, ['cube', 'rollup'], true) ? NameUse::Identifier : NameUse::Routine);
        $out->glue()->symbol('(');
        if ($this->star) {
            $out->symbol('*');
        }
        if ($this->distinct) {
            $out->keyword('DISTINCT');
        }
        foreach ($this->arguments as $position => $argument) {
            if ($position > 0) {
                $out->symbol(',');
            }
            if ($this->variadic && $position === count($this->arguments) - 1) {
                $out->keyword('VARIADIC');
            }
            $out->node($argument);
        }
        if ($this->order !== [] && !$this->withinGroup) {
            $out->keyword('ORDER', 'BY')->list($this->order);
        }
        $out->symbol(')');
        if ($this->withinGroup) {
            $out->keyword('WITHIN', 'GROUP')->symbol('(')->keyword('ORDER', 'BY')->list($this->order)->symbol(')');
        }
        if ($this->filter !== null) {
            $out->keyword('FILTER')->symbol('(')->keyword('WHERE')->node($this->filter)->symbol(')');
        }
        if ($this->over instanceof Name) {
            $out->keyword('OVER')->name($this->over, NameUse::Column);
        } elseif ($this->over !== null) {
            $out->keyword('OVER')->node($this->over);
        }
    }
}
