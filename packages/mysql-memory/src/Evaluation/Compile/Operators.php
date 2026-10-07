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
use MySqlMemory\Result\FieldType;
use MySqlMemory\Typing\Aggregation;
use MySqlMemory\Typing\Collation;
use MySqlMemory\Typing\Collations;
use MySqlMemory\Typing\Domain;
use MySqlMemory\Typing\Kind;
use MySqlMemory\Typing\Numeric;
use SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseExpression;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
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
     * Answers the domain of a truth value that is NULL when an operand can be.
     */
    public function truth(bool $nullable): Domain
    {
        return new Domain(Kind::Integer, FieldType::LongLong, 1, 0, false, Collation::Binary, $nullable);
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
            return new Bits($node->operator, $left, $right, Domain::integer(FieldType::LongLong, 21, true)->withNullable($left->domain()->nullable || $right->domain()->nullable), $text);
        }

        return new ArithmeticEvaluable($node->operator, $left, $right, Numeric::binary($node->operator, $left->domain(), $right->domain(), $this->compiler->settings->divPrecisionIncrement), $text);
    }

    /**
     * Compiles unary `+`, `-`, `~` and `!`.
     */
    public function unary(Unary $node, Scope $scope): Evaluable
    {
        $operand = $this->compiler->compile($node->operand, $scope);

        return match ($node->operator) {
            UnaryOperator::Plus => $operand,
            UnaryOperator::Not => new Negation($operand, $this->truth($operand->domain()->nullable)),
            UnaryOperator::Invert => new Bits(null, $operand, $operand, Domain::integer(FieldType::LongLong, 21, true)->withNullable($operand->domain()->nullable), (new Printer())->expression($node)),
            UnaryOperator::Minus => new Minus($operand, $this->negated($operand->domain()), (new Printer())->expression($node)),
        };
    }

    /**
     * Answers the domain of a negated operand.
     */
    public function negated(Domain $domain): Domain
    {
        return match (Numeric::operand($domain)) {
            Kind::Integer => Domain::integer(FieldType::LongLong, $domain->length + ($domain->unsigned ? 1 : 0), false)->withNullable($domain->nullable),
            Kind::Decimal => $domain->kind === Kind::Decimal ? $domain : Domain::decimal(Numeric::digits($domain)[0], Numeric::digits($domain)[1])->withNullable($domain->nullable),
            default => new Domain(Kind::Double, FieldType::Double, 23, $domain->kind === Kind::Double ? $domain->decimals : Domain::NOT_FIXED, false, Collation::Binary, $domain->nullable),
        };
    }

    /**
     * Compiles a comparison.
     */
    public function comparison(Comparison $node, Scope $scope): Evaluable
    {
        $left = $this->compiler->compile($node->left, $scope);
        $right = $this->compiler->compile($node->right, $scope);
        $comparator = Comparator::of($left->domain(), $right->domain(), $node->operator->value, $this->compiler->settings->connectionCollation);

        return new Compare($node->operator, $left, $right, $comparator, $this->truth($node->operator !== ComparisonOperator::NullSafeEqual && ($left->domain()->nullable || $right->domain()->nullable)));
    }

    /**
     * Compiles AND, OR and XOR.
     */
    public function logical(Logical $node, Scope $scope): Evaluable
    {
        $left = $this->compiler->compile($node->left, $scope);
        $right = $this->compiler->compile($node->right, $scope);

        return new Logic($node->operator, $left, $right, $this->truth($left->domain()->nullable || $right->domain()->nullable));
    }

    /**
     * Compiles NOT.
     */
    public function not(Not $node, Scope $scope): Evaluable
    {
        $operand = $this->compiler->compile($node->operand, $scope);

        return new Negation($operand, $this->truth($operand->domain()->nullable));
    }

    /**
     * Compiles IS [NOT] NULL.
     */
    public function nullTest(NullTest $node, Scope $scope): Evaluable
    {
        return new IsTest($this->compiler->compile($node->operand, $scope), null, $node->negated, $this->truth(false));
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

        return new IsTest($this->compiler->compile($node->operand, $scope), $truth, $node->negated, $this->truth(false));
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
        Collations::aggregate([$operand->domain(), $low->domain(), $high->domain()], 'between', $connection);
        $nullable = $operand->domain()->nullable || $low->domain()->nullable || $high->domain()->nullable;

        return new Range($operand, $low, $high, Comparator::of($operand->domain(), $low->domain(), 'between', $connection), Comparator::of($operand->domain(), $high->domain(), 'between', $connection), $node->negated, $this->truth($nullable));
    }

    /**
     * Compiles [NOT] IN with a list.
     */
    public function inList(InList $node, Scope $scope): Evaluable
    {
        $operand = $this->compiler->compile($node->operand, $scope);
        $elements = [];
        $nullable = $operand->domain()->nullable;
        foreach ($node->elements as $element) {
            $compiled = $this->compiler->compile($element, $scope);
            $elements[] = [$compiled, Comparator::of($operand->domain(), $compiled->domain(), 'in', $this->compiler->settings->connectionCollation)];
            $nullable = $nullable || $compiled->domain()->nullable;
        }

        return new Membership($operand, $elements, $node->negated, $this->truth($nullable));
    }

    /**
     * Compiles [NOT] LIKE.
     */
    public function like(Like $node, Scope $scope): Evaluable
    {
        $operand = $this->compiler->compile($node->operand, $scope);
        $pattern = $this->compiler->compile($node->pattern, $scope);
        $escape = $node->escape === null ? null : $this->compiler->compile($node->escape, $scope);
        [$collation] = Collations::aggregate([$operand->domain(), $pattern->domain()], 'like', $this->compiler->settings->connectionCollation);

        return new Pattern($operand, $pattern, $escape, $collation, $node->negated, $this->truth($operand->domain()->nullable || $pattern->domain()->nullable));
    }

    /**
     * Compiles CASE, with or without an operand.
     */
    public function caseOf(CaseExpression $node, Scope $scope): Evaluable
    {
        $operand = $node->operand === null ? null : $this->compiler->compile($node->operand, $scope);
        $branches = [];
        $results = [];
        foreach ($node->branches as $branch) {
            $condition = $this->compiler->compile($branch->condition, $scope);
            $result = $this->compiler->compile($branch->result, $scope);
            $comparator = $operand === null ? null : Comparator::of($operand->domain(), $condition->domain(), 'case', $this->compiler->settings->connectionCollation);
            $branches[] = [$condition, $comparator, $result];
            $results[] = $result->domain();
        }
        $else = $node->else === null ? null : $this->compiler->compile($node->else, $scope);
        $domain = (new Aggregation($this->compiler->settings->connectionCollation))->of([...$results, ...($else === null ? [] : [$else->domain()])], 'case');

        return new Choice($operand, $branches, $else, $domain->withNullable($domain->nullable || $else === null));
    }

    /**
     * Compiles CAST.
     */
    public function cast(Cast $node, Scope $scope): Evaluable
    {
        return (new Casts($this->compiler))->cast($this->compiler->compile($node->operand, $scope), $node->target);
    }
}
