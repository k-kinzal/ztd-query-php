<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Server\Problem;

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
use SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialProblem;
use SqlSemantics\Platform\MySql\Statement\Server\Problem\SpatialRule;
use Tests\Generator\MySql\Statement\Server\Spatial\SpatialAttributeKindGenerator;

/**
 * Generates SpatialProblem values from the generators of its operands.
 *
 * @implements ErisGenerator<SpatialProblem>
 */
final class SpatialProblemGenerator implements ErisGenerator
{
    /** @var ErisGenerator<SpatialProblem> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of SpatialProblem from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::bind(
            new SpatialRuleGenerator($release),
            static fn (SpatialRule $rule): ErisGenerator => Generators::map(
                static fn (array $parts): SpatialProblem => $rule === SpatialRule::LineFeed
                    ? new SpatialProblem(rule: $rule, attribute: $parts[0], value: "line\n" . $parts[1])
                    : new SpatialProblem(rule: $rule, attribute: $parts[2], value: null),
                Generators::tuple(new SpatialAttributeKindGenerator($release), Generators::choose(0, 9), Generators::oneOf(Generators::constant(null), new SpatialAttributeKindGenerator($release)))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (SpatialProblem $value) use ($release): bool {
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
     * @return GeneratedValueSingle<SpatialProblem>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<SpatialProblem>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
