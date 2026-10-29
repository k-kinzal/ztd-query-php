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
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\RevokePrivileges;
use SqlSemantics\Platform\MySql\Statement as M;
use Tests\Generator\MySql\Statement\Name\AccountNameGenerator;

/**
 * Generates routine wildcard revocations with independently generated recipients.
 *
 * @implements ErisGenerator<RevokePrivileges>
 */
final class RevokePrivilegesGenerator implements ErisGenerator
{
    /** @var ErisGenerator<RevokePrivileges> */
    private ErisGenerator $delegate;

    /**
     * Generates complete RevokePrivileges values for the post-analysis execution boundary.
     *
     * @param ErisGenerator<RevokePrivileges>|null $operands A constrained form of this concrete result
     * @param ErisGenerator<\SqlSemantics\Contract\AnalysisContext>|null $contexts Related declarations and session settings
     * @throws InvalidArgumentException When no form is available for the release.
     */
    public function __construct(\SqlSemantics\Contract\GrammarRelease $release, ?ErisGenerator $operands = null, ?ErisGenerator $contexts = null)
    {
        if ($operands === null) {
            $forms = $release->database() === 'mysql' ? [self::columnTargets()] : [];
            if ($release === \SqlSemantics\Contract\GrammarRelease::MySql847) {
                foreach (self::forms() as $form) {
                    $forms[] = $form;
                }
            }

            if ($forms === []) {
                throw new InvalidArgumentException('No RevokePrivileges forms are defined for the selected grammar.');
            }
            $operands = Generators::oneOf(...$forms);
        }
        $this->delegate = Generators::map(static function (\SqlSemantics\Statement\AnalyzedStatement $statement): RevokePrivileges {
            if (!$statement instanceof RevokePrivileges) {
                throw new LogicException('Publishing RevokePrivileges must preserve its concrete result type.');
            }
            return $statement;
        }, new \Tests\Generator\Semantics\Statement\AnalyzedStatementGenerator($release, $operands, $contexts));
    }
    /**
     * Composes column privileges, wildcard levels and independent recipient names.
     *
     * @return ErisGenerator<RevokePrivileges>
     */
    public static function columnTargets(): ErisGenerator
    {
        $names = new \Tests\Generator\Semantics\Statement\Identifier\NameGenerator();
        $columns = Generators::bind(Generators::choose(1, 4), static fn (int $count): ErisGenerator => Generators::vector($count, $names));
        $privilege = new Item\StaticPrivilegeGenerator(Generators::elements(M\Account\Privilege\Item\PrivilegeKind::Select, M\Account\Privilege\Item\PrivilegeKind::Insert, M\Account\Privilege\Item\PrivilegeKind::Update, M\Account\Privilege\Item\PrivilegeKind::References), $columns);
        $privileges = Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => Generators::vector($count, $privilege));
        $levels = Generators::oneOf(
            new Level\CurrentDatabaseLevelGenerator(),
            new Level\GlobalLevelGenerator(),
            new Level\DatabaseLevelGenerator($names),
            new Level\ObjectLevelGenerator(new \Tests\Generator\Semantics\Statement\Identifier\QualifiedNameGenerator($names, Generators::constant(null), Generators::constant(null)))
        );
        $account = new \Tests\Generator\MySql\Statement\Account\User\UserSpecificationGenerator(new AccountNameGenerator(Generators::map(static fn (int $length): string => str_repeat('u', $length), Generators::choose(1, 70))));
        $accounts = Generators::bind(Generators::choose(1, 3), static fn (int $count): ErisGenerator => Generators::vector($count, $account));
        return Generators::map(static fn (array $parts): RevokePrivileges => new RevokePrivileges(false, $parts[0], M\Account\Privilege\Level\ObjectKind::Table, $parts[1], $parts[2]), Generators::tuple($privileges, $levels, $accounts));
    }

    /**
     * Composes the written operands through Eris generation and shrinking.
     * @return ErisGenerator<RevokePrivileges>
     */
    public static function operands(): ErisGenerator
    {
        $operands = Generators::associative(['account' => new AccountNameGenerator(), 'kind' => Generators::elements(M\Account\Privilege\Level\ObjectKind::Function, M\Account\Privilege\Level\ObjectKind::Procedure)]);
        return Generators::map(static function (array $data): RevokePrivileges {
            if (!$data['account'] instanceof M\Name\AccountName || !$data['kind'] instanceof M\Account\Privilege\Level\ObjectKind) {
                throw new LogicException('Revocation operands require an account and object kind.');
            }
            return new RevokePrivileges(false, [new M\Account\Privilege\Item\AllPrivileges()], $data['kind'], new M\Account\Privilege\Level\CurrentDatabaseLevel(), [new M\Account\User\UserSpecification($data['account'])]);
        }, $operands);
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<RevokePrivileges>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<RevokePrivileges>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<bool> $ifExists The generated semantic operand
     * @param ErisGenerator<list<M\Account\Privilege\Item\Privilege>> $privileges The generated semantic operand
     * @param ErisGenerator<M\Account\Privilege\Level\ObjectKind> $kind The generated semantic operand
     * @param ErisGenerator<M\Account\Privilege\Level\PrivilegeLevel> $level The generated semantic operand
     * @param ErisGenerator<list<M\Account\User\UserSpecification>> $users The generated semantic operand
     * @param ErisGenerator<bool> $ignoreUnknownUser The generated semantic operand
     * @param ErisGenerator<bool>|null $tableKeyword The optional table keyword
     * @return ErisGenerator<RevokePrivileges>
     */
    public static function fromOperands(ErisGenerator $ifExists, ErisGenerator $privileges, ErisGenerator $kind, ErisGenerator $level, ErisGenerator $users, ErisGenerator $ignoreUnknownUser, ?ErisGenerator $tableKeyword = null): ErisGenerator
    {
        return Generators::map(static fn (array $parts): RevokePrivileges => new RevokePrivileges(ifExists: $parts[0], privileges: $parts[1], kind: $parts[2], level: $parts[3], users: $parts[4], ignoreUnknownUser: $parts[5], tableKeyword: $parts[6] && $parts[2] === M\Account\Privilege\Level\ObjectKind::Table), Generators::tuple($ifExists, $privileges, $kind, $level, $users, $ignoreUnknownUser, $tableKeyword ?? Generators::bool()));
    }

    /**
     * Composes the statement's MySQL 8.4 forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<RevokePrivileges>>
     */
    public static function forms(): iterable
    {
        yield 'RevokePrivileges/396' => RevokePrivilegesGenerator::fromOperands(
            ifExists: Generators::constant(false),
            privileges: Generators::tuple(
                new Item\PrivilegeGenerator([new Item\StaticPrivilegeGenerator(
                    kind: Item\PrivilegeKindGenerator::selecting([M\Account\Privilege\Item\PrivilegeKind::Delete, M\Account\Privilege\Item\PrivilegeKind::ShowDatabases]),
                    columns: Generators::constant([])
                )])
            ),
            kind: Level\ObjectKindGenerator::selecting([M\Account\Privilege\Level\ObjectKind::Table]),
            level: new Level\PrivilegeLevelGenerator([new Level\CurrentDatabaseLevelGenerator()]),
            users: Generators::tuple(
                new \Tests\Generator\MySql\Statement\Account\User\UserSpecificationGenerator(
                    user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([M\Expression\OptionalWords::Omitted])
                    )]),
                    identification: Generators::constant(null),
                    factors: Generators::constant([]),
                    initial: Generators::constant(null),
                    replace: Generators::constant(null),
                    retainCurrent: Generators::constant(false),
                    discardOld: Generators::constant(false)
                )
            ),
            ignoreUnknownUser: Generators::constant(false)
        );
        yield 'RevokePrivileges/397' => RevokePrivilegesGenerator::fromOperands(
            ifExists: Generators::constant(false),
            privileges: Generators::tuple(
                new Item\PrivilegeGenerator([new Item\AllPrivilegesGenerator()])
            ),
            kind: Level\ObjectKindGenerator::selecting([M\Account\Privilege\Level\ObjectKind::Table]),
            level: new Level\PrivilegeLevelGenerator([new Level\CurrentDatabaseLevelGenerator()]),
            users: Generators::tuple(
                new \Tests\Generator\MySql\Statement\Account\User\UserSpecificationGenerator(
                    user: new \Tests\Generator\MySql\Statement\Name\AccountGenerator([\Tests\Generator\MySql\Statement\Name\CurrentUserGenerator::fromOperands(
                        parentheses: \Tests\Generator\MySql\Statement\Expression\OptionalWordsGenerator::selecting([M\Expression\OptionalWords::Omitted])
                    )]),
                    identification: Generators::constant(null),
                    factors: Generators::constant([]),
                    initial: Generators::constant(null),
                    replace: Generators::constant(null),
                    retainCurrent: Generators::constant(false),
                    discardOld: Generators::constant(false)
                )
            ),
            ignoreUnknownUser: Generators::constant(false)
        );
    }

}
