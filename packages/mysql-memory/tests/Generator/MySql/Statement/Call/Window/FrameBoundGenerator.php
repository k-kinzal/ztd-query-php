<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Window;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBound;
use SqlSemantics\Platform\MySql\Statement\Call\Window\FrameBoundKind;
use Tests\Generator\MySql\Statement\Expression\IntervalUnitGenerator;
use Tests\Generator\MySql\Statement\Literal\NumberLiteralGenerator;
use Tests\Generator\MySql\Statement\Literal\ParameterGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates FrameBound values from the generators of its operands.
 *
 * @implements ErisGenerator<FrameBound>
 */
final class FrameBoundGenerator implements ErisGenerator
{
    /** @var ErisGenerator<FrameBound> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of FrameBound from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::bind(
            new FrameBoundKindGenerator($release),
            static fn (FrameBoundKind $kind): ErisGenerator => Generators::map(
                static fn (array $parts): FrameBound => new FrameBound(kind: $kind, offset: $kind->offset() ? ($parts[1] === null ? $parts[0] : $parts[2]) : null, unit: $kind->offset() ? $parts[1] : null, inputSource: null),
                Generators::tuple(
                    Generators::bind(Generators::bool(), static fn (bool $number): ErisGenerator => $number ? new NumberLiteralGenerator($release) : new ParameterGenerator($release)),
                    Generators::oneOf(Generators::constant(null), new IntervalUnitGenerator($release)),
                    new ScalarGenerator($release, $depth - 1)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (FrameBound $value) use ($release): bool {
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
     * @return GeneratedValueSingle<FrameBound>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<FrameBound>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
