<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type;

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
use SqlSemantics\Platform\MySql\Statement\Type\Kind\TemporalKind;
use SqlSemantics\Platform\MySql\Statement\Type\Temporal;
use Tests\Generator\MySql\Statement\Type\Kind\NumericModifierGenerator;
use Tests\Generator\MySql\Statement\Type\Kind\TemporalKindGenerator;

/**
 * Generates Temporal values from the generators of its operands.
 *
 * @implements ErisGenerator<Temporal>
 */
final class TemporalGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Temporal> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of Temporal from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::bind(
            new TemporalKindGenerator($release),
            static fn (TemporalKind $kind): ErisGenerator => Generators::map(
                static fn (array $parts): Temporal => new Temporal(kind: $kind, precision: $parts[0], modifiers: $parts[1], inputSource: null, sqlTsiYear: $parts[2]),
                Generators::tuple(
                    $kind === TemporalKind::Date ? Generators::constant(null) : Generators::oneOf(Generators::constant(null), Generators::map(static fn (int $digits): string => (string) $digits, Generators::choose(0, 6))),
                    $kind === TemporalKind::Year ? Generators::bind(Generators::choose(0, 2), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new NumericModifierGenerator($release))) : Generators::constant([]),
                    $kind === TemporalKind::Year ? Generators::bool() : Generators::constant(false)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (Temporal $value) use ($release): bool {
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
     * @return GeneratedValueSingle<Temporal>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Temporal>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
