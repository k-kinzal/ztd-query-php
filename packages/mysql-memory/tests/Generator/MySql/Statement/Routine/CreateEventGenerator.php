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
use SqlSemantics\Platform\MySql\Statement\Routine\CreateEvent;
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

/**
 * Generates CreateEvent values from the generators of its operands.
 *
 * @implements ErisGenerator<CreateEvent>
 */
final class CreateEventGenerator implements ErisGenerator
{
    /** @var ErisGenerator<CreateEvent> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of CreateEvent from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): CreateEvent => new CreateEvent(name: $parts[0], schedule: $parts[1], body: $parts[2], completion: $parts[3], status: $parts[4], comment: $parts[5], definer: $parts[6], ifNotExists: $parts[7], statementInformation: $parts[8], inputSource: $parts[9], definitionSource: $parts[10]),
            Generators::suchThat(
                static fn (array $parts): bool => (new \SqlSemantics\Platform\MySql\Rules\Routine\Definition\BodyConditions())->refusal($parts[2], \SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind::Event, $parts[0], null, [], $parts[10]?->bodyRefusalOffset) === null,
                Generators::tuple(
                    new QualifiedNameGenerator($release),
                    new ScheduleGenerator($release, $depth - 1),
                    Generators::bind(Generators::bool(), static fn (bool $program): ErisGenerator => $program ? new ProgramStatementGenerator($release, $depth - 1) : Generators::suchThat(static fn ($statement): bool => !$statement instanceof Begin && !$statement instanceof BinlogEvent && !$statement instanceof ExplainConnection, new StatementGenerator($release, $depth - 1))),
                    Generators::oneOf(Generators::constant(null), new CompletionGenerator($release)),
                    Generators::oneOf(Generators::constant(null), new EventStatusGenerator($release)),
                    Generators::oneOf(Generators::constant(null), new TextGenerator($release)),
                    Generators::oneOf(Generators::constant(null), new AccountGenerator($release)),
                    Generators::bool(),
                    Generators::constant(null),
                    Generators::constant(null),
                    Generators::oneOf(Generators::constant(null), new DefinitionSourceGenerator($release))
                )
            )
        );
        $this->delegate = Generators::map(
            static function (CreateEvent $statement) use ($release): CreateEvent {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof CreateEvent, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (CreateEvent $value) use ($release): bool {
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
     * @return GeneratedValueSingle<CreateEvent>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<CreateEvent>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
