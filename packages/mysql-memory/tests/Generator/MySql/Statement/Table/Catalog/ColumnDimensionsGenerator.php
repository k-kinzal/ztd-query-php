<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Table\Catalog;

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
use SqlSemantics\Platform\MySql\Statement\Table\Catalog\ColumnDimensions;

/**
 * Generates ColumnDimensions values from the generators of its operands.
 *
 * @implements ErisGenerator<ColumnDimensions>
 */
final class ColumnDimensionsGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ColumnDimensions> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): ColumnDimensions => new ColumnDimensions(name: $parts[0], characters: $parts[1], octets: $parts[1] === null ? null : $parts[1] * $parts[2], precision: $parts[3], scale: $parts[3] === null ? null : $parts[4] % ($parts[3] + 1), fraction: $parts[5]),
            Generators::tuple(
                Generators::elements(['int', 'varchar', 'decimal', 'datetime', 'json']),
                Generators::oneOf(Generators::constant(null), Generators::choose(0, 100)),
                Generators::choose(1, 4),
                Generators::oneOf(Generators::constant(null), Generators::choose(0, 65)),
                Generators::choose(0, 65),
                Generators::oneOf(Generators::constant(null), Generators::choose(0, 6))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (ColumnDimensions $value) use ($release): bool {
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
     * @return GeneratedValue<ColumnDimensions>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<ColumnDimensions> $element The generated counterexample
     * @return GeneratedValue<ColumnDimensions>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
