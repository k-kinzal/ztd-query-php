<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Variable;

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
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Statement\Identifier\Name;

/**
 * Generates SystemVariable values from the generators of its operands.
 *
 * @implements ErisGenerator<SystemVariable>
 */
final class SystemVariableGenerator implements ErisGenerator
{
    /** @var ErisGenerator<SystemVariable> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): SystemVariable => new SystemVariable(name: $parts[0], scope: in_array($parts[1], [VariableScope::Global, VariableScope::Session, VariableScope::Local], true) ? $parts[1] : null, instance: $parts[2], scalarInformation: null, inputSource: null),
            Generators::tuple(
                Generators::map(static fn (string $name): Name => new Name($name), Generators::elements(array_keys(SystemVariables::of($release)->definitions))),
                Generators::elements([null, VariableScope::Global, VariableScope::Session, VariableScope::Local]),
                Generators::oneOf(Generators::constant(null), Generators::map(static fn (string $name): Name => new Name($name), Generators::elements(array_keys(SystemVariables::of($release)->definitions))))
            )
        );
        $this->delegate = Generators::suchThat(
            static function (SystemVariable $value) use ($release): bool {
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
     * @return GeneratedValue<SystemVariable>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<SystemVariable> $element The generated counterexample
     * @return GeneratedValue<SystemVariable>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
