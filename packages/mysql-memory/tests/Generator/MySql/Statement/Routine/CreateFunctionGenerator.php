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
use SqlSemantics\Platform\MySql\Rules\Routine\Declaration\DeclaredCollation;
use SqlSemantics\Platform\MySql\Statement\Name\CollationName;
use SqlSemantics\Platform\MySql\Statement\Routine\CreateFunction;
use SqlSemantics\Platform\MySql\Statement\Routine\Parameter;
use SqlSemantics\Platform\MySql\Statement\Routine\ParameterList;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\Name\AccountGenerator;
use Tests\Generator\MySql\Statement\Name\CollationNameGenerator;
use Tests\Generator\MySql\Statement\Routine\Definition\DefinitionSourceGenerator;
use Tests\Generator\MySql\Statement\Routine\Definition\FunctionImplementationGenerator;
use Tests\Generator\MySql\Statement\Type\DeclaredNameGenerator;
use Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator;
use Tests\Support\Analysis;

/**
 * Generates CreateFunction values from the generators of its operands.
 *
 * @implements ErisGenerator<CreateFunction>
 */
final class CreateFunctionGenerator implements ErisGenerator
{
    /** @var ErisGenerator<CreateFunction> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $bit = (new ReleaseDomain())->bit($release);
        $this->delegate = Generators::map(
            static fn (array $parts): CreateFunction => new CreateFunction(name: $parts[0], parameters: $parts[1], returns: $parts[2], implementation: $parts[3], collation: (static fn (?CollationName $collation): ?CollationName => $collation === null || ($collation->name === null && ($bit & 3) === 0) ? null : $collation)((new DeclaredCollation())->problem($parts[2], $parts[4]) === null ? $parts[4] : null), definer: $parts[5], ifNotExists: ($bit & 508) !== 0 && $parts[6], statementInformation: $parts[7], inputSource: $parts[8], definitionSource: $parts[9]),
            Generators::tuple(
                new QualifiedNameGenerator($release),
                Generators::suchThat(static fn (ParameterList $parameters): bool => array_filter($parameters->parameters, static fn (Parameter $parameter): bool => $parameter->mode !== null) === [], new ParameterListGenerator($release)),
                new DeclaredNameGenerator($release),
                new FunctionImplementationGenerator($release, $depth - 1),
                Generators::oneOf(Generators::constant(null), new CollationNameGenerator($release)),
                Generators::oneOf(Generators::constant(null), new AccountGenerator($release)),
                Generators::bool(),
                Generators::constant(null),
                Generators::constant(null),
                Generators::oneOf(Generators::constant(null), new DefinitionSourceGenerator($release))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (CreateFunction $value) use ($release): bool {
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
            static fn (CreateFunction $statement): CreateFunction => (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (CreateFunction $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValue<CreateFunction>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<CreateFunction> $element The generated counterexample
     * @return GeneratedValue<CreateFunction>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
