<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Write;

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
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertRows;
use Tests\Generator\MySql\Statement\Dml\AssignmentGenerator;
use Tests\Generator\MySql\Statement\Dml\Insert\InsertedRowGenerator;
use Tests\Generator\MySql\Statement\Dml\Insert\InsertIntoGenerator;
use Tests\Generator\MySql\Statement\Dml\Insert\RowAliasGenerator;
use Tests\Generator\MySql\Statement\Dml\Insert\ValuesKeywordGenerator;

/**
 * Generates DeferredInsertRows values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredInsertRows>
 */
final class DeferredInsertRowsGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredInsertRows> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): DeferredInsertRows => new DeferredInsertRows(into: $parts[0], rows: [$parts[1], ...$parts[2]], alias: $parts[0]->replace ? null : $parts[3], onDuplicate: $parts[0]->replace ? [] : $parts[4], inputSource: null, keyword: $parts[5]),
            Generators::tuple(
                new InsertIntoGenerator($release),
                new DeferredInsertedRowGenerator($release, $depth - 1),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::bool(), static fn (bool $deferred): ErisGenerator => $deferred ? new DeferredInsertedRowGenerator($release, $depth - 1) : new InsertedRowGenerator($release, $depth - 1)))),
                Generators::oneOf(Generators::constant(null), new RowAliasGenerator($release)),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::bool(), static fn (bool $deferred): ErisGenerator => $deferred ? new DeferredColumnAssignmentGenerator($release, $depth - 1) : new AssignmentGenerator($release, $depth - 1)))),
                new ValuesKeywordGenerator($release)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DeferredInsertRows $value) use ($release): bool {
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
     * @return GeneratedValue<DeferredInsertRows>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<DeferredInsertRows> $element The generated counterexample
     * @return GeneratedValue<DeferredInsertRows>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
