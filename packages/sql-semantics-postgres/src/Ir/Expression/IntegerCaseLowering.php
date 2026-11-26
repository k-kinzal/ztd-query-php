<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\PostgreSql\Ir\Expression;

use SqlSemantics\Platform\PostgreSql\Ir\Arithmetic;
use SqlSemantics\Platform\PostgreSql\Ir\ColumnReference;
use SqlSemantics\Platform\PostgreSql\Ir\ExpressionLowering;
use SqlSemantics\Platform\PostgreSql\Ir\From\JoinBinding;
use SqlSemantics\Platform\PostgreSql\Ir\IntegerLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\NullLiteral;
use SqlSemantics\Platform\PostgreSql\Ir\Predicate\PredicateLowering;
use SqlSemantics\Platform\PostgreSql\Ir\SourceBinding;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\Check;
use SqlSemantics\Platform\PostgreSql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Scalar;
use SqlSemantics\Platform\PostgreSql\Model\Statement\Type\Known;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Cast;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Constructor\CaseExpression;
use SqlSemantics\Platform\PostgreSql\Statement\Expression\Grouped;
use SqlSemantics\Platform\PostgreSql\Statement\Literal\NullLiteral as ParsedNull;
use SqlSemantics\Platform\PostgreSql\Statement\Type\Descriptor\Builtin;

/**
 * Lowers the searched form after PostgreSQL type and predicate resolution.
 *
 * @visibility SqlSemantics\Platform\PostgreSql
 */
final class IntegerCaseLowering
{
    /**
     * Selects the int4 result role and preserves the order of the checked arms.
     * @throws InvalidConstruction When the CASE has no checked int4 representation
     */
    public function lower(CaseExpression $expression, SourceBinding|JoinBinding $binding, bool $catalogFirst): IntegerCase
    {
        Check::input($expression->operand === null, 'An int4 CASE requires the searched form.');
        $type = $binding->typeOf($expression);
        Check::input($type instanceof Known && $type->descriptor === Builtin::Int4, 'A searched CASE must resolve to pg_catalog.int4.');
        $predicates = new PredicateLowering();
        $arms = [];
        foreach ($expression->branches as $branch) {
            $condition = $predicates->lowerValue($branch->condition, $binding, $catalogFirst);
            $result = $this->result($branch->result, $binding, $catalogFirst);
            $arms[] = new IntegerCaseArm($condition, $result);
        }
        $otherwise = $expression->default === null ? null : $this->result($expression->default, $binding, $catalogFirst);

        return new IntegerCase($arms, $otherwise);
    }

    /**
     * Resolves a signed result or the explicit int4 cast of a NULL constant.
     * @throws InvalidConstruction When the arm requires another scalar domain
     */
    public function result(Scalar $expression, SourceBinding|JoinBinding $binding, bool $catalogFirst): Arithmetic|ColumnReference|IntegerCase|IntegerLiteral|NullLiteral
    {
        while ($expression instanceof Grouped) {
            $expression = $expression->operand;
        }
        if ($expression instanceof Cast && !$expression->type->setOf) {
            $operand = $expression->operand;
            while ($operand instanceof Grouped) {
                $operand = $operand->operand;
            }
            $type = $binding->typeOf($expression);
            if ($operand instanceof ParsedNull && $type instanceof Known && $type->descriptor === Builtin::Int4) {
                return new NullLiteral();
            }
        }
        $result = (new ExpressionLowering())->lower($expression, $binding, $catalogFirst);
        Check::input($result instanceof Arithmetic || $result instanceof ColumnReference || $result instanceof IntegerCase || $result instanceof IntegerLiteral || $result instanceof NullLiteral, 'An int4 CASE arm requires a signed integer or NULL.');

        return $result;
    }
}
