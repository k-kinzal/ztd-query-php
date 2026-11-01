<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Call\Problem\Window;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Call\Window\Nesting;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Call\Problem\Window\NestingConstraint;
use Tests\Generator\MySql\Statement\Call\Window\WindowSpecGenerator;

/**
 * Generates NestingConstraint values from the generators of its operands.
 *
 * @implements ErisGenerator<NestingConstraint>
 */
final class NestingConstraintGenerator implements ErisGenerator
{
    /** @var ErisGenerator<NestingConstraint> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of NestingConstraint from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): NestingConstraint => new NestingConstraint(window: $parts[0]),
            Generators::suchThat(
                static fn (array $parts): bool => (new Nesting())->contains(($parts[0])),
                Generators::tuple(
                    new WindowSpecGenerator($release)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (NestingConstraint $value) use ($release): bool {
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
     * @return GeneratedValueSingle<NestingConstraint>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<NestingConstraint>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
