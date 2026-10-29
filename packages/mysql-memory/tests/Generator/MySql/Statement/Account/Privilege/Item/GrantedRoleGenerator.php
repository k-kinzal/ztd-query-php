<?php

declare(strict_types=1);

namespace Tests\Generator\MySql\Statement\Account\Privilege\Item;

use Eris\Generator as ErisGenerator;
use Eris\Generator\GeneratedValue;
use Eris\Generator\GeneratedValueSingle;
use Eris\Generators;
use Eris\Random\RandomRange;
use SqlSemantics\Platform\MySql\Statement\Account\Privilege\Item\GrantedRole;
use SqlSemantics\Platform\MySql\Statement\Name\AccountName;
use Tests\Generator\MySql\Statement\Name\AccountNameGenerator;

/**
 * Generates a named or anonymous role operand without requiring an account catalog.
 *
 * @implements ErisGenerator<GrantedRole>
 */
final class GrantedRoleGenerator implements ErisGenerator
{
    /**
     * @var ErisGenerator<GrantedRole>
     */
    private ErisGenerator $delegate;

    /**
     * Composes operands with Eris, retaining their relationships during shrinking.
     */
    public function __construct()
    {
        $this->delegate = Generators::map(static fn (AccountName $role): GrantedRole => new GrantedRole($role), Generators::oneOf(new AccountNameGenerator(), new AccountNameGenerator(Generators::elements('', ' ', "\t"))));
    }

    /**
     * @param int $size The size selected by Eris
     * @return GeneratedValueSingle<GrantedRole>
     */
    public function __invoke($size, RandomRange $rand): GeneratedValueSingle
    {
        return ($this->delegate)($size, $rand);
    }

    /**
     * @param GeneratedValue<mixed> $element The generated counterexample
     * @return GeneratedValue<GrantedRole>
     */
    public function shrink(GeneratedValue $element): GeneratedValue
    {
        return $this->delegate->shrink($element);
    }
    /**
     * Composes all semantic operands, including spellings outside the convenience defaults.
     *
     * @param ErisGenerator<AccountName> $role The generated semantic operand
     * @return ErisGenerator<GrantedRole>
     */
    public static function fromOperands(ErisGenerator $role): ErisGenerator
    {
        return Generators::map(static fn (array $parts): GrantedRole => new GrantedRole(role: $parts[0]), Generators::tuple($role));
    }

}
