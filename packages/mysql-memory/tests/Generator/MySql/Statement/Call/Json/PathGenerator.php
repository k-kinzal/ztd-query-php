<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Json;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Call\Json\Path;
use SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Index;
use SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Kind;
use SqlSemantics\Platform\MySql\Statement\Call\Json\Path\Leg;

/**
 * Generates Path values from the generators of its operands.
 *
 * @implements ErisGenerator<Path>
 */
final class PathGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Path> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static function (array $steps): Path {
                $legs = [];
                foreach ($steps as [$descend, $leg]) {
                    if ($descend) {
                        $legs[] = new Leg(Kind::Descendants);
                    }
                    $legs[] = $leg;
                }

                return new Path($legs);
            },
            Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::tuple(
                Generators::bool(),
                Generators::bind(Generators::elements([Kind::Member, Kind::AnyMember, Kind::Cell, Kind::AnyCell, Kind::Range]), static fn (Kind $kind): ErisGenerator => Generators::map(
                    static fn (array $parts): Leg => new Leg(
                        kind: $kind,
                        name: $kind === Kind::Member ? 'member_' . $parts[0] : null,
                        from: in_array($kind, [Kind::Cell, Kind::Range], true) ? new Index($parts[2], min($parts[0], $parts[1])) : null,
                        to: $kind === Kind::Range ? new Index($parts[2], $parts[2] ? min($parts[0], $parts[1]) : max($parts[0], $parts[1])) : null,
                    ),
                    Generators::tuple(Generators::choose(0, 9), Generators::choose(0, 9), Generators::bool())
                ))
            )))
        );
        $this->delegate = Generators::suchThat(
            static function (Path $value) use ($release): bool {
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
     * @return GeneratedValue<Path>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<Path> $element The generated counterexample
     * @return GeneratedValue<Path>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
