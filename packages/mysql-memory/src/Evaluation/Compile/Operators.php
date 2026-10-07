<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Operator\Arithmetic as ArithmeticEvaluable;
use MySqlMemory\Evaluation\Operator\Bits;
use MySqlMemory\Evaluation\Operator\Choice;
use MySqlMemory\Evaluation\Operator\Comparator;
use MySqlMemory\Evaluation\Operator\Compare;
use MySqlMemory\Evaluation\Operator\IsTest;
use MySqlMemory\Evaluation\Operator\Logic;
use MySqlMemory\Evaluation\Operator\Membership;
use MySqlMemory\Evaluation\Operator\Minus;
use MySqlMemory\Evaluation\Operator\Negation;
use MySqlMemory\Evaluation\Operator\Pattern;
use MySqlMemory\Evaluation\Operator\Range;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Typing\Collations;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseExpression;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Expression\Logical;
use SqlSemantics\Platform\MySql\Statement\Expression\Not;
use SqlSemantics\Platform\MySql\Statement\Expression\NullTest;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Between;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\InList;
use SqlSemantics\Platform\MySql\Statement\Expression\Predicate\Like;
use SqlSemantics\Platform\MySql\Statement\Expression\Truth;
use SqlSemantics\Platform\MySql\Statement\Expression\TruthTest;
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field;

/**
 * Compiles operators and predicates, resolving the domain of each result and how its operands compare.
 *
 * A truth value is a BIGINT of display length 1.
 *
 * @visibility MySqlMemory\Evaluation
 */
final class Operators
{
    /**
     * @param Compiler $compiler The compiler of the statement
     */
    public function __construct(public readonly Compiler $compiler)
    {
    }

    /**
     * Answers the type of a truth value the engine builds without a node of its own, such as the equalities of a USING join.
     */
    public function truth(bool $nullable): Domain
    {
        return Domain::integer(Field::LongLong, 1)->withNullable($nullable);
    }

    /**
     * Compiles an arithmetic or bit operator.
     */
    public function arithmetic(Arithmetic $node, Scope $scope): Evaluable
    {
        $left = $this->compiler->compile($node->left, $scope);
        $right = $this->compiler->compile($node->right, $scope);
        $text = (new Printer())->expression($node);
        if ($node->operator->bitwise()) {
            return new Bits($node->operator, $left, $right, $this->compiler->domain($node), $text);
        }

        return new ArithmeticEvaluable($node->operator, $left, $right, $this->compiler->domain($node), $text);
    }

    /**
     * Compiles unary `+`, `-`, `~` and `!`.
     */
    public function unary(Unary $node, Scope $scope): Evaluable
    {
        $operand = $this->compiler->compile($node->operand, $scope);

        return match ($node->operator) {
            UnaryOperator::Plus => $operand,
            UnaryOperator::Not => new Negation($operand, $this->compiler->domain($node)),
            UnaryOperator::Invert => new Bits(null, $operand, $operand, $this->compiler->domain($node), (new Printer())->expression($node)),
            UnaryOperator::Minus => new Minus($operand, $this->compiler->domain($node), (new Printer())->expression($node)),
        };
    }


    /**
     * Compiles a comparison.
     */
    public function comparison(Comparison $node, Scope $scope): Evaluable
    {
        if ($this->compiler->rows->elements($node->left) !== null || $this->compiler->rows->elements($node->right) !== null) {
            return $this->compiler->rows->compare($node->operator, $node->left, $node->right, $scope);
        }
        $left = $this->compiler->compile($node->left, $scope);
        $right = $this->compiler->compile($node->right, $scope);
        $comparator = Comparator::of($left->domain(), $right->domain(), $node->operator->value, $this->compiler->settings->connectionCollation);

        return new Compare($node->operator, $left, $right, $comparator, $this->compiler->domain($node));
    }

    /**
     * Compiles AND, OR and XOR.
     */
    public function logical(Logical $node, Scope $scope): Evaluable
    {
        $left = $this->compiler->compile($node->left, $scope);
        $right = $this->compiler->compile($node->right, $scope);

        return new Logic($node->operator, $left, $right, $this->compiler->domain($node));
    }

