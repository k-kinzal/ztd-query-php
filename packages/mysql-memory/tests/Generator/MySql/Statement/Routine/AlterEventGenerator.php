<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Diagnostic\Check;
use SqlSemantics\Platform\MySql\Construction\Publication;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Replication\Log\BinlogEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\AlterEvent;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Begin;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Literal\TextGenerator;
use Tests\Generator\MySql\Statement\Name\AccountGenerator;
use Tests\Generator\MySql\Statement\Routine\Definition\DefinitionSourceGenerator;
use Tests\Generator\MySql\Statement\Routine\Event\CompletionGenerator;
use Tests\Generator\MySql\Statement\Routine\Event\EventStatusGenerator;
use Tests\Generator\MySql\Statement\Routine\Event\ScheduleGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\ProgramStatementGenerator;
use Tests\Generator\MySql\Statement\StatementGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;
use Tests\Support\Analysis;

/**
 * Generates AlterEvent values from the generators of its operands.
 *
 * @implements ErisGenerator<AlterEvent>
 */
final class AlterEventGenerator implements ErisGenerator
{
    /** @var ErisGenerator<AlterEvent> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of AlterEvent from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): AlterEvent => new AlterEvent(name: $parts[0], schedule: $parts[1], completion: $parts[2], newName: $parts[3], status: $parts[4], comment: $parts[5], body: $parts[6], definer: $parts[7], statementInformation: $parts[8], inputSource: $parts[9], definitionSource: $parts[10]),
            Generators::suchThat(
                static fn (array $parts): bool => ($parts[1]) !== null || ($parts[2]) !== null || ($parts[3]) !== null || ($parts[4]) !== null || ($parts[5]) !== null || ($parts[6]) !== null,
                Generators::tuple(
                    new QualifiedNameGenerator($release),
                    $depth > 0 ? Generators::oneOf(Generators::constant(null), new ScheduleGenerator($release, $depth - 1)) : Generators::constant(null),
                    Generators::oneOf(Generators::constant(null), new CompletionGenerator($release)),
                    Generators::oneOf(Generators::constant(null), new QualifiedNameGenerator($release)),
                    Generators::oneOf(Generators::constant(null), new EventStatusGenerator($release)),
                    Generators::oneOf(Generators::constant(null), new TextGenerator($release)),
                    $depth > 0 ? Generators::oneOf(Generators::constant(null), Generators::bind(Generators::bool(), static fn (bool $program): ErisGenerator => $program ? new ProgramStatementGenerator($release, $depth - 1) : Generators::suchThat(static fn ($statement): bool => !$statement instanceof Begin && !$statement instanceof BinlogEvent && !$statement instanceof ExplainConnection, new StatementGenerator($release, $depth - 1)))) : Generators::constant(null),
                    Generators::oneOf(Generators::constant(null), new AccountGenerator($release)),
                    Generators::constant(null),
                    Generators::constant(null),
                    Generators::oneOf(Generators::constant(null), new DefinitionSourceGenerator($release))
                )
            )
        );
        $this->delegate = Generators::map(
            static function (AlterEvent $statement) use ($release): AlterEvent {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof AlterEvent, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (AlterEvent $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (AlterEvent $value) use ($release): bool {
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
     * @return GeneratedValueSingle<AlterEvent>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<AlterEvent>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
