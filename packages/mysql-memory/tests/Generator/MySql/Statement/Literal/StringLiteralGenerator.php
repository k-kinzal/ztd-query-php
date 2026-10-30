<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Literal;

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
use SqlSemantics\Platform\MySql\Statement\Literal\StringLiteral;
use Tests\Generator\MySql\Statement\Type\Resolved\CollationGenerator;

/**
 * Generates StringLiteral values from the generators of its operands.
 *
 * @implements ErisGenerator<StringLiteral>
 */
final class StringLiteralGenerator implements ErisGenerator
{
    /** @var ErisGenerator<StringLiteral> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): StringLiteral => new StringLiteral(segments: $parts[0], introducer: null, national: $parts[1], collation: $parts[1] ? null : $parts[2], location: null, scalarInformation: null, quotations: []),
            Generators::tuple(
                Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => Generators::vector($count, Generators::map(static fn (int $number): string => 'text ' . $number, Generators::choose(0, 99)))),
                Generators::bool(),
                Generators::oneOf(Generators::constant(null), new CollationGenerator($release))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (StringLiteral $value) use ($release): bool {
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
     * @return GeneratedValue<StringLiteral>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<StringLiteral> $element The generated counterexample
     * @return GeneratedValue<StringLiteral>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
