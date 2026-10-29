<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
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
     * Generates complete AlterUser values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\AlterUser>|null $operands A constrained form of this concrete result
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

            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql5744) {
                $user = new User\UserSpecificationGenerator(
                    new \Tests\Generator\MySql\Statement\Name\AccountNameGenerator(),
                    new User\IdentificationGenerator(Generators::constant(null), Generators::constant(\SqlSemantics\Platform\MySql\Statement\Account\User\Credential::PasswordHash), new \Tests\Generator\MySql\Statement\Literal\TextGenerator(Generators::string())),
                );
                $forms[] = self::operands(Generators::bool(), Generators::bind(Generators::choose(1, 4), static fn (int $count): ErisGenerator => Generators::vector($count, $user)));
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No AlterUser forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): \SqlSemantics\Platform\MySql\Statement\Account\AlterUser {
            if (!$statement instanceof \SqlSemantics\Platform\MySql\Statement\Account\AlterUser) {
                throw new LogicException('Publishing AlterUser must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<bool> $ifExists Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Account\User\UserAlteration>> $users Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Option\TlsRequirement|null>|null $tls Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Account\Option\ResourceLimit>>|null $resources Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOption>>|null $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Option\UserComment|null>|null $comment Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\AlterUser>
     */
    public static function operands(ErisGenerator $ifExists, ErisGenerator $users, ?ErisGenerator $tls = null, ?ErisGenerator $resources = null, ?ErisGenerator $options = null, ?ErisGenerator $comment = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null): ErisGenerator
    {
        return Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\AlterUser {
            return new \SqlSemantics\Platform\MySql\Statement\Account\AlterUser(ifExists: $parts[0], users: $parts[1], tls: $parts[2], resources: $parts[3], options: $parts[4], comment: $parts[5], statementInformation: $parts[6], inputSource: $parts[7]);
        }, Generators::tuple($ifExists, $users, $tls ?? Generators::constant(null), $resources ?? Generators::constant([]), $options ?? Generators::constant([]), $comment ?? Generators::constant(null), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null)));
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\AlterUser>>
     */
    public static function forms(): iterable
    {
        yield 'AlterUser/12' => AlterUserGenerator::operands(
            ifExists: Generators::constant(false),
            users: Generators::tuple(
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
        yield 'AlterUser/247' => AlterUserGenerator::operands(
            ifExists: Generators::constant(false),
            users: Generators::tuple(
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
        yield 'AlterUser/250' => AlterUserGenerator::operands(
            ifExists: Generators::constant(false),
            users: Generators::tuple(
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
        yield 'AlterUser/251' => AlterUserGenerator::operands(
            ifExists: Generators::constant(false),
            users: Generators::tuple(
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
            options: Generators::tuple(
                new Option\AccountOptionGenerator(
                    kind: Option\AccountOptionKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Option\AccountOptionKind::AccountUnlock]),
                    number: Generators::constant(null)
                )
            ),
            comment: Generators::constant(null)
        );
        yield 'AlterUser/252' => AlterUserGenerator::operands(
            ifExists: Generators::constant(false),
            users: Generators::tuple(
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
            resources: Generators::tuple(
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
        yield 'AlterUser/401' => AlterUserGenerator::operands(
            ifExists: Generators::constant(false),
            users: Generators::tuple(
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
                conditions: Generators::tuple(
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
        yield 'AlterUser/405' => AlterUserGenerator::operands(
            ifExists: Generators::constant(false),
            users: Generators::tuple(
                new User\UserAlterationGenerator([new User\FactorChangeGenerator(
                    user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    )]),
                    action: User\FactorActionGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\User\FactorAction::Add]),
                    steps: Generators::tuple(
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
