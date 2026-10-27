<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes AlterUser values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\AlterUser>
 */
final class AlterUserGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\AlterUser> */
    use Composition;

    /**
     * @param ErisGenerator<bool> $ifExists Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Account\User\UserAlteration>> $users Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Option\TlsRequirement|null>|null $tls Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Account\Option\ResourceLimit>>|null $resources Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOption>>|null $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Option\UserComment|null>|null $comment Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $ifExists, ErisGenerator $users, ?ErisGenerator $tls = null, ?ErisGenerator $resources = null, ?ErisGenerator $options = null, ?ErisGenerator $comment = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\AlterUser {
            return new \SqlSemantics\Platform\MySql\Statement\Account\AlterUser(ifExists: $parts[0], users: $parts[1], tls: $parts[2], resources: $parts[3], options: $parts[4], comment: $parts[5], statementInformation: $parts[6], inputSource: $parts[7]);
        }, \Tests\Generator\Operands::tuple(
            $ifExists,
            $users,
            $tls ?? Generators::constant(null),
            $resources ?? Generators::constant([]),
            $options ?? Generators::constant([]),
            $comment ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ));
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\AlterUser>>
     */
    public static function forms(): iterable
    {
        yield 'AlterUser/12' => new AlterUserGenerator(
            ifExists: Generators::constant(false),
            users: \Tests\Generator\Operands::tuple(
                new User\UserAlterationGenerator([new User\UserSpecificationGenerator(
                    user: new User\SessionUserGenerator(),
                    identification: Generators::constant(null),
                    factors: Generators::constant([]),
                    initial: Generators::constant(null),
                    replace: Generators::constant(null),
                    retainCurrent: Generators::constant(false),
                    discardOld: Generators::constant(true)
                )])
            ),
            tls: Generators::constant(null),
            resources: Generators::constant([]),
            options: Generators::constant([]),
            comment: Generators::constant(null)
        );
        yield 'AlterUser/247' => new AlterUserGenerator(
            ifExists: Generators::constant(false),
            users: \Tests\Generator\Operands::tuple(
                new User\UserAlterationGenerator([new User\UserSpecificationGenerator(
                    user: new User\SessionUserGenerator(),
                    identification: new User\IdentificationGenerator(
                        plugin: Generators::constant(null),
                        credential: User\CredentialGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\User\Credential::RandomPassword]),
                        secret: Generators::constant(null)
                    ),
                    factors: Generators::constant([]),
                    initial: Generators::constant(null),
                    replace: Generators::constant(null),
                    retainCurrent: Generators::constant(false),
                    discardOld: Generators::constant(false)
                )])
            ),
            tls: Generators::constant(null),
            resources: Generators::constant([]),
            options: Generators::constant([]),
            comment: Generators::constant(null)
        );
        yield 'AlterUser/250' => new AlterUserGenerator(
            ifExists: Generators::constant(false),
            users: \Tests\Generator\Operands::tuple(
                new User\UserAlterationGenerator([new User\UserSpecificationGenerator(
                    user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    )]),
                    identification: Generators::constant(null),
                    factors: Generators::constant([]),
                    initial: Generators::constant(null),
                    replace: Generators::constant(null),
                    retainCurrent: Generators::constant(false),
                    discardOld: Generators::constant(false)
                )])
            ),
            tls: Generators::constant(null),
            resources: Generators::constant([]),
            options: Generators::constant([]),
            comment: new Option\UserCommentGenerator(
                kind: Option\CommentKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Option\CommentKind::Attribute]),
                text: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                    value: Generators::constant('text'),
                    radix: Generators::constant(null)
                )
            )
        );
        yield 'AlterUser/251' => new AlterUserGenerator(
            ifExists: Generators::constant(false),
            users: \Tests\Generator\Operands::tuple(
                new User\UserAlterationGenerator([new User\UserSpecificationGenerator(
                    user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    )]),
                    identification: Generators::constant(null),
                    factors: Generators::constant([]),
                    initial: Generators::constant(null),
                    replace: Generators::constant(null),
                    retainCurrent: Generators::constant(false),
                    discardOld: Generators::constant(false)
                )])
            ),
            tls: Generators::constant(null),
            resources: Generators::constant([]),
            options: \Tests\Generator\Operands::tuple(
                new Option\AccountOptionGenerator(
                    kind: Option\AccountOptionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOptionKind::AccountUnlock]),
                    number: Generators::constant(null)
                )
            ),
            comment: Generators::constant(null)
        );
        yield 'AlterUser/252' => new AlterUserGenerator(
            ifExists: Generators::constant(false),
            users: \Tests\Generator\Operands::tuple(
                new User\UserAlterationGenerator([new User\UserSpecificationGenerator(
                    user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    )]),
                    identification: Generators::constant(null),
                    factors: Generators::constant([]),
                    initial: Generators::constant(null),
                    replace: Generators::constant(null),
                    retainCurrent: Generators::constant(false),
                    discardOld: Generators::constant(false)
                )])
            ),
            tls: Generators::constant(null),
            resources: \Tests\Generator\Operands::tuple(
                new Option\ResourceLimitGenerator(
                    kind: Option\ResourceKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Option\ResourceKind::QueriesPerHour]),
                    number: new \Tests\Generator\MySql\Statement\Literal\NumeralGenerator(
                        text: Generators::constant('1'),
                        hexadecimal: Generators::constant(false)
                    )
                )
            ),
            options: Generators::constant([]),
            comment: Generators::constant(null)
        );
        yield 'AlterUser/401' => new AlterUserGenerator(
            ifExists: Generators::constant(false),
            users: \Tests\Generator\Operands::tuple(
                new User\UserAlterationGenerator([new User\UserSpecificationGenerator(
                    user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    )]),
                    identification: Generators::constant(null),
                    factors: Generators::constant([]),
                    initial: Generators::constant(null),
                    replace: Generators::constant(null),
                    retainCurrent: Generators::constant(false),
                    discardOld: Generators::constant(false)
                )])
            ),
            tls: new Option\TlsRequirementGenerator(
                kind: Option\TlsKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Option\TlsKind::Specified]),
                conditions: \Tests\Generator\Operands::tuple(
                    new Option\TlsConditionGenerator(
                        attribute: Option\TlsAttributeGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Option\TlsAttribute::Subject]),
                        value: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                            value: Generators::constant('text'),
                            radix: Generators::constant(null)
                        )
                    )
                )
            ),
            resources: Generators::constant([]),
            options: Generators::constant([]),
            comment: Generators::constant(null)
        );
        yield 'AlterUser/405' => new AlterUserGenerator(
            ifExists: Generators::constant(false),
            users: \Tests\Generator\Operands::tuple(
                new User\UserAlterationGenerator([new User\FactorChangeGenerator(
                    user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    )]),
                    action: User\FactorActionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\User\FactorAction::Add]),
                    steps: \Tests\Generator\Operands::tuple(
                        new User\FactorStepGenerator(
                            factor: User\FactorGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\User\Factor::Second]),
                            identification: new User\IdentificationGenerator(
                                plugin: Generators::constant(null),
                                credential: User\CredentialGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\User\Credential::Password]),
                                secret: new \Tests\Generator\MySql\Statement\Literal\TextGenerator(
                                    value: Generators::constant('text'),
                                    radix: Generators::constant(null)
                                )
                            )
                        )
                    )
                )])
            ),
            tls: Generators::constant(null),
            resources: Generators::constant([]),
            options: Generators::constant([]),
            comment: Generators::constant(null)
        );
    }

}
