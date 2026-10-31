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
use SqlSemantics\Platform\MySql\Statement\Routine\AlterRoutine;
use SqlSemantics\Platform\MySql\Statement\Routine\Characteristic\Determinism;
use SqlSemantics\Platform\MySql\Statement\Routine\ProgramKind;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Routine\Characteristic\CharacteristicGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;
use Tests\Support\Analysis;

/**
 * Generates AlterRoutine values from the generators of its operands.
 *
 * @implements ErisGenerator<AlterRoutine>
 */
final class AlterRoutineGenerator implements ErisGenerator
{
    /** @var ErisGenerator<AlterRoutine> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of AlterRoutine from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): AlterRoutine => new AlterRoutine(kind: $parts[0], name: $parts[1], characteristics: array_values(array_filter($parts[2], static fn ($characteristic): bool => !$characteristic instanceof Determinism)), statementInformation: $parts[3], inputSource: $parts[4]),
            Generators::tuple(
                Generators::elements([ProgramKind::Procedure, ProgramKind::Function]),
                new QualifiedNameGenerator($release),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new CharacteristicGenerator($release))),
                Generators::constant(null),
                Generators::constant(null)
            )
        );
        $this->delegate = Generators::map(
            static function (AlterRoutine $statement) use ($release): AlterRoutine {
                $published = (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement);
                Check::invariant($published instanceof AlterRoutine, 'Publication keeps the statement type.');

                return $published;
            },
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (AlterRoutine $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static function (AlterRoutine $value) use ($release): bool {
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
     * @return GeneratedValueSingle<AlterRoutine>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<AlterRoutine>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
