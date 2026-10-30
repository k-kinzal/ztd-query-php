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
use SqlSemantics\Platform\MySql\Statement\Table\Catalog\SystemTable;
use Tests\Generator\Semantics\Statement\Declaration\TableGenerator;

/**
 * Generates SystemTable values from the generators of its operands.
 *
 * @implements ErisGenerator<SystemTable>
 */
final class SystemTableGenerator implements ErisGenerator
{
    /** @var ErisGenerator<SystemTable> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): SystemTable => new SystemTable(schema: $parts[0], name: $parts[1], type: $parts[2], engine: $parts[3], version: $parts[4], rowFormat: $parts[5], collation: $parts[6], options: $parts[7], comment: $parts[8], columns: $parts[9], declaration: $parts[10]),
            Generators::tuple(
                Generators::map(static fn (int $number): string => 'text_' . $number, Generators::choose(0, 99)),
                Generators::map(static fn (int $number): string => 'text_' . $number, Generators::choose(0, 99)),
                Generators::map(static fn (int $number): string => 'text_' . $number, Generators::choose(0, 99)),
                Generators::oneOf(Generators::constant(null), Generators::map(static fn (int $number): string => 'text_' . $number, Generators::choose(0, 99))),
                Generators::oneOf(Generators::constant(null), Generators::choose(0, 100)),
                Generators::oneOf(Generators::constant(null), Generators::map(static fn (int $number): string => 'text_' . $number, Generators::choose(0, 99))),
                Generators::oneOf(Generators::constant(null), Generators::map(static fn (int $number): string => 'text_' . $number, Generators::choose(0, 99))),
                Generators::map(static fn (int $number): string => 'text_' . $number, Generators::choose(0, 99)),
                Generators::map(static fn (int $number): string => 'text_' . $number, Generators::choose(0, 99)),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new SystemColumnGenerator($release))),
                new TableGenerator($release)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (SystemTable $value) use ($release): bool {
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
     * @return GeneratedValue<SystemTable>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<SystemTable> $element The generated counterexample
     * @return GeneratedValue<SystemTable>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
