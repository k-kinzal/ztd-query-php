<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Variable;

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
use SqlSemantics\Platform\MySql\Statement\Variable\Catalog\SystemVariables;
use SqlSemantics\Platform\MySql\Statement\Variable\SystemTarget;
use SqlSemantics\Platform\MySql\Statement\Variable\VariableScope;
use SqlSemantics\Statement\Identifier\Name;
use Tests\Generator\Semantics\Statement\Identifier\NameGenerator;

/**
 * Generates SystemTarget values from the generators of its operands.
 *
 * @implements ErisGenerator<SystemTarget>
 */
final class SystemTargetGenerator implements ErisGenerator
{
    /** @var ErisGenerator<SystemTarget> */
    private ErisGenerator $delegate;

    /**
     * Composes the generator of SystemTarget from the generators of its components.
     *
     * @param GrammarRelease $release The release whose forms the values keep
     */
    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): SystemTarget => new SystemTarget(name: $parts[0], scope: $parts[1], instance: in_array(strtolower($parts[0]->value), \SqlSemantics\Platform\MySql\Statement\Variable\SystemVariable::KEY_CACHE, true) ? $parts[2] : null, scalarInformation: $parts[3], inputSource: $parts[4]),
            Generators::suchThat(
                static function (array $parts) use ($release): bool {
                    $definition = SystemVariables::of($release)->find($parts[0]->value);
                    if ($definition === null || $definition->writability === \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Writability::ReadOnly) {
                        return false;
                    }
                    if ($parts[1] === null) {
                        return true;
                    }

                    return in_array($parts[1], [VariableScope::Session, VariableScope::Local], true)
                        ? $definition->reach->session() && $definition->writability !== \SqlSemantics\Platform\MySql\Statement\Variable\Catalog\Writability::GlobalOnly
                        : $definition->reach->global();
                },
                Generators::tuple(
                    Generators::map(static fn (string $name): Name => new Name($name), Generators::elements(array_keys(SystemVariables::of($release)->definitions))),
                    Generators::oneOf(Generators::constant(null), new VariableScopeGenerator($release)),
                    Generators::oneOf(Generators::constant(null), new NameGenerator($release)),
                    Generators::constant(null),
                    Generators::constant(null)
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (SystemTarget $value) use ($release): bool {
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
     * @return GeneratedValueSingle<SystemTarget>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<SystemTarget>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
