<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowSpec;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredWindowFunction;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A window function or window specification that uses a part of the syntax the server does not support.
 *
 * Window refusals retain the semantic value that owns the diagnostic, so
 * a consumer can defer it until window planning without recovering input SQL
 * or guessing which call or frame produced it.
 *
 * Source: https://dev.mysql.com/doc/refman/8.4/en/window-function-restrictions.html.
 *
 * @visibility public
 * @example Reading the problem of an unsupported frame unit
 *     $query = (new \SqlSemantics\Facade\Semantics(\SqlSemantics\Platform\MySql\Dialect::MySql))->analyze('SELECT SUM(1) OVER (GROUPS UNBOUNDED PRECEDING)');
 *     $query->facts->diagnostics[0]->message() // => "This version of MySQL doesn't yet support 'GROUPS'"
 * @example Refusing an owner that does not use the unsupported feature
 *     $call = new \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat([new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral('1')]);
 *     new \SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing(\SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowingLimit::GroupConcat, $call) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 * @example Refusing a function owner without the stated option
 *     $call = new \SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction(\SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunctionKind::Rank, [], new \SqlSemantics\Statement\Identifier\Name('w'));
 *     new \SqlSemantics\Platform\MySql\Statement\Call\Problem\UnsupportedWindowing(\SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowingLimit::IgnoreNulls, $call) // throws \SqlSemantics\Diagnostic\InvalidConstruction
 */
final class UnsupportedWindowing implements Diagnostic
{
    use Snapshot;

    /**
     * @param WindowingLimit $limit The unsupported part
     * @param Aggregate|GroupConcat|WindowFunction|DeferredWindowFunction|WindowSpec|null $expression The call or specification refused after binding
     */
    public function __construct(public readonly WindowingLimit $limit, public readonly Aggregate|GroupConcat|WindowFunction|DeferredWindowFunction|WindowSpec|null $expression = null)
    {
        Check::input($expression === null || match ($limit) {
            WindowingLimit::GroupConcat => $expression instanceof GroupConcat && $expression->over !== null,
            WindowingLimit::DistinctAggregate => $expression instanceof Aggregate && $expression->over !== null && $expression->distinct && $expression->function !== \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Collect,
            WindowingLimit::IgnoreNulls => ($expression instanceof WindowFunction || $expression instanceof DeferredWindowFunction) && $expression->nulls === \SqlSemantics\Platform\MySql\Statement\Call\Window\NullTreatment::Ignore,
            WindowingLimit::FromLast => ($expression instanceof WindowFunction || $expression instanceof DeferredWindowFunction) && $expression->edge === \SqlSemantics\Platform\MySql\Statement\Call\Window\CountingEdge::Last,
            WindowingLimit::GroupsUnit => $expression instanceof WindowSpec && $expression->frame?->unit === \SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit::Groups,
            WindowingLimit::Exclusion => $expression instanceof WindowSpec && $expression->frame?->exclusion !== null,
        }, 'An unsupported window diagnostic identifies a semantic owner with the stated limitation.');
    }

    /**
     * Describes the problem in the words of the server.
     */
    public function message(): string
    {
        return "This version of MySQL doesn't yet support '" . $this->limit->value . "'";
    }
}
