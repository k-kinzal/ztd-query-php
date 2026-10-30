<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Expression\Conversion;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Expression\Conversion\CastForm;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Expression\Conversion\DeferredCast;
use Tests\Generator\MySql\Statement\Expression\Conversion\CastFormGenerator;
use Tests\Generator\MySql\Statement\Expression\RowOperandGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredOperandGenerator;
use Tests\Generator\MySql\Statement\Type\CastTargetGenerator;

/**
 * Generates DeferredCast values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredCast>
 */
final class DeferredCastGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredCast> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::bind(
            new CastFormGenerator($release),
            static fn (CastForm $form): ErisGenerator => Generators::map(
                static fn (array $parts): DeferredCast => new DeferredCast(operand: $parts[0], target: $parts[1], scalarInformation: null, inputSource: null, array: $parts[2], form: $form),
                Generators::tuple(
                    Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new RowOperandGenerator($release, $depth - 1),
                        1 => new DeferredOperandGenerator($release, $depth - 1),
                        default => throw new LogicException('Unreachable alternative.'),
                    }),
                    new CastTargetGenerator($release),
                    $form === CastForm::Convert || in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? Generators::constant(false) : Generators::bool()
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DeferredCast $value) use ($release): bool {
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
     * @return GeneratedValue<DeferredCast>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<DeferredCast> $element The generated counterexample
     * @return GeneratedValue<DeferredCast>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
