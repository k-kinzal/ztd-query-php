<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Routine;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\AnalysisContext;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Construction\Publication;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Replication\Log\BinlogEvent;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateProcedure;
use SqlSemantics\Platform\MySql\Statement\Server\Transaction\Begin;
use SqlSemantics\Platform\MySql\Statement\Utility\Explain\ExplainConnection;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Name\AccountGenerator;
use Tests\Generator\MySql\Statement\Routine\Characteristic\CharacteristicGenerator;
use Tests\Generator\MySql\Statement\Routine\Definition\DefinitionSourceGenerator;
use Tests\Generator\MySql\Statement\Routine\Program\ProgramStatementGenerator;
use Tests\Generator\MySql\Statement\StatementGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;
use Tests\Support\Analysis;

/**
 * Generates CreateProcedure values from the generators of its operands.
 *
 * @implements ErisGenerator<CreateProcedure>
 */
final class CreateProcedureGenerator implements ErisGenerator
{
    /** @var ErisGenerator<CreateProcedure> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): CreateProcedure => new CreateProcedure(name: $parts[0], parameters: $parts[1], body: $parts[2], characteristics: $parts[3], definer: $parts[4], ifNotExists: ($bit & 508) !== 0 && $parts[5], statementInformation: $parts[6], inputSource: $parts[7], definitionSource: $parts[8]),
            Generators::tuple(
                new QualifiedNameGenerator($release),
                new ParameterListGenerator($release),
                Generators::bind(Generators::bool(), static fn (bool $program): ErisGenerator => $program ? new ProgramStatementGenerator($release, $depth - 1) : Generators::suchThat(static fn ($statement): bool => !$statement instanceof Begin && !$statement instanceof BinlogEvent && !$statement instanceof ExplainConnection, new StatementGenerator($release, $depth - 1))),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new CharacteristicGenerator($release))),
                Generators::oneOf(Generators::constant(null), new AccountGenerator($release)),
                Generators::bool(),
                Generators::constant(null),
                Generators::constant(null),
                Generators::oneOf(Generators::constant(null), new DefinitionSourceGenerator($release))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (CreateProcedure $value) use ($release): bool {
                $domain = new ReleaseDomain();
                $transition = (new Generations())->transition($value);

                return ($domain->of($value) & $domain->bit($release)) !== 0
                    && (in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true) ? !(new ModernForms())->requires($value) : !(new LegacyForms())->requires($value))
                    && ($transition === 0 || ($transition & ($release === GrammarRelease::MySql5651 ? 1 : 2)) !== 0)
                    && ((new LexicalModes())->requirement($value) & 10) === 0;
            },
            $this->delegate
        );
        $this->delegate = Generators::map(
            static fn (CreateProcedure $statement): CreateProcedure => (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (CreateProcedure $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValue<CreateProcedure>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<CreateProcedure> $element The generated counterexample
     * @return GeneratedValue<CreateProcedure>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
