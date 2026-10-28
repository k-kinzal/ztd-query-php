<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Literal;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
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
        }, \Tests\Generator\Operands::tuple(
            $marker ?? Generators::constant('?'),
            $position ?? Generators::constant(0),
            $scalarInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Generates preparation input and bound values together, including reordered slots.
     * The generated hint controls the result scale in every subsequent binding.
     *
     * @return ErisGenerator<array{\SqlSemantics\Platform\MySql\Statement\QueryRoot, list<array{int, \MySqlMemory\Typing\Domain}>, string}>
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
        return Generators::map(self::division(...), \Tests\Generator\Operands::tuple(Generators::constant($release), $precision, Generators::choose(1, 10000), Generators::choose(1, 99), Generators::bool()));
    }

    /**
     * Publishes the generated operands and retains the same slot/value relationship.
     *
     * @param array{\SqlSemantics\Contract\GrammarRelease, \SqlSemantics\Platform\MySql\Statement\Hint\Form\VariableHint, int, int, bool} $parts The release, hint, dividend, divisor and slot order
     * @return array{\SqlSemantics\Platform\MySql\Statement\QueryRoot, list<array{int, \MySqlMemory\Typing\Domain}>, string}
     */
    public static function division(array $parts): array
    {
        [$release, $hint, $numerator, $denominator, $reverse] = $parts;
        $legacy = in_array($release, [\SqlSemantics\Contract\GrammarRelease::MySql5651, \SqlSemantics\Contract\GrammarRelease::MySql5744], true);
        $left = new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter(position: $reverse ? 1 : 0);
        $right = new \SqlSemantics\Platform\MySql\Statement\Literal\Parameter(position: $reverse ? 0 : 1);
        $expression = new \SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic(\SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator::Divide, $left, $right);
        $query = new \SqlSemantics\Platform\MySql\Statement\QueryRoot(new \SqlSemantics\Platform\MySql\Statement\Query\Select([], [new \SqlSemantics\Platform\MySql\Statement\Query\SelectExpression($expression, new \SqlSemantics\Statement\Identifier\Name('quotient'))], hints: $legacy ? [] : [$hint]));
        $session = (new \MySqlMemory\Instance(substr($release->value, 6)))->connect();
        $prepared = (new \MySqlMemory\Session\Statement\Binding())->statement($session, $query, prepared: true);
        \SqlSemantics\Diagnostic\Check::invariant($prepared instanceof \SqlSemantics\Platform\MySql\Statement\QueryRoot, 'Preparation retains the generated query.');
        $values = [[$numerator, \MySqlMemory\Typing\Domain::integer()], [$denominator, \MySqlMemory\Typing\Domain::integer()]];
        $scale = $legacy ? 4 : (int) $hint->value->value;
        $increment = bcdiv('5', bcpow('10', (string) ($scale + 1), 0), $scale + 1);
        $expected = bcadd(bcdiv((string) $numerator, (string) $denominator, $scale + 1), $increment, $scale);
        return [$prepared, $reverse ? array_reverse($values) : $values, $expected];
    }
}
