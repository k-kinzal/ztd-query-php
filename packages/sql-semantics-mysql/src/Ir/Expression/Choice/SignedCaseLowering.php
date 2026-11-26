<?php

declare(strict_types=1);

namespace SqlSemantics\Platform\MySql\Ir\Expression\Choice;

use SqlSemantics\Platform\MySql\Ir\BooleanLiteral;
use SqlSemantics\Platform\MySql\Ir\ColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\IntegralColumnReference;
use SqlSemantics\Platform\MySql\Ir\From\JoinBinding;
use SqlSemantics\Platform\MySql\Ir\NullLiteral;
use SqlSemantics\Platform\MySql\Ir\Predicate\IntegerMembership;
use SqlSemantics\Platform\MySql\Ir\Predicate\Membership\IntegerMembershipLowering;
use SqlSemantics\Platform\MySql\Ir\Predicate\RangeIntegerLiteral;
use SqlSemantics\Platform\MySql\Ir\SourceBinding;
use SqlSemantics\Platform\MySql\Model\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Model\Diagnostic\InvalidConstruction;
use SqlSemantics\Platform\MySql\Model\Statement\Scalar;
use SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseExpression;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList;
use SqlSemantics\Platform\MySql\Statement\Literal\BooleanLiteral as ParsedBoolean;

/**
 * Resolves both native CASE forms from the owned projection scope.
 *
 * @visibility SqlSemantics\Platform\MySql
 */
final class SignedCaseLowering
{
    /**
     * Lowers the subject, ordered decisions and conditional results.
     * @throws InvalidConstruction When an operand has no checked signed role
     */
    public function lower(CaseExpression $expression, SourceBinding|JoinBinding $binding, int $height = 0): SignedCase
    {
        Check::input($height <= 32, 'Expression height or expanded node count exceeds the IR profile.');
        $subject = $expression->operand === null ? null : $this->atom($this->ungroup($expression->operand), $binding);
        $atoms = new IntegerMembershipLowering();
        $arms = [];
        foreach ($expression->branches as $branch) {
            $decision = $this->ungroup($branch->condition);
            $check = $subject === null && $decision instanceof InList
                ? $atoms->lower($decision, $binding) : $this->atom($decision, $binding);
            $result = $this->result($branch->result, $binding, $height + 1);
            $arms[] = $check instanceof IntegerMembership || $subject === null
                ? new SearchedCaseArm($check, $result) : new SimpleCaseArm($check, $result);
        }
        $otherwise = $expression->else === null ? null : $this->result($expression->else, $binding, $height + 1);

        return new SignedCase($arms, $subject, $otherwise);
    }

    /**
     * Resolves one pure signed operand or an explicitly nested CASE result.
     * @throws InvalidConstruction When the result is outside the signed domain
     */
    public function result(Scalar $value, SourceBinding|JoinBinding $binding, int $height): BooleanLiteral|ColumnReference|IntegralColumnReference|NullLiteral|RangeIntegerLiteral|SignedCase
    {
        $value = $this->ungroup($value);
        return $value instanceof CaseExpression ? $this->lower($value, $binding, $height) : $this->atom($value, $binding);
    }

    /**
     * Resolves Boolean literals and pure signed atoms with the shared comparison rule.
     * @throws InvalidConstruction When the operand is outside the signed domain
     */
    public function atom(Scalar $value, SourceBinding|JoinBinding $binding): BooleanLiteral|ColumnReference|IntegralColumnReference|NullLiteral|RangeIntegerLiteral
    {
        return $value instanceof ParsedBoolean ? new BooleanLiteral($value->value)
            : (new IntegerMembershipLowering())->operand($value, $binding);
    }

    /**
     * Removes transient grouping around a structured operand.
     */
    public function ungroup(Scalar $value): Scalar
    {
        while ($value instanceof Grouped) {
            $value = $value->operand;
        }

        return $value;
    }
}
