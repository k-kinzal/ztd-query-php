<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Clause;

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
use SqlSemantics\Platform\MySql\Statement\Literal\NumberLiteral;
use SqlSemantics\Platform\MySql\Statement\Query\Clause\RowLimit;
use Tests\Generator\MySql\Statement\Literal\ParameterGenerator;

/**
 * Generates RowLimit values from the generators of its operands.
 *
 * @implements ErisGenerator<RowLimit>
 */
final class RowLimitGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RowLimit> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $operand = Generators::bind(Generators::choose(0, 2), static fn (int $choice): ErisGenerator => match ($choice) {
            0 => Generators::map(static fn (int $number): NumberLiteral => new NumberLiteral((string) $number), Generators::choose(0, 99)),
            1 => new ParameterGenerator($release),
            default => new ProgramVariableGenerator($release),
        });
        $this->delegate = Generators::map(
            static fn (array $parts): RowLimit => new RowLimit(count: $parts[0], offset: $parts[1], spelling: $parts[1] === null ? null : $parts[2], inputSource: null),
            Generators::tuple($operand, Generators::oneOf(Generators::constant(null), $operand), new OffsetSpellingGenerator($release))
        );
        $this->delegate = Generators::suchThat(
            static function (RowLimit $value) use ($release): bool {
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
     * @return GeneratedValue<RowLimit>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<RowLimit> $element The generated counterexample
     * @return GeneratedValue<RowLimit>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
