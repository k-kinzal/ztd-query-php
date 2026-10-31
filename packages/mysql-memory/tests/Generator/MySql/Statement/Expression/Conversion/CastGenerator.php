<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Conversion;

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
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\Cast;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CastForm;
use Tests\Generator\MySql\Statement\Type\CastTargetGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates Cast values from the generators of its operands.
 *
 * @implements ErisGenerator<Cast>
 */
final class CastGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Cast> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of Cast from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::bind(
            new CastFormGenerator($release),
            static fn (CastForm $form): ErisGenerator => Generators::map(
                static fn (array $parts): Cast => new Cast(operand: $parts[0], target: $parts[1], array: $parts[2], scalarInformation: null, inputSource: null, form: $form),
                Generators::tuple(
                    new ScalarGenerator($release, $depth - 1),
                    new CastTargetGenerator($release),
                    $form === CastForm::Convert || in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? Generators::constant(false) : Generators::bool()
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (Cast $value) use ($release): bool {
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
     * @return GeneratedValueSingle<Cast>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Cast>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
