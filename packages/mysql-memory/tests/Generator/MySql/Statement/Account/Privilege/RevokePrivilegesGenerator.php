<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Privilege;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
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
     * Composes the written operands through Eris generation and shrinking.
     */
    public function __construct()
    {
        $operands = Generators::associative(['account' => new AccountNameGenerator(), 'kind' => Generators::elements(M\Account\Privilege\Level\ObjectKind::Function, M\Account\Privilege\Level\ObjectKind::Procedure)]);
        $this->delegate = Generators::map(
            static function (array $data): RevokePrivileges {
                if (!$data['account'] instanceof M\Name\AccountName || !$data['kind'] instanceof M\Account\Privilege\Level\ObjectKind) {
                    throw new LogicException('Revocation operands require an account and object kind.');
                }
                return new RevokePrivileges(false, [new M\Account\Privilege\Item\AllPrivileges()], $data['kind'], new M\Account\Privilege\Level\CurrentDatabaseLevel(), [new M\Account\User\UserSpecification($data['account'])]);
            },
            $operands,
        );
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
     * @return ErisGenerator<RevokePrivileges>
     */
    public static function fromOperands(ErisGenerator $ifExists, ErisGenerator $privileges, ErisGenerator $kind, ErisGenerator $level, ErisGenerator $users, ErisGenerator $ignoreUnknownUser): ErisGenerator
    {
        return Generators::map(static fn (array $parts): RevokePrivileges => new RevokePrivileges(ifExists: $parts[0], privileges: $parts[1], kind: $parts[2], level: $parts[3], users: $parts[4], ignoreUnknownUser: $parts[5]), \Tests\Generator\Operands::tuple($ifExists, $privileges, $kind, $level, $users, $ignoreUnknownUser));
    }

    /**
     * Composes the statement's SQL forms from typed operand generators.
     *
     * @return iterable<string, ErisGenerator<RevokePrivileges>>
     */
    public static function forms(): iterable
    {
        yield 'RevokePrivileges/396' => RevokePrivilegesGenerator::fromOperands(
            ifExists: Generators::constant(false),
            privileges: \Tests\Generator\Operands::tuple(
                new Item\PrivilegeGenerator([new Item\StaticPrivilegeGenerator(
                    kind: Item\PrivilegeKindGenerator::selecting([M\Account\Privilege\Item\PrivilegeKind::Delete]),
                    columns: Generators::constant([])
                )])
            ),
            kind: Level\ObjectKindGenerator::selecting([M\Account\Privilege\Level\ObjectKind::Table]),
            level: new Level\PrivilegeLevelGenerator([new Level\CurrentDatabaseLevelGenerator()]),
            users: \Tests\Generator\Operands::tuple(
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
            privileges: \Tests\Generator\Operands::tuple(
                new Item\PrivilegeGenerator([new Item\AllPrivilegesGenerator()])
            ),
            kind: Level\ObjectKindGenerator::selecting([M\Account\Privilege\Level\ObjectKind::Table]),
            level: new Level\PrivilegeLevelGenerator([new Level\CurrentDatabaseLevelGenerator()]),
            users: \Tests\Generator\Operands::tuple(
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
