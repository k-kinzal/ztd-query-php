<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\ResourceGroup;

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
use SqlSemantics\Platform\MySql\Statement\Account\ResourceGroup\CreateResourceGroup;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\MySql\Statement\AssignmentOperatorGenerator;
use Tests\Support\Analysis;

/**
 * Generates CreateResourceGroup values from the generators of its operands.
 *
 * @implements ErisGenerator<CreateResourceGroup>
 */
final class CreateResourceGroupGenerator implements ErisGenerator
{
    /** @var ErisGenerator<CreateResourceGroup> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): CreateResourceGroup => new CreateResourceGroup(name: $parts[0], kind: $parts[1], cpus: $parts[2], priority: $parts[3], enabled: $parts[4], statementInformation: null, inputSource: null, cpusCommas: $parts[5] === null ? null : array_slice($parts[5], 0, max(0, count($parts[2]) - 1)), typeOperator: $parts[6], cpusOperator: $parts[7], priorityOperator: $parts[8]),
            Generators::tuple(
                new ResourceGroupNameGenerator($release),
                new ResourceGroupKindGenerator($release),
                Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new CpuRangeGenerator($release))),
                Generators::oneOf(Generators::constant(null), new ThreadPriorityGenerator($release)),
                Generators::oneOf(Generators::constant(null), Generators::bool()),
                Generators::oneOf(Generators::constant(null), Generators::vector(2, Generators::bool())),
                Generators::oneOf(Generators::constant(null), new AssignmentOperatorGenerator($release)),
                Generators::oneOf(Generators::constant(null), new AssignmentOperatorGenerator($release)),
                Generators::oneOf(Generators::constant(null), new AssignmentOperatorGenerator($release))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (CreateResourceGroup $value) use ($release): bool {
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
            static fn (CreateResourceGroup $statement): CreateResourceGroup => (new Publication())->publish(new AnalysisContext(new LanguageProfile($release), [new Name('pbt')]), $statement),
            $this->delegate
        );
        $this->delegate = Generators::suchThat(
            static fn (CreateResourceGroup $statement): bool => Analysis::accepted($statement),
            $this->delegate
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValue<CreateResourceGroup>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<CreateResourceGroup> $element The generated counterexample
     * @return GeneratedValue<CreateResourceGroup>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
