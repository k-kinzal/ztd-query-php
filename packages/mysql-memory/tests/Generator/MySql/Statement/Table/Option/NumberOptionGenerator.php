<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Option;

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
use SqlSemantics\Platform\MySql\Statement\Table\Option\Kind\NumberOptionKind;
use SqlSemantics\Platform\MySql\Statement\Table\Option\NumberOption;
use Tests\Generator\MySql\Statement\AssignmentOperatorGenerator;
use Tests\Generator\MySql\Statement\Literal\NumeralGenerator;
use Tests\Generator\MySql\Statement\Table\Option\Kind\NumberOptionKindGenerator;

/**
 * Generates NumberOption values from the generators of its operands.
 *
 * @implements ErisGenerator<NumberOption>
 */
final class NumberOptionGenerator implements ErisGenerator
{
    /** @var ErisGenerator<NumberOption> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::bind(
            new NumberOptionKindGenerator($release),
            static fn (NumberOptionKind $kind): ErisGenerator => Generators::map(
                static fn (array $parts): NumberOption => new NumberOption(kind: $kind, value: $parts[0], inputSource: null, operator: $parts[1]),
                Generators::tuple(
                    $kind->defaultable() ? Generators::oneOf(Generators::constant(null), new NumeralGenerator($release)) : new NumeralGenerator($release),
                    Generators::oneOf(Generators::constant(null), new AssignmentOperatorGenerator($release))
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (NumberOption $value) use ($release): bool {
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
     * @return GeneratedValue<NumberOption>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<NumberOption> $element The generated counterexample
     * @return GeneratedValue<NumberOption>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
