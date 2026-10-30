<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Problem;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Call\Window\Refinements;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\WindowRefinement;
use Tests\Generator\MySql\Statement\Call\Window\WindowSpecGenerator;
use Tests\Generator\MySql\Statement\Query\Clause\WindowDefinitionGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Selection\DeferredWindowSpecGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;

/**
 * Generates WindowRefinement values from the generators of its operands.
 *
 * @implements ErisGenerator<WindowRefinement>
 */
final class WindowRefinementGenerator implements ErisGenerator
{
    /** @var ErisGenerator<WindowRefinement> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): WindowRefinement => new WindowRefinement(window: $parts[0], ancestors: $parts[1], name: $parts[2]),
            Generators::suchThat(
                static fn (array $parts): bool => (new Refinements())->connected(($parts[0]), ($parts[1])) && (new Refinements())->condition(($parts[0]), ($parts[1])) !== null,
                Generators::tuple(
                    Generators::bind(Generators::elements(array_keys(array_filter([508, 508], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                        0 => new WindowSpecGenerator($release),
                        1 => new DeferredWindowSpecGenerator($release),
                        default => throw new LogicException('Unreachable alternative.'),
                    }),
                    ($bit & 508) !== 0 ? Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new WindowDefinitionGenerator($release))) : Generators::constant([]),
                    Generators::oneOf(Generators::constant(null), new NameGenerator($release))
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (WindowRefinement $value) use ($release): bool {
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
     * @return GeneratedValue<WindowRefinement>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<WindowRefinement> $element The generated counterexample
     * @return GeneratedValue<WindowRefinement>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
