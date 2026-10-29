<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Privilege;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use InvalidArgumentException;
use LogicException;
use SqlSemantics\Contract\GrammarRelease;
use SqlSemantics\Platform\MySql\Statement as M;
use SqlSemantics\Statement\Identifier\Name;
use SqlSemantics\Statement\Identifier\QualifiedName;
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
     * Generates complete GrantPrivileges values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<M\Account\Privilege\GrantPrivileges>|null $operands A constrained form of this concrete result
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
            foreach (self::additionalForms() as [$grammar, $form]) {
                if ($grammar === $release) {
                    $forms[] = $form;
                }
            }

            if ($release === GrammarRelease::MySql847) {
                $forms[] = Level\AbsentGrantTableGenerator::statements();
                $forms[] = Generators::map(static fn (array $parts): M\Statement => $parts[1], \Tests\Generator\MySql\Statement\Account\Problem\UnknownGrantColumnGenerator::statements());
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No GrantPrivileges forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): M\Account\Privilege\GrantPrivileges {
            if (!$statement instanceof M\Account\Privilege\GrantPrivileges) {
                throw new LogicException('Publishing GrantPrivileges must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * @param ErisGenerator<list<M\Account\Privilege\Item\Privilege>> $privileges Generated constructor operand
     * @param ErisGenerator<M\Account\Privilege\Level\ObjectKind> $kind Generated constructor operand
     * @param ErisGenerator<M\Account\Privilege\Level\PrivilegeLevel> $level Generated constructor operand
     * @param ErisGenerator<list<M\Account\User\Grantee|M\Account\User\UserSpecification>> $grantees Generated constructor operand
     * @param ErisGenerator<M\Account\Option\TlsRequirement|null>|null $tls Generated constructor operand
     * @param ErisGenerator<list<M\Account\Privilege\WithOption>>|null $options Generated constructor operand
     * @param ErisGenerator<M\Account\Privilege\GrantAs|null>|null $as Generated constructor operand
     * @param ErisGenerator<M\StatementSemantics|null>|null $statementInformation Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     * @param ErisGenerator<bool>|null $tableKeyword The optional TABLE keyword
     * @return ErisGenerator<M\Account\Privilege\GrantPrivileges>
     */
    public static function operands(ErisGenerator $privileges, ErisGenerator $kind, ErisGenerator $level, ErisGenerator $grantees, ?ErisGenerator $tls = null, ?ErisGenerator $options = null, ?ErisGenerator $as = null, ?ErisGenerator $statementInformation = null, ?ErisGenerator $inputSource = null, ?ErisGenerator $tableKeyword = null): ErisGenerator
    {
        return new PrivilegeChangeGenerator([Generators::map(static function (array $parts): M\Account\Privilege\GrantPrivileges {
            return new M\Account\Privilege\GrantPrivileges(privileges: $parts[0], kind: $parts[1], level: $parts[2], grantees: $parts[3], tls: $parts[4], options: $parts[5], as: $parts[6], statementInformation: $parts[7], inputSource: $parts[8], tableKeyword: $parts[9] && $parts[1] === M\Account\Privilege\Level\ObjectKind::Table);
        }, Generators::tuple($privileges, $kind, $level, $grantees, $tls ?? Generators::constant(null), $options ?? Generators::constant([]), $as ?? Generators::constant(null), $statementInformation ?? Generators::constant(null), $inputSource ?? Generators::constant(null), $tableKeyword ?? Generators::bool()))]);
    }
    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<M\Account\Privilege\GrantPrivileges>>
     */
    public static function forms(): iterable
    {
        yield 'GrantPrivileges/399' => GrantPrivilegesGenerator::operands(
            privileges: Generators::tuple(
                new Item\PrivilegeGenerator([new Item\StaticPrivilegeGenerator(
                    kind: Item\PrivilegeKindGenerator::selecting([M\Account\Privilege\Item\PrivilegeKind::Delete, M\Account\Privilege\Item\PrivilegeKind::ShowDatabases]),
                    columns: Generators::constant([])
                )])
            ),
            kind: Level\ObjectKindGenerator::selecting([M\Account\Privilege\Level\ObjectKind::Table]),
            level: new Level\PrivilegeLevelGenerator([new Level\CurrentDatabaseLevelGenerator()]),
            grantees: Generators::tuple(
                new \Tests\Generator\MySql\Statement\Account\User\GranteeGenerator(
                    user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([M\Expression\OptionalWords::Omitted])
                    )]),
                    identification: Generators::constant(null)
                )
            ),
            tls: Generators::constant(null),
            options: Generators::constant([]),
            as: Generators::constant(null)
        );
        yield 'GrantPrivileges/402' => GrantPrivilegesGenerator::operands(
            privileges: Generators::tuple(
                new Item\PrivilegeGenerator([new Item\StaticPrivilegeGenerator(
                    kind: Item\PrivilegeKindGenerator::selecting([M\Account\Privilege\Item\PrivilegeKind::Delete]),
                    columns: Generators::constant([])
                )])
            ),
            kind: Level\ObjectKindGenerator::selecting([M\Account\Privilege\Level\ObjectKind::Table]),
            level: new Level\PrivilegeLevelGenerator([new Level\DatabaseLevelGenerator(
                database: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                    value: Generators::map(static fn (int $value): string => 'name_' . $value, Generators::choose(0, 8)),
                    quote: Generators::constant(null)
                )
            )]),
            grantees: Generators::tuple(
                new \Tests\Generator\MySql\Statement\Account\User\GranteeGenerator(
                    user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([M\Expression\OptionalWords::Omitted])
                    )]),
                    identification: Generators::constant(null)
                )
            ),
            tls: Generators::constant(null),
            options: Generators::constant([]),
            as: Generators::constant(null)
        );
        yield 'GrantPrivileges/403' => GrantPrivilegesGenerator::operands(
            privileges: Generators::tuple(
                new Item\PrivilegeGenerator([new Item\StaticPrivilegeGenerator(
                    kind: Item\PrivilegeKindGenerator::selecting([M\Account\Privilege\Item\PrivilegeKind::Delete]),
                    columns: Generators::constant([])
                )])
            ),
            kind: Level\ObjectKindGenerator::selecting([M\Account\Privilege\Level\ObjectKind::Table]),
            level: new Level\PrivilegeLevelGenerator([new Level\GlobalLevelGenerator()]),
            grantees: Generators::tuple(
                new \Tests\Generator\MySql\Statement\Account\User\GranteeGenerator(
                    user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([M\Expression\OptionalWords::Omitted])
                    )]),
                    identification: Generators::constant(null)
                )
            ),
            tls: Generators::constant(null),
            options: Generators::constant([]),
            as: Generators::constant(null)
        );
        yield 'GrantPrivileges/404' => GrantPrivilegesGenerator::operands(
            privileges: Generators::tuple(
                new Item\PrivilegeGenerator([new Item\StaticPrivilegeGenerator(
                    kind: Item\PrivilegeKindGenerator::selecting([M\Account\Privilege\Item\PrivilegeKind::Delete]),
                    columns: Generators::constant([])
                )])
            ),
            kind: Level\ObjectKindGenerator::selecting([M\Account\Privilege\Level\ObjectKind::Table]),
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
            grantees: Generators::tuple(
                new \Tests\Generator\MySql\Statement\Account\User\GranteeGenerator(
                    user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([M\Expression\OptionalWords::Omitted])
                    )]),
                    identification: Generators::constant(null)
                )
            ),
            tls: Generators::constant(null),
            options: Generators::constant([]),
            as: Generators::constant(null)
        );
        yield 'GrantPrivileges/406' => GrantPrivilegesGenerator::operands(
            privileges: Generators::tuple(
                new Item\PrivilegeGenerator([new Item\StaticPrivilegeGenerator(
                    kind: Item\PrivilegeKindGenerator::selecting([M\Account\Privilege\Item\PrivilegeKind::Delete]),
                    columns: Generators::constant([])
                )])
            ),
            kind: Level\ObjectKindGenerator::selecting([M\Account\Privilege\Level\ObjectKind::Table]),
            level: new Level\PrivilegeLevelGenerator([new Level\CurrentDatabaseLevelGenerator()]),
            grantees: Generators::tuple(
                new \Tests\Generator\MySql\Statement\Account\User\GranteeGenerator(
                    user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([M\Expression\OptionalWords::Omitted])
                    )]),
                    identification: Generators::constant(null)
                )
            ),
            tls: Generators::constant(null),
            options: Generators::tuple(
                new WithOptionGenerator([new GrantOptionRightGenerator()])
            ),
            as: Generators::constant(null)
        );
    }

    /**
     * Composes further SQL roles and version-specific forms from typed operands.
     *
     * @return iterable<string, array{GrammarRelease, ErisGenerator<M\Account\Privilege\GrantPrivileges>}>
     */
    public static function additionalForms(): iterable
    {
        yield 'GrantPrivileges/additional/75' => [GrammarRelease::MySql847, GrantPrivilegesGenerator::operands(
            privileges: Generators::tuple(
                new Item\PrivilegeGenerator([new Item\DynamicPrivilegeGenerator(
                    name: \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                        value: Generators::constant('XA_RECOVER_ADMIN'),
                        quote: Generators::constant(null)
                    ),
                    columns: Generators::tuple(
                        \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(
                            value: Generators::constant('c'),
                            quote: Generators::constant(null)
                        )
                    )
                )])
            ),
            kind: Level\ObjectKindGenerator::selecting([M\Account\Privilege\Level\ObjectKind::Table]),
            level: new Level\PrivilegeLevelGenerator([new Level\GlobalLevelGenerator()]),
            grantees: Generators::tuple(
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

    /**
     * Composes a complete grant, its stored definition and its dependent call.
     *
     * @return ErisGenerator<array{M\Statement, M\Statement, M\Statement, int, bool, list<int>}>
     */
    public static function conditions(GrammarRelease $release): ErisGenerator
    {
        $legacy = in_array($release, [GrammarRelease::MySql5651, GrammarRelease::MySql5744], true);
        return Generators::map(static function (array $parts) use ($release, $legacy): array {
            [$form, $name] = $parts;
            $privileges = match ($form) {
                0, 4 => [new M\Account\Privilege\Item\AllPrivileges()],
                1 => [new M\Account\Privilege\Item\StaticPrivilege(M\Account\Privilege\Item\PrivilegeKind::Select, [$name])],
                5 => [new M\Account\Privilege\Item\StaticPrivilege(M\Account\Privilege\Item\PrivilegeKind::Super)],
                2 => [new M\Account\Privilege\Item\StaticPrivilege(M\Account\Privilege\Item\PrivilegeKind::Select)],
                default => [new M\Account\Privilege\Item\DynamicPrivilege($name)],
            };
            $kind = in_array($form, [0, 2, 5], true) ? M\Account\Privilege\Level\ObjectKind::Procedure : M\Account\Privilege\Level\ObjectKind::Table;
            $level = match ($form) {
                0, 5 => new M\Account\Privilege\Level\GlobalLevel(),
                2 => new M\Account\Privilege\Level\ObjectLevel(new QualifiedName($name, new Name('pbt'))),
                default => new M\Account\Privilege\Level\DatabaseLevel(new Name('pbt')),
            };
            $grant = new M\Account\Privilege\GrantPrivileges($privileges, $kind, $level, [new M\Account\User\Grantee(new M\Name\CurrentUser())], as: $form === 4 ? new M\Account\Privilege\GrantAs(new M\Name\CurrentUser()) : null);
            $procedure = new QualifiedName(new Name('grant_' . $name->value));
            $definition = new M\Routine\CreateProcedure($procedure, new M\Routine\ParameterList([]), $grant);
            $call = new M\Dml\ProcedureCall($procedure);
            $context = new \SqlSemantics\Contract\AnalysisContext(new \SqlSemantics\Contract\LanguageProfile($release), [new Name('pbt')]);
            $publication = new \SqlSemantics\Platform\MySql\Construction\Publication();
            return [$publication->publish($context, $grant), $publication->publish($context, $definition), $publication->publish($context, $call), match ($form) {
                3 => 3619, 4 => 3835, default => 1144
            }, $legacy && $form === 1, $form === 5 ? [1287] : []];
        }, Generators::tuple(Generators::choose(0, $legacy ? 2 : 5), \Tests\Generator\Semantics\Statement\Identifier\NameGenerator::fromOperands(Generators::map(static fn (int $value): string => 'operand_' . $value, Generators::choose(0, 1000)), Generators::constant(null))));
    }

}
