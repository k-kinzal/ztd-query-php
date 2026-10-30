<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\User;

use Eris\Generator as ErisGenerator;
use Eris\Generators;
use Tests\Generator\Composition;

/**
 * Composes RoleSelection values and preserves their operand generators when shrinking.
 *
 * @implements ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\User\RoleSelection>
 */
final class RoleSelectionGenerator implements ErisGenerator
{
    /** @use Composition<\SqlSemantics\Platform\MySql\Statement\Account\User\RoleSelection> */
    use Composition;

    /**
     * @param ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\User\RoleSet> $set Generated constructor operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Name\AccountName>>|null $roles Generated constructor operand
     * @param ErisGenerator<\SqlSemantics\Statement\Source\Span|null>|null $inputSource Generated constructor operand
     */
    public function __construct(ErisGenerator $set, ?ErisGenerator $roles = null, ?ErisGenerator $inputSource = null)
    {
        $this->delegate = Generators::map(static function (array $parts): \SqlSemantics\Platform\MySql\Statement\Account\User\RoleSelection {
            return new \SqlSemantics\Platform\MySql\Statement\Account\User\RoleSelection(set: $parts[0], roles: $parts[1], inputSource: $parts[2]);
        }, Generators::tuple(
            $set,
            $roles ?? Generators::constant([]),
            $inputSource ?? Generators::constant(null)
        ));
    }

    /**
     * Generates named roles or ALL/NONE without mixing incompatible operands.
     *
     * @param ErisGenerator<non-empty-list<\SqlSemantics\Platform\MySql\Statement\Name\AccountName>>|null $names Related role identities, when prerequisites have generated them
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\User\RoleSelection>
     */
    public static function defaultRoles(?ErisGenerator $names = null): ErisGenerator
    {
        $names ??= Generators::bind(Generators::choose(1, 4), static fn (int $count): ErisGenerator => Generators::vector($count, new \Tests\Generator\MySql\Statement\Name\AccountNameGenerator()));
        return Generators::oneOf(
            new self(RoleSetGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\User\RoleSet::Named]), $names),
            new self(RoleSetGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\User\RoleSet::All, \SqlSemantics\Platform\MySql\Statement\Account\User\RoleSet::None]))
        );
    }

    /**
     * Includes DEFAULT and varying ALL EXCEPT lists for session activation.
     *
     * @return ErisGenerator<\SqlSemantics\Platform\MySql\Statement\Account\User\RoleSelection>
     */
    public static function sessionRoles(): ErisGenerator
    {
        return Generators::oneOf(
            self::defaultRoles(),
            new self(RoleSetGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\User\RoleSet::Default])),
            new self(RoleSetGenerator::selecting([\SqlSemantics\Platform\MySql\Statement\Account\User\RoleSet::All]), Generators::seq(new \Tests\Generator\MySql\Statement\Name\AccountNameGenerator()))
        );
    }
}
