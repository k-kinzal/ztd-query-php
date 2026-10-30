<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program\Flow;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Replication\Log\BinlogEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\Flow\Loop;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Begin;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection;
use Tests\Generator\MySql\Statement\Routine\Program\ProgramStatementGenerator;
use Tests\Generator\MySql\Statement\StatementGenerator;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;

/**
 * Generates Loop values from the generators of its operands.
 *
 * @implements ErisGenerator<Loop>
 */
final class LoopGenerator implements ErisGenerator
{
    /** @var ErisGenerator<Loop> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): Loop => new Loop(statements: $parts[0], label: $parts[1], endLabel: $parts[1] === null ? null : $parts[2], inputSource: $parts[3]),
            Generators::tuple(
                Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::choose(0, 1), static fn (int $choice): ErisGenerator => match ($choice) {
                    0 => new ProgramStatementGenerator($release, $depth - 1),
                    1 => Generators::suchThat(static fn ($statement): bool => !$statement instanceof Begin && !$statement instanceof BinlogEvent && !$statement instanceof ExplainConnection, new StatementGenerator($release, $depth - 1)),
                    default => throw new LogicException('Unreachable alternative.'),
                }))),
                Generators::oneOf(Generators::constant(null), new NameGenerator($release)),
                Generators::oneOf(Generators::constant(null), new NameGenerator($release)),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (Loop $value) use ($release): bool {
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
     * @return GeneratedValue<Loop>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<Loop> $element The generated counterexample
     * @return GeneratedValue<Loop>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
