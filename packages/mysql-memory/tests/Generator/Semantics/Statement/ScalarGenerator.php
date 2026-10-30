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
     * Composes recursive integer expressions with shrinking owned by Eris.
     * The generated values carry SQL meaning; no independently calculated row
     * accompanies the expression.
     *
     * @return ErisGenerator<\SqlSemantics\Statement\Scalar>
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
        $leaves = new self([...$alternatives, new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator(self::integers())]);
        return Generators::bind(Generators::choose(0, 3), static function (int $depth) use ($leaves): ErisGenerator {
            $tree = $leaves;
            for ($level = 0; $level < $depth; $level++) {
                $tree = new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator($tree);
            }
            return $tree;
        });
    }

    /**
     * @return ErisGenerator<\SqlSemantics\Statement\Scalar>
     */
    public static function integerTree(int $depth): ErisGenerator
    {
        $leaf = new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 100)));
        if ($depth === 0) {
            return $leaf;
        }
        $child = self::integerTree($depth - 1);
        $group = new \Tests\Generator\MySql\Statement\Expression\GroupedGenerator($child);
        $negative = new \Tests\Generator\MySql\Statement\Expression\Operator\UnaryGenerator(Generators::constant(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::Minus), $group);
        $arithmetic = new \Tests\Generator\MySql\Statement\Expression\Operator\ArithmeticGenerator(
            \Tests\Generator\MySql\Statement\Expression\Operator\ArithmeticOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Minus, \SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Plus]),
            $group,
            $group,
        );
        $branch = new \Tests\Generator\MySql\Statement\Expression\Branching\CaseExpressionGenerator(Generators::constant(null), Generators::vector(1, new \Tests\Generator\MySql\Statement\Expression\Branching\CaseBranchGenerator($child, $child)), $child);
        return Generators::oneOf($leaf, $group, $negative, $arithmetic, $branch);
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
