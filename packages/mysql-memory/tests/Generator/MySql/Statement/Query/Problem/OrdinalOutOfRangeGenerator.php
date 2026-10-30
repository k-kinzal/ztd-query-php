<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Problem;

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
use SqlSemantics\Platform\MySql\Statement\Query\Problem\OrdinalOutOfRange;
use Tests\Generator\MySql\Statement\Query\Clause\OutputOrdinalGenerator;

/**
 * Generates OrdinalOutOfRange values from the generators of its operands.
 *
 * @implements ErisGenerator<OrdinalOutOfRange>
 */
final class OrdinalOutOfRangeGenerator implements ErisGenerator
{
    /** @var ErisGenerator<OrdinalOutOfRange> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): OrdinalOutOfRange => new OrdinalOutOfRange(position: $parts[0], items: $parts[1], ordinal: $parts[2], grouped: $parts[3]),
            Generators::tuple(
                Generators::choose(0, 100),
                Generators::choose(0, 100),
                Generators::oneOf(Generators::constant(null), new OutputOrdinalGenerator($release)),
                Generators::bool()
            )
        );
        $this->delegate = Generators::suchThat(
            static function (OrdinalOutOfRange $value) use ($release): bool {
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
     * @return GeneratedValue<OrdinalOutOfRange>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<OrdinalOutOfRange> $element The generated counterexample
     * @return GeneratedValue<OrdinalOutOfRange>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
