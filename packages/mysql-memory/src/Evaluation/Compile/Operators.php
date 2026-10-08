<?php

declare(strict_types=1);

namespace MySqlMemory\Evaluation\Compile;

use MySqlMemory\Error\ErrorCode;
use MySqlMemory\Evaluation\Compile\Family\Casts;
use MySqlMemory\Evaluation\Convert;
use MySqlMemory\Evaluation\Evaluable;
use MySqlMemory\Evaluation\Frame;
use MySqlMemory\Evaluation\Function\Strings;
use MySqlMemory\Evaluation\Leaf\Constant;
use MySqlMemory\Evaluation\Leaf\Retyped;
use MySqlMemory\Evaluation\Operator\Arithmetic as ArithmeticEvaluable;
use MySqlMemory\Evaluation\Operator\Bits;
use MySqlMemory\Evaluation\Operator\Choice;
use MySqlMemory\Evaluation\Operator\Comparison\Comparator;
use MySqlMemory\Evaluation\Operator\Comparison\Compare;
use MySqlMemory\Evaluation\Operator\Comparison\IsTest;
use MySqlMemory\Evaluation\Operator\Comparison\Membership;
use MySqlMemory\Evaluation\Operator\Comparison\Pattern;
use MySqlMemory\Evaluation\Operator\Comparison\Range;
use MySqlMemory\Evaluation\Operator\Logic;
use MySqlMemory\Evaluation\Operator\Minus;
use MySqlMemory\Evaluation\Operator\Negation;
use MySqlMemory\Evaluation\Operator\Numeric;
use MySqlMemory\Evaluation\Scope;
use MySqlMemory\Typing\Collations;
use MySqlMemory\Typing\Domain;
use SqlSemantics\Platform\MySql\Statement\Call\FunctionCall;
use SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseExpression;
use SqlSemantics\Platform\MySql\Statement\Expression\Comparison;
use SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
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
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Kind;
use SqlSemantics\Statement\Scalar;

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
        $text = (new Printer($this->compiler->facts, $this->compiler->settings->database))->expression($node);
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
        $tested = $node->operator === UnaryOperator::Not ? $this->tested($node->operand) : null;
        if ($tested !== null) {
            return $this->nullness($tested[0], !$tested[1], $scope, $node);
        }
        $operand = $this->compiler->compile($node->operand, $scope);

        return match ($node->operator) {
            UnaryOperator::Plus => $operand,
            UnaryOperator::Not => $this->negation($operand, $node->operand, $node),
            UnaryOperator::Invert => new Bits(null, $operand, $operand, $this->compiler->domain($node), (new Printer($this->compiler->facts, $this->compiler->settings->database))->expression($node)),
            UnaryOperator::Minus => new Minus($operand, $this->compiler->domain($node), (new Printer($this->compiler->facts, $this->compiler->settings->database))->expression($node)),
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

        return $this->compare($node->operator, $node->left, $node->right, $scope, $node);
    }

    /**
     * Compiles a comparison of two scalar operands.
     *
     * Operands compared as doubles are read as doubles when they are evaluated, a constant one once
     * for the statement.
     */
    public function compare(ComparisonOperator $operator, Scalar $leftNode, Scalar $rightNode, Scope $scope, Scalar $node): Evaluable
    {
        $left = $this->compiler->compile($leftNode, $scope);
        $right = $this->compiler->compile($rightNode, $scope);
        $connection = $this->compiler->settings->connectionCollation;
        $comparator = Comparator::of($left->domain(), $right->domain(), $operator->value, $connection);
        $converted = $comparator->mode !== Kind::String && $comparator->mode !== Kind::Json && ($left->domain()->kind === Kind::String || $right->domain()->kind === Kind::String);
        $nullFromOperands = !$converted || !$this->varies($leftNode) || !$this->varies($rightNode);
        if ($comparator->mode === Kind::Double) {
            $left = $this->numeric($left, $leftNode);
            $right = $this->numeric($right, $rightNode);
            $comparator = Comparator::of($left->domain(), $right->domain(), $operator->value, $connection);
        }

        return new Compare($operator, $left, $right, $comparator, $this->compiler->domain($node), $nullFromOperands);
    }

    /**
     * Reads an operand a comparison compares as doubles as a double, when it is evaluated: once for the statement when it is constant.
     */
    public function numeric(Evaluable $operand, Scalar $node): Evaluable
    {
        return $operand->domain()->kind === Kind::Double ? $operand : new Numeric($operand, $this->compiler->constancy($node)->constant());
    }

    /**
     * Reads an operand whose truth value is taken: a constant string or temporal value is read as a double once for the statement.
     */
    public function truthOperand(Evaluable $operand, Scalar $node): Evaluable
    {
        $kind = $operand->domain()->kind;
        $converted = in_array($kind, [Kind::String, Kind::Json, Kind::Date, Kind::Time, Kind::DateTime], true);

        return $converted && $this->compiler->constancy($node)->constant() ? new Numeric($operand, true) : $operand;
    }

    /**
     * Tells whether an operand reads the row outside any subquery, or calls a function that varies by row.
     */
    public function varies(Scalar $node): bool
    {
        return $this->compiler->constancy($node, false) === Constancy::Row;
    }

    /**
     * Compiles AND, OR and XOR.
     */
    public function logical(Logical $node, Scope $scope): Evaluable
    {
        $left = $this->truthOperand($this->compiler->compile($node->left, $scope), $node->left);
        $right = $this->truthOperand($this->compiler->compile($node->right, $scope), $node->right);

        return new Logic($node->operator, $left, $right, $this->compiler->domain($node));
    }

    /**
     * Compiles NOT.
     */
    public function not(Not $node, Scope $scope): Evaluable
    {
        $tested = $this->tested($node->operand);
        if ($tested !== null) {
            return $this->nullness($tested[0], !$tested[1], $scope, $node);
        }

        return $this->negation($this->compiler->compile($node->operand, $scope), $node->operand, $node);
    }

    /**
     * Answers the operand of a test of NULL and whether the test is negated, or null when the node is no such test.
     *
     * IS [NOT] NULL, IS [NOT] UNKNOWN and ISNULL() test for NULL.
     *
     * @return array{Scalar, bool}|null
     */
    public function tested(Scalar $node): ?array
    {
        while ($node instanceof Grouped) {
            $node = $node->operand;
        }

        return match (true) {
            $node instanceof NullTest => [$node->operand, $node->negated],
            $node instanceof TruthTest && $node->truth === Truth::Unknown => [$node->operand, $node->negated],
            $node instanceof FunctionCall && $node->schema === null && strtoupper($node->name->value) === 'ISNULL' && count($node->arguments) === 1 => [$node->arguments[0]->expression, false],
            default => null,
        };
    }

    /**
     * Compiles a test of NULL of an operand.
     *
     * IS NULL of an operand known when the statement is resolved is evaluated then, once, as the
     * server evaluates it while it resolves the statement, even when no row is read.
     *
     * @throws \MySqlMemory\Error\SqlError When the operand cannot be compiled, or evaluating it is an error
     */
    public function nullness(Scalar $operandNode, bool $negated, Scope $scope, Scalar $node): Evaluable
    {
        $operand = $this->compiler->compile($operandNode, $scope);
        $domain = $this->compiler->domain($node);
        if (!$negated && $operand->domain()->nullable && $this->compiler->constancy($operandNode) === Constancy::Resolved) {
            return new Constant($domain, IsTest::isNull($operand, new Frame($this->compiler->connection->context)) ? 1 : 0);
        }

        return new IsTest($operand, null, $negated, $domain);
    }

    /**
     * Negates a compiled operand: the negation of a test of NULL is the opposite test, as the server rewrites it.
     */
    public function negation(Evaluable $operand, Scalar $operandNode, Scalar $node): Evaluable
    {
        $test = $operand;
        while ($test instanceof Retyped) {
            $test = $test->evaluable;
        }
        if ($test instanceof IsTest && $test->truth === null) {
            return new IsTest($test->operand, null, !$test->negated, $this->compiler->domain($node));
        }

        return new Negation($this->truthOperand($operand, $operandNode), $this->compiler->domain($node));
    }

    /**
     * Compiles IS [NOT] NULL.
     */
    public function nullTest(NullTest $node, Scope $scope): Evaluable
    {
        return $this->nullness($node->operand, $node->negated, $scope, $node);
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
        if ($truth === null) {
            return $this->nullness($node->operand, $node->negated, $scope, $node);
        }

        return new IsTest($this->truthOperand($this->compiler->compile($node->operand, $scope), $node->operand), $truth, $node->negated, $this->compiler->domain($node));
    }

    /**
     * Compiles [NOT] BETWEEN.
     *
     * Values compared as doubles are read as doubles once for each row. When one bound compares as
     * a string and the other as a number, all three compare as doubles.
     */
    public function between(Between $node, Scope $scope): Evaluable
    {
        $operand = $this->compiler->compile($node->operand, $scope);
        $low = $this->compiler->compile($node->low, $scope);
        $high = $this->compiler->compile($node->high, $scope);
        $connection = $this->compiler->settings->connectionCollation;
        $modes = [Comparator::of($operand->domain(), $low->domain(), 'between', $connection)->mode, Comparator::of($operand->domain(), $high->domain(), 'between', $connection)->mode];
        $numeric = static fn (Kind $mode): bool => in_array($mode, [Kind::Double, Kind::Decimal, Kind::Integer], true);
        if ($modes === [Kind::Double, Kind::Double] || (in_array(Kind::String, $modes, true) && ($numeric($modes[0]) || $numeric($modes[1])))) {
            $operand = $operand->domain()->kind === Kind::Double ? $operand : new Numeric($operand, false);
            $low = $low->domain()->kind === Kind::Double ? $low : new Numeric($low, false);
            $high = $high->domain()->kind === Kind::Double ? $high : new Numeric($high, false);
        }

        return new Range($operand, $low, $high, Comparator::of($operand->domain(), $low->domain(), 'between', $connection), Comparator::of($operand->domain(), $high->domain(), 'between', $connection), $node->negated, $this->compiler->domain($node));
    }

    /**
     * Compiles [NOT] IN with a list.
     *
     * A list of one value is the comparison `=`, or `<>` for NOT IN, as the server rewrites it. A
     * value compared with every element as a double is read as a double once for each row.
     */
    public function inList(InList $node, Scope $scope): Evaluable
    {
        if ($this->compiler->rows->elements($node->operand) !== null) {
            return $this->compiler->rows->in($node->operand, $node->elements, $node->negated, $scope);
        }
        if (count($node->elements) === 1 && $this->compiler->rows->elements($node->elements[0]) === null) {
            return $this->compare($node->negated ? ComparisonOperator::NotEqual : ComparisonOperator::Equal, $node->operand, $node->elements[0], $scope, $node);
        }
        $connection = $this->compiler->settings->connectionCollation;
        $operand = $this->compiler->compile($node->operand, $scope);
        $compiled = [];
        $modes = [];
        $fixed = true;
        foreach ($node->elements as $element) {
            $compiled[] = $this->compiler->compile($element, $scope);
            $modes[] = Comparator::of($operand->domain(), $compiled[count($compiled) - 1]->domain(), 'in', $connection)->mode;
            $fixed = $fixed && $this->compiler->constancy($element)->constant();
        }
        $fixed = $fixed && count(array_unique(array_map(static fn (Kind $mode): string => $mode->name, $modes))) === 1;
        if (array_unique(array_map(static fn (Kind $mode): string => $mode->name, $modes)) === [Kind::Double->name]) {
            $operand = $operand->domain()->kind === Kind::Double ? $operand : new Numeric($operand, false);
            $compiled = array_map(static fn (Evaluable $element): Evaluable => $element->domain()->kind === Kind::Double ? $element : new Numeric($element, false), $compiled);
        }
        $elements = array_map(static fn (Evaluable $element): array => [$element, Comparator::of($operand->domain(), $element->domain(), 'in', $connection)], $compiled);

        return new Membership($operand, $elements, $node->negated, $this->compiler->domain($node), $fixed);
    }

    /**
     * Compiles [NOT] LIKE.
     *
     * An ESCAPE expression that varies by row is refused (ER_WRONG_ARGUMENTS). One known when the
     * statement is resolved is evaluated then, and refused when it is more than one character; one
     * known only when the statement runs, such as USER() or a user variable, is checked when the
     * first row is matched, so a LIKE never evaluated never refuses it.
     *
     * @throws \MySqlMemory\Error\SqlError When the escape varies by row, or is known to be more than one character
     */
    public function like(Like $node, Scope $scope): Evaluable
    {
        $operand = $this->compiler->compile($node->operand, $scope);
        $pattern = $this->compiler->compile($node->pattern, $scope);
        $constancy = $node->escape === null ? Constancy::Resolved : $this->compiler->constancy($node->escape);
        if ($constancy === Constancy::Row) {
            throw ErrorCode::WrongArguments->error('ESCAPE');
        }
        $escape = match (true) {
            $node->escape === null => null,
            $constancy === Constancy::Statement => $this->compiler->compile($node->escape, $scope),
            default => $this->escape($node->escape, $scope),
        };
        [$collation] = Collations::aggregate([$operand->domain(), $pattern->domain()], 'like', $this->compiler->settings->connectionCollation, true);

        return new Pattern($operand, $pattern, $escape, $collation, $node->negated, $this->compiler->domain($node), $constancy === Constancy::Statement);
    }

    /**
     * Compiles and evaluates an ESCAPE expression known when the statement is resolved into its value.
     *
     * @throws \MySqlMemory\Error\SqlError When the escape is more than one character
     */
    public function escape(Scalar $node, Scope $scope): Evaluable
    {
        $compiled = $this->compiler->compile($node, $scope);
        $value = $compiled->evaluate(new Frame($this->compiler->connection->context));
        $text = Convert::toText($value, $compiled->domain());
        if ($text !== null && (new Strings())->count($text, $compiled->domain()) > 1) {
            throw ErrorCode::WrongArguments->error('ESCAPE');
        }

        return new Constant($compiled->domain(), $value);
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
        return (new Casts($this->compiler))->cast($this->compiler->compile($node->operand, $scope), $node->target, $node);
    }
}
