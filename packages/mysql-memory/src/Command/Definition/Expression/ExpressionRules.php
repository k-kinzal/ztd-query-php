<?php

declare(strict_types=1);

namespace MySqlMemory\Command\Definition\Expression;

use MySqlMemory\Error\Family\ConstraintError;
use MySqlMemory\Error\Family\QueryError;
use MySqlMemory\Error\SqlError;
use MySqlMemory\Evaluation\Compile\Walker;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\Aggregate;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\GroupConcat;
use SqlSemantics\Platform\MySql\Statement\Call\Aggregate\JsonObjectAggregate;
use SqlSemantics\Platform\MySql\Statement\Call\ClockCall;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Call\KeywordCall;
use SqlSemantics\Platform\MySql\Statement\Call\Window\WindowFunction;
use SqlSemantics\Platform\MySql\Statement\Expression\Access\InsertedColumn;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\UserVariable;
use SqlSemantics\Statement\Node;
use SqlSemantics\Statement\Query;
use SqlSemantics\Statement\Scalar;

/**
 * Refuses what an expression of a table definition may not hold, as the server refuses it.
 *
 * A generated column, an expression default and a CHECK constraint refuse a subquery, a
 * variable and the functions their role refuses, the first one in written order; then an
 * aggregate (ER_INVALID_GROUP_FUNC_USE) or a window function. A CHECK constraint must first be a
 * condition (verified on a live 8.4 server).
 * Source: https://dev.mysql.com/doc/refman/8.4/en/create-table-generated-columns.html,
 * https://dev.mysql.com/doc/refman/8.4/en/data-type-defaults.html,
 * https://dev.mysql.com/doc/refman/8.4/en/create-table-check-constraints.html.
 *
 * @visibility MySqlMemory
 */
final class ExpressionRules
{
    /**
     * Refuses a CHECK constraint that is not a condition.
     *
     * @throws SqlError When the condition is not one
     */
    public function condition(Scalar $condition, string $name): void
    {
        if (!(new Conditions())->boolean($condition)) {
            throw ConstraintError::CheckNotBoolean->error($name);
        }
    }

    /**
     * Refuses the first subquery, variable or refused function of an expression.
     *
     * @param string $owner The column or constraint the expression belongs to, which the error names
     *
     * @throws SqlError When the expression holds one
     */
    public function forbidden(Scalar $expression, ExpressionRole $role, string $owner): void
    {
        $refused = $role->refused();
        foreach ((new Walker())->find($expression, Node::class) as $node) {
            if ($node instanceof Query) {
                throw $role->subquery($owner);
            }
            if ($node instanceof UserVariable || $node instanceof SystemVariable) {
                throw $role->variable($owner);
            }
            $name = $this->called($node);
            if ($name !== null && isset($refused[$name])) {
                throw $role->function($owner, $refused[$name]);
            }
            if ($node instanceof FunctionCall && $node->schema === null && strtolower($node->name->value) === 'name_const') {
                throw $role->subquery($owner);
            }
        }
    }

    /**
     * Answers the lowercase name a node calls a function by, or null for a node that calls none.
     */
    public function called(Node $node): ?string
    {
        return match (true) {
            $node instanceof FunctionCall => $node->schema === null ? strtolower($node->name->value) : null,
            $node instanceof KeywordCall => strtolower($node->function->value),
            $node instanceof ClockCall => strtolower($node->clock->value),
            $node instanceof InsertedColumn => 'values',
            default => null,
        };
    }

    /**
     * Refuses an aggregate (ER_INVALID_GROUP_FUNC_USE) or a window function, the first in written order.
     *
     * @throws SqlError When the expression holds one
     */
    public function grouped(Scalar $expression): void
    {
        foreach ((new Walker())->find($expression, Node::class) as $node) {
            if ($node instanceof WindowFunction) {
                throw ConstraintError::WindowFunctionContext->error(strtolower($node->kind->value));
            }
            if (($node instanceof Aggregate || $node instanceof GroupConcat || $node instanceof JsonObjectAggregate) && $node->over !== null) {
                throw ConstraintError::WindowFunctionContext->error($node instanceof Aggregate ? strtolower($node->function->value) : ($node instanceof GroupConcat ? 'group_concat' : 'json_objectagg'));
            }
            if ($node instanceof Aggregate || $node instanceof GroupConcat || $node instanceof JsonObjectAggregate) {
                throw QueryError::InvalidGroupFunctionUse->error();
            }
        }
    }
}
