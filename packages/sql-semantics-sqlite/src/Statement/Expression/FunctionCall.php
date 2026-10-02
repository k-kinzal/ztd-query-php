<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\Sqlite\Statement\Expression;

use SqlSemantics\Construction\Derivation;
use SqlSemantics\Contract\NameUse;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\Sqlite\Rules\Typing\Functions;
use SqlSemantics\Platform\Sqlite\Statement\Expression\Window\WindowSpec;
use SqlSemantics\Platform\Sqlite\Statement\Query\Ordering\SortTerm;
use SqlSemantics\Platform\Sqlite\Statement\Query\SetQuantifier;
use SqlSemantics\Rendering\Output;
use SqlSemantics\Resolution\Environment;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Fact\ScalarFact;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Scalar;
use SqlSemantics\Statement\Snapshot;
use SqlSemantics\Statement\Type\Invalid;

/**
 * A call of a scalar, aggregate or window function.
 *
 * Rule: SQLITE-FUNCTION-CALL-001. The arguments, the ordering of aggregate
 * arguments, the filter and the window are derived at the position of the
 * call. The result facts are those of SQLITE-FUNCTION-RESULT-001: a built-in
 * function has its documented result; a wrong number of arguments is
 * reported; any other name depends on the undeclared routine. The star form
 * passes no argument.
 * Source: https://sqlite.org/lang_expr.html#functions,
 * https://sqlite.org/lang_aggfunc.html, https://sqlite.org/windowfunctions.html.
 * Status: Implemented.
 *
 * @visibility public
 * @example Reading the parts of an aggregate call
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\Sqlite\Dialect::Sqlite))->analyze('SELECT count(DISTINCT a) FILTER (WHERE b > 0) FROM t');
 *     $call = $query->statement->columns[0]->expression;
 *     [$call->name->value, $call->quantifier, count($call->arguments), $call->filter !== null, $query->field(0)->nullability] // => ['count', \SqlSemantics\Platform\Sqlite\Statement\Query\SetQuantifier::Distinct, 1, true, \SqlSemantics\Statement\Type\Nullability::NotNull]
 * @example Refusing arguments next to the star
 *     new \SqlSemantics\Platform\Sqlite\Statement\Expression\FunctionCall(new \SqlSemantics\Statement\Identifier\Name('count'), [new \SqlSemantics\Platform\Sqlite\Statement\Expression\Literal\NullLiteral()], true) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class FunctionCall implements Scalar
{
    use Snapshot;

    /**
     * @var list<Scalar> The arguments in order
     */
    public readonly array $arguments;

    /**
     * @var list<SortTerm> The ordering of the aggregated arguments
     */
    public readonly array $order;

    /**
     * @param Name $name The function name
     * @param list<Scalar> $arguments The arguments in order
     * @param bool $star Whether the argument list is the star
     * @param SetQuantifier|null $quantifier The written DISTINCT or ALL
     * @param list<SortTerm> $order The ordering written inside the argument list
     * @param Scalar|null $filter The FILTER predicate
     * @param WindowSpec|Name|null $over The window written after OVER: a specification or a window name
     */
    public function __construct(
        public readonly Name $name,
        array $arguments = [],
        public readonly bool $star = false,
        public readonly ?SetQuantifier $quantifier = null,
        array $order = [],
        public readonly ?Scalar $filter = null,
        public readonly WindowSpec|Name|null $over = null,
    ) {
        $this->arguments = Check::listOf($arguments, Scalar::class, 'Function arguments are expressions.');
        $this->order = Check::listOf($order, SortTerm::class, 'An argument ordering is a list of ordering terms.');
        Check::input(!$star || ($arguments === [] && $quantifier === null && $order === []), 'The star form takes no argument, quantifier or ordering.');
    }

    /**
     * Derives every part of the call and the result of the function.
     */
    public function deriveScalar(Derivation $derivation, Environment $environment): ScalarFact
    {
        $arguments = [];
        foreach ($this->arguments as $argument) {
            $arguments[] = $derivation->scalar($argument, $environment);
        }
        foreach ($this->order as $term) {
            $derivation->scalar($term->expression, $environment);
        }
        if ($this->filter !== null) {
            $derivation->scalar($this->filter, $environment);
        }
        foreach ($this->over instanceof WindowSpec ? $this->over->expressions() : [] as $expression) {
            $derivation->scalar($expression, $environment);
        }
        $fact = (new Functions())->result($this->name, $arguments);
        if ($fact->type instanceof Invalid && $fact->type->cause instanceof Diagnostic) {
            $derivation->report($fact->type->cause);
        }

        return $fact;
    }

    /**
     * Writes the call.
     */
    public function render(Output $out): void
    {
        $out->name($this->name, NameUse::Routine)->symbol('(');
        if ($this->star) {
            $out->symbol('*');
        }
        if ($this->quantifier !== null) {
            $out->keyword($this->quantifier->value);
        }
        $out->list($this->arguments);
        if ($this->order !== []) {
            $out->keyword('ORDER', 'BY')->list($this->order);
        }
        $out->symbol(')');
        if ($this->filter !== null) {
            $out->keyword('FILTER')->symbol('(')->keyword('WHERE')->node($this->filter)->symbol(')');
        }
        if ($this->over instanceof Name) {
            $out->keyword('OVER')->name($this->over, NameUse::Label);
        } elseif ($this->over !== null) {
            $out->keyword('OVER')->symbol('(')->node($this->over)->symbol(')');
        }
    }
}
