<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Literal;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes Parameter values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Literal\Parameter>
 */
final class ParameterGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Literal\Parameter> */
    use Composition;

    /**
     * @param ErisGenerator<string>|null $marker Generated constructor operand
     * @param ErisGenerator<int>|null $position Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Fact\ScalarFact|null>|null $scalarInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(?ErisGenerator $marker = null, ?ErisGenerator $position = null, ?ErisGenerator $scalarInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Literal\Parameter {
            return new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter(marker: $parts[0], position: $parts[1], scalarInformation: $parts[2], inputSource: $parts[3]);
        }, Generators::tuple(
            $marker ?? Generators::constant('?'),
            $position ?? Generators::constant(0),
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Generates preparation input and bound values together, including reordered slots.
     * A literal divisor gives the hint a decimal result scale; two markers derive DOUBLE.
     *
     * @return ErisGenerator<array{\SqlSemantics\Platform\MySql\Statement\QueryRoot, list<array{int, \MySqlMemory\Typing\Domain}>}>
     */
    public static function preparedDivision(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $precision = new \Tests\Generator\MySql\Statement\Hint\Form\VariableHintGenerator(
            Generators::constant('div_precision_increment'),
            new \Tests\Generator\MySql\Statement\Hint\Form\HintLiteralGenerator(
                Generators::constant(\SqlSemantics\Platform\MySql\Statement\Hint\Form\HintLiteralKind::Integer),
                Generators::map(static fn (int $value): string => (string) $value, Generators::choose(0, 12)),
            ),
        );
        return Generators::map(self::division(...), Generators::tuple(Generators::constant($release), $precision, Generators::choose(1, 10000), Generators::choose(1, 99), Generators::bool(), Generators::bool()));
    }

    /**
     * Publishes the generated operands and retains the same slot/value relationship.
     *
     * @param array{\SqlSemantics\Contract\GrammarRelease, \SqlSemantics\Platform\MySql\Statement\Hint\Form\VariableHint, int, int, bool, bool} $parts The release, hint, dividend, divisor, slot order and whether both operands are parameters
     * @return array{\SqlSemantics\Platform\MySql\Statement\QueryRoot, list<array{int, \MySqlMemory\Typing\Domain}>}
     */
    public static function division(array $parts): array
    {
        [$release, $hint, $numerator, $denominator, $reverse, $pair] = $parts;
        $reverse = $reverse && $pair;
        $legacy = in_array($release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true);
        $left = new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter(position: $reverse ? 1 : 0);
        $right = $pair ? new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter(position: $reverse ? 0 : 1) : new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral((string) $denominator);
        $expression = new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Divide, $left, $right);
        $query = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($expression, new \SqlSemantics\Statement\Identifier\Name('quotient'))], hints: $legacy ? [] : [$hint]));
        $prepared = self::prepared($release, $query, $pair ? [0, 1] : [0], $legacy ? 4 : (int) $hint->value->value);
        $values = [[$numerator, \MySqlMemory\Typing\Domain::integer()], [$denominator, \MySqlMemory\Typing\Domain::integer()]];
        $values = $pair ? $values : [$values[0]];
        return [$prepared, $reverse ? array_reverse($values) : $values];
    }
    /**
     * Composes paired numeric markers and their bound execution inputs.
     *
     * @return ErisGenerator<array{\SqlSemantics\Platform\MySql\Statement\QueryRoot, list<array{int, \MySqlMemory\Typing\Domain}>}>
     */
    public static function arithmetic(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $operators = \Tests\Generator\MySql\Statement\Expression\Operator\ArithmeticOperatorGenerator::selecting([
            \SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Plus,
            \SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Minus,
            \SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Multiply,
            \SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Divide,
            \SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Modulo,
            \SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::IntegerDivide,
        ]);
        return Generators::map(self::arithmeticSample(...), Generators::tuple(Generators::constant($release), $operators, Generators::choose(-10000, 10000), Generators::choose(1, 99), Generators::bool()));
    }

    /**
     * Publishes the numeric expression and its related parameter values before execution.
     *
     * @param array{\SqlSemantics\Contract\GrammarRelease, \SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator, int, int, bool} $parts The release, operator, values and grouping choice
     * @return array{\SqlSemantics\Platform\MySql\Statement\QueryRoot, list<array{int, \MySqlMemory\Typing\Domain}>}
     */
    public static function arithmeticSample(array $parts): array
    {
        [$release, $operator, $left, $right, $grouped] = $parts;
        $a = new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter(position: 0);
        $b = new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter(position: 1);
        $expression = new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic($operator, $grouped ? new \SqlSemantics\Platform\MySql\Statement\Expression\Grouped($a) : $a, $grouped ? new \SqlSemantics\Platform\MySql\Statement\Expression\Grouped($b) : $b);
        $query = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($expression, new \SqlSemantics\Statement\Identifier\Name('answer'))]));
        $prepared = self::prepared($release, $query, [0, 1]);
        return [$prepared, [[$left, \MySqlMemory\Typing\Domain::integer()], [$right, \MySqlMemory\Typing\Domain::integer()]]];

    }

    /**
     * Composes a prepared statement with runtime values and a changed session setting.
     *
     * @return ErisGenerator<array{\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables, \SqlSemantics\Platform\MySql\Statement\QueryRoot, list<array{int|string, \MySqlMemory\Typing\Domain}>}>
     */
    public static function lifetime(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        return Generators::map(self::lifetimeSample(...), Generators::tuple(Generators::constant($release), Generators::choose(-999, 999), Generators::choose(2, 19), Generators::choose(1, 6)));
    }

    /**
     * Composes the changed session state, captured query and execution inputs.
     *
     * @throws LogicException When publication changes the concrete SET statement type.
     *
     * @param array{\SqlSemantics\Contract\GrammarRelease, int, int, int} $parts Release, value, divisor and preparation precision
     * @return array{\SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables, \SqlSemantics\Platform\MySql\Statement\QueryRoot, list<array{int|string, \MySqlMemory\Typing\Domain}>}
     */
    public static function lifetimeSample(array $parts): array
    {
        [$release, $value, $divisor, $precision] = $parts;
        $division = new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Divide, new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter(position: 0), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral((string) $divisor));
        $cast = new \SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast(new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter(position: 1), new \SqlSemantics\Platform\MySql\Statement\Type\CastTarget(\SqlSemantics\Platform\MySql\Statement\Type\Kind\CastKind::Signed));
        $variable = new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new \SqlSemantics\Statement\Identifier\Name('typed'));
        $query = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($division, new \SqlSemantics\Statement\Identifier\Name('quotient')), new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($cast, new \SqlSemantics\Statement\Identifier\Name('cast_value')), new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($variable, new \SqlSemantics\Statement\Identifier\Name('variable_value'))]));
        $prepared = self::prepared($release, $query, [0, 1], $precision, ['typed' => \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::integer()]);
        $text = $value . '.5';
        $setup = new \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables([
            new \SqlSemantics\Platform\MySql\Statement\Utility\Set\UserAssignment(new \SqlSemantics\Platform\MySql\Statement\Variable\UserVariable(new \SqlSemantics\Statement\Identifier\Name('typed')), new \SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral([$text])),
            new \SqlSemantics\Platform\MySql\Statement\Utility\Set\SystemAssignment(new \SqlSemantics\Platform\MySql\Statement\Variable\SystemTarget(new \SqlSemantics\Statement\Identifier\Name('div_precision_increment')), new \SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral((string) ($precision + 1))),
        ]);
        $context = new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile($release), [new \SqlSemantics\Statement\Identifier\Name('pbt')]);
        $assigned = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($context, $setup);
        if (!$assigned instanceof \SqlSemantics\Platform\MySql\Statement\Utility\Set\SetVariables) {
            throw new LogicException('The generated parameter prerequisites retain their SET statement type.');
        }
        return [$assigned, $prepared, [[$value, \MySqlMemory\Typing\Domain::integer()], [$text, \MySqlMemory\Typing\Domain::string(strlen($text), \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Collation::binary())]]];
    }

    /**
     * Publishes independently declared preparation inputs, without using the
     * MemoryDB binder or reading an execution session to construct the sample.
     *
     * @param list<int> $positions Generated marker positions
     * @param array<string, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain> $variables Declared user-variable domains
     */
    public static function prepared(\SqlSemantics\Contract\GrammarRelease $release, \SqlSemantics\Platform\MySql\Statement\QueryRoot $query, array $positions, int $precision = 4, array $variables = []): \SqlSemantics\Platform\MySql\Statement\QueryRoot
    {
        $connection = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings::defaults($release)->connection;
        $unbound = \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain::string(16383, $connection, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Field::VarString, \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Coercibility::Coercible);
        $settings = new \SqlSemantics\Platform\MySql\Statement\Type\Resolved\Settings($connection, divPrecisionIncrement: $precision, userVariables: $variables, parameters: array_fill_keys($positions, $unbound), unboundParameters: $positions);
        $context = new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile($release), [new \SqlSemantics\Statement\Identifier\Name('pbt')], session: $settings);
        $prepared = (new \SqlSemantics\Platform\MySql\Construction\Publication())->publish($context, $query);
        \SqlSemantics\Diagnostic\Check::invariant($prepared instanceof \SqlSemantics\Platform\MySql\Statement\QueryRoot, 'Publication retains the generated query.');
        return $prepared;
    }

}
