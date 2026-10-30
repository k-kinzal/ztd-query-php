<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use Tests\Generator\Composition;

/**
 * Composes AlterDefaultRole values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\AlterDefaultRole>
 */
final class AlterDefaultRoleGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\AlterDefaultRole> */
    use Composition;

    /**
     * Generates complete AlterDefaultRole values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\AlterDefaultRole>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = [];
            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No AlterDefaultRole forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Account\AlterDefaultRole {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\AlterDefaultRole) {
                throw new LogicException('Publishing AlterDefaultRole must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<bool> $ifExists Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\Account> $user Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\User\RoleSelection> $roles Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\AlterDefaultRole>
     */
    public static function operands(ErisGenerator $ifExists, ErisGenerator $user, ErisGenerator $roles, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\AlterDefaultRole {
            return new \SqlSemantics\Platform\MySql\Statement\Account\AlterDefaultRole(ifExists: $parts[0], user: $parts[1], roles: $parts[2], statementInformation: $parts[3], inputSource: $parts[4]);
        }, Generators::tuple($ifExists, $user, $roles, $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\AlterDefaultRole>>
     */
    public static function forms(): iterable
    {
        yield 'target and role selection' => self::operands(Generators::bool(), new \Tests\Generator\MySql\Statement\Name\AccountGenerator(), User\RoleSelectionGenerator::defaultRoles());
    }


    /**
     * Generates the user, roles, grant and default selection as one dependent input.
     * Eris shrinking rebuilds the same dependency graph before execution.
     *
     * @return ErisGenerator<array{\SqlSemantics\Platform\MySql\Statement\Account\CreateUser, \SqlSemantics\Platform\MySql\Statement\Account\CreateRole, \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantRoles, \SqlSemantics\Platform\MySql\Statement\Account\AlterDefaultRole}>
     */
    public static function withPrerequisites(\SqlSemantics\Contract\GrammarRelease $release): ErisGenerator
    {
        $users = new \Tests\Generator\MySql\Statement\Name\AccountNameGenerator(Generators::map(static fn (int $number): string => 'user_' . $number, Generators::choose(0, 999)), Generators::constant(null));
        $roles = self::roleNames();
        return Generators::bind(Generators::tuple($users, $roles), static function (array $parts) use ($release): ErisGenerator {
            [$user, $roles] = $parts;
            $profile = Generators::constant(new \SqlSemantics\Contract\LanguageProfile($release));
            $granted = Generators::tuple(...array_map(static fn (\SqlSemantics\Platform\MySql\Statement\Name\AccountName $role): ErisGenerator => Privilege\Item\GrantedRoleGenerator::fromOperands(Generators::constant($role)), $roles));
            return Generators::tuple(
                new CreateUserGenerator($release, CreateUserGenerator::operands(Generators::bool(), Generators::tuple(new User\UserSpecificationGenerator(Generators::constant($user))))),
                new CreateRoleGenerator($release, CreateRoleGenerator::fromOperands(Generators::bool(), Generators::constant($roles), $profile, Generators::constant(null))),
                new Privilege\GrantRolesGenerator($release, Privilege\GrantRolesGenerator::fromOperands($granted, Generators::constant([$user]), Generators::bool(), $profile, Generators::constant(null))),
                new self($release, self::operands(Generators::bool(), Generators::constant($user), User\RoleSelectionGenerator::defaultRoles(Generators::constant($roles))))
            );
        });
    }

    /**
     * Generates distinct role identities with a mandatory first role.
     *
     * @return ErisGenerator<non-empty-list<\SqlSemantics\Platform\MySql\Statement\Name\AccountName>>
     */
    public static function roleNames(): ErisGenerator
    {
        $first = new \Tests\Generator\MySql\Statement\Name\AccountNameGenerator(Generators::map(static fn (int $number): string => 'role_0_' . $number, Generators::choose(0, 999)), Generators::constant(null));
        $remaining = Generators::bind(Generators::choose(0, 3), static fn (int $count): ErisGenerator => Generators::tuple(...array_map(static fn (int $index): \Tests\Generator\MySql\Statement\Name\AccountNameGenerator => new \Tests\Generator\MySql\Statement\Name\AccountNameGenerator(Generators::map(static fn (int $number): string => 'role_' . ($index + 1) . '_' . $number, Generators::choose(0, 999)), Generators::constant(null)), array_keys(array_fill(0, $count, true)))));
        return Generators::bind($first, static fn (\SqlSemantics\Platform\MySql\Statement\Name\AccountName $role): ErisGenerator => Generators::map(static fn (array $roles): array => self::prependRole($role, $roles), $remaining));
    }

    /**
     * @param list<\SqlSemantics\Platform\MySql\Statement\Name\AccountName> $remaining Additional roles generated under distinct prefixes
     * @return non-empty-list<\SqlSemantics\Platform\MySql\Statement\Name\AccountName>
     */
    public static function prependRole(\SqlSemantics\Platform\MySql\Statement\Name\AccountName $first, array $remaining): array
    {
        return [$first, ...$remaining];
    }
}
