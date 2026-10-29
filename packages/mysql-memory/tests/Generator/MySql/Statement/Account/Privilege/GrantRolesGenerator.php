<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Privilege;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use InvalidArgumentException;
use LogicException;
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
     * Generates complete GrantRoles values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<GrantRoles>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            if ($release === GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No GrantRoles forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): GrantRoles {
            if (!$statement instanceof GrantRoles) {
                throw new LogicException('Publishing GrantRoles must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * Composes operands with Eris, retaining their relationships during shrinking.
     * @return ErisGenerator<GrantRoles>
     */
    public static function operands(GrammarRelease $release): ErisGenerator
    {
        $profile = new LanguageProfile($release);
        $requests = Generators::bind(Generators::bool(), static fn (bool $admin): ErisGenerator => Generators::bind(new GrantedRoleGenerator(), static fn (GrantedRole $firstRole): ErisGenerator => Generators::bind(new AccountGenerator(), static fn (Account $firstUser): ErisGenerator => Generators::bind(Generators::seq(new GrantedRoleGenerator()), static fn (array $roles): ErisGenerator => Generators::map(static fn (array $users): GrantRoles => new GrantRoles([$firstRole, ...$roles], [$firstUser, ...$users], $admin, $profile), Generators::seq(new AccountGenerator()))))));
        return Generators::oneOf($requests, Generators::bind(new RefusedGrantRolesGenerator(), static fn (RefusedGrantRoles $condition): ErisGenerator => Generators::map(static fn (bool $admin): GrantRoles => new GrantRoles($condition->roles, $condition->users, $admin, $profile), Generators::bool())));
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
        return Generators::map(static fn (array $parts): GrantRoles => new GrantRoles(roles: $parts[0], users: $parts[1], withAdminOption: $parts[2], profile: $parts[3], client: $parts[4]), Generators::tuple($roles, $users, $withAdminOption, $profile, $client));
    }

    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<GrantRoles>>
     */
    public static function forms(): iterable
    {
        yield 'GrantRoles/48' => GrantRolesGenerator::fromOperands(
            roles: Generators::tuple(
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
            users: Generators::tuple(
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
