<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Statement\Call\Problem;

use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Statement\Fact\Diagnostic;
use SqlSemantics\Statement\Snapshot;

/**
 * A window function or window specification that uses a part of the syntax the server does not support.
 *
 * Aggregate refusals retain the semantic call that owns the diagnostic, so
 * a consumer can defer it until window planning without recovering input SQL
 * or guessing which call produced it.
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
 */
final class UnsupportedWindowing implements Diagnostic
{
    use Snapshot;

    /**
     * @param WindowingLimit $limit The unsupported part
     * @param Aggregate|GroupConcat|null $expression The aggregate call refused after binding; null for a frame or other window option
     */
    public function __construct(public readonly WindowingLimit $limit, public readonly Aggregate|GroupConcat|null $expression = null)
    {
        Check::input($expression === null || ($limit === WindowingLimit::GroupConcat && $expression instanceof GroupConcat && $expression->over !== null)
            || ($limit === WindowingLimit::DistinctAggregate && $expression instanceof Aggregate && $expression->over !== null && $expression->distinct && $expression->function !== \SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Collect), 'An unsupported aggregate diagnostic identifies a call with the stated limitation.');
    }

    /**
     * Describes the problem in the words of the server.
     */
    public function message(): string
    {
        return "This version of MySQL doesn't yet support '" . $this->limit->value . "'";
    }
}
