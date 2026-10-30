<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Problem\Role;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Rules\Account\RoleDefinitions;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\Generations;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LegacyForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\LexicalModes;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ModernForms;
use SqlSemantics\Platform\MySql\Rules\Query\Structure\ReleaseDomain;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\Role\RefusedCreateRole;
use Tests\Generator\MySql\Statement\Name\AccountNameGenerator;

/**
 * Generates RefusedCreateRole values from the generators of its operands.
 *
 * @implements ErisGenerator<RefusedCreateRole>
 */
final class RefusedCreateRoleGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RefusedCreateRole> */
    private ErisGenerator $delegate;

    public function __construct(GrammarRelease $release)
    {
        $this->delegate = Generators::map(
            static fn (array $parts): RefusedCreateRole => new RefusedCreateRole(ifNotExists: $parts[0], roles: $parts[1]),
            Generators::suchThat(
                static fn (array $parts): bool => (new RoleDefinitions())->problem(($parts[0]), ($parts[1])) !== null,
                Generators::tuple(
                    Generators::bool(),
                    Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => $count === 0 ? Generators::constant([]) : Generators::vector($count, new AccountNameGenerator($release)))
                )
            )
        );
        $this->delegate = Generators::suchThat(
            static function (RefusedCreateRole $value) use ($release): bool {
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
     * @return GeneratedValue<RefusedCreateRole>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValue
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<RefusedCreateRole> $element The generated counterexample
     * @return GeneratedValue<RefusedCreateRole>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
