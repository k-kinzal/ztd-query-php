<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Literal;

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
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalForm;
use SqlSemantics\Platform\MySql\Statement\Literal\TemporalLiteral;
use Tests\Generator\MySql\Statement\Literal\Quoted\NotationGenerator;

/**
 * Generates TemporalLiteral values from the generators of its operands.
 *
 * @implements ErisGenerator<TemporalLiteral>
 */
final class TemporalLiteralGenerator implements ErisGenerator
{
    /** @var ErisGenerator<TemporalLiteral> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of TemporalLiteral from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): TemporalLiteral => new TemporalLiteral(form: $parts[0], text: match ($parts[0]) {
                TemporalForm::Date => sprintf('%04d-%02d-%02d', $parts[1], $parts[2], $parts[3]),
                TemporalForm::Time => sprintf('%02d:%02d:%02d', $parts[4], $parts[5], $parts[6]),
                TemporalForm::Timestamp => sprintf('%04d-%02d-%02d %02d:%02d:%02d', $parts[1], $parts[2], $parts[3], $parts[4], $parts[5], $parts[6]),
            }, scalarInformation: null, inputSource: null, quotation: $parts[7]),
            Generators::tuple(
                new TemporalFormGenerator($release),
                Generators::choose(1000, 9999),
                Generators::choose(1, 12),
                Generators::choose(1, 28),
                Generators::choose(0, 23),
                Generators::choose(0, 59),
                Generators::choose(0, 59),
                Generators::oneOf(Generators::constant(null), new NotationGenerator($release))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (TemporalLiteral $value) use ($release): bool {
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
     * @return GeneratedValueSingle<TemporalLiteral>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<TemporalLiteral>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
