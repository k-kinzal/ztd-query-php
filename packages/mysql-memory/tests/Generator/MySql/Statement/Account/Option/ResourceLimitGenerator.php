<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Option;

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
use SqlSemantics\Platform\MySql\Statement\Account\Option\ResourceLimit;
use Tests\Generator\MySql\Statement\Literal\NumeralGenerator;

/**
 * Generates ResourceLimit values from the generators of its operands.
 *
 * @implements ErisGenerator<ResourceLimit>
 */
final class ResourceLimitGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ResourceLimit> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of ResourceLimit from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): ResourceLimit => new ResourceLimit(kind: $parts[0], number: $parts[1], inputSource: $parts[2]),
            Generators::tuple(
                new ResourceKindGenerator($release),
                new NumeralGenerator($release),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (ResourceLimit $value) use ($release): bool {
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
     * @return GeneratedValueSingle<ResourceLimit>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<ResourceLimit>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
