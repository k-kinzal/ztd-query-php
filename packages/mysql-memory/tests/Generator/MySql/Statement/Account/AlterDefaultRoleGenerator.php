<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
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
     * @param ErisGenerator<bool> $ifExists Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Name\Account> $user Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\User\RoleSelection> $roles Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $ifExists, ErisGenerator $user, ErisGenerator $roles, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\AlterDefaultRole {
            return new \SqlSemantics\Platform\MySql\Statement\Account\AlterDefaultRole(ifExists: $parts[0], user: $parts[1], roles: $parts[2], statementInformation: $parts[3], inputSource: $parts[4]);
        }, \Tests\Generator\Operands::tuple(
            $ifExists,
            $user,
            $roles,
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\AlterDefaultRole>>
     */
    public static function forms(): iterable
    {
        yield 'AlterDefaultRole/248' => new AlterDefaultRoleGenerator(
            ifExists: Generators::constant(false),
            user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
            )]),
            roles: new User\RoleSelectionGenerator(
                set: User\RoleSetGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\User\RoleSet::All]),
                roles: Generators::constant([])
            )
        );
    }

}
