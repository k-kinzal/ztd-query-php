<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use Tests\Generator\MySql\Statement\Routine\Program\Definition\DeferredCreateTableGenerator;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\ProgramStatement;
use Tests\Generator\MySql\Statement\Account\Privilege\GrantPrivilegesGenerator;
use Tests\Generator\MySql\Statement\Routine\Condition\Diagnostics\GetDiagnosticsGenerator;
use Tests\Generator\MySql\Statement\Routine\Condition\ResignalGenerator;
use Tests\Generator\MySql\Statement\Routine\Condition\SignalGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Cursor\CloseCursorGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Cursor\FetchCursorGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Cursor\OpenCursorGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Flow\IfStatementGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Flow\IterateGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Flow\LeaveGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Flow\LoopGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Flow\RepeatLoopGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Flow\ReturnStatementGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Flow\SearchedCaseGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Flow\SimpleCaseGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Flow\WhileLoopGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Selection\DeferredQueryGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Server\DeferredExplainGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Server\DeferredGroupStartGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Server\DeferredKillGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Server\DeferredPurgeGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredDeleteGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredInsertQueryGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredInsertRowsGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredInsertSetGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\Write\DeferredMultipleDeleteGenerator;

/**
 * Generates ProgramStatement values from the generators of its operands.
 *
 * @implements ErisGenerator<ProgramStatement>
 */
final class ProgramStatementGenerator implements ErisGenerator
{
    /** @var ErisGenerator<ProgramStatement> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of ProgramStatement from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = $depth > 0
            ? Generators::bind(Generators::elements(array_keys(array_filter([511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 511, 504, 511, 511, 511, 508, 511, 511, 511, 511, 511], static fn (int $mask): bool => ($bit & $mask) !== 0))), static fn (int $choice): ErisGenerator => match ($choice) {
                0 => new GrantPrivilegesGenerator($release),
                1 => new GetDiagnosticsGenerator($release, $depth - 1),
                2 => new ResignalGenerator($release, $depth - 1),
                3 => new SignalGenerator($release, $depth - 1),
                4 => new BlockGenerator($release, $depth - 1),
                5 => new CloseCursorGenerator($release),
                6 => new FetchCursorGenerator($release),
                7 => new OpenCursorGenerator($release),
                8 => new DeferredAssignmentsGenerator($release, $depth - 1),
                9 => new DeferredEvaluationGenerator($release, $depth - 1),
                10 => new DeferredSelectionGenerator($release, $depth - 1),
                11 => new IfStatementGenerator($release, $depth - 1),
                12 => new IterateGenerator($release),
                13 => new LeaveGenerator($release),
                14 => new LoopGenerator($release, $depth - 1),
                15 => new RepeatLoopGenerator($release, $depth - 1),
                16 => new ReturnStatementGenerator($release, $depth - 1),
                17 => new SearchedCaseGenerator($release, $depth - 1),
                18 => new SimpleCaseGenerator($release, $depth - 1),
                19 => new WhileLoopGenerator($release, $depth - 1),
                20 => new DeferredQueryGenerator($release, $depth - 1),
                21 => new DeferredExplainGenerator($release, $depth - 1),
                22 => new DeferredGroupStartGenerator($release),
                23 => new DeferredKillGenerator($release, $depth - 1),
                24 => new DeferredPurgeGenerator($release, $depth - 1),
                25 => new DeferredDeleteGenerator($release, $depth - 1),
                26 => new DeferredInsertQueryGenerator($release, $depth - 1),
                27 => new DeferredInsertRowsGenerator($release, $depth - 1),
                28 => new DeferredInsertSetGenerator($release, $depth - 1),
                29 => new DeferredMultipleDeleteGenerator($release, $depth - 1),
                30 => new DeferredCreateTableGenerator($release, $depth - 1),
                default => throw new LogicException('Unreachable alternative.'),
            })
            : Generators::bind(Generators::choose(0, 6), static fn (int $choice): ErisGenerator => match ($choice) {
                0 => new GrantPrivilegesGenerator($release),
                1 => new CloseCursorGenerator($release),
                2 => new FetchCursorGenerator($release),
                3 => new OpenCursorGenerator($release),
                4 => new IterateGenerator($release),
                5 => new LeaveGenerator($release),
                6 => new DeferredGroupStartGenerator($release),
                default => throw new LogicException('Unreachable alternative.'),
            });
        $this->delegate = Generators::suchThat(
            static function (ProgramStatement $value) use ($release): bool {
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
     * @return GeneratedValueSingle<ProgramStatement>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<ProgramStatement>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
