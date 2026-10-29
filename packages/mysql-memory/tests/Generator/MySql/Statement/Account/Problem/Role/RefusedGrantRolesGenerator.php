<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Problem\Role;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\Role\RefusedGrantRoles;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use SqlSemantics\Platform\MySql\Statement\Name\CurrentUser;
use Tests\Generator\MySql\Statement\Name\AccountNameGenerator;

/**
 * Generates anonymous grants and explicit self-grants with their intrinsic condition.
 *
 * @implements ErisGenerator<RefusedGrantRoles>
 */
final class RefusedGrantRolesGenerator implements ErisGenerator
{
    /**
     * @var ErisGenerator<RefusedGrantRoles>
     */
    private ErisGenerator $delegate;

    /**
     * Composes operands with Eris, retaining their relationships during shrinking.
     */
    public function __construct()
    {
        $this->delegate = Generators::oneOf(
            Generators::map(static fn (AccountName $role): RefusedGrantRoles => new RefusedGrantRoles([new GrantedRole($role)], [new CurrentUser()]), new AccountNameGenerator(Generators::elements('', ' ', "\t"))),
            Generators::map(static fn (AccountName $role): RefusedGrantRoles => new RefusedGrantRoles([new GrantedRole($role)], [new AccountName($role->user, $role->host)]), new AccountNameGenerator()),
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<RefusedGrantRoles>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<RefusedGrantRoles>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<list<GrantedRole>> $roles The generated semantic operand
     * @param ErisGenerator<list<\SqlSemantics\Platform\MySql\Statement\Name\Account>> $users The generated semantic operand
     * @return ErisGenerator<RefusedGrantRoles>
     */
    public static function fromOperands(ErisGenerator $roles, ErisGenerator $users): ErisGenerator
    {
        return Generators::map(static fn (array $parts): RefusedGrantRoles => new RefusedGrantRoles(roles: $parts[0], users: $parts[1]), Generators::tuple($roles, $users));
    }


}
