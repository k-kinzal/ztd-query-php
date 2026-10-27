<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Privilege;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes GrantPrivileges values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges>
 */
final class GrantPrivilegesGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges> */
    use Composition;

    /**
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\Privilege>> $privileges Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind> $kind Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\PrivilegeLevel> $level Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Account\User\Grantee|\SqlSemantics\Platform\MySql\Statement\Account\User\UserSpecification>> $grantees Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Option\TlsRequirement|null>|null $tls Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\WithOption>>|null $options Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantAs|null>|null $as Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $privileges, ErisGenerator $kind, ErisGenerator $level, ErisGenerator $grantees, ?ErisGenerator $tls = null, ?ErisGenerator $options = null, ?ErisGenerator $as = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = new PrivilegeChangeGenerator([Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges {
            return new \SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges(privileges: $parts[0], kind: $parts[1], level: $parts[2], grantees: $parts[3], tls: $parts[4], options: $parts[5], as: $parts[6], statementInformation: $parts[7], inputSource: $parts[8]);
        }, \Tests\Generator\Operands::tuple(
            $privileges,
            $kind,
            $level,
            $grantees,
            $tls ?? Generators::constant(null),
            $options ?? Generators::constant([]),
            $as ?? Generators::constant(null),
            $statementInformation ?? Generators::constant(null),
            $inputSource ?? Generators::constant(null)
        ))]);
    }
    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges>>
     */
    public static function forms(): iterable
    {
        yield 'GrantPrivileges/399' => new GrantPrivilegesGenerator(
            privileges: \Tests\Generator\Operands::tuple(
                new Item\PrivilegeGenerator([new Item\StaticPrivilegeGenerator(
                    kind: Item\PrivilegeKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind::Delete]),
                    columns: Generators::constant([])
                )])
            ),
            kind: Level\ObjectKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table]),
            level: new Level\PrivilegeLevelGenerator([new Level\CurrentDatabaseLevelGenerator()]),
            grantees: \Tests\Generator\Operands::tuple(
                new \Tests\Generator\MySql\Statement\Account\User\GranteeGenerator(
                    user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    )]),
                    identification: Generators::constant(null)
                )
            ),
            tls: Generators::constant(null),
            options: Generators::constant([]),
            as: Generators::constant(null)
        );
        yield 'GrantPrivileges/402' => new GrantPrivilegesGenerator(
            privileges: \Tests\Generator\Operands::tuple(
                new Item\PrivilegeGenerator([new Item\StaticPrivilegeGenerator(
                    kind: Item\PrivilegeKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind::Delete]),
                    columns: Generators::constant([])
                )])
            ),
            kind: Level\ObjectKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table]),
            level: new Level\PrivilegeLevelGenerator([new Level\DatabaseLevelGenerator(
                database: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                )
            )]),
            grantees: \Tests\Generator\Operands::tuple(
                new \Tests\Generator\MySql\Statement\Account\User\GranteeGenerator(
                    user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    )]),
                    identification: Generators::constant(null)
                )
            ),
            tls: Generators::constant(null),
            options: Generators::constant([]),
            as: Generators::constant(null)
        );
        yield 'GrantPrivileges/403' => new GrantPrivilegesGenerator(
            privileges: \Tests\Generator\Operands::tuple(
                new Item\PrivilegeGenerator([new Item\StaticPrivilegeGenerator(
                    kind: Item\PrivilegeKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind::Delete]),
                    columns: Generators::constant([])
                )])
            ),
            kind: Level\ObjectKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table]),
            level: new Level\PrivilegeLevelGenerator([new Level\GlobalLevelGenerator()]),
            grantees: \Tests\Generator\Operands::tuple(
                new \Tests\Generator\MySql\Statement\Account\User\GranteeGenerator(
                    user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    )]),
                    identification: Generators::constant(null)
                )
            ),
            tls: Generators::constant(null),
            options: Generators::constant([]),
            as: Generators::constant(null)
        );
        yield 'GrantPrivileges/404' => new GrantPrivilegesGenerator(
            privileges: \Tests\Generator\Operands::tuple(
                new Item\PrivilegeGenerator([new Item\StaticPrivilegeGenerator(
                    kind: Item\PrivilegeKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind::Delete]),
                    columns: Generators::constant([])
                )])
            ),
            kind: Level\ObjectKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table]),
            level: new Level\PrivilegeLevelGenerator([new Level\ObjectLevelGenerator(
                name: new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                        quote: Generators::constant(null)
                    ),
                    schema: Generators::constant(null),
                    catalog: Generators::constant(null)
                )
            )]),
            grantees: \Tests\Generator\Operands::tuple(
                new \Tests\Generator\MySql\Statement\Account\User\GranteeGenerator(
                    user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    )]),
                    identification: Generators::constant(null)
                )
            ),
            tls: Generators::constant(null),
            options: Generators::constant([]),
            as: Generators::constant(null)
        );
        yield 'GrantPrivileges/406' => new GrantPrivilegesGenerator(
            privileges: \Tests\Generator\Operands::tuple(
                new Item\PrivilegeGenerator([new Item\StaticPrivilegeGenerator(
                    kind: Item\PrivilegeKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\PrivilegeKind::Delete]),
                    columns: Generators::constant([])
                )])
            ),
            kind: Level\ObjectKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table]),
            level: new Level\PrivilegeLevelGenerator([new Level\CurrentDatabaseLevelGenerator()]),
            grantees: \Tests\Generator\Operands::tuple(
                new \Tests\Generator\MySql\Statement\Account\User\GranteeGenerator(
                    user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Expression\OptionalWords::Omitted])
                    )]),
                    identification: Generators::constant(null)
                )
            ),
            tls: Generators::constant(null),
            options: \Tests\Generator\Operands::tuple(
                new WithOptionGenerator([new GrantOptionRightGenerator()])
            ),
            as: Generators::constant(null)
        );
    }

    /**
     * Composes further SQL roles and version-specific forms from typed operands.
     *
     * @return iterable<string, array{\SqlSemantics\Contract\GrammarRelease, ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\Privilege\GrantPrivileges>}>
     */
    public static function additionalForms(): iterable
    {
        yield 'GrantPrivileges/additional/75' => [\SqlSemantics\Contract\GrammarRelease::MySql847, new GrantPrivilegesGenerator(
            privileges: \Tests\Generator\Operands::tuple(
                new Item\PrivilegeGenerator([new Item\DynamicPrivilegeGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::constant('XA_RECOVER_ADMIN'),
                        quote: Generators::constant(null)
                    ),
                    columns: \Tests\Generator\Operands::tuple(
                        \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('c'),
                            quote: Generators::constant(null)
                        )
                    )
                )])
            ),
            kind: Level\ObjectKindGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\Privilege\Level\ObjectKind::Table]),
            level: new Level\PrivilegeLevelGenerator([new Level\GlobalLevelGenerator()]),
            grantees: \Tests\Generator\Operands::tuple(
                new \Tests\Generator\MySql\Statement\Account\User\GranteeGenerator(
                    user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\AccountNameGenerator::fromOperands(
                        user: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('u'),
                            quote: Generators::constant(null)
                        ),
                        host: Generators::constant(null)
                    )]),
                    identification: Generators::constant(null)
                )
            ),
            tls: Generators::constant(null),
            options: Generators::constant([]),
            as: Generators::constant(null)
        )];
    }

}
