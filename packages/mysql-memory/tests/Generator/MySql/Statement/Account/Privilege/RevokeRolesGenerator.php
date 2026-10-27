<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Privilege;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RevokeRoles values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeRoles>
 */
final class RevokeRolesGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeRoles> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $ifExists Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole>> $roles Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Name\Account>> $users Generated constructor operand
     * @param ErisGenerator<bool>|null $ignoreUnknownUser Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $ifExists, ErisGenerator $roles, ErisGenerator $users, ?ErisGenerator $ignoreUnknownUser = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeRoles {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeRoles(ifExists: $parts[0], roles: $parts[1], users: $parts[2], ignoreUnknownUser: $parts[3], statementInformation: $parts[4], inputSource: $parts[5]);
        }, \Tests\Generator\Operands::tuple(
            $ifExists,
            $roles,
            $users,
            $ignoreUnknownUser ?? Generators::constant(false),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokeRoles>>
     */
    public static function forms(): iterable
    {
        yield 'RevokeRoles/357' => new RevokeRolesGenerator(
            ifExists: Generators::constant(false),
            roles: \Tests\Generator\Operands::tuple(
                Item\GrantedRoleGenerator::fromOperands(
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
                new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                    parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                )])
            ),
            ignoreUnknownUser: Generators::constant(true)
        );
    }

}
