<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Type\Resolved;

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
use SqlSemantics\Platform\MySql\Statement\Type\Resolved\Domain;

/**
 * Generates Domain values from the generators of its operands.
 *
 * @implements ErisGenerator<Domain>
 */
final class DomainGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Domain> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of Domain from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): Domain => new Domain(kind: $parts[0], field: $parts[1], length: $parts[2], decimals: $parts[3], unsigned: $parts[4], collation: $parts[5], members: $parts[6], coercibility: $parts[7], display: $parts[8], capacity: $parts[9], displayDecimals: $parts[10], storageField: $parts[11]),
            Generators::tuple(
                new KindGenerator($release),
                new FieldGenerator($release),
                Generators::choose(0, 100),
                Generators::choose(0, 100),
                Generators::bool(),
                Generators::oneOf(Generators::constant(null), new CollationGenerator($release)),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::map(static fn (int $number): string => 'text_' . $number, Generators::choose(0, 99)))),
                new CoercibilityGenerator($release),
                Generators::oneOf(Generators::constant(null), Generators::choose(0, 100)),
                Generators::oneOf(Generators::constant(null), Generators::choose(0, 100)),
                Generators::oneOf(Generators::constant(null), Generators::choose(0, 100)),
                Generators::oneOf(Generators::constant(null), new FieldGenerator($release))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (Domain $value) use ($release): bool {
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
     * @return GeneratedValueSingle<Domain>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<Domain>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
