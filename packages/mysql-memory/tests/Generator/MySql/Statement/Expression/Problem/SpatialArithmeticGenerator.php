<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\ArithmeticOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Operator\UnaryOperator;
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\SpatialArithmetic;
use Tests\Generator\MySql\Statement\Expression\Operator\ArithmeticOperatorGenerator;
use Tests\Generator\MySql\Statement\Expression\Operator\UnaryOperatorGenerator;

/**
 * Generates SpatialArithmetic values from the generators of its operands.
 *
 * @implements ErisGenerator<SpatialArithmetic>
 */
final class SpatialArithmeticGenerator implements ErisGenerator
{
    /** @var ErisGenerator<SpatialArithmetic> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of SpatialArithmetic from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): SpatialArithmetic => new SpatialArithmetic(operator: $parts[0]),
            Generators::suchThat(
                static fn (array $parts): bool => ($parts[0]) instanceof ArithmeticOperator || in_array(($parts[0]), [UnaryOperator::Minus, UnaryOperator::Invert], true),
                Generators::tuple(
                    Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new ArithmeticOperatorGenerator($release),
                        1 => new UnaryOperatorGenerator($release),
                        default => throw new LogicException('Unreachable alternative.'),
                    })
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (SpatialArithmetic $value) use ($release): bool {
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
     * @return GeneratedValueSingle<SpatialArithmetic>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<SpatialArithmetic>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
