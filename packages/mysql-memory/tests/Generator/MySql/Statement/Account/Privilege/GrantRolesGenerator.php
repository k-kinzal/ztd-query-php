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
        $requests = \Tests\Generator\Operands::bind(Generators::bool(), static fn (bool $admin): ErisGenerator => \Tests\Generator\Operands::bind(
            new GrantedRoleGenerator(),
            static fn (GrantedRole $firstRole): ErisGenerator => \Tests\Generator\Operands::bind(
                new AccountGenerator(),
                static fn (Account $firstUser): ErisGenerator => \Tests\Generator\Operands::bind(
                    \Tests\Generator\Operands::seq(new GrantedRoleGenerator()),
                    static fn (array $roles): ErisGenerator => Generators::map(
                        static fn (array $users): GrantRoles => new GrantRoles([$firstRole, ...$roles], [$firstUser, ...$users], $admin, $profile),
                        \Tests\Generator\Operands::seq(new AccountGenerator())
                    )
                )
            )
        ));
        $this->delegate = Generators::oneOf(
            $requests,
            \Tests\Generator\Operands::bind(new RefusedGrantRolesGenerator(), static fn (RefusedGrantRoles $condition): ErisGenerator => Generators::map(static fn (bool $admin): GrantRoles => new GrantRoles($condition->roles, $condition->users, $admin, $profile), Generators::bool())),
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
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<list<GrantedRole>> $roles The generated semantic operand
     * @param ErisGenerator<list<Account>> $users The generated semantic operand
     * @param ErisGenerator<bool> $withAdminOption The generated semantic operand
     * @param ErisGenerator<LanguageProfile> $profile The generated semantic operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Type\Resolved\Charset|null> $client The generated semantic operand
     * @return ErisGenerator<GrantRoles>
     */
    public static function fromOperands(ErisGenerator $roles, ErisGenerator $users, ErisGenerator $withAdminOption, ErisGenerator $profile, ErisGenerator $client): ErisGenerator
    {
        return Generators::map(static fn (array $parts): GrantRoles => new GrantRoles(roles: $parts[0], users: $parts[1], withAdminOption: $parts[2], profile: $parts[3], client: $parts[4]), \Tests\Generator\Operands::tuple($roles, $users, $withAdminOption, $profile, $client));
    }

    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<GrantRoles>>
     */
    public static function forms(): iterable
    {
        yield 'GrantRoles/48' => GrantRolesGenerator::fromOperands(
            roles: \Tests\Generator\Operands::tuple(
                GrantedRoleGenerator::fromOperands(
                    role: \Tests\Generator\MySql\Statement\Name\AccountNameGenerator::fromOperands(
                        user: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('name'),
                            quote: Generators::constant(null)
                        ),
                        host: Generators::constant(null)
                    )
                )
            ),
            users: \Tests\Generator\Operands::tuple(
                new AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                    parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                )])
            ),
            withAdminOption: Generators::constant(false),
            profile: new \Tests\Generator\Semantics\Contract\LanguageProfileGenerator(
                grammar: \Tests\Generator\Semantics\Contract\GrammarReleaseGenerator::selecting([GrammarRelease::MySql847]),
                lexical: new \Tests\Generator\Semantics\Contract\LexicalSettingsGenerator(
                    settings: Generators::constant('')
                ),
                parameters: \Tests\Generator\Semantics\Contract\ParameterStyleGenerator::selecting([\SqlSemantics\Contract\ParameterStyle::Native])
            ),
            client: Generators::constant(null)
        );
    }

}
