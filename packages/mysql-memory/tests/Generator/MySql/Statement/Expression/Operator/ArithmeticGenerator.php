<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Operator;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Expression\Precedence;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Expression\Grouped;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\Arithmetic;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\IntervalAddition;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates Arithmetic values from the generators of its operands.
 *
 * @implements ErisGenerator<Arithmetic>
 */
final class ArithmeticGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Arithmetic> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of Arithmetic from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static function (array $parts): Arithmetic {
                [$operator, $left, $right, $modWord, $precision] = $parts;
                $precedence = new Precedence();
                if (!$precedence->fits($left, $operator->level(), $operator->level())) {
                    $left = new Grouped($left);
                }
                if ($precedence->opening($right) <= $operator->level() || ($precedence->leading($right) instanceof IntervalAddition && ($operator === ArithmeticOperator::Plus || $operator === ArithmeticOperator::Minus))) {
                    $right = new Grouped($right);
                }

                return new Arithmetic(operator: $operator, left: $left, right: $right, scalarInformation: null, inputSource: null, modWord: $operator === ArithmeticOperator::Modulo && $modWord, divisionPrecision: $operator === ArithmeticOperator::Divide ? $precision : null);
            },
            Generators::tuple(
                new ArithmeticOperatorGenerator($release),
                new ScalarGenerator($release, $depth - 1),
                new ScalarGenerator($release, $depth - 1),
                Generators::bool(),
                Generators::choose(0, 30)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (Arithmetic $value) use ($release): bool {
                $domain = new ReleaseDomain();
                $transition = (new Generations())->transition($value);

                return ($domain->of($value) & $domain->bit($release)) !== 0
                    && (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? !(new ModernForms())->requires($value) : !(new LegacyForms())->requires($value))
                    && ($transition === 0 || ($transition & ($release === GrammarRelease::MySql5651 ? 1 : 2)) !== 0)
                    && ((new LexicalModes())->requirement($value) & 10) === 0;
            },
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<Arithmetic>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Arithmetic>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