    /**
     * Compiles NOT.
     */
    public function not(Not $node, Scope $scope): Evaluable
    {
        return new Negation($this->compiler->compile($node->operand, $scope), $this->compiler->domain($node));
    }

    /**
     * Compiles IS [NOT] NULL.
     */
    public function nullTest(NullTest $node, Scope $scope): Evaluable
    {
        return new IsTest($this->compiler->compile($node->operand, $scope), null, $node->negated, $this->compiler->domain($node));
    }

    /**
     * Compiles IS [NOT] TRUE, FALSE and UNKNOWN.
     */
    public function truthTest(TruthTest $node, Scope $scope): Evaluable
    {
        $truth = match ($node->truth) {
            Truth::True => true,
            Truth::False => false,
            Truth::Unknown => null,
        };

        return new IsTest($this->compiler->compile($node->operand, $scope), $truth, $node->negated, $this->compiler->domain($node));
    }

    /**
     * Compiles [NOT] BETWEEN.
     */
    public function between(Between $node, Scope $scope): Evaluable
    {
        $operand = $this->compiler->compile($node->operand, $scope);
        $low = $this->compiler->compile($node->low, $scope);
        $high = $this->compiler->compile($node->high, $scope);
        $connection = $this->compiler->settings->connectionCollation;

        return new Range($operand, $low, $high, Comparator::of($operand->domain(), $low->domain(), 'between', $connection), Comparator::of($operand->domain(), $high->domain(), 'between', $connection), $node->negated, $this->compiler->domain($node));
    }

    /**
     * Compiles [NOT] IN with a list.
     */
    public function inList(InList $node, Scope $scope): Evaluable
    {
        if ($this->compiler->rows->elements($node->operand) !== null) {
            return $this->compiler->rows->in($node->operand, $node->elements, $node->negated, $scope);
        }
        $operand = $this->compiler->compile($node->operand, $scope);
        $elements = [];
        foreach ($node->elements as $element) {
            $compiled = $this->compiler->compile($element, $scope);
            $elements[] = [$compiled, Comparator::of($operand->domain(), $compiled->domain(), 'in', $this->compiler->settings->connectionCollation)];
        }

        return new Membership($operand, $elements, $node->negated, $this->compiler->domain($node));
    }

    /**
     * Compiles [NOT] LIKE.
     */
    public function like(Like $node, Scope $scope): Evaluable
    {
        $operand = $this->compiler->compile($node->operand, $scope);
        $pattern = $this->compiler->compile($node->pattern, $scope);
        $escape = $node->escape === null ? null : $this->compiler->compile($node->escape, $scope);
        [$collation] = Collations::aggregate([$operand->domain(), $pattern->domain()], 'like', $this->compiler->settings->connectionCollation, true);

        return new Pattern($operand, $pattern, $escape, $collation, $node->negated, $this->compiler->domain($node));
    }

    /**
     * Compiles CASE, with or without an operand.
     */
    public function caseOf(CaseExpression $node, Scope $scope): Evaluable
    {
        $operand = $node->operand === null ? null : $this->compiler->compile($node->operand, $scope);
        $branches = [];
        foreach ($node->branches as $branch) {
            $condition = $this->compiler->compile($branch->condition, $scope);
            $comparator = $operand === null ? null : Comparator::of($operand->domain(), $condition->domain(), 'case', $this->compiler->settings->connectionCollation);
            $branches[] = [$condition, $comparator, $this->compiler->compile($branch->result, $scope)];
        }
        $else = $node->else === null ? null : $this->compiler->compile($node->else, $scope);

        return new Choice($operand, $branches, $else, $this->compiler->domain($node));
    }

    /**
     * Compiles CAST.
     */
    public function cast(Cast $node, Scope $scope): Evaluable
    {
        return (new Casts($this->compiler))->cast($this->compiler->compile($node->operand, $scope), $node->target);
    }
}
