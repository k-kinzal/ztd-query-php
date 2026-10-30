<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Problem;

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
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\TimeZoneConversion;
use Tests\Generator\MySql\Statement\Expression\Conversion\AtTimeZoneGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Expression\DeferredTimeZoneGenerator;

/**
 * Generates TimeZoneConversion values from the generators of its operands.
 *
 * @implements ErisGenerator<TimeZoneConversion>
 */
final class TimeZoneConversionGenerator implements ErisGenerator
{
    /** @var ErisGenerator<TimeZoneConversion> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): TimeZoneConversion => new TimeZoneConversion(expression: $parts[0], operand: $parts[1]),
            Generators::tuple(
                Generators::bind(Generators::elements(array_keys(array_filter([508, 508], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new AtTimeZoneGenerator($release),
                    1 => new DeferredTimeZoneGenerator($release),
                    default => throw new LogicException('Unreachable alternative.'),
                }),
                Generators::bool()
            )
        );
        $this->delegate = Generators::suchThat(
            static function (TimeZoneConversion $value) use ($release): bool {
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
     * @return GeneratedValue<TimeZoneConversion>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<TimeZoneConversion> $element The generated counterexample
     * @return GeneratedValue<TimeZoneConversion>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
