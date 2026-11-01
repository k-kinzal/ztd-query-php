<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Json\Path;

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
use SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Index;
use SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Kind;
use SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Leg;

/**
 * Generates Leg values from the generators of its operands.
 *
 * @implements ErisGenerator<Leg>
 */
final class LegGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Leg> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of Leg from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::bind(
            new KindGenerator($release),
            static fn (Kind $kind): ErisGenerator => Generators::map(
                static function (array $parts) use ($kind): Leg {
                    [$name, $first, $second, $last] = $parts;
                    $from = in_array($kind, [Kind::Cell, Kind::Range], true) ? new Index($last, min($first, $second)) : null;
                    $to = $kind === Kind::Range ? new Index($last, $last ? min($first, $second) : max($first, $second)) : null;

                    return new Leg(kind: $kind, name: $kind === Kind::Member ? $name : null, from: $from, to: $to);
                },
                Generators::tuple(
                    Generators::map(static fn (int $number): string => 'member_' . $number, Generators::choose(0, 9)),
                    Generators::choose(0, 9),
                    Generators::choose(0, 9),
                    Generators::bool()
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (Leg $value) use ($release): bool {
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
     * @return GeneratedValueSingle<Leg>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Leg>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
