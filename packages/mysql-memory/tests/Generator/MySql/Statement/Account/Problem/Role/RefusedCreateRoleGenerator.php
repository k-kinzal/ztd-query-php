<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Problem\Role;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Platform\MySql\Statement\Account\Problem\Role\RefusedCreateRole;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use Tests\Generator\MySql\Statement\Name\AccountNameGenerator;

/**
 * Generates anonymous or duplicate role definitions with their intrinsic condition.
 *
 * @implements ErisGenerator<RefusedCreateRole>
 */
final class RefusedCreateRoleGenerator implements ErisGenerator
{
    /**
     * @var ErisGenerator<RefusedCreateRole>
     */
    private ErisGenerator $delegate;

    /**
     * Composes operands with Eris, retaining their relationships during shrinking.
     */
    public function __construct()
    {
        $this->delegate = Generators::oneOf(
            Generators::map(static fn (AccountName $role): RefusedCreateRole => new RefusedCreateRole(false, [$role]), new AccountNameGenerator(Generators::elements('', ' ', "\t"))),
            Generators::map(static fn (AccountName $role): RefusedCreateRole => new RefusedCreateRole(false, [$role, new AccountName($role->user, $role->host)]), new AccountNameGenerator()),
        );
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<RefusedCreateRole>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<RefusedCreateRole>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<bool> $ifNotExists The generated semantic operand
     * @param ErisGenerator<list<AccountName>> $roles The generated semantic operand
     * @return ErisGenerator<RefusedCreateRole>
     */
    public static function fromOperands(ErisGenerator $ifNotExists, ErisGenerator $roles): ErisGenerator
    {
        return Generators::map(static fn (array $parts): RefusedCreateRole => new RefusedCreateRole(ifNotExists: $parts[0], roles: $parts[1]), \Tests\Generator\Operands::tuple($ifNotExists, $roles));
    }


}
