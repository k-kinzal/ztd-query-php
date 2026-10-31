<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine\Program;

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
use SqlSemantics\Platform\MySql\Statement\Replication\Log\BinlogEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\Program\HandlerDeclaration;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Begin;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection;
use Tests\Generator\MySql\Statement\Routine\Condition\ConditionGenerator;
use Tests\Generator\MySql\Statement\StatementGenerator;

/**
 * Generates HandlerDeclaration values from the generators of its operands.
 *
 * @implements ErisGenerator<HandlerDeclaration>
 */
final class HandlerDeclarationGenerator implements ErisGenerator
{
    /** @var ErisGenerator<HandlerDeclaration> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of HandlerDeclaration from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): HandlerDeclaration => new HandlerDeclaration(action: $parts[0], conditions: $parts[1], statement: $parts[2], inputSource: $parts[3]),
            Generators::tuple(
                new HandlerActionGenerator($release),
                Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new ConditionGenerator($release))),
                Generators::bind(Generators::bool(), static fn (bool $program): ErisGenerator => $program ? new ProgramStatementGenerator($release, $depth - 1) : Generators::suchThat(static fn ($statement): bool => !$statement instanceof Begin && !$statement instanceof BinlogEvent && !$statement instanceof ExplainConnection, new StatementGenerator($release, $depth - 1))),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::suchThat(
            static function (HandlerDeclaration $value) use ($release): bool {
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
     * @return GeneratedValueSingle<HandlerDeclaration>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<HandlerDeclaration>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
