<?php

declare(strict_types=1);

namespace Tests\Generator\Semantics\Statement;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes Scalar values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Statement\Scalar>
 */
final class ScalarGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Statement\Scalar> */
    use Composition;

    /**
     * @param non-empty-list<ErisGenerator<\SqlSemantics\Statement\Scalar>> $alternatives Concrete SQL forms satisfying this role
     */
    public function __construct(array $alternatives)
    {
        $this->delegate = new ExpressionGenerator($alternatives);
    }

    /**
     * Composes recursive integer expressions with an independent arithmetic expectation.
     * Eris owns the depth, alternatives and operands; shrinking rebuilds both the
     * expression and its expected value, without evaluating SQL or calling MemoryDB.
     *
     * @return ErisGenerator<array{\SqlSemantics\Statement\Scalar, int}>
     */
    public static function integers(): ErisGenerator
    {
        return Generators::bind(Generators::choose(0, 4), self::integerTree(...));
    }

    /**
     * Extends an unrestricted scalar role with varying subtrees. Original
     * alternatives remain reachable while Eris chooses and shrinks the scalar
     * value and grouping depth. Ordinals, sampling operands, deferred conditions
     * and expressions paired with fixed layouts need their constrained generators.
     *
     * @param non-empty-list<ErisGenerator<\SqlSemantics\Statement\Scalar>> $alternatives
     * @return ErisGenerator<\SqlSemantics\Statement\Scalar>
     */
    public static function composed(array $alternatives): ErisGenerator
    {
        $leaves = new self([...$alternatives, Generators::map(static fn (array $sample): \SqlSemantics\Statement\Scalar => new \SqlSemantics\Platform\MySql\Statement\Expression\Grouped($sample[0]), self::integers())]);
        return Generators::bind(Generators::choose(0, 3), static function (int $depth) use ($leaves): ErisGenerator {
            $tree = $leaves;
            for ($level = 0; $level < $depth; $level++) {
                $tree = new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator($tree);
            }
            return $tree;
        });
    }

    /**
     * @return ErisGenerator<array{\SqlSemantics\Statement\Scalar, int}>
     */
    public static function integerTree(int $depth): ErisGenerator
    {
        $leaf = Generators::map(static fn (int $value): array => [new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral((string) $value), $value], Generators::choose(0, 100));
        if ($depth === 0) {
            return $leaf;
        }
        $child = self::integerTree($depth - 1);
        $group = Generators::map(self::integerGroup(...), $child);
        $negative = Generators::map(self::integerNegative(...), $child);
        $arithmetic = Generators::map(self::integerArithmetic(...), Generators::tuple($child, $child, Generators::bool()));
        $branch = Generators::map(self::integerBranch(...), Generators::tuple($child, $child, $child));
        return Generators::oneOf(...[$leaf, $group, $negative, $arithmetic, $branch]);
    }
    /**
     * @param array{\SqlSemantics\Statement\Scalar, int} $value
     * @return array{\SqlSemantics\Statement\Scalar, int}
     */
    public static function integerGroup(array $value): array
    {
        return [new \SqlSemantics\Platform\MySql\Statement\Expression\Grouped($value[0]), $value[1]];
    }

    /**
     * @param array{\SqlSemantics\Statement\Scalar, int} $value
     * @return array{\SqlSemantics\Statement\Scalar, int}
     */
    public static function integerNegative(array $value): array
    {
        return [new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Unary(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::Minus, new \SqlSemantics\Platform\MySql\Statement\Expression\Grouped($value[0])), -$value[1]];
    }

    /**
     * @param array{array{\SqlSemantics\Statement\Scalar, int}, array{\SqlSemantics\Statement\Scalar, int}, bool} $parts
     * @return array{\SqlSemantics\Statement\Scalar, int}
     */
    public static function integerArithmetic(array $parts): array
    {
        [$left, $right, $subtract] = $parts;
        $operator = $subtract ? \SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Minus : \SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Plus;
        return [new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic($operator, new \SqlSemantics\Platform\MySql\Statement\Expression\Grouped($left[0]), new \SqlSemantics\Platform\MySql\Statement\Expression\Grouped($right[0])), $subtract ? $left[1] - $right[1] : $left[1] + $right[1]];
    }

    /**
     * @param array{array{\SqlSemantics\Statement\Scalar, int}, array{\SqlSemantics\Statement\Scalar, int}, array{\SqlSemantics\Statement\Scalar, int}} $parts
     * @return array{\SqlSemantics\Statement\Scalar, int}
     */
    public static function integerBranch(array $parts): array
    {
        [$condition, $yes, $no] = $parts;
        return [new \SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseExpression(null, [new \SqlSemantics\Platform\MySql\Statement\Expression\Branching\CaseBranch($condition[0], $yes[0])], $no[0]), $condition[1] !== 0 ? $yes[1] : $no[1]];
    }

    /**
     * Composes scalar families whose execution depends on nested SQL roles.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Statement\Scalar>>
     */
    public static function expressionForms(): iterable
    {
        $numbers = \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator::numbers();
        $strings = \Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator::fromOperands(Generators::vector(1, Generators::map(static fn (int $value): string => 'value' . $value, Generators::choose(0, 32))), Generators::constant(null), Generators::constant(false), Generators::constant(null));
        yield 'Numeric categories' => $numbers;
        yield 'Concatenation operands' => new \Tests\Generator\MySql\Statement\Expression\Operator\ConcatenationGenerator($strings, $strings);
        yield 'Timestamp at time zone' => new \Tests\Generator\MySql\Statement\Expression\Conversion\AtTimeZoneGenerator(
            new \Tests\Generator\MySql\Statement\Literal\NullLiteralGenerator(),
            new \Tests\Generator\MySql\Statement\Expression\Conversion\TimeZoneGenerator(),
            Generators::bool(),
            Generators::map(static fn (int $precision): string => (string) $precision, Generators::choose(0, 6)),
        );
        $levels = new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(Generators::map(static fn (int $level): string => (string) $level, Generators::choose(1, 6)));
        yield 'Weight level list' => new \Tests\Generator\MySql\Statement\Call\Weight\WeightStringGenerator($strings, levels: Generators::vector(1, new \Tests\Generator\MySql\Statement\Call\Weight\WeightLevelGenerator($levels, reverse: Generators::bool())));
        yield 'Weight level range' => new \Tests\Generator\MySql\Statement\Call\Weight\WeightStringGenerator($strings, range: new \Tests\Generator\MySql\Statement\Call\Weight\WeightLevelRangeGenerator($levels, $levels));
        $projection = new \Tests\Generator\MySql\Statement\Query\SelectGenerator(Generators::constant([]), Generators::vector(2, new \Tests\Generator\MySql\Statement\Query\SelectExpressionGenerator($numbers)));
        $rows = new \Tests\Generator\MySql\Statement\Expression\Subquery\RowSubqueryGenerator($projection);
        yield 'Row subquery comparison' => new \Tests\Generator\MySql\Statement\Expression\ComparisonGenerator(
            \Tests\Generator\MySql\Statement\Expression\ComparisonOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\ComparisonOperator::Equal]),
            $rows,
            $rows,
        );
        yield 'Set function window' => new \Tests\Generator\MySql\Statement\Call\SetFunctionGenerator([
            new \Tests\Generator\MySql\Statement\Call\Aggregate\AggregateGenerator(
                \Tests\Generator\MySql\Statement\Call\Aggregate\AggregateFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Count]),
                Generators::constant([]),
                over: new \Tests\Generator\MySql\Statement\Call\Window\WindowSpecGenerator(),
            ),
        ]);
    }

}
