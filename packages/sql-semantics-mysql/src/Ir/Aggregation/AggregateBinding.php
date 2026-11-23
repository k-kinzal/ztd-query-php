<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Aggregation;

use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate as ParsedAggregate;

/**
 * Binds the native builtin aggregate and its position in the grouped input.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class AggregateBinding
{
    /**
     * Resolves the builtin operator and its written argument role.
     */
    public function lower(ParsedAggregate $expression, SourceBinding $binding): Aggregate
    {
        $operator = AggregateOperator::tryFrom(strtolower($expression->function->value));
        Check::input(
            $operator !== null && $expression->over === null && count($expression->arguments) <= 1,
            'The grouped aggregate requires one supported builtin without a window.'
        );
        $argument = $expression->arguments[0] ?? null;

        return new Aggregate($operator, $argument === null ? null : $binding->field($argument), $expression->distinct);
    }
}
