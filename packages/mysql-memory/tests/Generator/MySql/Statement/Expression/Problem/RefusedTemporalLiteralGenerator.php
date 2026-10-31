<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Expression\Problem;

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
use SqlSemantics\Platform\MySql\Statement\Expression\Problem\RefusedTemporalLiteral;
use Tests\Generator\MySql\Statement\Literal\TemporalFormGenerator;

/**
 * Generates RefusedTemporalLiteral values from the generators of its operands.
 *
 * @implements ErisGenerator<RefusedTemporalLiteral>
 */
final class RefusedTemporalLiteralGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RefusedTemporalLiteral> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of RefusedTemporalLiteral from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): RefusedTemporalLiteral => new RefusedTemporalLiteral(text: $parts[0], form: $parts[1]),
            Generators::tuple(
                Generators::map(static fn (int $number): string => 'text_' . $number, Generators::choose(0, 99)),
                new TemporalFormGenerator($release)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (RefusedTemporalLiteral $value) use ($release): bool {
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
     * @return GeneratedValueSingle<RefusedTemporalLiteral>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<RefusedTemporalLiteral>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
