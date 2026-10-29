<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes DeferredSelection values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection>
 */
final class DeferredSelectionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Statement\Node>> $items Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Relation\TableSource|\SqlSemantics\Platform\MySql\Statement\Relation\Dual|null>|null $from Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand|null>|null $where Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Query\SelectOption>>|null $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Clause\Grouping|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredGrouping|null>|null $groupBy Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand|null>|null $having Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Query\OrderItem|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Selection\DeferredOrderItem>>|null $orderBy Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Limit|null>|null $limit Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Query\Clause\WindowDefinition>>|null $windows Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Clause\ProcedureAnalyse|null>|null $procedure Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Scalar|\SqlSemantics\Platform\MySql\Statement\Expression\RowOperand|\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\DeferredOperand|null>|null $qualify Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Query\Into\IntoDestination|null>|null $into Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $items, ?ErisGenerator $from = null, ?ErisGenerator $where = null, ?ErisGenerator $options = null, ?ErisGenerator $groupBy = null, ?ErisGenerator $having = null, ?ErisGenerator $orderBy = null, ?ErisGenerator $limit = null, ?ErisGenerator $windows = null, ?ErisGenerator $procedure = null, ?ErisGenerator $qualify = null, ?ErisGenerator $into = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection {
            return new \SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection(items: $parts[0], from: $parts[1], where: $parts[2], options: $parts[3], groupBy: $parts[4], having: $parts[5], orderBy: $parts[6], limit: $parts[7], windows: $parts[8], procedure: $parts[9], qualify: $parts[10], into: $parts[11], inputSource: $parts[12]);
        }, \Tests\Generator\Operands::tuple(
            $items,
            $from ?? Generators::constant(null),
            $where ?? Generators::constant(null),
            $options ?? Generators::constant([]),
            $groupBy ?? Generators::constant(null),
            $having ?? Generators::constant(null),
            $orderBy ?? Generators::constant([]),
            $limit ?? Generators::constant(null),
            $windows ?? Generators::constant([]),
            $procedure ?? Generators::constant(null),
            $qualify ?? Generators::constant(null),
            $into ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes invocation-time expression roles into complete stored selections.
     * Every varying operand is generated before execution and retains its shrinker.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Routine\Program\DeferredSelection>>
     */
    public static function operandForms(): iterable
    {
        $numbers = new \Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator(Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 100)));
        $rows = new \Tests\Generator\MySql\Statement\Expression\RowGenerator(\Tests\Generator\Operands::vector(2, $numbers));
        $deferred = new Expression\DeferredArithmeticGenerator(
            \Tests\Generator\MySql\Statement\Expression\Operator\ArithmeticOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Plus]),
            $rows,
            $numbers,
        );
        $units = new \Tests\Generator\MySql\Statement\Expression\IntervalUnitGenerator();
        $numerals = new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(Generators::map(static fn (int $value): string => (string) $value, Generators::choose(1, 32)));
        $frames = new \Tests\Generator\MySql\Statement\Call\Window\FrameGenerator(
            \Tests\Generator\MySql\Statement\Call\Window\FrameUnitGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit::Rows]),
            new \Tests\Generator\MySql\Statement\Call\Window\FrameBoundGenerator(\Tests\Generator\MySql\Statement\Call\Window\FrameBoundKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind::CurrentRow])),
            exclusion: new \Tests\Generator\MySql\Statement\Call\Window\FrameExclusionGenerator(),
        );
        $deferredFrames = new Selection\Frame\DeferredFrameGenerator(
            \Tests\Generator\MySql\Statement\Call\Window\FrameUnitGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameUnit::Range]),
            new Selection\Frame\DeferredFrameBoundGenerator(
                \Tests\Generator\MySql\Statement\Call\Window\FrameBoundKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind::Preceding]),
                $rows,
                $units,
            ),
        );
        $windowAggregates = static fn (\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredAggregateKind $selected): ErisGenerator => \Tests\Generator\Operands::bind(Expression\Call\DeferredAggregateKindGenerator::selecting([$selected]), static function (\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredAggregateKind $kind) use ($numbers, $frames): ErisGenerator {
            $aggregate = $kind === \SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredAggregateKind::GroupConcat
                ? new \Tests\Generator\MySql\Statement\Call\Aggregate\GroupConcatGenerator(\Tests\Generator\Operands::vector(1, $numbers))
                : new \Tests\Generator\MySql\Statement\Call\Aggregate\JsonObjectAggregateGenerator($numbers, $numbers);
            return new Expression\Call\DeferredWindowAggregateGenerator($aggregate, new \Tests\Generator\MySql\Statement\Call\Window\WindowSpecGenerator(frame: $frames));
        });
        $expressions = [
            'group concat window' => $windowAggregates(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredAggregateKind::GroupConcat),
            'json object window' => $windowAggregates(\SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Call\DeferredAggregateKind::JsonObject),
            'frame interval' => new Expression\Call\DeferredWindowAggregateGenerator(
                new \Tests\Generator\MySql\Statement\Call\Aggregate\AggregateGenerator(\Tests\Generator\MySql\Statement\Call\Aggregate\AggregateFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Count]), Generators::constant([])),
                new Selection\DeferredWindowSpecGenerator(frame: $deferredFrames),
            ),
            'full text dependency' => new Expression\Access\DeferredFullTextSearchGenerator(
                \Tests\Generator\Operands::vector(1, new \Tests\Generator\MySql\Statement\Name\ColumnUseGenerator(new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator())),
                $rows,
            ),
            'window exclusion' => new Expression\Call\DeferredWindowAggregateGenerator(
                new \Tests\Generator\MySql\Statement\Call\Aggregate\AggregateGenerator(\Tests\Generator\MySql\Statement\Call\Aggregate\AggregateFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Aggregate\AggregateFunction::Count]), Generators::constant([])),
                new \Tests\Generator\MySql\Statement\Call\Window\WindowSpecGenerator(frame: $frames),
            ),
            'not' => new Expression\Operator\DeferredNotGenerator($deferred),
            'null test' => new Expression\Operator\DeferredNullTestGenerator($deferred, Generators::bool()),
            'unary' => new Expression\Operator\DeferredUnaryGenerator(\Tests\Generator\MySql\Statement\Expression\Operator\UnaryOperatorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator::Minus]), new Expression\DeferredOperandGenerator([new Expression\DeferredGroupedGenerator($deferred)])),
            'extract' => new Expression\Call\DeferredExtractGenerator($units, $rows),
            'get format' => new Expression\Call\DeferredGetFormatGenerator(new \Tests\Generator\MySql\Statement\Call\Temporal\TemporalFormatGenerator(), $rows),
            'substring separators' => new Expression\Call\DeferredKeywordCallGenerator(\Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::Substring]), \Tests\Generator\Operands::tuple($rows, $numbers)),
            'keyword call' => new Expression\Call\DeferredKeywordCallGenerator(\Tests\Generator\MySql\Statement\Call\KeywordFunctionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\KeywordFunction::Coalesce]), \Tests\Generator\Operands::vector(2, $rows)),
            'timestamp' => new Expression\Call\DeferredTimestampGenerator(new \Tests\Generator\MySql\Statement\Call\Temporal\TimestampOperationGenerator(), \Tests\Generator\MySql\Statement\Expression\IntervalUnitGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::Second, \SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::Day, \SqlSemantics\Platform\MySql\Statement\Expression\IntervalUnit::Month]), $rows, $numbers),
            'trim' => new Expression\Call\DeferredTrimGenerator($rows, new \Tests\Generator\MySql\Statement\Call\TrimSideGenerator(), $numbers),
            'date arithmetic' => new Expression\Call\DeferredDateArithmeticGenerator(Generators::bool(), $rows, $numbers, $units),
            'weight parameters' => new Expression\Call\DeferredWeightParametersGenerator($rows, $numerals, $numerals, $numerals),
            'json value' => new Expression\Call\Json\DeferredJsonValueCallGenerator($rows, new \Tests\Generator\MySql\Statement\Literal\StringLiteralGenerator(strings: Generators::constant('$.value'))),
            'cast' => new Expression\Conversion\DeferredCastGenerator($rows, new \Tests\Generator\MySql\Statement\Type\CastTargetGenerator(\Tests\Generator\MySql\Statement\Type\Kind\CastKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Signed]))),
            'clock' => new Expression\Time\DeferredClockGenerator(\Tests\Generator\MySql\Statement\Call\ClockGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Call\Clock::Now]), new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(Generators::map(static fn (int $value): string => (string) $value, Generators::choose(7, 32)))),
        ];
        foreach ($expressions as $name => $expression) {
            yield $name => new self(\Tests\Generator\Operands::vector(1, new DeferredProjectionGenerator($expression)));
        }
    }

}
