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
use SqlSemantics\Platform\MySql\Statement\Routine\CreateTrigger;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Begin;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Name\AccountGenerator;
use Tests\Generator\MySql\Statement\Routine\Definition\DefinitionSourceGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\ProgramStatementGenerator;
use Tests\Generator\MySql\Statement\Routine\Trigger\TriggerEventGenerator;
use Tests\Generator\MySql\Statement\Routine\Trigger\TriggerOrderGenerator;
use Tests\Generator\MySql\Statement\Routine\Trigger\TriggerTableGenerator;
use Tests\Generator\MySql\Statement\Routine\Trigger\TriggerTimeGenerator;
use Tests\Generator\MySql\Statement\StatementGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;

/**
 * Generates CreateTrigger values from the generators of its operands.
 *
 * @implements ErisGenerator<CreateTrigger>
 */
final class CreateTriggerGenerator implements ErisGenerator
{
    /** @var ErisGenerator<CreateTrigger> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of CreateTrigger from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     * @param int $depth The nesting depth left for the recursive components
     */
    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): CreateTrigger => new CreateTrigger(name: $parts[0], time: $parts[1], event: $parts[2], table: $parts[3], body: $parts[4], order: $parts[5], definer: $parts[6], ifNotExists: $parts[7], statementInformation: $parts[8], inputSource: $parts[9], definitionSource: $parts[10]),
            Generators::suchThat(
                static fn (array $parts): bool => (new \SqlSemantics\Platform\MySql\Rules\Routine\Definition\BodyConditions())->refusal($parts[4], \SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind::Trigger, $parts[0], null, [], $parts[10]?->bodyRefusalOffset) === null && (new \SqlSemantics\Platform\MySql\Rules\Routine\TriggerRows())->problem($parts[4], $parts[1], $parts[2]) === null && (new \SqlSemantics\Platform\MySql\Rules\Routine\Declaration\BodyNames())->unknown($parts[4], [], \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables::of($release)) === null,
                Generators::tuple(
                    new QualifiedNameGenerator($release),
                    new TriggerTimeGenerator($release),
                    new TriggerEventGenerator($release),
                    new TriggerTableGenerator($release),
                    Generators::bind(Generators::bool(), static fn (bool $program): ErisGenerator => $program ? new ProgramStatementGenerator($release, $depth - 1) : Generators::suchThat(static fn ($statement): bool => !$statement instanceof Begin && !$statement instanceof BinlogEvent && !$statement instanceof ExplainConnection, new StatementGenerator($release, $depth - 1))),
                    Generators::oneOf(Generators::constant(null), new TriggerOrderGenerator($release)),
                    Generators::oneOf(Generators::constant(null), new AccountGenerator($release)),
                    Generators::bool(),
                    Generators::constant(null),
                    Generators::constant(null),
                    Generators::oneOf(Generators::constant(null), new DefinitionSourceGenerator($release))
                )
            )
        );
        $this->delegate = Generators::map(
            static function (CreateTrigger $statement) use ($release): CreateTrigger {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof CreateTrigger, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (CreateTrigger $value) use ($release): bool {
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
     * @return GeneratedValueSingle<CreateTrigger>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<CreateTrigger>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
