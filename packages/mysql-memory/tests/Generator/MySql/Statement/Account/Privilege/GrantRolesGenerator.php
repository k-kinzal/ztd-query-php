<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Privilege;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Contract\LanguageProfile;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantRoles;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\Role\RefusedGrantRoles;
use SqlSemantics\Platform\MySql\Statement\Name\Account;
use Tests\Generator\MySql\Statement\Account\Privilege\Item\GrantedRoleGenerator;
use Tests\Generator\MySql\Statement\Account\Problem\Role\RefusedGrantRolesGenerator;
use Tests\Generator\MySql\Statement\Name\AccountGenerator;

/**
 * Generates ordered role grants independently of account and role-edge state.
 *
 * @implements ErisGenerator<GrantRoles>
 */
final class GrantRolesGenerator implements ErisGenerator
{
    /**
     * @var ErisGenerator<GrantRoles>
     */
    private ErisGenerator $delegate;

    /**
     * Composes operands with Eris, retaining their relationships during shrinking.
     */
    public function __construct(GrammarRelease $release)
    {
        $profile = new LanguageProfile($release);
        $requests = Generators::bind(Generators::bool(), static fn (bool $admin): ErisGenerator => Generators::bind(
            new GrantedRoleGenerator(),
            static fn (GrantedRole $firstRole): ErisGenerator => Generators::bind(
                new AccountGenerator(),
                static fn (Account $firstUser): ErisGenerator => Generators::bind(
                    Generators::seq(new GrantedRoleGenerator()),
                    static fn (array $roles): ErisGenerator => Generators::map(
                        static fn (array $users): GrantRoles => new GrantRoles([$firstRole, ...$roles], [$firstUser, ...$users], $admin, $profile),
                        Generators::seq(new AccountGenerator())
                    )
                )
            )
        ));
        $this->delegate = Generators::oneOf(
            $requests,
            Generators::bind(new RefusedGrantRolesGenerator(), static fn (RefusedGrantRoles $condition): ErisGenerator => Generators::map(static fn (bool $admin): GrantRoles => new GrantRoles($condition->roles, $condition->users, $admin, $profile), Generators::bool())),
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<GrantRoles>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<GrantRoles>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
}
