<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Query\Into;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Query\Into\IntoVariables;
use Tests\Generator\MySql\Statement\Query\Clause\ProgramVariableGenerator;
use Tests\Generator\MySql\Statement\Variable\UserVariableGenerator;

/**
 * Generates IntoVariables values from the generators of its operands.
 *
 * @implements ErisGenerator<IntoVariables>
 */
final class IntoVariablesGenerator implements ErisGenerator
{
    /** @var ErisGenerator<IntoVariables> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release, int $depth = 3)
    {
        $this->delegate = Generators::map(
            static fn (array $targets): IntoVariables => new IntoVariables(targets: $targets, inputSource: null),
            Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, Generators::bind(Generators::bool(), static fn (bool $user): ErisGenerator => $user ? new UserVariableGenerator($release) : new ProgramVariableGenerator($release))))
        );
        $this->delegate = Generators::suchThat(
            static function (IntoVariables $value) use ($release): bool {
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
     * @return GeneratedValue<IntoVariables>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<IntoVariables> $element The generated counterexample
     * @return GeneratedValue<IntoVariables>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
