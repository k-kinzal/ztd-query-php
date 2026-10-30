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
use SqlSemantics\Platform\MySql\Statement\Dml\Insert\InsertInto;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Write\DeferredInsertSet;
use Tests\Generator\MySql\Statement\Dml\AssignmentGenerator;
use Tests\Generator\MySql\Statement\Dml\Insert\InsertIntoGenerator;
use Tests\Generator\MySql\Statement\Dml\Insert\RowAliasGenerator;

/**
 * Generates DeferredInsertSet values from the generators of its operands.
 *
 * @implements ErisGenerator<DeferredInsertSet>
 */
final class DeferredInsertSetGenerator implements ErisGenerator
{
    /** @var ErisGenerator<DeferredInsertSet> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): DeferredInsertSet => new DeferredInsertSet(into: $parts[0], assignments: [$parts[1], ...$parts[2]], alias: $parts[0]->replace ? null : $parts[3], onDuplicate: $parts[0]->replace ? [] : $parts[4], inputSource: null),
            Generators::tuple(
                Generators::suchThat(static fn (InsertInto $into): bool => $into->columns === null, new InsertIntoGenerator($release)),
                new DeferredColumnAssignmentGenerator($release, $depth - 1),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::bool(), static fn (bool $deferred): ErisGenerator => $deferred ? new DeferredColumnAssignmentGenerator($release, $depth - 1) : new AssignmentGenerator($release, $depth - 1)))),
                Generators::oneOf(Generators::constant(null), new RowAliasGenerator($release)),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::bool(), static fn (bool $deferred): ErisGenerator => $deferred ? new DeferredColumnAssignmentGenerator($release, $depth - 1) : new AssignmentGenerator($release, $depth - 1))))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (DeferredInsertSet $value) use ($release): bool {
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
     * @return GeneratedValue<DeferredInsertSet>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<DeferredInsertSet> $element The generated counterexample
     * @return GeneratedValue<DeferredInsertSet>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
