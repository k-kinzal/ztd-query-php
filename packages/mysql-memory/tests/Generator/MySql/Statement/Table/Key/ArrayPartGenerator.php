<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Key;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Expression\Conversion\CastProblems;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Table\Key\ArrayPart;
use SqlSemantics\Platform\MySql\Statement\Type\CastTarget;
use Tests\Generator\MySql\Statement\Query\DirectionGenerator;
use Tests\Generator\MySql\Statement\Type\CastTargetGenerator;
use Tests\Generator\Semantics\Statement\ScalarGenerator;

/**
 * Generates ArrayPart values from the generators of its operands.
 *
 * @implements ErisGenerator<ArrayPart>
 */
final class ArrayPartGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ArrayPart> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of ArrayPart from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): ArrayPart => new ArrayPart(expression: $parts[0], target: $parts[1], direction: $parts[2], scalarInformation: null, inputSource: null),
            Generators::tuple(
                new ScalarGenerator($release, $depth - 1),
                Generators::suchThat(static fn (CastTarget $target): bool => (new CastProblems())->target($target, true) === null, new CastTargetGenerator($release)),
                Generators::oneOf(Generators::constant(null), new DirectionGenerator($release))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (ArrayPart $value) use ($release): bool {
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
     * @return GeneratedValueSingle<ArrayPart>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<ArrayPart>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
